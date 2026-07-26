<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Investor;
use App\Models\Project;
use App\Models\ProjectInvestment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectInvestmentController extends Controller
{
    public function index(Project $project): JsonResponse
    {
        return response()->json(
            ProjectInvestment::where('project_id', $project->id)
                ->with('investor:id,name,code')
                ->orderByDesc('is_company')->orderBy('id')
                ->get()
        );
    }

    public function store(Request $request, Project $project): JsonResponse
    {
        $data = $this->rules($request);
        $data['project_id'] = $project->id;
        $data = $this->resolveName($data);

        $row = ProjectInvestment::create($data);
        $this->syncTreasury($row);

        ActivityLog::log('created', 'Investment', "Added {$row->participant_name} to \"{$project->name}\" cap table", $project->id);

        return response()->json($row->load('investor:id,name,code'), 201);
    }

    public function update(Request $request, ProjectInvestment $investment): JsonResponse
    {
        $investment->update($this->resolveName($this->rules($request)));
        $this->syncTreasury($investment);

        ActivityLog::log('updated', 'Investment', "Updated cap-table row for {$investment->participant_name}", $investment->project_id);

        return response()->json($investment->load('investor:id,name,code'));
    }

    public function destroy(ProjectInvestment $investment): JsonResponse
    {
        $name = $investment->participant_name;
        \App\Models\TreasuryTransaction::where('investment_id', $investment->id)->delete();
        $investment->delete();

        ActivityLog::log('deleted', 'Investment', "Removed {$name} from a cap table", $investment->project_id);

        return response()->json(['message' => 'Deleted.']);
    }

    /**
     * The company funding a project's cap table is money leaving the General
     * Budget — keep exactly one treasury allocation in step with the row.
     */
    private function syncTreasury(ProjectInvestment $row): void
    {
        $existing = \App\Models\TreasuryTransaction::where('investment_id', $row->id)->first();

        if (! $row->is_company) {
            $existing?->delete();

            return;
        }

        $attrs = [
            'project_id' => $row->project_id,
            'direction' => 'out',
            'kind' => 'allocation',
            'status' => 'active',
            'amount' => $row->capital,
            'currency' => $row->currency,
            'rate' => $row->rate,
            'amount_base' => round((float) $row->capital * (float) $row->rate, 2),
            'tx_date' => now()->toDateString(),
            'note' => 'Company share in project cap table',
        ];

        $existing ? $existing->update($attrs) : \App\Models\TreasuryTransaction::create($attrs + ['investment_id' => $row->id]);
    }

    /** Cache participant_name from the company flag or the linked investor. */
    private function resolveName(array $data): array
    {
        if (! empty($data['is_company'])) {
            $data['investor_id'] = null;
            $data['participant_name'] = $data['participant_name'] ?? 'شرکت آریا مهندس‌زاده';
        } elseif (! empty($data['investor_id'])) {
            $investor = Investor::find($data['investor_id']);
            $data['participant_name'] = $investor?->name ?? ($data['participant_name'] ?? '—');
        }

        return $data;
    }

    private function rules(Request $request): array
    {
        return $request->validate([
            'investor_id' => ['nullable', 'integer', 'exists:investors,id'],
            'is_company' => ['boolean'],
            'participant_name' => ['nullable', 'string', 'max:255'],
            'capital' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:10'],
            'rate' => ['nullable', 'numeric', 'min:0'],
            'profit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'basis' => ['nullable', 'string', 'max:255'],
            'profit_received' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
