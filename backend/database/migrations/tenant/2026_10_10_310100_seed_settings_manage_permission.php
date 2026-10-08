<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SETG-7 (tenant DB): backfill the `settings.manage` permission for tenants provisioned
 * before PermissionsSeeder knew it, and grant it to the tenant `admin` role (the seeder
 * gives admin every non super_admin.* permission, so this keeps existing tenants in line
 * with new ones). Idempotent; a no-op on the permission grant when roles are not seeded
 * yet (fresh provisioning runs the seeder right after the migrations).
 */
return new class extends Migration
{
    private const PERMISSION = 'settings.manage';

    private const GUARD = 'web';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION, self::GUARD);

        $admin = Role::query()->where('name', 'admin')->where('guard_name', self::GUARD)->first();
        if ($admin instanceof Role && ! $admin->permissions()->whereKey($permission->getKey())->exists()) {
            $admin->givePermissionTo($permission);
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
            ->where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
