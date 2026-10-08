<?php

namespace App\Services;

use App\Models\Invoice;

class ProfitService
{
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
        $query = Invoice::where('status', 'confirmed')
            ->when($fromDate, fn ($q) => $q->whereDate('invoice_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('invoice_date', '<=', $toDate))
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId));

        $invoices = $query->get();

        $totalSales = '0.000';
        $totalCost = '0.000';

        foreach ($invoices as $inv) {
            $totalSales = bcadd($totalSales, $inv->net_total, 3);
            $totalCost = bcadd($totalCost, $inv->total_cost, 3);
        }

        $grossProfit = bcsub($totalSales, $totalCost, 3);
        $marginPct = '0.00';
        if (bccomp($totalSales, '0.000', 3) > 0) {
            $marginPct = bcmul(bcdiv($grossProfit, $totalSales, 4), '100', 2);
        }

        return [
            'invoice_count' => $invoices->count(),
            'total_sales' => $totalSales,
            'total_cost' => $totalCost,
            'gross_profit' => $grossProfit,
            'margin_percentage' => $marginPct,
        ];
    }
}
