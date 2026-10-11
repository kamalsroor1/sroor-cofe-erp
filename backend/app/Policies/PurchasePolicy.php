<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;
use App\Support\ActiveStore;

/**
 * STOR-2: abilities on a given purchase also check its store (branch). Reading needs
 * ActiveStore::canRead (store access or `stores.view_all`); cancelling (which moves the store's
 * stock and the supplier balance) needs ActiveStore::canAccess. Controllers answer a store
 * denial as 404 (CTO Q13), so a branch cannot probe other branches' ids.
 */
final class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.view');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return ($user->hasRole('admin')
            || $user->can('purchases.view'))
            && self::storeVisibleTo($user, $purchase);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.create');
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return ($user->hasRole('admin')
            || $user->can('purchases.delete'))
            && self::canChangeStoreOf($user, $purchase);
    }

    public function reorder(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.view');
    }

    /** Store access alone (no ability): lets controllers tell a store denial (404) from a missing permission (403). */
    public static function storeVisibleTo(User $user, Purchase $purchase): bool
    {
        return ActiveStore::canRead($user, self::storeOf($purchase));
    }

    private static function canChangeStoreOf(User $user, Purchase $purchase): bool
    {
        $storeId = self::storeOf($purchase);

        return $storeId !== null && ActiveStore::canAccess($user, $storeId);
    }

    private static function storeOf(Purchase $purchase): ?int
    {
        return $purchase->store_id !== null ? (int) $purchase->store_id : null;
    }
}
