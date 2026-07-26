<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\Asset;
use App\Models\DailySiteLog;
use App\Models\Investor;
use App\Models\Project;
use App\Models\ProjectAsset;
use App\Models\ProjectDocument;
use App\Models\ProjectInvestment;
use App\Models\ProjectMaterial;
use App\Models\ProjectMilestone;
use App\Models\ProjectSite;
use App\Models\ProjectTask;
use App\Models\Subcontractor;
use App\Models\SubcontractorPayment;
use App\Models\User;
use App\Support\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Fills the Herat football stadium with a full, realistic dataset — 30+ rows
 * in every dashboard section — so each pill (Financing, Work Breakdown,
 * Site Operations, Programme, Site Diary, Subcontracts, Plant & Materials,
 * Drawings & Documents) and the live Overview feed demo with real content.
 * Runs inside the tenant window set by DatabaseSeeder. Idempotent per block.
 */
class StadiumDemoSeeder extends Seeder
{
    private ?Project $stadium = null;

    private ?User $user = null;

    public function run(): void
    {
        $this->stadium = Project::where('name', 'ورزشگاه فوتبال هرات')->first();
        $this->user = User::first();
        if (! $this->stadium) {
            return;
        }

        $this->seedSites();
        $this->seedTasks();
        $this->seedMilestones();
        $this->seedDiary();
        $this->seedSubcontracts();
        $this->seedFinancing();
        $this->seedPlantAndMaterials();
        $this->seedDocuments();
        $this->seedActivity();
        $this->seedPartyAccounts();
        $this->seedProcurement();
    }

    /**
     * Procurement demo: suppliers, the warehouse (گدام) with on-hand stock,
     * one received PO (its goods raised the stock) and one still ordered,
     * plus consumption issued to the stadium.
     */
    private function seedProcurement(): void
    {
        if (\App\Models\Supplier::count() > 0) {
            return;
        }

        $suppliers = [];
        foreach ([
            ['شرکت سمنت هرات', 'materials'], ['فولاد فروشی برادران', 'materials'],
            ['مواد ساختمانی اتفاق', 'materials'], ['تیل و روغنیات پامیر', 'fuel'],
            ['تجهیزات برقی نور', 'equipment'], ['خدمات ترانسپورتی کاروان', 'services'],
        ] as $i => [$name, $cat]) {
            $suppliers[] = \App\Models\Supplier::create([
                'code' => 'SUP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => $name, 'category' => $cat, 'active' => true,
                'phone' => '079'.str_pad((string) (5000000 + $i * 613), 7, '0', STR_PAD_LEFT),
            ]);
        }

        $items = [];
        foreach ([
            ['سمنت پاکستانی', 'bag', 8000, 2000], ['سیخ ۱۲mm', 'ton', 120, 40],
            ['سیخ ۱۶mm', 'ton', 90, 30], ['ریگ دریایی', 'm³', 1500, 400],
            ['جغل ۱۹mm', 'm³', 1100, 300], ['تختهٔ فرمکاری', 'piece', 1400, 300],
            ['سیم جوش', 'kg', 400, 100], ['میخ ۵ سانتی', 'kg', 250, 50],
            ['واترپروف', 'm²', 2000, 500], ['دیزل', 'litre', 6000, 1500],
            ['پایپ PVC ۴ انچ', 'm', 900, 200], ['کیبل ۴×۱۶', 'm', 1800, 400],
        ] as $i => [$name, $unit, $qty, $min]) {
            $items[] = \App\Models\StockItem::create([
                'code' => 'STK-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => $name, 'unit' => $unit, 'quantity' => $qty, 'min_quantity' => $min,
            ]);
        }

        // A received PO — its lines already landed in the quantities above.
        $po1 = \App\Models\PurchaseOrder::create([
            'code' => 'PO-0001', 'supplier_id' => $suppliers[0]->id, 'project_id' => $this->stadium?->id,
            'order_date' => now()->subDays(20)->toDateString(), 'status' => 'received',
            'currency' => 'AFN', 'rate' => 1, 'notes' => 'خریداری سمنت و ریگ برای کانکریت تریبیون‌ها',
        ]);
        foreach ([[$items[0], 5000, 340], [$items[3], 800, 900]] as [$item, $qty, $price]) {
            $po1->items()->create([
                'stock_item_id' => $item->id, 'name' => $item->name, 'quantity' => $qty,
                'unit' => $item->unit, 'unit_price' => $price, 'line_total' => $qty * $price,
            ]);
            \App\Models\StockMovement::create([
                'stock_item_id' => $item->id, 'project_id' => $this->stadium?->id,
                'purchase_order_id' => $po1->id, 'user_id' => $this->user?->id,
                'direction' => 'in', 'kind' => 'purchase', 'quantity' => $qty,
                'movement_date' => now()->subDays(18)->toDateString(), 'note' => 'PO PO-0001',
            ]);
        }

        // A still-open order awaiting delivery.
        $po2 = \App\Models\PurchaseOrder::create([
            'code' => 'PO-0002', 'supplier_id' => $suppliers[1]->id, 'project_id' => $this->stadium?->id,
            'order_date' => now()->subDays(4)->toDateString(),
            'expected_date' => now()->addDays(6)->toDateString(), 'status' => 'ordered',
            'currency' => 'USD', 'rate' => 70, 'notes' => 'سفارش سیخ برای ستون‌های تریبیون شرقی',
        ]);
        $po2->items()->create([
            'stock_item_id' => $items[2]->id, 'name' => $items[2]->name, 'quantity' => 60,
            'unit' => 'ton', 'unit_price' => 620, 'line_total' => 37200,
        ]);

        // Consumption issued to the stadium site.
        foreach ([[$items[0], 1200, 30], [$items[3], 300, 15], [$items[9], 900, 7]] as [$item, $qty, $daysAgo]) {
            \App\Models\StockMovement::create([
                'stock_item_id' => $item->id, 'project_id' => $this->stadium?->id,
                'user_id' => $this->user?->id, 'direction' => 'out', 'kind' => 'consumption',
                'quantity' => $qty, 'movement_date' => now()->subDays($daysAgo)->toDateString(),
                'note' => 'مصرف ساحهٔ ورزشگاه',
            ]);
            $item->decrement('quantity', $qty);
        }
    }

