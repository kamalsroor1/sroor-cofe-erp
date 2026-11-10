<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Exceptions\PermissionEscalationException;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * SEC-04: the single rule that stops a non-admin delegate (users.manage / roles.manage) from
 * granting permissions they do not hold. Admins are exempt. Call inside the write transaction.
 */
final class RoleGrantGuard
{
    /**
     * Permission names the actor really holds (direct + via roles), read fresh from the database.
     *
     * @return list<string>
     */
    public function heldPermissions(User $actor): array
    {
        /** @var list<string> $names */
        $names = User::query()->findOrFail($actor->getKey())->getAllPermissions()->pluck('name')->all();

        return $names;
    }

    /**
     * @param  list<string>  $permissions  permission names about to be granted
     */
    public function assertCanGrantPermissions(User $actor, array $permissions): void
    {
        if ($actor->hasRole('admin')) {
            return;
        }

        if (array_diff($permissions, $this->heldPermissions($actor)) !== []) {
            throw new PermissionEscalationException(__('roles.cannot_grant_unheld_permission'));
        }
    }

    /** Assigning a role to a user grants every permission of that role. */
    public function assertCanAssignRole(User $actor, string $roleName): void
    {
        if ($actor->hasRole('admin')) {
            return;
        }

        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null) {
            return;
        }

        /** @var list<string> $rolePermissions */
        $rolePermissions = $role->permissions()->pluck('name')->all();
        $this->assertCanGrantPermissions($actor, $rolePermissions);
    }
}
