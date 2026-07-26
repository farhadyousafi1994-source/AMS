<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ExpenseBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseBudgetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = ExpenseBudget::orderByDesc('period');
        if ($request->filled('type')) {
            $q->where('type', $request->string('type'));
        }

        return response()->json($q->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:home,office'],
            'category' => ['nullable', 'string', 'max:100'],
            'period' => ['required', 'string', 'size:7'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);

        $budget = ExpenseBudget::updateOrCreate(
            ['type' => $data['type'], 'category' => $data['category'] ?? null, 'period' => $data['period']],
            ['amount' => $data['amount'], 'note' => $data['note'] ?? null]
        );

        return response()->json($budget, 201);
    }

    public function destroy(ExpenseBudget $budget): JsonResponse
    {
        $budget->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
