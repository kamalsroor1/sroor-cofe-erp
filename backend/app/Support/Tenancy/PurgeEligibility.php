<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * OPS-9 (CTO Q3): may this tenant be purged? Pure: every fact and the clock are passed in.
 *
 *  - archived  for at least `archived_min_days` (anchor: archived_at, else status_changed_at);
 *  - suspended / cancelled ("blocked") for at least `blocked_min_days` (anchor: status_changed_at);
 *  - a `violation` suspension is NEVER purged (evidence is kept), whatever the status;
 *  - every other status (trial, active, past_due, read_only, unknown) is never eligible;
 *  - an already purged / purging tenant is not "eligible" (the purge command resumes it).
 *
 * `eligibleAt` is the earliest moment the clock rule is satisfied (null when no amount of
 * waiting makes the tenant eligible), shown on the super-admin tenant page.
 */
final readonly class PurgeEligibility
{
    public const REASON_PURGED = 'purged';

    public const REASON_VIOLATION = 'violation';

    public const REASON_STATUS = 'status_not_eligible';

    public const REASON_TOO_RECENT = 'too_recent';

    public const REASON_NO_ANCHOR = 'no_anchor';

    private function __construct(
        public bool $eligible,
        public ?string $reason,
        public ?CarbonImmutable $eligibleAt,
    ) {}

    public static function evaluate(
        ?TenantStatus $status,
        ?TenantSuspensionReason $suspensionReason,
        ?CarbonInterface $archivedAt,
        ?CarbonInterface $statusChangedAt,
        bool $purged,
        CarbonInterface $now,
        int $archivedMinDays,
        int $blockedMinDays,
    ): self {
        if ($purged) {
            return new self(false, self::REASON_PURGED, null);
        }

        if ($suspensionReason === TenantSuspensionReason::Violation) {
            return new self(false, self::REASON_VIOLATION, null);
        }

        [$anchor, $days] = match ($status) {
            TenantStatus::Archived => [$archivedAt ?? $statusChangedAt, $archivedMinDays],
            TenantStatus::Suspended, TenantStatus::Cancelled => [$statusChangedAt, $blockedMinDays],
            default => [null, null],
        };

        if ($days === null) {
            return new self(false, self::REASON_STATUS, null);
        }

        if ($anchor === null) {
            return new self(false, self::REASON_NO_ANCHOR, null);
        }

        $eligibleAt = CarbonImmutable::instance($anchor)->addDays(max(0, $days));

        if ($eligibleAt->greaterThan(CarbonImmutable::instance($now))) {
            return new self(false, self::REASON_TOO_RECENT, $eligibleAt);
        }

        return new self(true, null, $eligibleAt);
    }

    /** The rule applied to a tenant row with the configured durations. */
    public static function forTenant(Tenant $tenant, CarbonInterface $now): self
    {
        return self::evaluate(
            $tenant->lifecycleStatus(),
            $tenant->suspension_reason,
            $tenant->archived_at,
            $tenant->status_changed_at,
            $tenant->isPurged(),
            $now,
            (int) config('tenant_lifecycle.purge.archived_min_days', 30),
            (int) config('tenant_lifecycle.purge.blocked_min_days', 90),
        );
    }
}
