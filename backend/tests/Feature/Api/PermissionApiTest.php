<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionApiTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;
    protected User $adminUser;
    protected string $adminToken;
    protected User $staffUser;
    protected string $staffToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-001',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');
        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $cashierRole = Role::findByName('cashier');
        $this->staffUser = User::factory()->create([
            'name'             => 'أحمد كاشير',
            'phone'            => '01099887766',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->staffUser->assignRole($cashierRole);
        $this->staffToken = $this->staffUser->createToken('staff-token')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/permissions');
        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_authenticated_admin_fetches_permissions_tree_with_is_admin_true(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/permissions');

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
                'data'    => [
                    'is_admin'   => true,
                    'user_roles' => ['admin'],
                ],
            ]);
    }

    public function test_authenticated_staff_fetches_permissions_tree_with_exact_role_and_permissions(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->staffToken)
            ->getJson('/api/v1/permissions');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_admin'   => false,
                    'user_roles' => ['cashier'],
                ],
            ]);

        $userPermissions = $response->json('data.user_permissions');
        $this->assertIsArray($userPermissions);
        $this->assertContains('pos.access', $userPermissions);
    }
}
