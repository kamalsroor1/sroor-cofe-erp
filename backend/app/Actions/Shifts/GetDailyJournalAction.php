<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Store;
use App\Services\DailyCashFlowService;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Support\Collection;

final class GetDailyJournalAction
{
    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly DailyCashFlowService $dailyCashFlow,
    ) {}

    /**
     * Get Daily Journal ledger and cash metrics for a specific date and store.
     *
     * $date is a tenant-local business date (SETG-2); displayed times are on the tenant clock.
     */
    public function execute(string $date, ?int $storeId = null): array
    {
        $storeId = $storeId ?: Store::getMainStore()?->id ?: Store::first()?->id;

        // Current Active Shift
        $activeShift = CashShift::with(['user', 'store'])
            ->where('status', 'open')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->first();

        // Invoices on selected date
        $invoices = Invoice::with('customer')
            ->whereDate('invoice_date', $date)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->get();

        $confirmedInvoices = $invoices->where('status', '!=', 'cancelled');
        $totalSales = $this->sumDecimal($confirmedInvoices->pluck('net_total'));
        $creditSales = $this->sumDecimal($confirmedInvoices->whereIn('payment_type', ['credit', 'partial'])->pluck('remaining_amount'));

        // BUG-01: drawer cash comes from the cash vouchers of the selected store only (shared
        // with the A4 print, dashboards and treasury summary through DailyCashFlowService).
        $cashFlow = $this->dailyCashFlow->forDate($date, $storeId !== null ? (int) $storeId : null);
        $cashSales = $cashFlow['cash_sales'];
        $customerPayments = $cashFlow['customer_receipts'];
        $supplierPayments = $cashFlow['supplier_paid'];
        $cashExpenses = $cashFlow['cash_expenses'];

        // Expenses on selected date (the list and the all-methods total)
        $expenses = Expense::whereDate('expense_date', $date)
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->get();

        $totalExpenses = $this->sumDecimal($expenses->pluck('amount'));

        $totalCashIn = $cashFlow['total_cash_in'];
        $totalCashOut = $cashFlow['total_cash_out'];
        $netCashToday = $cashFlow['net_cash'];

        $openingCashBalance = $activeShift ? (string) $activeShift->opening_cash_balance : '0.000';
        $expectedCashInDrawer = bcadd($openingCashBalance, (string) $netCashToday, 3);

        return [
            'date' => $date,
            'store_id' => $storeId,
            'active_shift' => $activeShift ? [
                'id' => $activeShift->id,
                'shift_number' => $activeShift->shift_number,
                'status' => $activeShift->status,
                'opened_at' => $this->tenantClock->toTenant($activeShift->opened_at)?->format('Y-m-d H:i'),
                'opening_cash_balance' => (float) $activeShift->opening_cash_balance,
                'user_name' => $activeShift->user?->name,
            ] : null,
            'summary' => [
                'total_sales' => (float) $totalSales,
                'cash_sales' => (float) $cashSales,
                'credit_sales' => (float) $creditSales,
                'customer_payments' => (float) $customerPayments,
                'total_cash_in' => (float) $totalCashIn,
                'supplier_payments' => (float) $supplierPayments,
                'total_expenses' => (float) $totalExpenses,
                'cash_expenses' => (float) $cashExpenses,
                'total_cash_out' => (float) $totalCashOut,
                'net_cash_today' => (float) $netCashToday,
                'opening_cash_balance' => (float) $openingCashBalance,
                'expected_cash_in_drawer' => (float) $expectedCashInDrawer,
            ],
            'invoices' => $invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name ?: __('pos.general_walkin_customer'),
                'net_total' => (float) $inv->net_total,
                'paid_amount' => (float) $inv->paid_amount,
                'remaining_amount' => (float) $inv->remaining_amount,
                'payment_method' => $inv->payment_type ?? $inv->payment_method,
                'status' => $inv->status,
                'time' => $this->tenantClock->toTenant($inv->created_at)?->format('H:i A'),
            ]),
            'expenses' => $expenses->map(fn ($e) => [
                'id' => $e->id,
                'expense_number' => $e->expense_number,
                'title' => $e->title,
                'category' => $e->category,
                'cost_center_label' => $e->cost_center_label,
                'amount' => (float) $e->amount,
                'payment_method' => $e->payment_method,
            ]),
        ];
    }

    /**
     * Exact scale-3 sum of DECIMAL(12,3) values (bcmath, never float).
     *
     * @param  Collection<array-key, mixed>  $values
     */
    private function sumDecimal(Collection $values): string
    {
        return $values->reduce(
            fn (string $carry, mixed $value): string => bcadd($carry, Decimal::normalize(is_numeric($value) ? (string) $value : '0'), 3),
            '0.000',
        );
    }
}
