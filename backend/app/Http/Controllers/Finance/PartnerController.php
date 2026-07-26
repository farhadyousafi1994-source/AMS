<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Partner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Partner::with('user:id,name')->orderBy('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $partner = Partner::create($this->rules($request));
        ActivityLog::log('created', 'Partner', "Added partner \"{$partner->name}\"");

        return response()->json($partner, 201);
    }

    public function update(Request $request, Partner $partner): JsonResponse
    {
        $partner->update($this->rules($request));
        ActivityLog::log('updated', 'Partner', "Updated partner \"{$partner->name}\"");

        return response()->json($partner);
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $name = $partner->name;
        $partner->delete();
        ActivityLog::log('deleted', 'Partner', "Deleted partner \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'share_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
