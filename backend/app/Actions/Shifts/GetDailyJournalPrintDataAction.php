<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Store;
use App\Services\DailyCashFlowService;
use App\Services\ShiftService;
use App\Support\Money\Decimal;
use Illuminate\Support\Collection;

/**
 * Data of the daily journal A4 print (`daily.journal.print`).
 *
 * BUG-01: the drawer figures come from DailyCashFlowService (cash vouchers of the store only),
 * exactly like the on-screen daily journal. The old route closure summed invoices.total_amount
 * (a column that does not exist: every sales figure printed 0), added the cash sale's own
 * voucher to the invoice's paid amount and took customer/supplier payments of every store.
 *
 * $storeId is the access-checked filter of ReportStoreFilter; null = every store (admins only).
 */
final class GetDailyJournalPrintDataAction
{
    public function __construct(
        private readonly DailyCashFlowService $dailyCashFlow,
        private readonly ShiftService $shiftService,
    ) {}

    /**
     * @return array<string, mixed> the variables of layouts.print-daily-journal-a4
     */
    public function execute(string $date, ?int $storeId): array
    {
        $storeName = __('common.all_stores');
        if ($storeId !== null) {
            $store = Store::query()->find($storeId);
            if ($store instanceof Store) {
                $storeName = $store->name;
            }
        }

        $invoices = Invoice::with(['customer', 'store'])
            ->whereDate('invoice_date', $date)
            ->where('status', 'confirmed')
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->get();

        $expenses = Expense::with('store')
            ->whereDate('expense_date', $date)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->get();

        $shiftsOnDate = CashShift::with(['user', 'store'])
            ->whereDate('opened_at', $date)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->latest('id')
            ->get();

        $cashFlow = $this->dailyCashFlow->forDate($date, $storeId);

        // Opening float of the latest shift opened that day (unchanged print semantics).
        $latestShift = $shiftsOnDate->first();
        $openingCashBalance = $latestShift instanceof CashShift ? Decimal::normalize((string) $latestShift->opening_cash_balance) : '0.000';

        return [
            'date' => $date,
            'storeName' => $storeName,
            'invoices' => $invoices,
            'invoicesCount' => $invoices->count(),
            'totalSales' => $this->sum($invoices->pluck('net_total')),
            'totalPaid' => $this->sum($invoices->pluck('paid_amount')),
            // Cash actually received at the sale (cash vouchers), not the invoices' paid amounts.
            'cashSales' => $cashFlow['cash_sales'],
            // Sold on account: the unpaid remainder of credit and partial invoices.
            'creditSales' => $this->sum($invoices->whereIn('payment_type', ['credit', 'partial'])->pluck('remaining_amount')),
            'customerPayments' => $cashFlow['customer_receipts'],
            'totalCashCollected' => $cashFlow['total_cash_in'],
            'expenses' => $expenses,
            'totalExpenses' => $this->sum($expenses->pluck('amount')),
            'totalSupplierPaid' => $cashFlow['supplier_paid'],
            'totalCashRefunds' => $cashFlow['cash_refunds'],
            'netCashToday' => $cashFlow['net_cash'],
            'openingCashBalance' => $openingCashBalance,
            // BUG-03: what the open shift(s) expect in the drawer, as on screen; null = no open shift.
            'expectedCashInDrawer' => $this->openShiftsExpectedCash($storeId),
            'shiftsOnDate' => $shiftsOnDate,
        ];
    }

    /**
     * Expected drawer cash of the open shift of the store (ShiftService::calculateShiftTotals(),
     * the figure of GET /shifts/current), or - for every store - the sum over the latest open
     * shift of each store. Null when no shift is open.
     */
    private function openShiftsExpectedCash(?int $storeId): ?string
    {
        $shifts = CashShift::query()
            ->where('status', 'open')
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->orderBy('id')
            ->get()
            ->keyBy(fn (CashShift $shift): string => (string) $shift->store_id); // later (higher id) wins

        if ($shifts->isEmpty()) {
            return null;
        }

        $total = '0.000';
        foreach ($shifts as $shift) {
            $total = bcadd($total, $this->shiftService->calculateShiftTotals($shift)['expected_cash_balance'], 3);
        }

        return $total;
    }

    /**
     * @param  Collection<array-key, mixed>  $values
     */
    private function sum(Collection $values): string
    {
        return $values->reduce(
            fn (string $carry, mixed $value): string => bcadd($carry, Decimal::normalize(is_numeric($value) ? (string) $value : '0'), 3),
            '0.000',
        );
    }
}
