<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Department::withCount('designations')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);

        $department = Department::create($data);

        ActivityLog::log('created', 'Department', "Added department \"{$department->name}\"");

        return response()->json($department, 201);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $department->update($this->rules($request, $department));

        ActivityLog::log('updated', 'Department', "Updated department \"{$department->name}\"");

        return response()->json($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $name = $department->name;
        $department->delete();

        ActivityLog::log('deleted', 'Department', "Deleted department \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request, ?Department $department = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department?->id)->whereNull('deleted_at')],
            'manager' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);
    }
}
