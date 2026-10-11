<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CashShift;
use App\Models\User;
use App\Support\ActiveStore;

/**
 * STOR-2: abilities on a given shift also check the shift's store (branch): reading needs
 * ActiveStore::canRead, closing needs ActiveStore::canAccess. Controllers answer a store
 * denial as 404 (CTO Q13).
 */
final class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('pos.access');
    }

    public function view(User $user, ?CashShift $shift = null): bool
    {
        return ($user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('pos.access'))
            && ($shift === null || self::storeVisibleTo($user, $shift));
    }

    /** Q10: opening a shift is guarded by the seeded daily_journal.close_shift (same as the route). */
    public function open(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.close_shift');
    }

    public function close(User $user, ?CashShift $shift = null): bool
    {
        return ($user->hasRole('admin')
            || $user->can('daily_journal.close_shift'))
            && ($shift === null || self::canChangeStoreOf($user, $shift));
    }

    public function zReport(User $user, ?CashShift $shift = null): bool
    {
        return ($user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('pos.access'))
            && ($shift === null || self::storeVisibleTo($user, $shift));
    }

    /** Store access alone (no ability): lets controllers tell a store denial (404) from a missing permission (403). */
    public static function storeVisibleTo(User $user, CashShift $shift): bool
    {
        return ActiveStore::canRead($user, self::storeOf($shift));
    }

    /** N4: a shift without a store is never "store 0": only admins (Gate::before) may close it. */
    private static function canChangeStoreOf(User $user, CashShift $shift): bool
    {
        $storeId = self::storeOf($shift);

        return $storeId !== null && ActiveStore::canAccess($user, $storeId);
    }

    private static function storeOf(CashShift $shift): ?int
    {
        return $shift->store_id !== null ? (int) $shift->store_id : null;
    }
}
