<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\SelfSignupTokenDTO;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Jobs\NotifyPlatformOfSignupRequestJob;
use App\Jobs\ProvisionVerifiedSignupJob;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Services\Signup\FreeShopGuard;
use App\Services\Signup\SignupVerificationCodeIssuer;
use App\Services\TenantProvisionerService;
use App\Support\Signup\SignupSecrets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

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
 *
 * SIGN-13: a free-plan signup whose email or phone already has a free shop (FreeShopGuard) is
 * committed as `rejected` once the code matched, and answered 422 on `email`.
 *
 * SIGN-12: after the commit, ProvisionVerifiedSignupJob creates the shop and the platform gets a
 * Telegram message per new self-signup (one summary per hour when more than
 * signup.self_signup_quiet_above_per_hour were verified in the last hour).
 */
final class VerifySelfSignupAction
{
    public function __construct(private readonly FreeShopGuard $freeShops) {}

    public function execute(SelfSignupTokenDTO $dto): SignupRequest
    {
        $code = (string) $dto->code;
        $refusedAsDuplicate = false;

        $signup = DB::connection(TenantProvisionerService::centralConnection())->transaction(function () use ($dto, $code, &$refusedAsDuplicate): ?SignupRequest {
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

            // SIGN-13: the email is proven now; re-check "one free shop" (a parallel signup may
            // have won the race since the form was sent). Committed as rejected, then 422.
            if ($this->wouldBeASecondFreeShop($signup)) {
                $signup->forceFill([
                    'status' => SignupRequestStatus::Rejected->value,
                    'rejection_reason' => (string) __('self_signup.validation.free_shop_exists'),
                    'email_verified_at' => now(),
                    'verification_code_hash' => null,
                    'verification_code_expires_at' => null,
                ])->save();
                $refusedAsDuplicate = true;

                return null;
            }

            $signup->forceFill([
                'status' => SignupRequestStatus::Verified->value,
                'email_verified_at' => now(),
                'verification_code_hash' => null,
                'verification_code_expires_at' => null,
                'verification_attempts' => 0,
            ])->save();

            // SIGN-12: provisioning and the platform alert leave only once `verified` is committed.
            ProvisionVerifiedSignupJob::dispatch((int) $signup->id)->afterCommit();
            $this->notifyPlatform($signup);

            return $signup;
        });

        if ($refusedAsDuplicate) {
            throw ValidationException::withMessages(['email' => (string) __('self_signup.validation.free_shop_exists')]);
        }

        if (! $signup instanceof SignupRequest) {
            throw ValidationException::withMessages(['code' => (string) __('self_signup.errors.code_invalid')]);
        }

        return $signup;
    }

    private function wouldBeASecondFreeShop(SignupRequest $signup): bool
    {
        $isFree = $signup->plan_code !== null
            && Plan::query()->where('slug', $signup->plan_code)->where('is_free', true)->exists();

        return $isFree && $this->freeShops->alreadyHasFreeShop((string) $signup->email, $signup->phone, (int) $signup->id);
    }

    private function notifyPlatform(SignupRequest $signup): void
    {
        $lastHour = SignupRequest::query()
            ->where('signup_flow', SignupFlow::SelfSignup->value)
            ->where('email_verified_at', '>=', now()->subHour())
            ->count();

        $summary = $lastHour > max(1, (int) config('signup.self_signup_quiet_above_per_hour', 20));
        if ($summary && ! Cache::add('self-signup:backlog-alert', true, now()->addHour())) {
            return;
        }

        try {
            NotifyPlatformOfSignupRequestJob::dispatch((int) $signup->id, $summary, true)->afterCommit();
        } catch (Throwable $e) {
            Log::warning('Self-signup notification could not be queued', ['exception' => $e::class]);
        }
    }
}
