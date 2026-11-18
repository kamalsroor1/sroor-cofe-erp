<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\SelfSignupTokenDTO;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Models\SignupRequest;
use App\Services\Signup\SignupVerificationCodeIssuer;
use App\Services\TenantProvisionerService;
use App\Support\Signup\SignupSecrets;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SIGN-11: check the emailed code of a self-signup.
 *
 * Central transaction, row locked FOR UPDATE (two parallel guesses cannot both spend one
 * attempt). A wrong code increments the attempt counter and, at `max_attempts`, burns the code;
 * that write is COMMITTED before the error is thrown. Every failure (unknown token, no code, burnt,
 * expired, wrong) is the same generic 422 on `code`, so nothing reveals which part was wrong.
 *
 * Success: `verified` + email_verified_at, the code hash removed. A row that is already past
 * verification is returned as is (a double submit is not an error).
 */
final class VerifySelfSignupAction
{
    public function execute(SelfSignupTokenDTO $dto): SignupRequest
    {
        $code = (string) $dto->code;

        $signup = DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($dto, $code): ?SignupRequest {
            $signup = SignupRequest::query()
                ->where('signup_token_hash', SignupSecrets::hashToken($dto->token))
                ->where('signup_flow', SignupFlow::SelfSignup->value)
                ->lockForUpdate()
                ->first();

            if (! $signup instanceof SignupRequest) {
                return null;
            }

            if ($signup->status !== SignupRequestStatus::AwaitingVerification) {
                return $signup;
            }

            $expired = $signup->verification_code_expires_at === null || $signup->verification_code_expires_at->isPast();
            if ($expired || $signup->verification_code_hash === null) {
                return null;
            }

            if (! SignupSecrets::codeMatches($signup->verification_code_hash, (int) $signup->getKey(), $code)) {
                $attempts = $signup->verification_attempts + 1;
                $signup->forceFill([
                    'verification_attempts' => $attempts,
                    // Burnt: a new code must be requested.
                    'verification_code_hash' => $attempts >= SignupVerificationCodeIssuer::maxAttempts() ? null : $signup->verification_code_hash,
                ])->save();

                return null;
            }

            $signup->forceFill([
                'status' => SignupRequestStatus::Verified->value,
                'email_verified_at' => now(),
                'verification_code_hash' => null,
                'verification_code_expires_at' => null,
                'verification_attempts' => 0,
            ])->save();

            return $signup;
        });

        if (! $signup instanceof SignupRequest) {
            throw ValidationException::withMessages(['code' => (string) __('self_signup.errors.code_invalid')]);
        }

        return $signup;
    }
}
