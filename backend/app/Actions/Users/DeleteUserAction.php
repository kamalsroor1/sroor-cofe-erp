<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteUserAction
{
    /**
     * Delete user safely preventing self-deletion.
     *
     * SEC-1 (L12): an actor without the `admin` role may not delete an admin account (403).
     */
    public function execute(int $userId, ?User $actor = null): bool
    {
        $user = User::findOrFail($userId);

        if ($actor instanceof User && $user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => __('auth.cannot_delete_own_account'),
            ]);
        }

        if ($actor instanceof User && ! $actor->hasRole('admin') && $user->hasRole('admin')) {
            throw new AuthorizationException(__('auth.cannot_modify_admin_account'));
        }

        return (bool) DB::transaction(function () use ($user) {
            return $user->delete();
        });
    }
}
