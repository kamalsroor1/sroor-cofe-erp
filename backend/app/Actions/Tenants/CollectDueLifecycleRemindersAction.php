<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\DTOs\Tenants\DueLifecycleReminder;
use App\Enums\TenantStatus;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\Billing\SubscriptionTimelineService;
use Carbon\CarbonImmutable;

/**
 * SELL-02: the subscription reminders due today (central, read-only).
 *
 * A tenant is due when its running trial OR paid period ends, in calendar days (app timezone),
 * in exactly one of config('tenant_lifecycle.reminder_days') (7 / 3 / 1), today (0), or ended
 * yesterday while it still has full access (-1: it has just entered the grace period).
 *
 * Candidates: stored status trial / active / past_due (suspended, cancelled, archived and
 * operator read-only tenants are never reminded), provisioned and not purged, with an end date
 * near today (a cheap pre-filter). The decision itself is SubscriptionTimelineService's, so it
 * agrees with the access middleware and the SPA banner.
 */
final class CollectDueLifecycleRemindersAction
{
    private const CHUNK = 100;

    public function __construct(private readonly SubscriptionTimelineService $timelines) {}

    /**
     * @return list<DueLifecycleReminder>
     */
    public function execute(CarbonImmutable $now): array
    {
        $reminderDays = $this->reminderDays();
        $today = $now->setTimezone((string) config('app.timezone', 'UTC'))->startOfDay();
        // One spare day each side: the exact day count is decided per tenant below.
        $from = $today->subDays(2);
        $to = $today->addDays(max($reminderDays) + 2);

        $tenants = Tenant::query()
            ->provisioned()
            ->whereIn('status', [TenantStatus::Trial->value, TenantStatus::Active->value, TenantStatus::PastDue->value])
            ->where(function ($query) use ($from, $to): void {
                $query->whereBetween('trial_ends_at', [$from, $to])
                    ->orWhereBetween('subscription_ends_at', [$from, $to]);
            })
            ->lazyById(self::CHUNK);

        $due = [];
        $planNames = [];

        foreach ($tenants as $tenant) {
            $timeline = $this->timelines->forTenant($tenant, $now);
            if ($timeline === null || $timeline->isReadOnly() || $timeline->endsAt === null || $timeline->daysLeft === null) {
                continue;
            }

            $days = $timeline->daysLeft;
            $isDue = ($days > 0 && in_array($days, $reminderDays, true))
                || $days === 0
                || ($days === -1 && $timeline->inGrace);
            if (! $isDue) {
                continue;
            }

            $planId = $tenant->plan_id;
            if ($planId !== null && ! array_key_exists($planId, $planNames)) {
                $planNames[$planId] = Plan::query()->whereKey($planId)->value('name');
            }

            $due[] = new DueLifecycleReminder(
                tenantId: (string) $tenant->getKey(),
                tenantName: (string) $tenant->name,
                ownerPhone: $tenant->phone,
                planName: $planId === null ? null : $this->stringOrNull($planNames[$planId]),
                kind: $timeline->kind,
                daysLeft: $days,
                endsAt: $timeline->endsAt,
                graceEndsAt: $timeline->graceEndsAt,
                targetDate: (string) $timeline->endDate(),
            );
        }

        return $due;
    }

    /**
     * Positive whole days from config('tenant_lifecycle.reminder_days'), at least one.
     *
     * @return non-empty-list<int>
     */
    private function reminderDays(): array
    {
        $days = array_values(array_unique(array_filter(
            array_map('intval', (array) config('tenant_lifecycle.reminder_days', [7, 3, 1])),
            static fn (int $day): bool => $day > 0,
        )));

        return $days === [] ? [1] : $days;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
