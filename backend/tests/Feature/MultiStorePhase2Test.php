<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerPricingHelper;
use App\Services\InvoiceService;
use App\Services\StockTransferService;
use Tests\TenantTestCase;

/**
 * QA-4: the services run inside the harness tenant's own database (inTenant()), and the
 * session store switch is exercised on the tenant's host, where the tenant web routes live.
 */
class MultiStorePhase2Test extends TenantTestCase
{
    protected Tenant $tenant;

    protected int $adminId;

    protected int $mainStoreId;

    protected int $vanStoreId;

    protected int $itemId;

    protected int $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->adminId = (int) $this->tenantAdmin($this->tenant)->id;
        $this->mainStoreId = (int) $this->tenantStore($this->tenant)->id;

        [$this->vanStoreId, $this->itemId, $this->customerId] = $this->inTenant($this->tenant, function (): array {
            Store::query()->findOrFail($this->mainStoreId)->update(['name' => 'المخزن الرئيسي', 'type' => 'main_warehouse']);

            $van = Store::create([
                'name' => 'عربية توزيع رقم 1 (جملة)',
                'code' => 'VAN-01',
                'type' => 'wholesale_van',
                'is_active' => true,
                'is_main' => false,
            ]);

            User::query()->findOrFail($this->adminId)->stores()->attach([$this->mainStoreId, $van->id]);

            $item = Item::create([
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
                'store_id' => $this->mainStoreId,
                'item_id' => $item->id,
                'quantity' => '100.000',
                'min_stock' => '10.000',
                'custom_selling_price' => null,
            ]);

            // Initialize 0 kg in Van Store with custom price 360.000
            StoreStock::create([
                'store_id' => $van->id,
                'item_id' => $item->id,
                'quantity' => '0.000',
                'min_stock' => '5.000',
                'custom_selling_price' => '360.000',
            ]);

            $customer = Customer::create([
                'name' => 'مطحنة الأهرام للبن',
                'phone' => '01000007002',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            Supplier::create([
                'name' => 'شركة استيراد البن العالمية',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            return [(int) $van->id, (int) $item->id, (int) $customer->id];
        });
    }

    /** Runs $callback inside the tenant with the tenant admin authenticated. */
    private function asTenantAdmin(\Closure $callback): mixed
    {
        return $this->inTenant($this->tenant, function () use ($callback) {
            $this->actingAs(User::query()->findOrFail($this->adminId));

            return $callback(Item::query()->findOrFail($this->itemId));
        });
    }

    public function test_stock_transfer_service_moves_stock_between_stores(): void
    {
        $this->asTenantAdmin(function (Item $item): void {
            $transferService = app(StockTransferService::class);

            // Transfer 30 kg from Main Store to Van
            $transfer = $transferService->createTransfer([
                'from_store_id' => $this->mainStoreId,
                'to_store_id' => $this->vanStoreId,
                'notes' => 'شحن عهدة بضاعة للعربية',
                'items' => [
                    [
                        'item_id' => $this->itemId,
                        'quantity' => '30.000',
                    ],
                ],
            ]);

            $this->assertEquals('confirmed', $transfer->status);

            // Check main store stock decreased to 70.000
            $this->assertEquals('70.000', $item->getStockInStore($this->mainStoreId));

            // Check van store stock increased to 30.000
            $this->assertEquals('30.000', $item->getStockInStore($this->vanStoreId));

            // Global stock remains 100.000
            $item->refresh();
            $this->assertEquals('100.000', $item->current_stock);
        });
    }

    public function test_stock_transfer_cancel_reverses_stock(): void
    {
        $this->asTenantAdmin(function (Item $item): void {
            $transferService = app(StockTransferService::class);

            $transfer = $transferService->createTransfer([
                'from_store_id' => $this->mainStoreId,
                'to_store_id' => $this->vanStoreId,
                'items' => [
                    [
                        'item_id' => $this->itemId,
                        'quantity' => '25.000',
                    ],
                ],
            ]);

            $this->assertEquals('75.000', $item->getStockInStore($this->mainStoreId));
            $this->assertEquals('25.000', $item->getStockInStore($this->vanStoreId));

            // Cancel transfer
            $transfer = $transferService->cancelTransfer($transfer, 'خطأ في التحميل');

            $this->assertEquals('cancelled', $transfer->status);
            $this->assertEquals('100.000', $item->getStockInStore($this->mainStoreId));
            $this->assertEquals('0.000', $item->getStockInStore($this->vanStoreId));
        });
    }

    public function test_invoice_service_deducts_stock_from_specific_store(): void
    {
        $this->asTenantAdmin(function (Item $item): void {
            $transferService = app(StockTransferService::class);
            $invoiceService = app(InvoiceService::class);

            // 1. Transfer 40 kg to Van
            $transferService->createTransfer([
                'from_store_id' => $this->mainStoreId,
                'to_store_id' => $this->vanStoreId,
                'items' => [
                    [
                        'item_id' => $this->itemId,
                        'quantity' => '40.000',
                    ],
                ],
            ]);

            // 2. Van sells 15 kg to customer at custom price 360.000
            $invoice = $invoiceService->confirmInvoice([
                'customer_id' => $this->customerId,
                'store_id' => $this->vanStoreId,
                'payment_type' => 'cash',
                'items' => [
                    [
                        'item_id' => $this->itemId,
                        'quantity' => '15.000',
                        'unit_price' => '360.000',
                    ],
                ],
            ]);

            $this->assertEquals($this->vanStoreId, $invoice->store_id);

            // Van stock should be 40 - 15 = 25 kg
            $this->assertEquals('25.000', $item->getStockInStore($this->vanStoreId));

            // Main store stock remains 60 kg
            $this->assertEquals('60.000', $item->getStockInStore($this->mainStoreId));

            // Global stock = 85 kg
            $item->refresh();
            $this->assertEquals('85.000', $item->current_stock);
        });
    }

    public function test_customer_pricing_helper_remembers_last_sold_price(): void
    {
        $this->asTenantAdmin(function (): void {
            $invoiceService = app(InvoiceService::class);
            $pricingHelper = app(CustomerPricingHelper::class);

            // 1. Sell at negotiated price 345.000
            $invoiceService->confirmInvoice([
                'customer_id' => $this->customerId,
                'store_id' => $this->mainStoreId,
                'payment_type' => 'credit',
                'items' => [
                    [
                        'item_id' => $this->itemId,
                        'quantity' => '10.000',
                        'unit_price' => '345.000',
                    ],
                ],
            ]);

            // 2. Query last sold price for this customer and item
            $lastPrice = $pricingHelper->getLastSoldPrice($this->customerId, $this->itemId);

            $this->assertNotNull($lastPrice);
            $this->assertEquals('345.000', $lastPrice['unit_price']);
            $this->assertEquals('10.000', $lastPrice['quantity']);

            // 3. Recommended pricing breakdown
            $breakdown = $pricingHelper->getRecommendedPrice($this->customerId, $this->itemId, $this->vanStoreId);
            $this->assertEquals('360.000', $breakdown['store_custom_price']);
            $this->assertEquals('400.000', $breakdown['master_price']);
            $this->assertEquals('345.000', $breakdown['last_customer_price']['unit_price']);
        });
    }

    public function test_store_switch_route(): void
    {
        // On a tenant host the session store switch is tenant.php's `tenant.store.switch`
        // (web.php's central `store.switch` never initialises tenancy; see QA-4 report).
        $response = $this->actingAs($this->tenantAdmin($this->tenant))
            ->postJson($this->tenantUrl($this->tenant, '/tenant/store/switch'), [
                'store_id' => $this->vanStoreId,
            ]);

        $response->assertOk();
        $this->assertEquals($this->vanStoreId, session('current_store_id'));
    }

    public function test_store_switch_refuses_a_store_that_only_exists_in_another_tenant(): void
    {
        $other = $this->createTenant();
        $foreignStoreId = $this->inTenant($other, fn (): int => (int) Store::create([
            'name' => 'فرع مستأجر آخر',
            'code' => 'B-01',
            'type' => 'retail',
            'is_active' => true,
            'is_main' => false,
        ])->id);

        $this->actingAs($this->tenantAdmin($this->tenant))
            ->postJson($this->tenantUrl($this->tenant, '/tenant/store/switch'), ['store_id' => $foreignStoreId])
            ->assertForbidden();

        $this->assertNotEquals($foreignStoreId, session('current_store_id'));
    }

    public function test_stock_transfer_in_one_tenant_leaves_the_other_tenants_stock_untouched(): void
    {
        $other = $this->createTenant();
        // Tenant B holds the "same" item and stock in its own main store.
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherItemId = $this->inTenant($other, function () use ($otherStoreId): int {
            $item = Item::create([
                'code' => 'COF-001', 'name' => 'بن برازيلي فاخر', 'category' => 'بن سادة', 'unit' => 'كجم',
                'current_stock' => '100.000', 'cost_price' => '250.000', 'weighted_avg_cost' => '250.000',
                'selling_price' => '400.000', 'min_stock_level' => '10.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $otherStoreId, 'item_id' => $item->id, 'quantity' => '100.000', 'min_stock' => '10.000']);

            return (int) $item->id;
        });

        $this->asTenantAdmin(fn () => app(StockTransferService::class)->createTransfer([
            'from_store_id' => $this->mainStoreId,
            'to_store_id' => $this->vanStoreId,
            'items' => [['item_id' => $this->itemId, 'quantity' => '30.000']],
        ]));

        $this->inTenant($other, function () use ($otherItemId, $otherStoreId): void {
            $this->assertSame(0, StockTransfer::query()->count());
            $this->assertEquals('100.000', Item::query()->findOrFail($otherItemId)->getStockInStore($otherStoreId));
        });
    }
}
