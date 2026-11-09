<?php

declare(strict_types=1);

namespace App\Support\Backup;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OPS-04: text-only Telegram alert for a failed backup run (second channel next to the mail).
 *
 * Policy (production-ops rule 8): Telegram reports success or failure, it NEVER carries a
 * dump or an archive. This class only ever calls `sendMessage`, with a fixed translated
 * text plus subject ids and exception CLASS names (the BackupFailedNotification contract:
 * no exception messages, they can name hosts, schemas or paths).
 *
 * Uses the platform bot (services.telegram.bot_token) and backup.tenants.notify_telegram_chat_id,
 * both from .env. It is a no-op when either is empty, and it never throws: an alert failure
 * must not mask the backup failure that triggered it.
 */
final class BackupTelegramAlert
{
    private const REASON_LIMIT = 120;

    /**
     * @param  array<string, string>  $failures  subject => reason (exception class or fixed text)
     */
    public static function send(string $command, array $failures): bool
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('backup.tenants.notify_telegram_chat_id');

        if (! is_string($token) || trim($token) === '' || ! is_string($chatId) || trim($chatId) === '' || $failures === []) {
            return false;
        }

        $lines = [(string) __('console.backups.telegram_alert', [
            'app' => (string) config('app.name'),
            'command' => $command,
            'count' => count($failures),
        ])];

        foreach ($failures as $subject => $reason) {
            $lines[] = '- '.$subject.': '.mb_strimwidth($reason, 0, self::REASON_LIMIT, '...');
        }

        try {
            $response = Http::timeout(10)->asForm()->post('https://api.telegram.org/bot'.trim($token).'/sendMessage', [
                'chat_id' => trim($chatId),
                'text' => implode("\n", $lines),
                'disable_web_page_preview' => true,
            ]);

            return $response->successful();
        } catch (Throwable $e) {
            // Never log the exception message: Guzzle puts the full URL (bot token) in it.
            Log::error('Backup Telegram alert could not be sent', ['exception' => $e::class]);

            return false;
        }
    }
}
