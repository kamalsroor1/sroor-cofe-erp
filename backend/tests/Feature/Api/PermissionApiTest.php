<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class PermissionApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        // The harness tenant carries the real PermissionsSeeder matrix and an `admin` user.
        $this->tenant = $this->createTenant();
        $this->staffUser = $this->createTenantUser($this->tenant, 'cashier', attributes: ['name' => 'أحمد كاشير']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/permissions', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_authenticated_admin_fetches_permissions_tree_with_is_admin_true(): void
    {
        $response = $this->getJson('/api/v1/permissions', $this->tenantHeaders($this->tenant));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_permissions',
                    'user_roles',
                    'is_admin',
                    'permission_modules' => [
                        'sales' => ['title', 'icon', 'permissions'],
                        'inventory',
                        'purchases',
                        'customers',
                        'suppliers',
                        'expenses',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_admin' => true,
                    'user_roles' => ['admin'],
                ],
            ]);
    }

    public function test_authenticated_staff_fetches_permissions_tree_with_exact_role_and_permissions(): void
    {
        $response = $this->getJson('/api/v1/permissions', $this->tenantHeaders($this->tenant, $this->staffUser));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_admin' => false,
                    'user_roles' => ['cashier'],
                ],
            ]);

        $userPermissions = $response->json('data.user_permissions');
        $this->assertIsArray($userPermissions);
        $this->assertContains('pos.access', $userPermissions);
    }

    public function test_permissions_tree_reflects_only_the_current_tenants_role_matrix(): void
    {
        $other = $this->createTenant();
        // Tenant B widens its own cashier role; tenant A's cashier must not see that grant.
        $this->inTenant($other, fn () => Role::findByName('cashier')->givePermissionTo('reports.view'));
        $otherCashier = $this->createTenantUser($other, 'cashier');

        $mine = $this->getJson('/api/v1/permissions', $this->tenantHeaders($this->tenant, $this->staffUser))
            ->assertOk()
            ->json('data.user_permissions');
        $theirs = $this->getJson('/api/v1/permissions', $this->tenantHeaders($other, $otherCashier))
            ->assertOk()
            ->json('data.user_permissions');

        $this->assertNotContains('reports.view', $mine, 'A role grant in another tenant leaked into this tenant.');
        $this->assertContains('reports.view', $theirs);
    }

    public function test_token_issued_in_one_tenant_is_rejected_by_another_tenant(): void
    {
        $other = $this->createTenant();
        $headers = $this->tenantHeaders($this->tenant, $this->staffUser);
        $headers['X-Tenant'] = (string) $other->getTenantKey();

        $this->getJson('/api/v1/permissions', $headers)
            ->assertStatus(401)
            ->assertJson(['success' => false]);
    }
}
