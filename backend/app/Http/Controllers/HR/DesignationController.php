<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Designation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Designation::with('department:id,name')->orderBy('title')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);

        $designation = Designation::create($data);

        ActivityLog::log('created', 'Designation', "Added designation \"{$designation->title}\"");

        return response()->json($designation->load('department:id,name'), 201);
    }

    public function update(Request $request, Designation $designation): JsonResponse
    {
        $designation->update($this->rules($request));

        ActivityLog::log('updated', 'Designation', "Updated designation \"{$designation->title}\"");

        return response()->json($designation->load('department:id,name'));
    }

    public function destroy(Designation $designation): JsonResponse
    {
        $title = $designation->title;
        $designation->delete();

        ActivityLog::log('deleted', 'Designation', "Deleted designation \"{$title}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);
    }
}
