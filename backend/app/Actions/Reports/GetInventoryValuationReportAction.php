<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Services\InventoryAnalyticsService;
use App\Services\Reports\InventoryValuation;

final class GetInventoryValuationReportAction
{
    /** Per-unit cost keys of a valued row, null without items.view_cost. */
    private const ROW_UNIT_COST_KEYS = ['average_cost', 'last_purchase_price', 'cost_price'];

    /** Per-unit cost key of an ABC row, null without items.view_cost. */
    private const ABC_UNIT_COST_KEYS = ['unit_cost'];

    public function __construct(
        private readonly InventoryAnalyticsService $inventoryAnalyticsService,
        private readonly InventoryValuation $inventoryValuation,
    ) {}

    /**
     * Inventory valuation at cost and at selling price, plus the ABC analysis.
     *
     * PERF-01: the three valuation totals cover EVERY active item (streamed, never the page);
     * `items` is one page of the valued rows (most valuable stock first, `stock_filter` applied)
     * and `abc_data.items` / `abc_data.dead_stock` are capped at the same page size (they are
     * already ranked by profit). `meta` tells the client how many rows exist in total.
     *
     * @param  array<int, array{quantity: string, revenue: string}>|null  $salesByItem  the period's
     *                                                                                  sales per item, when the caller already has them (comprehensive bundle)
     * @param  bool  $showUnitCost  BUG-02 review: whether the caller holds items.view_cost (CostVisibility).
     *                              Without it the PER-UNIT costs of the rows (average_cost,
     *                              last_purchase_price, cost_price, ABC unit_cost) are null; the
     *                              totals and per-row values (cost_val) stay as before.
     * @return array<string, mixed>
     */
    public function execute(ReportFilterDTO $dto, ?array $salesByItem = null, bool $showUnitCost = false): array
    {
        $storeId = $dto->store_id ?: null;

        $totals = $this->inventoryValuation->totals($storeId);
        $page = $this->inventoryValuation->page($storeId, $dto->stock_filter, $dto->page, $dto->per_page);

        $abcData = $this->inventoryAnalyticsService->getAbcAnalysis($dto->from_date, $dto->to_date, $storeId, salesByItem: $salesByItem);
        $abcItemsTotal = count($abcData['items']);
        $deadStockTotal = count($abcData['dead_stock']);
        $abcData['items'] = array_slice($abcData['items'], 0, $dto->per_page);
        $abcData['dead_stock'] = array_slice($abcData['dead_stock'], 0, $dto->per_page);
        $abcData['dead_stock_count'] = $deadStockTotal;

        // Masked here, after the ABC TenantCache read: the cached payload is shared by every user.
        if (! $showUnitCost) {
            $page['rows'] = self::withoutKeys($page['rows'], self::ROW_UNIT_COST_KEYS);
            $abcData['items'] = self::withoutKeys($abcData['items'], self::ABC_UNIT_COST_KEYS);
            $abcData['dead_stock'] = self::withoutKeys($abcData['dead_stock'], self::ABC_UNIT_COST_KEYS);
        }

        return [
            'stock_cost_valuation' => $totals['cost'],
            'stock_selling_valuation' => $totals['selling'],
            'expected_stock_profit' => $totals['profit'],
            'items' => $page['rows'],
            'abc_data' => $abcData,
            'meta' => [
                'current_page' => $dto->page,
                'per_page' => $dto->per_page,
                'total' => $page['total'],
                'last_page' => max(1, (int) ceil($page['total'] / $dto->per_page)),
                'valued_items_count' => $totals['count'],
                'abc_items_total' => $abcItemsTotal,
                'abc_items_shown' => count($abcData['items']),
                'dead_stock_shown' => count($abcData['dead_stock']),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  list<string>  $keys
     * @return list<array<string, mixed>>
     */
    private static function withoutKeys(array $rows, array $keys): array
    {
        $out = [];
        foreach ($rows as $row) {
            foreach ($keys as $key) {
                if (array_key_exists($key, $row)) {
                    $row[$key] = null;
                }
            }
            $out[] = $row;
        }

        return $out;
    }
}
