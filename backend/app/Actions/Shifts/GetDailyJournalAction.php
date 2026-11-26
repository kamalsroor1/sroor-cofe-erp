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
use App\Services\Shifts\BranchDrawers;
use App\Support\Money\Decimal;
use App\Support\TenantClock;
use Illuminate\Support\Collection;

final class GetDailyJournalAction
{
    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly DailyCashFlowService $dailyCashFlow,
        private readonly BranchDrawers $drawers,
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
     * SHIFT-02: the drawer figures are summed over every open shift of the branch (one per cashier
     * in a per-cashier branch); `open_shifts` lists them (own scope: the count and the viewer's own
     * shift, without money); `summary.outside_shifts_*` is the cash moved by writers without an
     * open shift (main treasury, in no Z).
     *
     * @return array<string, mixed>
     */
    public function execute(string $date, ?int $storeId = null, ?User $viewer = null): array
    {
        $storeId = $storeId ?: Store::getMainStore()?->id ?: Store::first()?->id;
        $ownerId = $viewer instanceof User && ! $viewer->can('reports.view') ? (int) $viewer->id : null;

        // Current Active Shift. SHIFT-02: a per-cashier branch has one open shift per cashier: the
        // block describes the viewer's own one when they have it, else the newest (a shared branch
        // has only one). The branch's drawer figures below are summed over every open shift.
        $openShifts = fn () => CashShift::with(['user', 'store'])
            ->where('status', 'open')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id');
        $activeShift = ($viewer instanceof User ? $openShifts()->where('user_id', $viewer->id)->first() : null)
            ?? $openShifts()->first();

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
        $ownScope = $ownerId !== null;
        $storeKey = $storeId !== null ? (int) $storeId : null;
        // DATE-03 / SHIFT-02: the selected day's opening float, summed over the drawers (one per
        // cashier in a per-cashier branch; the branch's one shift in a shared branch).
        $openingCashBalance = $ownScope ? null : $this->drawers->openingCashFor($date, $storeKey);

        // BUG-03: the drawer holds what the OPEN shift expects (opening + every cash movement since
        // it opened, possibly days ago) - the very figure of GET /shifts/current and of the shift's
        // provisional Z report, whatever date is selected. net_cash_today stays the selected day's
        // movement only. No open shift = nothing is expected in a drawer: null, never a guess.
        // SHIFT-02: summed over every open shift of the branch (each cashier's drawer).
        $drawers = $ownScope ? null : $this->drawers->summary($storeKey);
        $expectedCashInDrawer = $drawers['expected_cash_balance'] ?? null;

        // SHIFT-02: cash paid / received while the writer had no open shift (an admin's expense
        // in a per-cashier branch): in the treasury, in no drawer and no Z.
        $outside = $this->dailyCashFlow->forDate($date, $storeKey, $ownerId, outsideShiftsOnly: true);

        return [
            'scope' => $ownScope ? GetOwnShiftDashboardAction::SCOPE_OWN_SHIFT : GetOwnShiftDashboardAction::SCOPE_STORE,
            'date' => $date,
            'store_id' => $storeId,
            'active_shift' => $activeShift ? $this->activeShiftBlock($activeShift, $viewer, $ownScope) : null,
            'summary' => [
                // #220: the day's sales invoices without the cancelled ones (they stay in `invoices`).
                'sales_invoices_count' => $confirmedInvoices->count(),
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
                'opening_cash_balance' => $openingCashBalance,
                'expected_cash_in_drawer' => $expectedCashInDrawer !== null ? Decimal::normalize($expectedCashInDrawer) : null,
                // SHIFT-02: the part of the day's cash movement that belongs to no shift (already
                // inside total_cash_in / total_cash_out above).
                'outside_shifts_cash_in' => $outside['total_cash_in'],
                'outside_shifts_cash_out' => $outside['total_cash_out'],
                'outside_shifts_net_cash' => $outside['net_cash'],
            ],
            // SHIFT-02: every open shift of the branch (a per-cashier branch has one per cashier).
            // Own scope: the count and the viewer's own shift only, without money.
            'open_shifts' => $this->openShiftsBlock($drawers, $storeKey, $viewer, $ownScope),
            'invoices' => $invoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'customer_name' => $inv->customer?->name ?: __('pos.general_walkin_customer'),
                'net_total' => Decimal::normalize((string) $inv->net_total),
                'paid_amount' => Decimal::normalize((string) $inv->paid_amount),
                'remaining_amount' => Decimal::normalize((string) $inv->remaining_amount),
                'payment_method' => $inv->payment_type ?? $inv->payment_method,
                'status' => $inv->status,
                'is_cancelled' => $inv->status === 'cancelled',
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
     * SHIFT-02: the branch's open shifts (BranchDrawers::summary()). Own scope: the count and the
     * viewer's own open shift only, every money figure null.
     *
     * @param  array{count: int, opening_cash_balance: string, expected_cash_balance: string|null, shifts: list<array<string, mixed>>}|null  $drawers
     * @return array<string, mixed>
     */
    private function openShiftsBlock(?array $drawers, ?int $storeId, ?User $viewer, bool $ownScope): array
    {
        if (! $ownScope && $drawers !== null) {
            return $drawers;
        }

        $open = BranchDrawers::open($storeId);
        $own = $viewer instanceof User
            ? $open->filter(fn (CashShift $s): bool => $s->user_id !== null && (int) $s->user_id === (int) $viewer->id)
            : collect();

        return [
            'count' => $open->count(),
            'opening_cash_balance' => null,
            'expected_cash_balance' => null,
            'shifts' => $own->map(fn (CashShift $s): array => [
                'id' => (int) $s->id,
                'shift_number' => (string) $s->shift_number,
                'shift_mode' => (string) $s->shift_mode,
                'store_id' => $s->store_id !== null ? (int) $s->store_id : null,
                'user_id' => (int) $s->user_id,
                'user_name' => $viewer?->name,
                'opened_at' => $this->tenantClock->toTenant($s->opened_at)?->format('Y-m-d H:i'),
                'opening_cash_balance' => null,
                'expected_cash_balance' => null,
            ])->values()->all(),
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
