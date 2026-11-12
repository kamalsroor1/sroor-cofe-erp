<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Actions\Tenants\NotifyTenantOfLifecycleReminderAction;
use App\Enums\ReadOnlyReason;
use App\Enums\SubscriptionPeriodKind;
use App\Http\Middleware\DenyQuickLoginToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscriptionTimelineService;
use Carbon\CarbonImmutable;

/**
 * SELL-02: `subscription_banner` of the SPA bootstrap (GET /system/context), the permanent
 * in-app banner. Null when there is nothing to show or the user may not act on billing.
 *
 * Who: users who may open /billing (`billing.manage`; the `admin` role passes through
 * Gate::before). Everyone else (cashier, storekeeper…) gets null.
 *
 * States (from SubscriptionTimelineService, i.e. the lifecycle policy):
 *  - `trial`     running free trial, ALWAYS shown (days_left = calendar days to the trial end);
 *  - `expiring`  paid period ending within the largest reminder day (config
 *                tenant_lifecycle.reminder_days, 7) incl. the end day; farther away = null;
 *  - `grace`     the trial / paid period ended but the grace days still give full access
 *                (days_left = calendar days to grace_ends_at, when the shop turns view-only);
 *  - `read_only` view-only now (days_left null; `reason` = App\Enums\ReadOnlyReason value).
 * Blocked tenants (suspended / cancelled / archived) never reach /system/context: null.
 */
final class GetSubscriptionBannerAction
{
    public const STATE_TRIAL = 'trial';

    public const STATE_EXPIRING = 'expiring';

    public const STATE_GRACE = 'grace';

    public const STATE_READ_ONLY = 'read_only';

    public function __construct(private readonly SubscriptionTimelineService $timelines) {}

    /**
     * @return array{state: string, kind: string, days_left: int|null, ends_at: string|null, grace_ends_at: string|null, reason: string|null, action_path: string}|null
     */
    public function execute(User $user, ?Tenant $tenant, ?CarbonImmutable $now = null): ?array
    {
        // Same refusal as the /billing routes (DenyQuickLoginToken): never point to a page that 403s.
        if (! $tenant instanceof Tenant || ! $user->can('billing.manage') || DenyQuickLoginToken::isQuickLoginSession($user)) {
            return null;
        }

        $now ??= CarbonImmutable::now();
        $timeline = $this->timelines->forTenant($tenant, $now);
        if ($timeline === null) {
            return null;
        }

        [$state, $daysLeft] = match (true) {
            $timeline->isReadOnly() => [self::STATE_READ_ONLY, null],
            $timeline->inGrace => [self::STATE_GRACE, $timeline->graceDaysLeft],
            $timeline->kind === SubscriptionPeriodKind::Trial && $timeline->daysLeft !== null => [self::STATE_TRIAL, max(0, $timeline->daysLeft)],
            $timeline->kind === SubscriptionPeriodKind::Subscription && $timeline->daysLeft !== null
                && $timeline->daysLeft <= $this->expiringFromDays() => [self::STATE_EXPIRING, max(0, $timeline->daysLeft)],
            default => [null, null],
        };

        if ($state === null) {
            return null;
        }

        return [
            'state' => $state,
            'kind' => $timeline->kind->value,
            'days_left' => $daysLeft,
            'ends_at' => $timeline->endsAt?->toIso8601String(),
            'grace_ends_at' => $timeline->graceEndsAt?->toIso8601String(),
            'reason' => $state === self::STATE_READ_ONLY ? ReadOnlyReason::forTenant($tenant, $now)->value : null,
            'action_path' => NotifyTenantOfLifecycleReminderAction::ACTION_PATH,
        ];
    }

    /** The largest configured reminder day (7): a paid period shows the banner from then on. */
    private function expiringFromDays(): int
    {
        $days = array_filter(array_map('intval', (array) config('tenant_lifecycle.reminder_days', [7, 3, 1])), static fn (int $d): bool => $d > 0);

        return $days === [] ? 7 : max($days);
    }
}
