<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockService;
use Tests\TenantTestCase;

class StockAdjustmentFeatureTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant);
    }

    public function test_adjust_stock_with_surplus_increases_stock_and_logs_inbound_movement(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));

            $item = $this->stockedItem('ITM-ADJ-PLUS', 'شاي أسود كيني فاخر', '20.000', '50.000', '80.000');

            // Physical count shows 25 kg (+5 kg difference)
            $movement = app(StockService::class)->adjustStock(
                item: $item,
                actualQuantity: '25.000',
                reason: 'زيادة جرد دوري معتمد'
            );

            $item->refresh();
            $this->assertEquals('25.000', $item->current_stock);
            $this->assertEquals('stock_adjustment_in', $movement->movement_type);
            $this->assertEquals('5.000', $movement->quantity);
            $this->assertEquals('20.000', $movement->stock_before);
            $this->assertEquals('25.000', $movement->stock_after);
        });
    }

    public function test_adjust_stock_with_deficit_decreases_stock_and_logs_outbound_movement(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));

            $item = $this->stockedItem('ITM-ADJ-MINUS', 'بن محوج خاص', '50.000', '150.000', '220.000');

            // Physical count shows 47.5 kg (-2.5 kg deficit)
            $movement = app(StockService::class)->adjustStock(
                item: $item,
                actualQuantity: '47.500',
                reason: 'عجز جرد وهالك تشغيل'
            );

            $item->refresh();
            $this->assertEquals('47.500', $item->current_stock);
            $this->assertEquals('stock_adjustment_out', $movement->movement_type);
            $this->assertEquals('2.500', $movement->quantity);
            $this->assertEquals('50.000', $movement->stock_before);
            $this->assertEquals('47.500', $movement->stock_after);
        });
    }

    public function test_adjust_stock_with_identical_quantity_throws_exception(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));

            $item = $this->stockedItem('ITM-ADJ-SAME', 'أكواب ورقية 8 أونص', '100.000', '1.000', '2.000');

            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('مطابق تماماً للرصيد المسجل');

            app(StockService::class)->adjustStock(
                item: $item,
                actualQuantity: '100.000',
                reason: 'تسوية وهمية'
            );
        });
    }

    public function test_a_stock_count_in_one_tenant_leaves_the_same_item_code_in_another_untouched(): void
    {
        $other = $this->createTenant();
        $storeA = (int) $this->tenantStore($this->tenant)->id;
        $storeB = (int) $this->tenantStore($other)->id;
        $make = fn (int $storeId): int => $this->stockedItem('ITM-ADJ-TWIN', 'بن توأم للجرد', '10.000', '40.000', '60.000', $storeId)->id;

        $itemA = $this->inTenant($this->tenant, fn (): int => $make($storeA));
        $itemB = $this->inTenant($other, fn (): int => $make($storeB));

        $this->inTenant($this->tenant, function () use ($itemA): void {
            $this->actingAs(User::findOrFail($this->user->id));
            app(StockService::class)->adjustStock(item: Item::findOrFail($itemA), actualQuantity: '9.750', reason: 'عجز ربع كيلو');
            $this->assertSame('9.750', (string) Item::findOrFail($itemA)->current_stock);
        });

        $this->inTenant($other, function () use ($itemB): void {
            $this->assertSame('10.000', (string) Item::findOrFail($itemB)->current_stock);
            $this->assertDatabaseCount('stock_movements', 0);
        });
    }

    /**
     * Runs inside the tenant. A real tenant always has a main store, so the stock lives in a
     * store row as well as on the item (the old fixture had no store at all and exercised a
     * store-less path that production never takes).
     */
    private function stockedItem(string $code, string $name, string $stock, string $cost, string $price, ?int $storeId = null): Item
    {
        $item = Item::create([
            'code' => $code,
            'name' => $name,
            'current_stock' => $stock,
            'cost_price' => $cost,
            'selling_price' => $price,
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $storeId ?? (int) User::findOrFail($this->user->id)->default_store_id,
            'item_id' => $item->id,
            'quantity' => $stock,
        ]);

        return $item;
    }
}
