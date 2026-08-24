<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CashShift;
use App\Models\User;

final class ShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('pos.access')
            || $user->can('pos.sell');
    }

    public function view(User $user, ?CashShift $shift = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('pos.access')
            || $user->can('pos.sell');
    }

    public function open(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('pos.access')
            || $user->can('pos.sell');
    }

    public function close(User $user, ?CashShift $shift = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('pos.access')
            || $user->can('pos.sell');
    }

    public function zReport(User $user, ?CashShift $shift = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('pos.access')
            || $user->can('pos.sell');
    }
}
