<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectSite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectSiteController extends Controller
{
    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->rules($request);
        $data['project_id'] = $project->id;

        $site = ProjectSite::create($data);

        ActivityLog::log('created', 'Site', "Added site \"{$site->name}\" to project \"{$project->name}\"", $project->id);

        return response()->json($site, 201);
    }

    public function update(Request $request, ProjectSite $site): JsonResponse
    {
        $site->update($this->rules($request));

        ActivityLog::log('updated', 'Site', "Updated site \"{$site->name}\"", $site->project_id);

        return response()->json($site);
    }

    public function destroy(ProjectSite $site): JsonResponse
    {
        $name = $site->name;
        $site->delete();

        ActivityLog::log('deleted', 'Site', "Deleted site \"{$name}\"", $site->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'in_charge' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ]);
    }
}
