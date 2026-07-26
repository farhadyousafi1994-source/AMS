<?php

namespace App\Http\Controllers\Safety;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\SafetyIncident;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * HSE / Safety Incident register. Incidents may be tied to a project (and are
 * then subject to the same per-project visibility scoping as the rest of the
 * app) or logged company-wide. Records move open → investigating →
 * action_pending → closed; closing captures who/when and a closure note.
 */
class SafetyIncidentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $ids = $request->user()->visibleProjectIds();

        $rows = SafetyIncident::query()
            ->with(['project:id,name,code', 'reporter:id,name', 'closer:id,name'])
            // Project-bound incidents respect scoping; company-wide (null project) always show.
            ->when($ids !== null, fn ($q) => $q->where(
                fn ($w) => $w->whereIn('project_id', $ids)->orWhereNull('project_id')
            ))
            ->when($request->filled('project_id'), fn ($q) => $q->where('project_id', $request->integer('project_id')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('severity'), fn ($q) => $q->where('severity', $request->string('severity')))
            ->orderByDesc('incident_date')->orderByDesc('id')->get();

        return response()->json(['incidents' => $rows, 'summary' => $this->summary($rows)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = $this->nextCode();
        $data['reported_by'] = $request->user()->id;
        $data['reported_by_name'] = $data['reported_by_name'] ?? $request->user()->name;

        $incident = SafetyIncident::create($data);
        ActivityLog::log('created', 'SafetyIncident', "Logged {$incident->type} {$incident->code} — {$incident->title}", $incident->project_id);

        return response()->json($incident->load(['project:id,name,code', 'reporter:id,name']), 201);
    }

    public function update(Request $request, SafetyIncident $safetyIncident): JsonResponse
    {
        $data = $this->rules($request);
        $safetyIncident->update($data);
        ActivityLog::log('updated', 'SafetyIncident', "Updated incident {$safetyIncident->code}", $safetyIncident->project_id);

        return response()->json($safetyIncident->fresh()->load(['project:id,name,code', 'reporter:id,name']));
    }

    /** Close out an incident once corrective action is complete. */
    public function close(Request $request, SafetyIncident $safetyIncident): JsonResponse
    {
        abort_if($safetyIncident->status === 'closed', 422, 'Incident is already closed.');

        $data = $request->validate([
            'closure_note' => ['nullable', 'string', 'max:255'],
            'corrective_action' => ['nullable', 'string'],
            'lost_time_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $safetyIncident->update([
            'status' => 'closed',
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
            'closure_note' => $data['closure_note'] ?? $safetyIncident->closure_note,
            'corrective_action' => $data['corrective_action'] ?? $safetyIncident->corrective_action,
            'lost_time_days' => $data['lost_time_days'] ?? $safetyIncident->lost_time_days,
        ]);
        ActivityLog::log('updated', 'SafetyIncident', "Closed incident {$safetyIncident->code}", $safetyIncident->project_id);

        return response()->json($safetyIncident->fresh());
    }

    /** Reopen a closed incident for further investigation. */
    public function reopen(SafetyIncident $safetyIncident): JsonResponse
    {
        abort_unless($safetyIncident->status === 'closed', 422, 'Only a closed incident can be reopened.');
        $safetyIncident->update(['status' => 'investigating', 'closed_by' => null, 'closed_at' => null]);
        ActivityLog::log('updated', 'SafetyIncident', "Reopened incident {$safetyIncident->code}", $safetyIncident->project_id);

        return response()->json($safetyIncident->fresh());
    }

    public function destroy(SafetyIncident $safetyIncident): JsonResponse
    {
        $code = $safetyIncident->code;
        $pid = $safetyIncident->project_id;
        $safetyIncident->delete();
        ActivityLog::log('deleted', 'SafetyIncident', "Deleted incident {$code}", $pid);

        return response()->json(['message' => 'Deleted.']);
    }

    // ── helpers ──
    private function summary($rows): array
    {
        $open = $rows->where('status', '!=', 'closed');
        $monthStart = now()->startOfMonth();

        return [
            'count' => $rows->count(),
            'open' => $open->count(),
            'critical_open' => $open->where('severity', 'critical')->count(),
            'closed' => $rows->where('status', 'closed')->count(),
            'this_month' => $rows->filter(fn ($r) => $r->incident_date && $r->incident_date->gte($monthStart))->count(),
            'lost_time_days' => (int) $rows->sum('lost_time_days'),
            'injured' => (int) $rows->sum('injured_count'),
            'by_type' => [
                'hazard' => $rows->where('type', 'hazard')->count(),
                'near_miss' => $rows->where('type', 'near_miss')->count(),
                'incident' => $rows->where('type', 'incident')->count(),
                'accident' => $rows->where('type', 'accident')->count(),
            ],
        ];
    }

    private function nextCode(): string
    {
        $n = SafetyIncident::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'INC-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'type' => ['required', 'in:hazard,near_miss,incident,accident'],
            'severity' => ['required', 'in:low,medium,high,critical'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string', 'max:255'],
            'incident_date' => ['required', 'date'],
            'incident_time' => ['nullable', 'string', 'max:10'],
            'people_involved' => ['nullable', 'string', 'max:255'],
            'injured_count' => ['nullable', 'integer', 'min:0'],
            'lost_time_days' => ['nullable', 'integer', 'min:0'],
            'immediate_action' => ['nullable', 'string'],
            'corrective_action' => ['nullable', 'string'],
            'status' => ['nullable', 'in:open,investigating,action_pending,closed'],
            'reported_by_name' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
