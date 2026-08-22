<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;
use App\Services\ShiftService;

final class GetActiveShiftAction
{
    public function __construct(
        private readonly ShiftService $shiftService
    ) {}

    /**
     * Get active shift and calculate live metrics
     */
    public function execute(?int $storeId = null, ?int $userId = null): ?array
    {
        $shift = CashShift::with(['user', 'store'])
            ->where('status', 'open')
            ->when($storeId, fn($q) => $q->where('store_id', $storeId))
            ->when($userId, fn($q) => $q->where('user_id', $userId))
            ->latest('id')
            ->first();

        if (!$shift) {
            return null;
        }

        $metrics = $this->shiftService->calculateShiftTotals($shift);

        return [
            'shift'   => $shift,
            'metrics' => $metrics,
        ];
    }
}
