<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Root seeder for TENANT databases (`php artisan tenants:seed`, see
 * config('tenancy.seeder_parameters')).
 *
 * Tenant-safe by design: it only seeds the local ERP permissions and tenant roles.
 * It must never create users, the super_admin role, super_admin.* permissions,
 * plans or tenants — those are central concerns (DatabaseSeeder). Store and admin
 * user creation stay in TenantProvisionerService, the single provisioning path.
 */
final class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionsSeeder::class);
    }
}
