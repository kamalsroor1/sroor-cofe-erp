<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * BE-01 (tenant DB): backfill `returns.without_invoice` for tenants provisioned before
 * PermissionsSeeder knew it, granted to the `admin` role only. Idempotent.
 */
return new class extends Migration
{
    private const PERMISSION = 'returns.without_invoice';

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

        Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
