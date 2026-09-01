<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

final class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('expenses.manage')
            || $user->can('expenses.view')
            || $user->can('daily_journal.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin')
            || $user->can('expenses.manage')
            || $user->can('expenses.view')
            || $user->can('daily_journal.view');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('expenses.manage');
    }

    public function update(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin') || $user->can('expenses.manage');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return $user->hasRole('admin') || $user->can('expenses.manage');
    }
}
