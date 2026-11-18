<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SignupRequest;
use App\Support\Notifications\PlatformTelegramMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SIGN-12: tell the platform operator (platform Telegram channel) that self-signup needs a human:
 *
 *  - ISSUE_CAP_REACHED: today's automatic-provisioning cap is reached; new verified signups now
 *    wait in the manual queue (sent at most once per Cairo day by the caller);
 *  - ISSUE_PROVISIONING_FAILED: a verified signup could not be provisioned (`failure_code`);
 *  - ISSUE_CODES_CEILING (security review): today's platform-wide ceiling of verification-code
 *    emails is reached; new codes are refused (503) until Cairo midnight (once per day);
 *  - ISSUE_NETWORK_CAP (security review): a client network reached its daily automatic
 *    provisioning cap; its next verified signups wait in the manual queue (once per day).
 *
 * Best effort: never throws. Carries ids only; the text is translated and user input is one-lined.
 */
final class NotifyPlatformOfSelfSignupIssueJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public const ISSUE_CAP_REACHED = 'cap_reached';

    public const ISSUE_PROVISIONING_FAILED = 'provisioning_failed';

    public const ISSUE_CODES_CEILING = 'codes_ceiling';

    public const ISSUE_NETWORK_CAP = 'network_cap';

    public int $tries = 1;

    public function __construct(
        public readonly string $issue,
        public readonly ?int $signupRequestId = null,
        public readonly int $cap = 0,
    ) {}

    public function handle(PlatformTelegramMessenger $telegram): void
    {
        try {
            if (! $telegram->isConfigured()) {
                return;
            }

            $text = match ($this->issue) {
                self::ISSUE_CAP_REACHED => (string) __('self_signup.notify.cap_reached', ['cap' => $this->cap]),
                self::ISSUE_PROVISIONING_FAILED => $this->failureText(),
                self::ISSUE_CODES_CEILING => (string) __('self_signup.notify.codes_ceiling', ['cap' => $this->cap]),
                self::ISSUE_NETWORK_CAP => (string) __('self_signup.notify.network_cap', ['cap' => $this->cap]),
                default => null,
            };

            if ($text !== null && $text !== '') {
                $telegram->send($text);
            }
        } catch (Throwable $e) {
            Log::warning('Self-signup alert failed', ['issue' => $this->issue, 'exception' => $e::class]);
        }
    }

    private function failureText(): ?string
    {
        $request = $this->signupRequestId === null ? null : SignupRequest::query()->find($this->signupRequestId);
        if (! $request instanceof SignupRequest) {
            return null;
        }

        return (string) __('self_signup.notify.provisioning_failed', [
            'id' => $request->id,
            'business' => self::oneLine($request->business_name),
            'phone' => self::oneLine($request->phone),
            'code' => self::oneLine($request->failure_code) ?: '-',
        ]);
    }

    private static function oneLine(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', (string) $value)));
    }
}
