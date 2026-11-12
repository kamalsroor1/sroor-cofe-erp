<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\DTOs\Reports\ReportFilterDTO;
use App\Models\Customer;
use App\Models\Expense;
use App\Services\Reports\InventoryValuation;
use App\Services\Reports\InvoiceAggregates;
use App\Services\Reports\ReportDateRange;
use App\Services\Reports\SalesReturnAggregates;
use App\Support\Money\Decimal;

final class GetProfitLossReportAction
{
    public function __construct(
        private readonly SalesReturnAggregates $salesReturns,
        private readonly InvoiceAggregates $invoices,
        private readonly InventoryValuation $inventoryValuation,
    ) {}

    /**
     * Compute Executive Profit & Loss summary metrics
     */
    public function execute(ReportFilterDTO $dto): array
    {
        // PERF-01: aggregates in SQL (never a model per invoice); half-open date range on the column.
        $sold = $this->invoices->totals($dto->from_date, $dto->to_date, $dto->store_id ?: null);
        $grossSales = $sold['net_total'];
        $totalPaid = $sold['paid'];
        $totalRemaining = $sold['remaining'];
        $grossCost = $sold['cost']; // COGS of the invoices, before returns
        $invoicesCount = $sold['count'];

        // BUG-01: the period's sales returns (by return date) reduce sales by their value and
        // COGS by the original lines' cost; profit is computed on the net figures.
        $returns = $this->salesReturns->totals($dto->from_date, $dto->to_date, $dto->store_id);
        $totalSales = bcsub($grossSales, $returns['value'], 3);
        $totalCost = bcsub($grossCost, $returns['cost'], 3);

        $grossProfit = bcsub($totalSales, $totalCost, 3);
        $marginPct = '0.00';
        if (bccomp($totalSales, '0.000', 3) > 0) {
            $marginPct = bcmul(bcdiv($grossProfit, $totalSales, 4), '100', 2);
        }

        // Average value of an issued invoice (returns are separate documents).
        $avgInvoice = $invoicesCount > 0 ? bcdiv($grossSales, (string) $invoicesCount, 2) : '0.00';

        // Expenses
        $expensesQuery = Expense::query()->when($dto->store_id, fn ($q) => $q->where('store_id', $dto->store_id));
        ReportDateRange::apply($expensesQuery, 'expense_date', $dto->from_date, $dto->to_date);
        $expenseRow = $expensesQuery->toBase()
            ->selectRaw('COUNT(*) as expenses_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as expenses_sum')
            ->first(); // an aggregate without GROUP BY always returns one row
        $totalExpenses = Decimal::normalize(InvoiceAggregates::scalar($expenseRow->expenses_sum));
        $expensesCount = (int) $expenseRow->expenses_count;
        $netProfit = bcsub($grossProfit, $totalExpenses, 3);

        // Overall Customers Debt
        $totalCustomersDebt = Decimal::normalize((string) (Customer::where('is_active', true)->sum('current_balance') ?: 0));

        // Inventory Stock Valuation (shared with the inventory report; streamed, no models)
        $valuation = $this->inventoryValuation->totals($dto->store_id ?: null);
        $stockCostValuation = $valuation['cost'];
        $stockSellingValuation = $valuation['selling'];
        $expectedStockProfit = $valuation['profit'];

        return [
            'period' => [
                'preset' => $dto->period,
                'from_date' => $dto->from_date,
                'to_date' => $dto->to_date,
            ],
            'summary' => [
                // total_sales, total_cogs, gross_profit, margin and net_profit are NET of returns.
                'total_sales' => Decimal::normalize((string) $totalSales),
                'gross_sales' => Decimal::normalize((string) $grossSales),
                'total_returns' => $returns['value'],
                'returns_count' => $returns['count'],
                'returns_cost' => $returns['cost'],
                'net_sales' => Decimal::normalize((string) $totalSales),
                'gross_cogs' => Decimal::normalize((string) $grossCost),
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
