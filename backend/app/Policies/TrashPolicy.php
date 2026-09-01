<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class TrashPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('trash.access');
    }

    public function restore(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('trash.access');
    }

    public function forceDelete(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('trash.access');
    }
}
