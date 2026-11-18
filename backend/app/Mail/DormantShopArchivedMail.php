<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * FREE-15: a free shop was archived after archive_after_days without activity. The data is kept;
 * the owner contacts support (details in the shared layout) to restore it.
 */
final class DormantShopArchivedMail extends PlatformMailable
{
    public function __construct(
        public readonly string $businessName,
        public readonly int $inactiveDays,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.dormant_archived.subject';
    }

    protected function subjectReplace(): array
    {
        return ['business' => $this->businessName];
    }

    protected function htmlView(): string
    {
        return 'mail.platform.dormant-archived';
    }

    protected function textView(): string
    {
        return 'mail.platform.dormant-archived-text';
    }

    protected function viewData(): array
    {
        return [
            'businessName' => $this->businessName,
            'inactiveDays' => $this->inactiveDays,
        ];
    }
}
