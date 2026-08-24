<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('invoices.view')
            || $user->can('pos.access');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin')
            || $user->can('invoices.view')
            || $user->can('pos.access');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('invoices.create')
            || $user->can('pos.access');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin') || $user->can('invoices.edit');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin') || $user->can('invoices.delete');
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $user->hasRole('admin') || $user->can('invoices.cancel');
    }
}
