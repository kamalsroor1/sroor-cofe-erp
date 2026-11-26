<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingCycle;

/**
 * Input of IssueSelfServiceChangeInvoiceAction (POST /api/v1/billing/change-invoice, #232 UI §1).
 * The tenant and the requesting user come from the authenticated request, never from the body.
 *
 *  - action `upgrade` | `subscribe`: what the shop's screen offered. Both run the plan-change path
 *    (the server picks difference vs full cycle from the tenant's state, like /billing/change-preview);
 *  - `renew`: upgrade + one full cycle of the new plan after the current paid end (running term only).
 */
final readonly class SelfServiceChangeInvoiceDTO
{
    public const ACTION_UPGRADE = 'upgrade';

    public const ACTION_SUBSCRIBE = 'subscribe';

    /** @var list<string> */
    public const ACTIONS = [self::ACTION_UPGRADE, self::ACTION_SUBSCRIBE];

    public function __construct(
        public string $tenantId,
        public string $action,
        public int $planId,
        public BillingCycle $cycle,
        public bool $renew,
        public ?string $requestedByName,
        public ?int $requestedByUserId,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated IssueChangeInvoiceRequest data
     */
    public static function fromArray(array $data, string $tenantId, ?string $requestedByName, ?int $requestedByUserId): self
    {
        return new self(
            tenantId: $tenantId,
            action: (string) $data['action'],
            planId: (int) $data['plan_id'],
            cycle: BillingCycle::from((string) $data['cycle']),
            renew: filter_var($data['renew'] ?? false, FILTER_VALIDATE_BOOL),
            requestedByName: $requestedByName,
            requestedByUserId: $requestedByUserId,
        );
    }
}
