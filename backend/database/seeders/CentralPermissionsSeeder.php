<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * CENTRAL database only. Seeds the tenant-safe permission matrix plus the platform
 * super_admin role and super_admin.* permissions. Never call this inside $tenant->run().
 */
class CentralPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);

        Permission::firstOrCreate(['name' => 'super_admin.access', 'guard_name' => 'web']);

        $superAdminRole = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdminRole->syncPermissions(Permission::all());

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
