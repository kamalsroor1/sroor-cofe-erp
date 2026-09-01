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
            || $user->can('purchases.view')
            || $user->can('purchases.manage');
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.view')
            || $user->can('purchases.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.create')
            || $user->can('purchases.manage');
    }

    public function cancel(User $user, Purchase $purchase): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.delete')
            || $user->can('purchases.manage');
    }

    public function reorder(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('purchases.view')
            || $user->can('purchases.manage');
    }
}
