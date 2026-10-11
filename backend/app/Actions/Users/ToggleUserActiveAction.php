<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final class ToggleUserActiveAction
{
    /**
     * Toggle active state for user preventing disabling own account.
     *
     * SEC-1 (L12): an actor without the `admin` role may not toggle an admin account (403).
     */
    public function execute(int $userId, ?User $actor = null): User
    {
        $user = User::findOrFail($userId);

        if ($actor instanceof User && $user->id === $actor->id) {
            throw ValidationException::withMessages([
                'user' => __('auth.cannot_disable_own_account'),
            ]);
        }

        if ($actor instanceof User && ! $actor->hasRole('admin') && $user->hasRole('admin')) {
            throw new AuthorizationException(__('auth.cannot_modify_admin_account'));
        }

        $user->update(['is_active' => ! $user->is_active]);

        return $user;
    }
}
