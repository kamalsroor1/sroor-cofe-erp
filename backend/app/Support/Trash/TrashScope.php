<?php

declare(strict_types=1);

namespace App\Support\Trash;

use App\Models\Expense;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\User;
use App\Support\ActiveStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * SEC-21 (3): the recycle bin respects the branch (store) a record belongs to.
 *
 *  - branch documents (expenses, sales/purchase returns): only the stores the user may READ
 *    (ActiveStore::readableStoreIds: default store + assigned stores, or every store for
 *    admin / stores.manage / stores.view_all). A record without a store is visible only to users
 *    who may see every store;
 *  - deleted branches (stores): restoring or purging a branch is store management, so only
 *    admin / stores.manage see them;
 *  - tenant-wide master data (items, customers, suppliers) is not branch-scoped.
 *
 * Listing goes through scope() (READ access); restoring and purging through findForWrite()
 * (real access to the record's branch). A record outside either is simply "not found" (404), the
 * same answer as an id that does not exist.
 */
final class TrashScope
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  an onlyTrashed() query
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, User $actor): Builder
    {
        $model = $query->getModel();

        if ($model instanceof Store) {
            return self::managesStores($actor) ? $query : $query->whereRaw('1 = 0');
        }

        if ($model instanceof Expense || $model instanceof ReturnDocument) {
            $readable = ActiveStore::readableStoreIds($actor);

            return $readable === null ? $query : $query->whereIn($model->qualifyColumn('store_id'), $readable);
        }

        return $query;
    }

    /**
     * The trashed record $id for a RESTORE or PURGE. Listing uses the read scope (scope()), but a
     * write needs real access to the record's branch: `stores.view_all` only lets a user READ every
     * branch, it must not let them restore or purge another branch's documents (security review).
     * A branch document needs ActiveStore::canAccess on its store (admin / stores.manage when it has
     * none). Anything else is the same "not found" as a missing id.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  an onlyTrashed() query
     * @return TModel
     *
     * @throws ModelNotFoundException<TModel>
     */
    public static function findForWrite(Builder $query, User $actor, int $id): Model
    {
        $model = self::scope($query, $actor)->findOrFail($id);

        if ($model instanceof Expense || $model instanceof ReturnDocument) {
            $storeId = $model->store_id !== null ? (int) $model->store_id : null;
            $allowed = $storeId === null ? self::managesStores($actor) : ActiveStore::canAccess($actor, $storeId);

            if (! $allowed) {
                throw (new ModelNotFoundException)->setModel($model::class, [$id]);
            }
        }

        return $model;
    }

    private static function managesStores(User $actor): bool
    {
        return $actor->hasRole('admin') || $actor->can('stores.manage');
    }
}
