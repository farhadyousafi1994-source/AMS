<?php

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Branch::orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertBranchProvisioningAllowed($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'active' => ['boolean'],
        ]);

        $branch = Branch::create($data);

        ActivityLog::log('created', 'Branch', "Created branch \"{$branch->name}\"");

        return response()->json($branch, 201);
    }

    public function show(Branch $branch): JsonResponse
    {
        return response()->json($branch);
    }

    public function update(Request $request, Branch $branch): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:50'],
            'active' => ['boolean'],
        ]);

        $branch->update($data);

        ActivityLog::log('updated', 'Branch', "Updated branch \"{$branch->name}\"");

        return response()->json($branch);
    }

    public function destroy(Request $request, Branch $branch): JsonResponse
    {
        $this->assertBranchProvisioningAllowed($request);

        $name = $branch->name;
        $branch->delete();

        ActivityLog::log('deleted', 'Branch', "Deleted branch \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * Branch provisioning (create/delete) is reserved to the Platform Owner.
     * A tenant may do it only if the Platform Owner enabled branch_self_service
     * for that organization. This is enforced regardless of tenant roles.
     */
    private function assertBranchProvisioningAllowed(Request $request): void
    {
        $user = $request->user();
        if ($user?->isPlatformOwner()) {
            return;
        }
        $selfService = (bool) optional(\App\Models\Company::find(\App\Support\Tenant::id()))->branch_self_service;
        abort_unless($selfService, 403, 'Branch provisioning is reserved to the Platform Owner. Please submit a request.');
    }

    /** Switch the signed-in user's active branch (null/"all" = all branches). */
    public function switch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $user = $request->user();
        $branchId = $data['branch_id'] ?? null;

        // Only privileged users may pick "all branches"; others must pick one
        // of their assigned branches.
        if ($branchId !== null && ! $user->seesAllBranches()
            && ! in_array($branchId, $user->accessibleBranchIds(), true)) {
            abort(403, 'You are not assigned to that branch.');
        }

        $user->current_branch = $branchId;
        $user->save();

        $name = $branchId ? optional(Branch::find($branchId))->name : 'All Branches';
        ActivityLog::log('updated', 'Branch', "Switched active branch to \"{$name}\"");

        return response()->json(['current_branch' => $branchId, 'name' => $name]);
    }
}
