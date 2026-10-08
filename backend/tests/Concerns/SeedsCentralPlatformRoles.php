<?php

declare(strict_types=1);

namespace Tests\Concerns;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the CENTRAL platform identity (super_admin role + super_admin.* permissions).
 *
 * In the test suite the central and tenant schemas share one sqlite :memory: database,
 * so a "central" super_admin role is simply a row created by the central seeder.
 *
 * Phase 0 introduces Database\Seeders\CentralPermissionsSeeder. Until it exists the
 * role/permission are created directly so these regression tests fail on the real
 * defect (allowlists, missing central-context guard) rather than on a missing class.
 */
trait SeedsCentralPlatformRoles
{
    protected function seedCentralPlatformRoles(): Role
    {
        $centralSeeder = 'Database\Seeders\CentralPermissionsSeeder';

        if (class_exists($centralSeeder)) {
            $this->seed($centralSeeder);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'super_admin.access', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        if (! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }
}
