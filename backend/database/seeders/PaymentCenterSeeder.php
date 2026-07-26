<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PaymentApproval;
use App\Models\PaymentApprovalRule;
use App\Models\PaymentRequest;
use App\Models\Project;
use App\Models\Tradesman;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/** Seeds configurable approval rules and a linked demo Payment Center queue. */
class PaymentCenterSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company) {
            return;
        }
        Tenant::set($company->id);

        // ── Configurable approval rules (best-practice defaults) ──
        $rules = [
            ['name' => 'Standard payments (any)', 'type' => null, 'min_amount' => 0, 'max_amount' => 100000, 'levels' => ['Accountant'], 'sort_order' => 10],
            ['name' => 'Large payments', 'type' => null, 'min_amount' => 100000, 'max_amount' => 500000, 'levels' => ['Site Supervisor', 'President'], 'sort_order' => 20],
            ['name' => 'High-value payments', 'type' => null, 'min_amount' => 500000, 'max_amount' => null, 'levels' => ['Accountant', 'Site Supervisor', 'President'], 'sort_order' => 30],
            ['name' => 'Investor withdrawals', 'type' => 'investor_withdrawal', 'min_amount' => 0, 'max_amount' => null, 'levels' => ['President'], 'sort_order' => 5],
            ['name' => 'Payroll', 'type' => 'salary', 'min_amount' => 0, 'max_amount' => null, 'levels' => ['Accountant'], 'sort_order' => 6],
            ['name' => 'Procurement', 'type' => 'procurement', 'min_amount' => 0, 'max_amount' => null, 'levels' => ['Site Supervisor', 'Accountant'], 'sort_order' => 7],
        ];
        foreach ($rules as $r) {
            PaymentApprovalRule::firstOrCreate(
                ['company_id' => $company->id, 'name' => $r['name']],
                $r + ['active' => true]
            );
        }

        if (PaymentRequest::where('company_id', $company->id)->exists()) {
            return; // demo queue already seeded
        }

        $project = Project::where('company_id', $company->id)->orderBy('id')->first();
        $project2 = Project::where('company_id', $company->id)->orderBy('id')->skip(1)->first() ?? $project;
        $emp = Employee::where('company_id', $company->id)->first();
        $trades = Tradesman::where('company_id', $company->id)->first();

        // ── Linked demo requests across modules & statuses ──
        $seed = [
            ['type' => 'salary', 'payee' => $emp?->full_name ?? 'Eng. Farid', 'payee_type' => Employee::class, 'payee_id' => $emp?->id, 'project_id' => null, 'amount' => 45000, 'priority' => 'normal', 'module' => 'HR', 'notes' => 'Monthly salary'],
            ['type' => 'subcontractor', 'payee' => $trades?->name ?? 'استاد ولی', 'payee_type' => Tradesman::class, 'payee_id' => $trades?->id, 'project_id' => $project?->id, 'amount' => 120000, 'priority' => 'high', 'module' => 'Subcontractors', 'notes' => 'Plaster works stage 2'],
            ['type' => 'material', 'payee' => 'صنعت سمنت هرات', 'project_id' => $project?->id, 'amount' => 280000, 'priority' => 'high', 'module' => 'Procurement', 'notes' => 'Cement — 400 bags'],
            ['type' => 'supplier', 'payee' => 'Herat Steel Traders', 'project_id' => $project2?->id, 'amount' => 620000, 'priority' => 'urgent', 'module' => 'Procurement', 'notes' => 'Rebar delivery'],
            ['type' => 'investor_withdrawal', 'payee' => 'Haji Rahim (Investor)', 'project_id' => $project?->id, 'amount' => 350000, 'priority' => 'normal', 'module' => 'Investors', 'notes' => 'Profit withdrawal request'],
            ['type' => 'office_expense', 'payee' => 'Aria Office', 'project_id' => null, 'amount' => 18000, 'priority' => 'low', 'module' => 'Finance', 'notes' => 'Utilities & stationery'],
            ['type' => 'asset', 'payee' => 'Kabul Machinery', 'project_id' => $project2?->id, 'amount' => 900000, 'priority' => 'high', 'module' => 'Assets', 'notes' => 'Concrete mixer purchase'],
            ['type' => 'petty_cash', 'payee' => 'Site petty cash', 'project_id' => $project?->id, 'amount' => 12000, 'priority' => 'normal', 'module' => 'Finance', 'notes' => 'Site incidentals'],
        ];

        $no = 1;
        foreach ($seed as $s) {
            $pr = PaymentRequest::create([
                'company_id' => $company->id,
                'request_no' => 'PAY-'.str_pad((string) $no++, 5, '0', STR_PAD_LEFT),
                'type' => $s['type'],
                'payee_name' => $s['payee'],
                'payee_type' => $s['payee_type'] ?? null,
                'payee_id' => $s['payee_id'] ?? null,
                'project_id' => $s['project_id'],
                'currency' => 'AFN', 'rate' => 1,
                'requested_amount' => $s['amount'],
                'priority' => $s['priority'],
                'status' => 'pending', 'current_level' => 1,
                'source_module' => $s['module'],
                'notes' => $s['notes'],
                'needed_by' => now()->addDays(random_int(-2, 7)),
            ]);
            $this->buildWorkflow($pr, $company->id);
        }

        // Nudge a couple through the workflow so the demo shows every state.
        $this->advanceFirstLevel(PaymentRequest::where('request_no', 'PAY-00001')->first()); // → approved (single level)
        $this->markPaid(PaymentRequest::where('request_no', 'PAY-00006')->first());          // → paid
    }

    private function buildWorkflow(PaymentRequest $pr, int $companyId): void
    {
        $base = $pr->baseAmount();
        $rule = PaymentApprovalRule::where('active', true)
            ->where(fn ($q) => $q->whereNull('type')->orWhere('type', $pr->type))
            ->where('min_amount', '<=', $base)
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $base))
            ->orderByRaw('CASE WHEN type IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('min_amount')
            ->first();
        $levels = $rule?->levels ?? [];
        if (empty($levels)) {
            $pr->update(['status' => 'approved', 'approved_amount' => $pr->requested_amount]);

            return;
        }
        foreach ($levels as $i => $role) {
            PaymentApproval::create(['company_id' => $companyId, 'payment_request_id' => $pr->id, 'level' => $i + 1, 'role' => $role, 'status' => 'pending']);
        }
    }

    private function advanceFirstLevel(?PaymentRequest $pr): void
    {
        if (! $pr) {
            return;
        }
        $step = $pr->approvals()->where('level', $pr->current_level)->first();
        $step?->update(['status' => 'approved', 'decided_at' => now(), 'note' => 'Approved']);
        $next = $pr->approvals()->where('level', '>', $pr->current_level)->orderBy('level')->first();
        if ($next) {
            $pr->update(['current_level' => $next->level]);
        } else {
            $pr->update(['status' => 'approved', 'approved_amount' => $pr->requested_amount]);
        }
    }

    private function markPaid(?PaymentRequest $pr): void
    {
        if (! $pr) {
            return;
        }
        $pr->approvals()->update(['status' => 'approved', 'decided_at' => now()]);
        $pr->update(['status' => 'paid', 'approved_amount' => $pr->requested_amount, 'paid_amount' => $pr->requested_amount, 'payment_method' => 'cash', 'paid_at' => now()]);
    }
}
