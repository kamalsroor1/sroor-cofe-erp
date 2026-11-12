<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Services\InventoryAnalyticsService;
use App\Services\Reports\InventoryValuation;

final class GetInventoryValuationReportAction
{
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
     * @return array<string, mixed>
     */
    public function execute(ReportFilterDTO $dto, ?array $salesByItem = null): array
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
}
