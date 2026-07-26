<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseBudget;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Four equal partners (engineers), demo office overhead and household expenses
 * across recent months, plus a monthly home budget for budget-vs-actual.
 */
class OfficeHomeExpenseSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('abbreviation', 'AHMZ')->first();
        if (! $company || Partner::where('company_id', $company->id)->exists()) {
            return;
        }

        $admin = User::where('company_id', $company->id)->first();

        // The four equal shareholders (25% each). Each also gets a boss login
        // (President role → full visibility) linked to its partner record.
        $president = \Spatie\Permission\Models\Role::where('name', 'President')->where('guard_name', 'web')->first();
        foreach (['Abubakr', 'Omar', 'Usman', 'Ali'] as $name) {
            $user = User::withTrashed()->firstOrCreate(
                ['email' => strtolower($name).'@ariaherat.af'],
                [
                    'name' => $name.' (Shareholder)',
                    'password' => \Illuminate\Support\Facades\Hash::make('password'),
                    'company_id' => $company->id, 'current_company' => $company->id, 'type' => 'staff',
                ]
            );
            $user->companies()->syncWithoutDetaching([$company->id]);
            if ($president) {
                $user->syncRoles(['President']);
            }

            Partner::create([
                'company_id' => $company->id, 'name' => $name, 'user_id' => $user->id,
                'share_percent' => 25, 'active' => true,
            ]);
        }

        $mk = function (string $type, string $category, float $amount, int $monthsAgo, ?string $vendor, string $method) use ($company, $admin) {
            $date = now()->subMonths($monthsAgo)->startOfMonth()->addDays(rand(1, 25))->toDateString();
            $expense = Expense::create([
                'company_id' => $company->id, 'type' => $type, 'project_id' => null,
                'user_id' => $admin?->id, 'expense_date' => $date,
                'category' => $category, 'payee' => $vendor, 'vendor' => $vendor, 'payment_method' => $method,
                'approval_status' => 'approved', 'approved_by' => $admin?->id, 'approved_at' => now(),
                'currency' => 'AFN', 'amount' => $amount, 'rate' => 1, 'amount_base' => $amount,
            ]);
            // Approved office/home expense is real cash out of the General Budget.
            \App\Models\TreasuryTransaction::create([
                'company_id' => $company->id, 'expense_id' => $expense->id, 'project_id' => null,
                'direction' => 'out', 'kind' => 'withdrawal', 'status' => 'active',
                'amount' => $amount, 'currency' => 'AFN', 'rate' => 1, 'amount_base' => $amount,
                'tx_date' => $date, 'note' => ucfirst($type)." expense: {$category}".($vendor ? " — {$vendor}" : ''),
            ]);
        };

        // Office overhead — recurring across the last 3 months.
        foreach ([0, 1, 2] as $m) {
            $mk('office', 'Office Rent', 45000, $m, 'مالک دفتر', 'cash');
            $mk('office', 'Electricity', rand(6, 9) * 1000, $m, 'برشنا', 'cash');
            $mk('office', 'Internet', 4500, $m, 'مخابرات', 'bank');
            $mk('office', 'Refreshments', rand(3, 6) * 1000, $m, 'سوپرمارکت', 'cash');
        }
        $mk('office', 'Furniture', 38000, 1, 'مبل‌فروشی هرات', 'bank');
        $mk('office', 'Office Equipment', 22000, 0, 'کمپیوتر مارکیت', 'card');
        // One pending office expense awaiting approval.
        Expense::create([
            'company_id' => $company->id, 'type' => 'office', 'user_id' => $admin?->id,
            'expense_date' => now()->toDateString(), 'category' => 'Maintenance', 'vendor' => 'تعمیرکار',
            'payment_method' => 'cash', 'approval_status' => 'pending', 'currency' => 'AFN',
            'amount' => 7500, 'rate' => 1, 'amount_base' => 7500,
        ]);

        // Home / household expenses.
        foreach ([0, 1, 2] as $m) {
            $mk('home', 'Groceries', rand(18, 26) * 1000, $m, 'سبزی‌فروشی', 'cash');
            $mk('home', 'Utility Bills', rand(4, 7) * 1000, $m, 'برشنا/آبرسانی', 'cash');
        }
        $mk('home', 'Home Maintenance', 15000, 1, 'نلدوان', 'cash');
        $mk('home', 'Household Purchases', 12000, 0, 'بازار', 'cash');

        // Home monthly budget (for budget vs actual).
        foreach ([0, 1, 2] as $m) {
            ExpenseBudget::create([
                'company_id' => $company->id, 'type' => 'home', 'category' => null,
                'period' => now()->subMonths($m)->format('Y-m'), 'amount' => 40000, 'currency' => 'AFN',
            ]);
        }
    }
}
