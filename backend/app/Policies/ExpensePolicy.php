<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Support\ActiveStore;

/**
 * STOR-2: abilities on a given expense also check its store (branch). Reading needs
 * ActiveStore::canRead (store access or `stores.view_all`); editing or deleting needs
 * ActiveStore::canAccess. Controllers answer a store denial as 404 (CTO Q13), so a branch
 * cannot probe other branches' ids.
 */
final class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('expenses.manage')
            || $user->can('daily_journal.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return ($user->hasRole('admin')
            || $user->can('expenses.manage')
            || $user->can('daily_journal.view'))
            && self::storeVisibleTo($user, $expense);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('expenses.manage');
    }

    public function update(User $user, Expense $expense): bool
    {
        return ($user->hasRole('admin') || $user->can('expenses.manage'))
            && self::canChangeStoreOf($user, $expense);
    }

    public function delete(User $user, Expense $expense): bool
    {
        return ($user->hasRole('admin') || $user->can('expenses.manage'))
            && self::canChangeStoreOf($user, $expense);
    }

    /** Store access alone (no ability): lets controllers tell a store denial (404) from a missing permission (403). */
    public static function storeVisibleTo(User $user, Expense $expense): bool
    {
        return ActiveStore::canRead($user, self::storeOf($expense));
    }

    private static function canChangeStoreOf(User $user, Expense $expense): bool
    {
        $storeId = self::storeOf($expense);

        return $storeId !== null && ActiveStore::canAccess($user, $storeId);
    }

    private static function storeOf(Expense $expense): ?int
    {
        return $expense->store_id !== null ? (int) $expense->store_id : null;
    }
}
