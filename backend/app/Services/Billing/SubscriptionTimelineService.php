<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\SubscriptionPeriodKind;
use App\Enums\TenantAccessLevel;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\SubscriptionTimeline;
use App\Support\Tenancy\TenantLifecyclePolicy;
use Carbon\CarbonImmutable;

/**
 * SELL-02: the one place that turns a tenant's lifecycle into "what ends when" for the
 * subscription reminders and the SPA banner. Every status / deadline question is answered by
 * TenantLifecyclePolicy (the same rules as EnsureTenantActive and the daily sweep); this class
 * only picks the relevant end date and counts calendar days.
 *
 * The grace end is NOT recomputed here: it is the policy's next move (→ read_only) evaluated at
 * the end instant, so TENANT_GRACE_DAYS, an explicit grace_ends_at and a stored past_due start
 * are all honoured exactly as the access middleware honours them.
 */
final class SubscriptionTimelineService
{
    private const SECONDS_PER_DAY = 86400;

    public function __construct(private readonly TenantLifecyclePolicy $policy) {}

    /** Null for a blocked tenant (suspended / cancelled / archived): nothing to remind or show. */
    public function forTenant(Tenant $tenant, CarbonImmutable $now): ?SubscriptionTimeline
    {
        $snapshot = $tenant->lifecycleSnapshot();
        $decision = $this->policy->evaluate($snapshot, $now);

        if ($decision->access() === TenantAccessLevel::Blocked) {
            return null;
        }

        $effective = $decision->effectiveStatus;
        $kind = match (true) {
            $effective === TenantStatus::Trial => SubscriptionPeriodKind::Trial,
            in_array($effective, [TenantStatus::Active, TenantStatus::PastDue], true) => SubscriptionPeriodKind::Subscription,
            // read_only: a paid period that ended, else the trial that ended (or none at all).
            default => $snapshot->subscriptionEndsAt !== null ? SubscriptionPeriodKind::Subscription : SubscriptionPeriodKind::Trial,
        };
        $endsAt = $kind === SubscriptionPeriodKind::Trial ? $snapshot->trialEndsAt : $snapshot->subscriptionEndsAt;

        $graceEndsAt = null;
        $inGrace = false;
        $graceDaysLeft = null;
        if ($effective !== TenantStatus::ReadOnly && $endsAt !== null) {
            $graceEndsAt = $this->graceEnd($tenant, $endsAt);
            $inGrace = $now->greaterThanOrEqualTo($endsAt);
            $graceDaysLeft = $inGrace ? max(0, self::calendarDaysBetween($now, $graceEndsAt)) : null;
        }

        return new SubscriptionTimeline(
            kind: $kind,
            effectiveStatus: $effective,
            endsAt: $endsAt,
            graceEndsAt: $graceEndsAt,
            inGrace: $inGrace,
            daysLeft: $endsAt === null ? null : self::calendarDaysBetween($now, $endsAt),
            graceDaysLeft: $graceDaysLeft,
        );
    }

    /**
     * Whole calendar days (app timezone) from $from's date to $to's date: 0 for the same day,
     * negative when $to's date is earlier. Counted on dates, so a DST change never yields 23/25-hour days.
     */
    public static function calendarDaysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $timezone = (string) config('app.timezone', 'UTC');
        $fromDay = CarbonImmutable::parse($from->setTimezone($timezone)->toDateString().' 00:00:00', 'UTC');
        $toDay = CarbonImmutable::parse($to->setTimezone($timezone)->toDateString().' 00:00:00', 'UTC');

        return intdiv($toDay->getTimestamp() - $fromDay->getTimestamp(), self::SECONDS_PER_DAY);
    }

    /** When the full-access grace after $endsAt ends: the policy's move to read_only, seen at $endsAt. */
    private function graceEnd(Tenant $tenant, CarbonImmutable $endsAt): CarbonImmutable
    {
        $atEnd = $this->policy->evaluate($tenant->lifecycleSnapshot(), $endsAt);

        $stillFullAccess = in_array($atEnd->effectiveStatus, [TenantStatus::Trial, TenantStatus::PastDue], true);
        if ($stillFullAccess && $atEnd->nextStatus === TenantStatus::ReadOnly && $atEnd->nextTransitionAt !== null) {
            return $atEnd->nextTransitionAt;
        }

        // No grace days configured: read-only from the end itself.
        return $endsAt;
    }
}
