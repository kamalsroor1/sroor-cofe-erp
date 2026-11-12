<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Store;
use App\Services\Reports\InvoiceAggregates;
use App\Services\Reports\SalesReturnAggregates;
use App\Support\Money\Decimal;

final class GetStoresComparativeReportAction
{
    public function __construct(
        private readonly SalesReturnAggregates $salesReturns,
        private readonly InvoiceAggregates $invoices,
    ) {}

    /**
     * Compute comparative performance across all active stores/branches.
     *
     * BUG-01: total_sales, gross_profit, margin and share_pct are NET of each store's sales
     * returns dated in the period; gross_sales / total_returns / net_sales expose the parts.
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $stores = Store::where('is_active', true)->select('id', 'name')->get();
        $storeBreakdown = [];

        // PERF-01: ONE grouped statement for every store (it used to load the whole period twice
        // plus once per store). The overall total is every counted invoice, whatever its store.
        $soldByStore = $this->invoices->byStore($dto->from_date, $dto->to_date);
        $none = ['count' => 0, 'net_total' => '0.000', 'paid' => '0.000', 'remaining' => '0.000', 'cost' => '0.000'];

        $overallSales = '0.000';
        foreach ($soldByStore as $storeSold) {
            $overallSales = bcadd($overallSales, $storeSold['net_total'], 3);
        }

        $returnsByStore = $this->salesReturns->byStore($dto->from_date, $dto->to_date);
        foreach ($returnsByStore as $storeReturns) {
            $overallSales = bcsub($overallSales, $storeReturns['value'], 3);
        }

        foreach ($stores as $st) {
            $stSold = $soldByStore[(int) $st->id] ?? $none;
            $stGrossSales = $stSold['net_total'];
            $stPaid = $stSold['paid'];
            $stRemaining = $stSold['remaining'];
            $stCost = $stSold['cost'];

            $stReturns = $returnsByStore[(int) $st->id] ?? ['value' => '0.000', 'cost' => '0.000', 'count' => 0];
            $stSales = bcsub($stGrossSales, $stReturns['value'], 3);
            $stCost = bcsub($stCost, $stReturns['cost'], 3);

            $stProfit = bcsub($stSales, $stCost, 3);
            $stMargin = '0.00';
            if (bccomp($stSales, '0.000', 3) > 0) {
                $stMargin = bcmul(bcdiv($stProfit, $stSales, 4), '100', 2);
            }

            $sharePct = '0.0';
            if (bccomp($overallSales, '0.000', 3) > 0) {
                $sharePct = bcmul(bcdiv($stSales, $overallSales, 4), '100', 1);
            }

            $storeBreakdown[] = [
                'id' => $st->id,
                'name' => $st->name,
                'invoice_count' => $stSold['count'],
                'total_sales' => Decimal::normalize((string) $stSales),
                'gross_sales' => Decimal::normalize((string) $stGrossSales),
                'total_returns' => $stReturns['value'],
                'returns_count' => $stReturns['count'],
                'net_sales' => Decimal::normalize((string) $stSales),
                'total_paid' => Decimal::normalize((string) $stPaid),
                'total_remaining' => Decimal::normalize((string) $stRemaining),
                'gross_profit' => Decimal::normalize((string) $stProfit),
                'margin' => Decimal::normalize((string) $stMargin, 2),
                'share_pct' => Decimal::normalize((string) $sharePct, 1),
            ];
        }

        return $storeBreakdown;
    }
}
