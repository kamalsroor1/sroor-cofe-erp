<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingPaymentMethod;
use App\Models\BillingInvoice;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;

/**
 * Input of SubmitManualPaymentAction (PRD-03 / ENTI-3.3): what a shop sends with its receipt.
 */
final readonly class SubmitManualPaymentDTO
{
    public function __construct(
        public string $tenantId,
        public int $invoiceId,
        public BillingPaymentMethod $method,
        public string $amount,
        public ?string $payerReference,
        public CarbonImmutable $paidAt,
        public UploadedFile $receipt,
        public ?int $submittedBy,
        public ?string $note = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated SubmitBillingPaymentRequest data (without the file)
     */
    public static function fromArray(array $data, string $tenantId, int $invoiceId, UploadedFile $receipt, ?int $submittedBy): self
    {
        $tz = BillingInvoice::platformTimezone();

        $ref = trim((string) ($data['payer_reference'] ?? ''));
        if ($ref === '') {
            $ref = trim((string) ($data['reference'] ?? ''));
        }

        $note = trim((string) ($data['note'] ?? ''));
        if ($note === '') {
            $note = trim((string) ($data['notes'] ?? ''));
        }

        return new self(
            tenantId: $tenantId,
            invoiceId: $invoiceId,
            method: BillingPaymentMethod::from((string) $data['method']),
            amount: Decimal::normalize((string) $data['amount']),
            // #232 UI §2: optional; blank = not given (the operator verifies against the bank movement).
            payerReference: $ref === '' ? null : $ref,
            paidAt: CarbonImmutable::parse((string) $data['paid_at'], $tz)->setTimezone((string) config('app.timezone')),
            receipt: $receipt,
            submittedBy: $submittedBy,
            note: $note === '' ? null : $note,
        );
    }
}
