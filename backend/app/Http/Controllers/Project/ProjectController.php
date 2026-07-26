<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Users without 'all-projects' see only the projects assigned to them.
        $ids = $request->user()->visibleProjectIds();

        return response()->json(
            Project::withCount(['sites', 'milestones'])
                ->with('branch:id,name')
                ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        if (empty($data['code'])) {
            $data['code'] = $this->nextCode();
        }

        $project = Project::create($data);

        // Make the creator a member of the project so they can see it even
        // without the company-wide 'all-projects' permission (otherwise a
        // freshly created project would be invisible on their dashboard, map
        // and project list, which are all scoped to visibleProjectIds()).
        $request->user()->projects()->syncWithoutDetaching([$project->id => ['site_role' => 'manager']]);

        ActivityLog::log('created', 'Project', "Created project \"{$project->name}\"", $project->id);

        return response()->json($project, 201);
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $ids = $request->user()->visibleProjectIds();
        abort_if($ids !== null && ! in_array($project->id, $ids, true), 403, 'This project is not assigned to you.');

        $project->syncProgress(); // keep the system-driven progress/status fresh
        $project->load(['branch:id,name', 'sites', 'milestones'])
            ->loadCount(['tasks', 'projectAssets', 'materials', 'investments']);
        $project->setAttribute('funding', $this->fundingSummary($project));

        return response()->json($project);
    }

    /**
     * Live cap-table roll-up in the project's own currency. capital and
     * profit_percent are summed independently — never derived from one another.
     */
    private function fundingSummary(Project $project): array
    {
        $rows = $project->investments()->get();

        // Budget vs actual: real spend from expenses, subcontractor payments
        // and received purchase orders tied to this project (base currency).
        // Guarded so a database that hasn't run the latest migrations yet
        // degrades to a partial figure instead of a 500.
        $actual = (float) \App\Models\Expense::where('project_id', $project->id)->sum('amount_base')
            + (float) \App\Models\SubcontractorPayment::where('project_id', $project->id)->sum('amount');
        if (\Illuminate\Support\Facades\Schema::hasTable('purchase_order_items')) {
            $actual += (float) \App\Models\PurchaseOrderItem::whereHas('purchaseOrder', fn ($q) => $q
                ->where('project_id', $project->id)->where('status', 'received'))->sum('line_total');
        }

        return [
            'target' => (float) $project->contract_value,
            'raised' => (float) $rows->sum(fn ($r) => (float) $r->capital * (float) $r->rate),
            'profit_allocated' => (float) $rows->sum('profit_percent'),
            'participants' => $rows->count(),
            'actual_cost' => round($actual, 2),
        ];
    }

    private function nextCode(): string
    {
        $n = Project::withTrashed()->where('company_id', \App\Support\Tenant::id())->count() + 1;

        return 'AHMZ-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    /** Prefill for the creation wizard — still editable, still auto if blank. */
    public function nextCodePreview(): JsonResponse
    {
        return response()->json(['code' => $this->nextCode()]);
    }

    /** Live feed for the project dashboard. */
    public function activity(Project $project): JsonResponse
    {
        return response()->json(
            \App\Models\ActivityLog::with('user:id,name')
                ->where('company_id', \App\Support\Tenant::id())
                ->where('project_id', $project->id)
                ->latest()->limit(30)->get()
        );
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $wasRunning = ! in_array($project->status, ['completed', 'handover'], true);
        $project->update($this->validated($request, $project));

        // Completion releases the project's reserved receipts into the
        // General Budget's available balance.
        if ($wasRunning && in_array($project->status, ['completed', 'handover'], true)) {
            \App\Models\TreasuryTransaction::where('project_id', $project->id)
                ->where('status', 'reserved')->update(['status' => 'active']);
        }

        ActivityLog::log('updated', 'Project', "Updated project \"{$project->name}\"", $project->id);

        return response()->json($project);
    }

    public function destroy(Project $project): JsonResponse
    {
        $name = $project->name;
        $project->delete();

        ActivityLog::log('deleted', 'Project', "Deleted project \"{$name}\"", $project->id);

        return response()->json(['message' => 'Deleted.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'code' => ['nullable', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'name_fa' => ['nullable', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            // Type & status come from the Options Registry now, so any managed
            // code is valid — no hard-coded enum.
            'type' => ['nullable', 'string', 'max:60'],
            'contract_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:60'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'description' => ['nullable', 'string'],
        ]);
    }
}
