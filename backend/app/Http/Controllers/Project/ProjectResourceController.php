<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\ProjectMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The resources a project consumes: returnable assets (equipment, vehicles,
 * tools) whose allocation is tracked against the asset pool, and consumable
 * materials (cement, sand, rebar, …) recorded as free-form quantities.
 */
class ProjectResourceController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json([
            'assets' => ProjectAsset::where('project_id', $project->id)
                ->with('asset:id,code,name,category,unit,quantity_total,allocated')
                ->orderByDesc('id')->get(),
            'materials' => ProjectMaterial::where('project_id', $project->id)
                ->orderBy('name')->get(),
        ]);
    }

    // ── Returnable assets ──
    public function attachAsset(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'notes' => ['nullable', 'string'],
        ]);

        $asset = Asset::findOrFail($data['asset_id']);
        if ($data['quantity'] > $asset->available) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$asset->available} of \"{$asset->name}\" are available.",
            ]);
        }

        $row = ProjectAsset::create([
            'project_id' => $project->id,
            'asset_id' => $asset->id,
            'quantity' => $data['quantity'],
            'notes' => $data['notes'] ?? null,
        ]);

        $asset->increment('allocated', $data['quantity']);

        ActivityLog::log('created', 'Project', "Allocated {$data['quantity']}× \"{$asset->name}\" to project \"{$project->name}\"", $project->id);

        return response()->json($row->load('asset:id,code,name,category,unit,quantity_total,allocated'), 201);
    }

    public function detachAsset(ProjectAsset $projectAsset): JsonResponse
    {
        // Return the units to the pool before removing the allocation row.
        $projectAsset->asset()->first()?->decrement('allocated', $projectAsset->quantity);
        $projectAsset->delete();

        ActivityLog::log('deleted', 'Project', 'Returned allocated asset units to the pool', $projectAsset->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Consumable materials ──
    public function addMaterial(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['project_id'] = $project->id;

        $row = ProjectMaterial::create($data);

        ActivityLog::log('created', 'Project', "Planned material \"{$row->name}\" for project \"{$project->name}\"", $project->id);

        return response()->json($row, 201);
    }

    public function updateMaterial(Request $request, ProjectMaterial $material): JsonResponse
    {
        $material->update($request->validate([
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string'],
        ]));

        return response()->json($material);
    }

    public function deleteMaterial(ProjectMaterial $material): JsonResponse
    {
        $material->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
