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
