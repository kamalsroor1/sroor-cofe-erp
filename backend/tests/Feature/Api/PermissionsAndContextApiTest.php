<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Store;
use App\Models\CashShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionsAndContextApiTest extends TestCase
{
    use RefreshDatabase;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN',
            'is_main'   => true,
            'is_active' => true,
        ]);
    }

    public function test_guest_can_fetch_system_translations(): void
    {
        $response = $this->getJson('/api/v1/system/translations?locale=ar');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'locale',
                'data',
            ])
            ->assertJson([
                'success' => true,
                'locale'  => 'ar',
            ]);
    }

    public function test_permissions_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/permissions');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_authenticated_user_can_fetch_permissions_tree_and_own_roles(): void
    {
        $role = Role::create(['name' => 'cashier']);
        $permission = Permission::create(['name' => 'pos.access']);
        $role->givePermissionTo($permission);

        $user = User::factory()->create([
            'name'      => 'محمد كاشير',
            'phone'     => '01099887766',
            'password'  => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole($role);

        $token = $user->createToken('test-spa')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
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
                        'daily_journal',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_admin'         => false,
                    'user_roles'       => ['cashier'],
                    'user_permissions' => ['pos.access'],
                ],
            ]);
    }

    public function test_system_context_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/system/context');

        $response->assertStatus(401)
            ->assertJson(['success' => false]);
    }

    public function test_authenticated_user_can_fetch_complete_system_bootstrap_context(): void
    {
        $role = Role::create(['name' => 'admin']);
        $user = User::factory()->create([
            'name'      => 'كمال سرور',
            'phone'     => '01012316954',
            'password'  => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $user->assignRole($role);

        // Open a cash shift for testing
        CashShift::create([
            'store_id'             => $this->store->id,
            'user_id'              => $user->id,
            'shift_number'         => 'SH-001',
            'opening_cash_balance' => 500.000,
            'opened_at'            => now(),
            'status'               => 'open',
        ]);

        $token = $user->createToken('test-spa')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('X-Store-Id', (string)$this->store->id)
            ->getJson('/api/v1/system/context');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'auth' => ['user', 'is_impersonating'],
                    'active_store' => ['id', 'name', 'code'],
                    'stores',
                    'active_shift' => ['id', 'shift_number', 'opening_cash_balance'],
                    'system' => ['company_name', 'company_subtitle', 'system_theme_color', 'server_time'],
                    'branding' => ['logo_light', 'logo_dark', 'logo'],
                    'notifications',
                    'locale',
                    'translations',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    'auth' => [
                        'user' => [
                            'name' => 'كمال سرور',
                        ],
                    ],
                    'active_store' => [
                        'id'   => $this->store->id,
                        'name' => 'المخزن الرئيسي',
                    ],
                ],
            ]);
    }
}
