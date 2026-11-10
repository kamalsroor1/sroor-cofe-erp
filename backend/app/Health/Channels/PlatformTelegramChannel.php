<?php

declare(strict_types=1);

namespace App\Health\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OPS-7: plain-text Telegram alert for the PLATFORM OPERATOR (not a shop). Credentials come from
 * config('health.telegram') (env HEALTH_TELEGRAM_BOT_TOKEN / HEALTH_TELEGRAM_CHAT_ID), never from
 * the per-tenant settings that App\Services\TelegramService uses. No-op when unconfigured. A failed
 * send is logged without the token and never breaks the health run.
 */
final class PlatformTelegramChannel
{
    private const LIMIT = 3500;

    public function send(object $notifiable, Notification $notification): void
    {
        $token = (string) config('health.telegram.bot_token', '');
        $chatId = (string) config('health.telegram.chat_id', '');

        if ($token === '' || $chatId === '' || ! method_exists($notification, 'toTelegram')) {
            return;
        }

        $text = mb_strimwidth((string) $notification->toTelegram(), 0, self::LIMIT, '...');

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $text,
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('Platform Telegram alert was rejected.', ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            Log::warning('Platform Telegram alert could not be sent.', ['exception' => $e::class]);
        }
    }
}
