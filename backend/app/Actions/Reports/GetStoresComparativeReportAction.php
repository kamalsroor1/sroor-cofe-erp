<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Invoice;
use App\Models\Store;
use App\Support\Money\Decimal;

final class GetStoresComparativeReportAction
{
    /**
     * Compute comparative performance across all active stores/branches
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $stores = Store::where('is_active', true)->select('id', 'name')->get();
        $storeBreakdown = [];

        // Global total sales in period for share_pct calculation
        $allInvoices = Invoice::where('status', 'confirmed')
            ->whereDate('invoice_date', '>=', $dto->from_date)
            ->whereDate('invoice_date', '<=', $dto->to_date)
            ->get();

        $overallSales = '0.000';
        foreach ($allInvoices as $ai) {
            $overallSales = bcadd($overallSales, (string) $ai->net_total, 3);
        }

        foreach ($stores as $st) {
            $stInvoices = Invoice::where('status', 'confirmed')
                ->where('store_id', $st->id)
                ->whereDate('invoice_date', '>=', $dto->from_date)
                ->whereDate('invoice_date', '<=', $dto->to_date)
                ->get();

            $stSales = '0.000';
            $stPaid = '0.000';
            $stRemaining = '0.000';
            $stCost = '0.000';

            foreach ($stInvoices as $si) {
                $stSales = bcadd($stSales, (string) $si->net_total, 3);
                $stPaid = bcadd($stPaid, (string) $si->paid_amount, 3);
                $stRemaining = bcadd($stRemaining, (string) $si->remaining_amount, 3);
                $stCost = bcadd($stCost, (string) $si->total_cost, 3);
            }

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
                'invoice_count' => $stInvoices->count(),
                'total_sales' => Decimal::normalize((string) $stSales),
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
