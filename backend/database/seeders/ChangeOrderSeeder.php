<?php

namespace Database\Seeders;

use App\Models\ChangeOrder;
use App\Models\Company;
use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Demo change orders: an approved addition and deduction (which revise the
 * project's contract value), one submitted awaiting the owner, and a draft.
 */
class ChangeOrderSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company || ChangeOrder::where('company_id', $company->id)->exists()) {
            return;
        }

        $project = Project::where('company_id', $company->id)->whereNotNull('contract_value')
            ->orderByDesc('contract_value')->first();
        if (! $project) {
            return;
        }

        $seq = 0;
        $mk = function (array $a) use ($company, $project, &$seq) {
            $seq++;
            $amount = $a['amount'] ?? 0;

            return ChangeOrder::create([
                'company_id' => $company->id, 'project_id' => $project->id,
                'code' => 'CO-'.str_pad((string) $seq, 4, '0', STR_PAD_LEFT),
                'title' => $a['title'], 'description' => $a['desc'] ?? null, 'reason' => $a['reason'],
                'kind' => $a['kind'], 'status' => $a['status'],
                'cost_impact' => $amount, 'currency' => 'AFN', 'rate' => 1, 'cost_impact_base' => $amount,
                'time_impact_days' => $a['days'] ?? 0, 'requested_by_name' => 'انجنیر فرید (مدیر پروژه)',
                'decided_at' => $a['status'] === 'approved' ? now()->subDays(rand(3, 20)) : null,
                'co_date' => now()->subDays(rand(5, 30))->toDateString(),
            ]);
        };

        $mk(['title' => 'اضافه‌کاری دیوار احاطه', 'desc' => 'تمدید دیوار احاطه به طول ۸۰ متر به درخواست مالک.',
            'reason' => 'owner request', 'kind' => 'addition', 'status' => 'approved', 'amount' => 850000, 'days' => 20]);
        $mk(['title' => 'حذف کاشی‌کاری محوطه', 'desc' => 'حذف کاشی‌کاری بخش محوطهٔ بیرونی.',
            'reason' => 'design change', 'kind' => 'deduction', 'status' => 'approved', 'amount' => 300000, 'days' => 0]);
        $mk(['title' => 'تقویت فنداسیون به‌علت خاک نرم', 'desc' => 'تغییر طرح فنداسیون در گوشهٔ شمال‌شرقی.',
            'reason' => 'site condition', 'kind' => 'addition', 'status' => 'submitted', 'amount' => 420000, 'days' => 12]);
        $mk(['title' => 'تغییر رنگ نمای بیرونی', 'reason' => 'owner request', 'kind' => 'no_cost', 'status' => 'draft', 'amount' => 0, 'days' => 0]);

        // Apply approved change orders to the contract value.
        $original = (float) ($project->original_contract_value ?? $project->contract_value);
        $delta = ChangeOrder::where('project_id', $project->id)->where('status', 'approved')
            ->get()->sum(fn ($co) => $co->signedImpact());
        $project->forceFill(['contract_value' => round($original + $delta, 2)])->saveQuietly();
    }
}
