<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * FREE-15: the one reminder a free shop gets after remind_after_days (60) without activity.
 * Says what happens at the archive date and that data is never deleted.
 */
final class DormantShopReminderMail extends PlatformMailable
{
    public function __construct(
        public readonly string $businessName,
        public readonly string $loginUrl,
        public readonly int $inactiveDays,
        public readonly string $archiveOn,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.dormant_reminder.subject';
    }

    protected function subjectReplace(): array
    {
        return ['business' => $this->businessName];
    }

    protected function htmlView(): string
    {
        return 'mail.platform.dormant-reminder';
    }

    protected function textView(): string
    {
        return 'mail.platform.dormant-reminder-text';
    }

    protected function viewData(): array
    {
        return [
            'businessName' => $this->businessName,
            'loginUrl' => $this->loginUrl,
            'inactiveDays' => $this->inactiveDays,
            'archiveOn' => $this->archiveOn,
        ];
    }
}
