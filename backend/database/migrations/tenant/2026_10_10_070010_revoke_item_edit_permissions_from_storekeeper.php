<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CTO decision 2026-10-09 (tenant DB): the seeded `storekeeper` role must not hold
 * items.create / items.edit (item create, edit, toggle-active, price and cost changes), back to
 * admin-only as before the permission rename. PermissionsSeeder no longer grants them; this
 * revokes them from the `storekeeper` role of existing tenants.
 *
 * Only that role is touched: direct user grants, other roles and custom roles keep whatever the
 * tenant admin gave them. Idempotent; a no-op when the role or the permissions do not exist.
 * down() re-grants both permissions to the `storekeeper` role.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const PERMISSIONS = ['items.create', 'items.edit'];

    private const ROLE = 'storekeeper';

    private const GUARD = 'web';

    public function up(): void
    {
        $role = $this->role();
        if (! $role instanceof Role) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permissions = Permission::query()
            ->whereIn('name', self::PERMISSIONS)
            ->where('guard_name', self::GUARD)
            ->get();

        foreach ($permissions as $permission) {
            if ($role->permissions()->whereKey($permission->getKey())->exists()) {
                $role->revokePermissionTo($permission);
            }
        }

        $registrar->forgetCachedPermissions();
    }

    public function down(): void
    {
        $role = $this->role();
        if (! $role instanceof Role) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $name) {
            $permission = Permission::findOrCreate($name, self::GUARD);

            if (! $role->permissions()->whereKey($permission->getKey())->exists()) {
                $role->givePermissionTo($permission);
            }
        }

        $registrar->forgetCachedPermissions();
    }

    private function role(): ?Role
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return null;
        }

        $role = Role::query()->where('name', self::ROLE)->where('guard_name', self::GUARD)->first();

        return $role instanceof Role ? $role : null;
    }
};
