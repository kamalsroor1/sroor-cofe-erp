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
 *
 * Two shapes come from a support request (IssuePlanChangeInvoiceAction):
 *  - `differenceLine` (type upgrade): ONE plan line whose amount is the difference the admin
 *    decided (no proration); the plan is `planId`;
 *  - `addonLines` (type addon): [{addon_id, quantity, unit_price}], `unit_price` null = the catalog
 *    price of the tenant's plan for the cycle.
 * `fromRequest` skips the one-per-period duplicate check: the request row is the idempotency key.
 * `refuseWhenOpenPlanInvoice` (SELL-06: shop renewal requests, the trial-end command) refuses, under
 * the tenant row lock, while ANY pending/draft plan, renewal or upgrade invoice exists for the tenant.
 */
final readonly class IssueSubscriptionInvoiceDTO
{
    /**
     * @param  list<array{description: string, amount: string}>  $serviceLines
     * @param  array{description: string, amount: string}|null  $differenceLine
     * @param  list<array{addon_id: int, quantity: int, unit_price: string|null}>  $addonLines
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
        public ?array $differenceLine = null,
        public array $addonLines = [],
        public bool $fromRequest = false,
        public bool $refuseWhenOpenPlanInvoice = false,
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
