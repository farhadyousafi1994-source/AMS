<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * The warehouse (گدام): consumable stock. Every quantity change is one
 * movement row — purchases in, project consumption out — so on-hand
 * always equals the movement history.
 */
class StockController extends Controller
{
    public function index(): JsonResponse
    {
        $items = StockItem::withCount('movements')->orderBy('name')->get()
            ->map(function ($i) {
                $i->setAttribute('low', (float) $i->min_quantity > 0 && (float) $i->quantity <= (float) $i->min_quantity);

                return $i;
            });

        return response()->json($items);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = 'STK-'.str_pad((string) (StockItem::withTrashed()->where('company_id', Tenant::id())->count() + 1), 4, '0', STR_PAD_LEFT);

        $item = StockItem::create($data);
        ActivityLog::log('created', 'Stock', "Added stock item \"{$item->name}\"");

        return response()->json($item, 201);
    }

    public function show(StockItem $stockItem): JsonResponse
    {
        $stockItem->load(['movements' => fn ($q) => $q->with(['project:id,name', 'user:id,name'])->orderByDesc('movement_date')->orderByDesc('id')->limit(100)]);

        return response()->json($stockItem);
    }

    public function update(Request $request, StockItem $stockItem): JsonResponse
    {
        $stockItem->update($this->rules($request));
        ActivityLog::log('updated', 'Stock', "Updated stock item \"{$stockItem->name}\"");

        return response()->json($stockItem);
    }

    public function destroy(StockItem $stockItem): JsonResponse
    {
        abort_if($stockItem->movements()->exists(), 422, 'This item has stock movements — it cannot be deleted.');
        $name = $stockItem->name;
        $stockItem->delete();
        ActivityLog::log('deleted', 'Stock', "Deleted stock item \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    /** Manual in/out: purchases without a PO, project consumption, corrections. */
    public function addMovement(Request $request, StockItem $stockItem): JsonResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'kind' => ['required', 'in:purchase,consumption,adjustment,return'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'movement_date' => ['required', 'date'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'note' => ['nullable', 'string'],
        ]);

        if ($data['direction'] === 'out' && $data['quantity'] > (float) $stockItem->quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Only {$stockItem->quantity} {$stockItem->unit} of \"{$stockItem->name}\" are on hand.",
            ]);
        }

        $data['stock_item_id'] = $stockItem->id;
        $data['user_id'] = $request->user()?->id;
        $movement = StockMovement::create($data);

        $data['direction'] === 'in'
            ? $stockItem->increment('quantity', $data['quantity'])
            : $stockItem->decrement('quantity', $data['quantity']);

        $verb = $data['direction'] === 'in' ? 'Received' : 'Issued';
        ActivityLog::log('created', 'Stock', "{$verb} {$data['quantity']} {$stockItem->unit} of \"{$stockItem->name}\"", $data['project_id'] ?? null);

        return response()->json($movement->load(['project:id,name', 'user:id,name']), 201);
    }

    public function deleteMovement(StockMovement $movement): JsonResponse
    {
        // Reverse the quantity effect before removing the row.
        $item = $movement->stockItem;
        if ($item) {
            $movement->direction === 'in'
                ? $item->decrement('quantity', (float) $movement->quantity)
                : $item->increment('quantity', (float) $movement->quantity);
        }
        $movement->delete();
        ActivityLog::log('deleted', 'Stock', 'Reversed a stock movement', $movement->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['nullable', 'string', 'max:40'],
            'min_quantity' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
