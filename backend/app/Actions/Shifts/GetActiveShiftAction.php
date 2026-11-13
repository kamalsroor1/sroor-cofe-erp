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
     * get the figures.
     *
     * DEC-SHIFT blind count: `blind_count` is true for any $viewer without `reports.view` (the
     * opener included): the expected cash of the open shift is withheld (metrics
     * `expected_cash_balance` null), so the drawer is counted before the target is known. It is
     * shown after the close, in the closed shift's Z report. Admin holds reports.view.
     *
     * @return array{shift: CashShift, metrics: array<string, string|null>, cash_hidden: bool, blind_count: bool}|null
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

        $blindCount = $viewer instanceof User && ! $viewer->can('reports.view');
        $cashHidden = $blindCount && (int) $shift->user_id !== (int) $viewer->id;

        $metrics = $this->shiftService->calculateShiftTotals($shift);
        if ($cashHidden) {
            $metrics = array_map(fn (): ?string => null, $metrics);
        } elseif ($blindCount) {
            $metrics['expected_cash_balance'] = null;
        }

        return [
            'shift' => $shift,
            'metrics' => $metrics,
            'cash_hidden' => $cashHidden,
            'blind_count' => $blindCount,
        ];
    }
}
