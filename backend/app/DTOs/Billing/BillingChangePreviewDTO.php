<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingCycle;

/**
 * Input of PreviewBillingChangeAction (GET /api/v1/billing/change-preview, #232 UI spec 1.3).
 * The tenant comes from the resolved request, never from the query.
 *
 *  - action `upgrade`: `planId` is the target plan, `renew` adds one full cycle of it after the
 *    current paid end (upgrade + renewal);
 *  - action `renew`: the current plan at `cycle` (`planId` / `renew` ignored).
 */
final readonly class BillingChangePreviewDTO
{
    public const ACTION_UPGRADE = 'upgrade';

    public const ACTION_RENEW = 'renew';

    /** @var list<string> */
    public const ACTIONS = [self::ACTION_UPGRADE, self::ACTION_RENEW];

    public function __construct(
        public string $action,
        public ?int $planId,
        public BillingCycle $cycle,
        public bool $renew,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated BillingChangePreviewRequest data
     */
    public static function fromArray(array $data): self
    {
        $action = (string) $data['action'];
        $upgrade = $action === self::ACTION_UPGRADE;

        return new self(
            action: $action,
            planId: $upgrade && isset($data['plan_id']) ? (int) $data['plan_id'] : null,
            cycle: BillingCycle::from((string) $data['cycle']),
            renew: $upgrade && filter_var($data['renew'] ?? false, FILTER_VALIDATE_BOOL),
        );
    }

    public function isUpgrade(): bool
    {
        return $this->action === self::ACTION_UPGRADE;
    }
}
