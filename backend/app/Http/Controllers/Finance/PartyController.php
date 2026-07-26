<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Party;
use App\Models\PartyTransaction;
use App\Models\TreasuryTransaction;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Party accounts (حسابات): everyone the company borrows from or lends to
 * outside the cap table. balance = confirmed in − confirmed out, in base
 * currency. Positive = credit (we owe them); negative = debit (they owe us).
 * Confirmed rows mirror into the General Budget; pending rows are promises.
 */
class PartyController extends Controller
{
    use CompressesImages;

    public function index(Request $request): JsonResponse
    {
        $query = Party::query()
            ->withCount('transactions')
            ->withSum(['transactions as in_total' => fn ($q) => $q->where('direction', 'in')->where('status', 'confirmed')], 'amount_base')
            ->withSum(['transactions as out_total' => fn ($q) => $q->where('direction', 'out')->where('status', 'confirmed')], 'amount_base')
            ->withSum(['transactions as pending_total' => fn ($q) => $q->where('status', 'pending')], 'amount_base');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $rows = $query->orderBy('name')->get()->map(function ($p) {
            $p->setAttribute('balance', round((float) ($p->in_total ?? 0) - (float) ($p->out_total ?? 0), 2));

            return $p;
        });

        // Per-currency buckets so mixed money is never shown as one bare number:
        // net (in − out) per party per original currency, split by sign.
        $weOweCur = [];
        $theyOweCur = [];
        $pendingCur = [];
        PartyTransaction::query()->get()->groupBy('party_id')->each(function ($txs) use (&$weOweCur, &$theyOweCur, &$pendingCur) {
            $net = [];
            foreach ($txs as $t) {
                if ($t->status === 'pending') {
                    $pendingCur[$t->currency] = round(($pendingCur[$t->currency] ?? 0) + (float) $t->amount, 2);

                    continue;
                }
                $net[$t->currency] = ($net[$t->currency] ?? 0) + ($t->direction === 'in' ? 1 : -1) * (float) $t->amount;
            }
            foreach ($net as $cur => $v) {
                if ($v > 0.009) {
                    $weOweCur[$cur] = round(($weOweCur[$cur] ?? 0) + $v, 2);
                } elseif ($v < -0.009) {
                    $theyOweCur[$cur] = round(($theyOweCur[$cur] ?? 0) - $v, 2);
                }
            }
        });

        $summary = [
            'base' => \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN',
            'we_owe' => round((float) $rows->where('balance', '>', 0)->sum('balance'), 2),
            'they_owe' => round((float) $rows->where('balance', '<', 0)->sum(fn ($p) => abs($p->balance)), 2),
            'pending' => round((float) $rows->sum(fn ($p) => (float) ($p->pending_total ?? 0)), 2),
            'parties' => $rows->count(),
            'currencies' => ['we_owe' => $weOweCur, 'they_owe' => $theyOweCur, 'pending' => $pendingCur],
        ];

        return response()->json(['summary' => $summary, 'parties' => $rows->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->partyRules($request);
        $data['code'] = 'PTY-'.str_pad((string) (Party::withTrashed()->where('company_id', Tenant::id())->count() + 1), 4, '0', STR_PAD_LEFT);

        $party = Party::create($data);

        ActivityLog::log('created', 'Party', "Added account for \"{$party->name}\"");

        return response()->json($party, 201);
    }

    /** Statement: every transaction plus a running balance, oldest first. */
    public function show(Party $party): JsonResponse
    {
        $txs = $party->transactions()->with(['project:id,name,code', 'user:id,name'])
            ->orderBy('tx_date')->orderBy('id')->get();

        $running = 0.0;
        $txs->each(function ($t) use (&$running) {
            if ($t->status === 'confirmed') {
                $running += ($t->direction === 'in' ? 1 : -1) * (float) $t->amount_base;
            }
            $t->setAttribute('running_balance', round($running, 2));
        });

        $party->setAttribute('balance', round($running, 2));
        $party->setAttribute('statement', $txs->sortByDesc('tx_date')->sortByDesc('id')->values());

        return response()->json($party);
    }

    public function update(Request $request, Party $party): JsonResponse
    {
        $party->update($this->partyRules($request));

        ActivityLog::log('updated', 'Party', "Updated account \"{$party->name}\"");

        return response()->json($party);
    }

    public function destroy(Party $party): JsonResponse
    {
        abort_if($party->transactions()->exists(), 422, 'This account has transactions — delete them first.');
        $name = $party->name;
        $party->delete();

        ActivityLog::log('deleted', 'Party', "Deleted account \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Ledger entries ──
    public function addTransaction(Request $request, Party $party): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'status' => ['nullable', 'in:confirmed,pending'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'tx_date' => ['required', 'date'],
            'method' => ['nullable', 'in:cash,bank,hawala,other'],
            'basis' => ['nullable', 'string', 'max:255'],
            'handled_by' => ['nullable', 'string', 'max:255'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'note' => ['nullable', 'string'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $data['party_id'] = $party->id;
        $data['user_id'] = $request->user()?->id;
        $data['status'] = $data['status'] ?? 'confirmed';
        $data['rate'] = $data['rate'] ?? $this->defaultRate($data['currency'] ?? 'AFN');
        $data['amount_base'] = round($data['amount'] * $data['rate'], 2);

        if ($file = $request->file('attachment')) {
            [$data['attachment_path'], $attachMime] = $this->storeCompressed($file, 'party-docs/'.Tenant::id().'/'.$party->id);
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_mime'] = $attachMime;
        }
        unset($data['attachment']);

        $tx = PartyTransaction::create($data);
        $this->syncTreasury($tx, $party);

        $verb = $tx->direction === 'in' ? 'Received' : 'Paid';
        ActivityLog::log('created', 'Party', "{$verb} {$tx->amount} {$tx->currency} ".($tx->direction === 'in' ? 'from' : 'to')." \"{$party->name}\"", $tx->project_id);

        return response()->json($tx->load(['project:id,name,code', 'user:id,name']), 201);
    }

    /** A promise arrived: mark it confirmed so it hits balances + treasury. */
    public function confirmTransaction(PartyTransaction $transaction): JsonResponse
    {
        $transaction->update(['status' => 'confirmed']);
        $this->syncTreasury($transaction, $transaction->party);

        ActivityLog::log('updated', 'Party', "Confirmed pending {$transaction->amount} {$transaction->currency} for \"{$transaction->party?->name}\"", $transaction->project_id);

        return response()->json($transaction);
    }

    public function deleteTransaction(PartyTransaction $transaction): JsonResponse
    {
        TreasuryTransaction::where('party_transaction_id', $transaction->id)->delete();
        $transaction->delete();

        ActivityLog::log('deleted', 'Party', 'Deleted a party-account transaction', $transaction->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * Project view of party money: who paid and who received inside one
     * project, with in/out/net totals in base currency (confirmed only).
     */
    public function projectTransactions(\App\Models\Project $project): JsonResponse
    {
        $txs = PartyTransaction::with(['party:id,name,code,type', 'user:id,name'])
            ->where('project_id', $project->id)
            ->orderByDesc('tx_date')->orderByDesc('id')->get();

        $confirmed = $txs->where('status', 'confirmed');
        $in = round((float) $confirmed->where('direction', 'in')->sum('amount_base'), 2);
        $out = round((float) $confirmed->where('direction', 'out')->sum('amount_base'), 2);

        return response()->json([
            'summary' => [
                'in' => $in,
                'out' => $out,
                'net' => round($in - $out, 2),
                'base' => \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN',
            ],
            'transactions' => $txs->values(),
        ]);
    }

    public function downloadAttachment(PartyTransaction $transaction): StreamedResponse
    {
        abort_unless($transaction->attachment_path && Storage::exists($transaction->attachment_path), 404, 'File not found');

        return Storage::download($transaction->attachment_path, $transaction->attachment_name);
    }

    /** Confirmed party cash is real General-Budget cash; keep one linked row. */
    private function syncTreasury(PartyTransaction $tx, Party $party): void
    {
        $existing = TreasuryTransaction::where('party_transaction_id', $tx->id)->first();

        if ($tx->status !== 'confirmed') {
            $existing?->delete();

            return;
        }

        $attrs = [
            'project_id' => $tx->project_id,
            'direction' => $tx->direction,
            'kind' => $tx->direction === 'in' ? 'loan_in' : 'loan_out',
            'status' => 'active',
            'amount' => $tx->amount,
            'currency' => $tx->currency,
            'rate' => $tx->rate,
            'amount_base' => $tx->amount_base,
            'tx_date' => $tx->tx_date,
            'note' => ($tx->direction === 'in' ? 'From' : 'To')." party account: {$party->name}",
        ];

        $existing ? $existing->update($attrs) : TreasuryTransaction::create($attrs + ['party_transaction_id' => $tx->id]);
    }

    /** When no rate is sent, lock today's daily rate for non-base currencies. */
    private function defaultRate(string $currency): float
    {
        $base = \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN';
        if ($currency === $base) {
            return 1;
        }

        return (float) (\App\Models\ExchangeRate::where('currency_code', $currency)
            ->orderByDesc('rate_date')->orderByDesc('id')->value('rate_to_base') ?? 1);
    }

    private function partyRules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'in:person,company,bank,exchange,relative,other'],
            'phone' => ['nullable', 'string', 'max:50'],
            'relation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);
    }
}
