<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\SelfSignupTokenDTO;
use App\Enums\SignupRequestStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\SignupRequest;
use App\Services\Signup\SignupVerificationCodeIssuer;
use App\Services\TenantProvisionerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * SIGN-11: send a new verification code for a self-signup still awaiting verification.
 * Central transaction, row locked; the 60-second cooldown and the per-email hourly budget are
 * SignupVerificationCodeIssuer's (429 with Retry-After), its daily platform ceiling a 503.
 * Unknown (or expired, SignupRequest::forLiveToken) token: generic 422 on `token`; an already
 * verified signup: 409.
 */
final class ResendSelfSignupCodeAction
{
    public function __construct(
        private readonly SignupVerificationCodeIssuer $codes,
    ) {}

    public function execute(SelfSignupTokenDTO $dto): SignupRequest
    {
        $this->codes->ensureDailyCapacity();

        return DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($dto): SignupRequest {
            $signup = SignupRequest::query()
                ->forLiveToken($dto->token)
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
