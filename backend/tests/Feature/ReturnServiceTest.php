<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnServiceTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $mainStore;

    protected ReturnService $returnService;

    protected StockService $stockService;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $this->user = User::factory()->create();
        $this->user->assignRole($adminRole);
        $this->actingAs($this->user);

        $this->mainStore = Store::create([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-01',
            'type' => 'main_store',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->returnService = app(ReturnService::class);
        $this->stockService = app(StockService::class);
    }

    public function test_sales_return_increases_stock_and_reduces_customer_debt(): void
    {
        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'current_balance' => '500.000',
            'initial_balance' => '500.000',
        ]);

        $item = Item::create([
            'name' => 'بن برازيلي وسط',
            'code' => 'BRZ-01',
            'unit' => 'كجم',
            'selling_price' => '240.000',
            'cost_price' => '180.000',
            'weighted_avg_cost' => '180.000',
            'current_stock' => '0.000',
        ]);

        $this->stockService->addStock(
            item: $item,
            quantity: '10.000',
            unitCost: '180.000',
            source: $this->mainStore,
            documentNumber: 'INIT-001',
            movementType: 'initial_balance',
            notes: 'رصيد',
            storeId: $this->mainStore->id
        );

        $returnDoc = $this->returnService->createSalesReturn([
            'customer_id' => $customer->id,
            'store_id' => $this->mainStore->id,
            'reason' => 'إرجاع نصف كيلو بالخطأ',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => '0.500',
                    'unit_price' => '240.000',
                ],
            ],
        ]);

        $this->assertDatabaseHas('returns', [
            'id' => $returnDoc->id,
            'return_type' => 'sales_return',
            'total_amount' => '120.000',
        ]);

        // Stock must increase by 0.500 kg -> 10.500
        $item->refresh();
        $this->assertEquals('10.500', $item->current_stock);

        $stock = StoreStock::where('store_id', $this->mainStore->id)->where('item_id', $item->id)->first();
        $this->assertEquals('10.500', $stock->quantity);
    }

    public function test_purchase_return_deducts_stock_and_reduces_supplier_debt(): void
    {
        $supplier = Supplier::create([
            'name' => 'شركة الأهرام للبن',
            'company_name' => 'الأهرام',
            'current_balance' => '2000.000',
            'initial_balance' => '2000.000',
        ]);

        $item = Item::create([
            'name' => 'بن كولومبي سوبريمو',
            'code' => 'COL-01',
            'unit' => 'كجم',
            'selling_price' => '320.000',
            'cost_price' => '250.000',
            'weighted_avg_cost' => '250.000',
            'current_stock' => '0.000',
        ]);

        $this->stockService->addStock(
            item: $item,
            quantity: '20.000',
            unitCost: '250.000',
            source: $this->mainStore,
            documentNumber: 'INIT-002',
            movementType: 'initial_balance',
            notes: 'رصيد',
            storeId: $this->mainStore->id
        );

        $returnDoc = $this->returnService->createPurchaseReturn([
            'supplier_id' => $supplier->id,
            'store_id' => $this->mainStore->id,
            'reason' => 'إرجاع 5 كجم لوجود عيب في التحميص',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => '5.000',
                    'unit_price' => '250.000',
                ],
            ],
        ]);

        $this->assertDatabaseHas('returns', [
            'id' => $returnDoc->id,
            'return_type' => 'purchase_return',
            'total_amount' => '1250.000',
        ]);

        // Stock must decrease by 5.000 kg -> 15.000
        $item->refresh();
        $this->assertEquals('15.000', $item->current_stock);

        $stock = StoreStock::where('store_id', $this->mainStore->id)->where('item_id', $item->id)->first();
        $this->assertEquals('15.000', $stock->quantity);
    }
}
