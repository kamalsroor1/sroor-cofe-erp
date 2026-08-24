<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;

final class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('suppliers.manage')
            || $user->can('suppliers.view');
    }

    public function view(User $user, ?Supplier $supplier = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('suppliers.manage')
            || $user->can('suppliers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('suppliers.manage');
    }

    public function update(User $user, ?Supplier $supplier = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('suppliers.manage');
    }

    public function delete(User $user, ?Supplier $supplier = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('suppliers.manage');
    }

    public function pay(User $user, ?Supplier $supplier = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('suppliers.manage');
    }

    public function statement(User $user, ?Supplier $supplier = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('suppliers.manage')
            || $user->can('suppliers.view')
            || $user->can('suppliers.statement');
    }
}
