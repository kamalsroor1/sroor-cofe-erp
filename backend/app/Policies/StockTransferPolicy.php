<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use App\Support\ActiveStore;

/**
 * STOR-2: abilities on a given transfer also check its stores. Reading needs one readable side
 * (source or destination); cancelling (which moves stock in both stores) needs the same access
 * as creating it: the source store, and the destination unless the user may see every store.
 * Controllers answer a store denial as 404 (CTO Q13).
 */
final class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage')
            || $user->can('transfers.view');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return ($user->hasRole('admin')
            || $user->can('stores.manage')
            || $user->can('transfers.view'))
            && self::storeVisibleTo($user, $transfer);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage')
            || $user->can('transfers.create');
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return ($user->hasRole('admin')
            || $user->can('stores.manage'))
            && ActiveStore::canAccess($user, (int) $transfer->from_store_id)
            && (ActiveStore::canAccess($user, (int) $transfer->to_store_id) || ActiveStore::canViewAll($user));
    }

    /** Store access alone (no ability): lets controllers tell a store denial (404) from a missing permission (403). */
    public static function storeVisibleTo(User $user, StockTransfer $transfer): bool
    {
        return ActiveStore::canRead($user, (int) $transfer->from_store_id)
            || ActiveStore::canRead($user, (int) $transfer->to_store_id);
    }
}
