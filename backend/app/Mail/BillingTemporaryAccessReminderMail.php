<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * #232 spec E / UI §5 (option B): 6 hours before a shop's 48-hour temporary access ends without a
 * review decision, reminds the platform operators. Sent once per window
 * (billing_invoices.temporary_access_reminder_sent_at). Queued after commit (PlatformMailable).
 */
final class BillingTemporaryAccessReminderMail extends PlatformMailable
{
    public function __construct(
        public readonly string $tenantName,
        public readonly string $invoiceNumber,
        public readonly string $amount,
        public readonly string $currency,
        public readonly ?string $method,
        public readonly string $endsAt,
        public readonly string $reviewUrl,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.billing_grace_reminder.subject';
    }

    protected function subjectReplace(): array
    {
        return ['tenant' => $this->tenantName];
    }

    protected function htmlView(): string
    {
        return 'mail.platform.billing-grace-reminder';
    }

    protected function textView(): string
    {
        return 'mail.platform.billing-grace-reminder-text';
    }

    protected function viewData(): array
    {
        return [
            'tenantName' => $this->tenantName,
            'invoiceNumber' => $this->invoiceNumber,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'endsAt' => $this->endsAt,
            'reviewUrl' => $this->reviewUrl,
        ];
    }
}
