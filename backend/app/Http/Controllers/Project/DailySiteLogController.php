<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DailySiteLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailySiteLogController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(
            DailySiteLog::where('project_id', $project->id)
                ->with(['site:id,name', 'user:id,name'])
                ->orderByDesc('log_date')
                ->orderByDesc('id')
                ->get()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->rules($request);
        $data['project_id'] = $project->id;
        $data['user_id'] = $request->user()?->id;

        $log = DailySiteLog::create($data);
        $project->syncProgress(); // filing a log means work has started on the ground

        ActivityLog::log('created', 'DailyLog', "Added daily log ({$log->log_date->toDateString()}) to project \"{$project->name}\"", $project->id);

        return response()->json($log->load(['site:id,name', 'user:id,name']), 201);
    }

    public function update(Request $request, DailySiteLog $siteLog): JsonResponse
    {
        $siteLog->update($this->rules($request));

        ActivityLog::log('updated', 'DailyLog', "Updated daily log #{$siteLog->id}", $siteLog->project_id);

        return response()->json($siteLog->load(['site:id,name', 'user:id,name']));
    }

    public function destroy(DailySiteLog $siteLog): JsonResponse
    {
        $siteLog->delete();

        ActivityLog::log('deleted', 'DailyLog', "Deleted daily log #{$siteLog->id}", $siteLog->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'site_id' => ['nullable', 'integer', 'exists:project_sites,id'],
            'log_date' => ['required', 'date'],
            'weather' => ['nullable', 'string', 'max:100'],
            'labour_count' => ['nullable', 'integer', 'min:0'],
            'work_done' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
