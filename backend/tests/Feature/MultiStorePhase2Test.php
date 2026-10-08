<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\CustomerPricingHelper;
use App\Services\InvoiceService;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiStorePhase2Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Store $mainStore;

    protected Store $vanStore;

    protected Item $item;

    protected Customer $customer;

    protected Supplier $supplier;

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
            'name' => 'عربية توزيع رقم 1 (جملة)',
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
            'name' => 'بن برازيلي فاخر',
            'category' => 'بن سادة',
            'unit' => 'كجم',
            'current_stock' => '100.000',
            'cost_price' => '250.000',
            'weighted_avg_cost' => '250.000',
            'selling_price' => '400.000',
            'min_stock_level' => '10.000',
            'is_active' => true,
        ]);

        // Initialize 100 kg in Main Store
        StoreStock::create([
            'store_id' => $this->mainStore->id,
            'item_id' => $this->item->id,
            'quantity' => '100.000',
            'min_stock' => '10.000',
            'custom_selling_price' => null,
        ]);

        // Initialize 0 kg in Van Store with custom price 360.000
        StoreStock::create([
            'store_id' => $this->vanStore->id,
            'item_id' => $this->item->id,
            'quantity' => '0.000',
            'min_stock' => '5.000',
            'custom_selling_price' => '360.000',
        ]);

        $this->customer = Customer::create([
            'name' => 'مطحنة الأهرام للبن',
            'phone' => '01000007002',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'شركة استيراد البن العالمية',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);
    }

    public function test_stock_transfer_service_moves_stock_between_stores()
    {
        $this->actingAs($this->admin);
        $transferService = app(StockTransferService::class);

        // Transfer 30 kg from Main Store to Van
        $transfer = $transferService->createTransfer([
            'from_store_id' => $this->mainStore->id,
            'to_store_id' => $this->vanStore->id,
            'notes' => 'شحن عهدة بضاعة للعربية',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => '30.000',
                ],
            ],
        ]);

        $this->assertEquals('confirmed', $transfer->status);

        // Check main store stock decreased to 70.000
        $this->assertEquals('70.000', $this->item->getStockInStore($this->mainStore->id));

        // Check van store stock increased to 30.000
        $this->assertEquals('30.000', $this->item->getStockInStore($this->vanStore->id));

        // Global stock remains 100.000
        $this->item->refresh();
        $this->assertEquals('100.000', $this->item->current_stock);
    }

    public function test_stock_transfer_cancel_reverses_stock()
    {
        $this->actingAs($this->admin);
        $transferService = app(StockTransferService::class);

        $transfer = $transferService->createTransfer([
            'from_store_id' => $this->mainStore->id,
            'to_store_id' => $this->vanStore->id,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => '25.000',
                ],
            ],
        ]);

        $this->assertEquals('75.000', $this->item->getStockInStore($this->mainStore->id));
        $this->assertEquals('25.000', $this->item->getStockInStore($this->vanStore->id));

        // Cancel transfer
        $transfer = $transferService->cancelTransfer($transfer, 'خطأ في التحميل');

        $this->assertEquals('cancelled', $transfer->status);
        $this->assertEquals('100.000', $this->item->getStockInStore($this->mainStore->id));
        $this->assertEquals('0.000', $this->item->getStockInStore($this->vanStore->id));
    }

    public function test_invoice_service_deducts_stock_from_specific_store()
    {
        $this->actingAs($this->admin);
        $transferService = app(StockTransferService::class);
        $invoiceService = app(InvoiceService::class);

        // 1. Transfer 40 kg to Van
        $transferService->createTransfer([
            'from_store_id' => $this->mainStore->id,
            'to_store_id' => $this->vanStore->id,
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => '40.000',
                ],
            ],
        ]);

        // 2. Van sells 15 kg to customer at custom price 360.000
        $invoice = $invoiceService->confirmInvoice([
            'customer_id' => $this->customer->id,
            'store_id' => $this->vanStore->id,
            'payment_type' => 'cash',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => '15.000',
                    'unit_price' => '360.000',
                ],
            ],
        ]);

        $this->assertEquals($this->vanStore->id, $invoice->store_id);

        // Van stock should be 40 - 15 = 25 kg
        $this->assertEquals('25.000', $this->item->getStockInStore($this->vanStore->id));

        // Main store stock remains 60 kg
        $this->assertEquals('60.000', $this->item->getStockInStore($this->mainStore->id));

        // Global stock = 85 kg
        $this->item->refresh();
        $this->assertEquals('85.000', $this->item->current_stock);
    }

    public function test_customer_pricing_helper_remembers_last_sold_price()
    {
        $this->actingAs($this->admin);
        $invoiceService = app(InvoiceService::class);
        $pricingHelper = app(CustomerPricingHelper::class);

        // 1. Sell at negotiated price 345.000
        $invoiceService->confirmInvoice([
            'customer_id' => $this->customer->id,
            'store_id' => $this->mainStore->id,
            'payment_type' => 'credit',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => '10.000',
                    'unit_price' => '345.000',
                ],
            ],
        ]);

        // 2. Query last sold price for this customer and item
        $lastPrice = $pricingHelper->getLastSoldPrice($this->customer->id, $this->item->id);

        $this->assertNotNull($lastPrice);
        $this->assertEquals('345.000', $lastPrice['unit_price']);
        $this->assertEquals('10.000', $lastPrice['quantity']);

        // 3. Recommended pricing breakdown
        $breakdown = $pricingHelper->getRecommendedPrice($this->customer->id, $this->item->id, $this->vanStore->id);
        $this->assertEquals('360.000', $breakdown['store_custom_price']);
        $this->assertEquals('400.000', $breakdown['master_price']);
        $this->assertEquals('345.000', $breakdown['last_customer_price']['unit_price']);
    }

    public function test_store_switch_route()
    {
        $this->actingAs($this->admin);

        $response = $this->postJson(route('store.switch'), [
            'store_id' => $this->vanStore->id,
        ]);

        $response->assertOk();
        $this->assertEquals($this->vanStore->id, session('current_store_id'));
    }
}
