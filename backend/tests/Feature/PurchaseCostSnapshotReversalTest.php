<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\StockService;
use App\Services\StockTransferService;
use Closure;
use Exception;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * BUG-03 follow-up: purchase_items.wac_before / cost_price_before snapshot the item's costs
 * right before a purchase line is applied. weighted_avg_cost is stored at scale 3, so the
 * moving-average reversal (S x W - q x c) / (S - q) drifts by 0.001 when the average does
 * not terminate. While the purchase is still the item's last cost-affecting movement, a
 * cancel / delete restores the snapshot exactly; otherwise (later sale, purchase, hand edit,
 * legacy row without a snapshot) it keeps the formula.
 */
class PurchaseCostSnapshotReversalTest extends TenantTestCase
{
    private const MIGRATION = 'database/migrations/tenant/2026_10_11_120000_add_wac_before_to_purchase_items_table.php';

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

    private function makeItem(string $code, string $cost = '100.000'): Item
    {
        return Item::create([
            'code' => $code,
            'name' => 'بن لقطة التكلفة '.$code,
            'current_stock' => '0.000',
            'cost_price' => $cost,
            'weighted_avg_cost' => $cost,
            'selling_price' => '150.000',
            'is_active' => true,
        ]);
    }

    private function makeSupplier(): Supplier
    {
        return Supplier::create(['name' => 'مورد لقطة التكلفة', 'is_active' => true]);
    }

    /**
     * @param  list<array{0: string, 1: string}>  $lines  [quantity, cost]
     */
    private function buy(PurchaseService $service, Supplier $supplier, Item $item, array $lines): Purchase
    {
        return $service->createPurchase([
            'supplier_id' => $supplier->id,
            'store_id' => $this->mainStoreId,
            'paid_amount' => '0.000',
            'items' => array_map(fn (array $l): array => [
                'item_id' => $item->id,
                'quantity' => $l[0],
                'cost_price' => $l[1],
            ], $lines),
        ]);
    }

    private function assertCost(Item $item, string $stock, string $wac, string $cost): void
    {
        $item->refresh();
        $this->assertSame($stock, (string) $item->current_stock, 'current_stock');
        $this->assertSame($wac, (string) $item->weighted_avg_cost, 'weighted_avg_cost');
        $this->assertSame($cost, (string) $item->cost_price, 'cost_price');
    }

    private function firstLine(Purchase $purchase): PurchaseItem
    {
        return PurchaseItem::where('purchase_id', $purchase->id)->orderBy('id')->firstOrFail();
    }

    /**
     * 3 @ 10 then 7 @ 11.111: (30 + 77.777) / 10 = 10.7777 -> 10.778. The formula gives
     * (10 x 10.778 - 77.777) / 3 = 10.001 on cancel; the snapshot gives back 10.000.
     */
    private function buyDriftPair(PurchaseService $service, Supplier $supplier, Item $item): Purchase
    {
        $this->buy($service, $supplier, $item, [['3.000', '10.000']]);
        $second = $this->buy($service, $supplier, $item, [['7.000', '11.111']]);
        $this->assertCost($item, '10.000', '10.778', '11.111');

        return $second;
    }

