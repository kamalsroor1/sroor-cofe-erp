<?php

declare(strict_types=1);

namespace App\Services\Shifts;

use App\Enums\ShiftMode;
use App\Models\CashShift;
use App\Models\StorePosSetting;
use Illuminate\Database\Eloquent\Builder;

/**
 * SHIFT-01: the one place that knows which open shift a user works in, for both branch shift
 * modes (store_pos_settings.shift_mode):
 *
 *  - shared: the branch's open shift, whoever opened it;
 *  - per_cashier: the open shift THIS user opened in the branch (a null user - a job or a
 *    command - matches the branch's open shift without an opener).
 *
 * Used by ShiftService (open: one per key), SaleShiftGuard (the POS-10
 * sale requirement) and the readers that show "the current shift" (/shifts/current, /me, system
 * context, POS bootstrap).
 */
final class OpenShiftResolver
{
    public function modeFor(int $storeId): ShiftMode
    {
        return StorePosSetting::forStore($storeId)->shiftMode();
    }

    /**
     * Restricts $query (on cash_shifts) to the open shifts that count as "the user's shift" in the
     * branch for $mode.
     *
     * @param  Builder<CashShift>  $query
     * @return Builder<CashShift>
     */
    public function scopeOpen(Builder $query, int $storeId, ?int $userId, ShiftMode $mode): Builder
    {
        $query->where('cash_shifts.store_id', $storeId)->where('cash_shifts.status', 'open');

        if ($mode->isPerCashier()) {
            $userId === null
                ? $query->whereNull('cash_shifts.user_id')
                : $query->where('cash_shifts.user_id', $userId);
        }

        return $query;
    }

    /**
     * The user's current open shift in the branch (newest first), or null.
     *
     * @param  list<string>  $with
     */
    public function currentFor(int $storeId, ?int $userId, array $with = []): ?CashShift
    {
        return $this->scopeOpen(CashShift::query()->with($with), $storeId, $userId, $this->modeFor($storeId))
            ->latest('id')
            ->first();
    }

    public function hasOpenFor(int $storeId, ?int $userId): bool
    {
        return $this->scopeOpen(CashShift::query(), $storeId, $userId, $this->modeFor($storeId))->exists();
    }

    /**
     * The value of cash_shifts.open_guard (UNIQUE) while a shift is open: one key per branch in
     * shared mode, one per (branch, user) in per_cashier mode. A store-less shift uses store 0 and
     * a shift without an opener user 0. NULL once the shift is closed or deleted (CashShift hooks).
     */
    public static function guardKey(ShiftMode $mode, ?int $storeId, ?int $userId): string
    {
        $key = 'store:'.(int) ($storeId ?? 0);

        return $mode->isPerCashier() ? $key.':user:'.(int) ($userId ?? 0) : $key;
    }
}
