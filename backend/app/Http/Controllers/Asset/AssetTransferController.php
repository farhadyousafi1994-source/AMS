<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Support\Branch;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetTransferController extends Controller
{
    /**
     * Transfers visible to the active branch: those it requested (outgoing) and
     * those other branches requested from it and it must approve (incoming).
     */
    public function index(Request $request): JsonResponse
    {
        $active = Branch::id();

        $transfers = AssetTransfer::with(['asset:id,name,category,code', 'fromBranch:id,name', 'toBranch:id,name', 'requester:id,name'])
            ->when($active !== null, fn ($q) => $q->where(fn ($w) => $w
                ->where('to_branch_id', $active)->orWhere('from_branch_id', $active)))
            ->orderByDesc('id')
            ->get()
            ->map(function (AssetTransfer $t) use ($active) {
                $arr = $t->toArray();
                $arr['direction'] = $active !== null && $t->from_branch_id === $active ? 'incoming' : 'outgoing';

                return $arr;
            });

        return response()->json($transfers);
    }

    /**
     * Request a quantity of an asset from the branch that owns it, into the
     * active branch. e.g. Herat requests 3 bulldozers from Kabul's 5.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
        ]);

        $active = Branch::id();
        abort_if($active === null, 422, 'Select a branch before requesting a transfer.');

        $asset = Asset::findOrFail($data['asset_id']);
        abort_if($asset->branch_id === null, 422, 'That asset is not assigned to a branch.');
        abort_if($asset->branch_id === $active, 422, 'That asset already belongs to your branch.');
        abort_if($data['quantity'] > $asset->available, 422, 'Requested quantity exceeds what is available at the owning branch.');

        $transfer = AssetTransfer::create([
            'asset_id' => $asset->id,
            'from_branch_id' => $asset->branch_id,
            'to_branch_id' => $active,
            'quantity' => $data['quantity'],
            'status' => 'pending',
            'reason' => $data['reason'] ?? null,
            'requested_by' => $request->user()?->id,
        ]);

        ActivityLog::log('created', 'AssetTransfer', "Requested {$data['quantity']}× {$asset->name} from another branch");

        return response()->json($transfer->load(['asset:id,name,code', 'fromBranch:id,name', 'toBranch:id,name']), 201);
    }

    /** Owner branch approves/rejects. Approving moves the quantity between branches. */
    public function decide(Request $request, AssetTransfer $transfer): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:approved,rejected']]);
        abort_if($transfer->status !== 'pending', 422, 'This request has already been decided.');

        if ($data['status'] === 'approved') {
            DB::transaction(function () use ($transfer) {
                $source = Asset::findOrFail($transfer->asset_id);
                abort_if($transfer->quantity > $source->available, 422, 'No longer enough units available to transfer.');

                $source->quantity_total = (int) $source->quantity_total - $transfer->quantity;
                $source->save();

                // Merge into the destination branch's matching asset, or clone one.
                $dest = Asset::where('branch_id', $transfer->to_branch_id)
                    ->where('name', $source->name)
                    ->where('category', $source->category)
                    ->first();

                if ($dest) {
                    $dest->quantity_total = (int) $dest->quantity_total + $transfer->quantity;
                    $dest->save();
                } else {
                    $clone = $source->replicate(['code']);
                    $clone->branch_id = $transfer->to_branch_id;
                    $clone->quantity_total = $transfer->quantity;
                    $clone->allocated = 0;
                    $clone->code = $this->nextCode();
                    $clone->save();
                }
            });
        }

        $transfer->update(['status' => $data['status'], 'approved_by' => $request->user()?->id]);

        ActivityLog::log('updated', 'AssetTransfer', "Asset transfer #{$transfer->id} {$data['status']}");

        return response()->json($transfer->load(['asset:id,name,code', 'fromBranch:id,name', 'toBranch:id,name']));
    }

    private function nextCode(): string
    {
        $n = Asset::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'AST-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
