<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SELL-02: plain-text message to the PLATFORM OPERATOR through the same Telegram channel as the
 * signup alert (App\Jobs\NotifyPlatformOfSignupRequestJob): the platform bot
 * services.telegram.bot_token, chat signup.notify.telegram_chat_id, else services.telegram.chat_id.
 * Never the per-shop bot of App\Services\TelegramService.
 *
 * send() never throws and never logs the exception message (Guzzle puts the URL, with the bot
 * token, in it). It returns true only when Telegram accepted the message.
 */
final class PlatformTelegramMessenger
{
    /** Below Telegram's 4096-character limit. */
    public const MAX_LENGTH = 3800;

    public function isConfigured(): bool
    {
        return $this->token() !== null && $this->chatId() !== null;
    }

    public function send(string $text): bool
    {
        $token = $this->token();
        $chatId = $this->chatId();
        if ($token === null || $chatId === null) {
            return false;
        }

        try {
            $response = Http::timeout(10)->asForm()->post('https://api.telegram.org/bot'.$token.'/sendMessage', [
                'chat_id' => $chatId,
                'text' => mb_strimwidth($text, 0, self::MAX_LENGTH, '...'),
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('Platform Telegram message was rejected', ['status' => $response->status()]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning('Platform Telegram message could not be sent', ['exception' => $e::class]);

            return false;
        }
    }

    private function token(): ?string
    {
        $token = config('services.telegram.bot_token');

        return is_string($token) && trim($token) !== '' ? trim($token) : null;
    }

    private function chatId(): ?string
    {
        $chatId = config('signup.notify.telegram_chat_id') ?: config('services.telegram.chat_id');

        return is_string($chatId) && trim($chatId) !== '' ? trim($chatId) : null;
    }
}
