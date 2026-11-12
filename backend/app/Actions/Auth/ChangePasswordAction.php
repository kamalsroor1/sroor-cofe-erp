<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\DTOs\Auth\ChangePasswordDTO;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\Auth\CurrentPasswordVerifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * SELL-01: the signed-in user replaces their own password (the only write a user flagged
 * `must_change_password` may make).
 *
 * On the locked user row, in one transaction: the current password is compared, the new hash is
 * written, the flag is cleared and every OTHER token of the user is revoked (SEC-03); the token
 * making this request stays alive. A wrong current password writes nothing and is counted
 * AFTER the transaction (CurrentPasswordVerifier::failed: SEC-05 lockout, 422 on
 * `current_password`), so the count can never be rolled back with it. "New must differ from
 * current" is a FormRequest rule. Logged without any password.
 */
final class ChangePasswordAction
{
    public function __construct(
        private readonly CurrentPasswordVerifier $currentPasswordVerifier,
        private readonly ActivityLogService $activityLogService,
    ) {}

    public function execute(ChangePasswordDTO $dto, User $user): User
    {
        $this->currentPasswordVerifier->assertNotLocked($user);
        $currentTokenId = data_get($user->currentAccessToken(), 'id');

        $changed = DB::transaction(function () use ($dto, $user, $currentTokenId): ?User {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if (! $this->currentPasswordVerifier->matches($locked, $dto->currentPassword)) {
                return null;
            }

            $locked->forceFill([
                'password' => Hash::make($dto->password),
                'must_change_password' => false,
            ])->save();

            $revoked = $locked->tokens()
                ->when($currentTokenId !== null, fn ($query) => $query->whereKeyNot($currentTokenId))
                ->delete();

            $this->activityLogService->log(
                module: 'auth',
                action: 'password_changed',
                description: __('auth.activity_password_changed', ['name' => $locked->name]),
                subject: $locked,
                properties: ['other_sessions_revoked' => (int) $revoked],
                userId: (int) $locked->id,
            );

            return $locked;
        });

        if ($changed === null) {
            $this->currentPasswordVerifier->failed($user);
        }

        $this->currentPasswordVerifier->passed($user);

        return $changed;
    }
}
