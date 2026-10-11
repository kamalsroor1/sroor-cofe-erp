<?php

declare(strict_types=1);

namespace App\Health\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * OPS-5: one mail per failed `backup:tenants` / `backup:restore-tenant` run, listing every
 * subject (central DB or tenant id) that failed and why. Sent synchronously to the
 * platform operator address (backup.tenants.notify_mail): a backup failure must not
 * depend on a healthy queue. Never carries dumps, paths with credentials or secrets.
 *
 * Contract (security audit, W2 lane 3I): a reason is the exception CLASS or a fixed,
 * translated refusal text, never an exception message (those can name DB hosts, schemas,
 * paths or SQL). Callers log the message and pass `$e::class` here.
 */
final class BackupFailedNotification extends Notification
{
    use Queueable;

    /** Longest reason kept per subject (exception messages can be long). */
    private const REASON_LIMIT = 300;

    /**
     * @param  array<string, string>  $failures  subject => reason
     */
    public function __construct(
        public readonly string $command,
        public readonly array $failures,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = (string) config('app.name');

        $message = (new MailMessage)
            ->error()
            ->subject((string) __('console.backups.mail_subject', ['app' => $app, 'count' => count($this->failures)]))
            ->line((string) __('console.backups.mail_intro', ['command' => $this->command, 'count' => count($this->failures)]));

        foreach ($this->failures as $subject => $reason) {
            $message->line((string) __('console.backups.mail_line', [
                'subject' => $subject,
                'reason' => mb_strimwidth($reason, 0, self::REASON_LIMIT, '...'),
            ]));
        }

        return $message->line((string) __('console.backups.mail_outro'));
    }
}
