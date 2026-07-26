<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectTask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTaskController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(
            ProjectTask::where('project_id', $project->id)
                ->with('site:id,name')
                ->withCount('lifts')
                ->orderBy('phase')->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->rules($request);
        $data['project_id'] = $project->id;
        $data['user_id'] = $request->user()?->id;

        $task = ProjectTask::create($data);
        $project->syncProgress();

        ActivityLog::log('created', 'Task', "Added task \"{$task->title}\" to project \"{$project->name}\"", $project->id);

        return response()->json($task->load('site:id,name'), 201);
    }

    public function update(Request $request, ProjectTask $task): JsonResponse
    {
        $task->update($this->rules($request));
        $task->project?->syncProgress();

        ActivityLog::log('updated', 'Task', "Updated task \"{$task->title}\"", $task->project_id);

        return response()->json($task->load('site:id,name'));
    }

    public function destroy(ProjectTask $task): JsonResponse
    {
        $title = $task->title;
        $project = $task->project;
        $task->delete();
        $project?->syncProgress();

        ActivityLog::log('deleted', 'Task', "Deleted task \"{$title}\"", $project?->id);

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'site_id' => ['nullable', 'integer', 'exists:project_sites,id'],
            'phase' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:todo,in_progress,done,blocked'],
            'priority' => ['nullable', 'in:low,medium,high'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
