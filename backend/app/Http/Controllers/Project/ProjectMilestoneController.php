<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectMilestone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectMilestoneController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->rules($request);
        $data['project_id'] = $project->id;

        $milestone = ProjectMilestone::create($data);

        ActivityLog::log('created', 'Milestone', "Added milestone \"{$milestone->title}\" to project \"{$project->name}\"", $project->id);

        return response()->json($milestone, 201);
    }

    public function update(Request $request, ProjectMilestone $milestone): JsonResponse
    {
        $milestone->update($this->rules($request));

        ActivityLog::log('updated', 'Milestone', "Updated milestone \"{$milestone->title}\"", $milestone->project_id);

        return response()->json($milestone);
    }

    public function destroy(ProjectMilestone $milestone): JsonResponse
    {
        $title = $milestone->title;
        $milestone->delete();

        ActivityLog::log('deleted', 'Milestone', "Deleted milestone \"{$title}\"", $milestone->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,in_progress,done'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
