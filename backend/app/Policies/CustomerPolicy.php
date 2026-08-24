<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

final class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('customers.manage')
            || $user->can('pos.access')
            || $user->can('invoices.create');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->hasRole('admin')
            || $user->can('customers.manage')
            || $user->can('pos.access')
            || $user->can('invoices.create');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('customers.manage')
            || $user->can('pos.access')
            || $user->can('invoices.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $user->hasRole('admin') || $user->can('customers.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasRole('admin') || $user->can('customers.manage');
    }

    public function collectPayment(User $user, Customer $customer): bool
    {
        return $user->hasRole('admin')
            || $user->can('customers.manage')
            || $user->can('daily_journal.view');
    }

    public function statement(User $user, Customer $customer): bool
    {
        return $user->hasRole('admin')
            || $user->can('customers.statement')
            || $user->can('customers.manage');
    }
}
