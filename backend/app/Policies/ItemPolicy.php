<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Item;
use App\Models\User;

final class ItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('items.view')
            || $user->can('items.manage')
            || $user->can('pos.access');
    }

    public function view(User $user, Item $item): bool
    {
        return $user->hasRole('admin')
            || $user->can('items.view')
            || $user->can('items.manage')
            || $user->can('pos.access');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('items.manage');
    }

    public function update(User $user, Item $item): bool
    {
        return $user->hasRole('admin') || $user->can('items.manage');
    }

    public function delete(User $user, Item $item): bool
    {
        return $user->hasRole('admin') || $user->can('items.manage');
    }

    public function adjustStock(User $user, Item $item): bool
    {
        return $user->hasRole('admin')
            || $user->can('items.manage')
            || $user->can('inventory.adjust');
    }

    public function movements(User $user, Item $item): bool
    {
        return $user->hasRole('admin')
            || $user->can('items.view')
            || $user->can('items.manage');
    }
}
