<?php

declare(strict_types=1);

namespace App\Actions\Shifts;

use App\Models\CashShift;
use App\Models\User;
use App\Services\ShiftService;

final class GetActiveShiftAction
{
    public function __construct(
        private readonly ShiftService $shiftService
    ) {}

    /**
     * Get active shift and calculate live metrics
     *
     * ROLE-01: `cash_hidden` is true when $viewer lacks `reports.view` and did not open the shift
     * (one shared drawer per store): every metric is then null (keys kept) and the caller renders
     * the shift without its cash figures. The opener of the shift, and any reports.view reader,
     * get the figures unchanged (the opener needs them to close the shift).
     *
     * @return array{shift: CashShift, metrics: array<string, string|null>, cash_hidden: bool}|null
     */
    public function execute(?int $storeId = null, ?int $userId = null, ?User $viewer = null): ?array
    {
        $shift = CashShift::with(['user', 'store'])
            ->where('status', 'open')
            ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->latest('id')
            ->first();

        if (! $shift) {
            return null;
        }

        $cashHidden = $viewer instanceof User
            && ! $viewer->can('reports.view')
            && (int) $shift->user_id !== (int) $viewer->id;

        $metrics = $this->shiftService->calculateShiftTotals($shift);
        if ($cashHidden) {
            $metrics = array_map(fn (): ?string => null, $metrics);
        }

        return [
            'shift' => $shift,
            'metrics' => $metrics,
            'cash_hidden' => $cashHidden,
        ];
    }
}
