<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

final class ActivityLogPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any activity logs.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('logs.view') || $user->hasRole('admin') || $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can view a specific activity log.
     */
    public function view(User $user, ActivityLog $log): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determine whether the user can export activity logs.
     */
    public function export(User $user): bool
    {
        return $this->viewAny($user);
    }
}
