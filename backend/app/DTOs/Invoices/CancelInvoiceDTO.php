<?php

declare(strict_types=1);

namespace App\DTOs\Invoices;

final class CancelInvoiceDTO
{
    public function __construct(
        public readonly int $invoice_id,
        public readonly string $reason = 'إلغاء من النظام',
    ) {}

    public static function fromArray(int $invoiceId, array $data): self
    {
        return new self(
            invoice_id: $invoiceId,
            reason: (string)($data['reason'] ?? 'إلغاء من النظام'),
        );
    }
}
