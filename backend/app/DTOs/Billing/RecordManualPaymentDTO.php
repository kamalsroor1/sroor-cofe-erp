<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingPaymentMethod;
use App\Models\BillingInvoice;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;

/**
 * Input of RecordManualPaymentAction (OPS-20): a payment the operator received outside the system.
 * `idempotencyKey` is the optional per-dialog UUID the console sends (lower-cased).
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
        public ?string $idempotencyKey = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated RecordBillingPaymentRequest data
     */
    public static function fromArray(array $data, int $invoiceId): self
    {
        $tz = BillingInvoice::platformTimezone();

        $reference = trim((string) ($data['reference'] ?? ''));
        if ($reference === '') {
            $reference = trim((string) ($data['payer_reference'] ?? ''));
        }

        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '') {
            $note = trim((string) ($data['notes'] ?? ''));
        }

        $key = mb_strtolower(trim((string) ($data['idempotency_key'] ?? '')));

        return new self(
            invoiceId: $invoiceId,
            method: BillingPaymentMethod::from((string) $data['method']),
            amount: Decimal::normalize((string) $data['amount']),
            reference: $reference === '' ? null : $reference,
            note: $note === '' ? null : $note,
            paidAt: CarbonImmutable::parse((string) $data['paid_at'], $tz)->setTimezone((string) config('app.timezone')),
            idempotencyKey: $key === '' ? null : $key,
        );
    }
}
