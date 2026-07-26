<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shareholder (partner) equity. The four owners each hold share_percent of the
 * company. Their combined available balances always equal the General Budget's
 * available cash: each owner's share of the earned pool, plus their own
 * deposits, minus their own withdrawals. Deposits and withdrawals move real cash
 * in/out of the General Budget, so a withdrawal is drawn from there.
 */
class ShareholderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->snapshot($request));
    }

    public function show(Request $request, Partner $partner): JsonResponse
    {
        $snapshot = $this->snapshot($request);
        $me = collect($snapshot['shareholders'])->firstWhere('id', $partner->id);

        return response()->json([
            'shareholder' => $me,
            'company' => $snapshot['company'],
            'transactions' => $partner->transactions()->with('creator:id,name')->get(),
        ]);
    }

    /** Combined feed of every shareholder's deposits & withdrawals (transparency). */
    public function activity(): JsonResponse
    {
        $rows = PartnerTransaction::with(['partner:id,name', 'creator:id,name'])
            ->orderByDesc('tx_date')->orderByDesc('id')->limit(100)->get();

        return response()->json($rows);
    }

    public function deposit(Request $request, Partner $partner): JsonResponse
    {
        return $this->record($request, $partner, 'deposit');
    }

    public function withdraw(Request $request, Partner $partner): JsonResponse
    {
        return $this->record($request, $partner, 'withdrawal');
    }

    /** Build the full equity snapshot for every shareholder + the company pool. */
    private function snapshot(Request $request): array
    {
        $treasury = TreasuryTransaction::summary();
        $pool = (float) $treasury['available'];                 // General Budget available cash
        $base = $treasury['base'];

        $deposits = (float) PartnerTransaction::where('type', 'deposit')->sum('amount_base');
        $withdrawals = (float) PartnerTransaction::where('type', 'withdrawal')->sum('amount_base');

        // Earned pool that is actually being shared (back out partner capital moves).
        $earned = round($pool - $deposits + $withdrawals, 2);

        $sharedExpenses = (float) Expense::whereIn('type', ['office', 'home'])->sum('amount_base');

        $byPartner = PartnerTransaction::select('partner_id', 'type', DB::raw('SUM(amount_base) as total'))
            ->groupBy('partner_id', 'type')->get()
            ->groupBy('partner_id');

        $meUserId = $request->user()?->id;

        $shareholders = Partner::where('active', true)->orderBy('id')->get()->map(function (Partner $p) use ($earned, $sharedExpenses, $byPartner, $base, $meUserId) {
            $share = (float) $p->share_percent / 100;
            $mine = $byPartner[$p->id] ?? collect();
            $dep = (float) ($mine->firstWhere('type', 'deposit')->total ?? 0);
            $wd = (float) ($mine->firstWhere('type', 'withdrawal')->total ?? 0);

            $entitlement = round($share * $earned, 2);

            return [
                'id' => $p->id,
                'name' => $p->name,
                'share_percent' => (float) $p->share_percent,
                'user_id' => $p->user_id,
                'is_me' => $meUserId !== null && $p->user_id === $meUserId,
                'profit_share' => $entitlement,            // 25% of the earned pool
                'deposits' => round($dep, 2),
                'withdrawals' => round($wd, 2),
                'expense_share' => round($share * $sharedExpenses, 2),   // their share of home/office costs
                'available' => round($entitlement + $dep - $wd, 2),      // what they can still withdraw
                'base' => $base,
            ];
        })->values();

        return [
            'company' => [
                'base' => $base,
                'available_pool' => round($pool, 2),        // General Budget available
                'earned_pool' => $earned,                    // profit being shared
                'total_deposits' => round($deposits, 2),
                'total_withdrawals' => round($withdrawals, 2),
                'shared_expenses' => round($sharedExpenses, 2),
            ],
            'shareholders' => $shareholders,
        ];
    }

    private function record(Request $request, Partner $partner, string $type): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'tx_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        $base = Currency::where('is_base', true)->value('code') ?? 'AFN';
        $currency = $data['currency'] ?? $base;
        $rate = $data['rate'] ?? ($currency === $base ? 1
            : (float) (ExchangeRate::where('currency_code', $currency)->orderByDesc('rate_date')->orderByDesc('id')->value('rate_to_base') ?? 1));
        $amountBase = round($data['amount'] * $rate, 2);
        $date = $data['tx_date'] ?? now()->toDateString();

        if ($type === 'withdrawal') {
            $snapshot = $this->snapshot($request);
            $me = collect($snapshot['shareholders'])->firstWhere('id', $partner->id);
            abort_if($amountBase > ($me['available'] ?? 0) + 0.01, 422,
                'Amount exceeds '.$partner->name."'s available balance ({$me['available']} {$base}).");
        }

        $result = DB::transaction(function () use ($partner, $type, $data, $currency, $rate, $amountBase, $date, $request) {
            $treasury = TreasuryTransaction::create([
                'direction' => $type === 'deposit' ? 'in' : 'out',
                'kind' => $type === 'deposit' ? 'deposit' : 'withdrawal',
                'status' => 'active',
                'amount' => $data['amount'],
                'currency' => $currency,
                'rate' => $rate,
                'amount_base' => $amountBase,
                'tx_date' => $date,
                'note' => 'Shareholder '.$type.': '.$partner->name.($data['note'] ? ' — '.$data['note'] : ''),
            ]);

            return PartnerTransaction::create([
                'partner_id' => $partner->id,
                'type' => $type,
                'amount' => $data['amount'],
                'currency' => $currency,
                'rate' => $rate,
                'amount_base' => $amountBase,
                'tx_date' => $date,
                'note' => $data['note'] ?? null,
                'treasury_transaction_id' => $treasury->id,
                'created_by' => $request->user()?->id,
            ]);
        });

        ActivityLog::log('created', 'Shareholder', ucfirst($type).' of '.$data['amount'].' '.$currency.' — '.$partner->name);

        return response()->json($result->load('creator:id,name'), 201);
    }
}
