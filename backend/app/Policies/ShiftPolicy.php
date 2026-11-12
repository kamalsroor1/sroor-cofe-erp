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

    /**
     * BUG-03 + ROLE-01: allowed when the shift's store is readable (ActiveStore::canRead) AND one of:
     *  - the user is admin, or
     *  - the user holds reports.view: they read EVERY shift of that store (the same rule that
     *    decides who sees another user's cash figures on /shifts, /shifts/current and the
     *    journal), or
     *  - the shift's user_id is the user (own shift; a null user_id is never "own").
     * So a cashier (no reports.view) reads only the report of the shift they opened; another
     * cashier's shift is answered 404 by the controller (owner decision). Without a shift
     * (route-level check) the permission alone decides, as before.
     */
    public function zReport(User $user, ?CashShift $shift = null): bool
    {
        if ($shift === null) {
            return $user->hasRole('admin')
                || $user->can('daily_journal.view')
                || $user->can('pos.access');
        }

        if (! self::storeVisibleTo($user, $shift)) {
            return false;
        }

        return $user->hasRole('admin')
            || $user->can('reports.view')
            || ($shift->user_id !== null && (int) $shift->user_id === (int) $user->getKey());
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
