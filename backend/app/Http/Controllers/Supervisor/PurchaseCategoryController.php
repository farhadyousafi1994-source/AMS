<?php

namespace App\Http\Controllers\Supervisor;

use App\Http\Controllers\Controller;
use App\Models\PurchaseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            PurchaseCategory::where('active', true)->orderBy('sort')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort' => ['nullable', 'integer'],
        ]);

        return response()->json(PurchaseCategory::create($data), 201);
    }

    public function destroy(PurchaseCategory $purchaseCategory): JsonResponse
    {
        $purchaseCategory->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
