<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

final class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('roles.manage');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasRole('admin')
            || $user->can('roles.manage');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasRole('admin')
            || $user->can('roles.manage');
    }
}
