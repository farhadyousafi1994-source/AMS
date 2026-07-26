<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PaymentApproval;
use App\Models\PaymentApprovalRule;
use App\Models\PaymentRequest;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Finance Officer's single Payment Center. Every module raises a request
 * here; a configurable multi-level approval workflow gates it; approved
 * requests are processed (paid) from one screen with method, reference,
 * optional fingerprint verification and a receipt.
 */
class PaymentCenterController extends Controller
{
    /** Filterable queue + headline stats. */
    public function index(Request $request): JsonResponse
    {
        $q = PaymentRequest::with(['project:id,name', 'requester:id,name', 'approvals'])
            ->when($request->filled('status') && $request->string('status') !== 'all', fn ($x) => $x->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($x) => $x->where('type', $request->string('type')))
            ->when($request->filled('priority'), fn ($x) => $x->where('priority', $request->string('priority')))
            ->when($request->filled('project_id'), fn ($x) => $x->where('project_id', $request->integer('project_id')))
            ->when($request->filled('from'), fn ($x) => $x->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($x) => $x->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('q'), function ($x) use ($request) {
                $n = '%'.$request->string('q').'%';
                $x->where(fn ($w) => $w->where('payee_name', 'like', $n)->orWhere('request_no', 'like', $n)->orWhere('notes', 'like', $n));
            })
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');

        return response()->json(['data' => $q->limit(300)->get(), 'stats' => $this->stats()]);
    }

    private function stats(): array
    {
        $rows = PaymentRequest::selectRaw('status, count(*) as c, sum(requested_amount * rate) as base')->groupBy('status')->get();
        $by = fn ($s) => $rows->firstWhere('status', $s);
        $overdue = PaymentRequest::whereIn('status', ['pending', 'approved'])->whereNotNull('needed_by')->where('needed_by', '<', now())->count();

        return [
            'pending' => ['count' => (int) ($by('pending')->c ?? 0), 'amount' => (float) ($by('pending')->base ?? 0)],
            'approved' => ['count' => (int) ($by('approved')->c ?? 0), 'amount' => (float) ($by('approved')->base ?? 0)],
            'paid' => ['count' => (int) ($by('paid')->c ?? 0), 'amount' => (float) ($by('paid')->base ?? 0)],
            'rejected' => ['count' => (int) ($by('rejected')->c ?? 0), 'amount' => (float) ($by('rejected')->base ?? 0)],
            'overdue' => $overdue,
        ];
    }

    public function show(PaymentRequest $paymentRequest): JsonResponse
    {
        return response()->json($paymentRequest->load(['project:id,name', 'requester:id,name', 'approvals.approver:id,name']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'payee_name' => ['required', 'string', 'max:190'],
            'payee_type' => ['nullable', 'string', 'max:190'],
            'payee_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'requested_amount' => ['required', 'numeric', 'min:0.01'],
            'priority' => ['nullable', 'in:low,normal,high,urgent'],
            'source_module' => ['nullable', 'string', 'max:60'],
            'source_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string'],
            'needed_by' => ['nullable', 'date'],
        ]);
        $data['currency'] = $data['currency'] ?? 'AFN';
        $data['rate'] = $data['rate'] ?? 1;
        $data['priority'] = $data['priority'] ?? 'normal';
        $data['requested_by'] = $request->user()?->id;
        $data['request_no'] = $this->nextNo();

        $pr = PaymentRequest::create($data);
        $this->buildWorkflow($pr);
        ActivityLog::log('created', 'PaymentRequest', "Raised {$pr->request_no} — {$pr->payee_name} ({$pr->type})", $pr->project_id);

