<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\UpdateUserDTO;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class UpdateUserAction
{
    /**
     * Update user details, optional password and sync role.
     *
     * SEC-1 (L12): an actor without the `admin` role (e.g. a roles.manage delegate) may not
     * assign the admin role, may not change an admin's role, password or active flag, and may
     * not change their own role. Checked on the locked row, before anything is written (403).
     * CTO 2026-10-11: nor an admin's identity (name, phone, email); only an admin edits an admin.
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

            $user->update($data);
            $user->syncRoles([$dto->role]);

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
