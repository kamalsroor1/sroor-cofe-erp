<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\StockService;
use Exception;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TenantTestCase;

class ConcurrencyTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected int $mainStoreId;

    protected int $customerId;

    protected int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant, 'admin');

        [$this->mainStoreId, $this->customerId, $this->itemId] = $this->inTenant(
            $this->tenant,
            fn (): array => $this->seedOneKiloInStock(User::findOrFail($this->user->id), 'MAIN-02', '1.000'),
        );
    }

    public function test_overselling_beyond_stock_fails_and_maintains_data_integrity(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $invoiceService = app(InvoiceService::class);

            // First sale of 0.750 kg succeeds
            $inv1 = $invoiceService->confirmInvoice($this->salePayload($this->mainStoreId, $this->customerId, $this->itemId, '0.750'));

            $this->assertDatabaseHas('invoices', ['id' => $inv1->id, 'store_id' => $this->mainStoreId]);
            $item = Item::findOrFail($this->itemId);
            $this->assertEquals('0.250', $item->current_stock);

            // Second sale attempts to sell 0.500 kg (only 0.250 available) -> MUST THROW EXCEPTION
            try {
                $invoiceService->confirmInvoice($this->salePayload($this->mainStoreId, $this->customerId, $this->itemId, '0.500'));
                $this->fail('Selling 0.500 kg with only 0.250 kg in stock was accepted.');
            } catch (Exception $e) {
                $this->assertNotInstanceOf(AssertionFailedError::class, $e);
            }

            // Stock remains untouched at 0.250, and the failed sale left no document behind.
            $item->refresh();
            $this->assertEquals('0.250', $item->current_stock);
            $this->assertSame('0.250', (string) StoreStock::where('store_id', $this->mainStoreId)->where('item_id', $this->itemId)->value('quantity'));
            $this->assertSame(1, Invoice::query()->count());
        });
    }

    public function test_stock_of_another_tenant_never_covers_an_oversell(): void
    {
        // Tenant B holds plenty of the same item code; A must still be limited to its own 1.000 kg.
        $other = $this->createTenant();
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        [, , $foreignItemId] = $this->inTenant(
            $other,
            fn (): array => $this->seedOneKiloInStock(User::findOrFail($otherAdminId), 'MAIN-02', '50.000'),
        );

        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));

            try {
                app(InvoiceService::class)->confirmInvoice($this->salePayload($this->mainStoreId, $this->customerId, $this->itemId, '1.250'));
                $this->fail('Tenant A sold more than its own stock.');
            } catch (Exception $e) {
                $this->assertNotInstanceOf(AssertionFailedError::class, $e);
            }

            $this->assertSame('1.000', (string) Item::findOrFail($this->itemId)->current_stock);
            $this->assertSame(0, Invoice::query()->count());
        });

        $this->inTenant($other, function () use ($foreignItemId): void {
            $this->assertSame('50.000', (string) Item::findOrFail($foreignItemId)->current_stock);
            $this->assertSame(0, Invoice::query()->count());
        });
    }

    /**
     * Runs inside the tenant: a store, a customer and an item with $qty kg booked in through the stock engine.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedOneKiloInStock(User $actor, string $storeCode, string $qty): array
    {
        $this->actingAs($actor);

        $mainStore = Store::create([
            'name' => 'المخزن الرئيسي',
            'code' => $storeCode,
            'type' => 'main_store',
            'is_active' => true,
            'is_default' => true,
        ]);

        $customer = Customer::create([
            'name' => 'عميل تجريبي',
            'current_balance' => '0.000',
        ]);

        $item = Item::create([
            'name' => 'بن يمني مطري فاخر',
            'code' => 'YEM-01',
            'unit' => 'كجم',
            'selling_price' => '400.000',
            'cost_price' => '300.000',
            'weighted_avg_cost' => '300.000',
            'current_stock' => '0.000',
        ]);

        // Opening balance into the store
        app(StockService::class)->addStock(
            item: $item,
            quantity: $qty,
            unitCost: '300.000',
            source: $mainStore,
            documentNumber: 'INIT-001',
            movementType: 'initial_balance',
            notes: 'رصيد افتتاحي',
            storeId: $mainStore->id
        );

        return [$mainStore->id, $customer->id, $item->id];
    }

    /** @return array<string, mixed> */
    private function salePayload(int $storeId, int $customerId, int $itemId, string $qty): array
    {
        return [
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'items' => [
                [
                    'item_id' => $itemId,
                    'quantity' => $qty,
                    'unit_price' => '400.000',
                    'discount_amount' => '0.000',
                ],
            ],
        ];
    }
}
