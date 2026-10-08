<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StockService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $mainStore;

    protected Customer $customer;

    protected Item $item;

    protected StockService $stockService;

    protected InvoiceService $invoiceService;

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

        $this->customer = Customer::create([
            'name' => 'عميل تجريبي',
            'current_balance' => '0.000',
        ]);

        $this->item = Item::create([
            'name' => 'بن يمني مطري فاخر',
            'code' => 'YEM-01',
            'unit' => 'كجم',
            'selling_price' => '400.000',
            'cost_price' => '300.000',
            'weighted_avg_cost' => '300.000',
            'current_stock' => '0.000',
        ]);

        $this->stockService = app(StockService::class);
        $this->invoiceService = app(InvoiceService::class);

        // Add 1.000 kg to main store
        $this->stockService->addStock(
            item: $this->item,
            quantity: '1.000',
            unitCost: '300.000',
            source: $this->mainStore,
            documentNumber: 'INIT-001',
            movementType: 'initial_balance',
            notes: 'رصيد افتتاحي',
            storeId: $this->mainStore->id
        );
    }

    public function test_overselling_beyond_stock_fails_and_maintains_data_integrity(): void
    {
        // First sale of 0.750 kg succeeds
        $inv1 = $this->invoiceService->confirmInvoice([
            'customer_id' => $this->customer->id,
            'store_id' => $this->mainStore->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => '0.750',
                    'unit_price' => '400.000',
                    'discount_amount' => '0.000',
                ],
            ],
        ]);

        $this->assertNotNull($inv1);
        $this->item->refresh();
        $this->assertEquals('0.250', $this->item->current_stock);

        // Second sale attempts to sell 0.500 kg (only 0.250 available) -> MUST THROW EXCEPTION
        $this->expectException(Exception::class);

        $this->invoiceService->confirmInvoice([
            'customer_id' => $this->customer->id,
            'store_id' => $this->mainStore->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => '0.500',
                    'unit_price' => '400.000',
                    'discount_amount' => '0.000',
                ],
            ],
        ]);

        // Stock remains untouched at 0.250
        $this->item->refresh();
        $this->assertEquals('0.250', $this->item->current_stock);
    }
}
