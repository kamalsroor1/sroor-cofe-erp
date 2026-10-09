<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Stores\GetStoreStocksAction;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StoreStock;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * SETG-10, lane 2H: Item::lowStock(), Item::isLowStock() and the store-stock "low" filter
 * use the same rule as TenantSettings::whereLowStock(): the item's own minimum when it has
 * one (> 0), else the tenant low_stock_default_threshold (default 5.000).
 */
final class LowStockDefaultThresholdScopeTest extends TenantTestCase
{
    public function test_item_scope_and_row_check_use_own_minimum_else_tenant_default(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $items = $this->items($tenant);

            $low = Item::query()->lowStock()->pluck('code')->sort()->values()->all();

            $this->assertSame(['LS-NOMIN-LOW', 'LS-OWN-LOW'], $low);
            foreach ($items as $code => $item) {
                $this->assertSame(in_array($code, $low, true), $item->isLowStock(), $code);
            }

            // Raising the tenant default pulls the no-minimum item at 7.000 in.
            Setting::set('low_stock_default_threshold', '8.000');

            $this->assertSame(
                ['LS-NOMIN-HIGH', 'LS-NOMIN-LOW', 'LS-OWN-LOW'],
                Item::query()->lowStock()->pluck('code')->sort()->values()->all(),
            );
            $this->assertTrue($items['LS-NOMIN-HIGH']->isLowStock());
        });
    }

    public function test_store_stock_low_filter_uses_the_same_rule(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $this->items($tenant);
            $storeId = (int) $this->tenantStore($tenant)->getKey();

            $codes = collect(app(GetStoreStocksAction::class)->execute($storeId, '', 'low')->items())
                ->map(fn (StoreStock $row) => $row->item?->code)
                ->sort()
                ->values()
                ->all();

            $this->assertSame(['LS-NOMIN-LOW', 'LS-OWN-LOW'], $codes);
        });
    }

    /**
     * Own minimum 10: 8 is low, 12 is not. No minimum (0): 3 is low (<= 5 default), 7 is
     * not. Inactive items never show in the item scope.
     *
     * @return array<string, Item>
     */
    private function items(Tenant $tenant): array
    {
        $storeId = (int) $this->tenantStore($tenant)->getKey();
        $rows = [
            'LS-OWN-LOW' => ['10.000', '8.000', true],
            'LS-OWN-OK' => ['10.000', '12.000', true],
            'LS-NOMIN-LOW' => ['0.000', '3.000', true],
            'LS-NOMIN-HIGH' => ['0.000', '7.000', true],
        ];

        $items = [];
        foreach ($rows as $code => [$min, $qty, $active]) {
            $items[$code] = Item::create([
                'name' => $code,
                'code' => $code,
                'unit' => 'كجم',
                'cost_price' => '1.000',
                'selling_price' => '2.000',
                'current_stock' => $qty,
                'min_stock_level' => $min,
                'is_active' => $active,
            ]);
            StoreStock::create(['store_id' => $storeId, 'item_id' => $items[$code]->id, 'quantity' => $qty]);
        }

        Item::create([
            'name' => 'LS-INACTIVE',
            'code' => 'LS-INACTIVE',
            'unit' => 'كجم',
            'cost_price' => '1.000',
            'selling_price' => '2.000',
            'current_stock' => '0.000',
            'min_stock_level' => '10.000',
            'is_active' => false,
        ]);

        return $items;
    }
}
