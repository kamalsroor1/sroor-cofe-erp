<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CentralPermission;
use Illuminate\Database\Seeder;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CENTRAL database only. Never call this inside $tenant->run(): it refuses to run while
 * tenancy is initialized (the default connection would be a tenant DB).
 *
 * 1. Platform operators (IDEN-1.2): every App\Enums\CentralPermission on the `central`
 *    guard, role `super_admin` (all of them) and role `support` (read-only). Every query
 *    is pinned to the central connection by name. Central-guard permissions that are not
 *    in the enum are pruned, so no tenant ability can ever be granted to a CentralUser
 *    through spatie's own Gate::before.
 *
 * 2. @deprecated removed in IDEN-1.4 (W2-B3): the Phase 0 legacy matrix for
 *    App\Models\User operators in the central `users` table (tenant permission matrix,
 *    `super_admin.access` and a `web`-guard `super_admin` role). The current SPA and
 *    /api/v1/super-admin/* still authenticate that way until batch 3.
 */
class CentralPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            throw new RuntimeException('CentralPermissionsSeeder must run in the central context, never inside a tenant.');
        }

        // 1. Prune first: PermissionsSeeder syncs every non-`super_admin.%` permission of ANY
        //    guard onto the web `admin` role, so a stray central-guard row would break it.
        // 2. Legacy web guard before the central guard, so on a fresh DB the web `super_admin`
        //    role keeps the lower id (DatabaseSeeder still looks it up by name only).
        $this->pruneCentralGuard();
        $this->seedLegacyWebGuard();
        $this->seedCentralGuard();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function seedCentralGuard(): void
    {
        $connection = $this->centralConnection();
        $guard = CentralPermission::GUARD;

        $permissions = [];
        foreach (CentralPermission::values() as $name) {
            $permissions[$name] = Permission::on($connection)->firstOrCreate(['name' => $name, 'guard_name' => $guard]);
        }

        foreach (CentralPermission::roleMatrix() as $roleName => $names) {
            /** @var Role $role */
            $role = Role::on($connection)->firstOrCreate(['name' => $roleName, 'guard_name' => $guard]);

            $role->permissions()->sync(array_map(
                static fn (string $name): int => (int) $permissions[$name]->getKey(),
                $names,
            ));
        }
    }

    /** The enum is the only source of central-guard permissions: anything else is deleted (pivots cascade). */
    private function pruneCentralGuard(): void
    {
        Permission::on($this->centralConnection())
            ->where('guard_name', CentralPermission::GUARD)
            ->whereNotIn('name', CentralPermission::values())
            ->get()
            ->each(fn (Permission $stale) => $stale->delete());
    }

    /**
     * @deprecated removed in IDEN-1.4 (W2-B3) together with PlatformSuperAdmin's legacy User branch.
     */
    private function seedLegacyWebGuard(): void
    {
        $this->call(PermissionsSeeder::class);

        $connection = $this->centralConnection();

        Permission::on($connection)->firstOrCreate(['name' => 'super_admin.access', 'guard_name' => 'web']);

        /** @var Role $superAdminRole */
        $superAdminRole = Role::on($connection)->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->permissions()->sync(
            Permission::on($connection)->where('guard_name', 'web')->pluck('id')->all(),
        );
    }

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }
}
