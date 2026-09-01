<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class TreasuryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('reports.view');
    }

    public function view(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.view')
            || $user->can('reports.view');
    }

    public function transfer(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('daily_journal.manage');
    }
}
