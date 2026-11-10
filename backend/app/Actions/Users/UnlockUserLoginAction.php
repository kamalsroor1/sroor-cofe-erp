<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Auth\AccountLockout;
use Illuminate\Auth\Access\AuthorizationException;

final class UnlockUserLoginAction
{
    public function __construct(
        private readonly ActivityLogService $activityLogService,
    ) {}

    /**
     * SEC-05: lift the sign-in lockout (lock + failure counter) for every login identifier of the
     * user. Idempotent. Returns whether the account was actually locked.
     *
     * An actor without the `admin` role may not unlock an admin account (403): resetting the
     * counters would otherwise hand a lesser role unlimited guesses against an admin.
     */
    public function execute(int $userId, User $actor): bool
    {
        $user = User::query()->findOrFail($userId);

        if (! $actor->hasRole('admin') && $user->hasRole('admin')) {
            throw new AuthorizationException(__('auth.cannot_modify_admin_account'));
        }

        $wasLocked = AccountLockout::clearForIdentifiers($user->phone, $user->email);

        $this->activityLogService->log(
            module: 'users',
            action: 'login_unlocked',
            description: __('users.activity_login_unlocked', ['name' => $user->name, 'actor' => $actor->name]),
            subject: $user,
            properties: ['was_locked' => $wasLocked],
        );

        return $wasLocked;
    }
}
