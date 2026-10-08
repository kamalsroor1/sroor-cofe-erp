<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantStatus;
use Carbon\CarbonImmutable;

/**
 * Outcome of TenantLifecyclePolicy::extendTrial() (IDEN-3.1, Q-L4).
 *
 * When allowed, the caller sets `trial_ends_at = $newTrialEndsAt`, stamps
 * `trial_extended_at`, and moves the tenant to `$targetStatus` (trial) if it is not
 * stored as trial already. When refused, `$reasonKey` is a `subscription.*` translation key.
 */
final readonly class TrialExtensionDecision
{
    private function __construct(
        public bool $allowed,
        public ?string $reasonKey,
        public ?CarbonImmutable $newTrialEndsAt,
        public ?TenantStatus $targetStatus,
    ) {}

    public static function allow(CarbonImmutable $newTrialEndsAt): self
    {
        return new self(true, null, $newTrialEndsAt, TenantStatus::Trial);
    }

    public static function deny(string $reasonKey): self
    {
        return new self(false, $reasonKey, null, null);
    }
}
