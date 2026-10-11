<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReturnDocument;
use App\Models\User;
use App\Support\ActiveStore;

/**
 * STOR-2: abilities on a given return document also check its store (branch). Reading needs
 * ActiveStore::canRead (store access or `stores.view_all`); deleting needs ActiveStore::canAccess.
 * Controllers answer a store denial as 404 (CTO Q13), so a branch cannot probe other branches' ids.
 *
 * Bound to ReturnDocument through #[UsePolicy] (the model name does not match this class).
 */
final class ReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('returns.manage');
    }

    public function view(User $user, ReturnDocument $returnDocument): bool
    {
        return ($user->hasRole('admin')
            || $user->can('returns.manage'))
            && self::storeVisibleTo($user, $returnDocument);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('returns.manage');
    }

    public function delete(User $user, ReturnDocument $returnDocument): bool
    {
        return ($user->hasRole('admin')
            || $user->can('returns.manage'))
            && self::canChangeStoreOf($user, $returnDocument);
    }

    /** Store access alone (no ability): lets controllers tell a store denial (404) from a missing permission (403). */
    public static function storeVisibleTo(User $user, ReturnDocument $returnDocument): bool
    {
        return ActiveStore::canRead($user, self::storeOf($returnDocument));
    }

    private static function canChangeStoreOf(User $user, ReturnDocument $returnDocument): bool
    {
        $storeId = self::storeOf($returnDocument);

        return $storeId !== null && ActiveStore::canAccess($user, $storeId);
    }

    private static function storeOf(ReturnDocument $returnDocument): ?int
    {
        return $returnDocument->store_id !== null ? (int) $returnDocument->store_id : null;
    }
}
