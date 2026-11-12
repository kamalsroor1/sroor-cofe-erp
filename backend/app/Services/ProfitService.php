<?php

namespace App\Services;

use App\Models\Invoice;
use App\Services\Reports\InvoiceAggregates;
use App\Services\Reports\SalesReturnAggregates;

class ProfitService
{
    public function __construct(
        private readonly SalesReturnAggregates $salesReturns,
        private readonly InvoiceAggregates $invoices,
    ) {}

    /**
     * Calculate financial and profit metrics for an invoice
     */
    public function calculateInvoiceProfit(Invoice $invoice): array
    {
        $netTotal = $invoice->net_total;
        $totalCost = $invoice->total_cost;
        $profit = bcsub($netTotal, $totalCost, 3);
        $marginPct = '0.00';

        if (bccomp($netTotal, '0.000', 3) > 0) {
            $marginPct = bcmul(bcdiv($profit, $netTotal, 4), '100', 2);
        }

        return [
            'subtotal' => $invoice->subtotal,
            'discount_amount' => $invoice->discount_amount,
            'net_total' => $netTotal,
            'total_cost' => $totalCost,
            'gross_profit' => $profit,
            'margin_percentage' => $marginPct,
        ];
    }

    /**
     * Calculate aggregate profit report for a date range and optional store
     */
    public function getPeriodicProfits(?string $fromDate = null, ?string $toDate = null, ?int $storeId = null): array
    {
        // PERF-01: one aggregate statement instead of a model per invoice of the period.
        $sold = $this->invoices->totals($fromDate, $toDate, $storeId ?: null);
        $grossSales = $sold['net_total'];
        $grossCost = $sold['cost'];

        // BUG-01: sales returns dated in the period reduce its sales and its cost.
        $returns = $this->salesReturns->totals($fromDate, $toDate, $storeId);
        $totalSales = bcsub($grossSales, $returns['value'], 3);
        $totalCost = bcsub($grossCost, $returns['cost'], 3);

        $grossProfit = bcsub($totalSales, $totalCost, 3);
        $marginPct = '0.00';
        if (bccomp($totalSales, '0.000', 3) > 0) {
            $marginPct = bcmul(bcdiv($grossProfit, $totalSales, 4), '100', 2);
        }

        return [
            'invoice_count' => $sold['count'],
            // total_sales / total_cost are NET of the period's sales returns.
            'total_sales' => $totalSales,
            'gross_sales' => $grossSales,
            'total_returns' => $returns['value'],
            'returns_cost' => $returns['cost'],
            'returns_count' => $returns['count'],
            'net_sales' => $totalSales,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
            'margin_percentage' => $marginPct,
        ];
    }
}
