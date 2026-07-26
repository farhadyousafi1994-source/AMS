<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $users = User::with(['roles', 'projects:id,name'])
            ->where('company_id', $request->user()->current_company)
            ->get();

        return response()->json($users);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'type' => ['nullable', 'string'],
            'roles' => ['array'],
            'project_ids' => ['array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'type' => $data['type'] ?? null,
            'company_id' => $request->user()->current_company,
            'current_company' => $request->user()->current_company,
        ]);

        if ($request->user()->current_company) {
            $user->companies()->syncWithoutDetaching([$request->user()->current_company]);
        }
        $user->syncRoles($data['roles'] ?? []);
        if (array_key_exists('project_ids', $data)) {
            $user->projects()->sync($data['project_ids']);
        }

        ActivityLog::log('created', 'User', "Created user \"{$user->name}\"");

        return response()->json($user->load(['roles', 'projects:id,name']), 201);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('roles'));
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'type' => ['nullable', 'string'],
            'roles' => ['array'],
            'project_ids' => ['array'],
            'project_ids.*' => ['integer', 'exists:projects,id'],
        ]);

        $user->fill(collect($data)->except(['password', 'roles', 'project_ids'])->toArray());
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        if (array_key_exists('roles', $data)) {
            $user->syncRoles($data['roles']);
        }
        if (array_key_exists('project_ids', $data)) {
            $user->projects()->sync($data['project_ids']);
        }

        ActivityLog::log('updated', 'User', "Updated user \"{$user->name}\"");

        return response()->json($user->load(['roles', 'projects:id,name']));
    }

    public function destroy(User $user): JsonResponse
    {
        $name = $user->name;
        $user->delete();

        ActivityLog::log('deleted', 'User', "Deleted user \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }
}
