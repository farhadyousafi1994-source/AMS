<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ChangeOrder;
use App\Models\Currency;
use App\Models\Project;
use App\Support\CompressesImages;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Change Orders (Variation Orders). Approving one revises the project's contract
 * value (original + sum of approved change orders), which flows straight into
 * the financing meters. Additions raise it, deductions lower it.
 */
class ChangeOrderController extends Controller
{
    use CompressesImages;

    public function index(Request $request): JsonResponse
    {
        $ids = $request->user()->visibleProjectIds();
        $rows = ChangeOrder::query()
            ->with(['project:id,name,code', 'requester:id,name', 'approver:id,name'])
            ->when($ids !== null, fn ($q) => $q->whereIn('project_id', $ids))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('id')->get();

        return response()->json(['change_orders' => $rows, 'summary' => $this->summary($rows)]);
    }

    /** A single project's change orders + revised contract figures. */
    public function projectIndex(Project $project): JsonResponse
    {
        $rows = ChangeOrder::where('project_id', $project->id)
            ->with(['requester:id,name', 'approver:id,name'])->orderByDesc('id')->get();

        $summary = $this->summary($rows) + [
            'original_contract' => (float) ($project->original_contract_value ?? $project->contract_value),
            'revised_contract' => (float) $project->contract_value,
        ];

        return response()->json(['change_orders' => $rows, 'summary' => $summary]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = $this->nextCode();
        $data['requested_by'] = $request->user()->id;
        $data['status'] = in_array($data['status'] ?? 'draft', ['draft', 'submitted'], true) ? ($data['status'] ?? 'draft') : 'draft';
        $data = $this->applyRateLock($data);
        $this->attach($request, $data);

        $co = ChangeOrder::create($data);
        ActivityLog::log('created', 'ChangeOrder', "Raised change order {$co->code} ({$co->kind})", $co->project_id);

        return response()->json($co->load(['project:id,name,code', 'requester:id,name']), 201);
    }

    public function update(Request $request, ChangeOrder $changeOrder): JsonResponse
    {
        abort_if($changeOrder->status === 'approved', 422, 'An approved change order cannot be edited.');
        $data = $this->rules($request);
        $data = $this->applyRateLock($data);
        $this->attach($request, $data);
        $changeOrder->update($data);

        ActivityLog::log('updated', 'ChangeOrder', "Updated change order {$changeOrder->code}", $changeOrder->project_id);

        return response()->json($changeOrder->fresh()->load(['project:id,name,code', 'requester:id,name']));
    }

    public function submit(ChangeOrder $changeOrder): JsonResponse
    {
        abort_unless($changeOrder->status === 'draft', 422, 'Only a draft can be submitted.');
        $changeOrder->update(['status' => 'submitted']);
        ActivityLog::log('updated', 'ChangeOrder', "Submitted change order {$changeOrder->code}", $changeOrder->project_id);

        return response()->json($changeOrder);
    }

    /** Owner decision. Approval revises the contract value. */
    public function decide(Request $request, ChangeOrder $changeOrder): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'decision_note' => ['nullable', 'string', 'max:255'],
        ]);
        abort_unless(in_array($changeOrder->status, ['submitted', 'draft'], true), 422, 'Already decided.');

        $changeOrder->update([
            'status' => $data['decision'],
            'approved_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['decision_note'] ?? null,
        ]);
        $this->reviseContract($changeOrder->project);

        ActivityLog::log('updated', 'ChangeOrder', ucfirst($data['decision'])." change order {$changeOrder->code}", $changeOrder->project_id);

        return response()->json($changeOrder->fresh());
    }

    public function destroy(ChangeOrder $changeOrder): JsonResponse
    {
        $project = $changeOrder->project;
        $code = $changeOrder->code;
        $changeOrder->delete();
        $this->reviseContract($project);   // roll back its effect if it was approved

        ActivityLog::log('deleted', 'ChangeOrder', "Deleted change order {$code}", $project?->id);

        return response()->json(['message' => 'Deleted.']);
    }

    public function attachment(ChangeOrder $changeOrder): StreamedResponse
    {
        abort_unless($changeOrder->attachment_path && Storage::exists($changeOrder->attachment_path), 404, 'No file');

        return Storage::download($changeOrder->attachment_path, $changeOrder->attachment_name);
    }

    // ── helpers ──
    /** Revised contract = original + sum of approved change-order impacts. */
    private function reviseContract(?Project $project): void
    {
        if (! $project) {
            return;
        }
        $original = (float) ($project->original_contract_value ?? $project->contract_value);
        $delta = ChangeOrder::where('project_id', $project->id)->where('status', 'approved')
            ->get()->sum(fn ($co) => $co->signedImpact());

        $project->forceFill(['contract_value' => round($original + $delta, 2)])->saveQuietly();
    }

    private function summary($rows): array
    {
        $approved = $rows->where('status', 'approved');

        return [
            'count' => $rows->count(),
            'pending' => $rows->whereIn('status', ['draft', 'submitted'])->count(),
            'approved' => $approved->count(),
            'additions' => round((float) $approved->where('kind', 'addition')->sum('cost_impact_base'), 2),
            'deductions' => round((float) $approved->where('kind', 'deduction')->sum('cost_impact_base'), 2),
            'net_impact' => round((float) $approved->sum(fn ($co) => $co->signedImpact()), 2),
            'time_impact' => (int) $approved->sum('time_impact_days'),
            'base' => Currency::where('is_base', true)->value('code') ?? 'AFN',
        ];
    }

    private function attach(Request $request, array &$data): void
    {
        if ($file = $request->file('attachment')) {
            [$data['attachment_path'], $attachMime] = $this->storeCompressed($file, 'change-orders/'.Tenant::id());
            $data['attachment_name'] = $file->getClientOriginalName();
            $data['attachment_mime'] = $attachMime;
        }
        unset($data['attachment']);
    }

    private function applyRateLock(array $data): array
    {
        $base = Currency::where('is_base', true)->value('code') ?? 'AFN';
        $rate = (($data['currency'] ?? 'AFN') === $base) ? 1 : (float) ($data['rate'] ?? 1);
        $data['rate'] = $rate;
        $data['cost_impact_base'] = round(((float) ($data['cost_impact'] ?? 0)) * $rate, 2);

        return $data;
    }

    private function nextCode(): string
    {
        $n = ChangeOrder::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'CO-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:100'],
            'kind' => ['required', 'in:addition,deduction,no_cost'],
            'status' => ['nullable', 'in:draft,submitted'],
            'cost_impact' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'time_impact_days' => ['nullable', 'integer'],
            'requested_by_name' => ['nullable', 'string', 'max:255'],
            'co_date' => ['nullable', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);
    }
}