    /**
     * Party accounts (حسابات) demo, mirroring the client's own example:
     * someone lends 50K and gets 20K back → 30K credit (we owe him);
     * we pay someone 100K who gave nothing → 100K debit (he owes us);
     * plus a bank, an exchange, and one pending promise.
     */
    private function seedPartyAccounts(): void
    {
        if (\App\Models\Party::count() > 0) {
            return;
        }

        $mk = function (int $seq, string $name, string $type, ?string $relation = null) {
            return \App\Models\Party::create([
                'code' => 'PTY-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'name' => $name, 'type' => $type, 'relation' => $relation,
                'phone' => '078'.str_pad((string) (4000000 + $seq * 733), 7, '0', STR_PAD_LEFT),
                'active' => true,
            ]);
        };

        $tx = function (\App\Models\Party $party, string $dir, float $amount, int $daysAgo, string $basis, string $status = 'confirmed', ?int $projectId = null, string $currency = 'USD') {
            // Lock the daily rate at entry, like the real form does.
            $rate = $currency === 'AFN' ? 1
                : (float) (\App\Models\ExchangeRate::where('currency_code', $currency)->orderByDesc('rate_date')->value('rate_to_base') ?? 70);
            $row = \App\Models\PartyTransaction::create([
                'party_id' => $party->id, 'project_id' => $projectId, 'user_id' => $this->user?->id,
                'direction' => $dir, 'status' => $status,
                'amount' => $amount, 'currency' => $currency, 'rate' => $rate, 'amount_base' => round($amount * $rate, 2),
                'tx_date' => now()->subDays($daysAgo)->toDateString(),
                'method' => 'cash', 'basis' => $basis, 'handled_by' => 'کریم رضایی (حساب‌دار)',
            ]);
            if ($status === 'confirmed') {
                \App\Models\TreasuryTransaction::create([
                    'party_transaction_id' => $row->id, 'project_id' => $projectId,
                    'direction' => $dir, 'kind' => $dir === 'in' ? 'loan_in' : 'loan_out', 'status' => 'active',
                    'amount' => $amount, 'currency' => $currency, 'rate' => $rate, 'amount_base' => round($amount * $rate, 2),
                    'tx_date' => $row->tx_date, 'note' => ($dir === 'in' ? 'From' : 'To')." party account: {$party->name}",
                ]);
            }

            return $row;
        };

        $qadir = $mk(1, 'حاجی عبدالودود', 'person', 'دوست نزدیک رئیس');
        $tx($qadir, 'in', 50_000, 40, 'قرض برای پروژهٔ ورزشگاه', 'confirmed', $this->stadium?->id);
        $tx($qadir, 'out', 20_000, 12, 'بازپرداخت قسمتی از قرض', 'confirmed', $this->stadium?->id); // → 30K USD credit
        $tx($qadir, 'in', 500_000, 6, 'حوالهٔ افغانی برای مصارف روزمرهٔ ساحه', 'confirmed', $this->stadium?->id, 'AFN'); // mixed-currency ledger

        $karim = $mk(2, 'محمد کریم توخی', 'person', 'قراردادی سابق');
        $tx($karim, 'out', 100_000, 25, 'قرض به همکار تجارتی'); // → 100K debit

        $bank = $mk(3, 'عزیزی بانک — شعبهٔ هرات', 'bank', 'بانک همکار');
        $tx($bank, 'in', 250_000, 60, 'اعتبار کوتاه‌مدت بانکی', 'confirmed', $this->stadium?->id);
        $tx($bank, 'out', 100_000, 8, 'بازپرداخت قسط اول اعتبار');

        $sarafi = $mk(4, 'صرافی شهزاده', 'exchange', null);
        $tx($sarafi, 'in', 40_000, 15, 'حواله از کابل برای مصارف ساحه', 'confirmed', $this->stadium?->id);

        $uncle = $mk(5, 'خانوادهٔ رئیس', 'relative', 'اقارب');
        $tx($uncle, 'in', 30_000, 5, 'وعدهٔ کمک — هنوز نرسیده', 'pending', $this->stadium?->id);
    }

