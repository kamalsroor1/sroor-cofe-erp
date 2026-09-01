<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

final class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Store $store): bool
    {
        if ($user->hasRole('admin') || $user->can('stores.manage')) {
            return true;
        }

        return (int)$user->default_store_id === $store->id
            || $user->stores()->where('stores.id', $store->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage');
    }

    public function update(User $user, Store $store): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage');
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage');
    }

    public function assignUsers(User $user, Store $store): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage');
    }

    public function switchStore(User $user, Store $store): bool
    {
        if ($user->hasRole('admin') || $user->can('stores.manage')) {
            return true;
        }

        return (int)$user->default_store_id === $store->id
            || $user->stores()->where('stores.id', $store->id)->exists();
    }
}
