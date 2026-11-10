<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;

final class SetUserPosPinAction
{
    /**
     * BE-08: set (or, with null, clear) the target user's manager approval PIN, stored hashed.
     * An actor without the `admin` role may not change an admin account's PIN (403), unless it is
     * their own.
     *
     * @throws AuthorizationException
     */
    public function execute(User $target, ?string $pin, User $actor): User
    {
        if ($target->id !== $actor->id && ! $actor->hasRole('admin') && $target->hasRole('admin')) {
            throw new AuthorizationException(__('auth.cannot_modify_admin_account'));
        }

        $target->forceFill(['pos_pin' => $pin === null ? null : Hash::make($pin)])->save();

        return $target;
    }
}
