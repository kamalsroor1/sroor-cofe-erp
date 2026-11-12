<?php

declare(strict_types=1);

namespace App\Actions\ScreenLock;

use App\DTOs\Auth\UnlockScreenDTO;
use App\Models\User;
use App\Services\Approvals\ManagerPinVerifier;
use App\Support\Auth\CurrentPasswordVerifier;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;

/**
 * SET-02: verify the current user's own secret to dismiss the SPA idle lock screen. The token is
 * never revoked by the lock; this endpoint only answers "is it still the same person?".
 *
 * No new secret check is introduced, both paths reuse the existing throttled verifiers so the
 * endpoint cannot become a guessing oracle:
 *  - password: CurrentPasswordVerifier (same per-user bucket as PUT /profile and
 *    /auth/change-password: account_lockout_attempts per account_lockout_minutes; locked = 422);
 *  - PIN: ManagerPinVerifier with the user as their own approver (same per-approver/per-actor
 *    buckets as every manager-PIN approval; throttled = 429).
 * Errors are re-keyed onto the field the client sent (`password` / `pin`).
 */
final class UnlockScreenAction
{
    public function __construct(
        private readonly CurrentPasswordVerifier $passwordVerifier,
        private readonly ManagerPinVerifier $pinVerifier,
    ) {}

    /**
     * @throws ValidationException wrong secret / password lockout (422)
     * @throws ThrottleRequestsException too many wrong PINs (429)
     */
    public function execute(User $user, UnlockScreenDTO $dto): void
    {
        if ($dto->password !== null) {
            // No transaction here, so the failure is counted right away (see CurrentPasswordVerifier).
            try {
                $this->passwordVerifier->assertNotLocked($user);

                if (! $this->passwordVerifier->matches($user, $dto->password)) {
                    $this->passwordVerifier->failed($user);
                }
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['password' => $e->validator->errors()->all()]);
            }

            $this->passwordVerifier->passed($user);

            return;
        }

        try {
            $this->pinVerifier->verify(
                (int) $user->id,
                (string) $dto->pin,
                $user,
                null,
                static fn (User $candidate): bool => (int) $candidate->id === (int) $user->id,
            );
        } catch (ValidationException) {
            throw ValidationException::withMessages(['pin' => __('screen_lock.pin_invalid')]);
        }
    }
}