    // ── Site Operations (30 zones) ──
    private function seedSites(): void
    {
        if ($this->stadium->sites()->count() >= 10) {
            return;
        }

        $engineers = ['انجنیر احمد', 'انجنیر فرید', 'انجنیر ویس', 'انجنیر مصطفی', 'انجنیر ذبیح'];
        $zones = [
            'تریبیون شمالی', 'تریبیون جنوبی', 'تریبیون شرقی', 'تریبیون غربی (VIP)',
            'زمین اصلی فوتبال', 'زمین تمرین ۱', 'زمین تمرین ۲', 'مسیر دوش (ترک دوش)',
            'پارکینگ شمالی', 'پارکینگ جنوبی', 'دروازهٔ ورودی اصلی', 'دروازهٔ VIP و رسانه‌ها',
            'ساختمان اداری', 'رختکن تیم میزبان', 'رختکن تیم مهمان', 'اتاق کنفرانس مطبوعاتی',
            'برج نورافگن ۱', 'برج نورافگن ۲', 'برج نورافگن ۳', 'برج نورافگن ۴',
            'سیستم آبرسانی و ذخیره', 'تصفیه‌خانهٔ فاضلاب', 'سب‌استیشن برق', 'جنراتورخانه',
            'گدام ساحه', 'دفتر انجنیری ساحه', 'کلینیک ساحه', 'کانتین کارگران',
            'سرک حلقوی داخلی', 'محوطهٔ سبز و باغبانی',
        ];

        foreach ($zones as $i => $zone) {
            ProjectSite::firstOrCreate(
                ['project_id' => $this->stadium->id, 'name' => $zone],
                ['location' => 'هرات، ناحیهٔ هفتم — ساحهٔ ورزشگاه', 'in_charge' => $engineers[$i % count($engineers)], 'active' => true]
            );
        }
    }

    // ── Work Breakdown (32 tasks, avg progress ≈ 36%) ──
    private function seedTasks(): void
    {
        if ($this->stadium->tasks()->count() >= 10) {
            return;
        }

        $crews = ['تیم کانکریت A', 'تیم کانکریت B', 'تیم آهن‌بندی', 'تیم فرمکاری', 'تیم برق', 'تیم نل‌دوانی', 'تیم سروی', 'تیم ماشین‌آلات'];
        // [phase, title, status, progress]
        $rows = [
            ['تهداب', 'پاک‌کاری و تسطیح ساحه', 'done', 100],
            ['تهداب', 'نقشه‌برداری و پیاده‌کردن نقشه', 'done', 100],
            ['تهداب', 'حفاری تهداب تریبیون شمالی', 'done', 100],
            ['تهداب', 'حفاری تهداب تریبیون جنوبی', 'done', 100],
            ['تهداب', 'کانکریت‌ریزی تهداب تریبیون شمالی', 'done', 100],
            ['تهداب', 'کانکریت‌ریزی تهداب تریبیون جنوبی', 'done', 100],
            ['تهداب', 'عایق‌کاری تهداب‌ها', 'done', 100],
            ['اسکلت', 'ستون‌های تریبیون شمالی — طبقهٔ اول', 'done', 100],
            ['اسکلت', 'ستون‌های تریبیون جنوبی — طبقهٔ اول', 'in_progress', 70],
            ['اسکلت', 'گادر و سقف تریبیون شمالی', 'in_progress', 55],
            ['اسکلت', 'آهن‌بندی پله‌های تماشاچیان شمالی', 'in_progress', 60],
            ['اسکلت', 'کانکریت پله‌های تماشاچیان شمالی', 'in_progress', 40],
            ['اسکلت', 'ستون‌های تریبیون شرقی', 'in_progress', 30],
            ['اسکلت', 'ستون‌های تریبیون غربی (VIP)', 'in_progress', 25],
            ['اسکلت', 'اسکلت فلزی سایبان تریبیون‌ها', 'todo', 0],
            ['تاسیسات', 'کیبل‌کشی برق اصلی از سب‌استیشن', 'in_progress', 45],
            ['تاسیسات', 'نصب پایپ‌های آبرسانی حلقوی', 'in_progress', 35],
            ['تاسیسات', 'کانال‌های زهکشی زمین فوتبال', 'todo', 0],
            ['تاسیسات', 'نصب برج‌های نورافگن', 'todo', 0],
            ['تاسیسات', 'سیستم آبیاری اتوماتیک چمن', 'todo', 0],
            ['تاسیسات', 'سیستم صوتی و تابلوی امتیاز', 'todo', 0],
            ['تاسیسات', 'شبکهٔ کمره‌های امنیتی', 'todo', 0],
            ['نازک‌کاری', 'دیوارهای رختکن‌ها', 'todo', 0],
            ['نازک‌کاری', 'کاشی‌کاری تشناب‌ها و حمام‌ها', 'todo', 0],
            ['نازک‌کاری', 'رنگمالی تریبیون‌ها', 'todo', 0],
            ['نازک‌کاری', 'نصب چوکی‌های تماشاچیان', 'todo', 0],
            ['نازک‌کاری', 'شیشه و المونیم ساختمان اداری', 'todo', 0],
            ['محوطه', 'جغل‌اندازی و آسفالت پارکینگ‌ها', 'todo', 0],
            ['محوطه', 'جدول‌کاری سرک حلقوی', 'todo', 0],
            ['محوطه', 'فنس‌کشی دور ساحه', 'in_progress', 50],
            ['محوطه', 'کشت چمن زمین اصلی', 'todo', 0],
            ['محوطه', 'سرسبزی و نهال‌شانی محوطه', 'todo', 0],
        ];

        $sites = $this->stadium->sites()->pluck('id')->all();
        foreach ($rows as $i => [$phase, $title, $status, $progress]) {
            ProjectTask::firstOrCreate(
                ['project_id' => $this->stadium->id, 'title' => $title],
                [
                    'phase' => $phase, 'status' => $status, 'progress' => $progress,
                    'priority' => $progress > 0 && $progress < 100 ? 'high' : 'medium',
                    'assignee' => $crews[$i % count($crews)],
                    'site_id' => $sites ? $sites[$i % count($sites)] : null,
                    'user_id' => $this->user?->id,
                    'due_date' => now()->addDays(14 + $i * 12)->toDateString(),
                ]
            );
        }
    }

