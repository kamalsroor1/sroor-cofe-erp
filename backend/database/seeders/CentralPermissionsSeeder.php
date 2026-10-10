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
 * 2. W2-B3 (IDEN-1.4 follow-up): the Phase 0 legacy matrix for App\Models\User operators
 *    (the tenant permission matrix in the central DB, `super_admin.access` and a `web`-guard
 *    `super_admin` role) is no longer seeded: PlatformSuperAdmin never reads it. Rows that
 *    already exist in a central DB are left untouched on purpose: MigrateLegacySuperAdminsAction
 *    (LegacySuperAdminDirectory) still finds the Phase 0 operators through that `web` role.
 */
class CentralPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            throw new RuntimeException('CentralPermissionsSeeder must run in the central context, never inside a tenant.');
        }

        // Prune first, so only the enum's central-guard permissions remain before the roles sync.
        $this->pruneCentralGuard();
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

    private function centralConnection(): string
    {
        return (string) config('tenancy.database.central_connection', config('database.default'));
    }
}
