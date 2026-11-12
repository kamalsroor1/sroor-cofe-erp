<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;
use App\Services\ShiftService;
use App\Support\Money\Decimal;
use App\Support\TenantClock;

final class GetShiftZReportAction
{
    /** The drawer figures of the report, in print order. */
    private const FIGURES = [
        'opening_cash_balance',
        'total_cash_sales',
        'total_credit_sales',
        'total_payments_collected',
        'total_expenses',
        'total_supplier_paid',
        'total_refunds',
        'expected_cash_balance',
    ];

    public function __construct(
        private readonly TenantClock $tenantClock,
        private readonly ShiftService $shiftService,
    ) {}

    /**
     * Thermal Z-Report data of a shift. Times are rendered on the tenant clock (SETG-2).
     *
     * BUG-03: an OPEN shift has no stored totals yet (they are written by closeShift()), so its
     * report is a provisional X-report computed live with ShiftService::calculateShiftTotals() -
     * the same figures as GET /shifts/current - with no closing time and nothing counted yet
     * (actual_cash_balance / cash_difference are null). A CLOSED shift reads the signed-off
     * stored columns.
     *
     * total_customer_receipts is not stored: for a closed shift it is derived from the stored
     * lines (total_payments_collected = cash sales + customer receipts, see calculateShiftTotals).
     *
     * @return array<string, mixed>
     */
    public function execute(int $shiftId): array
    {
        $shift = CashShift::with(['user', 'store'])->findOrFail($shiftId);
        $isProvisional = $shift->status === 'open';

        $figures = $isProvisional ? $this->liveFigures($shift) : $this->storedFigures($shift);

        return [
            'id' => $shift->id,
            'shift_number' => $shift->shift_number,
            'status' => $shift->status,
            'is_provisional' => $isProvisional,
            'store_name' => $shift->store?->name ?? __('common.main_branch'),
            'cashier_name' => $shift->user?->name ?? __('common.cashier'),
            'opened_at' => $this->tenantClock->toTenant($shift->opened_at)?->format('Y-m-d H:i:s'),
            'closed_at' => $isProvisional ? null : $this->tenantClock->toTenant($shift->closed_at)?->format('Y-m-d H:i:s'),
            // When the figures were taken: now for a provisional report, the close for a closed one.
            'generated_at' => ($isProvisional ? $this->tenantClock->now() : ($this->tenantClock->toTenant($shift->closed_at) ?? $this->tenantClock->now()))->format('Y-m-d H:i:s'),
            ...$figures,
            'actual_cash_balance' => $isProvisional ? null : Decimal::normalize((string) $shift->actual_cash_balance),
            'cash_difference' => $isProvisional ? null : Decimal::normalize((string) $shift->cash_difference),
            'notes' => $shift->notes,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function liveFigures(CashShift $shift): array
    {
        $totals = $this->shiftService->calculateShiftTotals($shift);

        $figures = [];
        foreach (self::FIGURES as $key) {
            $figures[$key] = Decimal::normalize((string) $totals[$key]);
        }
        $figures['total_customer_receipts'] = Decimal::normalize((string) $totals['total_customer_receipts']);

        return $figures;
    }

    /**
     * @return array<string, string>
     */
    private function storedFigures(CashShift $shift): array
    {
        $figures = [];
        foreach (self::FIGURES as $key) {
            $figures[$key] = Decimal::normalize((string) ($shift->{$key} ?? '0'));
        }
        $figures['total_customer_receipts'] = bcsub($figures['total_payments_collected'], $figures['total_cash_sales'], 3);

        return $figures;
    }
}
