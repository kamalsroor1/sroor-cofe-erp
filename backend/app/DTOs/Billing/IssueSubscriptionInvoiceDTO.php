<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;

/**
 * Input of IssueSubscriptionInvoiceAction (PRD-03 / ENTI-3.2, ENTI-3.10).
 *
 * `serviceLines` are one-off lines [{description, amount}] (amount = DECIMAL(12,3) string);
 * `discount` is an absolute amount taken off the subtotal before VAT. `issuedBy` is the
 * central user id, or null when the tenant asked for the invoice itself.
 */
final readonly class IssueSubscriptionInvoiceDTO
{
    /**
     * @param  list<array{description: string, amount: string}>  $serviceLines
     */
    public function __construct(
        public string $tenantId,
        public BillingInvoiceType $type,
        public ?int $planId,
        public ?BillingCycle $cycle,
        public bool $useFounderPrice,
        public array $serviceLines,
        public string $discount,
        public ?string $notes,
        public ?CarbonImmutable $periodStart,
        public ?int $issuedBy,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated IssueTenantInvoiceRequest data
     */
    public static function fromArray(array $data, string $tenantId, ?int $issuedBy): self
    {
        $lines = [];
        foreach ((array) ($data['service_lines'] ?? []) as $line) {
            if (! is_array($line)) {
                continue;
            }
            $lines[] = [
                'description' => trim((string) ($line['description'] ?? '')),
                'amount' => Decimal::normalize((string) ($line['amount'] ?? '0')),
            ];
        }

        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';

        return new self(
            tenantId: $tenantId,
            type: BillingInvoiceType::from((string) $data['type']),
            planId: isset($data['plan_id']) ? (int) $data['plan_id'] : null,
            cycle: isset($data['billing_cycle']) ? BillingCycle::from((string) $data['billing_cycle']) : null,
            useFounderPrice: filter_var($data['use_founder_price'] ?? false, FILTER_VALIDATE_BOOL),
            serviceLines: $lines,
            discount: Decimal::normalize((string) ($data['discount'] ?? '0')),
            notes: $notes === '' ? null : $notes,
            periodStart: isset($data['period_start']) && $data['period_start'] !== ''
                ? CarbonImmutable::parse((string) $data['period_start'])
                : null,
            issuedBy: $issuedBy,
        );
    }
}
