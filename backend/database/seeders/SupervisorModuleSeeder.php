<?php

namespace Database\Seeders;

use App\Models\CashAdvance;
use App\Models\Company;
use App\Models\Project;
use App\Models\PurchaseCategory;
use App\Models\PurchaseRequest;
use App\Models\SiteInvoice;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Slice 1 seed: trade categories, ready-made site roles (Supervisor / Engineer),
 * a couple of assigned field users, and one purchase request walked through the
 * full flow so the pages aren't empty. Runs inside the tenant context.
 */
class SupervisorModuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company) {
            return;
        }

        // 13-item starter category list (editable by admin).
        $cats = ['Cement', 'Rebar / Steel', 'Tiling', 'Piping / Plumbing', 'Electrical',
            'Paint', 'Wood / Carpentry', 'Tools', 'Fuel', 'Transport', 'Rental', 'Labor', 'Other'];
        foreach ($cats as $i => $name) {
            PurchaseCategory::firstOrCreate(
                ['company_id' => $company->id, 'name' => $name],
                ['sort' => $i, 'active' => true]
            );
        }

        // ── Ready-made roles with a complete, sensible slice of access each ──
        // Helpers: crud() = full list/create/edit/show/delete; view() = read-only.

        // Site Supervisor — the field. Raises purchase requests, uploads bill
        // photos, records attendance & workers, keeps the site diary.
        $supervisor = $this->role('Site Supervisor', array_merge(
            ['dashboard-list', 'project-list', 'project-show', 'purchase-category-list'],
            $this->crud('purchase-request'),
            $this->crud('site-invoice'),
            $this->crud('worker'),
            $this->crud('worker-attendance'),
            $this->crud('daily-log'),
            ['task-list', 'task-edit', 'attendance-list', 'attendance-create',
                'document-list', 'document-create', 'lift-list', 'lift-show', 'cash-advance-list'],
        ));

        // Site Engineer — runs their project and approves site spend.
        $engineer = $this->role('Site Engineer', array_merge(
            ['dashboard-list', 'project-list', 'project-show', 'purchase-category-list', 'purchase-approve'],
            $this->view('purchase-request'),
            $this->view('site-invoice'),
            $this->crud('task'), $this->crud('milestone'), $this->crud('lift'),
            $this->crud('change-order'), $this->crud('document'),
            ['site-list', 'site-create', 'site-edit',
                'worker-list', 'worker-show', 'worker-attendance-list', 'worker-attendance-show',
                'report-list', 'incident-list', 'incident-create', 'incident-show'],
        ));

        // Accountant — company-wide finance: money in & out, payroll, currency,
        // the Payment Center, and the books.
        $this->role('Accountant', array_merge(
            ['dashboard-list', 'project-list', 'project-show', 'report-list', 'all-projects', 'all-branches'],
            $this->crud('invoice'), $this->crud('receipt'), $this->crud('expense'),
            $this->crud('office-expense'), $this->crud('home-expense'), $this->crud('expense-budget'),
            $this->crud('currency'), $this->crud('exchange-rate'), $this->crud('treasury'),
            $this->crud('party'), $this->crud('party-transaction'), $this->crud('payment-request'),
            $this->view('purchase-request'), $this->view('site-invoice'), $this->view('contract'),
            $this->view('partner'), $this->view('investor'), $this->view('payroll'),
            $this->view('purchase-order'),
            ['cash-advance-list', 'cash-release', 'expense-approve',
                'payment-approve', 'payment-process',
                'change-order-list', 'change-order-show', 'change-order-approve'],
        ));

        // Storekeeper — the warehouse and procurement.
        $this->role('Storekeeper', array_merge(
            ['dashboard-list', 'project-list', 'project-show'],
            $this->crud('stock-item'), $this->crud('stock-movement'),
            $this->crud('supplier'), $this->crud('purchase-order'),
            $this->view('asset'), $this->view('purchase-request'),
            $this->view('site-invoice'), ['purchase-category-list'],
        ));

        // HR Officer — people, structure, payroll & leave.
        $this->role('HR Officer', array_merge(
            ['dashboard-list', 'report-list'],
            $this->crud('employee'), $this->crud('department'), $this->crud('designation'),
            $this->crud('attendance'), $this->crud('payroll'), $this->crud('leave'),
            $this->view('project'), $this->view('asset'),
        ));

        // President / Boss — sees and does everything.
        Role::firstOrCreate(['name' => 'President', 'guard_name' => 'web'])->syncPermissions(Permission::all());

        // Field Engineer — field support under the Site Engineer.
        $this->role('Field Engineer', array_merge(
            ['dashboard-list', 'project-list', 'project-show', 'site-list'],
            $this->crud('daily-log'), $this->crud('document'),
            ['task-list', 'task-edit', 'milestone-list',
                'purchase-request-list', 'purchase-request-create', 'purchase-request-show',
                'worker-list', 'worker-create', 'worker-attendance-list', 'worker-attendance-create',
                'lift-list', 'lift-create', 'lift-edit', 'lift-show',
                'incident-list', 'incident-create'],
        ));

        // Viewer — observes everything, changes nothing (list + show only).
        Role::firstOrCreate(['name' => 'Viewer', 'guard_name' => 'web'])->syncPermissions(
            Permission::where('name', 'like', '%-list')->orWhere('name', 'like', '%-show')->pluck('name')
        );

        $stadium = Project::where('company_id', $company->id)->where('name', 'like', '%ورزشگاه%')->first()
            ?? Project::where('company_id', $company->id)->orderBy('id')->first();
        if (! $stadium) {
            return;
        }

        // Two demo field users assigned to the stadium project.
        $naseer = User::withTrashed()->firstOrCreate(
            ['email' => 'supervisor@ariaherat.af'],
            ['name' => 'Naseer (Supervisor)', 'password' => Hash::make('password'),
                'company_id' => $company->id, 'current_company' => $company->id, 'type' => 'staff']
        );
        $farid = User::withTrashed()->firstOrCreate(
            ['email' => 'engineer@ariaherat.af'],
            ['name' => 'Eng. Farid', 'password' => Hash::make('password'),
                'company_id' => $company->id, 'current_company' => $company->id, 'type' => 'staff']
        );
        $naseer->companies()->syncWithoutDetaching([$company->id]);
        $farid->companies()->syncWithoutDetaching([$company->id]);
        $naseer->syncRoles([$supervisor->name]);
        $farid->syncRoles([$engineer->name]);
        $stadium->users()->syncWithoutDetaching([
            $naseer->id => ['site_role' => 'supervisor'],
            $farid->id => ['site_role' => 'engineer'],
        ]);

        if (PurchaseRequest::where('company_id', $company->id)->exists()) {
            return; // demo flow already seeded
        }

        $cement = PurchaseCategory::where('company_id', $company->id)->where('name', 'Cement')->first();

        // A fully closed request (raised → approved → advance → receipt → closed).
        $pr = PurchaseRequest::create([
            'project_id' => $stadium->id, 'user_id' => $naseer->id, 'category_id' => $cement?->id,
            'code' => 'PR-0001', 'title' => 'Cement for column footings',
            'items' => [['name' => 'Cement (bag)', 'qty' => 60, 'unit' => 'bag', 'est_price' => 500]],
            'estimated_total' => 30000, 'currency' => 'AFN', 'status' => 'closed',
            'approver_id' => $farid->id, 'decided_at' => now()->subDays(4), 'decision_note' => 'Approved — needed for slab.',
        ]);
        CashAdvance::create([
            'purchase_request_id' => $pr->id, 'amount_given' => 30000, 'currency' => 'AFN',
            'given_by' => 1, 'given_at' => now()->subDays(4), 'note' => 'Cash to Naseer.',
        ]);
        SiteInvoice::create([
            'project_id' => $stadium->id, 'purchase_request_id' => $pr->id, 'category_id' => $cement?->id,
            'source' => 'purchase', 'vendor' => 'صنعت سمنت هرات', 'actual_total' => 28800, 'currency' => 'AFN',
            'uploaded_by' => $naseer->id, 'invoice_date' => now()->subDays(4)->toDateString(),
        ]);

        // A pending request waiting on the engineer.
        PurchaseRequest::create([
            'project_id' => $stadium->id, 'user_id' => $naseer->id,
            'category_id' => PurchaseCategory::where('company_id', $company->id)->where('name', 'Tools')->value('id'),
            'code' => 'PR-0002', 'title' => 'Hand tools & shuttering nails',
            'items' => [['name' => 'Shuttering nails', 'qty' => 25, 'unit' => 'kg', 'est_price' => 120],
                ['name' => 'Trowel', 'qty' => 4, 'unit' => 'pcs', 'est_price' => 150]],
            'estimated_total' => 3600, 'currency' => 'AFN', 'status' => 'pending',
        ]);

        // Slice 2 demo — a registered crew and today's attendance.
        $crew = [
            ['name' => 'عبدالرحمن', 'father' => 'محمد نبی', 'trade' => 'Mason', 'wage' => 700],
            ['name' => 'نجیب‌الله', 'father' => 'عبدالقادر', 'trade' => 'Laborer', 'wage' => 450],
            ['name' => 'اسدالله', 'father' => 'گل احمد', 'trade' => 'Carpenter', 'wage' => 650],
            ['name' => 'وحیدالله', 'father' => 'نور محمد', 'trade' => 'Laborer', 'wage' => 450],
            ['name' => 'فرهاد', 'father' => 'شیرآغا', 'trade' => 'Steel fixer', 'wage' => 600],
        ];
        foreach ($crew as $i => $c) {
            $w = Worker::create([
                'project_id' => $stadium->id, 'code' => 'WKR-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => $c['name'], 'father_name' => $c['father'], 'trade' => $c['trade'],
                'default_wage' => $c['wage'], 'registered_by' => $naseer->id, 'active' => true,
            ]);
            // Today: most present, one absent — with Herat-ish coordinates.
            \App\Models\WorkerAttendance::create([
                'worker_id' => $w->id, 'project_id' => $stadium->id, 'work_date' => now()->toDateString(),
                'status' => $i === 3 ? 'absent' : 'present', 'task' => 'کار روی تریبیون شمالی',
                'day_rate' => $c['wage'], 'gps_lat' => 34.3529 + $i * 0.0001, 'gps_lng' => 62.2040 + $i * 0.0001,
                'recorded_by' => $naseer->id, 'signed_at' => now(),
            ]);
        }
    }

    private function role(string $name, array $permissions): Role
    {
        $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        // Only sync permissions that actually exist, so a typo never wipes a role.
        $existing = Permission::whereIn('name', array_unique($permissions))->pluck('name')->all();
        $role->syncPermissions($existing);

        return $role;
    }

    /** Full list/create/edit/show/delete for an entity. */
    private function crud(string $entity): array
    {
        return array_map(fn ($a) => "{$entity}-{$a}", ['list', 'create', 'edit', 'show', 'delete']);
    }

    /** Read-only list + show for an entity. */
    private function view(string $entity): array
    {
        return ["{$entity}-list", "{$entity}-show"];
    }
}
