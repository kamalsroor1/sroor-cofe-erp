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

class RoleApiTest extends TestCase
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

        $this->store = Store::create([
            'name'      => 'المحمصة الرئيسية',
            'code'      => 'MAIN-01',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->adminRole = Role::findByName('admin');
        $this->cashierRole = Role::findByName('cashier');

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
        $response = $this->getJson('/api/v1/roles');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_roles(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/roles');

        $response->assertStatus(403);
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

    public function test_can_get_matrix_for_specific_role(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/roles?role_id=' . $this->cashierRole->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'selected_role' => [
                        'id'   => $this->cashierRole->id,
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

    public function test_update_role_permissions_fails_with_invalid_permission(): void
    {
        $payload = [
            'permissions' => ['non_existing_permission_xyz'],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson("/api/v1/roles/{$this->cashierRole->id}/permissions", $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['permissions.0']);
    }
}