    // ── Programme (30 milestones) ──
    private function seedMilestones(): void
    {
        if ($this->stadium->milestones()->count() >= 10) {
            return;
        }

        $rows = [
            ['عقد قرارداد و تسلیمی ساحه', -120, 'done', 100],
            ['موبیلایزیشن و ایجاد کمپ ساحه', -110, 'done', 100],
            ['سروی توپوگرافی و تست خاک', -100, 'done', 100],
            ['منظوری نقشه‌های اجرایی', -90, 'done', 100],
            ['تسطیح و آماده‌سازی ساحه', -80, 'done', 100],
            ['تکمیل حفاری تهداب‌ها', -60, 'done', 100],
            ['تکمیل کانکریت تهداب تریبیون شمالی', -40, 'done', 100],
            ['تکمیل کانکریت تهداب تریبیون جنوبی', -25, 'done', 100],
            ['تکمیل ستون‌های طبقهٔ اول — شمالی', -5, 'in_progress', 80],
            ['تکمیل ستون‌های طبقهٔ اول — جنوبی', 15, 'in_progress', 55],
            ['تکمیل اسکلت تریبیون شرقی', 45, 'in_progress', 25],
            ['تکمیل اسکلت تریبیون غربی (VIP)', 60, 'pending', 0],
            ['نصب گادرهای سقف شمالی', 75, 'pending', 0],
            ['تکمیل پله‌های تماشاچیان — همهٔ تریبیون‌ها', 90, 'pending', 0],
            ['تکمیل سب‌استیشن و برق اصلی', 105, 'pending', 0],
            ['تکمیل شبکهٔ آبرسانی', 120, 'pending', 0],
            ['تکمیل زهکشی زمین فوتبال', 135, 'pending', 0],
            ['نصب چهار برج نورافگن', 150, 'pending', 0],
            ['تکمیل سایبان فلزی تریبیون‌ها', 165, 'pending', 0],
            ['تکمیل رختکن‌ها و اتاق‌های فنی', 180, 'pending', 0],
            ['کاشی‌کاری و نازک‌کاری داخلی', 210, 'pending', 0],
            ['نصب چوکی‌های تماشاچیان (۲۰٬۰۰۰)', 240, 'pending', 0],
            ['تکمیل سیستم صوتی و تابلوی امتیاز', 255, 'pending', 0],
            ['کشت و تثبیت چمن زمین اصلی', 270, 'pending', 0],
            ['آسفالت پارکینگ‌ها و سرک حلقوی', 300, 'pending', 0],
            ['فنس‌کشی و دروازه‌های ورودی', 315, 'pending', 0],
            ['تست‌های برق، آب و ایمنی', 330, 'pending', 0],
            ['سرسبزی و محوطه‌سازی نهایی', 345, 'pending', 0],
            ['بازرسی نهایی و رفع نواقص', 360, 'pending', 0],
            ['افتتاح و تسلیمی به کارفرما', 380, 'pending', 0],
        ];

        foreach ($rows as [$title, $offsetDays, $status, $progress]) {
            ProjectMilestone::firstOrCreate(
                ['project_id' => $this->stadium->id, 'title' => $title],
                ['due_date' => now()->addDays($offsetDays)->toDateString(), 'status' => $status, 'progress' => $progress]
            );
        }
    }

