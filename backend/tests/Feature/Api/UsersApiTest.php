<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected User $unauthorizedUser;
    protected string $unauthorizedToken;
    protected Store $store;
    protected Role $adminRole;
    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->adminRole = Role::findByName('admin');
        $this->cashierRole = Role::findByName('cashier');

        $this->store = Store::create([
            'name'      => 'المحمصة الرئيسية',
            'code'      => 'MAIN-01',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور المدير',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($this->adminRole);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name'             => 'مستخدم بدون صلاحيات',
            'phone'            => '01000000000',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/users');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_users_management(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/users');

        $response->assertStatus(403);
    }

    public function test_can_list_users_with_roles_and_stores(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/users');

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
        $targetUser = User::factory()->create([
            'name'             => 'أحمد كاشير',
            'phone'            => '01088887777',
            'password'         => Hash::make('secret123'),
            'default_store_id' => $this->store->id,
        ]);
        $targetUser->assignRole($this->cashierRole);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'    => $targetUser->id,
                    'name'  => 'أحمد كاشير',
                    'phone' => '01088887777',
                ],
            ]);
    }

    public function test_can_create_new_user(): void
    {
        $payload = [
            'name'             => 'محمود الكاشير',
            'phone'            => '01011112222',
            'email'            => 'cashier@sroor.com',
            'password'         => 'secret123',
            'role'             => 'cashier',
            'default_store_id' => $this->store->id,
            'is_active'        => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/users', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'  => 'محمود الكاشير',
                    'phone' => '01011112222',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'phone' => '01011112222',
            'name'  => 'محمود الكاشير',
        ]);
    }

    public function test_create_user_fails_validation_on_missing_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/users', [
                'name' => 'اسم بدون هاتف وبدون كلمة مرور',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone', 'password', 'role']);
    }

    public function test_can_update_user(): void
    {
        $targetUser = User::factory()->create([
            'name'             => 'موظف سابق',
            'phone'            => '01099990000',
            'password'         => Hash::make('oldpass'),
            'default_store_id' => $this->store->id,
        ]);
        $targetUser->assignRole($this->cashierRole);

        $payload = [
            'name'             => 'موظف معدل',
            'phone'            => '01099990000',
            'email'            => 'updated@sroor.com',
            'role'             => 'cashier',
            'default_store_id' => $this->store->id,
            'is_active'        => true,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/users/{$targetUser->id}", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name' => 'موظف معدل',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id'   => $targetUser->id,
            'name' => 'موظف معدل',
        ]);
    }

    public function test_can_toggle_user_active_state(): void
    {
        $targetUser = User::factory()->create([
            'phone'     => '01055554444',
            'is_active' => true,
        ]);
        $targetUser->assignRole($this->cashierRole);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson("/api/v1/users/{$targetUser->id}/toggle-active");

        $response->assertStatus(200)
            ->assertJson([
                'success'   => true,
                'is_active' => false,
            ]);

        $this->assertFalse((bool)$targetUser->fresh()->is_active);
    }

    public function test_can_delete_user_and_prevent_self_deletion(): void
    {
        $targetUser = User::factory()->create([
            'phone' => '01077778888',
        ]);
        $targetUser->assignRole($this->cashierRole);

        // Delete another user
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/users/{$targetUser->id}");

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('users', ['id' => $targetUser->id]);

        // Attempt self-deletion
        $selfResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson("/api/v1/users/{$this->adminUser->id}");

        $selfResponse->assertStatus(422)
            ->assertJson(['success' => false]);
    }
}
