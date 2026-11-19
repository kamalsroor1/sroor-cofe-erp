<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AccountDeletionRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MOB-05: tell the platform owner (platform Telegram bot) that an account-deletion request arrived.
 *
 * Best effort: no token / chat configured = no-op, and nothing here ever throws. Carries only the
 * request id; the text is translated and every user value is flattened to one line.
 */
final class NotifyPlatformOfAccountDeletionRequestJob implements ShouldQueue
{
    use Dispatchable;
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $accountDeletionRequestId,
    ) {}

    private static function oneLine(?string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', (string) $value)));
    }

    public function handle(): void
    {
        try {
            $token = config('services.telegram.bot_token');
            $chatId = config('legal.account_deletion.telegram_chat_id') ?: config('services.telegram.chat_id');

            if (! is_string($token) || trim($token) === '' || ! is_string($chatId) || trim($chatId) === '') {
                return;
            }

            $request = AccountDeletionRequest::query()->find($this->accountDeletionRequestId);
            if (! $request instanceof AccountDeletionRequest) {
                return;
            }

            $text = (string) __('account_deletion.notify.new_request', [
                'contact' => self::oneLine($request->contact_name),
                'phone' => self::oneLine($request->phone),
                'email' => self::oneLine($request->email) ?: '-',
                'shop' => self::oneLine($request->shop_code) ?: '-',
            ]);

            Http::timeout(10)->asForm()->post('https://api.telegram.org/bot'.trim($token).'/sendMessage', [
                'chat_id' => trim($chatId),
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);
        } catch (Throwable $e) {
            // Never log the message: Guzzle puts the full URL (bot token) in it.
            Log::warning('Account deletion request notification failed', ['exception' => $e::class]);
        }
    }
}
