<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * ROLE-01 (tenant DB): backfill `returns.delete` and `customers.delete` for tenants provisioned
 * before PermissionsSeeder knew them. Deleting a return or a customer used to ride on
 * `returns.manage` / `customers.manage` (which the cashier role holds); the owner decided both
 * deletions are admin only by default. Granted to the `admin` role ONLY (CTO D5); other roles
 * get them manually from the roles screen. Idempotent.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = ['returns.delete', 'customers.delete'];

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
