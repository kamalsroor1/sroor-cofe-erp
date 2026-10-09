<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

class RoleApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    protected int $cashierRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->adminHeaders = $this->tenantHeaders($this->tenant);
        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->cashierRoleId = $this->inTenant($this->tenant, fn (): int => (int) Role::findByName('cashier')->id);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/roles', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_roles(): void
    {
        $response = $this->getJson('/api/v1/roles', $this->tenantHeaders($this->tenant, $this->unauthorizedUser));

        $response->assertStatus(403);
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

    public function test_can_get_matrix_for_specific_role(): void
    {
        $response = $this->getJson('/api/v1/roles?role_id='.$this->cashierRoleId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'selected_role' => [
                        'id' => $this->cashierRoleId,
                        'name' => 'cashier',
                    ],
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

        $this->assertSame(['invoices.view', 'pos.access'], $this->rolePermissions($this->tenant, $this->cashierRoleId));
    }

    public function test_update_role_permissions_fails_with_invalid_permission(): void
    {
        $before = $this->rolePermissions($this->tenant, $this->cashierRoleId);

        $payload = [
            'permissions' => ['non_existing_permission_xyz'],
        ];

        $response = $this->putJson("/api/v1/roles/{$this->cashierRoleId}/permissions", $payload, $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);

        $this->assertSame($before, $this->rolePermissions($this->tenant, $this->cashierRoleId), 'A rejected update must not change the role.');
    }

    public function test_another_tenants_custom_role_is_invisible_and_immutable(): void
    {
        $other = $this->createTenant();
        // Created after cloning, so the id is in tenant B's private range and does not exist in A.
        $foreignRoleId = $this->inTenant($other, function (): int {
            $role = Role::findOrCreate('مشرف وردية', 'web');
            $role->syncPermissions(['pos.access']);

            return (int) $role->id;
        });
        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => Role::query()->whereKey($foreignRoleId)->exists()));

        $list = $this->getJson('/api/v1/roles?role_id='.$foreignRoleId, $this->adminHeaders)->assertOk();
        $this->assertNotContains('مشرف وردية', array_column((array) $list->json('data.roles'), 'name'));
        $this->assertNull($list->json('data.selected_role'));

        $this->putJson("/api/v1/roles/{$foreignRoleId}/permissions", ['permissions' => ['reports.view']], $this->adminHeaders)
            ->assertNotFound();

        $this->assertSame(['pos.access'], $this->rolePermissions($other, $foreignRoleId), 'A role in another tenant was modified.');
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
