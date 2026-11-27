<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Models\BillingInvoice;
use Carbon\CarbonImmutable;

/**
 * Input of VerifyManualPaymentAction.
 *
 * `transferredAt` (#232 spec A) is the ACTUAL transfer date-time the super-admin read on the
 * receipt / bank statement, on the platform clock. It starts the paid term of a tenant with no
 * paid term running. Required for manual-gateway payments (enforced by the Action, which knows the
 * method); null for gateway payments, whose term starts at the confirmation moment.
 */
final readonly class VerifyManualPaymentDTO
{
    public function __construct(
        public int $paymentId,
        public ?CarbonImmutable $transferredAt = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated VerifyBillingPaymentRequest data
     */
    public static function fromArray(array $data, int $paymentId): self
    {
        $raw = trim((string) ($data['transferred_at'] ?? ''));
        $tz = BillingInvoice::platformTimezone();

        return new self(
            paymentId: $paymentId,
            transferredAt: $raw === ''
                ? null
                : CarbonImmutable::parse($raw, $tz)->setTimezone((string) config('app.timezone'))->startOfSecond(),
        );
    }
}
