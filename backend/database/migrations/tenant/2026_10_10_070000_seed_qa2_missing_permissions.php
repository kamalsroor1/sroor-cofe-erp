<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * QA-2 (tenant DB, migration decision Q10): backfill the permissions the code checks but
 * no seeder created, for tenants provisioned before PermissionsSeeder knew them.
 *
 * CTO D5: granted to the tenant `admin` role ONLY. cashier / storekeeper / accountant and
 * custom roles get them manually from the roles screen.
 *
 * Idempotent. A no-op on the grant when roles are not seeded yet (fresh provisioning runs
 * PermissionsSeeder right after the migrations, which creates the same rows).
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = ['inventory.adjust', 'daily_journal.manage', 'stores.view_all'];

    private const GUARD = 'web';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $admin = Role::query()->where('name', 'admin')->where('guard_name', self::GUARD)->first();

        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::findOrCreate($name, self::GUARD);

            if ($admin instanceof Role && ! $admin->permissions()->whereKey($permission->getKey())->exists()) {
                $admin->givePermissionTo($permission);
            }
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        // Removes the role/user grants too (FK cascade + spatie detach on delete).
        Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', self::GUARD)
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
