<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Project;
use App\Models\Subcontractor;
use App\Models\SubcontractorPayment;
use App\Models\Tradesman;
use App\Models\WorkMeasurement;
use Illuminate\Database\Seeder;

/**
 * Demo cross-project subcontractors (استادکاران): people who work on several
 * projects, each with weekly payments and work measurements, plus a fingerprint
 * id so the payout-scan lookup can be demonstrated.
 */
class TradesmenSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company || Tradesman::where('company_id', $company->id)->exists()) {
            return;
        }

        $projects = Project::where('company_id', $company->id)->orderBy('id')->take(3)->get();
        if ($projects->isEmpty()) {
            return;
        }

        $people = [
            ['name' => 'استاد کریم', 'father' => 'محمد عمر', 'trade' => 'پلستر و رنگ', 'fp' => 'FP-1001', 'rate' => 120, 'unit' => 'm2'],
            ['name' => 'استاد نظیر', 'father' => 'عبدالحق', 'trade' => 'سنگ‌کاری و بلاک', 'fp' => 'FP-1002', 'rate' => 90, 'unit' => 'm2'],
            ['name' => 'استاد شفیع', 'father' => 'گل‌آقا', 'trade' => 'آهنگری (سیخ‌بندی)', 'fp' => 'FP-1003', 'rate' => 15, 'unit' => 'kg'],
            ['name' => 'استاد ولی', 'father' => 'نیک‌محمد', 'trade' => 'کاشی‌کاری', 'fp' => 'FP-1004', 'rate' => 200, 'unit' => 'm2'],
        ];

        foreach ($people as $i => $p) {
            $t = Tradesman::create([
                'company_id' => $company->id,
                'code' => 'SUB-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'name' => $p['name'], 'father_name' => $p['father'], 'trade' => $p['trade'],
                'fingerprint_id' => $p['fp'], 'default_rate' => $p['rate'], 'rate_unit' => $p['unit'],
                'start_date' => now()->subMonths(8)->toDateString(), 'phone' => '07'.rand(10000000, 99999999),
                'active' => true,
            ]);

            // Engage on the first 1–3 projects and pay them week by week.
            $onProjects = $projects->take($i === 0 ? 3 : (($i % 2) + 1));
            foreach ($onProjects as $prj) {
                $contract = [1200000, 900000, 1500000, 800000][$i] + $prj->id * 50000;
                $eng = Subcontractor::create([
                    'company_id' => $company->id, 'project_id' => $prj->id, 'tradesman_id' => $t->id,
                    'name' => $p['name'], 'trade' => $p['trade'], 'contract_amount' => $contract,
                    'currency' => 'AFN', 'active' => true,
                ]);

                // ~8 weekly payments + one advance.
                SubcontractorPayment::create([
                    'company_id' => $company->id, 'subcontractor_id' => $eng->id, 'project_id' => $prj->id,
                    'payment_date' => now()->subWeeks(9)->toDateString(), 'kind' => 'advance',
                    'amount' => 50000, 'currency' => 'AFN', 'note' => 'مساعدی آغاز کار',
                ]);
                for ($w = 8; $w >= 1; $w--) {
                    SubcontractorPayment::create([
                        'company_id' => $company->id, 'subcontractor_id' => $eng->id, 'project_id' => $prj->id,
                        'payment_date' => now()->subWeeks($w)->toDateString(), 'kind' => 'payment',
                        'amount' => rand(30, 80) * 1000, 'currency' => 'AFN', 'note' => 'پرداخت هفته‌وار',
                    ]);
                }

                // A work measurement (qty × rate).
                $qty = rand(120, 480);
                WorkMeasurement::create([
                    'company_id' => $company->id, 'tradesman_id' => $t->id, 'project_id' => $prj->id,
                    'measure_date' => now()->subWeeks(2)->toDateString(), 'description' => 'اندازه‌گیری کار انجام‌شده',
                    'unit' => $p['unit'], 'quantity' => $qty, 'unit_price' => $p['rate'],
                    'amount' => $qty * $p['rate'],
                ]);

                // Immutable per-project performance rating (feedback).
                $comments = [
                    'کار با کیفیت و به‌وقت تحویل داد.',
                    'کیفیت خوب بود اما کمی تأخیر داشت.',
                    'رعایت ایمنی و نظم عالی در ساحه.',
                    'کار قناعت‌بخش، نیاز به نظارت بیشتر داشت.',
                ];
                $stars = [5, 4, 5, 3, 4][$i % 5];
                \App\Models\TradesmanRating::create([
                    'company_id' => $company->id, 'tradesman_id' => $t->id, 'project_id' => $prj->id,
                    'stars' => $stars, 'quality' => min(5, $stars), 'timeliness' => max(3, $stars - 1),
                    'safety' => min(5, $stars + ($stars < 5 ? 1 : 0)),
                    'comment' => $comments[($i + $prj->id) % count($comments)],
                    'rated_by_name' => 'انجنیر فرید (انجنیر ساحه)',
                ]);
            }
        }
    }
}
