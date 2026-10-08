<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Enums\TenantAccessLevel;
use App\Enums\TenantStatus;
use Carbon\CarbonImmutable;

/**
 * What the lifecycle policy concluded for one tenant at one instant (IDEN-3.1).
 *
 * `effectiveStatus` is the DERIVED status: the stored status advanced by every
 * time-driven move whose deadline has passed, even if the daily sweep has not
 * persisted it yet. Access enforcement (IDEN-3.5) and the subscription banner
 * (IDEN-3.8) must read the derived status, never the raw column.
 */
final readonly class LifecycleDecision
{
    /**
     * @param  list<LifecycleTransitionStep>  $pendingTransitions  moves from stored to effective, oldest first
     * @param  ?TenantStatus  $nextStatus  next automatic move from the effective status, if any
     * @param  ?CarbonImmutable  $nextTransitionAt  when that move happens (always in the future)
     * @param  ?int  $daysLeft  whole days until $nextTransitionAt, rounded up (null = no deadline)
     */
    public function __construct(
        public TenantStatus $storedStatus,
        public TenantStatus $effectiveStatus,
        public ?CarbonImmutable $effectiveSince,
        public array $pendingTransitions,
        public ?TenantStatus $nextStatus,
        public ?CarbonImmutable $nextTransitionAt,
        public ?int $daysLeft,
    ) {}

    public function access(): TenantAccessLevel
    {
        return $this->effectiveStatus->accessLevel();
    }

    public function canRead(): bool
    {
        return $this->access()->allowsReads();
    }

    public function canWrite(): bool
    {
        return $this->access()->allowsWrites();
    }

    /**
     * HTTP status to refuse a request with (403 / 423), or null when it is allowed.
     * The write allowlist (logout, store switch, receipt upload) is the caller's job.
     */
    public function deniedStatus(bool $isWrite): ?int
    {
        return $this->access()->deniedStatus($isWrite);
    }

    /** True when the stored status is behind and the sweep has moves to persist. */
    public function isDerived(): bool
    {
        return $this->pendingTransitions !== [];
    }

    /** Translation key of the user-facing explanation of the effective status. */
    public function messageKey(): string
    {
        return 'subscription.state_messages.'.$this->effectiveStatus->value;
    }

    /**
     * @return array{days: int}
     */
    public function messageParameters(): array
    {
        return ['days' => $this->daysLeft ?? 0];
    }
}
