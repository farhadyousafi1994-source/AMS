<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\Subcontractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubcontractorController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        $subs = Subcontractor::where('project_id', $project->id)
            ->withSum(['payments as paid_total' => fn ($q) => $q->where('kind', 'payment')], 'amount')
            ->withSum(['payments as advance_total' => fn ($q) => $q->where('kind', 'advance')], 'amount')
            ->orderBy('name')
            ->get()
            ->map(fn ($s) => $this->withSettlement($s));

        return response()->json($subs);
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->rules($request);
        $data['project_id'] = $project->id;

        $sub = Subcontractor::create($data);

        ActivityLog::log('created', 'Subcontractor', "Added subcontractor \"{$sub->name}\" to project \"{$project->name}\"", $project->id);

        return response()->json($sub, 201);
    }

    public function show(Subcontractor $subcontractor): JsonResponse
    {
        $subcontractor->load(['payments' => fn ($q) => $q->with('user:id,name')->orderByDesc('payment_date')->orderByDesc('id')]);
        $subcontractor->loadSum(['payments as paid_total' => fn ($q) => $q->where('kind', 'payment')], 'amount');
        $subcontractor->loadSum(['payments as advance_total' => fn ($q) => $q->where('kind', 'advance')], 'amount');

        return response()->json($this->withSettlement($subcontractor));
    }

    public function update(Request $request, Subcontractor $subcontractor): JsonResponse
    {
        $subcontractor->update($this->rules($request));

        ActivityLog::log('updated', 'Subcontractor', "Updated subcontractor \"{$subcontractor->name}\"", $subcontractor->project_id);

        return response()->json($subcontractor);
    }

    public function destroy(Subcontractor $subcontractor): JsonResponse
    {
        $name = $subcontractor->name;
        $subcontractor->delete();

        ActivityLog::log('deleted', 'Subcontractor', "Deleted subcontractor \"{$name}\"", $subcontractor->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * Attach the settlement figures the client calls "تصفیه‌خط":
     * contract − (payments + advances) = balance.
     */
    private function withSettlement(Subcontractor $s): Subcontractor
    {
        $paid = (float) ($s->paid_total ?? 0);
        $advance = (float) ($s->advance_total ?? 0);
        $contract = (float) ($s->contract_amount ?? 0);

        $s->setAttribute('paid_total', round($paid, 2));
        $s->setAttribute('advance_total', round($advance, 2));
        $s->setAttribute('total_settled', round($paid + $advance, 2));
        $s->setAttribute('balance', round($contract - $paid - $advance, 2));

        return $s;
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tradesman_id' => ['nullable', 'integer', 'exists:tradesmen,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'trade' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'contract_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'active' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
