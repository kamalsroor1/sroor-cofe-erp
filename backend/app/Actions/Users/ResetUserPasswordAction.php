<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\DTOs\Users\TemporaryPasswordResult;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Auth\AccountLockout;
use App\Support\Auth\TemporaryPassword;
use App\Support\Roles\RoleGrantGuard;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * SELL-01: an admin / user manager resets another user's forgotten password.
 *
 * On the locked target row, in one transaction: the same rules as a password set through
 * UpdateUserAction (a non-admin may not touch an admin account, and must pass
 * RoleGrantGuard::assertCanTakeOverAccount, SEC-20), then a server-generated temporary password
 * is hashed in, `must_change_password` is set and ALL of the target's tokens are revoked (SEC-03).
 * Activity-logged as `password_reset` without the value. The sign-in lockout of the account is
 * lifted after the commit (the user is about to sign in with the new password).
 *
 * Resetting one's own account is refused (422): that is POST /auth/change-password.
 */
final class ResetUserPasswordAction
{
    public function __construct(
        private readonly RoleGrantGuard $roleGrantGuard,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(int $userId, User $actor): TemporaryPasswordResult
    {
        $result = DB::transaction(function () use ($userId, $actor): TemporaryPasswordResult {
            $target = User::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            if ((int) $target->id === (int) $actor->id) {
                throw ValidationException::withMessages(['user' => __('users.cannot_reset_own_password')]);
            }

            if (! $actor->hasRole('admin')) {
                if ($target->hasRole('admin')) {
                    throw new AuthorizationException(__('auth.cannot_modify_admin_account'));
                }

                $this->roleGrantGuard->assertCanTakeOverAccount($actor, $target);
            }

            $temporaryPassword = TemporaryPassword::generate();

            $target->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
            ])->save();

            $target->tokens()->delete();

            $this->activityLogService->log(
                module: 'users',
                action: 'password_reset',
                description: __('users.activity_password_reset_temporary', ['name' => $target->name, 'actor' => $actor->name]),
                subject: $target,
                properties: ['tokens_revoked' => true, 'temporary' => true, 'must_change_password' => true],
            );

            return new TemporaryPasswordResult($target, $temporaryPassword);
        });

        AccountLockout::clearForIdentifiers($result->user->phone, $result->user->email);

        return $result;
    }
}
