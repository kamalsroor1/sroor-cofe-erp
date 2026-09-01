<?php

namespace App\Services;

use App\Models\Item;
use App\Models\InvoiceItem;
use App\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class InventoryAnalyticsService
{
    public static function clearCache(?int $storeId = null): void
    {
        $prefix = 'erp_abc_' . ($storeId ?? 'all');
        Cache::forget($prefix);
        // Also clear the 'all' key when specific store is cleared
        if ($storeId) {
            Cache::forget('erp_abc_all');
        }
    }

    /**
     * Perform ABC Analysis and calculate sales velocity and dead stock
     */
    public function getAbcAnalysis(string $fromDate, string $toDate, ?int $storeId = null, string $sortBy = 'profit'): array
    {
        $cacheKey = "erp_abc_" . ($storeId ?? 'all') . "_{$fromDate}_{$toDate}_{$sortBy}";

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($fromDate, $toDate, $storeId, $sortBy) {
            $daysInPeriod = max(1, Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)) + 1);

            // 1. Get all active items with their current stock and unit cost
            $items = Item::active()->get()->keyBy('id');

        // 2. Aggregate sales per item in period
        $salesQuery = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'confirmed')
            ->whereDate('invoices.invoice_date', '>=', $fromDate)
            ->whereDate('invoices.invoice_date', '<=', $toDate);

        if ($storeId) {
            $salesQuery->where('invoices.store_id', $storeId);
        }

        $aggregatedSales = $salesQuery
            ->select([
                'invoice_items.item_id',
                DB::raw('SUM(invoice_items.quantity) as total_quantity'),
                DB::raw('SUM(invoice_items.total_price) as total_revenue'),
            ])
            ->groupBy('invoice_items.item_id')
            ->get()
            ->keyBy('item_id');

        $analyzedItems = [];
        $totalCompanyProfit = '0.000';
        $totalCompanyRevenue = '0.000';
        $soldItemIds = [];

        foreach ($items as $itemId => $item) {
            $salesData = $aggregatedSales->get($itemId);

            $qtySold = $salesData ? (string)$salesData->total_quantity : '0.000';
            $revenue = $salesData ? (string)$salesData->total_revenue : '0.000';
            $unitCost = (string)($item->weighted_avg_cost ?: ($item->cost_price ?: '0.000'));
            $cogs = bcmul($qtySold, $unitCost, 3);
            $grossProfit = bcsub($revenue, $cogs, 3);

            $velocity = bcdiv($qtySold, (string)$daysInPeriod, 3); // Daily run rate

            if (bccomp($qtySold, '0.000', 3) > 0) {
                $soldItemIds[] = $itemId;
                $totalCompanyProfit = bcadd($totalCompanyProfit, $grossProfit, 3);
                $totalCompanyRevenue = bcadd($totalCompanyRevenue, $revenue, 3);
            }

            $currentStock = (string)($item->current_stock ?: '0.000');
            $stockValue = bcmul($currentStock, $unitCost, 3);

            $analyzedItems[] = [
                'id'                 => $item->id,
                'name'               => $item->name,
                'code'               => $item->code,
                'unit'               => $item->unit,
                'current_stock'      => $currentStock,
                'stock_value'        => $stockValue,
                'unit_price'         => (string)$item->selling_price,
                'unit_cost'          => $unitCost,
                'quantity_sold'      => $qtySold,
                'revenue'            => $revenue,
                'cogs'               => $cogs,
                'gross_profit'       => $grossProfit,
                'velocity'           => $velocity,
                'profit_margin'      => bccomp($revenue, '0.000', 3) > 0
                    ? bcmul(bcdiv($grossProfit, $revenue, 4), '100', 2)
                    : '0.00',
                'is_sold'            => bccomp($qtySold, '0.000', 3) > 0,
            ];
        }

        // Sort items by profit descending (or revenue)
        usort($analyzedItems, function ($a, $b) use ($sortBy) {
            $valA = $sortBy === 'revenue' ? $a['revenue'] : $a['gross_profit'];
            $valB = $sortBy === 'revenue' ? $b['revenue'] : $b['gross_profit'];
            return bccomp($valB, $valA, 3);
        });

        // 3. Assign ABC Classes and Cumulative Shares
        $cumulativeProfit = '0.000';
        $cumulativeRevenue = '0.000';

        $classACount = 0;
        $classBCount = 0;
        $classCCount = 0;
        $classAProfit = '0.000';
        $classBProfit = '0.000';
        $classCProfit = '0.000';

        $deadStockItems = [];

        foreach ($analyzedItems as &$row) {
            if (!$row['is_sold']) {
                $row['abc_class'] = 'C';
                $row['profit_share'] = '0.00';
                $row['cum_profit_share'] = '100.00';
                $row['revenue_share'] = '0.00';
                $classCCount++;

                if (bccomp($row['current_stock'], '0.000', 3) > 0) {
                    $deadStockItems[] = $row;
                }
                continue;
            }

            $profitShare = bccomp($totalCompanyProfit, '0.000', 3) > 0
                ? bcmul(bcdiv($row['gross_profit'], $totalCompanyProfit, 4), '100', 2)
                : '0.00';

            $revShare = bccomp($totalCompanyRevenue, '0.000', 3) > 0
                ? bcmul(bcdiv($row['revenue'], $totalCompanyRevenue, 4), '100', 2)
                : '0.00';

            $cumulativeProfit = bcadd($cumulativeProfit, $row['gross_profit'], 3);
            $cumProfitShare = bccomp($totalCompanyProfit, '0.000', 3) > 0
                ? bcmul(bcdiv($cumulativeProfit, $totalCompanyProfit, 4), '100', 2)
                : '0.00';

            $row['profit_share'] = $profitShare;
            $row['cum_profit_share'] = $cumProfitShare;
            $row['revenue_share'] = $revShare;

            // ABC Rule: Items contributing to the first 80% of total profit = Class A.
            // Items contributing up to 95% = Class B.
            // Remaining items (or unsold) = Class C.
            $prevShare = (float)bccomp($totalCompanyProfit, '0.000', 3) > 0
                ? (float)bcmul(bcdiv(bcsub($cumulativeProfit, $row['gross_profit'], 3), $totalCompanyProfit, 4), '100', 2)
                : 0.0;

            if ($prevShare < 80.00 || $classACount === 0) {
                $row['abc_class'] = 'A';
                $classACount++;
                $classAProfit = bcadd($classAProfit, $row['gross_profit'], 3);
            } elseif ($prevShare < 95.00) {
                $row['abc_class'] = 'B';
                $classBCount++;
                $classBProfit = bcadd($classBProfit, $row['gross_profit'], 3);
            } else {
                $row['abc_class'] = 'C';
                $classCCount++;
                $classCProfit = bcadd($classCProfit, $row['gross_profit'], 3);
            }
        }
        unset($row);

        return [
            'items'                 => $analyzedItems,
            'dead_stock'            => $deadStockItems,
            'total_items_count'     => count($analyzedItems),
            'total_profit'          => $totalCompanyProfit,
            'total_revenue'         => $totalCompanyRevenue,
            'days_in_period'        => $daysInPeriod,
            'class_a' => [
                'count'  => $classACount,
                'profit' => $classAProfit,
                'share'  => bccomp($totalCompanyProfit, '0.000', 3) > 0
                    ? bcmul(bcdiv($classAProfit, $totalCompanyProfit, 4), '100', 1)
                    : '0.0',
            ],
            'class_b' => [
                'count'  => $classBCount,
                'profit' => $classBProfit,
                'share'  => bccomp($totalCompanyProfit, '0.000', 3) > 0
                    ? bcmul(bcdiv($classBProfit, $totalCompanyProfit, 4), '100', 1)
                    : '0.0',
            ],
            'class_c' => [
                'count'  => $classCCount,
                'profit' => $classCProfit,
                'share'  => bccomp($totalCompanyProfit, '0.000', 3) > 0
                    ? bcmul(bcdiv($classCProfit, $totalCompanyProfit, 4), '100', 1)
                    : '0.0',
            ],
        ];
        });
    }
}