    // ── Site Diary (35 daily logs) ──
    private function seedDiary(): void
    {
        if ($this->stadium->dailyLogs()->count() >= 10) {
            return;
        }

        $weather = ['آفتابی', 'آفتابی', 'ابری', 'بادی', 'گرم', 'نیمه‌ابری'];
        $work = [
            'کانکریت‌ریزی ستون‌های محور B تریبیون شمالی — ۴۵ متر مکعب.',
            'آهن‌بندی پله‌های تماشاچیان، ردیف ۱۲ تا ۱۸.',
            'فرمکاری ستون‌های تریبیون جنوبی، محور C.',
            'حفاری کانال زهکشی ضلع شرقی با اکسکاواتور.',
            'انتقال و انبار ۲۰۰۰ خریطه سمنت به گدام ساحه.',
            'تراکم‌کاری بستر پارکینگ شمالی با غلتک.',
            'جوشکاری اسکلت فلزی سایبان — قسمت اول.',
            'نصب پایپ آبرسانی قطر ۴ انچ، ۱۲۰ متر.',
            'کیبل‌کشی مسیر سب‌استیشن تا جنراتورخانه.',
            'قالب‌برداری ستون‌های هفتهٔ قبل و کیورینگ.',
            'تست کیوب کانکریت — نتایج مطابق مشخصات.',
            'بارگیری و خروج خاک اضافی، ۶۰ سرویس کمپرسی.',
            'ترمیم فنس موقت ضلع غربی پس از باد شدید.',
            'نصب داربست برای گادرهای سقف شمالی.',
        ];

        $sites = $this->stadium->sites()->pluck('id')->all();
        for ($i = 0; $i < 35; $i++) {
            $date = now()->subDays(42 - $i)->toDateString();
            DailySiteLog::firstOrCreate(
                ['project_id' => $this->stadium->id, 'log_date' => $date],
                [
                    'site_id' => $sites ? $sites[$i % count($sites)] : null,
                    'user_id' => $this->user?->id,
                    'labour_count' => 120 + (($i * 37) % 260),
                    'weather' => $weather[$i % count($weather)],
                    'work_done' => $work[$i % count($work)],
                ]
            );
        }
    }

    // ── Subcontracts (30 firms + payments) ──
    private function seedSubcontracts(): void
    {
        if ($this->stadium->subcontractors()->count() >= 10) {
            return;
        }

        // [name, trade, contract USD]
        $rows = [
            ['شرکت کانکریت هریرود', 'کانکریت‌ریزی', 1_850_000], ['گروپ آهن‌بندی برادران احمدی', 'آهن‌بندی', 940_000],
            ['تیم فرمکاری استاد نعیم', 'فرمکاری', 520_000], ['شرکت برق توحید', 'برق‌کاری', 1_200_000],
            ['نل‌دوانی اتفاق', 'نل‌دوانی', 680_000], ['جوشکاری صنعتی خراسان', 'جوشکاری اسکلت فلزی', 1_450_000],
            ['سنگ‌کاری هرات باستان', 'سنگ‌کاری', 390_000], ['کاشی‌کاری مهدوی', 'کاشی و سرامیک', 310_000],
            ['رنگمالی نوین', 'رنگمالی', 260_000], ['المونیم و شیشهٔ آریانا', 'شیشه و المونیم', 540_000],
            ['کف‌سازی صنعتی پامیر', 'کف‌سازی', 430_000], ['ایزولاسیون بام شرق', 'عایق‌کاری', 280_000],
            ['دروازه‌سازی فولاد هرات', 'دروازه و پنجرهٔ فلزی', 350_000], ['سیستم آبیاری سبزآب', 'آبیاری اتوماتیک', 220_000],
            ['چمن ورزشی سبزینه', 'چمن طبیعی ورزشی', 610_000], ['نورپردازی ستاره', 'نورافگن و روشنایی', 890_000],
            ['سیستم صوتی هما', 'صوتی و تابلوی امتیاز', 470_000], ['امنیت الکترونیک آسیا', 'کمره و کنترول دخول', 330_000],
            ['لفت و زینهٔ برقی البرز', 'لفت', 410_000], ['تهویهٔ مطبوع سرما', 'HVAC', 560_000],
            ['آسفالت هرات‌راه', 'آسفالت', 720_000], ['جدول‌کاری میثاق', 'جدول و پیاده‌رو', 190_000],
            ['فنس‌کشی حصار', 'فنس و حصارکشی', 240_000], ['داربست فلزی اتحاد', 'داربست', 160_000],
            ['حفاری غرب', 'حفاری و خاک‌برداری', 830_000], ['سروی دقیق', 'نقشه‌برداری', 140_000],
            ['باغبانی گلستان', 'محوطهٔ سبز', 180_000], ['نجاری چوب هنر', 'نجاری', 210_000],
            ['سقف فلزی آسمان', 'سایبان و سقف فلزی', 1_050_000], ['واترپروفینگ بارش', 'عایق بام و تهداب', 300_000],
        ];

        foreach ($rows as $i => [$name, $trade, $amount]) {
            $sub = Subcontractor::firstOrCreate(
                ['project_id' => $this->stadium->id, 'name' => $name],
                [
                    'trade' => $trade, 'contract_amount' => $amount, 'currency' => 'USD', 'active' => true,
                    'phone' => '079'.str_pad((string) (2000000 + $i * 917), 7, '0', STR_PAD_LEFT),
                    'scope' => "قرارداد {$trade} پروژهٔ ورزشگاه فوتبال هرات.",
                ]
            );

            // ~2/3 of firms already have an advance and/or progress payment.
            if ($sub->payments()->count() === 0 && $i % 3 !== 2) {
                SubcontractorPayment::create([
                    'subcontractor_id' => $sub->id, 'project_id' => $this->stadium->id, 'user_id' => $this->user?->id,
                    'payment_date' => now()->subDays(50 - $i)->toDateString(),
                    'kind' => 'advance', 'amount' => round($amount * 0.1), 'currency' => 'USD', 'rate' => 1,
                    'note' => 'پیش‌پرداخت ۱۰٪ قرارداد',
                ]);
                if ($i % 2 === 0) {
                    SubcontractorPayment::create([
                        'subcontractor_id' => $sub->id, 'project_id' => $this->stadium->id, 'user_id' => $this->user?->id,
                        'payment_date' => now()->subDays(12 - ($i % 10))->toDateString(),
                        'kind' => 'payment', 'amount' => round($amount * 0.15), 'currency' => 'USD', 'rate' => 1,
                        'note' => 'پرداخت پیشرفت کار — صورت‌حساب ۱',
                    ]);
                }
            }
        }
    }

