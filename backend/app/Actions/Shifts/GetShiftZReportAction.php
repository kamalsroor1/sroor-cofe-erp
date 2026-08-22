<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;

final class GetShiftZReportAction
{
    /**
     * Build thermal Z-Report data breakdown for closed/current shift
     */
    public function execute(int $shiftId): array
    {
        $shift = CashShift::with(['user', 'store'])->findOrFail($shiftId);

        return [
            'id'                       => $shift->id,
            'shift_number'             => $shift->shift_number,
            'status'                   => $shift->status,
            'store_name'               => $shift->store?->name ?? 'الفرع الرئيسي',
            'cashier_name'             => $shift->user?->name ?? 'الكاشير',
            'opened_at'                => $shift->opened_at?->format('Y-m-d H:i:s'),
            'closed_at'                => $shift->closed_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            'opening_cash_balance'     => (float)$shift->opening_cash_balance,
            'total_cash_sales'         => (float)$shift->total_cash_sales,
            'total_credit_sales'       => (float)$shift->total_credit_sales,
            'total_payments_collected' => (float)$shift->total_payments_collected,
            'total_expenses'           => (float)($shift->total_expenses ?? 0),
            'total_refunds'            => (float)$shift->total_refunds,
            'expected_cash_balance'    => (float)$shift->expected_cash_balance,
            'actual_cash_balance'      => (float)$shift->actual_cash_balance,
            'cash_difference'          => (float)$shift->cash_difference,
            'notes'                    => $shift->notes,
        ];
    }
}
