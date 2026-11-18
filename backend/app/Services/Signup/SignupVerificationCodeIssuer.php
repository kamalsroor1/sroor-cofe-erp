<?php

declare(strict_types=1);

namespace App\Services\Signup;

use App\Exceptions\Signup\SignupCodesPausedException;
use App\Jobs\NotifyPlatformOfSelfSignupIssueJob;
use App\Mail\SignupVerificationCodeMail;
use App\Models\SignupRequest;
use App\Support\Signup\SignupIdentity;
use App\Support\Signup\SignupSecrets;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * SIGN-11: issue (or re-issue) the email verification code of a self-signup. Shared by
 * StartSelfSignupAction and ResendSelfSignupCodeAction.
 *
 * Call it inside the caller's central transaction with the row locked (or freshly inserted):
 *  - a resend within `resend_after_seconds` of the last code is refused (429, Retry-After);
 *  - per-mailbox budgets keyed on the CANONICAL email (security review), across every signup row
 *    of that mailbox, each a 429 with Retry-After, spent only when a code is actually issued:
 *    `codes_per_email_network_per_hour` per email + client network (signup_requests.ip_network),
 *    `codes_per_email_per_hour` and `codes_per_email_per_day` per email whatever the network;
 *  - the platform-wide `max_codes_per_day` (Cairo day) ceiling: callers run ensureDailyCapacity()
 *    BEFORE their transaction (503 + one Telegram alert per day); issue() re-checks it (503, no
 *    alert: an alert queued inside a transaction that rolls back would be lost);
 *  - the new code replaces the old one (keyed hash only), resets the wrong-attempt counter and
 *    restarts the validity window;
 *  - the mail is queued afterCommit (PlatformMailable): a rolled-back signup sends nothing.
 */
final class SignupVerificationCodeIssuer
{
    private const HOUR = 3600;

    private const DAY = 86400;

    private const CAIRO = 'Africa/Cairo';

    public function issue(SignupRequest $signup, bool $resend): void
    {
        $email = (string) $signup->email;
        $mailbox = hash('sha256', (string) SignupIdentity::canonicalEmail($email));

        if ($resend) {
            $this->assertResendCooldownOver($signup);
        }

        if (self::dailyCeilingReached()) {
            throw new SignupCodesPausedException(self::secondsUntilCairoMidnight());
        }

        $budgets = [
            ['self-signup-codes|net|'.$mailbox.'|'.hash('sha256', (string) $signup->ip_network), self::limit('codes_per_email_network_per_hour', 5), self::HOUR],
            ['self-signup-codes|'.$mailbox, self::limit('codes_per_email_per_hour', 10), self::HOUR],
            ['self-signup-codes|day|'.$mailbox, self::limit('codes_per_email_per_day', 15), self::DAY],
        ];

        foreach ($budgets as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw self::throttled(RateLimiter::availableIn($key));
            }
        }

        $code = SignupSecrets::newCode();

        $signup->forceFill([
            'verification_code_hash' => SignupSecrets::hashCode((int) $signup->getKey(), $code),
            'verification_code_expires_at' => now()->addMinutes(self::ttlMinutes()),
            'verification_attempts' => 0,
            'verification_sent_at' => now(),
        ])->save();

        foreach ($budgets as [$key, , $decay]) {
            RateLimiter::hit($key, $decay);
        }
        RateLimiter::hit(self::dailyCeilingKey(), self::secondsUntilCairoMidnight() + 60);

        Mail::to($email)->queue(new SignupVerificationCodeMail(
            code: $code,
            validMinutes: self::ttlMinutes(),
            locale: $signup->locale,
        ));
    }

    /**
     * The platform-wide daily ceiling of code emails: call it BEFORE opening the transaction that
     * issues a code. Reached: alert the platform once per Cairo day and answer 503 (Retry-After).
     */
    public function ensureDailyCapacity(): void
    {
        if (! self::dailyCeilingReached()) {
            return;
        }

        $today = CarbonImmutable::now(self::CAIRO);
        if (Cache::add('self-signup:codes-ceiling-alert:'.$today->format('Y-m-d'), true, $today->endOfDay()->addHour())) {
            NotifyPlatformOfSelfSignupIssueJob::dispatch(NotifyPlatformOfSelfSignupIssueJob::ISSUE_CODES_CEILING, null, self::maxCodesPerDay());
        }

        throw new SignupCodesPausedException(self::secondsUntilCairoMidnight());
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

    private static function limit(string $key, int $default): int
    {
        return max(1, (int) config('signup.verification.'.$key, $default));
    }

    private static function maxCodesPerDay(): int
    {
        return max(1, (int) config('signup.max_codes_per_day', 1000));
    }

    private static function dailyCeilingReached(): bool
    {
        return RateLimiter::tooManyAttempts(self::dailyCeilingKey(), self::maxCodesPerDay());
    }

    private static function dailyCeilingKey(): string
    {
        return 'self-signup-codes|platform|'.CarbonImmutable::now(self::CAIRO)->format('Y-m-d');
    }

    private static function secondsUntilCairoMidnight(): int
    {
        $now = CarbonImmutable::now(self::CAIRO);

        return max(1, $now->addDay()->startOfDay()->getTimestamp() - $now->getTimestamp());
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
