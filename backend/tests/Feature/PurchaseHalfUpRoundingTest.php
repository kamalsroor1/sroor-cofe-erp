<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseItem;
use App\Models\ReturnItem;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Support\Money\Decimal;
use Tests\TenantTestCase;

/**
 * CTO decision (W2 batch 2): money is rounded HALF-UP everywhere (App\Support\Money\Decimal),
 * purchases and purchase returns included, like sales and sales returns. Each case below is one
 * where the old truncation (bcmul/bcdiv at scale 3) gave a different, lower value.
 * Only new documents are affected: stored totals are never recomputed.
 */
final class PurchaseHalfUpRoundingTest extends TenantTestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant);
    }

    private function item(string $code, string $cost = '10.000'): Item
    {
        return Item::query()->create([
            'code' => $code,
            'name' => 'صنف '.$code,
            'unit' => 'كجم',
            'current_stock' => '0.000',
            'cost_price' => $cost,
            'weighted_avg_cost' => $cost,
            'selling_price' => '20.000',
            'is_active' => true,
        ]);
    }

    public function test_purchase_line_total_is_half_up(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::query()->findOrFail($this->user->id));
            $item = $this->item('HU-LINE');
            $supplier = Supplier::query()->create(['name' => 'مورد التقريب', 'is_active' => true]);

            // 0.255 x 12.345 = 3.147975 -> 3.148 (truncation: 3.147).
            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id,
                'items' => [['item_id' => $item->id, 'quantity' => '0.255', 'cost_price' => '12.345']],
            ]);

            $line = PurchaseItem::query()->where('purchase_id', $purchase->id)->sole();
            $this->assertSame('3.148', (string) $line->total_price);
            $this->assertSame('3.148', (string) $purchase->refresh()->subtotal);
        });
    }

    public function test_equal_expense_allocation_and_landed_unit_cost_are_half_up(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::query()->findOrFail($this->user->id));
            $supplier = Supplier::query()->create(['name' => 'مورد المصاريف', 'is_active' => true]);
            $items = [$this->item('HU-A'), $this->item('HU-B'), $this->item('HU-C')];

            // 2.000 split over 3 lines = 0.6666… -> 0.667 each (truncation: 0.666).
            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id,
                'additional_expenses' => [['description' => 'نقل', 'amount' => '2.000', 'allocation_method' => 'equal']],
                'items' => array_map(fn (Item $i): array => ['item_id' => $i->id, 'quantity' => '1.000', 'cost_price' => '10.000'], $items),
            ]);

            foreach (PurchaseItem::query()->where('purchase_id', $purchase->id)->get() as $line) {
                $this->assertSame('0.667', (string) $line->allocated_expense);
                $this->assertSame('10.667', (string) $line->cost_price);
            }
        });
    }

    public function test_by_quantity_allocation_is_computed_exactly_then_half_up(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::query()->findOrFail($this->user->id));
            $supplier = Supplier::query()->create(['name' => 'مورد الكميات', 'is_active' => true]);
            $a = $this->item('HU-Q1');
            $b = $this->item('HU-Q2');

            // 10.000 x 1 / 3 = 3.3333… -> 3.333; 10.000 x 2 / 3 = 6.6666… -> 6.667
            // (the old ratio at scale 6, then truncation, gave 6.666).
            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id,
                'additional_expenses' => [['description' => 'جمارك', 'amount' => '10.000', 'allocation_method' => 'by_quantity']],
                'items' => [
                    ['item_id' => $a->id, 'quantity' => '1.000', 'cost_price' => '10.000'],
                    ['item_id' => $b->id, 'quantity' => '2.000', 'cost_price' => '10.000'],
                ],
            ]);

            $lines = PurchaseItem::query()->where('purchase_id', $purchase->id)->orderBy('id')->get();
            $this->assertSame('3.333', (string) $lines[0]->allocated_expense);
            $this->assertSame('6.667', (string) $lines[1]->allocated_expense);
            // Landed unit cost of line 2: 10.000 + 6.667 / 2 = 13.3335 -> 13.334.
            $this->assertSame('13.334', (string) $lines[1]->cost_price);
        });
    }

    public function test_weighted_average_cost_is_half_up(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $service = app(PurchaseService::class);

            // (1 x 10.000 + 2 x 10.001) / 3 = 10.000666… -> 10.001 (truncation: 10.000).
            $this->assertSame('10.001', $service->calculateWeightedAverageCost('1.000', '10.000', '2.000', '10.001'));
            // Valuations are half-up too: 0.255 x 12.345 = 3.148.
            $this->assertSame(Decimal::mul('0.255', '12.345'), $service->calculateWeightedAverageCost('0.000', '0.000', '1.000', '3.148'));
        });
    }

    public function test_purchase_return_line_total_is_half_up(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::query()->findOrFail($this->user->id));
            $storeId = (int) Store::query()->where('is_main', true)->value('id');
            $item = $this->item('HU-RET');
            $item->forceFill(['current_stock' => '5.000'])->save();
            StoreStock::query()->create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => '5.000']);
            $supplier = Supplier::query()->create(['name' => 'مورد المرتجع', 'is_active' => true]);

            $return = app(ReturnService::class)->createPurchaseReturn([
                'supplier_id' => $supplier->id,
                'store_id' => $storeId,
                'items' => [['item_id' => $item->id, 'quantity' => '0.255', 'unit_price' => '12.345']],
            ]);

            $this->assertSame('3.148', (string) ReturnItem::query()->where('return_id', $return->id)->value('total_price'));
            $this->assertSame('3.148', (string) $return->refresh()->total_amount);
            $this->assertSame('4.745', (string) StoreStock::query()->where('store_id', $storeId)->where('item_id', $item->id)->value('quantity'));
        });
    }
}
