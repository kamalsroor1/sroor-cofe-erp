<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\SubscriptionPeriodKind;
use App\Enums\TenantStatus;
use Carbon\CarbonImmutable;

/**
 * SELL-02: where a tenant stands against the end of its current trial / paid period at one
 * instant. Built by App\Services\Billing\SubscriptionTimelineService from the lifecycle policy,
 * read by the reminders (tenants:send-lifecycle-reminders) and the SPA subscription banner, so
 * both always agree.
 *
 *  - effectiveStatus: the DERIVED lifecycle status (never blocked here: blocked tenants get no timeline).
 *  - endsAt: end of the trial (kind trial) or of the paid period (kind subscription); null when unknown.
 *  - graceEndsAt: when the full-access grace after endsAt runs out (the tenant becomes read-only);
 *    null while read-only.
 *  - inGrace: endsAt has passed but the tenant still has full access.
 *  - daysLeft: CALENDAR days (app timezone) from today to endsAt's date: 0 on the end day,
 *    negative once it passed; null without endsAt.
 *  - graceDaysLeft: calendar days from today to graceEndsAt's date while in grace, else null.
 */
final readonly class SubscriptionTimeline
{
    public function __construct(
        public SubscriptionPeriodKind $kind,
        public TenantStatus $effectiveStatus,
        public ?CarbonImmutable $endsAt,
        public ?CarbonImmutable $graceEndsAt,
        public bool $inGrace,
        public ?int $daysLeft,
        public ?int $graceDaysLeft,
    ) {}

    public function isReadOnly(): bool
    {
        return $this->effectiveStatus === TenantStatus::ReadOnly;
    }

    /** endsAt's calendar date (app timezone), Y-m-d, or null. */
    public function endDate(): ?string
    {
        return $this->endsAt?->setTimezone((string) config('app.timezone', 'UTC'))->toDateString();
    }
}
