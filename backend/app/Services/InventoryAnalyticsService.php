<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Services\Reports\CarryingCost;
use App\Services\Reports\ReportDateRange;
use App\Support\Money\Decimal;
use App\Support\TenantCache;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class InventoryAnalyticsService
{
    public const CACHE_NAMESPACE = 'erp_abc';

    public static function clearCache(?int $storeId = null): void
    {
        // Version bump invalidates every ABC key (all stores, all date ranges) of the current tenant.
        TenantCache::bump(self::CACHE_NAMESPACE);
    }

    /**
     * Perform ABC Analysis and calculate sales velocity and dead stock
     *
     * PERF-01: $salesByItem (item id => ['quantity' => ..., 'revenue' => ...], the period's
     * confirmed invoice lines per item, as the item profitability report computes them as its
     * gross figures) skips the sales aggregate when a caller already has it. Sold quantity and
     * revenue are scale-3 strings whichever path computed them.
     *
     * @param  array<int, array{quantity: string, revenue: string}>|null  $salesByItem
     */
    public function getAbcAnalysis(string $fromDate, string $toDate, ?int $storeId = null, string $sortBy = 'profit', ?array $salesByItem = null): array
    {
        $cacheKey = TenantCache::key(self::CACHE_NAMESPACE.':v'.TenantCache::version(self::CACHE_NAMESPACE).':'.($storeId ?? 'all').":{$fromDate}:{$toDate}:{$sortBy}");

        return Cache::remember($cacheKey, now()->addMinutes(15), function () use ($fromDate, $toDate, $storeId, $sortBy, $salesByItem) {
            $daysInPeriod = max(1, Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)) + 1);

            // 1. Every active item with its current stock and unit cost. PERF-01: plain rows of
            // the needed columns (no models); values settled at scale 3 like the decimal:3 casts.
            $items = DB::table('items')
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'name', 'code', 'unit', 'current_stock', 'cost_price', 'weighted_avg_cost', 'selling_price'])
                ->keyBy('id');

            // 2. Sales per item in the period (PERF-01: half-open range on the column)
            $salesByItem ??= $this->salesByItem($fromDate, $toDate, $storeId);

            $analyzedItems = [];
            $totalCompanyProfit = '0.000';
            $totalCompanyRevenue = '0.000';
            $soldItemIds = [];

            foreach ($items as $itemId => $item) {
                $salesData = $salesByItem[(int) $itemId] ?? null;

                $qtySold = $salesData['quantity'] ?? '0.000';
                $revenue = $salesData['revenue'] ?? '0.000';
                // BUG-02: the carrying cost (average, the last purchase cost only while the average
                // is zero / NULL), the same unit cost as the inventory valuation.
                $unitCost = CarryingCost::of($item->weighted_avg_cost, $item->cost_price);
                $cogs = Decimal::mul($qtySold, $unitCost); // PERF-01: half-up per line (money rule); was truncated
                $grossProfit = bcsub($revenue, $cogs, 3);

                $velocity = bcdiv($qtySold, (string) $daysInPeriod, 3); // Daily run rate

                if (bccomp($qtySold, '0.000', 3) > 0) {
                    $soldItemIds[] = $itemId;
                    $totalCompanyProfit = bcadd($totalCompanyProfit, $grossProfit, 3);
                    $totalCompanyRevenue = bcadd($totalCompanyRevenue, $revenue, 3);
                }

                $currentStock = $this->decimalOrNull($item->current_stock) ?? '0.000';
                $stockValue = Decimal::mul($currentStock, $unitCost); // PERF-01: half-up, as the valuation lines

                $analyzedItems[] = [
                    'id' => (int) $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'unit' => $item->unit,
                    'current_stock' => $currentStock,
                    'stock_value' => $stockValue,
                    'unit_price' => (string) $this->decimalOrNull($item->selling_price),
                    'unit_cost' => $unitCost,
                    'quantity_sold' => $qtySold,
                    'revenue' => $revenue,
                    'cogs' => $cogs,
                    'gross_profit' => $grossProfit,
                    'velocity' => $velocity,
                    'profit_margin' => bccomp($revenue, '0.000', 3) > 0
                        ? bcmul(bcdiv($grossProfit, $revenue, 4), '100', 2)
                        : '0.00',
                    'is_sold' => bccomp($qtySold, '0.000', 3) > 0,
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
                if (! $row['is_sold']) {
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
                $prevShare = (float) bccomp($totalCompanyProfit, '0.000', 3) > 0
                    ? (float) bcmul(bcdiv(bcsub($cumulativeProfit, $row['gross_profit'], 3), $totalCompanyProfit, 4), '100', 2)
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
                'items' => $analyzedItems,
                'dead_stock' => $deadStockItems,
                'total_items_count' => count($analyzedItems),
                'total_profit' => $totalCompanyProfit,
                'total_revenue' => $totalCompanyRevenue,
                'days_in_period' => $daysInPeriod,
                'class_a' => [
                    'count' => $classACount,
                    'profit' => $classAProfit,
                    'share' => bccomp($totalCompanyProfit, '0.000', 3) > 0
                        ? bcmul(bcdiv($classAProfit, $totalCompanyProfit, 4), '100', 1)
                        : '0.0',
                ],
                'class_b' => [
                    'count' => $classBCount,
                    'profit' => $classBProfit,
                    'share' => bccomp($totalCompanyProfit, '0.000', 3) > 0
                        ? bcmul(bcdiv($classBProfit, $totalCompanyProfit, 4), '100', 1)
                        : '0.0',
                ],
                'class_c' => [
                    'count' => $classCCount,
                    'profit' => $classCProfit,
                    'share' => bccomp($totalCompanyProfit, '0.000', 3) > 0
                        ? bcmul(bcdiv($classCProfit, $totalCompanyProfit, 4), '100', 1)
                        : '0.0',
                ],
            ];
        });
    }

    /**
     * @return array<int, array{quantity: string, revenue: string}>
     */
    private function salesByItem(string $fromDate, string $toDate, ?int $storeId): array
    {
        $salesQuery = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->where('invoices.status', 'confirmed')
            ->whereNull('invoices.deleted_at');
        ReportDateRange::apply($salesQuery, 'invoices.invoice_date', $fromDate, $toDate);

        if ($storeId) {
            $salesQuery->where('invoices.store_id', $storeId);
        }

        $out = [];
        foreach ($salesQuery->toBase()
            ->select('invoice_items.item_id')
            ->selectRaw('SUM(invoice_items.quantity) as total_quantity')
            ->selectRaw('SUM(invoice_items.total_price) as total_revenue')
            ->groupBy('invoice_items.item_id')
            ->get() as $row) {
            $out[(int) $row->item_id] = [
                'quantity' => Decimal::normalize((string) $row->total_quantity),
                'revenue' => Decimal::normalize((string) $row->total_revenue),
            ];
        }

        return $out;
    }

    /**
     * A raw DECIMAL column as the decimal:3 cast reads it ("12.500"), or null when NULL.
     * Matches the former `$item->col ?: …` checks on the cast model, where "0.000" is truthy.
     */
    private function decimalOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Decimal::normalize((string) $value);
    }
}
