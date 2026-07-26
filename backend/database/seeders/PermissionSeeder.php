<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Entities that exist in this platform scaffold. Projects/Finance/
     * Procurement/HR entities are added here once those modules ship.
     */
    protected array $entities = [
        'company', 'user', 'role', 'branch',
        'project', 'site', 'task', 'lift', 'milestone', 'daily-log', 'subcontractor', 'sub-payment', 'document',
        'currency', 'exchange-rate', 'expense', 'office-expense', 'home-expense', 'partner', 'expense-budget', 'invoice', 'receipt',
        'department', 'designation', 'asset', 'employee', 'investor', 'investment',
        'contract', 'contract-milestone', 'contract-payment', 'change-order', 'report', 'treasury',
        'party', 'party-transaction',
        'supplier', 'stock-item', 'stock-movement', 'purchase-order',
        'attendance', 'payroll', 'leave',
        // Supervisor & site management module
        'purchase-request', 'cash-advance', 'site-invoice', 'purchase-category',
        'worker', 'worker-attendance',
        'tradesman', 'work-measurement',
        // HSE / Safety
        'incident',
        'lookup', 'ui-setting', 'fingerprint', 'payment-request',
        'backup', 'log', 'trash', 'notification', 'dashboard',
        // System feature visibility — a Super Admin unticks the "-list" box in
        // a role to hide the feature for that role's users.
        'theme', 'language', 'lang-en', 'lang-fa', 'lang-pa',
    ];

    protected array $actions = ['list', 'create', 'edit', 'show', 'delete'];

    /** Extra permissions that don't fit the entity-action grid. */
    protected array $custom = ['purchase-approve', 'cash-release', 'expense-approve', 'change-order-approve', 'incident-close',
        'payment-approve', 'payment-process',
        // Bypass per-project scoping — see data across ALL projects.
        'all-projects',
        // View/switch across ALL branches (otherwise pinned to assigned branches).
        'all-branches'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Purge permissions of removed modules (Billing BOQ/IPC, Plant & Fuel).
        Permission::where('name', 'like', 'boq-%')
            ->orWhere('name', 'like', 'ipc-%')
            ->orWhere('name', 'like', 'plant-log-%')
            ->orWhere('name', 'ipc-certify')
            ->delete();

        foreach ($this->entities as $entity) {
            foreach ($this->actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$entity}-{$action}",
                    'guard_name' => 'web',
                ]);
            }
        }

        foreach ($this->custom as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());
    }
}
