<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * POSB-2: per-store POS settings. Requires `settings.manage` AND access to that store
 * (the tenant `admin` role passes through Gate::before). Used as
 * Gate::allows('view', [StorePosSetting::class, $store]).
 */
final class StorePosSettingPolicy
{
    public function view(User $user, Store $store): bool
    {
        return $user->can('settings.manage') && $this->canAccessStore($user, $store);
    }

    public function update(User $user, Store $store): bool
    {
        return $user->can('settings.manage') && $this->canAccessStore($user, $store);
    }

    /**
     * Same store-access rule as StorePolicy::view (stores.manage sees every branch;
     * otherwise the user's default store or an assigned one).
     */
    private function canAccessStore(User $user, Store $store): bool
    {
        if ($user->can('stores.manage')) {
            return true;
        }

        return (int) $user->default_store_id === (int) $store->id
            || $user->stores()->where('stores.id', $store->id)->exists();
    }
}
