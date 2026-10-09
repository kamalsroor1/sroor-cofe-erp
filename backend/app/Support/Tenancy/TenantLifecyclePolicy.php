<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use InvalidArgumentException;

/**
 * The tenant lifecycle rules (IDEN-3.1). PURE: no clock, no config lookups, no DB.
 *
 * The caller passes the current instant and a TenantLifecycleSnapshot; the durations
 * are constructor arguments (the container fills them from config/tenant_lifecycle.php).
 * That makes every decision reproducible in a unit test and identical in the HTTP
 * middleware (IDEN-3.5), the banner (IDEN-3.8) and the daily sweep (IDEN-3.6).
 *
 * Time-driven moves (all by TenantLifecycleActor::System, deadline inclusive):
 *   trial      → read_only  at trial_ends_at
 *   active     → past_due   at subscription_ends_at
 *   past_due   → read_only  at grace_ends_at, else past_due start + grace days (7)
 *   read_only  → suspended  at read_only start + read-only days (30)
 *   suspended  → archived   at suspension start + retention days (90), never for a
 *                           `violation` suspension (CTO W1 Q2)
 *   cancelled  → archived   at cancellation start + retention days (90)
 * Leaving `archived` (unarchive) is a manual super-admin move only (TenantStatus).
 * A tenant that was never swept is caught up in at most MAX_CATCH_UP_STEPS moves, each
 * one starting when the previous deadline passed (not "now").
 */
final class TenantLifecyclePolicy
{
    /** Longest automatic chain: active → past_due → read_only → suspended → archived. */
    public const MAX_CATCH_UP_STEPS = 4;

    private const SECONDS_PER_DAY = 86400;

    /**
     * @param  ?int  $retentionDays  null disables automatic archiving
     */
    public function __construct(
        #[Config('tenant_lifecycle.past_due_grace_days')]
        private readonly int $pastDueGraceDays,
        #[Config('tenant_lifecycle.read_only_days')]
        private readonly int $readOnlyDays,
        #[Config('tenant_lifecycle.retention_days')]
        private readonly ?int $retentionDays,
        #[Config('tenant_lifecycle.trial_extension_days')]
        private readonly int $trialExtensionDays,
    ) {
        if ($pastDueGraceDays < 0) {
            throw new InvalidArgumentException('tenant_lifecycle.past_due_grace_days must be >= 0.');
        }
        if ($readOnlyDays < 1) {
            throw new InvalidArgumentException('tenant_lifecycle.read_only_days must be >= 1.');
        }
        if ($retentionDays !== null && $retentionDays < 1) {
            throw new InvalidArgumentException('tenant_lifecycle.retention_days must be >= 1 or null.');
        }
        if ($trialExtensionDays < 1) {
            throw new InvalidArgumentException('tenant_lifecycle.trial_extension_days must be >= 1.');
        }
    }

    /**
     * Derive the effective status of a tenant at $now, the moves still to persist, and
     * the next deadline.
     */
    public function evaluate(TenantLifecycleSnapshot $snapshot, CarbonImmutable $now): LifecycleDecision
    {
        $status = $snapshot->status;
        $since = $this->storedSince($snapshot);
        $steps = [];

        $next = $this->nextAutomaticMove($status, $since, $snapshot);

        while ($next !== null && $now->greaterThanOrEqualTo($next[1]) && count($steps) < self::MAX_CATCH_UP_STEPS) {
            $steps[] = new LifecycleTransitionStep($status, $next[0], $next[1]);
            [$status, $since] = $next;
            $next = $this->nextAutomaticMove($status, $since, $snapshot);
        }

        return new LifecycleDecision(
            storedStatus: $snapshot->status,
            effectiveStatus: $status,
            effectiveSince: $since,
            pendingTransitions: $steps,
            nextStatus: $next === null ? null : $next[0],
            nextTransitionAt: $next === null ? null : $next[1],
            daysLeft: $next === null ? null : $this->daysUntil($now, $next[1]),
        );
    }

    /**
     * Whether $actor may move a tenant from $from to $to (the TenantStatus state machine).
     */
    public function canTransition(TenantStatus $from, TenantStatus $to, TenantLifecycleActor $actor): bool
    {
        return $from->canTransitionTo($to, $actor);
    }

