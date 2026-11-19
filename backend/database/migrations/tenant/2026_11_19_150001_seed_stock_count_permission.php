<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SHOP-02 (tenant DB): backfill `stock.count` (stocktake sessions) for tenants provisioned
 * before PermissionsSeeder knew it. Granted to the `admin` role and to the `storekeeper` role
 * (the stock manager role; counting stock is its daily job). Every plan gets it: the stocktake
 * is gated by this permission only, never by a plan feature. Idempotent.
 */
return new class extends Migration
{
    private const PERMISSION = 'stock.count';

    /** @var list<string> */
    private const ROLES = ['admin', 'storekeeper'];

    private const GUARD = 'web';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION, self::GUARD);

        $roles = Role::query()->whereIn('name', self::ROLES)->where('guard_name', self::GUARD)->get();
        foreach ($roles as $role) {
            if (! $role->permissions()->whereKey($permission->getKey())->exists()) {
                $role->givePermissionTo($permission);
            }
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
