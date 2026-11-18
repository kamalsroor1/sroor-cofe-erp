<?php

declare(strict_types=1);

namespace App\Actions\System;

use App\Actions\Tenants\NotifyTenantOfLifecycleReminderAction;
use App\DTOs\Tenants\DueLifecycleReminder;
use App\Http\Middleware\DenyQuickLoginToken;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\SubscriptionTimelineService;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SELL-02: the user's latest unread subscription reminder (written by
 * tenants:send-lifecycle-reminders into the tenant `notifications` table) as an entry of the
 * bootstrap `notifications` list the header bell shows (FE-01 shape: type, icon, title,
 * description, link, link_label), rendered in the request locale.
 *
 * Only a reminder about the period that is running NOW is shown (same kind and end date as the
 * current SubscriptionTimeline): after a payment or an extension the stale one disappears.
 * A missing table (a tenant whose migration has not run yet) never breaks the bootstrap.
 */
final class ListSubscriptionReminderAlertsAction
{
    private const SCAN_LIMIT = 10;

    public function __construct(private readonly SubscriptionTimelineService $timelines) {}

    /**
     * @return list<array<string, string>>
     */
    public function execute(User $user, ?Tenant $tenant, ?CarbonImmutable $now = null): array
    {
        // Same refusal as the /billing routes (DenyQuickLoginToken): never link to a page that 403s.
        if (! $tenant instanceof Tenant || ! $user->can('billing.manage') || DenyQuickLoginToken::isQuickLoginSession($user)) {
            return [];
        }

        $timeline = $this->timelines->forTenant($tenant, $now ?? CarbonImmutable::now());
        if ($timeline === null || $timeline->endDate() === null) {
            return [];
        }

        try {
            $notifications = $user->unreadNotifications()
                ->where('type', NotifyTenantOfLifecycleReminderAction::TYPE)
                ->limit(self::SCAN_LIMIT)
                ->get();
        } catch (Throwable $e) {
            Log::warning('Subscription reminders could not be read', ['exception' => $e::class]);

            return [];
        }

        foreach ($notifications as $notification) {
            /** @var DatabaseNotification $notification */
            $data = $notification->data;
            if (($data['kind'] ?? null) !== $timeline->kind->value || ($data['ends_on'] ?? null) !== $timeline->endDate()) {
                continue;
            }

            return [$this->alert($data)];
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function alert(array $data): array
    {
        $kind = (string) $data['kind'];
        $stage = (string) ($data['stage'] ?? '');
        $days = (int) ($data['days_left'] ?? 0);
        // PLAN-10 review: a trial that falls back to the free plan is never threatened with view-only.
        $fallsBack = ($data['falls_back_to_free'] ?? false) === true && $kind === 'trial';
        $group = 'subscription.reminders.'.($fallsBack ? 'trial_fallback' : $kind);

        $titleKey = match ($stage) {
            DueLifecycleReminder::STAGE_TODAY => $group.'.today',
            DueLifecycleReminder::STAGE_GRACE => $group.'.grace',
            default => $days === 1 ? $group.'.tomorrow' : $group.'.days_left',
        };
        $urgent = ! $fallsBack && in_array($stage, [DueLifecycleReminder::STAGE_TODAY, DueLifecycleReminder::STAGE_GRACE], true);

        return [
            'type' => $urgent ? 'danger' : 'warning',
            'icon' => 'alert-triangle',
            'title' => (string) __($titleKey, ['days' => $days]),
            'description' => (string) __($group.'.description'),
            'link' => NotifyTenantOfLifecycleReminderAction::ACTION_PATH,
            'link_label' => (string) __('subscription.reminders.action'),
        ];
    }
}
