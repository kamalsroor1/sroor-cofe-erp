<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Entitlements\PlanEnforcer;

/**
 * BILL-03: profit figures (gross/net profit, COGS, margins) belong to the `reports.advanced`
 * plan feature. A shop without it still gets sales, expenses and every other basic report, but
 * the profit fields are removed from the payload (not nulled in some places and present in
 * others): one rule, one place. Cost visibility per user stays CostVisibility's job.
 */
final class ProfitVisibility
{
    public const FEATURE = 'reports.advanced';

    /** Keys of the P&L summary that reveal cost or profit. */
    private const SUMMARY_KEYS = [
        'gross_cogs', 'total_cogs', 'gross_profit', 'margin_percentage', 'net_profit',
        'returns_cost', 'stock_cost_valuation', 'expected_stock_profit',
    ];

    /** Keys of an items-profitability row / totals that reveal cost or profit. */
    private const ITEM_KEYS = ['total_cogs', 'profit', 'margin', 'gross_cogs', 'returned_cogs'];

    /** Keys of a per-store comparison row / inventory totals that reveal profit. */
    private const STORE_KEYS = ['gross_profit', 'margin', 'expected_stock_profit'];

    /**
     * BILL-03 review: inventory report top level. The cost valuation next to the selling valuation
     * IS the expected stock profit, so it goes too.
     */
    private const INVENTORY_KEYS = ['stock_cost_valuation', 'expected_stock_profit'];

    /** Keys of a valued stock row (`items`): its profit, and its cost value next to its selling value. */
    private const INVENTORY_ROW_KEYS = ['profit', 'cost_val'];

    /**
     * Keys of an ABC analysis row (`abc_data.items` / `abc_data.dead_stock`): cost of goods sold,
     * profit, margin, the profit shares, and the stock cost value next to the selling price.
     */
    private const ABC_ROW_KEYS = ['cogs', 'gross_profit', 'profit_margin', 'profit_share', 'cum_profit_share', 'stock_value'];

    /** Keys of an ABC class summary (`abc_data.class_a|b|c`): its profit and its share of profit. */
    private const ABC_CLASS_KEYS = ['profit', 'share'];

    public static function allows(): bool
    {
        return app(PlanEnforcer::class)->hasFeature(self::FEATURE);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return array<string, mixed>
     */
    public static function summary(array $summary): array
    {
        return self::allows() ? $summary : array_diff_key($summary, array_flip(self::SUMMARY_KEYS));
    }

    /**
     * @param  array<array-key, mixed>  $row  one item row or the totals row
     * @return array<array-key, mixed>
     */
    public static function itemRow(array $row): array
    {
        return self::allows() ? $row : array_diff_key($row, array_flip(self::ITEM_KEYS));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function itemRows(array $rows): array
    {
        if (self::allows()) {
            return $rows;
        }

        return array_map(static fn (array $row): array => self::itemRow($row), $rows);
    }

    /**
     * BILL-03 review: the inventory valuation + ABC payload (GetInventoryValuationReportAction).
     * Stripping only the top level left `abc_data.items[].cogs/gross_profit/profit_margin`,
     * `abc_data.total_profit`, `abc_data.class_*.profit` and the cost valuation in the response.
     *
     * @param  array<string, mixed>  $inventory
     * @return array<string, mixed>
     */
    public static function inventory(array $inventory): array
    {
        if (self::allows()) {
            return $inventory;
        }

        $inventory = array_diff_key($inventory, array_flip(self::INVENTORY_KEYS));

        if (isset($inventory['items']) && is_array($inventory['items'])) {
            $inventory['items'] = self::withoutKeys($inventory['items'], self::INVENTORY_ROW_KEYS);
        }

        if (isset($inventory['abc_data']) && is_array($inventory['abc_data'])) {
            $abc = $inventory['abc_data'];
            unset($abc['total_profit']);

            foreach (['items', 'dead_stock'] as $list) {
                if (isset($abc[$list]) && is_array($abc[$list])) {
                    $abc[$list] = self::withoutKeys($abc[$list], self::ABC_ROW_KEYS);
                }
            }

            foreach (['class_a', 'class_b', 'class_c'] as $class) {
                if (isset($abc[$class]) && is_array($abc[$class])) {
                    $abc[$class] = array_diff_key($abc[$class], array_flip(self::ABC_CLASS_KEYS));
                }
            }

            $inventory['abc_data'] = $abc;
        }

        return $inventory;
    }

    /**
     * @param  array<array-key, mixed>  $rows
     * @param  list<string>  $keys
     * @return list<mixed>
     */
    private static function withoutKeys(array $rows, array $keys): array
    {
        $drop = array_flip($keys);

        return array_values(array_map(static fn (mixed $row): mixed => is_array($row) ? array_diff_key($row, $drop) : $row, $rows));
    }

    /**
     * @param  array<array-key, mixed>  $rows  per-store rows, or any array holding STORE_KEYS at its top level
     * @return array<array-key, mixed>
     */
    public static function storeRows(array $rows): array
    {
        if (self::allows()) {
            return $rows;
        }

        if (array_is_list($rows)) {
            return array_map(static fn (mixed $row): mixed => is_array($row) ? array_diff_key($row, array_flip(self::STORE_KEYS)) : $row, $rows);
        }

        return array_diff_key($rows, array_flip(self::STORE_KEYS));
    }
}
