<?php

declare(strict_types=1);

namespace App\Health\Notifications;

use App\Health\Channels\PlatformTelegramChannel;
use Illuminate\Notifications\Notification;

/**
 * SIGN-10: Telegram alert to the platform operator when a queued platform email
 * (App\Mail\PlatformMailable) fails permanently. Carries the mailable class, the recipient
 * DOMAINS and the exception class only: never the address, the body or an exception message.
 */
final class PlatformMailFailedNotification extends Notification
{
    /**
     * @param  list<string>  $recipientDomains
     */
    public function __construct(
        public readonly string $mailable,
        public readonly array $recipientDomains,
        public readonly string $exception,
    ) {}

    /**
     * Telegram only once a bot token and chat id are configured.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ((string) config('health.telegram.bot_token', '') === '' || (string) config('health.telegram.chat_id', '') === '') {
            return [];
        }

        return [PlatformTelegramChannel::class];
    }

    public function toTelegram(): string
    {
        return (string) __('mail.failed.telegram', [
            'mailable' => class_basename($this->mailable),
            'domains' => $this->recipientDomains !== [] ? implode(', ', $this->recipientDomains) : '-',
            'exception' => $this->exception,
        ]);
    }
}
