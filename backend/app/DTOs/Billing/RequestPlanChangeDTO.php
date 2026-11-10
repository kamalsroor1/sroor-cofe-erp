<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\PlanChangeRequestKind;

/**
 * Input of RequestPlanChangeAction: what a shop asks support for. The tenant and the requesting
 * user come from the authenticated request, never from the body.
 */
final readonly class RequestPlanChangeDTO
{
    public function __construct(
        public string $tenantId,
        public PlanChangeRequestKind $kind,
        public ?string $requestedPlanCode,
        public ?string $addonKey,
        public int $quantity,
        public BillingCycle $cycle,
        public ?string $notes,
        public ?string $requestedByName,
        public ?int $requestedByUserId,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated RequestPlanChangeRequest data
     */
    public static function fromArray(array $data, string $tenantId, ?string $requestedByName, ?int $requestedByUserId): self
    {
        $kind = PlanChangeRequestKind::from((string) $data['kind']);
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : '';

        return new self(
            tenantId: $tenantId,
            kind: $kind,
            requestedPlanCode: $kind === PlanChangeRequestKind::PlanChange ? trim((string) ($data['requested_plan_code'] ?? '')) : null,
            addonKey: $kind === PlanChangeRequestKind::Addon ? trim((string) ($data['addon_key'] ?? '')) : null,
            quantity: $kind === PlanChangeRequestKind::Addon ? (int) ($data['quantity'] ?? 1) : 1,
            cycle: BillingCycle::from((string) $data['billing_cycle']),
            notes: $notes === '' ? null : $notes,
            requestedByName: $requestedByName,
            requestedByUserId: $requestedByUserId,
        );
    }
}
