<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Actions\Dashboard\GetOwnShiftDashboardAction;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\User;
use App\Services\DailyCashFlowService;
use App\Services\Reports\ReportDateRange;
use App\Services\ShiftService;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Support\Collection;

final class GetDailyJournalAction
{
    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly DailyCashFlowService $dailyCashFlow,
        private readonly ShiftService $shiftService,
    ) {}

    /**
     * Get Daily Journal ledger and cash metrics for a specific date and store.
     *
     * $date is a tenant-local business date (SETG-2); displayed times are on the tenant clock.
     *
     * ROLE-01: a $viewer WITHOUT `reports.view` (a cashier) gets `scope: own_shift`: the same keys,
     * but every list and total holds only the documents the viewer booked (`user_id`) on that
     * business date, and the drawer figures (`summary.opening_cash_balance`,
     * `summary.expected_cash_in_drawer`, `active_shift.opening_cash_balance`) are null. The store's
     * open shift is still described (id, number, status, opened_at, is_own) but the opener's name
     * only when it is the viewer. No $viewer (internal callers) or `reports.view`: `scope: store`,
     * unchanged figures.
     *
     * @return array<string, mixed>
     */
    public function execute(string $date, ?int $storeId = null, ?User $viewer = null): array
    {
        $storeId = $storeId ?: Store::getMainStore()?->id ?: Store::first()?->id;
        $ownerId = $viewer instanceof User && ! $viewer->can('reports.view') ? (int) $viewer->id : null;

        // Current Active Shift
        $activeShift = CashShift::with(['user', 'store'])
            ->where('status', 'open')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->first();

        // Invoices on selected date
        // PERF-01: [date, date + 1 day) on the column itself, so the date indexes are usable.
        $invoices = Invoice::with('customer')
            ->tap(fn ($q) => ReportDateRange::apply($q, 'invoice_date', $date, $date))
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($ownerId !== null, fn ($q) => $q->where('user_id', $ownerId))
            ->latest('id')
            ->get();

        $confirmedInvoices = $invoices->where('status', '!=', 'cancelled');
        $totalSales = $this->sumDecimal($confirmedInvoices->pluck('net_total'));
        $creditSales = $this->sumDecimal($confirmedInvoices->whereIn('payment_type', ['credit', 'partial'])->pluck('remaining_amount'));

        // BUG-01: drawer cash comes from the cash vouchers of the selected store only (shared
        // with the A4 print, dashboards and treasury summary through DailyCashFlowService).
        // ROLE-01: own scope = the viewer's own vouchers and expenses only.
        $cashFlow = $this->dailyCashFlow->forDate($date, $storeId !== null ? (int) $storeId : null, $ownerId);
        $cashSales = $cashFlow['cash_sales'];
        $customerPayments = $cashFlow['customer_receipts'];
        $supplierPayments = $cashFlow['supplier_paid'];
        $cashExpenses = $cashFlow['cash_expenses'];

        // Expenses on selected date (the list and the all-methods total)
        $expenses = Expense::query()
            ->tap(fn ($q) => ReportDateRange::apply($q, 'expense_date', $date, $date))
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($ownerId !== null, fn ($q) => $q->where('user_id', $ownerId))
            ->latest('id')
            ->get();

        $totalExpenses = $this->sumDecimal($expenses->pluck('amount'));

        $totalCashIn = $cashFlow['total_cash_in'];
        $totalCashOut = $cashFlow['total_cash_out'];
        $netCashToday = $cashFlow['net_cash'];

        // ROLE-01: the drawer (opening float, expected cash) is store-wide: null in own scope.
        $openingCashBalance = $activeShift ? Decimal::normalize((string) $activeShift->opening_cash_balance) : '0.000';
        $ownScope = $ownerId !== null;

        // BUG-03: the drawer holds what the OPEN shift expects (opening + every cash movement since
        // it opened, possibly days ago) - the very figure of GET /shifts/current and of the shift's
        // provisional Z report, whatever date is selected. net_cash_today stays the selected day's
        // movement only. No open shift = nothing is expected in a drawer: null, never a guess.
        $expectedCashInDrawer = ! $ownScope && $activeShift instanceof CashShift
            ? $this->shiftService->calculateShiftTotals($activeShift)['expected_cash_balance']
            : null;

        return [
            'scope' => $ownScope ? GetOwnShiftDashboardAction::SCOPE_OWN_SHIFT : GetOwnShiftDashboardAction::SCOPE_STORE,
            'date' => $date,
            'store_id' => $storeId,
            'active_shift' => $activeShift ? $this->activeShiftBlock($activeShift, $viewer, $ownScope) : null,
            'summary' => [
                'total_sales' => Decimal::normalize((string) $totalSales),
                'cash_sales' => Decimal::normalize((string) $cashSales),
                'credit_sales' => Decimal::normalize((string) $creditSales),
                'customer_payments' => Decimal::normalize((string) $customerPayments),
                'total_cash_in' => Decimal::normalize((string) $totalCashIn),
                'supplier_payments' => Decimal::normalize((string) $supplierPayments),
                'total_expenses' => Decimal::normalize((string) $totalExpenses),
                'cash_expenses' => Decimal::normalize((string) $cashExpenses),
                // BE-10: cash handed back to customers for sales returns (already inside total_cash_out).
                'cash_refunds' => Decimal::normalize((string) $cashFlow['cash_refunds']),
                'total_cash_out' => Decimal::normalize((string) $totalCashOut),
                'net_cash_today' => Decimal::normalize((string) $netCashToday),
                'opening_cash_balance' => $ownScope ? null : $openingCashBalance,
                'expected_cash_in_drawer' => $expectedCashInDrawer !== null ? Decimal::normalize($expectedCashInDrawer) : null,
            ],
            'invoices' => $invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name ?: __('pos.general_walkin_customer'),
                'net_total' => Decimal::normalize((string) $inv->net_total),
                'paid_amount' => Decimal::normalize((string) $inv->paid_amount),
                'remaining_amount' => Decimal::normalize((string) $inv->remaining_amount),
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
                'amount' => Decimal::normalize((string) $e->amount),
                'payment_method' => $e->payment_method,
            ]),
        ];
    }

    /**
     * The store's open shift. Own scope: no opening float, and the opener's name only when the
     * viewer opened it.
     *
     * @return array<string, mixed>
     */
    private function activeShiftBlock(CashShift $shift, ?User $viewer, bool $ownScope): array
    {
        $isOwn = $viewer instanceof User && $shift->user_id !== null && (int) $shift->user_id === (int) $viewer->id;

        return [
            'id' => $shift->id,
            'shift_number' => $shift->shift_number,
            'status' => $shift->status,
            'opened_at' => $this->tenantClock->toTenant($shift->opened_at)?->format('Y-m-d H:i'),
            'opening_cash_balance' => $ownScope ? null : Decimal::normalize((string) $shift->opening_cash_balance),
            'user_name' => $ownScope && ! $isOwn ? null : $shift->user?->name,
            'is_own' => $isOwn,
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
