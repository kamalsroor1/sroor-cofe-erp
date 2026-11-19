<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockCount;
use App\Models\User;
use App\Support\ActiveStore;

/**
 * SHOP-02: stocktake sessions need `stock.count` (every plan; no plan feature) plus access to the
 * session's store. Reading needs a readable store; changing it (count, post, cancel) needs the
 * store itself. Controllers answer a store denial as 404 (CTO Q13).
 */
final class StockCountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('stock.count');
    }

    public function create(User $user): bool
    {
        return $user->can('stock.count');
    }

    public function view(User $user, StockCount $count): bool
    {
        return $user->can('stock.count') && ActiveStore::canRead($user, (int) $count->store_id);
    }

    public function update(User $user, StockCount $count): bool
    {
        return $user->can('stock.count') && ActiveStore::canAccess($user, (int) $count->store_id);
    }
}
