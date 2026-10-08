<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * P0-AUTH-4: the super_admin role and super_admin.* permissions belong to the CENTRAL
 * database only. A tenant DB (simulated here by seeding PermissionsSeeder alone) must
 * never create them, and a tenant admin must never be able to assign them.
 */
class SuperAdminRoleIsolationApiTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;

    protected User $admin;

    protected string $adminToken;

    protected User $cashier;

    protected string $cashierToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'مدير المستأجر',
            'phone' => '01000007031',
            'email' => 'tenant-admin@sroor.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->admin->assignRole('admin');
        $this->adminToken = $this->admin->createToken('admin')->plainTextToken;

        $this->cashier = User::factory()->create([
            'name' => 'كاشير',
            'phone' => '01000007015',
            'email' => 'cashier@sroor.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->cashier->assignRole('cashier');
        $this->cashierToken = $this->cashier->createToken('cashier')->plainTextToken;
    }

    /** Simulate a legacy tenant DB that was seeded before the fix. */
    private function legacySuperAdminRole(): Role
    {
        $permission = Permission::firstOrCreate(['name' => 'super_admin.access', 'guard_name' => 'web']);
        $role = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    /** @return array<string, string> */
    private function asAdmin(): array
    {
        return ['Authorization' => 'Bearer '.$this->adminToken, 'X-Store-Id' => (string) $this->store->id];
    }

    private function roleHasPermission(string $roleName, string $permission): bool
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return Role::findByName($roleName)->fresh('permissions')->permissions->contains('name', $permission);
    }

    public function test_tenant_permissions_seeder_creates_no_super_admin(): void
    {
        $this->assertDatabaseMissing('roles', ['name' => 'super_admin']);
        $this->assertSame(0, Permission::where('name', 'like', 'super_admin.%')->count());
        $this->assertFalse($this->roleHasPermission('admin', 'super_admin.access'));
    }

    public function test_tenant_admin_cannot_create_user_with_super_admin_role(): void
    {
        $this->legacySuperAdminRole();

        $this->withHeaders($this->asAdmin())
            ->postJson('/api/v1/users', [
                'name' => 'محاولة تصعيد',
                'phone' => '01000007016',
                'email' => 'escalate@sroor.test',
                'password' => 'secret123',
                'role' => 'super_admin',
                'default_store_id' => $this->store->id,
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertDatabaseMissing('users', ['phone' => '01000007016']);
        $this->assertSame(0, User::role('super_admin')->count());
    }

    public function test_tenant_admin_cannot_update_user_to_super_admin(): void
    {
        $this->legacySuperAdminRole();

        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/users/{$this->cashier->id}", [
                'name' => 'كاشير',
                'phone' => '01000007015',
                'email' => 'cashier@sroor.test',
                'role' => 'super_admin',
                'default_store_id' => $this->store->id,
                'is_active' => true,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['role']);

        $this->assertFalse($this->cashier->fresh()->hasRole('super_admin'));
        $this->assertTrue($this->cashier->fresh()->hasRole('cashier'));
    }

    public function test_role_permissions_rejects_super_admin_permission(): void
    {
        Permission::firstOrCreate(['name' => 'super_admin.access', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $cashierRole = Role::findByName('cashier');

        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/roles/{$cashierRole->id}/permissions", [
                'permissions' => ['pos.access', 'super_admin.access'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.1']);

        $this->assertFalse($this->roleHasPermission('cashier', 'super_admin.access'));
        // Rejected request must not partially apply: cashier keeps its prior permissions.
        $this->assertTrue($this->roleHasPermission('cashier', 'invoices.create'));
    }

    public function test_admin_role_sync_excludes_super_admin_permissions(): void
    {
        // Legacy tenant DB still holds the permission row.
        Permission::firstOrCreate(['name' => 'super_admin.access', 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $adminRole = Role::findByName('admin');

        $response = $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/roles/{$adminRole->id}/permissions", [
                'permissions' => ['pos.access'],
            ])
            ->assertStatus(200);

        $this->assertNotContains('super_admin.access', $response->json('data.permissions'));
        $this->assertFalse($this->roleHasPermission('admin', 'super_admin.access'));
    }

    public function test_super_admin_role_permissions_cannot_be_edited(): void
    {
        $role = $this->legacySuperAdminRole();

        $this->withHeaders($this->asAdmin())
            ->putJson("/api/v1/roles/{$role->id}/permissions", [
                'permissions' => ['pos.access'],
            ])
            ->assertStatus(404);

        $this->assertTrue($this->roleHasPermission('super_admin', 'super_admin.access'));
    }

    public function test_cashier_cannot_update_role_permissions(): void
    {
        $cashierRole = Role::findByName('cashier');

        $this->withHeaders(['Authorization' => 'Bearer '.$this->cashierToken])
            ->putJson("/api/v1/roles/{$cashierRole->id}/permissions", [
                'permissions' => ['pos.access', 'roles.manage'],
            ])
            ->assertStatus(403);

        $this->assertFalse($this->roleHasPermission('cashier', 'roles.manage'));
    }

    public function test_audit_command_is_read_only(): void
    {
        $this->legacySuperAdminRole();

        $before = [
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_has_permissions' => DB::table('role_has_permissions')->count(),
            'model_has_roles' => DB::table('model_has_roles')->count(),
            'model_has_permissions' => DB::table('model_has_permissions')->count(),
        ];

        $this->artisan('tenants:audit-super-admin')->assertExitCode(0);

        $after = [
            'roles' => DB::table('roles')->count(),
            'permissions' => DB::table('permissions')->count(),
            'role_has_permissions' => DB::table('role_has_permissions')->count(),
            'model_has_roles' => DB::table('model_has_roles')->count(),
            'model_has_permissions' => DB::table('model_has_permissions')->count(),
        ];

        $this->assertSame($before, $after);
    }
}
