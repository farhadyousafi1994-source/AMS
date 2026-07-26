<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * One demo login per staff role so every role can be tried out. All use the
 * password "password". Runs after roles + branches exist.
 */
class StaffUsersSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();
        if (! $company) {
            return;
        }

        $herat = Branch::where('company_id', $company->id)->where('name', 'Herat Site Office')->first();
        $kabul = Branch::where('company_id', $company->id)->where('name', 'Kabul Head Office')->first();
        $both = array_values(array_filter([$herat?->id, $kabul?->id]));

        // email, display name, role, branches they may see, default branch
        $staff = [
            ['president@ariaherat.af', 'Eng. Zada (President)', 'President', $both, null],
            ['engineer@ariaherat.af', 'Eng. Farid (Site Engineer)', 'Site Engineer', [$herat?->id], $herat?->id],
            ['fieldengineer@ariaherat.af', 'Eng. Sohrab (Field Engineer)', 'Field Engineer', [$herat?->id], $herat?->id],
            ['supervisor@ariaherat.af', 'Naseer (Site Supervisor)', 'Site Supervisor', [$herat?->id], $herat?->id],
            ['accountant@ariaherat.af', 'Ahmad Zia (Accountant)', 'Accountant', $both, $herat?->id],
            ['storekeeper@ariaherat.af', 'Karim (Storekeeper)', 'Storekeeper', [$herat?->id], $herat?->id],
            ['kabul.engineer@ariaherat.af', 'Eng. Wali (Kabul)', 'Site Engineer', [$kabul?->id], $kabul?->id],
            ['viewer@ariaherat.af', 'Guest (Viewer)', 'Viewer', $both, null],
        ];

        foreach ($staff as [$email, $name, $roleName, $branches, $current]) {
            if (! Role::where('name', $roleName)->where('guard_name', 'web')->exists()) {
                continue;
            }

            $user = User::withTrashed()->firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make('password'),
                    'company_id' => $company->id,
                    'current_company' => $company->id,
                    'current_branch' => $current,
                    'type' => 'staff',
                ]
            );

            $user->companies()->syncWithoutDetaching([$company->id]);
            $user->syncRoles([$roleName]);

            $branchIds = array_values(array_filter($branches));
            if (! empty($branchIds)) {
                $user->branches()->syncWithoutDetaching($branchIds);
            }
            if ($current && ! $user->current_branch) {
                $user->update(['current_branch' => $current]);
            }
        }
    }
}