    // ── Financing (30-row cap table; capital and profit % stay independent) ──
    private function seedFinancing(): void
    {
        if ($this->stadium->investments()->count() >= 10) {
            return;
        }

        $extraInvestors = [
            ['حاجی عبدالقدیر', 'individual', 900_000, 0.9], ['الحاج محمد نادر', 'individual', 850_000, 0.85],
            ['شرکت تجارتی فیض', 'company', 800_000, 0.8], ['انجنیر شکیب', 'individual', 750_000, 0.75],
            ['داکتر فرشته', 'individual', 700_000, 0.7], ['شرکت ساختمانی بلخ', 'company', 650_000, 0.65],
            ['حاجی روح‌الله', 'individual', 600_000, 0.6], ['خواجه سلطان‌احمد', 'individual', 550_000, 0.55],
            ['شرکت لوژستیکی سپین‌غر', 'company', 500_000, 0.5], ['محمد ابراهیم زاده', 'individual', 450_000, 0.45],
            ['بی‌بی حوا', 'individual', 400_000, 0.4], ['شرکت زراعتی سبزوار', 'company', 380_000, 0.38],
            ['حاجی گل‌محمد', 'individual', 360_000, 0.36], ['استاد عبدالحمید', 'individual', 340_000, 0.34],
            ['شرکت ترانسپورتی کاروان', 'company', 320_000, 0.32], ['محمد یوسف احمدی', 'individual', 300_000, 0.3],
            ['حاجی نثاراحمد', 'individual', 290_000, 0.29], ['شرکت صادراتی زعفران هرات', 'company', 280_000, 0.28],
            ['میرویس بارکزی', 'individual', 270_000, 0.27], ['داکتر عبدالباری', 'individual', 260_000, 0.26],
            ['شرکت نساجی ابریشم', 'company', 250_000, 0.25], ['حاجی شیرآغا', 'individual', 240_000, 0.24],
            ['انجنیر پرویز', 'individual', 230_000, 0.23], ['شرکت معدنی غوریان', 'company', 220_000, 0.22],
            ['عبدالرحیم رحیمی', 'individual', 210_000, 0.21], ['حاجی محمد عارف', 'individual', 200_000, 0.2],
            ['شاروالی هرات (سهم انکشافی)', 'government', 500_000, 0.5],
        ];

        $seq = Investor::withTrashed()->count();
        foreach ($extraInvestors as [$name, $type, $capital, $profit]) {
            $seq++;
            $investor = Investor::firstOrCreate(
                ['name' => $name],
                ['code' => 'INV-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT), 'type' => $type,
                    'phone' => '070'.str_pad((string) (7000000 + $seq * 131), 7, '0', STR_PAD_LEFT)]
            );

            ProjectInvestment::firstOrCreate(
                ['project_id' => $this->stadium->id, 'investor_id' => $investor->id],
                [
                    'is_company' => false, 'participant_name' => $name,
                    'capital' => $capital, 'currency' => 'USD', 'rate' => 1,
                    'profit_percent' => $profit, 'profit_received' => 0,
                    'basis' => 'سرمایه‌گذار خرد — قرارداد مشارکت',
                ]
            );
        }
    }

