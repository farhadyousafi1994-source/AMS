<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetTransfer;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractMilestone;
use App\Models\ContractPayment;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\Notification;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Models\Project;
use App\Models\SafetyIncident;
use App\Models\TreasuryTransaction;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/**
 * Fills every remaining menu with realistic demo data so no screen is empty:
 * Contracts, Shareholder history, Asset transfers, Leaves,
 * Safety Incidents and Notifications.
 * Idempotent — guards on existing counts so a re-seed does not duplicate.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company) {
            return;
        }
        Tenant::set($company->id);

        $projects = Project::withoutGlobalScopes()->where('company_id', $company->id)->get();
        $admin = User::where('email', 'admin@ariaherat.af')->first();
        $branches = Branch::withoutGlobalScopes()->where('company_id', $company->id)->orderBy('id')->get();
        $herat = $branches->firstWhere('name', 'Herat Site Office') ?? $branches->first();
        $kabul = $branches->firstWhere('name', 'Kabul Head Office') ?? $branches->last();

        $this->contracts($projects, $admin);
        $this->shareholderHistory($company, $admin);
        $this->assetTransfers($herat, $kabul, $admin);
        $this->leaves($admin);
        $this->safety($projects, $admin);
        $this->notifications($company, $admin);
    }

    private function contracts($projects, $admin): void
    {
        if (Contract::count() > 0) {
            return;
        }
        $defs = [
            ['title' => 'Main Works Contract — Client', 'party' => 'Herat Municipality', 'ptype' => 'client', 'dir' => 'in', 'amount' => 48000000, 'status' => 'active'],
            ['title' => 'Asphalt Supply Agreement', 'party' => 'Herat Asphalt Co.', 'ptype' => 'supplier', 'dir' => 'out', 'amount' => 9600000, 'status' => 'active'],
            ['title' => 'Steel Fixing Subcontract', 'party' => 'Ustad Karim Group', 'ptype' => 'subcontractor', 'dir' => 'out', 'amount' => 3200000, 'status' => 'active'],
            ['title' => 'Hospital Fit-out Contract', 'party' => 'Ministry of Public Health', 'ptype' => 'client', 'dir' => 'in', 'amount' => 34000000, 'status' => 'draft'],
        ];
        foreach ($defs as $i => $d) {
            $project = $projects[$i % $projects->count()];
            $c = Contract::create([
                'project_id' => $project->id,
                'code' => 'CT-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'title' => $d['title'],
                'party_name' => $d['party'],
                'party_type' => $d['ptype'],
                'direction' => $d['dir'],
                'amount' => $d['amount'],
                'currency' => 'AFN',
                'rate' => 1,
                'status' => $d['status'],
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->addMonths(9)->toDateString(),
                'scope' => $d['title'].' for '.$project->name,
            ]);
            ContractMilestone::create(['contract_id' => $c->id, 'title' => 'Mobilization', 'amount' => round($d['amount'] * 0.2, 2), 'due_date' => now()->subMonth()->toDateString(), 'status' => 'done']);
            ContractMilestone::create(['contract_id' => $c->id, 'title' => 'Mid-term delivery', 'amount' => round($d['amount'] * 0.4, 2), 'due_date' => now()->addMonths(3)->toDateString(), 'status' => 'pending']);
            if ($d['status'] === 'active') {
                ContractPayment::create(['contract_id' => $c->id, 'user_id' => $admin?->id, 'payment_date' => now()->subWeeks(3)->toDateString(), 'kind' => 'advance', 'amount' => round($d['amount'] * 0.15, 2), 'currency' => 'AFN', 'rate' => 1, 'note' => 'Advance payment']);
            }
        }
    }

    private function shareholderHistory($company, $admin): void
    {
        if (PartnerTransaction::count() > 0) {
            return;
        }
        $partners = Partner::all();
        foreach ($partners as $i => $partner) {
            // Every partner deposits capital; the first also makes a small withdrawal.
            $this->partnerMove($company, $partner, $admin, 'deposit', 500000 + $i * 100000, now()->subMonths(2)->subDays($i)->toDateString());
            if ($i === 0) {
                $this->partnerMove($company, $partner, $admin, 'withdrawal', 150000, now()->subDays(10)->toDateString());
            }
        }
    }

    private function partnerMove($company, $partner, $admin, string $type, float $amount, string $date): void
    {
        $direction = $type === 'deposit' ? 'in' : 'out';
        $tx = TreasuryTransaction::create([
            'company_id' => $company->id,
            'direction' => $direction,
            'kind' => $type,
            'status' => 'active',
            'amount' => $amount,
            'currency' => 'AFN',
            'rate' => 1,
            'amount_base' => $amount,
            'tx_date' => $date,
            'note' => ucfirst($type).' — '.$partner->name,
        ]);
        PartnerTransaction::create([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'type' => $type,
            'amount' => $amount,
            'currency' => 'AFN',
            'rate' => 1,
            'amount_base' => $amount,
            'tx_date' => $date,
            'note' => ucfirst($type).' by '.$partner->name,
            'treasury_transaction_id' => $tx->id,
            'created_by' => $admin?->id,
        ]);
    }

    private function assetTransfers($herat, $kabul, $admin): void
    {
        if (AssetTransfer::count() > 0 || ! $herat || ! $kabul) {
            return;
        }
        $assets = Asset::where('category', 'heavy_equipment')->take(3)->get();
        foreach ($assets as $i => $asset) {
            AssetTransfer::create([
                'asset_id' => $asset->id,
                'from_branch_id' => $i % 2 === 0 ? $herat->id : $kabul->id,
                'to_branch_id' => $i % 2 === 0 ? $kabul->id : $herat->id,
                'quantity' => 1,
                'status' => $i === 0 ? 'pending' : 'approved',
                'reason' => 'Reallocated to active site',
                'requested_by' => $admin?->id,
                'approved_by' => $i === 0 ? null : $admin?->id,
            ]);
        }
    }

    private function leaves($admin): void
    {
        if (Leave::count() > 0) {
            return;
        }
        $employees = Employee::take(5)->get();
        $types = ['annual', 'sick', 'unpaid', 'annual', 'sick'];
        foreach ($employees as $i => $emp) {
            $start = now()->subDays(20 - $i * 3);
            $end = (clone $start)->addDays(2 + ($i % 3));
            Leave::create([
                'employee_id' => $emp->id,
                'type' => $types[$i % count($types)],
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => $start->diffInDays($end) + 1,
                'paid' => $types[$i % count($types)] !== 'unpaid',
                'status' => $i === 0 ? 'pending' : 'approved',
                'reason' => 'Personal / family',
                'approved_by' => $i === 0 ? null : $admin?->id,
            ]);
        }
    }

    private function safety($projects, $admin): void
    {
        if (SafetyIncident::count() > 0) {
            return;
        }
        $defs = [
            ['near_miss', 'medium', 'Scaffold plank slipped near Block A', 'open', 0],
            ['hazard', 'low', 'Exposed rebar without caps', 'action_pending', 0],
            ['incident', 'high', 'Worker minor hand injury — grinder', 'investigating', 1],
            ['accident', 'critical', 'Excavator tipped on soft slope', 'closed', 1],
            ['near_miss', 'low', 'Falling debris near walkway', 'closed', 0],
            ['hazard', 'medium', 'Fuel stored near hot works', 'open', 0],
        ];
        foreach ($defs as $i => $d) {
            $project = $projects[$i % $projects->count()];
            SafetyIncident::create([
                'project_id' => $project->id,
                'code' => 'INC-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'type' => $d[0],
                'severity' => $d[1],
                'title' => $d[2],
                'location' => $project->location,
                'incident_date' => now()->subDays(15 - $i * 2)->toDateString(),
                'injured_count' => $d[4],
                'lost_time_days' => $d[3] === 'closed' && $d[4] > 0 ? 3 : 0,
                'immediate_action' => 'Area cordoned off; supervisor notified.',
                'corrective_action' => $d[3] === 'closed' ? 'Toolbox talk held; controls added.' : null,
                'status' => $d[3],
                'reported_by' => $admin?->id,
                'reported_by_name' => 'Site Supervisor',
                'closed_by' => $d[3] === 'closed' ? $admin?->id : null,
                'closed_at' => $d[3] === 'closed' ? now()->subDays(5) : null,
                'closure_note' => $d[3] === 'closed' ? 'Resolved and verified on site.' : null,
            ]);
        }
    }

    private function notifications($company, $admin): void
    {
        if (Notification::count() > 0 || ! $admin) {
            return;
        }
        $defs = [
            ['approval', 'Purchase request awaiting approval', 'PR-0002 for shuttering nails needs your approval.', false, '/site/purchases'],
            ['safety', 'New safety incident logged', 'A high-severity incident was reported on site.', false, '/safety/incidents'],
            ['finance', 'Receipt recorded', 'A client receipt of 200,000 AFN was recorded.', true, '/finance/receipts'],
            ['hr', 'Leave request pending', 'An employee submitted a leave request for approval.', false, '/hr/leaves'],
        ];
        foreach ($defs as $d) {
            Notification::create([
                'company_id' => $company->id,
                // Company-scoped so every role sees it (user_id kept for authoring only).
                'user_id' => $admin->id,
                'type' => $d[0],
                'title' => $d[1],
                'body' => $d[2],
                'read_at' => $d[3] ? now()->subDay() : null,
                'data' => ['link' => $d[4]],
            ]);
        }
    }
}
