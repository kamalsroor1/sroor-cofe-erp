<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

final class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('customers.manage')
            || $user->can('suppliers.manage');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('customers.manage')
            || $user->can('suppliers.manage');
    }

    public function createCustomerReceipt(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('customers.manage')
            || $user->can('daily_journal.view');
    }

    public function createSupplierVoucher(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('suppliers.manage')
            || $user->can('daily_journal.view');
    }
}
