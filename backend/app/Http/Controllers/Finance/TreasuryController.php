<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\TreasuryTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The company's General Budget. Cap-table rows where the company itself is
 * the participant create allocations here automatically; project receipts
 * are parked as "reserved" and released when the project completes.
 */
class TreasuryController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json(TreasuryTransaction::summary());
    }

    public function index(Request $request): JsonResponse
    {
        $query = TreasuryTransaction::with('project:id,name,code')->orderByDesc('tx_date')->orderByDesc('id');
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        return response()->json([
            'summary' => TreasuryTransaction::summary(),
            'transactions' => $query->limit(200)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'direction' => ['required', 'in:in,out'],
            'kind' => ['required', 'in:deposit,withdrawal,allocation,project_receipt,adjustment'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'tx_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string'],
        ]);

        if (! isset($data['rate'])) {
            $base = \App\Models\Currency::where('is_base', true)->value('code') ?? 'AFN';
            $data['rate'] = ($data['currency'] ?? 'AFN') === $base ? 1
                : (float) (\App\Models\ExchangeRate::where('currency_code', $data['currency'])
                    ->orderByDesc('rate_date')->orderByDesc('id')->value('rate_to_base') ?? 1);
        }
        $data['amount_base'] = round($data['amount'] * $data['rate'], 2);
        $data['tx_date'] = $data['tx_date'] ?? now()->toDateString();
        // Project receipts stay reserved until the project is completed.
        $data['status'] = $data['kind'] === 'project_receipt' ? 'reserved' : 'active';

        $row = TreasuryTransaction::create($data);

        ActivityLog::log('created', 'Treasury', "Treasury {$row->kind}: {$row->amount} {$row->currency}", $row->project_id);

        return response()->json($row->load('project:id,name,code'), 201);
    }

    public function destroy(TreasuryTransaction $treasury): JsonResponse
    {
        // Auto-created rows follow their source record instead.
        abort_if($treasury->investment_id !== null, 422, 'This allocation is managed by the project cap table.');
        abort_if($treasury->party_transaction_id !== null, 422, 'This entry is managed by a party account.');
        $treasury->delete();

        ActivityLog::log('deleted', 'Treasury', "Removed treasury {$treasury->kind} of {$treasury->amount} {$treasury->currency}", $treasury->project_id);

        return response()->json(['message' => 'Deleted.']);
    }
}
