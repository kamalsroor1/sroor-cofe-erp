<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\Payment;
use App\Services\Reports\ReportDateRange;
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
 * ROLE-01: $userId narrows every figure to the vouchers / expenses that user booked (the daily
 * journal of a user without `reports.view`); null = everyone's (unchanged).
 * SHIFT-02: $outsideShiftsOnly narrows every figure to the rows no cash shift counts (paid from
 * the main treasury, not a drawer), see outsideEveryShift().
 */
final class DailyCashFlowService
{
    /**
     * @return array{cash_sales: string, customer_receipts: string, total_cash_in: string, supplier_paid: string, cash_expenses: string, cash_refunds: string, total_cash_out: string, net_cash: string}
     */
    public function forDate(string $date, ?int $storeId, ?int $userId = null, bool $outsideShiftsOnly = false): array
    {
        $vouchers = fn (): Builder => $this->cashVouchers($date, $storeId, $userId, $outsideShiftsOnly);

        // Cash taken at the sale (cash invoices and the paid part of partial invoices)
        $cashSales = $this->sum($vouchers()->collectedAtSale()->excludingCancelledInvoices(), 'payments.amount');

        // Cash collected from customers afterwards (receipts / debt collection)
        $customerReceipts = $this->sum($vouchers()->customerReceipts()->excludingCancelledInvoices(), 'payments.amount');

        // Cash paid to suppliers from the drawer
        $supplierPaid = $this->sum($vouchers()->whereNotNull('payments.supplier_id'), 'payments.amount');

        // Operating expenses paid in cash
        $expenses = Expense::query()
            ->where('expenses.payment_method', 'cash')
            ->when($storeId !== null, fn (Builder $q) => $q->where('expenses.store_id', $storeId))
            ->when($userId !== null, fn (Builder $q) => $q->where('expenses.user_id', $userId))
            ->when($outsideShiftsOnly, fn (Builder $q) => $this->outsideEveryShift($q, 'expenses', $storeId));
        ReportDateRange::apply($expenses, 'expense_date', $date, $date);
        $cashExpenses = $this->sum($expenses, 'amount');

        // BE-10: cash handed back to customers for sales returns (negative refund vouchers),
        // shown as a positive outflow.
        $cashRefunds = bcsub('0.000', $this->sum($vouchers()->refunds(), 'payments.amount'), 3);

        $totalCashIn = bcadd($cashSales, $customerReceipts, 3);
        $totalCashOut = bcadd(bcadd($supplierPaid, $cashExpenses, 3), $cashRefunds, 3);

        return [
            'cash_sales' => $cashSales,
            'customer_receipts' => $customerReceipts,
            'total_cash_in' => $totalCashIn,
            'supplier_paid' => $supplierPaid,
            'cash_expenses' => $cashExpenses,
            'cash_refunds' => $cashRefunds,
            'total_cash_out' => $totalCashOut,
            'net_cash' => bcsub($totalCashIn, $totalCashOut, 3),
        ];
    }

    /**
     * @return Builder<Payment>
     */
    private function cashVouchers(string $date, ?int $storeId, ?int $userId, bool $outsideShiftsOnly = false): Builder
    {
        $query = Payment::query()
            ->where('payments.payment_method', 'cash')
            ->when($storeId !== null, fn (Builder $q) => $q->forStore((int) $storeId))
            ->when($userId !== null, fn (Builder $q) => $q->where('payments.user_id', $userId))
            ->when($outsideShiftsOnly, fn (Builder $q) => $this->outsideEveryShift($q, 'payments', $storeId));
        // PERF-02: a half-open range on the column itself (whereDate() = DATE(col) hid the
        // payment_date index and read every voucher of the tenant).
        ReportDateRange::apply($query, 'payments.payment_date', $date, $date);

        return $query;
    }

    /**
     * SHIFT-02: the rows no cash shift counts - unstamped (cash_shift_id NULL: written while the
     * writer had no open shift, e.g. an admin paying an expense from the main treasury in a
     * per-cashier branch) AND outside the legacy window of every shift that could claim them
     * (ShiftAttribution::scopeToShift(): same store, created_at in [opened_at, closed_at ?? now];
     * a per-cashier shift only takes its opener's rows). They are in the treasury but in no Z.
     *
     * $storeId: the store already filtered (payments: forStore(), so a legacy voucher without
     * store_id is matched against that store's shifts); null = every store, where such a legacy
     * voucher is compared with every store's shifts (counted inside when any could claim it).
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    private function outsideEveryShift(Builder $query, string $table, ?int $storeId): Builder
    {
        return $query->whereNull($table.'.cash_shift_id')
            ->whereNotExists(function ($shift) use ($table, $storeId): void {
                $shift->selectRaw('1')
                    ->from('cash_shifts')
                    ->whereNull('cash_shifts.deleted_at')
                    ->whereColumn('cash_shifts.opened_at', '<=', $table.'.created_at')
                    ->where(function ($until) use ($table): void {
                        $until->whereNull('cash_shifts.closed_at')
                            ->orWhereColumn('cash_shifts.closed_at', '>=', $table.'.created_at');
                    });

                if ($storeId !== null) {
                    $shift->where('cash_shifts.store_id', $storeId);
                } else {
                    $shift->where(function ($store) use ($table): void {
                        $store->whereColumn('cash_shifts.store_id', $table.'.store_id')
                            ->orWhereNull($table.'.store_id');
                    });
                }

                $shift->where(function ($owner) use ($table): void {
                    $owner->where('cash_shifts.shift_mode', '!=', 'per_cashier')
                        ->orWhereColumn('cash_shifts.user_id', $table.'.user_id')
                        ->orWhere(function ($nobody) use ($table): void {
                            $nobody->whereNull('cash_shifts.user_id')->whereNull($table.'.user_id');
                        });
                });
            });
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
