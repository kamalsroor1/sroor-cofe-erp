<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantStatus;
use Carbon\CarbonImmutable;

/**
 * The lifecycle facts of one tenant, as stored (IDEN-3.1). Input of TenantLifecyclePolicy.
 *
 * Built by the caller from the central `tenants` row. Keeping it a plain value object is
 * what keeps the policy pure.
 *
 *  - statusChangedAt: when the stored status was entered (`tenants.status_changed_at`).
 *    It anchors the read-only (30 days) and retention (90 days) clocks. When null, those
 *    clocks do not run: a tenant is never locked out automatically on missing data.
 *  - trialEndsAt / subscriptionEndsAt: end of the trial / of the paid period.
 *  - graceEndsAt: explicit end of the past_due grace; ignored when it predates the moment
 *    past_due started (a leftover from an earlier billing cycle).
 *  - trialExtendedAt: set once the one-time +7-day extension was used (Q-L4).
 *  - hasEverPaid: true after the first verified payment; a paying tenant cannot get a
 *    trial extension.
 *  - suspensionReason: why the tenant was suspended/cancelled (`tenants.suspension_reason`,
 *    CTO W1 Q2). A `violation` suspension is never archived automatically.
 *
 * Build it from a central row with App\Models\Tenant::lifecycleSnapshot() (IDEN-3.2).
 */
final readonly class TenantLifecycleSnapshot
{
    public function __construct(
        public TenantStatus $status,
        public ?CarbonImmutable $statusChangedAt = null,
        public ?CarbonImmutable $trialEndsAt = null,
        public ?CarbonImmutable $subscriptionEndsAt = null,
        public ?CarbonImmutable $graceEndsAt = null,
        public ?CarbonImmutable $trialExtendedAt = null,
        public bool $hasEverPaid = false,
        public ?TenantSuspensionReason $suspensionReason = null,
    ) {}
}
