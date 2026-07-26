<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Support\Branch;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Warehouse shows every branch's assets. Those belonging to another branch
        // are flagged locked so the UI can grey them out and offer a transfer.
        $query = Asset::query()->with('branch:id,name')->orderBy('category')->orderBy('name');

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('tracking')) {
            $query->where('tracking', $request->string('tracking'));
        }
        if ($request->filled('location')) {
            $query->where('location', $request->string('location'));
        }

        $active = Branch::id();
        $assets = $query->get()->map(function (Asset $a) use ($active) {
            $arr = $a->toArray();
            $arr['locked'] = $active !== null && $a->branch_id !== null && $a->branch_id !== $active;
            $arr['branch_name'] = $a->branch?->name;

            return $arr;
        });

        return response()->json($assets);
    }

    public function show(Asset $asset): JsonResponse
    {
        $asset->load(['maintenanceLogs' => fn ($q) => $q->orderByDesc('log_date')]);

        return response()->json($asset);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data = $this->normalise($data);
        $data['code'] = $this->nextCode();

        $asset = Asset::create($data);

        ActivityLog::log('created', 'Asset', "Added asset \"{$asset->name}\" ({$asset->code})");

        return response()->json($asset, 201);
    }

    public function update(Request $request, Asset $asset): JsonResponse
    {
        $data = $this->normalise($this->rules($request, $asset));
        $asset->update($data);

        ActivityLog::log('updated', 'Asset', "Updated asset \"{$asset->name}\"");

        return response()->json($asset);
    }

    public function destroy(Asset $asset): JsonResponse
    {
        $name = $asset->name;
        $asset->delete();

        ActivityLog::log('deleted', 'Asset', "Deleted asset \"{$name}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Maintenance log (per-unit assets) ──
    public function addMaintenance(Request $request, Asset $asset): JsonResponse
    {
        $data = $request->validate([
            'log_date' => ['required', 'date'],
            'work_type' => ['nullable', 'string', 'max:255'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'description' => ['nullable', 'string'],
        ]);
        $data['cost'] = $data['cost'] ?? 0;
        $data['currency'] = $data['currency'] ?? 'AFN';

        $log = $asset->maintenanceLogs()->create($data);

        ActivityLog::log('created', 'Asset', "Maintenance logged for \"{$asset->name}\"");

        return response()->json($log, 201);
    }

    public function deleteMaintenance(\App\Models\AssetMaintenanceLog $maintenanceLog): JsonResponse
    {
        $maintenanceLog->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ── helpers ──
    private function normalise(array $data): array
    {
        if (($data['tracking'] ?? 'unit') === 'unit') {
            $data['quantity_total'] = 1;
            $data['unit'] = null;
        } else {
            // by-count: no serial/condition on the group record
            $data['serial'] = null;
            $data['condition'] = null;
            $data['quantity_total'] = max(1, (int) ($data['quantity_total'] ?? 1));
        }

        return $data;
    }

    private function nextCode(): string
    {
        $seq = Asset::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'AST-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }

    private function rules(Request $request, ?Asset $asset = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'category' => ['required', 'in:heavy_equipment,vehicle,tool,equipment'],
            'tracking' => ['required', 'in:unit,count'],
            'quantity_total' => ['nullable', 'integer', 'min:1'],
            'allocated' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'in:piece,set'],
            'status' => ['nullable', 'in:available,in_use,maintenance,retired'],
            'location' => ['nullable', 'string', 'max:255'],
            'serial' => ['nullable', 'string', 'max:255'],
            'condition' => ['nullable', 'in:new,good,fair,needs_repair'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_value' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
