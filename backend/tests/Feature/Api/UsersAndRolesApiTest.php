<?php

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UsersAndRolesApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;
    protected Role $adminRole;
    protected Role $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create(['name' => 'admin']);
        $this->cashierRole = Role::create(['name' => 'cashier']);

        Permission::create(['name' => 'pos.access']);
        Permission::create(['name' => 'invoices.view']);
        Permission::create(['name' => 'users.manage']);
        Permission::create(['name' => 'roles.manage']);
        Permission::create(['name' => 'logs.view']);

        $this->cashierRole->givePermissionTo('pos.access');

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
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;
    }

    public function test_can_list_users(): void
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

        $this->assertFalse($targetUser->fresh()->is_active);
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

    public function test_can_get_roles_permissions_matrix(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/roles');

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

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/roles/{$this->cashierRole->id}/permissions", $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'   => $this->cashierRole->id,
                    'name' => 'cashier',
                ],
            ]);

        $this->assertTrue($this->cashierRole->fresh()->hasPermissionTo('invoices.view'));
    }

    public function test_can_get_activity_logs(): void
    {
        ActivityLog::create([
            'module'      => 'sales',
            'action'      => 'created',
            'description' => 'تم إنشاء فاتورة مبيعات جديدة',
            'user_id'     => $this->adminUser->id,
            'store_id'    => $this->store->id,
            'ip_address'  => '127.0.0.1',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/activity-logs');

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
}
