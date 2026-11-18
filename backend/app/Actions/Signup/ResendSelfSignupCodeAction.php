<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\SelfSignupTokenDTO;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\SignupRequest;
use App\Services\Signup\SignupVerificationCodeIssuer;
use App\Services\TenantProvisionerService;
use App\Support\Signup\SignupSecrets;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SIGN-11: send a new verification code for a self-signup still awaiting verification.
 * Central transaction, row locked; the 60-second cooldown and the per-email hourly budget are
 * SignupVerificationCodeIssuer's (429 with Retry-After). Unknown token: generic 422 on `token`;
 * an already verified signup: 409.
 */
final class ResendSelfSignupCodeAction
{
    public function __construct(
        private readonly SignupVerificationCodeIssuer $codes,
    ) {}

    public function execute(SelfSignupTokenDTO $dto): SignupRequest
    {
        return DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($dto): SignupRequest {
            $signup = SignupRequest::query()
                ->where('signup_token_hash', SignupSecrets::hashToken($dto->token))
                ->where('signup_flow', SignupFlow::SelfSignup->value)
                ->lockForUpdate()
                ->first();

            if (! $signup instanceof SignupRequest) {
                throw ValidationException::withMessages(['token' => (string) __('self_signup.errors.token_invalid')]);
            }

            if ($signup->status !== SignupRequestStatus::AwaitingVerification) {
                throw BusinessRuleException::conflict('self_signup.errors.already_verified');
            }

            $this->codes->issue($signup, resend: true);

            return $signup;
        });
    }
}
