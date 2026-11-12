<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;
use App\Services\ShiftService;
use App\Support\Database\TransactionRetry;
use App\Support\Money\Decimal;

/**
 * BUG-06 backfill for one CLOSED shift: recomputes its cash expenses and cash supplier payments
 * on the shift's own window [opened_at, closed_at] (ShiftService::calculateShiftTotals, the same
 * math the close uses) and stores those two columns only.
 *
 * Every other stored figure (cash sales, collected, refunds, expected, actual, difference) is the
 * record the cashier signed off and is never touched. When the Z report lines no longer add up to
 * the stored expected balance (data edited after the close), the two components are still stored
 * and the shift is reported as inconsistent; expected is not "fixed".
 */
final class RecalculateClosedShiftTotalsAction
{
    public function __construct(
        private readonly ShiftService $shiftService,
    ) {}

    /**
     * Null when the shift is missing or not closed (any more).
     *
     * @return array{id: int, shift_number: string, changed: bool, consistent: bool, total_expenses: string, total_supplier_paid: string, lines_total: string, expected_cash_balance: string}|null
     */
    public function execute(int $shiftId, bool $dryRun = false): ?array
    {
        if ($dryRun) {
            $shift = CashShift::withTrashed()->whereKey($shiftId)->first();

            return $shift instanceof CashShift ? $this->recalculate($shift, false) : null;
        }

        return TransactionRetry::run(function () use ($shiftId): ?array {
            $shift = CashShift::withTrashed()->whereKey($shiftId)->lockForUpdate()->first();

            return $shift instanceof CashShift ? $this->recalculate($shift, true) : null;
        });
    }

    /**
     * @return array{id: int, shift_number: string, changed: bool, consistent: bool, total_expenses: string, total_supplier_paid: string, lines_total: string, expected_cash_balance: string}|null
     */
    private function recalculate(CashShift $shift, bool $write): ?array
    {
        if ($shift->status !== 'closed' || $shift->opened_at === null || $shift->closed_at === null) {
            return null;
        }

        $totals = $this->shiftService->calculateShiftTotals($shift);
        $expenses = $totals['total_expenses'];
        $supplierPaid = $totals['total_supplier_paid'];

        $changed = bccomp($expenses, Decimal::normalize((string) $shift->total_expenses), 3) !== 0
            || bccomp($supplierPaid, Decimal::normalize((string) $shift->total_supplier_paid), 3) !== 0;

        // The Z report: opening + collected - expenses - supplier payments - refunds.
        $lines = bcadd(Decimal::normalize((string) $shift->opening_cash_balance), Decimal::normalize((string) $shift->total_payments_collected), 3);
        $lines = bcsub($lines, $expenses, 3);
        $lines = bcsub($lines, $supplierPaid, 3);
        $lines = bcsub($lines, Decimal::normalize((string) $shift->total_refunds), 3);
        $expected = Decimal::normalize((string) $shift->expected_cash_balance);

        if ($write && $changed) {
            $shift->update([
                'total_expenses' => $expenses,
                'total_supplier_paid' => $supplierPaid,
            ]);
        }

        return [
            'id' => (int) $shift->id,
            'shift_number' => (string) $shift->shift_number,
            'changed' => $changed,
            'consistent' => bccomp($lines, $expected, 3) === 0,
            'total_expenses' => $expenses,
            'total_supplier_paid' => $supplierPaid,
            'lines_total' => $lines,
            'expected_cash_balance' => $expected,
        ];
    }
}
