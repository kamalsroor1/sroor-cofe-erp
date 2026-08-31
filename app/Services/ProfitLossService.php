<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Expense;
use App\Models\Store;
use App\Models\ReturnDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class ProfitLossService
{
    public static function clearCache(?int $storeId = null): void
    {
        // No-op for backward compatibility
    }

    /**
     * Get Comprehensive Profit & Loss Statement per branch/van and company-wide
     */
    public function getProfitLossReport(string $fromDate, string $toDate, ?int $storeId = null): array
    {
        $stores = Store::active()->get();

        $storeReports = [];
        $grandRevenue = '0.000';
        $grandCogs = '0.000';
        $grandGrossProfit = '0.000';
        $grandExpenses = '0.000';
        $grandNetProfit = '0.000';
        $grandCostCenters = [
            'rent'        => '0.000',
            'utilities'   => '0.000',
            'salaries'    => '0.000',
            'vehicles'    => '0.000',
            'maintenance' => '0.000',
            'packaging'   => '0.000',
            'hospitality' => '0.000',
            'marketing'   => '0.000',
            'shipping'    => '0.000',
            'operational' => '0.000',
        ];

        foreach ($stores as $store) {
            if ($storeId && $store->id !== $storeId) {
                continue;
            }

            // 1. Sales & Invoices in period
            $invoices = Invoice::with(['items.item'])
                ->where('store_id', $store->id)
                ->where('status', 'confirmed')
                ->whereDate('invoice_date', '>=', $fromDate)
                ->whereDate('invoice_date', '<=', $toDate)
                ->get();

            $storeRevenue = '0.000';
            $storeCogs = '0.000';
            $invoicesCount = $invoices->count();

            foreach ($invoices as $inv) {
                $storeRevenue = bcadd($storeRevenue, (string)$inv->net_total, 3);

                foreach ($inv->items as $item) {
                    $itemCost = bccomp((string)$item->cost_price, '0.000', 3) > 0
                        ? (string)$item->cost_price
                        : (string)($item->item?->weighted_avg_cost ?: ($item->item?->cost_price ?: '0.000'));
                    $itemCogs = bcmul((string)$item->quantity, $itemCost, 3);
                    $storeCogs = bcadd($storeCogs, $itemCogs, 3);
                }
            }

            // 2. Returns in period
            $returns = ReturnDocument::with(['items.item'])
                ->where('store_id', $store->id)
                ->where('return_type', 'sales_return')
                ->whereDate('return_date', '>=', $fromDate)
                ->whereDate('return_date', '<=', $toDate)
                ->get();

            $returnAmount = '0.000';
            $returnCogs = '0.000';
            foreach ($returns as $ret) {
                $returnAmount = bcadd($returnAmount, (string)$ret->total_amount, 3);
                foreach ($ret->items as $ritem) {
                    $itemCost = bccomp((string)$ritem->cost_price, '0.000', 3) > 0
                        ? (string)$ritem->cost_price
                        : (string)($ritem->item?->weighted_avg_cost ?: ($ritem->item?->cost_price ?: '0.000'));
                    $rcogs = bcmul((string)$ritem->quantity, $itemCost, 3);
                    $returnCogs = bcadd($returnCogs, $rcogs, 3);
                }
            }

            // Net Revenue & Net COGS after returns
            $netRevenue = bcsub($storeRevenue, $returnAmount, 3);
            $netCogs = bcsub($storeCogs, $returnCogs, 3);
            $grossProfit = bcsub($netRevenue, $netCogs, 3);
            $grossMargin = bccomp($netRevenue, '0.000', 3) > 0
                ? bcmul(bcdiv($grossProfit, $netRevenue, 4), '100', 2)
                : '0.00';

            // 3. Expenses per cost center (include unassigned general expenses if default main store)
            $expenses = Expense::where(function ($q) use ($store) {
                    $q->where('store_id', $store->id);
                    if ($store->is_default) {
                        $q->orWhereNull('store_id');
                    }
                })
                ->whereDate('expense_date', '>=', $fromDate)
                ->whereDate('expense_date', '<=', $toDate)
                ->get();

            $storeExpensesTotal = '0.000';
            $storeCostCenters = [
                'rent'        => '0.000',
                'utilities'   => '0.000',
                'salaries'    => '0.000',
                'vehicles'    => '0.000',
                'maintenance' => '0.000',
                'packaging'   => '0.000',
                'hospitality' => '0.000',
                'marketing'   => '0.000',
                'shipping'    => '0.000',
                'operational' => '0.000',
            ];

            foreach ($expenses as $exp) {
                $amt = (string)$exp->amount;
                $cc = $exp->cost_center ?: 'operational';
                if (!isset($storeCostCenters[$cc])) {
                    $cc = 'operational';
                }
                $storeCostCenters[$cc] = bcadd($storeCostCenters[$cc], $amt, 3);
                $storeExpensesTotal = bcadd($storeExpensesTotal, $amt, 3);

                // Add to grand
                $grandCostCenters[$cc] = bcadd($grandCostCenters[$cc], $amt, 3);
            }

            $netOperatingProfit = bcsub($grossProfit, $storeExpensesTotal, 3);
            $netMargin = bccomp($netRevenue, '0.000', 3) > 0
                ? bcmul(bcdiv($netOperatingProfit, $netRevenue, 4), '100', 2)
                : '0.00';

            $storeReports[] = [
                'store_id'             => $store->id,
                'store_name'           => $store->name,
                'store_code'           => $store->code,
                'is_default'           => $store->is_default,
                'invoices_count'       => $invoicesCount,
                'gross_sales'          => $storeRevenue,
                'returns_amount'       => $returnAmount,
                'net_revenue'          => $netRevenue,
                'cogs'                 => $netCogs,
                'gross_profit'         => $grossProfit,
                'gross_margin'         => $grossMargin,
                'cost_centers'         => $storeCostCenters,
                'expenses_total'       => $storeExpensesTotal,
                'net_operating_profit' => $netOperatingProfit,
                'net_margin'           => $netMargin,
            ];

            $grandRevenue = bcadd($grandRevenue, $netRevenue, 3);
            $grandCogs = bcadd($grandCogs, $netCogs, 3);
            $grandGrossProfit = bcadd($grandGrossProfit, $grossProfit, 3);
            $grandExpenses = bcadd($grandExpenses, $storeExpensesTotal, 3);
            $grandNetProfit = bcadd($grandNetProfit, $netOperatingProfit, 3);
        }

        $grandGrossMargin = bccomp($grandRevenue, '0.000', 3) > 0
            ? bcmul(bcdiv($grandGrossProfit, $grandRevenue, 4), '100', 2)
            : '0.00';

        $grandNetMargin = bccomp($grandRevenue, '0.000', 3) > 0
            ? bcmul(bcdiv($grandNetProfit, $grandRevenue, 4), '100', 2)
            : '0.00';

        return [
            'stores'            => $storeReports,
            'grand_revenue'     => $grandRevenue,
            'grand_cogs'        => $grandCogs,
            'grand_gross_profit'=> $grandGrossProfit,
            'grand_gross_margin'=> $grandGrossMargin,
            'grand_cost_centers'=> $grandCostCenters,
            'grand_expenses'    => $grandExpenses,
            'grand_net_profit'  => $grandNetProfit,
            'grand_net_margin'  => $grandNetMargin,
            'from_date'         => $fromDate,
            'to_date'           => $toDate,
        ];
    }
}
