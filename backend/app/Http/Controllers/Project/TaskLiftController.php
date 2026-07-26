<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\TaskLift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Lifts under a work-breakdown task. Each lift is a hold point: pour → inspect
 * (pass/fail) before the next lift proceeds. The controller flags — never hard-
 * blocks — pouring the next lift while the previous one isn't passed.
 */
class TaskLiftController extends Controller
{
    public function index(ProjectTask $task): JsonResponse
    {
        $lifts = $task->lifts()->get();

        return response()->json(['lifts' => $lifts, 'summary' => $this->summary($lifts)]);
    }

    /** Every lift in a project, for the project-wide inspection overview. */
    public function projectIndex(Project $project): JsonResponse
    {
        $lifts = TaskLift::where('project_id', $project->id)
            ->with('task:id,title,phase')->orderByDesc('id')->get();

        return response()->json(['lifts' => $lifts, 'summary' => $this->summary($lifts)]);
    }

    public function store(Request $request, ProjectTask $task): JsonResponse
    {
        $data = $request->validate([
            'lift_type' => ['nullable', 'in:concrete,earthwork,scaffold,other'],
            'description' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'planned_qty' => ['nullable', 'numeric', 'min:0'],
            'height_m' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['task_id'] = $task->id;
        $data['project_id'] = $task->project_id;
        $data['seq'] = (int) ($task->lifts()->max('seq') ?? 0) + 1;
        $data['status'] = 'planned';
        $data['recorded_by'] = $request->user()->id;

        $lift = TaskLift::create($data);
        ActivityLog::log('created', 'TaskLift', "Added Lift {$lift->seq} to \"{$task->title}\"", $task->project_id);

        return response()->json($this->index($task)->getData(), 201);
    }

    /** Record a pour or an inspection, or edit lift fields. */
    public function update(Request $request, TaskLift $lift): JsonResponse
    {
        $data = $request->validate([
            'lift_type' => ['nullable', 'in:concrete,earthwork,scaffold,other'],
            'description' => ['nullable', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:20'],
            'planned_qty' => ['nullable', 'numeric', 'min:0'],
            'height_m' => ['nullable', 'numeric', 'min:0'],
            'action' => ['nullable', 'in:pour,inspect,edit'],
            'poured_qty' => ['nullable', 'numeric', 'min:0'],
            'pour_date' => ['nullable', 'date'],
            'inspection_result' => ['nullable', 'in:pass,fail'],
            'inspected_by' => ['nullable', 'string', 'max:255'],
            'inspection_note' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        $action = $data['action'] ?? 'edit';

        if ($action === 'pour') {
            $lift->fill([
                'poured_qty' => $data['poured_qty'] ?? $lift->planned_qty,
                'pour_date' => $data['pour_date'] ?? now()->toDateString(),
                'status' => 'poured',
            ]);
            ActivityLog::log('updated', 'TaskLift', "Poured Lift {$lift->seq}", $lift->project_id);
        } elseif ($action === 'inspect') {
            $result = $data['inspection_result'] ?? 'pass';
            $lift->fill([
                'inspection_result' => $result,
                'inspected_by' => $data['inspected_by'] ?? $request->user()->name,
                'inspected_at' => now(),
                'inspection_note' => $data['inspection_note'] ?? null,
                'status' => $result === 'pass' ? 'passed' : 'failed',
            ]);
            ActivityLog::log('updated', 'TaskLift', "Inspected Lift {$lift->seq}: {$result}", $lift->project_id);
        } else {
            $lift->fill(collect($data)->only(['lift_type', 'description', 'unit', 'planned_qty', 'height_m', 'notes'])->toArray());
        }

        $lift->save();

        return response()->json($this->index($lift->task)->getData());
    }

    public function destroy(TaskLift $lift): JsonResponse
    {
        $task = $lift->task;
        $lift->delete();
        ActivityLog::log('deleted', 'TaskLift', "Deleted a lift", $task?->project_id);

        return response()->json($this->index($task)->getData());
    }

    private function summary($lifts): array
    {
        $sorted = $lifts->sortBy('seq')->values();
        // Hold point: the next lift can pour only if every earlier lift passed.
        $lastNotPassed = $sorted->firstWhere(fn ($l) => $l->status !== 'passed');

        return [
            'count' => $lifts->count(),
            'poured' => $lifts->whereIn('status', ['poured', 'passed', 'failed'])->count(),
            'passed' => $lifts->where('status', 'passed')->count(),
            'failed' => $lifts->where('status', 'failed')->count(),
            'poured_qty' => round((float) $lifts->sum('poured_qty'), 3),
            'planned_qty' => round((float) $lifts->sum('planned_qty'), 3),
            // The seq that is safe to pour next (all before it passed).
            'hold_at' => $lastNotPassed?->seq,
        ];
    }
}
