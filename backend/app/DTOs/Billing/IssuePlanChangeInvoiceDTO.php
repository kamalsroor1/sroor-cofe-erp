<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Support\Money\Decimal;

/**
 * Input of IssuePlanChangeInvoiceAction (console). `amount` + `description` apply to a plan-change
 * request (the DIFFERENCE the admin decided); `quantity` + `unitPrice` apply to an add-on request
 * (null = the request quantity / the catalog price of the tenant's plan).
 *
 * #232: `withRenewal` (plan change on a running paid term) = "upgrade + renewal" (default false =
 * upgrade only); `releaseFounderHold` = the owner reviewed a request held for a tenant with an
 * agreed founder price and issues it anyway.
 */
final readonly class IssuePlanChangeInvoiceDTO
{
    public function __construct(
        public int $requestId,
        public ?string $amount,
        public ?string $description,
        public ?int $quantity,
        public ?string $unitPrice,
        public ?string $notes,
        public bool $withRenewal = false,
        public bool $releaseFounderHold = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated InvoicePlanChangeRequestRequest data
     */
    public static function fromArray(array $data, int $requestId): self
    {
        $text = static function (mixed $value): ?string {
            $value = trim((string) ($value ?? ''));

            return $value === '' ? null : $value;
        };

        return new self(
            requestId: $requestId,
            amount: isset($data['amount']) ? Decimal::normalize((string) $data['amount']) : null,
            description: $text($data['description'] ?? null),
            quantity: isset($data['quantity']) ? (int) $data['quantity'] : null,
            unitPrice: isset($data['unit_price']) ? Decimal::normalize((string) $data['unit_price']) : null,
            notes: $text($data['notes'] ?? null),
            withRenewal: filter_var($data['with_renewal'] ?? false, FILTER_VALIDATE_BOOL),
            releaseFounderHold: filter_var($data['release_founder_hold'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }
}