    // ── Plant & Materials (15 allocations + 21 materials) ──
    private function seedPlantAndMaterials(): void
    {
        if ($this->stadium->projectAssets()->count() < 5) {
            $plant = [
                ['بلدوزر', 2], ['اکسکاواتور (بیل مکانیکی)', 2], ['لودر', 1], ['گریدر', 1],
                ['غلتک (رولر)', 2], ['موتر کمپرسی', 4], ['میکسر کانکریت', 2], ['جرثقیل (کرین)', 1],
                ['تانکر آب', 1], ['جنراتور', 2], ['موتر باربری (ترک)', 6], ['پیکاپ', 3],
                ['بیل', 40], ['فرغون (چرخ دستی)', 50], ['ویبراتور کانکریت', 4],
            ];
            foreach ($plant as [$name, $qty]) {
                $asset = Asset::where('name', $name)->first();
                if (! $asset) {
                    continue;
                }
                $qty = min($qty, max(0, $asset->available));
                if ($qty === 0) {
                    continue;
                }
                ProjectAsset::create([
                    'project_id' => $this->stadium->id, 'asset_id' => $asset->id,
                    'quantity' => $qty, 'notes' => 'تخصیص برای ساحهٔ ورزشگاه',
                ]);
                $asset->increment('allocated', $qty);
            }
        }

        if ($this->stadium->materials()->count() < 5) {
            $materials = [
                ['سمنت پاکستانی', 24000, 'bag'], ['ریگ دریایی', 5500, 'm³'], ['جغل ۱۹mm', 4200, 'm³'],
                ['سیخ ۱۲mm', 380, 'ton'], ['سیخ ۱۴mm', 320, 'ton'], ['سیخ ۱۶mm', 260, 'ton'],
                ['سیخ ۲۰mm', 180, 'ton'], ['آجر پخته', 250000, 'piece'], ['بلاک سمنتی', 90000, 'piece'],
                ['سنگ گرانیت', 3800, 'm²'], ['کاشی و سرامیک', 5200, 'm²'], ['رنگ پلاستیک', 2600, 'litre'],
                ['چوب چهارتراش', 145, 'm³'], ['تختهٔ فرمکاری', 4800, 'piece'], ['سیم جوش', 950, 'kg'],
                ['واترپروف تهداب', 6800, 'm²'], ['پایپ PVC ۴ انچ', 2400, 'm'], ['پایپ فلزی گالوانیزه', 1800, 'm'],
                ['کیبل برق ۴×۱۶', 5600, 'm'], ['چراغ LED ضد آب', 850, 'piece'], ['پروفیل المونیم', 3200, 'm'],
            ];
            foreach ($materials as [$name, $qty, $unit]) {
                ProjectMaterial::firstOrCreate(
                    ['project_id' => $this->stadium->id, 'name' => $name],
                    ['quantity' => $qty, 'unit' => $unit]
                );
            }
        }
    }

    // ── Drawings & Documents (30 items with real preview images) ──
    private function seedDocuments(): void
    {
        if ($this->stadium->documents()->count() >= 10) {
            return;
        }

        // [title, category]
        $docs = [
            ['نقشهٔ عمومی سایت‌پلان', 'drawing'], ['نقشهٔ معماری تریبیون شمالی', 'drawing'],
            ['نقشهٔ معماری تریبیون جنوبی', 'drawing'], ['نقشهٔ ساختمانی تهداب‌ها', 'drawing'],
            ['نقشهٔ آهن‌بندی ستون‌ها', 'drawing'], ['نقشهٔ برق و روشنایی', 'drawing'],
            ['نقشهٔ آبرسانی و فاضلاب', 'drawing'], ['نقشهٔ زهکشی زمین فوتبال', 'drawing'],
            ['نقشهٔ سایبان فلزی', 'drawing'], ['نقشهٔ محوطه‌سازی و پارکینگ', 'drawing'],
            ['قرارداد اصلی با ریاست تربیت بدنی', 'contract'], ['قرارداد کانکریت هریرود', 'contract'],
            ['قرارداد جوشکاری خراسان', 'contract'], ['قرارداد برق توحید', 'contract'],
            ['ضمانت‌نامهٔ بانکی اجرا', 'contract'],
            ['جواز ساخت‌وساز شاروالی', 'permit'], ['منظوری زیست‌محیطی', 'permit'],
            ['جواز اتصال برق شهری', 'permit'], ['منظوری امنیتی ساحه', 'permit'],
            ['عکس پیشرفت — هفتهٔ ۱', 'photo'], ['عکس پیشرفت — هفتهٔ ۴', 'photo'],
            ['عکس پیشرفت — هفتهٔ ۸', 'photo'], ['عکس پیشرفت — هفتهٔ ۱۲', 'photo'],
            ['عکس کانکریت‌ریزی تهداب شمالی', 'photo'], ['عکس ستون‌های طبقهٔ اول', 'photo'],
            ['راپور ماهوار پیشرفت — ماه ۱', 'report'], ['راپور ماهوار پیشرفت — ماه ۲', 'report'],
            ['راپور ماهوار پیشرفت — ماه ۳', 'report'], ['راپور تست کیوب کانکریت', 'report'],
            ['راپور سروی توپوگرافی', 'report'],
        ];

        $palette = ['drawing' => '#4F46E5', 'contract' => '#1D4ED8', 'permit' => '#0D9488', 'photo' => '#EA580C', 'report' => '#7C3AED'];
        $dir = 'documents/'.Tenant::id().'/'.$this->stadium->id;

        foreach ($docs as $i => [$title, $category]) {
            $fileName = 'demo-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT).'.svg';
            $path = $dir.'/'.$fileName;
            $svg = $this->docSvg($title, $category, $palette[$category]);
            Storage::put($path, $svg);

            ProjectDocument::firstOrCreate(
                ['project_id' => $this->stadium->id, 'title' => $title],
                [
                    'user_id' => $this->user?->id, 'category' => $category, 'version' => 1,
                    'file_name' => $fileName, 'file_path' => $path,
                    'mime_type' => 'image/svg+xml', 'size' => strlen($svg),
                ]
            );
        }
    }

