<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Store;

final class GetDailyJournalAction
{
    /**
     * Get Daily Journal ledger and cash metrics for a specific date and store
     */
    public function execute(string $date, ?int $storeId = null): array
    {
        $storeId = $storeId ?: Store::getMainStore()?->id ?: Store::first()?->id;

        // Current Active Shift
        $activeShift = CashShift::with(['user', 'store'])
            ->where('status', 'open')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->first();

        // Invoices on selected date
        $invoices = Invoice::with('customer')
            ->whereDate('invoice_date', $date)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->get();

        $confirmedInvoices = $invoices->where('status', '!=', 'cancelled');
        $totalSales = (float)$confirmedInvoices->sum('net_total');
        $cashSales = (float)$confirmedInvoices->where('payment_type', 'cash')->sum('paid_amount');
        $creditSales = (float)$confirmedInvoices->whereIn('payment_type', ['credit', 'partial'])->sum('remaining_amount');
        $partialSales = (float)$confirmedInvoices->where('payment_type', 'partial')->sum('paid_amount');

        // Customer Payments on selected date
        $customerPayments = (float)Payment::whereNotNull('customer_id')
            ->whereDate('payment_date', $date)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Supplier Payments on selected date
        $supplierPayments = (float)Payment::whereNotNull('supplier_id')
            ->whereDate('payment_date', $date)
            ->where('payment_method', 'cash')
            ->sum('amount');

        // Expenses on selected date
        $expenses = Expense::whereDate('expense_date', $date)
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->get();

        $totalExpenses = (float)$expenses->sum('amount');
        $cashExpenses = (float)$expenses->where('payment_method', 'cash')->sum('amount');

        // Net Cash calculations with bcmath
        $totalCashIn = bcadd(bcadd((string)$cashSales, (string)$partialSales, 3), (string)$customerPayments, 3);
        $totalCashOut = bcadd((string)$supplierPayments, (string)$cashExpenses, 3);
        $netCashToday = bcsub((string)$totalCashIn, (string)$totalOut = (string)$totalCashOut, 3);

        $openingCashBalance = $activeShift ? (string)$activeShift->opening_cash_balance : '0.000';
        $expectedCashInDrawer = bcadd($openingCashBalance, (string)$netCashToday, 3);

        return [
            'date'         => $date,
            'store_id'     => $storeId,
            'active_shift' => $activeShift ? [
                'id'                   => $activeShift->id,
                'shift_number'         => $activeShift->shift_number,
                'status'               => $activeShift->status,
                'opened_at'            => $activeShift->opened_at?->format('Y-m-d H:i'),
                'opening_cash_balance' => (float)$activeShift->opening_cash_balance,
                'user_name'            => $activeShift->user?->name,
            ] : null,
            'summary'      => [
                'total_sales'             => (float)$totalSales,
                'cash_sales'              => (float)bcadd((string)$cashSales, (string)$partialSales, 3),
                'credit_sales'            => (float)$creditSales,
                'customer_payments'       => (float)$customerPayments,
                'total_cash_in'           => (float)$totalCashIn,
                'supplier_payments'       => (float)$supplierPayments,
                'total_expenses'          => (float)$totalExpenses,
                'cash_expenses'           => (float)$cashExpenses,
                'total_cash_out'          => (float)$totalCashOut,
                'net_cash_today'          => (float)$netCashToday,
                'opening_cash_balance'    => (float)$openingCashBalance,
                'expected_cash_in_drawer' => (float)$expectedCashInDrawer,
            ],
            'invoices'     => $invoices->map(fn($inv) => [
                'id'               => $inv->id,
                'invoice_number'   => $inv->invoice_number,
                'customer_name'    => $inv->customer?->name ?: 'عميل نقدي سريع',
                'net_total'        => (float)$inv->net_total,
                'paid_amount'      => (float)$inv->paid_amount,
                'remaining_amount' => (float)$inv->remaining_amount,
                'payment_method'   => $inv->payment_type ?? $inv->payment_method,
                'status'           => $inv->status,
                'time'             => $inv->created_at?->format('H:i A'),
            ]),
            'expenses'     => $expenses->map(fn($e) => [
                'id'                => $e->id,
                'expense_number'    => $e->expense_number,
                'title'             => $e->title,
                'category'          => $e->category,
                'cost_center_label' => $e->cost_center_label,
                'amount'            => (float)$e->amount,
                'payment_method'    => $e->payment_method,
            ]),
        ];
    }
}
