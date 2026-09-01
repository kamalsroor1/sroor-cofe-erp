<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('users.manage')
            || $user->can('roles.manage');
    }

    public function view(User $user, ?User $model = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('users.manage')
            || $user->can('roles.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('users.manage')
            || $user->can('roles.manage');
    }

    public function update(User $user, ?User $model = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('users.manage')
            || $user->can('roles.manage');
    }

    public function delete(User $user, ?User $model = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('users.manage')
            || $user->can('roles.manage');
    }

    public function toggleActive(User $user, ?User $model = null): bool
    {
        return $user->hasRole('admin')
            || $user->can('users.manage')
            || $user->can('roles.manage');
    }
}
