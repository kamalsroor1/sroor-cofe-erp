<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('roles.manage')
            || $user->can('settings.manage');
    }

    public function update(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('roles.manage')
            || $user->can('settings.manage');
    }

    public function telegram(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('roles.manage')
            || $user->can('settings.manage');
    }
}
