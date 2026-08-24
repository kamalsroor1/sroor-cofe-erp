<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ReturnDocument;
use App\Models\User;

final class ReturnPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('returns.view')
            || $user->can('returns.manage');
    }

    public function view(User $user, ReturnDocument $returnDocument): bool
    {
        return $user->hasRole('admin')
            || $user->can('returns.view')
            || $user->can('returns.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('returns.create')
            || $user->can('returns.manage');
    }

    public function delete(User $user, ReturnDocument $returnDocument): bool
    {
        return $user->hasRole('admin')
            || $user->can('returns.manage');
    }
}