    /**
     * The one-time +N-day trial extension by a super-admin (Q-L4): only for a tenant that
     * never paid, has not used it yet, and is (effectively) in trial or read-only.
     * A running trial is extended from its end date; a read-only one from $now.
     */
    public function extendTrial(TenantLifecycleSnapshot $snapshot, CarbonImmutable $now): TrialExtensionDecision
    {
        if ($snapshot->hasEverPaid) {
            return TrialExtensionDecision::deny('subscription.trial_extension.already_paid');
        }

        if ($snapshot->trialExtendedAt !== null) {
            return TrialExtensionDecision::deny('subscription.trial_extension.already_used');
        }

        $effective = $this->evaluate($snapshot, $now)->effectiveStatus;

        if ($effective === TenantStatus::Trial && $snapshot->trialEndsAt !== null) {
            return TrialExtensionDecision::allow($snapshot->trialEndsAt->addDays($this->trialExtensionDays));
        }

        if ($effective === TenantStatus::ReadOnly) {
            return TrialExtensionDecision::allow($now->addDays($this->trialExtensionDays));
        }

        return TrialExtensionDecision::deny('subscription.trial_extension.not_eligible');
    }

    /**
     * When the stored status started. A stored past_due without a stamp started when the
     * paid period ended.
     */
    private function storedSince(TenantLifecycleSnapshot $snapshot): ?CarbonImmutable
    {
        if ($snapshot->statusChangedAt !== null) {
            return $snapshot->statusChangedAt;
        }

        return $snapshot->status === TenantStatus::PastDue ? $snapshot->subscriptionEndsAt : null;
    }

    /**
     * The next time-driven move out of $status, entered at $since.
     *
     * @return array{0: TenantStatus, 1: CarbonImmutable}|null
     */
    private function nextAutomaticMove(TenantStatus $status, ?CarbonImmutable $since, TenantLifecycleSnapshot $snapshot): ?array
    {
        $deadline = match ($status) {
            TenantStatus::Trial => $snapshot->trialEndsAt,
            TenantStatus::Active => $snapshot->subscriptionEndsAt,
            TenantStatus::PastDue => $this->graceDeadline($since, $snapshot->graceEndsAt),
            TenantStatus::ReadOnly => $since?->addDays($this->readOnlyDays),
            TenantStatus::Suspended, TenantStatus::Cancelled => $this->retentionDeadline($status, $since, $snapshot),
            TenantStatus::Archived => null,
        };

        if ($deadline === null) {
            return null;
        }

        $target = match ($status) {
            TenantStatus::Trial, TenantStatus::PastDue => TenantStatus::ReadOnly,
            TenantStatus::Active => TenantStatus::PastDue,
            TenantStatus::ReadOnly => TenantStatus::Suspended,
            default => TenantStatus::Archived,
        };

        return [$target, $deadline];
    }

    /**
     * suspended / cancelled → archived after the retention days. A tenant suspended for a
     * `violation` is never archived automatically (CTO W1 Q2): only a super-admin does it.
     * A cancelled tenant is archived after the same retention whatever its reason.
     */
    private function retentionDeadline(TenantStatus $status, ?CarbonImmutable $since, TenantLifecycleSnapshot $snapshot): ?CarbonImmutable
    {
        if ($this->retentionDays === null) {
            return null;
        }

        if ($status === TenantStatus::Suspended && $snapshot->suspensionReason?->blocksAutomaticArchive() === true) {
            return null;
        }

        return $since?->addDays($this->retentionDays);
    }

    private function graceDeadline(?CarbonImmutable $since, ?CarbonImmutable $graceEndsAt): ?CarbonImmutable
    {
        if ($since === null) {
            return $graceEndsAt;
        }

        if ($graceEndsAt !== null && $graceEndsAt->greaterThan($since)) {
            return $graceEndsAt;
        }

        return $since->addDays($this->pastDueGraceDays);
    }

    /** Whole days from $now to $deadline, rounded up, never negative. */
    private function daysUntil(CarbonImmutable $now, CarbonImmutable $deadline): int
    {
        $seconds = max(0, $deadline->getTimestamp() - $now->getTimestamp());

        return intdiv($seconds + self::SECONDS_PER_DAY - 1, self::SECONDS_PER_DAY);
    }
}
