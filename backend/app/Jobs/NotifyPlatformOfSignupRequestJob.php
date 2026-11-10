<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\SignupRequestStatus;
use App\Models\SignupRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * PRD-05: tell the platform owner (platform Telegram bot) that a signup request arrived.
 *
 * Best effort: no token / chat configured = no-op, and nothing here ever throws, so the
 * public request is never affected. Carries only the request id; the text is translated.
 */
final class NotifyPlatformOfSignupRequestJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $signupRequestId, public readonly bool $backlogSummary = false) {}

    /** User text on one line, so an applicant cannot forge extra lines in the alert. */
    private static function oneLine(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', (string) $value)));
    }

    public function handle(): void
    {
        try {
            $token = config('services.telegram.bot_token');
            $chatId = config('signup.notify.telegram_chat_id') ?: config('services.telegram.chat_id');

            if (! is_string($token) || trim($token) === '' || ! is_string($chatId) || trim($chatId) === '') {
                return;
            }

            $request = SignupRequest::query()->find($this->signupRequestId);
            if (! $request instanceof SignupRequest) {
                return;
            }

            $text = $this->backlogSummary
                ? (string) __('signup.notify.backlog', [
                    'count' => SignupRequest::query()->where('status', SignupRequestStatus::Pending->value)->count(),
                ])
                : (string) __('signup.notify.new_request', [
                    'business' => self::oneLine($request->business_name),
                    'contact' => self::oneLine($request->contact_name),
                    'phone' => self::oneLine($request->phone),
                    'plan' => self::oneLine($request->plan_code) ?: '-',
                ]);

            Http::timeout(10)->asForm()->post('https://api.telegram.org/bot'.trim($token).'/sendMessage', [
                'chat_id' => trim($chatId),
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            // Never log the message: Guzzle puts the full URL (bot token) in it.
            Log::warning('Signup request notification failed', ['exception' => $e::class]);
        }
    }
}
