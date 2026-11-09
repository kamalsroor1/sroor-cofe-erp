<?php

declare(strict_types=1);

namespace App\Health\Notifications;

use App\Health\Channels\PlatformTelegramChannel;
use Spatie\Health\Notifications\CheckFailedNotification;

/**
 * OPS-7: the failing-health-check alert, mail (as before) plus a Telegram text to the platform
 * operator. Channels come from config('health.notifications.notifications'); the throttle of the
 * parent applies per channel.
 */
final class PlatformCheckFailedNotification extends CheckFailedNotification
{
    public function toTelegram(): string
    {
        $lines = [(string) __('console.health.telegram_title', $this->transParameters())];

        foreach ($this->results as $result) {
            $lines[] = '- '.$result->check->getLabel().': '.$result->getNotificationMessage();
        }

        return implode("\n", $lines);
    }

    /**
     * Mail always; Telegram only once a bot token and chat id are configured.
     *
     * @return array<int, string>
     */
    public function via(): array
    {
        $channels = parent::via();

        if ((string) config('health.telegram.bot_token', '') === '' || (string) config('health.telegram.chat_id', '') === '') {
            return array_values(array_filter($channels, static fn (string $c): bool => $c !== PlatformTelegramChannel::class));
        }

        return $channels;
    }
}
