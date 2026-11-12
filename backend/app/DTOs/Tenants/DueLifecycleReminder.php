<?php

declare(strict_types=1);

namespace App\DTOs\Tenants;

use App\Enums\SubscriptionPeriodKind;
use Carbon\CarbonImmutable;

/**
 * SELL-02: one subscription reminder due today (tenants:send-lifecycle-reminders).
 *
 * daysLeft is in calendar days (app timezone): N (a configured reminder day), 0 (ends today)
 * or -1 (ended yesterday: the grace period has just started).
 */
final readonly class DueLifecycleReminder
{
    public const STAGE_TODAY = 'today';

    public const STAGE_GRACE = 'grace';

    public function __construct(
        public string $tenantId,
        public string $tenantName,
        public ?string $ownerPhone,
        public ?string $planName,
        public SubscriptionPeriodKind $kind,
        public int $daysLeft,
        public CarbonImmutable $endsAt,
        public ?CarbonImmutable $graceEndsAt,
        public string $targetDate,
    ) {}

    /** `d7` / `d3` / `d1`, `today` or `grace`. */
    public function stage(): string
    {
        return match (true) {
            $this->daysLeft > 0 => 'd'.$this->daysLeft,
            $this->daysLeft === 0 => self::STAGE_TODAY,
            default => self::STAGE_GRACE,
        };
    }

    /** The `tenant_lifecycle_reminders.kind` value, e.g. `trial.d3`. */
    public function key(): string
    {
        return $this->kind->value.'.'.$this->stage();
    }

    /**
     * @return array{tenant_id: string, tenant_name: string, kind: string, stage: string, days_left: int, target_date: string}
     */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'tenant_name' => $this->tenantName,
            'kind' => $this->kind->value,
            'stage' => $this->stage(),
            'days_left' => $this->daysLeft,
            'target_date' => $this->targetDate,
        ];
    }
}
