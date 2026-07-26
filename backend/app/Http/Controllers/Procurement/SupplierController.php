<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Supplier;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            Supplier::withCount('purchaseOrders')->orderBy('name')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = 'SUP-'.str_pad((string) (Supplier::withTrashed()->where('company_id', Tenant::id())->count() + 1), 4, '0', STR_PAD_LEFT);

        $supplier = Supplier::create($data);
        ActivityLog::log('created', 'Supplier', "Added supplier \"{$supplier->name}\"");

        return response()->json($supplier, 201);
    }

    public function show(Supplier $supplier): JsonResponse
    {
        $supplier->load(['purchaseOrders' => fn ($q) => $q->with(['project:id,name', 'items'])->orderByDesc('order_date')]);

        return response()->json($supplier);
    }

    public function update(Request $request, Supplier $supplier): JsonResponse
    {
        $supplier->update($this->rules($request));
        ActivityLog::log('updated', 'Supplier', "Updated supplier \"{$supplier->name}\"");

        return response()->json($supplier);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        abort_if($supplier->purchaseOrders()->exists(), 422, 'This supplier has purchase orders — cancel them first.');
        $name = $supplier->name;
        $supplier->delete();
        ActivityLog::log('deleted', 'Supplier', "Deleted supplier \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'in:materials,equipment,fuel,services,other'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'active' => ['boolean'],
        ]);
    }
}
