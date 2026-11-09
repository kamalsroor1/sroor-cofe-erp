<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

/**
 * Input of RejectManualPaymentAction: the reviewer's reason (3-500 characters, shown to the shop).
 */
final readonly class RejectManualPaymentDTO
{
    public function __construct(public int $paymentId, public string $reason) {}

    /**
     * @param  array<string, mixed>  $data  validated RejectBillingPaymentRequest data
     */
    public static function fromArray(array $data, int $paymentId): self
    {
        return new self($paymentId, trim((string) $data['reason']));
    }
}
