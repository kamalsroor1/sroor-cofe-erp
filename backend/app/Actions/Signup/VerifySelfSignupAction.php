<?php

declare(strict_types=1);

namespace App\Actions\Signup;

use App\DTOs\Signup\SelfSignupTokenDTO;
use App\Enums\SignupFlow;
use App\Enums\SignupRequestStatus;
use App\Jobs\NotifyPlatformOfSignupRequestJob;
use App\Jobs\ProvisionVerifiedSignupJob;
use App\Mail\SignupShopExistsMail;
use App\Models\SignupRequest;
use App\Services\Signup\FreeShopGuard;
use App\Services\Signup\FreeShopMatch;
use App\Services\Signup\SelfSignupOutcomes;
use App\Services\Signup\SignupVerificationCodeIssuer;
use App\Services\TenantProvisionerService;
use App\Support\Signup\SignupSecrets;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
 * SIGN-13 + security review: once the code matched (the email is proven), ANY self-signup (free or
 * paid: a paid trial ends on the free plan) whose email or phone already has a shop or a verified
 * signup on its way (FreeShopGuard) is committed as `rejected` (final: password hash dropped) and
 * answered a generic 422 on `email` that never says which identity matched. The verified address
 * gets SignupShopExistsMail (sign-in link only when the shop is that mailbox's own).
 *
 * The token is looked up with SignupRequest::forLiveToken (dead 1 hour after a final state).
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
                ->forLiveToken($dto->token)
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

            // SIGN-13: the email is proven now; "one shop per identity" is checked here only
            // (never at start). Committed as rejected, the owner is mailed, then a generic 422.
            $existing = $this->freeShops->match($signup->email, $signup->phone, (int) $signup->id);
            if ($existing instanceof FreeShopMatch) {
                $signup->forceFill([
                    'status' => SignupRequestStatus::Rejected->value,
                    'rejection_reason' => (string) __('self_signup.console.duplicate_shop_rejected'),
                    'email_verified_at' => now(),
                    'verification_code_hash' => null,
                    'verification_code_expires_at' => null,
                    'password_hash' => null,
                    'terminal_at' => now(),
                ])->save();
                $this->mailShopExists($signup, $existing);
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
            throw ValidationException::withMessages(['email' => (string) __('self_signup.errors.shop_exists_contact_support')]);
        }

        if (! $signup instanceof SignupRequest) {
            throw ValidationException::withMessages(['code' => (string) __('self_signup.errors.code_invalid')]);
        }

        return $signup;
    }

    /** Queued afterCommit (PlatformMailable): leaves only with the committed rejection. */
    private function mailShopExists(SignupRequest $signup, FreeShopMatch $existing): void
    {
        if ($signup->email === null) {
            return;
        }

        $loginUrl = $existing->emailOwnedSlug === null || $existing->emailOwnedSlug === ''
            ? null
            : SelfSignupOutcomes::loginUrl($existing->emailOwnedSlug);

        Mail::to($signup->email)->queue(new SignupShopExistsMail($loginUrl, $signup->locale));
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
