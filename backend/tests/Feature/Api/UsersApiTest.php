<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class UsersApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/users', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_users_management(): void
    {
        $response = $this->getJson('/api/v1/users', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_can_list_users_with_roles_and_stores(): void
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

    public function test_can_show_user_profile(): void
    {
        $targetUser = $this->createTenantUser($this->tenant, 'cashier', attributes: [
            'name' => 'أحمد كاشير',
            'phone' => '01000007006',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->getJson("/api/v1/users/{$targetUser->id}", $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $targetUser->id,
                    'name' => 'أحمد كاشير',
                    'phone' => '01000007006',
                ],
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

    public function test_create_user_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'اسم بدون هاتف وبدون كلمة مرور',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'password', 'role']);
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

    public function test_users_of_another_tenant_are_invisible_and_immutable(): void
    {
        $other = $this->createTenant();
        $foreign = $this->createTenantUser($other, 'cashier', attributes: [
            'name' => 'كاشير مستأجر آخر',
            'phone' => '01000007010',
        ]);

        $list = $this->getJson('/api/v1/users', $this->adminHeaders)->assertOk();
        $list->assertJsonMissing(['name' => 'كاشير مستأجر آخر']);
        $this->assertNotContains($foreign->id, array_column((array) $list->json('data'), 'id'));

        $this->getJson("/api/v1/users/{$foreign->id}", $this->adminHeaders)->assertNotFound();
        $this->putJson("/api/v1/users/{$foreign->id}", [
            'name' => 'اختراق',
            'phone' => '01000007010',
            'role' => 'admin',
            'default_store_id' => $this->storeId,
            'is_active' => true,
        ], $this->adminHeaders)->assertNotFound();
        // Not found in this tenant's DB. (Today toggle/destroy catch every Throwable and answer 422, not 404.)
        $toggle = $this->patchJson("/api/v1/users/{$foreign->id}/toggle-active", [], $this->adminHeaders);
        $this->assertContains($toggle->status(), [404, 422]);
        $toggle->assertJsonPath('success', false);
        $delete = $this->deleteJson("/api/v1/users/{$foreign->id}", [], $this->adminHeaders);
        $this->assertContains($delete->status(), [404, 422]);
        $delete->assertJsonPath('success', false);

        $this->inTenant($other, function () use ($foreign): void {
            $user = User::findOrFail($foreign->id);
            $this->assertSame('كاشير مستأجر آخر', $user->name);
            $this->assertTrue((bool) $user->is_active);
            $this->assertTrue($user->hasRole('cashier'));
            $this->assertFalse($user->hasRole('admin'));
        });
    }
}
