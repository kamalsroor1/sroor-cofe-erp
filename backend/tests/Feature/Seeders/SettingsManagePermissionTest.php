<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use Database\Seeders\PermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/**
 * SETG-7 (tenant-settings-catalog §5 #5): `settings.manage` is checked by the settings
 * endpoints, SettingPolicy and the POSB-2 store POS settings, but was never seeded. It is
 * now part of the tenant PermissionsSeeder (admin gets it) and a tenant migration backfills
 * it into tenants provisioned before the seeder change.
 */
final class SettingsManagePermissionTest extends TenantTestCase
{
    private const MIGRATION = 'migrations/tenant/2026_10_10_310100_seed_settings_manage_permission.php';

    public function test_seeder_creates_settings_manage_and_grants_it_to_admin_only(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $this->assertTrue(Permission::query()->where('name', 'settings.manage')->where('guard_name', 'web')->exists());

            $this->assertTrue(Role::findByName('admin', 'web')->hasPermissionTo('settings.manage'));
            foreach (['cashier', 'storekeeper', 'accountant'] as $role) {
                $this->assertFalse(Role::findByName($role, 'web')->hasPermissionTo('settings.manage'), "{$role} must not get settings.manage");
            }
        });
    }

    public function test_seeder_is_idempotent(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            (new PermissionsSeeder)->run();
            (new PermissionsSeeder)->run();

            $this->assertSame(1, Permission::query()->where('name', 'settings.manage')->count());
        });
    }

    public function test_a_non_admin_with_settings_manage_can_read_and_update_settings(): void
    {
        $tenant = $this->createTenant();
        $manager = $this->createTenantUser($tenant, null, ['settings.manage']);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant, $manager))
            ->assertStatus(200);

        $this->postJson('/api/v1/settings', ['company_name' => 'محل المدير'], $this->tenantHeaders($tenant, $manager))
            ->assertStatus(200);
    }

    public function test_a_user_without_settings_manage_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant, $cashier))->assertStatus(403);
        $this->postJson('/api/v1/settings', ['company_name' => 'x'], $this->tenantHeaders($tenant, $cashier))->assertStatus(403);
    }

    public function test_migration_backfills_the_permission_for_existing_tenants_and_is_reversible(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            // Simulate a tenant provisioned before the seeder knew settings.manage.
            Permission::query()->where('name', 'settings.manage')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->assertFalse($this->roleHas('admin'));

            $this->runMigration('up');
            $this->runMigration('up'); // idempotent

            $this->assertSame(1, Permission::query()->where('name', 'settings.manage')->count());
            $this->assertTrue($this->roleHas('admin'));
            $this->assertFalse($this->roleHas('cashier'));

            $this->runMigration('down');

            $this->assertFalse(Permission::query()->where('name', 'settings.manage')->exists());
        });
    }

    public function test_migration_is_a_no_op_when_roles_were_not_seeded_yet(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            Permission::query()->where('name', 'settings.manage')->delete();
            Role::query()->where('name', 'admin')->delete();
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            $this->runMigration('up');

            $this->assertTrue(Permission::query()->where('name', 'settings.manage')->exists());
            $this->assertFalse(Role::query()->where('name', 'admin')->exists());
        });
    }

    private function roleHas(string $role): bool
    {
        return Role::findByName($role, 'web')->permissions()->where('name', 'settings.manage')->exists();
    }

    /** Run the migration file's up() or down() directly against the current (tenant) connection. */
    private function runMigration(string $direction): void
    {
        $migration = require database_path(self::MIGRATION);
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, $direction));

        $migration->{$direction}();
    }
}
