<?php

namespace App\Http\Controllers\Contract;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\ContractPayment;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContractController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Contract::with('project:id,name')
            ->withSum(['payments as paid_total' => fn ($q) => $q->where('kind', 'payment')], 'amount')
            ->withSum(['payments as advance_total' => fn ($q) => $q->where('kind', 'advance')], 'amount');

        foreach (['direction', 'party_type', 'status'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->input($filter));
            }
        }
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        $rows = $query->orderByDesc('id')->get()->map(fn ($c) => $this->withSettlement($c));

        return response()->json($rows);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->rules($request);
        $data['code'] = $this->nextCode();

        $contract = Contract::create($data);

        ActivityLog::log('created', 'Contract', "Created contract \"{$contract->title}\" with {$contract->party_name}");

        return response()->json($contract->load('project:id,name'), 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        $contract->load([
            'project:id,name',
            'milestones' => fn ($q) => $q->orderBy('due_date')->orderBy('id'),
            'payments' => fn ($q) => $q->with('user:id,name')->orderByDesc('payment_date')->orderByDesc('id'),
        ]);
        $contract->loadSum(['payments as paid_total' => fn ($q) => $q->where('kind', 'payment')], 'amount');
        $contract->loadSum(['payments as advance_total' => fn ($q) => $q->where('kind', 'advance')], 'amount');
        $this->withSettlement($contract);

        // Cross-project history: every other contract of the same party.
        $history = Contract::with('project:id,name')
            ->where('party_name', $contract->party_name)
            ->where('id', '!=', $contract->id)
            ->withSum(['payments as paid_total' => fn ($q) => $q->where('kind', 'payment')], 'amount')
            ->withSum(['payments as advance_total' => fn ($q) => $q->where('kind', 'advance')], 'amount')
            ->orderByDesc('id')->get()->map(fn ($c) => $this->withSettlement($c));
        $contract->setAttribute('party_history', $history);

        return response()->json($contract);
    }

    public function update(Request $request, Contract $contract): JsonResponse
    {
        $contract->update($this->rules($request));

        ActivityLog::log('updated', 'Contract', "Updated contract \"{$contract->title}\"");

        return response()->json($contract->load('project:id,name'));
    }

    public function destroy(Contract $contract): JsonResponse
    {
        $title = $contract->title;
        $contract->delete();

        ActivityLog::log('deleted', 'Contract', "Deleted contract \"{$title}\"");

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Milestones ──
    public function addMilestone(Request $request, Contract $contract): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,in_progress,done'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['contract_id'] = $contract->id;

        $row = ContractMilestone::create($data);

        ActivityLog::log('created', 'Contract', "Added milestone \"{$row->title}\" to contract \"{$contract->title}\"");

        return response()->json($row, 201);
    }

    public function updateMilestone(Request $request, ContractMilestone $milestone): JsonResponse
    {
        $milestone->update($request->validate([
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'in:pending,in_progress,done'],
            'notes' => ['nullable', 'string'],
        ]));

        return response()->json($milestone);
    }

    public function deleteMilestone(ContractMilestone $milestone): JsonResponse
    {
        $milestone->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    // ── Payments ──
    public function addPayment(Request $request, Contract $contract): JsonResponse
    {
        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'kind' => ['required', 'in:payment,advance'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string'],
        ]);
        $data['contract_id'] = $contract->id;
        $data['user_id'] = $request->user()?->id;

        $row = ContractPayment::create($data);

        ActivityLog::log('created', 'Contract', "Recorded {$row->kind} of {$row->amount} on contract \"{$contract->title}\"");

        return response()->json($row->load('user:id,name'), 201);
    }

    public function deletePayment(ContractPayment $payment): JsonResponse
    {
        $payment->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * Settlement: amount − (payments + advances) = balance. The direction
     * only changes whether the money flows in (client) or out (subcontractor);
     * the arithmetic is identical.
     */
    private function withSettlement(Contract $c): Contract
    {
        $paid = (float) ($c->paid_total ?? 0);
        $advance = (float) ($c->advance_total ?? 0);
        $amount = (float) ($c->amount ?? 0);

        $c->setAttribute('paid_total', round($paid, 2));
        $c->setAttribute('advance_total', round($advance, 2));
        $c->setAttribute('total_settled', round($paid + $advance, 2));
        $c->setAttribute('balance', round($amount - $paid - $advance, 2));

        return $c;
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'party_name' => ['required', 'string', 'max:255'],
            'party_type' => ['nullable', 'in:individual,company,government'],
            'party_phone' => ['nullable', 'string', 'max:50'],
            'direction' => ['nullable', 'in:client,subcontractor'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:draft,active,completed,cancelled'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'scope' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    private function nextCode(): string
    {
        $n = Contract::withTrashed()->where('company_id', Tenant::id())->count() + 1;

        return 'CON-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }
}
