<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\UpdateUserDTO;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Roles\RoleGrantGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UpdateUserAction
{
    public function __construct(
        private readonly RoleGrantGuard $roleGrantGuard,
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * Update user details, optional password and sync role.
     *
     * SEC-1 (L12): an actor without the `admin` role (e.g. a roles.manage delegate) may not
     * assign the admin role, may not change an admin's role, password or active flag, and may
     * not change their own role. Checked on the locked row, before anything is written (403).
     * CTO 2026-10-11: nor an admin's identity (name, phone, email); only an admin edits an admin.
     * SEC-20: nor another user's password, phone or email unless the actor holds every permission
     * and every store that user works in (RoleGrantGuard::assertCanTakeOverAccount); the same rule
     * applies before changing another user's role; and a new default store must be one the actor
     * can access (RoleGrantGuard::assertCanAssignStore). A password set for another user
     * is written to the activity log (never the value) and, as before, revokes all of their tokens.
     */
    public function execute(UpdateUserDTO $dto, ?User $actor = null): User
    {
        return DB::transaction(function () use ($dto, $actor) {
            $user = User::query()->whereKey($dto->id)->lockForUpdate()->firstOrFail();

            if ($actor instanceof User && ! $actor->hasRole('admin')) {
                $this->guardNonAdminActor($actor, $user, $dto);
            }

            $data = [
                'name' => $dto->name,
                'phone' => $dto->phone,
                'email' => $dto->email,
                'default_store_id' => $dto->default_store_id,
                'is_active' => $dto->is_active,
            ];

            if ($dto->password) {
                $data['password'] = Hash::make($dto->password);
            }

            $wasActive = (bool) $user->is_active;
            $rolesBefore = $user->getRoleNames()->sort()->values()->all();

            // SELL-01: a password chosen by someone else must be replaced at the next sign-in.
            if ($dto->password !== null && (int) $actor?->id !== (int) $user->id) {
                $user->forceFill(['must_change_password' => true]);
            }

            $user->update($data);
            $user->syncRoles([$dto->role]);

            // SEC-03: password reset, deactivation or a different role set ends all sessions.
            $rolesAfter = $user->getRoleNames()->sort()->values()->all();
            if ($dto->password || ($wasActive && ! $dto->is_active) || $rolesBefore !== $rolesAfter) {
                $user->tokens()->delete();
            }

            if ($dto->password !== null && (int) $actor?->id !== (int) $user->id) {
                $this->activityLogService->log(
                    module: 'users',
                    action: 'password_reset',
                    description: __('users.activity_password_reset', ['name' => $user->name, 'actor' => $actor->name ?? '-']),
                    subject: $user,
                    properties: ['tokens_revoked' => true],
                );
            }

            return $user;
        });
    }

    private function guardNonAdminActor(User $actor, User $target, UpdateUserDTO $dto): void
    {
        $targetIsAdmin = $target->hasRole('admin');

        if ($dto->role === 'admin' && ! $targetIsAdmin) {
            throw new AuthorizationException(__('auth.cannot_assign_admin_role'));
        }

        if ($targetIsAdmin && ($dto->role !== 'admin' || $dto->password !== null || $dto->is_active !== (bool) $target->is_active)) {
            throw new AuthorizationException(__('auth.cannot_modify_admin_account'));
        }

        if ($targetIsAdmin && $this->identityChanged($target, $dto)) {
            throw new AuthorizationException(__('auth.cannot_modify_admin_identity'));
        }

        $currentRoles = $target->getRoleNames()->values()->all();
        if ((int) $actor->id === (int) $target->id && $currentRoles !== [$dto->role]) {
            throw new AuthorizationException(__('auth.cannot_change_own_role'));
        }

        // SEC-04: a role change may not hand out permissions the actor does not hold.
        // SEC-20 review: nor strip the role of a user who holds permissions or stores the actor
        // lacks (demote first, then reset the password, would otherwise pass in two requests).
        if ($currentRoles !== [$dto->role]) {
            $this->roleGrantGuard->assertCanAssignRole($actor, $dto->role);
            $this->roleGrantGuard->assertCanTakeOverAccount($actor, $target);
        }

        // SEC-20 review: moving a user (self included) to a store widens that user's store access,
        // so the new default store must be one the actor can access.
        $currentStoreId = $target->default_store_id !== null ? (int) $target->default_store_id : null;
        if ($dto->default_store_id !== $currentStoreId) {
            $this->roleGrantGuard->assertCanAssignStore($actor, $dto->default_store_id);
        }

        // SEC-20: a new password, phone or email of another user is an account takeover in all but
        // name, so the actor must already hold everything that user holds.
        if ($dto->password !== null || $this->credentialsChanged($target, $dto)) {
            $this->roleGrantGuard->assertCanTakeOverAccount($actor, $target);
        }
    }

    /** Login identifiers (phone, email) differ from the stored value (a null email equals an empty one). */
    private function credentialsChanged(User $target, UpdateUserDTO $dto): bool
    {
        return (string) $target->phone !== $dto->phone
            || (string) $target->email !== (string) $dto->email;
    }

    /** Name, phone, email or default store differs from the stored value (a null email equals an empty one). */
    private function identityChanged(User $target, UpdateUserDTO $dto): bool
    {
        return (string) $target->name !== $dto->name
            || (string) $target->phone !== $dto->phone
            || (string) $target->email !== (string) $dto->email
            || ($target->default_store_id !== null ? (int) $target->default_store_id : null) !== $dto->default_store_id;
    }
}
