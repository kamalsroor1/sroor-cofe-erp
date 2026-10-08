<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiStorePhase1Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Store $mainStore;

    protected Store $vanStore;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);

        $this->mainStore = Store::create([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-01',
            'type' => 'main_warehouse',
            'is_active' => true,
            'is_main' => true,
        ]);

        $this->vanStore = Store::create([
            'name' => 'عربية توزيع رقم 1',
            'code' => 'VAN-01',
            'type' => 'wholesale_van',
            'is_active' => true,
            'is_main' => false,
        ]);

        $this->admin = User::create([
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'email' => 'admin@sroor.com',
            'password' => bcrypt('password'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->admin->assignRole('admin');
        $this->admin->stores()->attach([$this->mainStore->id, $this->vanStore->id]);

        $this->item = Item::create([
            'code' => 'COF-001',
            'name' => 'بن كولومبي فاخر',
            'category' => 'بن سادة',
            'unit' => 'كجم',
            'current_stock' => '100.000',
            'cost_price' => '250.000',
            'weighted_avg_cost' => '250.000',
            'selling_price' => '400.000',
            'min_stock_level' => '10.000',
            'is_active' => true,
        ]);
    }

    public function test_store_creation_and_scopes()
    {
        $this->assertEquals('MAIN-01', $this->mainStore->code);
        $this->assertTrue($this->mainStore->is_main);
        $this->assertEquals($this->mainStore->id, Store::getMainStore()->id);

        $this->assertCount(1, Store::vans()->get());
        $this->assertEquals('VAN-01', Store::vans()->first()->code);
    }

    public function test_store_stock_custom_price_fallback()
    {
        // 1. Stock in main store with NO custom price (should fallback to 400.000)
        $mainStock = StoreStock::create([
            'store_id' => $this->mainStore->id,
            'item_id' => $this->item->id,
            'quantity' => '80.000',
            'min_stock' => '10.000',
            'custom_selling_price' => null,
        ]);

        $this->assertEquals('400.000', $mainStock->effective_selling_price);
        $this->assertEquals('400.000', $this->item->getEffectivePriceForStore($this->mainStore->id));
        $this->assertEquals('80.000', $this->item->getStockInStore($this->mainStore->id));

        // 2. Stock in van store with CUSTOM wholesale price 360.000
        $vanStock = StoreStock::create([
            'store_id' => $this->vanStore->id,
            'item_id' => $this->item->id,
            'quantity' => '20.000',
            'min_stock' => '5.000',
            'custom_selling_price' => '360.000',
        ]);

        $this->item->refresh();
        $this->assertEquals('360.000', $vanStock->effective_selling_price);
        $this->assertEquals('360.000', $this->item->getEffectivePriceForStore($this->vanStore->id));
        $this->assertEquals('20.000', $this->item->getStockInStore($this->vanStore->id));
    }

    public function test_user_store_relationships_and_current_store()
    {
        $this->assertCount(2, $this->admin->stores);
        $this->assertEquals($this->mainStore->id, $this->admin->defaultStore->id);
        $this->assertEquals($this->mainStore->id, $this->admin->getCurrentStore()->id);

        // Simulate session switch to van
        session(['current_store_id' => $this->vanStore->id]);
        $this->assertEquals($this->vanStore->id, $this->admin->getCurrentStore()->id);
    }

    public function test_stock_transfer_models_creation()
    {
        $transfer = StockTransfer::create([
            'transfer_number' => 'TRF-20260811-0001',
            'from_store_id' => $this->mainStore->id,
            'to_store_id' => $this->vanStore->id,
            'user_id' => $this->admin->id,
            'transfer_date' => now()->toDateString(),
            'status' => 'pending',
            'notes' => 'شحن عهدة بضاعة لعربية التوزيع',
        ]);

        $item = StockTransferItem::create([
            'stock_transfer_id' => $transfer->id,
            'item_id' => $this->item->id,
            'quantity' => '25.000',
        ]);

        $this->assertEquals('TRF-20260811-0001', $transfer->transfer_number);
        $this->assertEquals($this->mainStore->id, $transfer->fromStore->id);
        $this->assertEquals($this->vanStore->id, $transfer->toStore->id);
        $this->assertCount(1, $transfer->items);
        $this->assertEquals('25.000', $transfer->items->first()->quantity);
    }
}
