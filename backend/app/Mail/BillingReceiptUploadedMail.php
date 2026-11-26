<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * #232 spec E / UI §5 (option B): tells the platform operators a shop uploaded a payment receipt.
 * Facts only (tenant, invoice + type, amount + method, upload time, temporary-access end or
 * "manual review"); the receipt itself is never attached (private data, open decision د).
 * Queued after commit (PlatformMailable). Values are passed raw and translated in the mail locale.
 */
final class BillingReceiptUploadedMail extends PlatformMailable
{
    public function __construct(
        public readonly string $tenantName,
        public readonly string $invoiceNumber,
        public readonly string $invoiceType,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $method,
        public readonly string $uploadedAt,
        public readonly ?string $temporaryAccessEndsAt,
        public readonly string $reviewUrl,
        ?string $locale = null,
    ) {
        parent::__construct($locale);
    }

    protected function subjectKey(): string
    {
        return 'mail.billing_receipt.subject';
    }

    protected function subjectReplace(): array
    {
        return ['tenant' => $this->tenantName, 'amount' => $this->amount.' '.$this->currency];
    }

    protected function htmlView(): string
    {
        return 'mail.platform.billing-receipt';
    }

    protected function textView(): string
    {
        return 'mail.platform.billing-receipt-text';
    }

    protected function viewData(): array
    {
        return [
            'tenantName' => $this->tenantName,
            'invoiceNumber' => $this->invoiceNumber,
            'invoiceType' => $this->invoiceType,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'uploadedAt' => $this->uploadedAt,
            'temporaryAccessEndsAt' => $this->temporaryAccessEndsAt,
            'reviewUrl' => $this->reviewUrl,
        ];
    }
}
