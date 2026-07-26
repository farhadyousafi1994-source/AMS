<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Lookup;
use App\Support\Tenant;
use Illuminate\Database\Seeder;

/** Seeds the Options Registry with bilingual defaults for every dropdown group. */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company) {
            return;
        }
        Tenant::set($company->id);

        foreach ($this->groups() as $group => $items) {
            foreach (array_values($items) as $i => $it) {
                Lookup::withTrashed()->updateOrCreate(
                    ['company_id' => $company->id, 'group' => $group, 'code' => $it[0]],
                    ['label_en' => $it[1], 'label_fa' => $it[2], 'sort_order' => $i, 'active' => true, 'is_system' => true, 'deleted_at' => null]
                );
            }
        }
    }

    private function groups(): array
    {
        return [
            'project_type' => [
                ['building', 'Building Construction', 'ساختمانی'],
                ['road', 'Road Building', 'سرک‌سازی'],
                ['bridge', 'Bridge', 'پل'],
                ['infrastructure', 'Infrastructure', 'زیربنایی'],
                ['water', 'Water & Sanitation', 'آبرسانی'],
            ],
            'project_status' => [
                ['planning', 'Planning', 'پلان‌گذاری'],
                ['awaiting_funding', 'Awaiting Funding', 'در انتظار تمویل'],
                ['active', 'Active', 'فعال'],
                ['on_hold', 'On Hold', 'متوقف'],
                ['near_completion', 'Near Completion', 'نزدیک به تکمیل'],
                ['completed', 'Completed', 'تکمیل‌شده'],
                ['handover', 'Handover', 'تحویل‌دهی'],
                ['cancelled', 'Cancelled', 'لغوشده'],
            ],
            'task_phase' => [
                ['foundation', 'Foundation', 'تهداب'],
                ['structure', 'Structure', 'اسکلت'],
                ['finishing', 'Finishing', 'تکمیل‌کاری'],
                ['mep', 'MEP', 'تأسیسات'],
                ['general', 'General', 'عمومی'],
            ],
            'priority' => [
                ['low', 'Low', 'پایین'],
                ['medium', 'Medium', 'متوسط'],
                ['high', 'High', 'بالا'],
                ['urgent', 'Urgent', 'عاجل'],
            ],
            'unit' => [
                ['m', 'Meter (m)', 'متر'],
                ['m2', 'Square meter (m²)', 'متر مربع'],
                ['m3', 'Cubic meter (m³)', 'متر مکعب'],
                ['km', 'Kilometer (km)', 'کیلومتر'],
                ['ton', 'Ton', 'تُن'],
                ['kg', 'Kilogram (kg)', 'کیلوگرام'],
                ['bag', 'Bag', 'بوری'],
                ['no', 'Number (no)', 'عدد'],
                ['ls', 'Lump sum', 'مقطوع'],
                ['day', 'Day', 'روز'],
                ['hour', 'Hour', 'ساعت'],
                ['liter', 'Liter', 'لیتر'],
            ],
            'drawing_category' => [
                ['drawing', 'Drawing', 'نقشه'],
                ['contract', 'Contract', 'قرارداد'],
                ['permit', 'Permit', 'جواز'],
                ['photo', 'Photo', 'عکس'],
                ['report', 'Report', 'راپور'],
                ['other', 'Other', 'سایر'],
            ],
            'payment_method' => [
                ['cash', 'Cash', 'نقد'],
                ['bank', 'Bank', 'بانک'],
                ['hawala', 'Hawala', 'حواله'],
                ['card', 'Card', 'کارت'],
                ['cheque', 'Cheque', 'چک'],
                ['other', 'Other', 'سایر'],
            ],
            'party_type' => [
                ['client', 'Client', 'مشتری'],
                ['supplier', 'Supplier', 'تهیه‌کننده'],
                ['subcontractor', 'Subcontractor', 'قراردادی فرعی'],
                ['lender', 'Lender', 'قرض‌دهنده'],
                ['bank', 'Bank', 'بانک'],
                ['exchange', 'Exchange (Sarai)', 'صرافی'],
                ['relative', 'Relative', 'اقارب'],
                ['other', 'Other', 'سایر'],
            ],
            'worker_trade' => [
                ['mason', 'Mason', 'معمار'],
                ['laborer', 'Laborer', 'کارگر'],
                ['carpenter', 'Carpenter', 'نجار'],
                ['steel_fixer', 'Steel Fixer', 'آهنگر'],
                ['electrician', 'Electrician', 'برق‌کار'],
                ['plumber', 'Plumber', 'نل‌دوان'],
                ['painter', 'Painter', 'رنگمال'],
                ['operator', 'Operator', 'اپراتور'],
            ],
            'fuel_type' => [
                ['diesel', 'Diesel', 'دیزل'],
                ['petrol', 'Petrol', 'پترول'],
                ['gas', 'Gas', 'گاز'],
            ],
            'leave_type' => [
                ['annual', 'Annual', 'سالانه'],
                ['sick', 'Sick', 'مریضی'],
                ['unpaid', 'Unpaid', 'بدون معاش'],
                ['maternity', 'Maternity', 'زایمان'],
                ['emergency', 'Emergency', 'اضطراری'],
            ],
            'incident_type' => [
                ['hazard', 'Hazard', 'خطر'],
                ['near_miss', 'Near Miss', 'نزدیک به حادثه'],
                ['incident', 'Incident', 'حادثه'],
                ['accident', 'Accident', 'سانحه'],
            ],
            'incident_severity' => [
                ['low', 'Low', 'کم'],
                ['medium', 'Medium', 'متوسط'],
                ['high', 'High', 'بالا'],
                ['critical', 'Critical', 'بحرانی'],
            ],
            'expense_category' => [
                ['office_rent', 'Office Rent', 'کرایه دفتر'],
                ['utilities', 'Utility Bills', 'بل‌های خدماتی'],
                ['groceries', 'Groceries', 'مواد خوراکه'],
                ['maintenance', 'Maintenance', 'ترمیمات'],
                ['transport', 'Transport', 'ترانسپورت'],
                ['stationery', 'Stationery', 'قرطاسیه'],
                ['other', 'Other', 'سایر'],
            ],
            'province' => $this->provinces(),
            // ── Assets & procurement ──
            'asset_category' => [
                ['heavy_machinery', 'Heavy Machinery', 'ماشین‌آلات سنگین'],
                ['vehicles', 'Vehicles', 'وسایط نقلیه'],
                ['tools', 'Tools', 'ابزار'],
                ['equipment', 'Equipment', 'تجهیزات'],
            ],
            'asset_condition' => [
                ['new', 'New', 'نو'],
                ['good', 'Good', 'خوب'],
                ['fair', 'Fair', 'متوسط'],
                ['needs_repair', 'Needs Repair', 'نیاز به ترمیم'],
            ],
            // ── Site diary ──
            'weather' => [
                ['sunny', 'Sunny', 'آفتابی'],
                ['cloudy', 'Cloudy', 'ابری'],
                ['rainy', 'Rainy', 'بارانی'],
                ['windy', 'Windy', 'بادی'],
                ['snow', 'Snow', 'برفی'],
                ['hot', 'Hot', 'گرم'],
                ['cold', 'Cold', 'سرد'],
            ],
            'change_order_reason' => [
                ['owner_request', 'Owner request', 'درخواست مالک'],
                ['design_change', 'Design change', 'تغییر دیزاین'],
                ['site_condition', 'Site condition', 'وضعیت ساحه'],
                ['error', 'Error / omission', 'اشتباه / از قلم‌افتادگی'],
            ],
            // ── HR ──
            'gender' => [
                ['male', 'Male', 'مرد'],
                ['female', 'Female', 'زن'],
            ],
            'marital_status' => [
                ['single', 'Single', 'مجرد'],
                ['married', 'Married', 'متأهل'],
            ],
            'employment_type' => [
                ['permanent', 'Permanent', 'دائمی'],
                ['contract', 'Contract', 'قراردادی'],
                ['daily_wage', 'Daily Wage', 'روزمزد'],
            ],
        ];
    }

    private function provinces(): array
    {
        $p = [
            ['herat', 'Herat', 'هرات'], ['kabul', 'Kabul', 'کابل'], ['kandahar', 'Kandahar', 'کندهار'],
            ['balkh', 'Balkh', 'بلخ'], ['nangarhar', 'Nangarhar', 'ننگرهار'], ['kunduz', 'Kunduz', 'کندز'],
            ['baghlan', 'Baghlan', 'بغلان'], ['badakhshan', 'Badakhshan', 'بدخشان'], ['takhar', 'Takhar', 'تخار'],
            ['ghazni', 'Ghazni', 'غزنی'], ['helmand', 'Helmand', 'هلمند'], ['farah', 'Farah', 'فراه'],
            ['badghis', 'Badghis', 'بادغیس'], ['ghor', 'Ghor', 'غور'], ['bamyan', 'Bamyan', 'بامیان'],
            ['parwan', 'Parwan', 'پروان'], ['kapisa', 'Kapisa', 'کاپیسا'], ['wardak', 'Maidan Wardak', 'میدان وردک'],
            ['logar', 'Logar', 'لوگر'], ['paktia', 'Paktia', 'پکتیا'], ['paktika', 'Paktika', 'پکتیکا'],
            ['khost', 'Khost', 'خوست'], ['laghman', 'Laghman', 'لغمان'], ['kunar', 'Kunar', 'کنر'],
            ['nuristan', 'Nuristan', 'نورستان'], ['samangan', 'Samangan', 'سمنگان'], ['sar_e_pul', 'Sar-e Pul', 'سرپل'],
            ['jowzjan', 'Jowzjan', 'جوزجان'], ['faryab', 'Faryab', 'فاریاب'], ['zabul', 'Zabul', 'زابل'],
            ['uruzgan', 'Uruzgan', 'ارزگان'], ['daykundi', 'Daykundi', 'دایکندی'], ['nimroz', 'Nimroz', 'نیمروز'],
            ['panjshir', 'Panjshir', 'پنجشیر'],
        ];

        return $p;
    }
}