    /** A visible placeholder image so document thumbnails render for real. */
    private function docSvg(string $title, string $category, string $color): string
    {
        $safe = htmlspecialchars($title, ENT_QUOTES);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="640" height="440" viewBox="0 0 640 440">
  <rect width="640" height="440" fill="#F8FAFC"/>
  <rect width="640" height="86" fill="{$color}"/>
  <text x="320" y="42" text-anchor="middle" fill="#fff" font-family="Tahoma" font-size="20" font-weight="bold">آریا هرات مهندس زاده</text>
  <text x="320" y="68" text-anchor="middle" fill="#ffffffbb" font-family="Tahoma" font-size="13">ورزشگاه فوتبال هرات — {$category}</text>
  <g stroke="#CBD5E1" stroke-width="1"><line x1="60" y1="150" x2="580" y2="150"/><line x1="60" y1="210" x2="580" y2="210"/><line x1="60" y1="270" x2="580" y2="270"/><line x1="60" y1="330" x2="440" y2="330"/></g>
  <rect x="60" y="118" width="220" height="16" rx="4" fill="#E2E8F0"/>
  <rect x="60" y="178" width="340" height="16" rx="4" fill="#E2E8F0"/>
  <rect x="60" y="238" width="280" height="16" rx="4" fill="#E2E8F0"/>
  <text x="320" y="400" text-anchor="middle" fill="#334155" font-family="Tahoma" font-size="19" font-weight="bold">{$safe}</text>
</svg>
SVG;
    }

    // ── Overview live feed (32 recent activities) ──
    private function seedActivity(): void
    {
        if (ActivityLog::where('project_id', $this->stadium->id)->count() >= 10) {
            return;
        }

        $events = [
            ['created', 'DailyLog', 'روزنامچهٔ ساحه ثبت شد — کانکریت‌ریزی محور B'],
            ['updated', 'Task', 'پیشرفت «ستون‌های تریبیون جنوبی» به ۷۰٪ رسید'],
            ['created', 'SubPayment', 'پیش‌پرداخت شرکت کانکریت هریرود ثبت شد'],
            ['created', 'Document', 'راپور تست کیوب کانکریت آپلود شد'],
            ['updated', 'Milestone', '«تکمیل ستون‌های طبقهٔ اول — شمالی» به ۸۰٪ رسید'],
            ['created', 'Investment', 'حاجی عبدالقدیر به جدول سرمایه افزوده شد'],
            ['created', 'Task', 'وظیفهٔ «فنس‌کشی دور ساحه» ایجاد شد'],
            ['updated', 'Subcontractor', 'قرارداد نورپردازی ستاره تمدید شد'],
            ['created', 'Project', '۴ عراده موتر کمپرسی به ساحه تخصیص یافت'],
            ['created', 'DailyLog', 'روزنامچهٔ ساحه ثبت شد — آهن‌بندی پله‌ها'],
            ['created', 'Document', 'عکس پیشرفت هفتهٔ ۱۲ آپلود شد'],
            ['updated', 'Task', '«کیبل‌کشی برق اصلی» به ۴۵٪ رسید'],
            ['created', 'SubPayment', 'پرداخت پیشرفت کار جوشکاری خراسان ثبت شد'],
            ['created', 'Site', 'ساحهٔ «برج نورافگن ۳» فعال شد'],
            ['created', 'Project', 'مواد «سیخ ۱۶mm» به لست مواد افزوده شد'],
            ['updated', 'Milestone', '«تکمیل اسکلت تریبیون شرقی» آغاز شد'],
        ];

        for ($i = 0; $i < 32; $i++) {
            [$action, $module, $description] = $events[$i % count($events)];
            ActivityLog::create([
                'company_id' => Tenant::id(),
                'user_id' => $this->user?->id,
                'project_id' => $this->stadium->id,
                'action' => $action, 'module' => $module,
                'description' => $description,
                'created_at' => now()->subHours(3 + $i * 9),
                'updated_at' => now()->subHours(3 + $i * 9),
            ]);
        }
    }
}
