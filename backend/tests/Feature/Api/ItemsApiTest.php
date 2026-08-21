<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ItemsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'items.manage']);

        $this->store = Store::create([
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
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($role);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;
    }

    public function test_authenticated_user_can_list_items_with_metrics(): void
    {
        $item = Item::create([
            'name'            => 'بن برازيلي سانتوس فاخر',
            'code'            => 'COF-BR-001',
            'category'        => 'بن حبوب',
            'unit'            => 'كجم',
            'cost_price'      => '280.000',
            'selling_price'   => '380.000',
            'current_stock'   => '50.000',
            'min_stock_level' => '10.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $item->id,
            'quantity' => '50.000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/items');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_items', 'low_stock_count', 'total_stock_value'],
                'categories',
            ])
            ->assertJson([
                'success' => true,
                'summary' => [
                    'total_items' => 1,
                ],
            ]);
    }

    public function test_can_create_a_new_item_with_auto_initialized_store_stocks(): void
    {
        $payload = [
            'name'            => 'بن كولومبي سوبريمو',
            'code'            => 'COF-COL-001',
            'category'        => 'بن حبوب',
            'unit'            => 'كجم',
            'cost_price'      => '350.000',
            'selling_price'   => '480.000',
            'min_stock_level' => '5.000',
            'notes'           => 'تحميص وسط',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/items', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'            => 'بن كولومبي سوبريمو',
                    'code'            => 'COF-COL-001',
                    'category'        => 'بن حبوب',
                    'unit'            => 'كجم',
                    'cost_price'      => 350.000,
                    'selling_price'   => 480.000,
                    'min_stock_level' => 5.000,
                    'current_stock'   => 0.000,
                ],
            ]);

        $this->assertDatabaseHas('items', [
            'name' => 'بن كولومبي سوبريمو',
            'code' => 'COF-COL-001',
        ]);

        $this->assertDatabaseHas('store_stocks', [
            'store_id' => $this->store->id,
            'quantity' => '0.000',
        ]);
    }

    public function test_can_view_single_item_details(): void
    {
        $item = Item::create([
            'name'            => 'بن يمني مطري',
            'code'            => 'COF-YEM-001',
            'category'        => 'بن حبوب',
            'unit'            => 'كجم',
            'cost_price'      => '500.000',
            'selling_price'   => '700.000',
            'current_stock'   => '15.000',
            'min_stock_level' => '2.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/items/' . $item->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'   => $item->id,
                    'name' => 'بن يمني مطري',
                    'code' => 'COF-YEM-001',
                ],
            ]);
    }

    public function test_can_update_item_details(): void
    {
        $item = Item::create([
            'name'            => 'بن حبوب',
            'code'            => 'COF-001',
            'category'        => 'بن',
            'unit'            => 'كجم',
            'cost_price'      => '100.000',
            'selling_price'   => '150.000',
            'current_stock'   => '10.000',
            'min_stock_level' => '2.000',
            'is_active'       => true,
        ]);

        $payload = [
            'name'            => 'بن إسبريسو بلند إيطالي',
            'code'            => 'COF-ESP-001',
            'category'        => 'خلطات إسبريسو',
            'unit'            => 'كجم',
            'cost_price'      => '320.000',
            'selling_price'   => '440.000',
            'min_stock_level' => '10.000',
            'notes'           => 'توليفة 80/20',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->putJson('/api/v1/items/' . $item->id, $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'name'          => 'بن إسبريسو بلند إيطالي',
                    'code'          => 'COF-ESP-001',
                    'selling_price' => 440.000,
                ],
            ]);

        $this->assertDatabaseHas('items', [
            'id'   => $item->id,
            'name' => 'بن إسبريسو بلند إيطالي',
        ]);
    }

    public function test_can_toggle_item_active_status(): void
    {
        $item = Item::create([
            'name'            => 'صنف معطل',
            'code'            => 'ITM-DIS-001',
            'category'        => 'أخرى',
            'unit'            => 'قطعة',
            'cost_price'      => '10.000',
            'selling_price'   => '15.000',
            'current_stock'   => '0.000',
            'min_stock_level' => '0.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->patchJson('/api/v1/items/' . $item->id . '/toggle-active');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'is_active' => false,
                ],
            ]);

        $this->assertDatabaseHas('items', [
            'id'        => $item->id,
            'is_active' => false,
        ]);
    }

    public function test_can_adjust_item_stock_and_record_movement(): void
    {
        $item = Item::create([
            'name'            => 'بن حب إثيوبي يرغاتشيف',
            'code'            => 'COF-ETH-001',
            'category'        => 'بن حبوب',
            'unit'            => 'كجم',
            'cost_price'      => '420.000',
            'selling_price'   => '580.000',
            'current_stock'   => '10.000',
            'min_stock_level' => '5.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $item->id,
            'quantity' => '10.000',
        ]);

        $payload = [
            'store_id'      => $this->store->id,
            'movement_type' => 'stock_adjustment_in',
            'quantity'      => '5.500',
            'notes'         => 'جرد زيادة',
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/items/' . $item->id . '/adjust-stock', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'movement' => [
                    'quantity'      => '5.500',
                    'stock_before'  => '10.000',
                    'stock_after'   => '15.500',
                    'movement_type' => 'stock_adjustment_in',
                ],
            ]);

        $this->assertDatabaseHas('store_stocks', [
            'store_id' => $this->store->id,
            'item_id'  => $item->id,
            'quantity' => '15.500',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'item_id'       => $item->id,
            'store_id'      => $this->store->id,
            'movement_type' => 'stock_adjustment_in',
        ]);
    }

    public function test_can_view_item_movements_ledger(): void
    {
        $item = Item::create([
            'name'            => 'هيل جامبو هندي فاخر',
            'code'            => 'SP-CARD-001',
            'category'        => 'بهارات وتوابل',
            'unit'            => 'كجم',
            'cost_price'      => '900.000',
            'selling_price'   => '1200.000',
            'current_stock'   => '8.000',
            'min_stock_level' => '2.000',
            'is_active'       => true,
        ]);

        StockMovement::create([
            'item_id'         => $item->id,
            'store_id'        => $this->store->id,
            'user_id'         => $this->adminUser->id,
            'movement_type'   => 'purchase_in',
            'quantity'        => '10.000',
            'stock_before'    => '0.000',
            'stock_after'     => '10.000',
            'unit_cost'       => '900.000',
            'source_type'     => Item::class,
            'source_id'       => $item->id,
            'document_number' => 'PO-260821-0001',
        ]);

        StockMovement::create([
            'item_id'         => $item->id,
            'store_id'        => $this->store->id,
            'user_id'         => $this->adminUser->id,
            'movement_type'   => 'sales_out',
            'quantity'        => '2.000',
            'stock_before'    => '10.000',
            'stock_after'     => '8.000',
            'unit_cost'       => '900.000',
            'source_type'     => Item::class,
            'source_id'       => $item->id,
            'document_number' => 'INV-260821-0001',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/items/' . $item->id . '/movements');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'stats' => [
                        'total_in'        => 10.000,
                        'total_out'       => 2.000,
                        'net_movement'    => 8.000,
                        'movements_count' => 2,
                    ],
                ],
            ]);
    }

    public function test_can_view_low_stock_radar(): void
    {
        $lowItem = Item::create([
            'name'            => 'بن جواتيمالا أنتيجوا',
            'code'            => 'COF-GUA-001',
            'category'        => 'بن حبوب',
            'unit'            => 'كجم',
            'cost_price'      => '380.000',
            'selling_price'   => '520.000',
            'current_stock'   => '2.000',
            'min_stock_level' => '10.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $lowItem->id,
            'quantity' => '2.000',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/items/low-stock');

        $response->assertStatus(200)
            ->assertJson([
                'success'   => true,
                'count'     => 1,
                'low_items' => [
                    [
                        'id'              => $lowItem->id,
                        'name'            => 'بن جواتيمالا أنتيجوا',
                        'current_stock'   => '2.000',
                        'min_stock_level' => '10.000',
                        'deficit'         => '8.000',
                    ],
                ],
            ]);
    }

    public function test_can_delete_unused_item(): void
    {
        $item = Item::create([
            'name'            => 'صنف تجريبي قابل للحذف',
            'code'            => 'DEL-001',
            'category'        => 'تجريبي',
            'unit'            => 'قطعة',
            'cost_price'      => '5.000',
            'selling_price'   => '10.000',
            'current_stock'   => '0.000',
            'min_stock_level' => '0.000',
            'is_active'       => true,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/items/' . $item->id);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('items', [
            'id' => $item->id,
        ]);
    }
}
