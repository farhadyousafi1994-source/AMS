<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\CashAdvance;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\SiteInvoice;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Field purchase requests: supervisor raises → engineer approves/rejects →
 * office releases a cash advance → supervisor uploads the receipt → reconcile →
 * close. A request cannot close without a receipt (hard block, confirmed policy).
 */
class PurchaseRequestController extends Controller
{
    use CompressesImages;

    public function index(Request $request): JsonResponse
    {
        $rows = PurchaseRequest::query()
            ->forUser($request->user())
            ->with(['project:id,name,code', 'category:id,name', 'supervisor:id,name', 'approver:id,name'])
            ->withSum('advances as advanced_total', 'amount_given')
            ->withSum('invoices as spent_total', 'actual_total')
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->input('project_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->boolean('mine'), fn ($q) => $q->where('user_id', $request->user()->id))
            ->orderByDesc('id')
            ->get();

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'category_id' => ['nullable', 'integer', 'exists:purchase_categories,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.est_price' => ['nullable', 'numeric', 'min:0'],
            'estimated_total' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'note' => ['nullable', 'string'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['status'] = 'pending';
        $data['code'] = $this->nextCode();

        $project = Project::findOrFail($data['project_id']);

        // Petty-cash: a request at/under the project's limit auto-approves and
        // skips the engineer. limit 0 means every purchase needs approval.
        $limit = (float) ($project->petty_cash_limit ?? 0);
        if ($limit > 0 && (float) ($data['estimated_total'] ?? 0) <= $limit) {
            $data['status'] = 'approved';
            $data['approver_id'] = $request->user()->id;
            $data['decided_at'] = now();
            $data['decision_note'] = 'Auto-approved (under petty-cash limit)';
        }

        $pr = PurchaseRequest::create($data);

        ActivityLog::log('created', 'PurchaseRequest', "Raised purchase request {$pr->code}", $pr->project_id);

        return response()->json($this->hydrate($pr), 201);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeProject($request, $purchaseRequest);

        return response()->json($this->hydrate($purchaseRequest));
    }

    /** Engineer/admin approves or rejects. */
    public function decide(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeProject($request, $purchaseRequest);
        abort_unless($request->user()->can('purchase-approve'), 403, 'You cannot approve or reject requests.');
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_note' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless($purchaseRequest->status === 'pending', 422, 'Only a pending request can be decided.');

        $purchaseRequest->update([
            'status' => $data['decision'],
            'approver_id' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);

        ActivityLog::log('updated', 'PurchaseRequest', ucfirst($data['decision'])." purchase request {$purchaseRequest->code}", $purchaseRequest->project_id);

        return response()->json($this->hydrate($purchaseRequest));
    }

    /** Office releases cash against an approved request. */
    public function releaseAdvance(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeProject($request, $purchaseRequest);
        abort_unless($request->user()->can('cash-release'), 403, 'You cannot release cash advances.');
        $data = $request->validate([
            'amount_given' => ['required', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        abort_unless(in_array($purchaseRequest->status, ['approved', 'purchased'], true), 422, 'Cash is released only for approved requests.');

        CashAdvance::create([
            'purchase_request_id' => $purchaseRequest->id,
            'amount_given' => $data['amount_given'],
            'currency' => $purchaseRequest->currency,
            'given_by' => $request->user()->id,
            'given_at' => now(),
            'note' => $data['note'] ?? null,
        ]);

        ActivityLog::log('created', 'PurchaseRequest', "Released {$data['amount_given']} {$purchaseRequest->currency} advance for {$purchaseRequest->code}", $purchaseRequest->project_id);

        return response()->json($this->hydrate($purchaseRequest->fresh()));
    }

    /** Supervisor uploads the receipt: creates the archive entry + marks purchased. */
    public function uploadReceipt(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeProject($request, $purchaseRequest);
        $data = $request->validate([
            'vendor' => ['nullable', 'string', 'max:255'],
            'actual_total' => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:purchase_categories,id'],
            'invoice_date' => ['nullable', 'date'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        abort_if(in_array($purchaseRequest->status, ['pending', 'rejected'], true), 422, 'Approve the request before uploading a receipt.');

        $file = $request->file('image');
        [$imagePath, $imageMime] = $this->storeCompressed($file, 'site-invoices/'.Tenant::id().'/'.$purchaseRequest->project_id);
        $inv = SiteInvoice::create([
            'project_id' => $purchaseRequest->project_id,
            'purchase_request_id' => $purchaseRequest->id,
            'category_id' => $data['category_id'] ?? $purchaseRequest->category_id,
            'source' => 'purchase',
            'vendor' => $data['vendor'] ?? null,
            'actual_total' => $data['actual_total'],
            'currency' => $purchaseRequest->currency,
            'image_path' => $imagePath,
            'image_name' => $file->getClientOriginalName(),
            'image_mime' => $imageMime,
            'uploaded_by' => $request->user()->id,
            'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
        ]);

        if ($purchaseRequest->status === 'approved') {
            $purchaseRequest->update(['status' => 'purchased']);
        }

        ActivityLog::log('created', 'SiteInvoice', "Uploaded receipt {$data['actual_total']} {$purchaseRequest->currency} for {$purchaseRequest->code}", $purchaseRequest->project_id);

        return response()->json(['request' => $this->hydrate($purchaseRequest->fresh()), 'invoice' => $inv], 201);
    }

    /** Close-out: hard block if no receipt exists (confirmed policy). */
    public function close(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeProject($request, $purchaseRequest);
        abort_unless($purchaseRequest->invoices()->exists(), 422, 'Cannot close: at least one receipt must be uploaded first.');
        abort_if($purchaseRequest->status === 'closed', 422, 'Already closed.');

        $purchaseRequest->update(['status' => 'closed']);

        ActivityLog::log('updated', 'PurchaseRequest', "Closed purchase request {$purchaseRequest->code}", $purchaseRequest->project_id);

        return response()->json($this->hydrate($purchaseRequest));
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorizeProject($request, $purchaseRequest);
        $code = $purchaseRequest->code;
        $purchaseRequest->delete();

        ActivityLog::log('deleted', 'PurchaseRequest', "Deleted purchase request {$code}", $purchaseRequest->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    /** Field users may only touch their assigned projects. */
    private function authorizeProject(Request $request, PurchaseRequest $pr): void
    {
        $user = $request->user();
        if ($user->is_super_admin || $user->type === 'admin') {
            return;
        }
        abort_unless(in_array($pr->project_id, $user->assignedProjectIds(), true), 403, 'This project is not assigned to you.');
    }

    private function hydrate(PurchaseRequest $pr): PurchaseRequest
    {
        $pr->load(['project:id,name,code', 'category:id,name', 'supervisor:id,name', 'approver:id,name', 'advances.giver:id,name', 'invoices.uploader:id,name']);
        $pr->setAttribute('advanced_total', $pr->advancedTotal());
        $pr->setAttribute('spent_total', $pr->spentTotal());
        $pr->setAttribute('reconcile_diff', round($pr->advancedTotal() - $pr->spentTotal(), 2));

        return $pr;
    }

    private function nextCode(): string
    {
        $n = PurchaseRequest::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'PR-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
