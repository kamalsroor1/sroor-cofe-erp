<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CTO decision 2026-10-11 (tenant DB): the seeded `storekeeper` role enters purchase costs, so it
 * must see item costs (`items.view_cost`). PermissionsSeeder grants it to new tenants; this grants
 * it to the `storekeeper` role of existing tenants.
 *
 * Only that role is touched. Idempotent: the permission is created on the `web` guard when
 * missing, attached only when the role exists and does not already hold it. down() revokes the
 * permission from the `storekeeper` role only (the permission itself and every other holder,
 * admin and accountant included, keep it).
 */
return new class extends Migration
{
    private const PERMISSION = 'items.view_cost';

    private const ROLE = 'storekeeper';

    private const GUARD = 'web';

    public function up(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION, self::GUARD);

        $role = $this->role();
        if ($role instanceof Role && ! $role->permissions()->whereKey($permission->getKey())->exists()) {
            $role->givePermissionTo($permission);
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        if (! $this->tablesExist()) {
            return;
        }

        $role = $this->role();
        if (! $role instanceof Role) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::query()
            ->where('name', self::PERMISSION)
            ->where('guard_name', self::GUARD)
            ->first();

        if ($permission instanceof Permission && $role->permissions()->whereKey($permission->getKey())->exists()) {
            $role->revokePermissionTo($permission);
        }

        $registrar->forgetCachedPermissions();
    }

    private function tablesExist(): bool
    {
        return Schema::hasTable('permissions') && Schema::hasTable('roles');
    }

    private function role(): ?Role
    {
        $role = Role::query()->where('name', self::ROLE)->where('guard_name', self::GUARD)->first();

        return $role instanceof Role ? $role : null;
    }
};
