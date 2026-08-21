<?php

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StockTransfersApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $sourceStore;
    protected Store $destStore;
    protected Item $itemA;
    protected Item $itemB;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'stores.view']);
        Permission::create(['name' => 'stores.manage']);

        $this->sourceStore = Store::create([
            'name'      => 'المستودع الرئيسي',
            'code'      => 'WH-MAIN',
            'type'      => 'warehouse',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->destStore = Store::create([
            'name'      => 'فرع مدينة نصر',
            'code'      => 'BR-NASR',
            'type'      => 'retail',
            'is_main'   => false,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->sourceStore->id,
        ]);
        $this->adminUser->assignRole($role);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->itemA = Item::create([
            'name'            => 'بن كولومبي سوبريمو',
            'code'            => 'BN-COL-SUP',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '420.000',
            'selling_price'   => '580.000',
            'price_retail'    => '580.000',
            'price_wholesale' => '540.000',
            'current_stock'   => '100.000',
            'min_stock'       => '20.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->sourceStore->id,
            'item_id'  => $this->itemA->id,
            'quantity' => '100.000',
        ]);

        $this->itemB = Item::create([
            'name'            => 'بن يمني مطري',
            'code'            => 'BN-YEM-MAT',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '850.000',
            'selling_price'   => '1100.000',
            'price_retail'    => '1100.000',
            'price_wholesale' => '1000.000',
            'current_stock'   => '50.000',
            'min_stock'       => '10.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->sourceStore->id,
            'item_id'  => $this->itemB->id,
            'quantity' => '50.000',
        ]);
    }

    public function test_can_list_transfers_with_summary_counts(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/transfers');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_count', 'confirmed_count', 'cancelled_count'],
            ]);
    }

    public function test_can_create_and_execute_stock_transfer(): void
    {
        $payload = [
            'from_store_id' => $this->sourceStore->id,
            'to_store_id'   => $this->destStore->id,
            'transfer_date' => now()->toDateString(),
            'notes'         => 'تحويل بضاعة افتتاح الفرع',
            'items'         => [
                [
                    'item_id'  => $this->itemA->id,
                    'quantity' => 25.000,
                ],
                [
                    'item_id'  => $this->itemB->id,
                    'quantity' => 10.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/transfers', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'from_store_id' => $this->sourceStore->id,
                    'to_store_id'   => $this->destStore->id,
                    'status'        => 'confirmed',
                    'items_count'   => 2,
                ],
            ]);

        // Source store stock decreased: 100 - 25 = 75, 50 - 10 = 40
        $sourceStockA = StoreStock::where('store_id', $this->sourceStore->id)->where('item_id', $this->itemA->id)->value('quantity');
        $this->assertEquals(75.000, (float)$sourceStockA);

        $sourceStockB = StoreStock::where('store_id', $this->sourceStore->id)->where('item_id', $this->itemB->id)->value('quantity');
        $this->assertEquals(40.000, (float)$sourceStockB);

        // Destination store stock increased: 0 + 25 = 25, 0 + 10 = 10
        $destStockA = StoreStock::where('store_id', $this->destStore->id)->where('item_id', $this->itemA->id)->value('quantity');
        $this->assertEquals(25.000, (float)$destStockA);

        $destStockB = StoreStock::where('store_id', $this->destStore->id)->where('item_id', $this->itemB->id)->value('quantity');
        $this->assertEquals(10.000, (float)$destStockB);
    }

    public function test_can_show_single_stock_transfer(): void
    {
        $payload = [
            'from_store_id' => $this->sourceStore->id,
            'to_store_id'   => $this->destStore->id,
            'transfer_date' => now()->toDateString(),
            'items'         => [
                [
                    'item_id'  => $this->itemA->id,
                    'quantity' => 5.000,
                ],
            ],
        ];

        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/transfers', $payload);

        $transferId = $createRes->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/transfers/' . $transferId);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'          => $transferId,
                    'items_count' => 1,
                ],
            ]);
    }

    public function test_can_cancel_transfer_and_rollback_inventory(): void
    {
        $payload = [
            'from_store_id' => $this->sourceStore->id,
            'to_store_id'   => $this->destStore->id,
            'transfer_date' => now()->toDateString(),
            'items'         => [
                [
                    'item_id'  => $this->itemA->id,
                    'quantity' => 30.000,
                ],
            ],
        ];

        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/transfers', $payload);

        $transferId = $createRes->json('data.id');

        // Source = 70, Dest = 30
        $this->assertEquals(70.000, (float)StoreStock::where('store_id', $this->sourceStore->id)->where('item_id', $this->itemA->id)->value('quantity'));
        $this->assertEquals(30.000, (float)StoreStock::where('store_id', $this->destStore->id)->where('item_id', $this->itemA->id)->value('quantity'));

        // Cancel
        $cancelRes = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/transfers/' . $transferId . '/cancel', [
                'reason' => 'إلغاء أمر النقل بناءً على طلب إدارة التشغيل',
            ]);

        $cancelRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'status'       => 'cancelled',
                    'is_cancelled' => true,
                ],
            ]);

        // Source restored to 100, Dest deducted back to 0
        $this->assertEquals(100.000, (float)StoreStock::where('store_id', $this->sourceStore->id)->where('item_id', $this->itemA->id)->value('quantity'));
        $this->assertEquals(0.000, (float)StoreStock::where('store_id', $this->destStore->id)->where('item_id', $this->itemA->id)->value('quantity'));
    }
}
