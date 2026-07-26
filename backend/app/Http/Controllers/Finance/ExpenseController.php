<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\TreasuryTransaction;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Expenses of three types: project | office | home. Project expenses keep their
 * original behaviour; office/home add payment method, vendor, approval and a
 * receipt attachment, and (when approved) post a linked withdrawal to Treasury.
 */
class ExpenseController extends Controller
{
    use CompressesImages;

    /** Which permission entity gates a given expense type. */
    private function permKey(string $type): string
    {
        return ['office' => 'office-expense', 'home' => 'home-expense'][$type] ?? 'expense';
    }

    public function index(Request $request): JsonResponse
    {
        $type = $request->input('type', 'project');
        $query = Expense::with(['project:id,name', 'user:id,name', 'approver:id,name'])
            ->where('type', $type)
            ->orderByDesc('expense_date')->orderByDesc('id');

        // Project expenses are scoped to the user's visible projects; office/home
        // expenses aren't project-bound so they stay under their own permissions.
        if ($type === 'project') {
            $ids = $request->user()->visibleProjectIds();
            $query->when($ids !== null, fn ($q) => $q->whereIn('project_id', $ids));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }
        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->string('approval_status'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['type'] = $data['type'] ?? 'project';
        abort_unless($request->user()->can($this->permKey($data['type']).'-create'), 403, 'Not allowed for this expense type.');

        $data['user_id'] = $request->user()->id;
        $data = $this->applyRateLock($data);
        $this->attachReceipt($request, $data);
        if (($data['approval_status'] ?? 'approved') === 'approved') {
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
        }

        $expense = Expense::create($data);
        $this->syncTreasury($expense);

        ActivityLog::log('created', 'Expense', ucfirst($expense->type)." expense {$expense->amount} {$expense->currency} ({$expense->category})", $expense->project_id);

        return response()->json($expense->load(['project:id,name', 'user:id,name', 'approver:id,name']), 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $data = $this->rules($request, $expense);
        abort_unless($request->user()->can($this->permKey($expense->type).'-edit'), 403, 'Not allowed.');

        $data = $this->applyRateLock($data);
        $this->attachReceipt($request, $data);
        $expense->update($data);
        $this->syncTreasury($expense->fresh());

        ActivityLog::log('updated', 'Expense', "Updated {$expense->type} expense #{$expense->id}", $expense->project_id);

        return response()->json($expense->load(['project:id,name', 'user:id,name', 'approver:id,name']));
    }

    /** Approve a pending office/home expense — posts the treasury withdrawal. */
    public function approve(Request $request, Expense $expense): JsonResponse
    {
        abort_unless($request->user()->can($this->permKey($expense->type).'-edit') || $request->user()->can('expense-approve'), 403, 'Not allowed.');
        $decision = $request->validate(['decision' => ['required', 'in:approved,rejected']])['decision'];

        $expense->update([
            'approval_status' => $decision,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);
        $this->syncTreasury($expense->fresh());

        ActivityLog::log('updated', 'Expense', ucfirst($decision)." {$expense->type} expense #{$expense->id}", $expense->project_id);

        return response()->json($expense);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        abort_unless($request->user()->can($this->permKey($expense->type).'-delete'), 403, 'Not allowed.');
        TreasuryTransaction::where('expense_id', $expense->id)->delete();
        $expense->delete();

        ActivityLog::log('deleted', 'Expense', "Deleted {$expense->type} expense #{$expense->id}", $expense->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    public function attachment(Expense $expense): StreamedResponse
    {
        abort_unless($expense->attachment_path && Storage::exists($expense->attachment_path), 404, 'No file');

        return Storage::download($expense->attachment_path, $expense->attachment_name);
    }

    // ── helpers ──
    private function attachReceipt(Request $request, array &$data): void
    {
        if ($file = $request->file('attachment')) {
            [$data['attachment_path'], $attachMime] = $this->storeCompressed($file, 'expense-docs/'.Tenant::id().'/'.($data['type'] ?? 'project'));
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_mime'] = $attachMime;
        }
        unset($data['attachment']);
    }

    /** Approved office/home expenses are real cash out of the General Budget. */
    private function syncTreasury(Expense $expense): void
    {
        $existing = TreasuryTransaction::where('expense_id', $expense->id)->first();

        if (! in_array($expense->type, ['office', 'home'], true) || $expense->approval_status !== 'approved') {
            $existing?->delete();

            return;
        }

        $attrs = [
            'project_id' => null,
            'direction' => 'out',
            'kind' => 'withdrawal',
            'status' => 'active',
            'amount' => $expense->amount,
            'currency' => $expense->currency,
            'rate' => $expense->rate,
            'amount_base' => $expense->amount_base,
            'tx_date' => $expense->expense_date,
            'note' => ucfirst($expense->type)." expense: {$expense->category}".($expense->vendor ? " — {$expense->vendor}" : ''),
        ];

        $existing ? $existing->update($attrs) : TreasuryTransaction::create($attrs + ['expense_id' => $expense->id]);
    }

    private function applyRateLock(array $data): array
    {
        $base = Currency::where('is_base', true)->value('code') ?? 'AFN';
        $rate = ($data['currency'] === $base) ? 1 : (float) ($data['rate'] ?? 1);
        $data['rate'] = $rate;
        $data['amount_base'] = round(((float) $data['amount']) * $rate, 2);

        return $data;
    }

    private function rules(Request $request, ?Expense $expense = null): array
    {
        return $request->validate([
            'type' => ['nullable', 'in:project,office,home'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'expense_date' => ['required', 'date'],
            'category' => ['required', 'string', 'max:100'],
            'payee' => ['nullable', 'string', 'max:255'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'payment_method' => ['nullable', 'in:cash,bank,hawala,card,other'],
            'approval_status' => ['nullable', 'in:pending,approved,rejected'],
            'description' => ['nullable', 'string'],
            'currency' => ['required', 'string', 'max:10'],
            'amount' => ['required', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
    }
}
