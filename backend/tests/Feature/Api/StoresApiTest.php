<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StoresApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $mainStore;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'stores.manage']);

        $this->mainStore = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'warehouse',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->adminUser->assignRole($role);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;
    }

    public function test_authenticated_user_can_list_stores(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/stores');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'active_store',
                'stores',
                'all_users',
            ])
            ->assertJson([
                'success' => true,
            ]);

        $this->assertNotEmpty($response->json('stores'));
    }

    public function test_admin_can_create_a_new_store(): void
    {
        $payload = [
            'name'    => 'فرع مدينة نصر',
            'code'    => 'NASR-01',
            'type'    => 'retail_shop',
            'address' => 'شارع عباس العقاد',
            'phone'   => '01011223344',
            'is_main' => false,
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/stores', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name' => 'فرع مدينة نصر',
                    'code' => 'NASR-01',
                    'type' => 'retail_shop',
                ],
            ]);

        $this->assertDatabaseHas('stores', [
            'name' => 'فرع مدينة نصر',
            'code' => 'NASR-01',
        ]);
    }

    public function test_can_view_single_store_details(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/stores/' . $this->mainStore->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'   => $this->mainStore->id,
                    'name' => 'المخزن الرئيسي',
                ],
            ]);
    }

    public function test_admin_can_update_store_details(): void
    {
        $branch = Store::create([
            'name'      => 'فرع المعادي',
            'code'      => 'MAADI-01',
            'type'      => 'retail_shop',
            'is_main'   => false,
            'is_active' => true,
        ]);

        $payload = [
            'name'    => 'فرع المعادي الجديد',
            'code'    => 'MAADI-02',
            'type'    => 'retail_shop',
            'address' => 'شارع 9',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/stores/' . $branch->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name' => 'فرع المعادي الجديد',
                    'code' => 'MAADI-02',
                ],
            ]);

        $this->assertDatabaseHas('stores', [
            'id'   => $branch->id,
            'name' => 'فرع المعادي الجديد',
            'code' => 'MAADI-02',
        ]);
    }

    public function test_cannot_disable_main_store(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson('/api/v1/stores/' . $this->mainStore->id . '/toggle-active');

        $response->assertStatus(422)
            ->assertJsonStructure(['errors']);
    }

    public function test_can_toggle_active_status_of_regular_store(): void
    {
        $branch = Store::create([
            'name'      => 'عربية توزيع 1',
            'code'      => 'VAN-01',
            'type'      => 'van',
            'is_main'   => false,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson('/api/v1/stores/' . $branch->id . '/toggle-active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('stores', [
            'id'        => $branch->id,
            'is_active' => false,
        ]);
    }

    public function test_can_assign_users_to_store(): void
    {
        $staff = User::factory()->create([
            'name'      => 'أحمد كاشير',
            'phone'     => '01033445566',
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/stores/' . $this->mainStore->id . '/assign-users', [
                'user_ids' => [$staff->id],
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('store_user', [
            'store_id' => $this->mainStore->id,
            'user_id'  => $staff->id,
        ]);
    }

    public function test_can_fetch_store_stocks_with_valuation(): void
    {
        $item = Item::create([
            'name'            => 'بن برازيلي كولومبي',
            'code'            => 'COF-001',
            'cost_price'      => 200.000,
            'selling_price'   => 280.000,
            'min_stock_level' => 10.000,
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->mainStore->id,
            'item_id'  => $item->id,
            'quantity' => 25.000,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/stores/stocks?store_id=' . $this->mainStore->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'item_id',
                        'item_name',
                        'item_code',
                        'quantity',
                        'cost_price',
                        'total_valuation',
                    ],
                ],
                'meta',
            ])
            ->assertJson([
                'success' => true,
                'data'    => [
                    [
                        'item_name'       => 'بن برازيلي كولومبي',
                        'quantity'        => 25.000,
                        'total_valuation' => 5000.000, // 25 * 200
                    ],
                ],
            ]);
    }

    public function test_user_can_switch_active_store(): void
    {
        $branch = Store::create([
            'name'      => 'فرع الإسكندرية',
            'code'      => 'ALX-01',
            'type'      => 'retail_shop',
            'is_main'   => false,
            'is_active' => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/stores/switch', [
                'store_id' => $branch->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success'      => true,
                'active_store' => [
                    'id'   => $branch->id,
                    'name' => 'فرع الإسكندرية',
                ],
            ]);

        $this->assertEquals($branch->id, $this->adminUser->fresh()->default_store_id);
    }
}
