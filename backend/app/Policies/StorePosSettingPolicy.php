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
     * POS-10: ONLY the "require an open shift to sell" rule. Branch owners (`stores.manage`) may
     * read and change it besides `settings.manage`; they gain nothing else from the POS settings
     * (discount cap, scale parser and quick keys stay `settings.manage`-only via view/update).
     */
    public function updateShiftRule(User $user, Store $store): bool
    {
        return ($user->can('settings.manage') || $user->can('stores.manage')) && $this->canAccessStore($user, $store);
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
