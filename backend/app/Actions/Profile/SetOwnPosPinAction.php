<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Actions\Users\SetUserPosPinAction;
use App\DTOs\Users\SetUserPosPinDTO;
use App\Models\User;
use App\Support\Auth\CurrentPasswordVerifier;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * BE-08 / SEC-21 review: the signed-in user sets (or clears) their OWN manager approval PIN after
 * re-entering their account password.
 *
 * The password is checked through CurrentPasswordVerifier, the same per-user lockout budget as
 * PUT /profile, POST /auth/change-password and the idle screen unlock (5 wrong answers lock it for
 * 15 minutes; 422 on `current_password`). Before, PUT /profile/pos-pin ran a bare Hash::check in
 * the FormRequest, limited only by `throttle:10,1`: an unlimited password oracle for a left-open
 * session. No transaction is open here, so a failure is counted right away and can never be
 * rolled back; the PIN write itself is SetUserPosPinAction's transaction.
 */
final class SetOwnPosPinAction
{
    public function __construct(
        private readonly CurrentPasswordVerifier $passwordVerifier,
        private readonly SetUserPosPinAction $setUserPosPinAction,
    ) {}

    /**
     * @throws ValidationException wrong or locked current password (422 on current_password)
     */
    public function execute(User $user, SetUserPosPinDTO $dto, #[SensitiveParameter] string $currentPassword): User
    {
        $this->passwordVerifier->assertNotLocked($user);

        if (! $this->passwordVerifier->matches($user, $currentPassword)) {
            $this->passwordVerifier->failed($user);
        }

        $updated = $this->setUserPosPinAction->execute($dto, $user);

        $this->passwordVerifier->passed($user);

        return $updated;
    }
}
