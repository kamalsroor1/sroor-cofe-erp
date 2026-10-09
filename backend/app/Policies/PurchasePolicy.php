<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

final class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.view');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.create');
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.delete');
    }

    public function reorder(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.view');
    }
}
