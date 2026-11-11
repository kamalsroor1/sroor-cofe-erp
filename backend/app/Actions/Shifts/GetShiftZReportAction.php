<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;
use App\Support\Money\Decimal;
use App\Support\TenantClock;

final class GetShiftZReportAction
{
    public function __construct(
        private readonly TenantClock $tenantClock,
    ) {}

    /**
     * Build thermal Z-Report data breakdown for closed/current shift.
     * Times are rendered on the tenant clock (SETG-2); storage is unchanged.
     */
    public function execute(int $shiftId): array
    {
        $shift = CashShift::with(['user', 'store'])->findOrFail($shiftId);

        return [
            'id' => $shift->id,
            'shift_number' => $shift->shift_number,
            'status' => $shift->status,
            'store_name' => $shift->store?->name ?? 'الفرع الرئيسي',
            'cashier_name' => $shift->user?->name ?? 'الكاشير',
            'opened_at' => $this->tenantClock->toTenant($shift->opened_at)?->format('Y-m-d H:i:s'),
            'closed_at' => ($this->tenantClock->toTenant($shift->closed_at) ?? $this->tenantClock->now())->format('Y-m-d H:i:s'),
            'opening_cash_balance' => Decimal::normalize((string) $shift->opening_cash_balance),
            'total_cash_sales' => Decimal::normalize((string) $shift->total_cash_sales),
            'total_credit_sales' => Decimal::normalize((string) $shift->total_credit_sales),
            'total_payments_collected' => Decimal::normalize((string) $shift->total_payments_collected),
            'total_expenses' => Decimal::normalize((string) ($shift->total_expenses ?? 0)),
            'total_refunds' => Decimal::normalize((string) $shift->total_refunds),
            'expected_cash_balance' => Decimal::normalize((string) $shift->expected_cash_balance),
            'actual_cash_balance' => Decimal::normalize((string) $shift->actual_cash_balance),
            'cash_difference' => Decimal::normalize((string) $shift->cash_difference),
            'notes' => $shift->notes,
        ];
    }
}
