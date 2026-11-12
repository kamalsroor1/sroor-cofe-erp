<?php

declare(strict_types=1);

namespace App\Support\Roles;

use App\Exceptions\PermissionEscalationException;
use App\Models\User;
use App\Support\ActiveStore;
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

    /**
     * SEC-20: changing another user's login credentials (password, phone, email) or manager
     * approval PIN hands the actor that user's identity, so it is treated like granting the
     * target's permissions to the actor: the actor must already hold every permission the target
     * holds (via roles or directly) and be able to access (not merely view) each of the target's
     * stores. Admins are exempt; a user acting on their own account too.
     */
    public function assertCanTakeOverAccount(User $actor, User $target): void
    {
        if ($actor->hasRole('admin') || (int) $actor->getKey() === (int) $target->getKey()) {
            return;
        }

        $fresh = User::query()->findOrFail($target->getKey());

        /** @var list<string> $targetPermissions */
        $targetPermissions = $fresh->getAllPermissions()->pluck('name')->all();

        if (array_diff($targetPermissions, $this->heldPermissions($actor)) !== []) {
            throw new PermissionEscalationException(__('users.cannot_change_credentials_of_more_privileged_user'));
        }

        // SEC-20 review: the target's stores come with the account too, so an actor confined to
        // some stores may not take over a user who works in a store outside them. Read-only
        // `stores.view_all` is deliberately not enough: the taken-over account can sell there.
        foreach ($this->storeIdsOf($fresh) as $storeId) {
            if (! ActiveStore::canAccess($actor, $storeId)) {
                throw new PermissionEscalationException(__('users.cannot_change_credentials_of_more_privileged_user'));
            }
        }
    }

    /**
     * SEC-20 review: a non-admin actor may only point a user (themselves included) at a store
     * the actor can access; assigning a store widens that user's store access.
     */
    public function assertCanAssignStore(User $actor, ?int $storeId): void
    {
        if ($storeId === null || ActiveStore::canAccess($actor, $storeId)) {
            return;
        }

        throw new PermissionEscalationException(__('users.cannot_assign_inaccessible_store'));
    }

    /**
     * Default store plus every assigned store of the user.
     *
     * @return list<int>
     */
    private function storeIdsOf(User $user): array
    {
        $ids = $user->stores()->pluck('stores.id')->map(fn ($id): int => (int) $id)->all();

        if ($user->default_store_id !== null) {
            $ids[] = (int) $user->default_store_id;
        }

        return array_values(array_unique($ids));
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
