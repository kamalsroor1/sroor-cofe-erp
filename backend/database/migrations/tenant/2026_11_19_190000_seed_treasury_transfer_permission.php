<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * SHOP-06 (tenant DB): backfill `treasury.transfer` (move money between treasury accounts) for
 * tenants provisioned before PermissionsSeeder knew it. Granted to the `admin` role (CTO D5) and
 * to every role that already held `daily_journal.manage`, the permission TreasuryPolicy::transfer
 * used to check, so nobody loses the ability they had. Idempotent.
 */
return new class extends Migration
{
    private const PERMISSION = 'treasury.transfer';

    private const LEGACY_PERMISSION = 'daily_journal.manage';

    private const GUARD = 'web';

    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $permission = Permission::findOrCreate(self::PERMISSION, self::GUARD);

        $roles = Role::query()
            ->where('guard_name', self::GUARD)
            ->where(fn ($q) => $q->where('name', 'admin')
                ->orWhereHas('permissions', fn ($p) => $p->where('name', self::LEGACY_PERMISSION)->where('guard_name', self::GUARD)))
            ->get();

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
