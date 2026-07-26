<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $company = Company::firstOrCreate(
            ['name_en' => 'Aria Herat Mohandes Zada'],
            [
                'name_fa' => 'شرکت ساختمانی و سرک سازی هرات آریا مهندس زاده',
                'abbreviation' => 'AHMZ',
                'business_type' => 'construction',
                'is_main' => true,
                'lang' => 'en',
                'calendar_type' => 'en',
                'currency' => 'AFN',
                'city' => 'Herat',
                'country' => 'Afghanistan',
            ]
        );

        Branch::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Kabul Head Office'],
            ['active' => true]
        );
        Branch::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Herat Site Office'],
            ['active' => true]
        );

        $admin = User::withTrashed()->firstOrCreate(
            ['email' => 'admin@ariaherat.af'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'current_company' => $company->id,
                'type' => 'admin',
            ]
        );

        $admin->companies()->syncWithoutDetaching([$company->id]);
        $admin->assignRole('Super Admin');

        // Platform Owner (VIP Root) — the immutable root of the SaaS platform.
        // Its authority comes from its email (config/platform.php), not a role.
        $owner = User::withTrashed()->firstOrCreate(
            ['email' => config('platform.owner_email', 'support@briskcodes.com')],
            [
                'name' => 'Platform Owner',
                'password' => Hash::make('password'),
                'company_id' => $company->id,
                'current_company' => $company->id,
                'type' => 'admin',
                'is_super_admin' => true,
            ]
        );
        $owner->companies()->syncWithoutDetaching([$company->id]);

        // Sample projects (one per business line) so the module isn't empty
        Tenant::set($company->id);

        $building = Project::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Aria Town — Block A'],
            [
                'code' => 'PRJ-001',
                'client_name' => 'Aria Town Housing',
                'type' => 'building',
                'contract_value' => 12500000,
                'currency' => 'AFN',
                'status' => 'active',
                'progress' => 40,
                'start_date' => now()->subMonths(3)->toDateString(),
                'end_date' => now()->addMonths(9)->toDateString(),
                'description' => 'Construction of a 5-storey administrative block.',
            ]
        );
        $building->sites()->firstOrCreate(['name' => 'Excavation Site'], ['in_charge' => 'Eng. Ahmad', 'active' => true]);
        $building->sites()->firstOrCreate(['name' => 'Concrete Pouring Site'], ['in_charge' => 'Eng. Nasir', 'active' => true]);
        $building->milestones()->firstOrCreate(['title' => 'Foundation complete'], ['status' => 'done', 'progress' => 100, 'due_date' => now()->subMonth()->toDateString()]);
        $building->milestones()->firstOrCreate(['title' => 'Structure to 3rd floor'], ['status' => 'in_progress', 'progress' => 45, 'due_date' => now()->addMonths(2)->toDateString()]);

        // Work breakdown — tasks grouped by phase, assigned to teams
        $building->tasks()->firstOrCreate(['title' => 'Site clearing & layout'], ['phase' => 'Foundation', 'assignee' => 'Team A', 'status' => 'done', 'priority' => 'high', 'progress' => 100]);
        $building->tasks()->firstOrCreate(['title' => 'Excavation for footings'], ['phase' => 'Foundation', 'assignee' => 'Team A', 'status' => 'done', 'priority' => 'high', 'progress' => 100]);
        $building->tasks()->firstOrCreate(['title' => 'Rebar & column footings'], ['phase' => 'Foundation', 'assignee' => 'Team B', 'status' => 'in_progress', 'priority' => 'high', 'progress' => 60, 'due_date' => now()->addWeeks(1)->toDateString()]);
        $building->tasks()->firstOrCreate(['title' => 'Ground-floor slab'], ['phase' => 'Structure', 'assignee' => 'Team B', 'status' => 'todo', 'priority' => 'medium', 'progress' => 0, 'due_date' => now()->addWeeks(3)->toDateString()]);
        $building->tasks()->firstOrCreate(['title' => 'Block work — ground floor'], ['phase' => 'Structure', 'assignee' => 'Ustad Karim', 'status' => 'todo', 'priority' => 'medium', 'progress' => 0]);

        $excavation = $building->sites()->where('name', 'Excavation Site')->first();
        $building->dailyLogs()->firstOrCreate(
            ['log_date' => now()->subDay()->toDateString(), 'site_id' => $excavation?->id],
            ['user_id' => $admin->id, 'weather' => 'Sunny', 'labour_count' => 22, 'work_done' => 'Poured 8 m³ concrete for column footings.', 'notes' => 'Cement stock running low, reorder soon.']
        );
        $building->dailyLogs()->firstOrCreate(
            ['log_date' => now()->toDateString(), 'site_id' => $excavation?->id],
            ['user_id' => $admin->id, 'weather' => 'Cloudy', 'labour_count' => 25, 'work_done' => 'Rebar tying for ground-floor slab.']
        );

        $sub = $building->subcontractors()->firstOrCreate(
            ['name' => 'Ustad Karim'],
            ['phone' => '0700-000000', 'trade' => 'Plaster', 'scope' => 'Internal plaster, ground + first floor.',
                'contract_amount' => 96000, 'currency' => 'AFN', 'active' => true]
        );
        $sub->payments()->firstOrCreate(
            ['payment_date' => now()->subWeeks(2)->toDateString(), 'kind' => 'advance'],
            ['project_id' => $building->id, 'user_id' => $admin->id, 'amount' => 5000, 'currency' => 'AFN', 'note' => 'Advance (مساعدی) before start.']
        );

        // HR master data (departments + designations) and the asset pool
        $this->call(ConstructionMasterSeeder::class);

        // Fill the Herat stadium with 30+ rows per dashboard section
        $this->call(StadiumDemoSeeder::class);

        // Finance foundation: base currency AFN + USD with a daily rate
        \App\Models\Currency::firstOrCreate(['company_id' => $company->id, 'code' => 'AFN'], ['name' => 'Afghan Afghani', 'symbol' => 'AFN', 'is_base' => true, 'active' => true]);
        \App\Models\Currency::firstOrCreate(['company_id' => $company->id, 'code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_base' => false, 'active' => true]);
        \App\Models\ExchangeRate::firstOrCreate(
            ['company_id' => $company->id, 'currency_code' => 'USD', 'rate_date' => now()->toDateString()],
            ['rate_to_base' => 70, 'user_id' => $admin->id]
        );
        // Sample expense in USD — rate locked at 70 (1000 USD => 70,000 AFN)
        \App\Models\Expense::firstOrCreate(
            ['company_id' => $company->id, 'category' => 'Materials', 'expense_date' => now()->subDays(3)->toDateString(), 'amount' => 1000, 'currency' => 'USD'],
            ['project_id' => $building->id, 'user_id' => $admin->id, 'payee' => 'Steel supplier', 'description' => 'Rebar purchase.', 'rate' => 70, 'amount_base' => 70000]
        );

        // Sample client invoice (progress billing) + a partial receipt
        $invoice = \App\Models\Invoice::firstOrCreate(
            ['company_id' => $company->id, 'invoice_no' => 'INV-0001'],
            [
                'project_id' => $building->id, 'user_id' => $admin->id,
                'client_name' => 'Aria Town Housing', 'invoice_date' => now()->subDays(10)->toDateString(),
                'due_date' => now()->addDays(20)->toDateString(), 'currency' => 'AFN', 'rate' => 1,
                'status' => 'sent', 'subtotal' => 500000, 'discount' => 0, 'tax' => 0,
                'total' => 500000, 'total_base' => 500000, 'notes' => 'Progress billing — foundation stage.',
            ]
        );
        if ($invoice->items()->count() === 0) {
            $invoice->items()->create(['description' => 'Foundation works (40%)', 'qty' => 1, 'unit_price' => 500000, 'amount' => 500000]);
        }
        \App\Models\Receipt::firstOrCreate(
            ['company_id' => $company->id, 'receipt_no' => 'RCP-0001'],
            [
                'invoice_id' => $invoice->id, 'project_id' => $building->id, 'user_id' => $admin->id,
                'receipt_date' => now()->subDays(5)->toDateString(), 'payer' => 'Aria Town Housing',
                'method' => 'bank', 'currency' => 'AFN', 'rate' => 1, 'amount' => 200000, 'amount_base' => 200000,
                'note' => 'First installment.',
            ]
        );
        $invoice->update(['status' => 'partial']);

        $road = Project::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Herat Ring Road — Asphalt'],
            [
                'code' => 'PRJ-002',
                'client_name' => 'Herat Municipality',
                'type' => 'road',
                'contract_value' => 48000000,
                'currency' => 'AFN',
                'status' => 'planning',
                'progress' => 5,
                'start_date' => now()->addWeeks(2)->toDateString(),
                'description' => 'Asphalt paving of the Herat ring road, phase 1.',
            ]
        );
        $road->sites()->firstOrCreate(['name' => 'Segment 1 (0–4 km)'], ['in_charge' => 'Eng. Karim', 'active' => true]);

        // Further demo projects across Herat & Kabul (coordinates are assigned
        // automatically by BranchSeeder so they all appear on the dashboard map).
        $moreProjects = [
            ['name' => 'Kabul–Herat Highway Rehabilitation', 'code' => 'PRJ-003', 'client' => 'Ministry of Public Works', 'type' => 'road', 'value' => 96000000, 'status' => 'active', 'progress' => 62, 'start' => now()->subMonths(6), 'end' => now()->addMonths(10), 'desc' => 'Rehabilitation and resurfacing of a 40 km highway section.'],
            ['name' => 'Herat Central Hospital', 'code' => 'PRJ-004', 'client' => 'Ministry of Public Health', 'type' => 'building', 'value' => 34000000, 'status' => 'active', 'progress' => 28, 'start' => now()->subMonths(2), 'end' => now()->addMonths(14), 'desc' => 'Construction of a 120-bed regional hospital.'],
            ['name' => 'Injil District School', 'code' => 'PRJ-005', 'client' => 'Ministry of Education', 'type' => 'building', 'value' => 8600000, 'status' => 'near_completion', 'progress' => 88, 'start' => now()->subMonths(8), 'end' => now()->addMonths(1), 'desc' => '12-classroom school with boundary wall.'],
            ['name' => 'Guzargah Bridge & Culverts', 'code' => 'PRJ-006', 'client' => 'Herat Municipality', 'type' => 'road', 'value' => 15400000, 'status' => 'on_hold', 'progress' => 15, 'start' => now()->subMonths(1), 'end' => now()->addMonths(7), 'desc' => 'Reinforced-concrete bridge and drainage culverts.'],
            ['name' => 'Kabul Office Tower', 'code' => 'PRJ-007', 'client' => 'Aria Holdings', 'type' => 'building', 'value' => 72000000, 'status' => 'planning', 'progress' => 3, 'start' => now()->addWeeks(3), 'end' => now()->addMonths(20), 'desc' => '8-storey commercial office tower in Kabul.'],
        ];
        foreach ($moreProjects as $mp) {
            $p = Project::firstOrCreate(
                ['company_id' => $company->id, 'name' => $mp['name']],
                [
                    'code' => $mp['code'], 'client_name' => $mp['client'], 'type' => $mp['type'],
                    'contract_value' => $mp['value'], 'currency' => 'AFN', 'status' => $mp['status'],
                    'progress' => $mp['progress'], 'start_date' => $mp['start']->toDateString(),
                    'end_date' => $mp['end']->toDateString(), 'description' => $mp['desc'],
                ]
            );
            $p->sites()->firstOrCreate(['name' => 'Main Site'], ['in_charge' => 'Eng. Team', 'active' => true]);
            $p->milestones()->firstOrCreate(['title' => 'Mobilization'], ['status' => 'done', 'progress' => 100, 'due_date' => now()->subWeeks(2)->toDateString()]);
        }

        // Supervisor & site-management module (categories, roles, demo flow)
        $this->call(SupervisorModuleSeeder::class);

        // Cross-project subcontractors (استادکاران) with weekly payments + ratings
        $this->call(TradesmenSeeder::class);

        // Rich employee profiles (studies, documents, attendance, salary history)
        $this->call(EmployeeProfileSeeder::class);

        // Concrete/earthwork lifts with inspection hold points on structural tasks
        $this->call(LiftsSeeder::class);

        // Office & Home expenses, four equal partners, home budget
        $this->call(OfficeHomeExpenseSeeder::class);

        // Change Orders (variations) — revise a project's contract value
        $this->call(ChangeOrderSeeder::class);

        // Multi-branch: create Herat + Kabul branches and assign projects/assets
        $this->call(BranchSeeder::class);

        // A demo login for every staff role (all password "password")
        $this->call(StaffUsersSeeder::class);

        // Fill every remaining menu with demo data (contracts, shareholder history,
        // asset transfers, leaves, safety, notifications)
        $this->call(LookupSeeder::class);
        $this->call(DemoDataSeeder::class);
        $this->call(PaymentCenterSeeder::class);

        // Dari display names for the standard roles (English name stays the key).
        $roleFa = [
            'Super Admin' => 'مدیر کل', 'President' => 'رئیس', 'Viewer' => 'فقط مشاهده',
            'Accountant' => 'محاسب', 'Storekeeper' => 'گدام‌دار', 'Field Engineer' => 'انجنیر ساحه',
            'Site Engineer' => 'انجنیر ساحه', 'Site Supervisor' => 'سرپرست ساحه',
        ];
        foreach ($roleFa as $name => $fa) {
            \Spatie\Permission\Models\Role::where('name', $name)->whereNull('name_fa')->update(['name_fa' => $fa]);
        }

        // Fingerprint subsystem — a ready-to-use software device + enabled policy.
        if ($company = \App\Models\Company::where('abbreviation', 'AHMZ')->first()) {
            Tenant::set($company->id);
            \App\Models\FingerprintDevice::firstOrCreate(
                ['company_id' => $company->id, 'name' => 'Office Reader (Simulator)'],
                ['brand' => 'simulator', 'model' => 'SIM-1', 'connection' => 'simulator', 'status' => 'online', 'active' => true, 'is_default' => true, 'last_seen_at' => now()]
            );
            \App\Models\FingerprintSetting::firstOrCreate(
                ['company_id' => $company->id],
                ['enabled' => true, 'enforcement' => 'optional', 'allow_override' => true, 'fallback_when_unavailable' => true, 'min_quality' => 40]
            );
        }

        Tenant::clear();
    }
}
