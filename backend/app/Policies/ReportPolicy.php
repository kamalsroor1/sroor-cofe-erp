<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('reports.view')
            || $user->can('reports.advanced');
    }

    public function view(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('reports.view')
            || $user->can('reports.advanced');
    }
}
