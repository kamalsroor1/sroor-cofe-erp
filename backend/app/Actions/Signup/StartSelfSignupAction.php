<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\SelfSignupStarted;
use App\DTOs\Signup\StartSelfSignupDTO;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Mail\PlatformMailable;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Services\Signup\SignupTrialTerms;
use App\Services\Signup\SignupVerificationCodeIssuer;
use App\Services\Signup\SubdomainSuggester;
use App\Services\TenantProvisionerService;
use App\Support\Signup\SignupSecrets;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * SIGN-11: start a public self-signup (central `signup_requests`, flow `self_signup`).
 *
 * One central transaction: the row is stored as `awaiting_verification` with the password HASH,
 * the sha256 of a fresh opaque token and the resolved plan (the requested one, else the platform
 * default through SignupTrialTerms) and workspace code (the requested one, else suggested from the
 * business name); then the first verification code is issued and its mail queued after commit.
 * A refused code (per-email hourly budget, 429) rolls the row back.
 *
 * A filled honeypot stores and sends nothing but answers the same shape, with a token that matches
 * no row, so a bot cannot tell it was detected.
 */
final class StartSelfSignupAction
{
    public function __construct(
        private readonly SignupTrialTerms $trialTerms,
        private readonly SubdomainSuggester $subdomains,
        private readonly SignupVerificationCodeIssuer $codes,
    ) {}

    public function execute(StartSelfSignupDTO $dto): SelfSignupStarted
    {
        $token = SignupSecrets::newToken();

        if ($dto->honeypotFilled) {
            return $this->started($token, $dto->email, $dto->subdomain ?? $this->subdomains->suggest($dto->businessName));
        }

        $plan = $this->trialTerms->resolvePlan($dto->planCode);
        if (! $plan instanceof Plan) {
            throw ValidationException::withMessages(['plan_code' => (string) __('signup.errors.no_trial_plan')]);
        }

        $subdomain = $dto->subdomain ?? $this->subdomains->suggest($dto->businessName);
        $passwordHash = Hash::make($dto->password);

        DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($dto, $plan, $subdomain, $passwordHash, $token): void {
            $signup = new SignupRequest([
                'business_name' => $dto->businessName,
                'contact_name' => $dto->ownerName,
                'phone' => $dto->phone,
                'email' => $dto->email,
                'city' => $dto->city,
                'business_type' => $dto->businessType->value,
                'plan_code' => $plan->slug,
                'preferred_subdomain' => $subdomain,
                'status' => SignupRequestStatus::AwaitingVerification->value,
                'signup_flow' => SignupFlow::SelfSignup->value,
                'locale' => PlatformMailable::resolveLocale($dto->locale),
                'ip' => $dto->ip,
                'user_agent' => $dto->userAgent,
                'terms_accepted_at' => now(),
                'terms_version' => $dto->termsVersion,
            ]);
            $signup->forceFill([
                'password_hash' => $passwordHash,
                'signup_token_hash' => SignupSecrets::hashToken($token),
            ])->save();

            $this->codes->issue($signup, resend: false);
        });

        return $this->started($token, $dto->email, $subdomain);
    }

    private function started(string $token, string $email, string $subdomain): SelfSignupStarted
    {
        return new SelfSignupStarted(
            token: $token,
            maskedEmail: SignupSecrets::maskEmail($email),
            subdomain: $subdomain,
            codeExpiresIn: SignupVerificationCodeIssuer::ttlMinutes() * 60,
            resendAvailableIn: SignupVerificationCodeIssuer::resendAfterSeconds(),
        );
    }
}
