<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    /**
     * Tenant roles and permissions always live on the tenant `web` guard, pinned explicitly so
     * the request's default guard never matters (a super-admin provisioning a tenant runs with
     * `central` as the default guard; spatie would otherwise stamp the roles `central`).
     */
    public const GUARD = 'web';

    /**
     * Tenant permission names grouped by module. The display label of each one lives in
     * lang/{ar,en}/permissions.php under "permissions.<name>". PermissionsParityTest
     * (tests/Feature/Architecture) enforces both the labels and that every name the code
     * checks is listed here.
     *
     * CTO D5: a permission added here is granted to the `admin` role only (the admin sync
     * below, plus Gate::before); other roles receive it manually from the roles screen.
     * Existing tenants get new names through a tenant migration (see 2026_10_10_070000).
     *
     * @var list<string>
     */
    public const PERMISSIONS = [
        // POS & Invoices
        'pos.access',
        'invoices.view',
        'invoices.create',
        'invoices.edit',
        'invoices.cancel',
        'invoices.delete',
        'invoices.discount',

        // Items & Inventory
        'items.view',
        'items.create',
        'items.edit',
        'items.delete',
        'items.view_cost',
        // QA-2: stock adjustments (AdjustStockRequest, ItemPolicy::adjustStock).
        'inventory.adjust',

        // Purchases
        'purchases.view',
        'purchases.create',
        'purchases.delete',

        // Stores & Transfers
        'stores.manage',
        // QA-2 (coordinator): read every branch at once (X-Store-Id "all"; ActiveStore, ApiTokenAuth).
        'stores.view_all',
        'transfers.view',
        'transfers.create',

        // Contacts
        'customers.manage',
        'customers.statement',
        'suppliers.manage',
        'suppliers.statement',

        // Financials & Daily Journal
        'daily_journal.view',
        'daily_journal.close_shift',
        // QA-2: treasury transfers between drawers (TreasuryPolicy::transfer).
        'daily_journal.manage',
        'expenses.manage',
        'returns.manage',

        // Admin & Reports
        'reports.view',
        'trash.access',
        'roles.manage',
        'logs.view',

        // SETG-7: settings endpoints, SettingPolicy and store POS settings.
        'settings.manage',
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Every tenant permission (labels: lang/{ar,en}/permissions.php, key "permissions.<name>").
        foreach (self::PERMISSIONS as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => self::GUARD]);
        }

        // 2. Roles Setup & Permission Assignment
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => self::GUARD]);
        $cashierRole = Role::firstOrCreate(['name' => 'cashier', 'guard_name' => self::GUARD]);
        $storeRole = Role::firstOrCreate(['name' => 'storekeeper', 'guard_name' => self::GUARD]);
        $accountantRole = Role::firstOrCreate(['name' => 'accountant', 'guard_name' => self::GUARD]);

        // Tenant admin gets every local ERP permission. super_admin.* is central-only
        // (see CentralPermissionsSeeder) and is excluded even if a legacy row exists.
        $storeAdminPermissions = Permission::where('guard_name', self::GUARD)
            ->where('name', 'not like', 'super_admin.%')
            ->get();
        $adminRole->syncPermissions($storeAdminPermissions);

        // Cashier permissions
        $cashierRole->syncPermissions([
            'pos.access',
            'invoices.view',
            'invoices.create',
            'items.view',
            'customers.manage',
            'customers.statement',
            'daily_journal.view',
            'daily_journal.close_shift',
            'returns.manage',
        ]);

        // Storekeeper permissions. CTO 2026-10-09: no items.create / items.edit (item create,
        // edit, toggle-active, price and cost changes stay admin-only); see the tenant migration
        // 2026_10_10_070010_revoke_item_edit_permissions_from_storekeeper.
        $storeRole->syncPermissions([
            'items.view',
            'purchases.view',
            'purchases.create',
            'transfers.view',
            'transfers.create',
            'suppliers.manage',
            'suppliers.statement',
            'returns.manage',
        ]);

        // Accountant permissions
        $accountantRole->syncPermissions([
            'invoices.view',
            'purchases.view',
            'customers.manage',
            'customers.statement',
            'suppliers.manage',
            'suppliers.statement',
            'daily_journal.view',
            'daily_journal.close_shift',
            'expenses.manage',
            'reports.view',
            'items.view',
            'items.view_cost',
        ]);
    }
}
