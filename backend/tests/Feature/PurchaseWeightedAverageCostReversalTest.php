<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\StockService;
use Closure;
use Tests\TenantTestCase;

/**
 * BUG-03: cancelling / restoring / deleting a purchase (and returning goods to the supplier)
 * must move items.weighted_avg_cost (tenant-wide moving average, used as sales COGS) and
 * items.cost_price (last purchase landed cost) exactly, not just the stock quantity.
 *
 * Reversal rule (moving average, valuation-conserving): remove the document's qty x cost
 * from the current valuation at the current tenant-wide quantity:
 *   new WAC = (stock x WAC - qty x cost) / (stock - qty)
 * When the remaining quantity or the remaining valuation is <= 0 there is no average to
 * keep, so the WAC falls back to the last known cost (the latest other confirmed purchase).
 */
class PurchaseWeightedAverageCostReversalTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected int $mainStoreId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant);
        $this->mainStoreId = (int) $this->tenantStore($this->tenant)->id;
    }

    private function asTenantUser(Closure $callback): void
    {
        $this->inTenant($this->tenant, function () use ($callback): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $callback(app(PurchaseService::class));
        });
    }

    private function makeItem(string $code): Item
    {
        return Item::create([
            'code' => $code,
            'name' => 'بن اختبار '.$code,
            'current_stock' => '0.000',
            'cost_price' => '100.000',
            'weighted_avg_cost' => '100.000',
            'selling_price' => '150.000',
            'is_active' => true,
        ]);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create(['name' => 'مورد متوسط التكلفة', 'is_active' => true]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $lines  [quantity, cost]
     */
    private function buy(PurchaseService $service, Supplier $supplier, Item $item, array $lines, ?int $storeId = null): Purchase
    {
        return $service->createPurchase(array_filter([
            'supplier_id' => $supplier->id,
            'store_id' => $storeId,
            'paid_amount' => '0.000',
            'items' => array_map(fn (array $l): array => [
                'item_id' => $item->id,
                'quantity' => $l[0],
                'cost_price' => $l[1],
            ], $lines),
        ], fn ($v) => $v !== null));
    }

    private function assertCost(Item $item, string $stock, string $wac, string $cost): void
    {
        $item->refresh();
        $this->assertSame($stock, (string) $item->current_stock, 'current_stock');
        $this->assertSame($wac, (string) $item->weighted_avg_cost, 'weighted_avg_cost');
        $this->assertSame($cost, (string) $item->cost_price, 'cost_price');
    }

    public function test_cancel_reverses_weighted_average_cost_to_the_exact_pre_purchase_value(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-CANCEL');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            $second = $this->buy($service, $supplier, $item, [['10.000', '120.000']]);
            $this->assertCost($item, '20.000', '110.000', '120.000');

            $service->cancelPurchase($second, 'BUG-03');

            $this->assertCost($item, '10.000', '100.000', '100.000');
        });
    }

    public function test_restore_reapplies_weighted_average_cost_to_the_exact_pre_cancel_value(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-RESTORE');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            $second = $this->buy($service, $supplier, $item, [['10.000', '120.000']]);

            $service->cancelPurchase($second, 'BUG-03');
            $service->restorePurchase($second);

            $this->assertCost($item, '20.000', '110.000', '120.000');

            // A second cancel/restore round-trip stays exact (no drift accumulates).
            $service->cancelPurchase($second, 'BUG-03 again');
            $this->assertCost($item, '10.000', '100.000', '100.000');
            $service->restorePurchase($second);
            $this->assertCost($item, '20.000', '110.000', '120.000');
        });
    }

    public function test_delete_reverses_weighted_average_cost_like_cancel(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-DELETE');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            $second = $this->buy($service, $supplier, $item, [['10.000', '120.000']]);

            $service->deletePurchase($second);

            $this->assertCost($item, '10.000', '100.000', '100.000');
            $this->assertSoftDeleted('purchases', ['id' => $second->id]);
        });
    }

    public function test_cancel_and_restore_with_fractional_quantities_are_exact(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-FRACTION');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['2.750', '100.000']]);
            $second = $this->buy($service, $supplier, $item, [['0.250', '120.000']]);
            // (275 + 30) / 3 = 101.6666.. -> half-up 101.667
            $this->assertCost($item, '3.000', '101.667', '120.000');

            $service->cancelPurchase($second, 'BUG-03');
            // (3 x 101.667 - 30) / 2.75 = 100.000363.. -> 100.000
            $this->assertCost($item, '2.750', '100.000', '100.000');

            $service->restorePurchase($second);
            $this->assertCost($item, '3.000', '101.667', '120.000');
        });
    }

    public function test_cancel_after_partial_sale_removes_the_purchase_valuation_at_current_quantity(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-PARTSOLD');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            $second = $this->buy($service, $supplier, $item, [['10.000', '120.000']]);
            app(StockService::class)->deductStock($item, '5.000', $item, 'INV-WAC-1');
            $this->assertCost($item, '15.000', '110.000', '120.000');

            // Valuation 15 x 110 = 1650; minus the purchase 10 x 120 = 450 over 5 units = 90.
            $service->cancelPurchase($second, 'BUG-03');
            $this->assertCost($item, '5.000', '90.000', '100.000');

            // (5 x 90 + 10 x 120) / 15 = 110: restore lands on the exact pre-cancel value.
            $service->restorePurchase($second);
            $this->assertCost($item, '15.000', '110.000', '120.000');
        });
    }

    public function test_cancel_when_remaining_stock_is_zero_falls_back_to_the_last_known_purchase_cost(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-ZERO');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            app(StockService::class)->deductStock($item, '10.000', $item, 'INV-WAC-2');
            $second = $this->buy($service, $supplier, $item, [['10.000', '120.000']]);
            $this->assertCost($item, '10.000', '120.000', '120.000');

            $service->cancelPurchase($second, 'BUG-03');
            $this->assertCost($item, '0.000', '100.000', '100.000');

            $service->restorePurchase($second);
            $this->assertCost($item, '10.000', '120.000', '120.000');
        });
    }

    public function test_cancel_when_remaining_valuation_is_not_positive_falls_back_to_the_last_known_purchase_cost(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-NEGVAL');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            $second = $this->buy($service, $supplier, $item, [['10.000', '120.000']]);
            // A manual cost edit leaves a valuation (20 x 50 = 1000) below the purchase's 1200.
            Item::whereKey($item->id)->update(['weighted_avg_cost' => '50.000']);

            $service->cancelPurchase($second, 'BUG-03');

            $this->assertCost($item, '10.000', '100.000', '100.000');
        });
    }

    public function test_a_cancel_rejected_for_insufficient_stock_leaves_costs_and_stock_untouched(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-ROLLBACK');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            $second = $this->buy($service, $supplier, $item, [['10.000', '120.000']]);
            app(StockService::class)->deductStock($item, '15.000', $item, 'INV-WAC-3');

            try {
                $service->cancelPurchase($second, 'BUG-03');
                $this->fail('Cancelling more than the remaining stock must be rejected.');
            } catch (\Exception $e) {
                $this->assertStringContainsString('تعذر إلغاء الفاتورة', $e->getMessage());
            }

            $this->assertCost($item, '5.000', '110.000', '120.000');
            $this->assertSame('confirmed', Purchase::findOrFail($second->id)->status);
        });
    }

    public function test_weighted_average_cost_is_tenant_wide_across_stores(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $mainStoreId = $this->mainStoreId;
            $branchId = (int) Store::create([
                'name' => 'فرع متوسط التكلفة', 'code' => 'WAC-BR', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
            ])->id;
            $item = $this->makeItem('WAC-STORES');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']], $mainStoreId);
            $branchPurchase = $this->buy($service, $supplier, $item, [['30.000', '140.000']], $branchId);
            // (10 x 100 + 30 x 140) / 40 = 130 over the whole tenant, not per store.
            $this->assertCost($item, '40.000', '130.000', '140.000');

            $service->cancelPurchase($branchPurchase, 'BUG-03');
            $this->assertCost($item, '10.000', '100.000', '100.000');
            $this->assertDatabaseHas('store_stocks', ['store_id' => $branchId, 'item_id' => $item->id, 'quantity' => '0.000']);
            $this->assertDatabaseHas('store_stocks', ['store_id' => $mainStoreId, 'item_id' => $item->id, 'quantity' => '10.000']);

            $service->restorePurchase($branchPurchase);
            $this->assertCost($item, '40.000', '130.000', '140.000');
        });
    }

    public function test_cancel_of_several_lines_of_the_same_item_reverses_them_together(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-MULTILINE');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            $second = $this->buy($service, $supplier, $item, [['5.000', '120.000'], ['5.000', '140.000']]);
            // (1000 + 600) / 15 = 106.667; (15 x 106.667 + 700) / 20 = 115.00025 -> 115.000
            $this->assertCost($item, '20.000', '115.000', '140.000');

            // (20 x 115 - 600 - 700) / 10 = 100
            $service->cancelPurchase($second, 'BUG-03');
            $this->assertCost($item, '10.000', '100.000', '100.000');

            $service->restorePurchase($second);
            $this->assertCost($item, '20.000', '115.000', '140.000');
        });
    }

    public function test_cancel_and_restore_of_an_older_purchase_keep_the_newer_purchase_cost_price(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('WAC-OLDER');
            $supplier = $this->makeSupplier();

            $first = $this->buy($service, $supplier, $item, [['10.000', '100.000']]);
            $this->buy($service, $supplier, $item, [['10.000', '120.000']]);

            // (20 x 110 - 10 x 100) / 10 = 120; the newer purchase still owns cost_price.
            $service->cancelPurchase($first, 'BUG-03');
            $this->assertCost($item, '10.000', '120.000', '120.000');

            $service->restorePurchase($first);
            $this->assertCost($item, '20.000', '110.000', '120.000');
        });
    }

    public function test_purchase_return_at_purchase_cost_removes_its_valuation_from_the_average(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $storeId = $this->mainStoreId;
            $item = $this->makeItem('WAC-PRET');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']], $storeId);
            $second = $this->buy($service, $supplier, $item, [['10.000', '120.000']], $storeId);

            app(ReturnService::class)->createReturn([
                'return_type' => 'purchase_return',
                'supplier_id' => $supplier->id,
                'purchase_id' => $second->id,
                'store_id' => $storeId,
                'items' => [['item_id' => $item->id, 'quantity' => '10.000', 'unit_price' => '120.000']],
            ]);

            // (20 x 110 - 10 x 120) / 10 = 100; cost_price is the purchase price, not touched by a return.
            $this->assertCost($item, '10.000', '100.000', '120.000');
        });
    }

    public function test_purchase_return_without_store_id_uses_the_purchase_store(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $storeId = $this->mainStoreId;
            $item = $this->makeItem('WAC-PRET-NOSTORE');
            $supplier = $this->makeSupplier();

            $purchase = $this->buy($service, $supplier, $item, [['10.000', '100.000']], $storeId);

            $return = app(ReturnService::class)->createReturn([
                'return_type' => 'purchase_return',
                'supplier_id' => $supplier->id,
                'purchase_id' => $purchase->id,
                'items' => [['item_id' => $item->id, 'quantity' => '2.000', 'unit_price' => '100.000']],
            ]);

            $this->assertSame($storeId, (int) $return->store_id);
        });
    }

    public function test_purchase_return_at_the_average_cost_keeps_the_average(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $storeId = $this->mainStoreId;
            $item = $this->makeItem('WAC-PRET-AVG');
            $supplier = $this->makeSupplier();

            $this->buy($service, $supplier, $item, [['10.000', '100.000']], $storeId);
            $this->buy($service, $supplier, $item, [['10.000', '120.000']], $storeId);

            app(ReturnService::class)->createReturn([
                'return_type' => 'purchase_return',
                'supplier_id' => $supplier->id,
                'store_id' => $storeId,
                'items' => [['item_id' => $item->id, 'quantity' => '5.000', 'unit_price' => '110.000']],
            ]);

            $this->assertCost($item, '15.000', '110.000', '120.000');
        });
    }
}
