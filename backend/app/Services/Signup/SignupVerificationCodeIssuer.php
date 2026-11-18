<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Mail\SignupVerificationCodeMail;
use App\Models\SignupRequest;
use App\Support\Signup\SignupSecrets;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SIGN-11: issue (or re-issue) the email verification code of a self-signup. Shared by
 * StartSelfSignupAction and ResendSelfSignupCodeAction.
 *
 * Call it inside the caller's central transaction with the row locked (or freshly inserted):
 *  - a resend within `resend_after_seconds` of the last code is refused (429, Retry-After);
 *  - at most `codes_per_email_per_hour` codes per email address, across every signup row of that
 *    address (429, Retry-After); the budget is spent only when a code is actually issued;
 *  - the new code replaces the old one (keyed hash only), resets the wrong-attempt counter and
 *    restarts the validity window;
 *  - the mail is queued afterCommit (PlatformMailable): a rolled-back signup sends nothing.
 */
final class SignupVerificationCodeIssuer
{
    private const EMAIL_BUDGET_SECONDS = 3600;

    public function issue(SignupRequest $signup, bool $resend): void
    {
        $email = (string) $signup->email;

        if ($resend) {
            $this->assertResendCooldownOver($signup);
        }

        $key = self::emailBudgetKey($email);
        if (RateLimiter::tooManyAttempts($key, self::codesPerHour())) {
            throw self::throttled(RateLimiter::availableIn($key));
        }

        $code = SignupSecrets::newCode();

        $signup->forceFill([
            'verification_code_hash' => SignupSecrets::hashCode((int) $signup->getKey(), $code),
            'verification_code_expires_at' => now()->addMinutes(self::ttlMinutes()),
            'verification_attempts' => 0,
            'verification_sent_at' => now(),
        ])->save();

        RateLimiter::hit($key, self::EMAIL_BUDGET_SECONDS);

        Mail::to($email)->queue(new SignupVerificationCodeMail(
            code: $code,
            businessName: $signup->business_name,
            validMinutes: self::ttlMinutes(),
            locale: $signup->locale,
        ));
    }

    /** Seconds until a new code may be requested for $signup (0 = now). */
    public static function resendAvailableIn(SignupRequest $signup): int
    {
        $sentAt = $signup->verification_sent_at;
        if (! $sentAt instanceof Carbon) {
            return 0;
        }

        $wait = $sentAt->copy()->addSeconds(self::resendAfterSeconds())->getTimestamp() - now()->getTimestamp();

        return max(0, $wait);
    }

    public static function ttlMinutes(): int
    {
        return max(1, (int) config('signup.verification.code_ttl_minutes', 15));
    }

    public static function maxAttempts(): int
    {
        return max(1, (int) config('signup.verification.max_attempts', 5));
    }

    public static function resendAfterSeconds(): int
    {
        return max(0, (int) config('signup.verification.resend_after_seconds', 60));
    }

    private static function codesPerHour(): int
    {
        return max(1, (int) config('signup.verification.codes_per_email_per_hour', 5));
    }

    private static function emailBudgetKey(string $email): string
    {
        return 'self-signup-codes|'.hash('sha256', strtolower(trim($email)));
    }

    private function assertResendCooldownOver(SignupRequest $signup): void
    {
        $wait = self::resendAvailableIn($signup);
        if ($wait > 0) {
            throw self::throttled($wait);
        }
    }

    private static function throttled(int $seconds): ThrottleRequestsException
    {
        $seconds = max(1, $seconds);

        return new ThrottleRequestsException((string) __('self_signup.errors.code_throttled'), null, [
            'Retry-After' => (string) $seconds,
        ]);
    }
}
