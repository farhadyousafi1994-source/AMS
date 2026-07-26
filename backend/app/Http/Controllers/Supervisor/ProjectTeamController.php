<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Per-project team assignment. This is what limits a supervisor/engineer to
 * their own projects across the whole supervisor module.
 */
class ProjectTeamController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(
            $project->users()->get(['users.id', 'name', 'email'])
                ->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'site_role' => $u->pivot->site_role,
                ])
        );
    }

    public function sync(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'members' => ['array'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.site_role' => ['nullable', 'in:supervisor,engineer'],
        ]);

        $pivot = [];
        foreach ($data['members'] ?? [] as $m) {
            $pivot[$m['user_id']] = ['site_role' => $m['site_role'] ?? null];
        }
        $project->users()->sync($pivot);

        ActivityLog::log('updated', 'Project', "Updated site team for \"{$project->name}\"", $project->id);

        return response()->json(['message' => 'Saved.', 'count' => count($pivot)]);
    }
}
