<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class UsersAndRolesApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected int $storeId;

    protected int $cashierRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        // The harness tenant carries the real PermissionsSeeder matrix (admin, cashier, …).
        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);
        $this->cashierRoleId = $this->inTenant($this->tenant, fn (): int => (int) Role::findByName('cashier')->id);
    }

    public function test_can_list_users(): void
    {
        $response = $this->getJson('/api/v1/users', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'name', 'phone', 'email', 'is_active', 'roles', 'primary_role'],
                ],
                'roles',
                'stores',
                'pagination',
            ]);
    }

    public function test_can_create_new_user(): void
    {
        $payload = [
            'name' => 'محمود الكاشير',
            'phone' => '01000007002',
            'email' => 'cashier@sroor.com',
            'password' => 'secret123',
            'role' => 'cashier',
            'default_store_id' => $this->storeId,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/users', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'محمود الكاشير',
                    'phone' => '01000007002',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('users', [
            'phone' => '01000007002',
            'name' => 'محمود الكاشير',
        ]));
    }

    public function test_can_update_user(): void
    {
        $targetUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'name' => 'موظف سابق',
            'phone' => '01000007007',
            'password' => Hash::make('oldpass'),
        ]);

        $payload = [
            'name' => 'موظف معدل',
            'phone' => '01000007007',
            'email' => 'updated@sroor.com',
            'role' => 'cashier',
            'default_store_id' => $this->storeId,
            'is_active' => true,
        ];

        $response = $this->putJson("/api/v1/users/{$targetUser->id}", $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'موظف معدل',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'موظف معدل',
        ]));
    }

    public function test_can_toggle_user_active_state(): void
    {
        $targetUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'phone' => '01000007004',
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/v1/users/{$targetUser->id}/toggle-active", [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_active' => false,
            ]);

        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => (bool) User::findOrFail($targetUser->id)->is_active));
    }

    public function test_can_delete_user_and_prevent_self_deletion(): void
    {
        $targetUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'phone' => '01000007009',
        ]);

        // Delete another user
        $response = $this->deleteJson("/api/v1/users/{$targetUser->id}", [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, fn () => $this->assertSoftDeleted('users', ['id' => $targetUser->id]));

        // Attempt self-deletion
        $selfResponse = $this->deleteJson("/api/v1/users/{$this->adminUser->id}", [], $this->adminHeaders);

        $selfResponse->assertStatus(422)
            ->assertJson(['success' => false]);
    }

    public function test_can_get_roles_permissions_matrix(): void
    {
        $response = $this->getJson('/api/v1/roles', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'roles',
                    'selected_role',
                    'permission_modules',
                ],
            ]);
    }

    public function test_can_update_role_permissions(): void
    {
        $payload = [
            'permissions' => ['pos.access', 'invoices.view'],
        ];

        $response = $this->putJson("/api/v1/roles/{$this->cashierRoleId}/permissions", $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $this->cashierRoleId,
                    'name' => 'cashier',
                ],
            ]);

        $this->assertTrue($this->inTenant($this->tenant, function (): bool {
            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return Role::findById($this->cashierRoleId)->hasPermissionTo('invoices.view');
        }));
    }

    public function test_can_get_activity_logs(): void
    {
        $this->inTenant($this->tenant, fn () => ActivityLog::create([
            'module' => 'sales',
            'action' => 'created',
            'description' => 'تم إنشاء فاتورة مبيعات جديدة',
            'user_id' => $this->adminUser->id,
            'store_id' => $this->storeId,
            'ip_address' => '127.0.0.1',
        ]));

        $response = $this->getJson('/api/v1/activity-logs', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data',
                'stats',
                'total_count',
                'pagination',
                'users',
                'stores',
                'modules_list',
            ]);
    }

    public function test_changing_a_role_in_one_tenant_never_changes_the_same_role_in_another(): void
    {
        $other = $this->createTenant();
        // Roles are cloned from the same seeded template, so B's cashier has the SAME id as A's.
        $otherCashierId = $this->inTenant($other, fn (): int => (int) Role::findByName('cashier')->id);
        $this->assertSame($this->cashierRoleId, $otherCashierId);
        $before = $this->rolePermissions($other, $otherCashierId);
        $this->assertContains('pos.access', $before);
        $this->assertNotContains('roles.manage', $before);

        $this->putJson("/api/v1/roles/{$this->cashierRoleId}/permissions", [
            'permissions' => ['roles.manage'],
        ], $this->adminHeaders)->assertOk();

        $this->assertSame(['roles.manage'], $this->rolePermissions($this->tenant, $this->cashierRoleId));
        $this->assertSame($before, $this->rolePermissions($other, $otherCashierId), 'A role edit leaked into another tenant.');

        // Behavioural check through B's API: B's cashier still cannot manage users (roles.manage), and still has POS.
        $bCashier = $this->createTenantUser($other, 'cashier');
        $this->getJson('/api/v1/users', $this->tenantHeaders($other, $bCashier))->assertForbidden();
        $this->assertTrue($this->inTenant($other, fn (): bool => User::findOrFail($bCashier->id)->can('pos.access')));
    }

    /** @return list<string> sorted permission names of $roleId inside $tenant */
    private function rolePermissions(Tenant $tenant, int $roleId): array
    {
        return $this->inTenant($tenant, function () use ($roleId): array {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $names = Role::findById($roleId)->permissions()->pluck('name')->all();
            sort($names);

            return $names;
        });
    }
}