        return response()->json($pr->load('approvals'), 201);
    }

    /** Resolve the approval chain from the configurable rules for this request. */
    private function buildWorkflow(PaymentRequest $pr): void
    {
        $base = $pr->baseAmount();
        $rule = PaymentApprovalRule::where('active', true)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', $pr->type))
            ->where('min_amount', '<=', $base)
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $base))
            ->orderByRaw('CASE WHEN type IS NULL THEN 1 ELSE 0 END') // prefer type-specific rules
            ->orderByDesc('min_amount')
            ->first();

        $levels = $rule?->levels ?? [];
        if (empty($levels)) {
            // No approval required — immediately payable.
            $pr->update(['status' => 'approved', 'approved_amount' => $pr->requested_amount, 'current_level' => 1]);

            return;
        }
        foreach ($levels as $i => $role) {
            PaymentApproval::create([
                'company_id' => Tenant::id(),
                'payment_request_id' => $pr->id,
                'level' => $i + 1,
                'role' => $role,
                'status' => 'pending',
            ]);
        }
        $pr->update(['status' => 'pending', 'current_level' => 1]);
    }

    public function approve(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        abort_unless($paymentRequest->status === 'pending', 422, 'This request is not awaiting approval.');
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        $step = $paymentRequest->approvals()->where('level', $paymentRequest->current_level)->first();
        abort_unless($step, 422, 'No approval step found.');
        $step->update(['status' => 'approved', 'approver_id' => $request->user()?->id, 'note' => $note, 'decided_at' => now()]);

        $next = $paymentRequest->approvals()->where('level', '>', $paymentRequest->current_level)->orderBy('level')->first();
        if ($next) {
            $paymentRequest->update(['current_level' => $next->level]);
        } else {
            $paymentRequest->update(['status' => 'approved', 'approved_amount' => $paymentRequest->requested_amount]);
        }
        ActivityLog::log('updated', 'PaymentRequest', "Approved {$paymentRequest->request_no} (level {$step->level})", $paymentRequest->project_id);

        return response()->json($paymentRequest->fresh()->load('approvals.approver:id,name'));
    }

    public function reject(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        abort_unless(in_array($paymentRequest->status, ['pending', 'approved']), 422, 'This request cannot be rejected.');
        $note = $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null;

        $step = $paymentRequest->approvals()->where('level', $paymentRequest->current_level)->first();
        $step?->update(['status' => 'rejected', 'approver_id' => $request->user()?->id, 'note' => $note, 'decided_at' => now()]);
        $paymentRequest->update(['status' => 'rejected']);
        ActivityLog::log('updated', 'PaymentRequest', "Rejected {$paymentRequest->request_no}", $paymentRequest->project_id);

        return response()->json($paymentRequest->fresh()->load('approvals.approver:id,name'));
    }

    /** Finance Officer pays an approved request. */
    public function process(Request $request, PaymentRequest $paymentRequest): JsonResponse
    {
        abort_unless($paymentRequest->status === 'approved', 422, 'Only fully-approved requests can be paid.');
        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,bank,cheque,hawala'],
            'approved_amount' => ['nullable', 'numeric', 'min:0.01'],
            'reference' => ['nullable', 'string', 'max:120'],
            'fingerprint_verified' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);
        $paid = $data['approved_amount'] ?? $paymentRequest->approved_amount ?? $paymentRequest->requested_amount;

        $paymentRequest->update([
            'status' => 'paid',
            'payment_method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'approved_amount' => $paid,
            'paid_amount' => $paid,
            'fingerprint_verified' => (bool) ($data['fingerprint_verified'] ?? false),
            'notes' => $data['notes'] ?? $paymentRequest->notes,
            'paid_by' => $request->user()?->id,
            'paid_at' => now(),
        ]);
        ActivityLog::log('updated', 'PaymentRequest', "Paid {$paymentRequest->request_no} — {$paymentRequest->payee_name} via {$data['payment_method']}", $paymentRequest->project_id);

        return response()->json($paymentRequest->fresh()->load('approvals.approver:id,name'));
    }

    private function nextNo(): string
    {
        $n = PaymentRequest::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'PAY-'.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    // ── Configurable approval rules ───────────────────────────────────────
    public function rules(): JsonResponse
    {
        return response()->json(PaymentApprovalRule::orderBy('sort_order')->orderBy('name')->get());
    }

    public function storeRule(Request $request): JsonResponse
    {
        $rule = PaymentApprovalRule::create($this->ruleData($request));

        return response()->json($rule, 201);
    }

    public function updateRule(Request $request, PaymentApprovalRule $rule): JsonResponse
    {
        $rule->update($this->ruleData($request));

        return response()->json($rule);
    }

    public function destroyRule(PaymentApprovalRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    private function ruleData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:40'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'levels' => ['required', 'array', 'min:1'],
            'levels.*' => ['string', 'max:60'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }
}
