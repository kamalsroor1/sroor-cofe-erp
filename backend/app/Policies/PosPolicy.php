<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class PosPolicy
{
    public function access(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('pos.access')
            || $user->can('invoices.create');
    }

    public function checkout(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('pos.access')
            || $user->can('invoices.create');
    }

    public function quickCustomer(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('pos.access')
            || $user->can('customers.manage');
    }
}
