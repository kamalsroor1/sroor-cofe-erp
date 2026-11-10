<?php

declare(strict_types=1);

namespace App\DTOs\Invoices;

final class CancelInvoiceDTO
{
    /**
     * @param  array{user_id?: mixed, pin?: mixed}|null  $override
     */
    public function __construct(
        public readonly int $invoice_id,
        public readonly string $reason = 'إلغاء من النظام',
        public readonly ?array $override = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(int $invoiceId, array $data): self
    {
        $reason = $data['reason'] ?? $data['cancellation_reason'] ?? 'إلغاء من النظام';

        return new self(
            invoice_id: $invoiceId,
            reason: (string) $reason,
            override: isset($data['override']) && is_array($data['override']) ? $data['override'] : null,
        );
    }
}
