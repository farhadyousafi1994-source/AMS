<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Investor;
use App\Models\Project;
use App\Models\ProjectInvestment;
use Illuminate\Database\Seeder;

/**
 * Seeds HR master data (departments + designations) and the company asset
 * pool in Persian. Runs inside the tenant context set by DatabaseSeeder,
 * so company_id is auto-filled by the BelongsToCompany trait.
 */
class ConstructionMasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDepartments();
        $this->seedAssets();
        $this->seedEmployees();
        $this->seedInvestors();
        $this->seedStadiumProject();
        $this->seedTreasury();
    }

    /**
     * General Budget demo mirroring the client's example: a 100M opening
     * balance, the stadium's 20M company share drawn against it, and a 15M
     * project receipt parked as reserved until the project completes.
     */
    private function seedTreasury(): void
    {
        if (\App\Models\TreasuryTransaction::count() > 0) {
            return;
        }

        $stadium = Project::where('name', 'ورزشگاه فوتبال هرات')->first();

        \App\Models\TreasuryTransaction::create([
            'direction' => 'in', 'kind' => 'deposit', 'status' => 'active',
            'amount' => 100_000_000, 'currency' => 'USD', 'rate' => 1, 'amount_base' => 100_000_000,
            'tx_date' => now()->subMonths(6)->toDateString(),
            'note' => 'سرمایهٔ عمومی شرکت',
        ]);

        if ($stadium) {
            $companyRow = $stadium->investments()->where('is_company', true)->first();
            \App\Models\TreasuryTransaction::create([
                'project_id' => $stadium->id, 'investment_id' => $companyRow?->id,
                'direction' => 'out', 'kind' => 'allocation', 'status' => 'active',
                'amount' => 20_000_000, 'currency' => 'USD', 'rate' => 1, 'amount_base' => 20_000_000,
                'tx_date' => now()->subMonths(4)->toDateString(),
                'note' => 'Company share in project cap table',
            ]);
            \App\Models\TreasuryTransaction::create([
                'project_id' => $stadium->id,
                'direction' => 'in', 'kind' => 'project_receipt', 'status' => 'reserved',
                'amount' => 15_000_000, 'currency' => 'USD', 'rate' => 1, 'amount_base' => 15_000_000,
                'tx_date' => now()->subMonth()->toDateString(),
                'note' => 'قسط اول پروژهٔ ورزشگاه — تا ختم پروژه ذخیره',
            ]);
        }
    }

    /**
     * Flagship demo: the football stadium — a USD 100M contract whose cap
     * table is deliberately underfunded (raised 45M, 55M gap) and has 20% of
     * profit still unallocated, so both funding meters show a live shortfall.
     * The company itself is the lead participant.
     */
    private function seedStadiumProject(): void
    {
        $project = Project::firstOrCreate(
            ['name' => 'ورزشگاه فوتبال هرات'],
            [
                'code' => 'AHMZ-'.str_pad((string) (Project::count() + 1), 3, '0', STR_PAD_LEFT),
                'client_name' => 'ریاست تربیت بدنی و ورزش هرات',
                'location' => 'هرات، ناحیهٔ هفتم',
                'type' => 'building',
                'contract_value' => 100_000_000,
                'currency' => 'USD',
                'status' => 'active',
                'progress' => 35,
                'start_date' => now()->subMonths(4)->toDateString(),
                'end_date' => now()->addMonths(20)->toDateString(),
                'description' => 'اعمار ورزشگاه فوتبال معیاری با ظرفیت ۲۰٬۰۰۰ تماشاچی.',
            ]
        );

        if ($project->investments()->count() > 0) {
            return;
        }

        $ahmad = Investor::where('name', 'حاجی احمد')->first();
        $ali = Investor::where('name', 'انجنیر علی')->first();

        // [investor_id, is_company, participant_name, capital, profit_percent, basis]
        $rows = [
            [null, true, 'شرکت آریا مهندس‌زاده', 20_000_000, 50, 'مدیریت اجرایی و ضمانت پروژه'],
            [$ahmad?->id, false, 'حاجی احمد', 15_000_000, 10, 'سرمایه‌گذار عمده'],
            [$ali?->id, false, 'انجنیر علی', 10_000_000, 20, 'سرمایه و نظارت تخنیکی'],
        ];
        foreach ($rows as [$investorId, $isCompany, $name, $capital, $profit, $basis]) {
            ProjectInvestment::create([
                'project_id' => $project->id,
                'investor_id' => $investorId,
                'is_company' => $isCompany,
                'participant_name' => $name,
                'capital' => $capital,
                'currency' => 'USD',
                'rate' => 1,
                'profit_percent' => $profit,
                'basis' => $basis,
                'profit_received' => 0,
            ]);
        }
    }

    private function seedInvestors(): void
    {
        $list = [
            ['حاجی احمد', 'individual', '0700123456'],
            ['انجنیر علی', 'individual', '0700234567'],
            ['شرکت عمران', 'company', '0700345678'],
            ['داکتر نجیب', 'individual', '0700456789'],
        ];
        $seq = 0;
        foreach ($list as [$name, $type, $phone]) {
            $seq++;
            Investor::firstOrCreate(
                ['name' => $name],
                ['code' => 'INV-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT), 'type' => $type, 'phone' => $phone]
            );
        }
    }

    private function seedEmployees(): void
    {
        $dsg = fn (string $title) => Designation::where('title', $title)->first();
        $vehicle = Asset::where('name', 'موتر باربری هاوو')->first();

        $seq = 0;
        $people = [
            ['نصیر احمدی', 'رئیس', 'permanent', 'active', null, 120000],
            ['فرید یوسفی', 'مدیر پروژه', 'permanent', 'active', null, 90000],
            ['انجنیر احمد', 'انجنیر ساحه', 'permanent', 'active', 'جواز انجنیری سیول', 75000],
            ['کریم رضایی', 'حساب‌دار', 'permanent', 'active', null, 45000],
            ['نجیب‌الله', 'سوپروایزر', 'contract', 'active', null, 38000],
            ['گل‌آقا', 'دریور', 'permanent', 'active', 'لایسنس درجه اول', 25000],
        ];
        foreach ($people as [$name, $title, $type, $status, $license, $salary]) {
            $seq++;
            $designation = $dsg($title);
            Employee::firstOrCreate(
                ['full_name' => $name],
                [
                    'code' => 'EMP-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                    'phone' => '070'.str_pad((string) (1000000 + $seq), 7, '0', STR_PAD_LEFT),
                    'gender' => 'male', 'nationality' => 'افغان',
                    'department_id' => $designation?->department_id,
                    'designation_id' => $designation?->id,
                    'employment_type' => $type, 'status' => $status,
                    'join_date' => now()->subMonths(6)->toDateString(),
                    'license' => $license, 'basic_salary' => $salary, 'salary_currency' => 'AFN',
                    'payment_method' => 'cash',
                    'assigned_vehicle_id' => $title === 'دریور' ? $vehicle?->id : null,
                ]
            );
        }
    }

    private function seedDepartments(): void
    {
        $tree = [
            'مدیریت' => ['رئیس', 'مدیر پروژه'],
            'انجنیری' => ['انجنیر ساحه', 'انجنیر سیول', 'سروییر'],
            'مالی' => ['مدیر مالی', 'حساب‌دار', 'تحویل‌دار'],
            'منابع بشری و اداری' => ['آفیسر منابع بشری', 'آفیسر اداری'],
            'تدارکات و گدام' => ['آفیسر تدارکات', 'گدام‌دار'],
            'ساحه و عملیات' => ['سوپروایزر', 'فورمن', 'معمار', 'سیخ‌کار'],
            'ترانسپورت و ماشین‌آلات' => ['دریور', 'اپراتور ماشین‌آلات سنگین', 'میخانیک'],
        ];

        foreach ($tree as $dept => $titles) {
            $department = Department::firstOrCreate(['name' => $dept], ['active' => true]);
            foreach ($titles as $title) {
                Designation::firstOrCreate(
                    ['title' => $title, 'department_id' => $department->id],
                    ['active' => true]
                );
            }
        }
    }

    private function seedAssets(): void
    {
        // [name, category, quantity_total, allocated, unit, status, location]
        $byCount = [
            // ماشین‌آلات سنگین (Heavy Equipment)
            ['بلدوزر', 'heavy_equipment', 5, 2, 'piece', 'available', 'گدام مرکزی'],
            ['اکسکاواتور (بیل مکانیکی)', 'heavy_equipment', 4, 1, 'piece', 'available', 'گدام مرکزی'],
            ['لودر', 'heavy_equipment', 3, 1, 'piece', 'available', 'گدام مرکزی'],
            ['گریدر', 'heavy_equipment', 2, 0, 'piece', 'available', 'گدام مرکزی'],
            ['غلتک (رولر)', 'heavy_equipment', 3, 1, 'piece', 'available', 'گدام مرکزی'],
            ['موتر کمپرسی', 'heavy_equipment', 8, 3, 'piece', 'available', 'گدام مرکزی'],
            ['میکسر کانکریت', 'heavy_equipment', 4, 2, 'piece', 'available', 'ساحهٔ ورزشگاه'],
            ['جرثقیل (کرین)', 'heavy_equipment', 2, 0, 'piece', 'maintenance', 'گدام مرکزی'],
            ['تانکر آب', 'heavy_equipment', 3, 1, 'piece', 'available', 'گدام مرکزی'],
            ['جنراتور', 'heavy_equipment', 6, 2, 'piece', 'available', 'گدام مرکزی'],
            ['فنیشر آسفالت', 'heavy_equipment', 1, 0, 'piece', 'available', 'گدام مرکزی'],
            ['کمپرسور هوا', 'heavy_equipment', 2, 0, 'piece', 'available', 'گدام مرکزی'],
            ['ماشین قیرپاش', 'heavy_equipment', 1, 0, 'piece', 'available', 'گدام مرکزی'],
            // وسایط نقلیه (Vehicles)
            ['موتر باربری (ترک)', 'vehicle', 40, 10, 'piece', 'available', 'گدام مرکزی'],
            ['پیکاپ', 'vehicle', 12, 4, 'piece', 'available', 'گدام مرکزی'],
            ['موتر سرویس', 'vehicle', 6, 2, 'piece', 'available', 'دفتر مرکزی'],
            ['موترسایکل', 'vehicle', 15, 5, 'piece', 'available', 'دفتر مرکزی'],
            ['تانکر تیل', 'vehicle', 3, 1, 'piece', 'available', 'گدام مرکزی'],
            // ابزار (Tools)
            ['بیل', 'tool', 150, 40, 'piece', 'available', 'گدام مرکزی'],
            ['کلنگ', 'tool', 100, 30, 'piece', 'available', 'گدام مرکزی'],
            ['فرغون (چرخ دستی)', 'tool', 200, 60, 'piece', 'available', 'گدام مرکزی'],
            ['داربست', 'tool', 300, 100, 'set', 'available', 'گدام مرکزی'],
            ['قالب فلزی', 'tool', 250, 80, 'piece', 'available', 'گدام مرکزی'],
            ['قالب چوبی', 'tool', 180, 50, 'piece', 'available', 'گدام مرکزی'],
            ['ویبراتور کانکریت', 'tool', 12, 4, 'piece', 'available', 'ساحهٔ ورزشگاه'],
            ['بیلچه', 'tool', 90, 20, 'piece', 'available', 'گدام مرکزی'],
            ['تراز', 'tool', 40, 10, 'piece', 'available', 'گدام مرکزی'],
            ['متر', 'tool', 60, 15, 'piece', 'available', 'گدام مرکزی'],
            ['ماله', 'tool', 120, 30, 'piece', 'available', 'گدام مرکزی'],
            ['سطل', 'tool', 200, 40, 'piece', 'available', 'گدام مرکزی'],
            // تجهیزات (Equipment)
            ['دستگاه جوش', 'equipment', 15, 5, 'piece', 'available', 'گدام مرکزی'],
            ['دریل', 'equipment', 25, 8, 'piece', 'available', 'گدام مرکزی'],
            ['سنگ‌فرز', 'equipment', 20, 6, 'piece', 'available', 'گدام مرکزی'],
            ['پمپ آب', 'equipment', 10, 3, 'piece', 'available', 'گدام مرکزی'],
            ['چراغ ساحه', 'equipment', 30, 10, 'piece', 'available', 'ساحهٔ ورزشگاه'],
        ];

        $seq = 0;
        foreach ($byCount as [$name, $cat, $total, $alloc, $unit, $status, $loc]) {
            $seq++;
            Asset::firstOrCreate(
                ['name' => $name],
                [
                    'code' => 'AST-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                    'category' => $cat, 'tracking' => 'count', 'quantity_total' => $total,
                    'allocated' => $alloc, 'unit' => $unit, 'status' => $status, 'location' => $loc,
                    'currency' => 'AFN',
                ]
            );
        }

        // A few per-unit flagship machines (own serial + maintenance history)
        $perUnit = [
            ['بلدوزر کاترپیلر D6', 'heavy_equipment', 'KDR-4471', 'good', 'available', 'گدام مرکزی', 1],
            ['اکسکاواتور کوماتسو PC220', 'heavy_equipment', 'PC-2210', 'good', 'in_use', 'ساحهٔ ورزشگاه', 1],
            ['موتر باربری هاوو', 'vehicle', 'پلیت ۳۴-۵۶۷', 'good', 'in_use', 'ساحهٔ ورزشگاه', 1],
        ];
        foreach ($perUnit as [$name, $cat, $serial, $cond, $status, $loc, $alloc]) {
            $seq++;
            $asset = Asset::firstOrCreate(
                ['name' => $name, 'serial' => $serial],
                [
                    'code' => 'AST-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                    'category' => $cat, 'tracking' => 'unit', 'quantity_total' => 1,
                    'allocated' => $alloc, 'condition' => $cond, 'status' => $status,
                    'location' => $loc, 'currency' => 'AFN',
                ]
            );
            if ($asset->maintenanceLogs()->count() === 0) {
                $asset->maintenanceLogs()->create([
                    'log_date' => now()->subMonth()->toDateString(),
                    'work_type' => 'سرویس عمومی', 'cost' => 12000, 'currency' => 'AFN',
                    'description' => 'تعویض روغن و فلتر.',
                ]);
            }
        }
    }
}