    public function test_create_records_the_costs_before_each_line(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-RECORD');
            $supplier = $this->makeSupplier();

            $first = $this->buy($service, $supplier, $item, [['3.000', '10.000']]);
            $second = $this->buy($service, $supplier, $item, [['7.000', '11.111']]);

            $this->assertSame('100.000', (string) $this->firstLine($first)->wac_before);
            $this->assertSame('100.000', (string) $this->firstLine($first)->cost_price_before);
            $this->assertSame('10.000', (string) $this->firstLine($second)->wac_before);
            $this->assertSame('10.000', (string) $this->firstLine($second)->cost_price_before);
        });
    }

    public function test_cancel_restores_a_non_terminating_average_exactly(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-DRIFT');
            $second = $this->buyDriftPair($service, $this->makeSupplier(), $item);

            $service->cancelPurchase($second, 'BUG-03 snapshot');

            $this->assertCost($item, '3.000', '10.000', '10.000');
        });
    }

    public function test_delete_restores_a_non_terminating_average_exactly(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-DELETE');
            $second = $this->buyDriftPair($service, $this->makeSupplier(), $item);

            $service->deletePurchase($second);

            $this->assertCost($item, '3.000', '10.000', '10.000');
            $this->assertSoftDeleted('purchases', ['id' => $second->id]);
        });
    }

    public function test_restore_after_cancel_and_a_second_round_trip_stay_exact(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-RESTORE');
            $second = $this->buyDriftPair($service, $this->makeSupplier(), $item);

            $service->cancelPurchase($second, 'BUG-03 snapshot');
            $this->assertCost($item, '3.000', '10.000', '10.000');

            $service->restorePurchase($second);
            $this->assertCost($item, '10.000', '10.778', '11.111');
            $this->assertSame('10.000', (string) $this->firstLine($second)->wac_before);
            $this->assertSame('10.000', (string) $this->firstLine($second)->cost_price_before);

            $service->cancelPurchase($second, 'BUG-03 snapshot again');
            $this->assertCost($item, '3.000', '10.000', '10.000');

            $service->restorePurchase($second);
            $this->assertCost($item, '10.000', '10.778', '11.111');
        });
    }

    public function test_legacy_line_without_a_snapshot_keeps_the_formula_path(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-LEGACY');
            $second = $this->buyDriftPair($service, $this->makeSupplier(), $item);
            PurchaseItem::where('purchase_id', $second->id)->update(['wac_before' => null, 'cost_price_before' => null]);

            $service->cancelPurchase($second, 'BUG-03 legacy');

            // (10 x 10.778 - 7 x 11.111) / 3 = 30.003 / 3 = 10.001; cost_price -> previous purchase.
            $this->assertCost($item, '3.000', '10.001', '10.000');

            // Restoring records a snapshot from then on.
            $service->restorePurchase($second);
            $this->assertSame('10.001', (string) $this->firstLine($second)->wac_before);
        });
    }

    public function test_a_later_sale_keeps_the_formula_path(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-SALE');
            $second = $this->buyDriftPair($service, $this->makeSupplier(), $item);
            app(StockService::class)->deductStock($item, '1.000', $item, 'INV-SNAP-1', storeId: $this->mainStoreId);

            $service->cancelPurchase($second, 'BUG-03 after sale');

            // (9 x 10.778 - 77.777) / 2 = 19.225 / 2 = 9.6125 -> half-up 9.613 (value-conserving).
            $this->assertCost($item, '2.000', '9.613', '10.000');
        });
    }

    public function test_a_later_purchase_keeps_the_formula_path_for_the_earlier_one(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-LATERPUR');
            $supplier = $this->makeSupplier();
            $second = $this->buyDriftPair($service, $supplier, $item);
            $this->buy($service, $supplier, $item, [['10.000', '12.000']]);
            // (10 x 10.778 + 120) / 20 = 11.389
            $this->assertCost($item, '20.000', '11.389', '12.000');

            $service->cancelPurchase($second, 'BUG-03 not last');

            // (20 x 11.389 - 77.777) / 13 = 150.003 / 13 = 11.5386.. -> 11.539; the newer
            // purchase still owns cost_price.
            $this->assertCost($item, '13.000', '11.539', '12.000');
        });
    }

    public function test_a_hand_edited_average_keeps_the_formula_path(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-HANDEDIT');
            $second = $this->buyDriftPair($service, $this->makeSupplier(), $item);
            Item::whereKey($item->id)->update(['weighted_avg_cost' => '12.000']);

            $service->cancelPurchase($second, 'BUG-03 hand edit');

            // (10 x 12 - 77.777) / 3 = 42.223 / 3 = 14.0743.. -> 14.074
            $this->assertCost($item, '3.000', '14.074', '10.000');
        });
    }

    public function test_a_store_transfer_after_the_purchase_does_not_block_the_exact_path(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $branchId = (int) Store::create([
                'name' => 'فرع لقطة التكلفة', 'code' => 'SNAP-BR', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
            ])->id;
            $item = $this->makeItem('SNAP-TRANSFER');
            $second = $this->buyDriftPair($service, $this->makeSupplier(), $item);

            app(StockTransferService::class)->createTransfer([
                'from_store_id' => $this->mainStoreId,
                'to_store_id' => $branchId,
                'items' => [['item_id' => $item->id, 'quantity' => '2.000']],
            ]);

            $service->cancelPurchase($second, 'BUG-03 after transfer');

            $this->assertCost($item, '3.000', '10.000', '10.000');
            $this->assertSame('1.000', (string) StoreStock::where('store_id', $this->mainStoreId)->where('item_id', $item->id)->value('quantity'));
            $this->assertSame('2.000', (string) StoreStock::where('store_id', $branchId)->where('item_id', $item->id)->value('quantity'));
        });
    }

    public function test_several_lines_of_the_same_item_go_back_to_the_first_line_snapshot(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-MULTI');
            $supplier = $this->makeSupplier();
            $this->buy($service, $supplier, $item, [['3.000', '10.000']]);
            $second = $this->buy($service, $supplier, $item, [['2.000', '11.111'], ['5.000', '12.345']]);
            // (30 + 22.222) / 5 = 10.4444 -> 10.444; (5 x 10.444 + 61.725) / 10 = 11.3945 -> 11.395
            $this->assertCost($item, '10.000', '11.395', '12.345');
            $lines = PurchaseItem::where('purchase_id', $second->id)->orderBy('id')->get();
            $this->assertSame('10.000', (string) $lines[0]->wac_before);
            $this->assertSame('10.444', (string) $lines[1]->wac_before);

            $service->cancelPurchase($second, 'BUG-03 multi-line');

            $this->assertCost($item, '3.000', '10.000', '10.000');

            $service->restorePurchase($second);
            $this->assertCost($item, '10.000', '11.395', '12.345');
        });
    }

    public function test_cancel_without_an_earlier_purchase_restores_the_opening_cost(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-OPENING');
            $only = $this->buy($service, $this->makeSupplier(), $item, [['10.000', '120.000']]);
            $this->assertCost($item, '10.000', '120.000', '120.000');

            $service->cancelPurchase($only, 'BUG-03 opening cost');

            // No stock left and no other purchase: the formula path would keep 120 / 120.
            $this->assertCost($item, '0.000', '100.000', '100.000');
        });
    }

    public function test_fractional_quantities_restore_exactly(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-FRACTION');
            $supplier = $this->makeSupplier();
            $this->buy($service, $supplier, $item, [['0.750', '10.000']]);
            $second = $this->buy($service, $supplier, $item, [['0.250', '11.111']]);
            // (7.5 + 2.778) / 1 = 10.278
            $this->assertCost($item, '1.000', '10.278', '11.111');

            $service->cancelPurchase($second, 'BUG-03 fraction');

            $this->assertCost($item, '0.750', '10.000', '10.000');
        });
    }

    public function test_a_cancel_rejected_for_insufficient_stock_leaves_costs_and_snapshot_untouched(): void
    {
        $this->asTenantUser(function (PurchaseService $service): void {
            $item = $this->makeItem('SNAP-ROLLBACK');
            $second = $this->buyDriftPair($service, $this->makeSupplier(), $item);
            app(StockService::class)->deductStock($item, '8.000', $item, 'INV-SNAP-2', storeId: $this->mainStoreId);

            try {
                $service->cancelPurchase($second, 'BUG-03 rollback');
                $this->fail('Cancelling more than the remaining stock must be rejected.');
            } catch (Exception $e) {
                $this->assertStringContainsString('تعذر إلغاء الفاتورة', $e->getMessage());
            }

            $this->assertCost($item, '2.000', '10.778', '11.111');
            $this->assertSame('confirmed', Purchase::findOrFail($second->id)->status);
            $this->assertSame('10.000', (string) $this->firstLine($second)->wac_before);
            $this->assertSame('2.000', (string) StoreStock::where('store_id', $this->mainStoreId)->where('item_id', $item->id)->value('quantity'));
        });
    }

    public function test_migration_adds_nullable_columns_and_is_reversible_and_idempotent(): void
    {
        // TenantTestCase keeps tenant tables out of the central DB: run against the tenant DB.
        $this->inTenant($this->tenant, function (): void {
            $this->assertTrue(Schema::hasColumn('purchase_items', 'wac_before'));
            $this->assertTrue(Schema::hasColumn('purchase_items', 'cost_price_before'));

            $migration = require base_path(self::MIGRATION);
            $migration->up(); // guarded by hasColumn: must not throw
            $this->assertTrue(Schema::hasColumn('purchase_items', 'wac_before'));

            $migration->down();
            $this->assertFalse(Schema::hasColumn('purchase_items', 'wac_before'), 'down() must drop wac_before');
            $this->assertFalse(Schema::hasColumn('purchase_items', 'cost_price_before'), 'down() must drop cost_price_before');
            $migration->down(); // guarded: a second down must not throw

            $migration->up();
            $this->assertTrue(Schema::hasColumn('purchase_items', 'wac_before'));
            $this->assertTrue(Schema::hasColumn('purchase_items', 'cost_price_before'));
        });
    }
}
