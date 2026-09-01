<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;

final class StockTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.view')
            || $user->can('stores.manage')
            || $user->can('transfers.view');
    }

    public function view(User $user, StockTransfer $transfer): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.view')
            || $user->can('stores.manage')
            || $user->can('transfers.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage')
            || $user->can('transfers.create');
    }

    public function cancel(User $user, StockTransfer $transfer): bool
    {
        return $user->hasRole('admin')
            || $user->can('stores.manage');
    }
}
