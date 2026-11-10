<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingPaymentMethod;
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
        public string $payerReference,
        public CarbonImmutable $paidAt,
        public UploadedFile $receipt,
        public ?int $submittedBy,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated SubmitBillingPaymentRequest data (without the file)
     */
    public static function fromArray(array $data, string $tenantId, int $invoiceId, UploadedFile $receipt, ?int $submittedBy): self
    {
        return new self(
            tenantId: $tenantId,
            invoiceId: $invoiceId,
            method: BillingPaymentMethod::from((string) $data['method']),
            amount: Decimal::normalize((string) $data['amount']),
            payerReference: trim((string) $data['payer_reference']),
            paidAt: CarbonImmutable::parse((string) $data['paid_at']),
            receipt: $receipt,
            submittedBy: $submittedBy,
        );
    }
}
