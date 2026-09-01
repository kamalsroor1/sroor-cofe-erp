<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SystemContextApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'warehouse',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'email'            => 'kamal@sroor.com',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/system/context');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_get_system_context_bootstrap_payload(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/system/context');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'auth' => ['user'],
                    'active_store',
                    'stores',
                    'system' => ['platform_name', 'company_name', 'system_theme_color'],
                    'branding',
                    'notifications',
                    'locale',
                    'translations',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_system_context_includes_active_store_and_stores_list(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->withHeader('X-Store-Id', (string)$this->store->id)
            ->getJson('/api/v1/system/context');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'active_store' => [
                        'id'   => $this->store->id,
                        'name' => 'المخزن الرئيسي',
                    ],
                ],
            ]);
    }

    public function test_system_context_includes_low_stock_and_debt_alerts(): void
    {
        Item::create([
            'name'            => 'بن كولومبي ناقص',
            'code'            => 'BN-LOW',
            'category'        => 'coffee_beans',
            'cost_price'      => '400.000',
            'selling_price'   => '550.000',
            'current_stock'   => '5.000',
            'min_stock_level' => '20.000',
            'is_active'       => true,
        ]);

        Customer::create([
            'name'            => 'عميل مدين',
            'phone'           => '01011112222',
            'current_balance' => '1500.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/system/context');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $notifications = $response->json('data.notifications');
        $this->assertNotEmpty($notifications);
    }

    public function test_can_fetch_translation_dictionary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/system/translations?locale=ar');

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
}
