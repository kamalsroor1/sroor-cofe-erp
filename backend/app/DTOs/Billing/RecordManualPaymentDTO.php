<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingPaymentMethod;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;

/**
 * Input of RecordManualPaymentAction (OPS-20): a payment the operator received outside the system.
 */
final readonly class RecordManualPaymentDTO
{
    public function __construct(
        public int $invoiceId,
        public BillingPaymentMethod $method,
        public string $amount,
        public ?string $reference,
        public ?string $note,
        public CarbonImmutable $paidAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated RecordBillingPaymentRequest data
     */
    public static function fromArray(array $data, int $invoiceId): self
    {
        $reference = trim((string) ($data['reference'] ?? ''));
        $note = trim((string) ($data['note'] ?? ''));

        return new self(
            invoiceId: $invoiceId,
            method: BillingPaymentMethod::from((string) $data['method']),
            amount: Decimal::normalize((string) $data['amount']),
            reference: $reference === '' ? null : $reference,
            note: $note === '' ? null : $note,
            paidAt: CarbonImmutable::parse((string) $data['paid_at']),
        );
    }
}
