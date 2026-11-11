<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StoreStock;
use App\Support\Money\Decimal;

final class GetProfitLossReportAction
{
    /**
     * Compute Executive Profit & Loss summary metrics
     */
    public function execute(ReportFilterDTO $dto): array
    {
        $invoicesQuery = Invoice::where('status', 'confirmed')
            ->whereDate('invoice_date', '>=', $dto->from_date)
            ->whereDate('invoice_date', '<=', $dto->to_date)
            ->when($dto->store_id, fn ($q) => $q->where('store_id', $dto->store_id));

        $invoices = (clone $invoicesQuery)->get();
        $totalSales = '0.000';
        $totalPaid = '0.000';
        $totalRemaining = '0.000';
        $totalCost = '0.000'; // COGS
        $invoicesCount = $invoices->count();

        foreach ($invoices as $inv) {
            $totalSales = bcadd($totalSales, (string) $inv->net_total, 3);
            $totalPaid = bcadd($totalPaid, (string) $inv->paid_amount, 3);
            $totalRemaining = bcadd($totalRemaining, (string) $inv->remaining_amount, 3);
            $totalCost = bcadd($totalCost, (string) $inv->total_cost, 3);
        }

        $grossProfit = bcsub($totalSales, $totalCost, 3);
        $marginPct = '0.00';
        if (bccomp($totalSales, '0.000', 3) > 0) {
            $marginPct = bcmul(bcdiv($grossProfit, $totalSales, 4), '100', 2);
        }

        $avgInvoice = $invoicesCount > 0 ? bcdiv($totalSales, (string) $invoicesCount, 2) : '0.00';

        // Expenses
        $expensesQuery = Expense::whereDate('expense_date', '>=', $dto->from_date)
            ->whereDate('expense_date', '<=', $dto->to_date)
            ->when($dto->store_id, fn ($q) => $q->where('store_id', $dto->store_id));

        $totalExpenses = (string) ($expensesQuery->sum('amount') ?: '0.000');
        $expensesCount = $expensesQuery->count();
        $netProfit = bcsub($grossProfit, $totalExpenses, 3);

        // Overall Customers Debt
        $totalCustomersDebt = Decimal::normalize((string) (Customer::where('is_active', true)->sum('current_balance') ?: 0));

        // Inventory Stock Valuation
        $stockCostValuation = '0.000';
        $stockSellingValuation = '0.000';

        if ($dto->store_id) {
            $storeStocks = StoreStock::with('item')
                ->where('store_id', $dto->store_id)
                ->whereHas('item', fn ($q) => $q->where('is_active', true))
                ->get();

            foreach ($storeStocks as $stk) {
                $item = $stk->item;
                if (! $item) {
                    continue;
                }
                $qty = (string) ($stk->quantity ?? '0.000');
                $costPrice = (string) ($item->cost_price ?? '0.000');
                $sellingPrice = (string) ($stk->custom_selling_price ?: $item->selling_price);

                $stockCostValuation = bcadd($stockCostValuation, bcmul($qty, $costPrice, 3), 3);
                $stockSellingValuation = bcadd($stockSellingValuation, bcmul($qty, $sellingPrice, 3), 3);
            }
        } else {
            $allItems = Item::where('is_active', true)->get();
            foreach ($allItems as $itm) {
                $qty = (string) ($itm->current_stock ?? '0.000');
                $costPrice = (string) ($itm->cost_price ?? '0.000');
                $sellingPrice = (string) ($itm->selling_price ?? '0.000');

                $stockCostValuation = bcadd($stockCostValuation, bcmul($qty, $costPrice, 3), 3);
                $stockSellingValuation = bcadd($stockSellingValuation, bcmul($qty, $sellingPrice, 3), 3);
            }
        }

        $expectedStockProfit = bcsub($stockSellingValuation, $stockCostValuation, 3);

        return [
            'period' => [
                'preset' => $dto->period,
                'from_date' => $dto->from_date,
                'to_date' => $dto->to_date,
            ],
            'summary' => [
                'total_sales' => Decimal::normalize((string) $totalSales),
                'total_cogs' => Decimal::normalize((string) $totalCost),
                'gross_profit' => Decimal::normalize((string) $grossProfit),
                'margin_percentage' => Decimal::normalize((string) $marginPct, 2),
                'total_expenses' => Decimal::normalize((string) $totalExpenses),
                'expenses_count' => $expensesCount,
                'net_profit' => Decimal::normalize((string) $netProfit),
                'invoices_count' => $invoicesCount,
                'avg_invoice' => Decimal::normalize((string) $avgInvoice),
                'total_paid' => Decimal::normalize((string) $totalPaid),
                'total_remaining' => Decimal::normalize((string) $totalRemaining),
                'total_customers_debt' => $totalCustomersDebt,
                'stock_cost_valuation' => Decimal::normalize((string) $stockCostValuation),
                'stock_selling_valuation' => Decimal::normalize((string) $stockSellingValuation),
                'expected_stock_profit' => Decimal::normalize((string) $expectedStockProfit),
            ],
        ];
    }
}
