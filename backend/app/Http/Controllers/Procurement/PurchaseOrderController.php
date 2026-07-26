<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PurchaseOrder;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PurchaseOrder::with(['supplier:id,name,code', 'project:id,name'])
            ->withSum('items as total', 'line_total');
        foreach (['status', 'supplier_id', 'project_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, $request->input($f));
            }
        }

        return response()->json($query->orderByDesc('order_date')->orderByDesc('id')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $items = $data['items'];
        unset($data['items']);
        $data['code'] = 'PO-'.str_pad((string) (PurchaseOrder::withTrashed()->where('company_id', Tenant::id())->count() + 1), 4, '0', STR_PAD_LEFT);

        $po = PurchaseOrder::create($data);
        $this->saveItems($po, $items);

        ActivityLog::log('created', 'PurchaseOrder', "Created {$po->code} for supplier #{$po->supplier_id}", $po->project_id);

        return response()->json($po->load(['supplier:id,name,code', 'project:id,name', 'items']), 201);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        return response()->json(
            $purchaseOrder->load(['supplier:id,name,code,phone', 'project:id,name', 'items.stockItem:id,name,unit'])
        );
    }

    public function update(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_if($purchaseOrder->status === 'received', 422, 'A received order can no longer be edited.');

        $data = $this->rules($request);
        $items = $data['items'];
        unset($data['items']);

        $purchaseOrder->update($data);
        $purchaseOrder->items()->delete();
        $this->saveItems($purchaseOrder, $items);

        ActivityLog::log('updated', 'PurchaseOrder', "Updated {$purchaseOrder->code}", $purchaseOrder->project_id);

        return response()->json($purchaseOrder->load(['supplier:id,name,code', 'project:id,name', 'items']));
    }

    /**
     * Receiving is the moment goods reach the گدام: each line linked to a
     * stock item creates an in-movement and raises the on-hand quantity.
     */
    public function receive(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_if($purchaseOrder->status === 'received', 422, 'Already received.');
        abort_if($purchaseOrder->status === 'cancelled', 422, 'A cancelled order cannot be received.');

        foreach ($purchaseOrder->items as $line) {
            if (! $line->stock_item_id) {
                continue;
            }
            $item = StockItem::find($line->stock_item_id);
            if (! $item) {
                continue;
            }
            StockMovement::create([
                'stock_item_id' => $item->id,
                'project_id' => $purchaseOrder->project_id,
                'purchase_order_id' => $purchaseOrder->id,
                'user_id' => $request->user()?->id,
                'direction' => 'in', 'kind' => 'purchase',
                'quantity' => $line->quantity,
                'movement_date' => now()->toDateString(),
                'note' => "PO {$purchaseOrder->code}",
            ]);
            $item->increment('quantity', (float) $line->quantity);
        }

        $purchaseOrder->update(['status' => 'received']);
        ActivityLog::log('updated', 'PurchaseOrder', "Received {$purchaseOrder->code} into stock", $purchaseOrder->project_id);

        return response()->json($purchaseOrder->load('items'));
    }

    public function destroy(PurchaseOrder $purchaseOrder): JsonResponse
    {
        abort_if($purchaseOrder->status === 'received', 422, 'A received order cannot be deleted — its goods are already in stock.');
        $code = $purchaseOrder->code;
        $purchaseOrder->items()->delete();
        $purchaseOrder->delete();
        ActivityLog::log('deleted', 'PurchaseOrder', "Deleted {$code}", $purchaseOrder->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    private function saveItems(PurchaseOrder $po, array $items): void
    {
        foreach ($items as $line) {
            $name = $line['name'] ?? null;
            if (! $name && ! empty($line['stock_item_id'])) {
                $name = StockItem::find($line['stock_item_id'])?->name;
            }
            $po->items()->create([
                'stock_item_id' => $line['stock_item_id'] ?? null,
                'name' => $name ?? '—',
                'quantity' => $line['quantity'] ?? 0,
                'unit' => $line['unit'] ?? null,
                'unit_price' => $line['unit_price'] ?? 0,
                'line_total' => round(($line['quantity'] ?? 0) * ($line['unit_price'] ?? 0), 2),
            ]);
        }
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:draft,ordered,received,cancelled'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_item_id' => ['nullable', 'integer', 'exists:stock_items,id'],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit' => ['nullable', 'string', 'max:40'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
