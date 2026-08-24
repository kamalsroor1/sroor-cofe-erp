<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;

final class TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || $user->can('super_admin.access');
    }

    public function view(User $user, ?Tenant $tenant = null): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || $user->can('super_admin.access');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || $user->can('super_admin.access');
    }

    public function update(User $user, ?Tenant $tenant = null): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || $user->can('super_admin.access');
    }

    public function delete(User $user, ?Tenant $tenant = null): bool
    {
        return $user->hasRole('super_admin')
            || $user->hasRole('admin')
            || $user->can('super_admin.access');
    }
}
