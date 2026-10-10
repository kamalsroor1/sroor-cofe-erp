<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * P0-AUTH-4: the super_admin role and super_admin.* permissions belong to the CENTRAL
 * identity only. A tenant DB must never create them, and a tenant admin must never be able
 * to assign them.
 *
 * IDEN-1.8: runs against a real tenant database provisioned through the production pipeline
 * (Tests\TenantTestCase) instead of a shared sqlite DB seeded with PermissionsSeeder alone.
 * Central operators are App\Models\CentralUser on the `central` guard; nothing in a tenant
 * DB (role, permission, phone) can make anyone one.
 */
class SuperAdminRoleIsolationApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private Tenant $tenant;

    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->tenant = $this->createTenant();
        $this->cashier = $this->createTenantUser($this->tenant, 'cashier', [], [
            'name' => 'كاشير',
            'phone' => '01000007015',
            'email' => 'cashier@sroor.test',
        ]);
    }

    /** Simulate a legacy tenant DB that was seeded before the fix. */
    private function legacySuperAdminRole(): Role
    {
        return $this->inTenant($this->tenant, function (): Role {
            $permission = Permission::findOrCreate('super_admin.access', 'web');
            $role = $this->webRole('super_admin');
            $role->givePermissionTo($permission);
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    private function legacySuperAdminPermission(): void
    {
        $this->inTenant($this->tenant, static function (): void {
            Permission::findOrCreate('super_admin.access', 'web');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function tenantRoleId(string $name): int
    {
        return $this->inTenant($this->tenant, static fn (): int => (int) Role::findByName($name, 'web')->id);
    }

    private function roleHasPermission(string $roleName, string $permission): bool
    {
        return $this->inTenant($this->tenant, static function () use ($roleName, $permission): bool {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return Role::findByName($roleName, 'web')->fresh('permissions')->permissions->contains('name', $permission);
        });
    }

    public function test_tenant_permissions_seeder_creates_no_super_admin(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, Role::query()->where('name', 'super_admin')->count());
            $this->assertSame(0, Permission::query()->where('name', 'like', 'super_admin.%')->count());
            $this->assertSame(0, Role::query()->where('guard_name', 'central')->count(), 'No central-guard role in a tenant DB.');
        });
        $this->assertFalse($this->roleHasPermission('admin', 'super_admin.access'));
    }

    public function test_tenant_admin_cannot_create_user_with_super_admin_role(): void
    {
        $this->legacySuperAdminRole();

        $this->postJson('/api/v1/users', [
            'name' => 'محاولة تصعيد',
            'phone' => '01000007016',
            'email' => 'escalate@sroor.test',
            'password' => 'secret123',
            'role' => 'super_admin',
            'default_store_id' => $this->tenantStore($this->tenant)->id,
            'is_active' => true,
        ], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, User::query()->where('phone', '01000007016')->count());
            $this->assertSame(0, User::role('super_admin')->count());
        });
    }

    public function test_tenant_admin_cannot_update_user_to_super_admin(): void
    {
        $this->legacySuperAdminRole();

        $this->putJson("/api/v1/users/{$this->cashier->id}", [
            'name' => 'كاشير',
            'phone' => '01000007015',
            'email' => 'cashier@sroor.test',
            'role' => 'super_admin',
            'default_store_id' => $this->tenantStore($this->tenant)->id,
            'is_active' => true,
        ], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $roles = $this->inTenant($this->tenant, fn (): array => User::query()->findOrFail($this->cashier->id)->getRoleNames()->all());
        $this->assertNotContains('super_admin', $roles);
        $this->assertContains('cashier', $roles);
    }

    public function test_role_permissions_rejects_super_admin_permission(): void
    {
        $this->legacySuperAdminPermission();
        $cashierRoleId = $this->tenantRoleId('cashier');

        $this->putJson("/api/v1/roles/{$cashierRoleId}/permissions", [
            'permissions' => ['pos.access', 'super_admin.access'],
        ], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.1']);

        $this->assertFalse($this->roleHasPermission('cashier', 'super_admin.access'));
        // Rejected request must not partially apply: cashier keeps its prior permissions.
        $this->assertTrue($this->roleHasPermission('cashier', 'invoices.create'));
    }

    public function test_admin_role_sync_excludes_super_admin_permissions(): void
    {
        // Legacy tenant DB still holds the permission row.
        $this->legacySuperAdminPermission();
        $adminRoleId = $this->tenantRoleId('admin');

        $response = $this->putJson("/api/v1/roles/{$adminRoleId}/permissions", [
            'permissions' => ['pos.access'],
        ], $this->tenantHeaders($this->tenant))->assertStatus(200);

        $this->assertNotContains('super_admin.access', $response->json('data.permissions'));
        $this->assertFalse($this->roleHasPermission('admin', 'super_admin.access'));
    }

    public function test_super_admin_role_permissions_cannot_be_edited(): void
    {
        $role = $this->legacySuperAdminRole();

        $this->putJson("/api/v1/roles/{$role->id}/permissions", [
            'permissions' => ['pos.access'],
        ], $this->tenantHeaders($this->tenant))
            ->assertStatus(404);

        $this->assertTrue($this->roleHasPermission('super_admin', 'super_admin.access'));
    }

    public function test_cashier_cannot_update_role_permissions(): void
    {
        $cashierRoleId = $this->tenantRoleId('cashier');

        $this->putJson("/api/v1/roles/{$cashierRoleId}/permissions", [
            'permissions' => ['pos.access', 'roles.manage'],
        ], $this->tenantHeaders($this->tenant, $this->cashier))
            ->assertStatus(403);

        $this->assertFalse($this->roleHasPermission('cashier', 'roles.manage'));
    }

    public function test_tenant_admin_cannot_reach_central_roles_through_the_tenant_api(): void
    {
        $centralSuperAdminRoleId = (int) $this->seedCentralPlatformRoles()->id;
        $centralPivot = static fn (): array => DB::table('role_has_permissions')
            ->orderBy('role_id')->orderBy('permission_id')
            ->get()->map(fn ($r): array => (array) $r)->all();

        $this->endTenancy();
        $before = $centralPivot();

        // A central role id means nothing inside the tenant: the request runs on the tenant DB
        // (the id may or may not name some tenant role there), never on the central one.
        $status = $this->putJson("/api/v1/roles/{$centralSuperAdminRoleId}/permissions", [
            'permissions' => ['pos.access'],
        ], $this->tenantHeaders($this->tenant))->getStatusCode();

        $this->assertLessThan(500, $status);
        $this->endTenancy();
        $this->assertSame($before, $centralPivot(), 'The central role/permission pivot must be untouched.');
    }

    public function test_audit_command_is_read_only(): void
    {
        $this->legacySuperAdminRole();

        $count = static fn (): array => [
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_has_permissions' => DB::table('role_has_permissions')->count(),
            'model_has_roles' => DB::table('model_has_roles')->count(),
            'model_has_permissions' => DB::table('model_has_permissions')->count(),
        ];

        $before = ['central' => $count(), 'tenant' => $this->inTenant($this->tenant, $count)];

        $this->artisan('tenants:audit-super-admin')->assertExitCode(0);

        $this->endTenancy();
        $this->assertSame($before, ['central' => $count(), 'tenant' => $this->inTenant($this->tenant, $count)]);
    }
}
