<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Support\Money\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * BUG-01 (single source): the cash that reached / left the drawer on one business date.
 *
 * Every cash amount that reaches the drawer is exactly one cash payment voucher: the invoice
 * writes one per paid line at the sale (PAY-INV-*), a debt collection writes its own. Adding
 * invoices.paid_amount on top of the vouchers counted every cash sale twice, counted non-cash
 * split lines as cash and re-counted later collections. The daily journal (screen + A4 print),
 * the dashboards and the treasury summary all read their cash figures from here.
 *
 * $storeId null means every store; callers pass null only for a verified all-stores read.
 */
final class DailyCashFlowService
{
    /**
     * @return array{cash_sales: string, customer_receipts: string, total_cash_in: string, supplier_paid: string, cash_expenses: string, total_cash_out: string, net_cash: string}
     */
    public function forDate(string $date, ?int $storeId): array
    {
        // Cash taken at the sale (cash invoices and the paid part of partial invoices)
        $cashSales = $this->sum($this->cashVouchers($date, $storeId)->collectedAtSale()->excludingCancelledInvoices(), 'payments.amount');

        // Cash collected from customers afterwards (receipts / debt collection)
        $customerReceipts = $this->sum($this->cashVouchers($date, $storeId)->customerReceipts()->excludingCancelledInvoices(), 'payments.amount');

        // Cash paid to suppliers from the drawer
        $supplierPaid = $this->sum($this->cashVouchers($date, $storeId)->whereNotNull('payments.supplier_id'), 'payments.amount');

        // Operating expenses paid in cash
        $cashExpenses = $this->sum(Expense::query()
            ->whereDate('expense_date', $date)
            ->where('payment_method', 'cash')
            ->when($storeId !== null, fn (Builder $q) => $q->where('store_id', $storeId)), 'amount');

        $totalCashIn = bcadd($cashSales, $customerReceipts, 3);
        $totalCashOut = bcadd($supplierPaid, $cashExpenses, 3);

        return [
            'cash_sales' => $cashSales,
            'customer_receipts' => $customerReceipts,
            'total_cash_in' => $totalCashIn,
            'supplier_paid' => $supplierPaid,
            'cash_expenses' => $cashExpenses,
            'total_cash_out' => $totalCashOut,
            'net_cash' => bcsub($totalCashIn, $totalCashOut, 3),
        ];
    }

    /**
     * @return Builder<Payment>
     */
    private function cashVouchers(string $date, ?int $storeId): Builder
    {
        return Payment::query()
            ->where('payments.payment_method', 'cash')
            ->whereDate('payments.payment_date', $date)
            ->when($storeId !== null, fn (Builder $q) => $q->forStore((int) $storeId));
    }

    /**
     * Exact scale-3 sum of a DECIMAL(12,3) column (bcmath over the row values, never a float SUM).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     */
    private function sum(Builder $query, string $column): string
    {
        /** @var Collection<int, mixed> $values */
        $values = $query->pluck($column);

        return $values->reduce(
            fn (string $carry, mixed $value): string => bcadd($carry, Decimal::normalize(is_numeric($value) ? (string) $value : '0'), 3),
            '0.000',
        );
    }
}
