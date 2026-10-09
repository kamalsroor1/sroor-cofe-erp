<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\CentralPermission;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

/**
 * IDEN-1.2: CentralPermissionsSeeder seeds the operator matrix on the `central` guard,
 * in the central DB only, and refuses to run inside a tenant.
 */
final class CentralPermissionsSeederTest extends TenantTestCase
{
    /** @return list<string> */
    private function centralRolePermissions(string $role): array
    {
        /** @var list<string> $names */
        $names = Role::findByName($role, CentralPermission::GUARD)->permissions()->pluck('name')->all();
        sort($names);

        return $names;
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }

    public function test_seeds_every_central_permission_on_the_central_guard_in_the_central_db(): void
    {
        $this->seed(CentralPermissionsSeeder::class);

        /** @var list<string> $central */
        $central = DB::connection($this->centralConnectionName())->table('permissions')
            ->where('guard_name', CentralPermission::GUARD)
            ->pluck('name')
            ->all();

        $this->assertSame($this->sorted(CentralPermission::values()), $this->sorted($central));

        foreach ($central as $name) {
            $this->assertStringStartsWith('super_admin.', $name, 'Central abilities keep the prefix every tenant admin is denied.');
        }
    }

    public function test_super_admin_role_gets_every_central_permission_and_no_erp_permission(): void
    {
        $this->seed(CentralPermissionsSeeder::class);

        $permissions = $this->centralRolePermissions(CentralPermission::ROLE_SUPER_ADMIN);

        $this->assertSame($this->sorted(CentralPermission::values()), $permissions);
        $this->assertNotContains('customers.manage', $permissions);
    }

    public function test_support_role_is_read_only(): void
    {
        $this->seed(CentralPermissionsSeeder::class);

        $support = $this->centralRolePermissions(CentralPermission::ROLE_SUPPORT);

        $this->assertSame($this->sorted(CentralPermission::readOnlyValues()), $support);
        $this->assertNotEmpty($support);

        foreach ($support as $name) {
            $this->assertStringEndsWith('.view', $name);
        }

        $this->assertNotContains(CentralPermission::TenantsManage->value, $support);
        $this->assertNotContains(CentralPermission::TenantsImpersonate->value, $support);
        $this->assertNotContains(CentralPermission::MonitoringView->value, $support);
    }

    public function test_is_idempotent(): void
    {
        $this->seed(CentralPermissionsSeeder::class);
        $permissions = Permission::query()->count();
        $roles = Role::query()->count();
        $pivot = DB::connection($this->centralConnectionName())->table('role_has_permissions')->count();

        $this->seed(CentralPermissionsSeeder::class);

        $this->assertSame($permissions, Permission::query()->count());
        $this->assertSame($roles, Role::query()->count());
        $this->assertSame($pivot, DB::connection($this->centralConnectionName())->table('role_has_permissions')->count());
    }

    public function test_prunes_central_guard_permissions_that_are_not_central_abilities(): void
    {
        $this->seed(CentralPermissionsSeeder::class);

        $planted = Permission::create(['name' => 'customers.manage', 'guard_name' => CentralPermission::GUARD]);
        Role::findByName(CentralPermission::ROLE_SUPER_ADMIN, CentralPermission::GUARD)->givePermissionTo($planted);

        $this->seed(CentralPermissionsSeeder::class);

        $this->assertFalse(Permission::query()->where('guard_name', CentralPermission::GUARD)->where('name', 'customers.manage')->exists());
        $this->assertNotContains('customers.manage', $this->centralRolePermissions(CentralPermission::ROLE_SUPER_ADMIN));

        // The tenant-guard permission of the same name is untouched.
        $this->assertTrue(Permission::query()->where('guard_name', 'web')->where('name', 'customers.manage')->exists());
    }

    /** Legacy expectations: removed with the legacy branch in IDEN-1.4 (W2-B3). */
    public function test_keeps_the_legacy_web_guard_super_admin_role_until_iden_1_4(): void
    {
        $this->seed(CentralPermissionsSeeder::class);

        $legacy = Role::findByName('super_admin', 'web');

        $this->assertTrue($legacy->hasPermissionTo('super_admin.access'));
        $this->assertSame(0, $legacy->permissions()->where('guard_name', '!=', 'web')->count());
    }

    public function test_refuses_to_run_inside_a_tenant(): void
    {
        $tenant = $this->createTenant();

        tenancy()->initialize($tenant);

        try {
            $this->seed(CentralPermissionsSeeder::class);
            $this->fail('CentralPermissionsSeeder must refuse to run while tenancy is initialized.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('central context', $e->getMessage());
        } finally {
            tenancy()->end();
        }

        // Nothing leaked into the tenant DB.
        $this->inTenant($tenant, function (): void {
            $this->assertSame(0, Permission::query()->where('guard_name', CentralPermission::GUARD)->count());
        });
    }
}
