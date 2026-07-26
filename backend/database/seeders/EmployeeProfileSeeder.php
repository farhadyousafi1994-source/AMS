<?php

namespace Database\Seeders;

use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEducation;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Fills the rich employee profile with demo data: studies, specializations,
 * documents, project assignments, attendance and two payroll runs so the
 * salary-history tab isn't empty.
 */
class EmployeeProfileSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company || EmployeeEducation::where('company_id', $company->id)->exists()) {
            return;
        }

        $projects = Project::where('company_id', $company->id)->orderBy('id')->pluck('id')->all();
        $employees = Employee::where('company_id', $company->id)->get();
        if ($employees->isEmpty()) {
            return;
        }

        // Two recent payroll runs shared by everyone.
        $runs = [];
        foreach ([1, 2] as $back) {
            $period = now()->subMonths($back)->format('Y-m');
            $runs[] = PayrollRun::firstOrCreate(
                ['company_id' => $company->id, 'period' => $period],
                ['status' => 'paid', 'currency' => 'AFN']
            );
        }

        $profiles = [
            'رئیس' => ['edu' => ['لیسانس', 'اقتصاد', 'پوهنتون هرات'], 'spec' => ['مدیریت پروژه', 'قراردادها', 'مالی']],
            'انجنیر ساحه' => ['edu' => ['لیسانس', 'انجنیری سیول', 'پوهنتون کابل'], 'spec' => ['طراحی سازه', 'نظارت ساحه', 'کنترول کیفیت']],
            'حساب‌دار' => ['edu' => ['لیسانس', 'حسابداری', 'پوهنتون هرات'], 'spec' => ['حسابداری', 'گزارش‌دهی مالی']],
            'سوپروایزر' => ['edu' => ['بکلوریا', null, 'لیسهٔ سلطان'], 'spec' => ['مدیریت کارگران', 'حاضری', 'تدارکات ساحه']],
            'دریور' => ['edu' => ['بکلوریا', null, 'لیسهٔ حربی'], 'spec' => ['رانندگی وسایط سنگین']],
        ];

        foreach ($employees as $i => $emp) {
            $title = $emp->designation?->title ?? '';
            $prof = $profiles[$title] ?? ['edu' => ['بکلوریا', null, 'لیسه'], 'spec' => ['کار عمومی ساحه']];

            $emp->update([
                'specializations' => $prof['spec'],
                'assigned_projects' => array_slice($projects, 0, min(2, count($projects))),
                'dob' => now()->subYears(28 + $i)->toDateString(),
                'tazkira' => $emp->tazkira ?: '13'.str_pad((string) (1400 + $i), 8, '0'),
            ]);

            EmployeeEducation::create([
                'company_id' => $company->id, 'employee_id' => $emp->id,
                'degree' => $prof['edu'][0], 'field' => $prof['edu'][1], 'institution' => $prof['edu'][2],
                'year_from' => (string) (2008 + $i), 'year_to' => (string) (2012 + $i), 'grade' => 'خوب',
            ]);

            // Documents (metadata; files uploaded through the UI).
            EmployeeDocument::create(['company_id' => $company->id, 'employee_id' => $emp->id, 'doc_type' => 'national_id', 'title' => 'تذکرهٔ تابعیت', 'number' => $emp->tazkira]);
            if (in_array($title, ['رئیس', 'انجنیر ساحه', 'حساب‌دار'], true)) {
                EmployeeDocument::create(['company_id' => $company->id, 'employee_id' => $emp->id, 'doc_type' => 'degree', 'title' => 'سند تحصیلی — '.$prof['edu'][0]]);
            }
            if ($emp->license) {
                EmployeeDocument::create(['company_id' => $company->id, 'employee_id' => $emp->id, 'doc_type' => 'license', 'title' => $emp->license]);
            }

            // Attendance: last ~24 weekdays, mostly present.
            for ($d = 24; $d >= 1; $d--) {
                $date = now()->subDays($d);
                if ($date->isFriday()) {
                    continue;
                }
                $status = ($d % 11 === 0) ? 'absent' : (($d % 17 === 0) ? 'leave' : 'present');
                AttendanceRecord::firstOrCreate(
                    ['company_id' => $company->id, 'employee_id' => $emp->id, 'att_date' => $date->toDateString()],
                    ['status' => $status]
                );
            }

            // Salary history: an item in each run, with a standard breakdown.
            foreach ($runs as $run) {
                $basic = (float) ($emp->basic_salary ?? 0);
                $allow = round($basic * 0.10, 2);
                $housing = round($basic * 0.08, 2);
                $transport = round($basic * 0.05, 2);
                $tax = round($basic * 0.02, 2);
                $gross = $basic + $allow + $housing + $transport;
                PayrollItem::firstOrCreate(
                    ['company_id' => $company->id, 'payroll_run_id' => $run->id, 'employee_id' => $emp->id],
                    [
                        'basic' => $basic, 'allowances' => $allow, 'housing' => $housing, 'transport' => $transport,
                        'bonus' => 0, 'overtime' => 0, 'tax' => $tax, 'loan' => 0, 'advance' => 0, 'deductions' => 0,
                        'absent_days' => 1, 'present_days' => 22, 'leave_days' => 0,
                        'gross' => $gross, 'net' => round($gross - $tax, 2),
                    ]
                );
            }
        }
    }
}
