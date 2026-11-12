<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\DTOs\Tenants\DueLifecycleReminder;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Ramsey\Uuid\Uuid;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;

/**
 * SELL-02: write one subscription reminder into the tenant's OWN `notifications` table, for
 * every active user who may act on it (role `admin`, or the `billing.manage` permission
 * directly or through a role).
 *
 * The notification id is a UUIDv5 of (tenant, reminder key, target date, user) and the rows are
 * inserted with insertOrIgnore, so a retry (a crashed run, a racing scheduler, a lost
 * `tenant_notified_at` write) never notifies a user twice. The data holds translation inputs,
 * not text: it is rendered in the reader's locale (App\Actions\System\ListSubscriptionReminderAlertsAction).
 *
 * Tenancy is initialised by hand (not $tenant->run()) so the previous context (central, or
 * another tenant) is restored in `finally`, even when the write throws.
 */
final class NotifyTenantOfLifecycleReminderAction
{
    public const TYPE = 'subscription_reminder';

    public const ACTION_PATH = '/billing';

    private const UUID_NAMESPACE = 'sroor:tenant-lifecycle-reminder';

    /**
     * @return int users notified (rows written or already present)
     */
    public function execute(Tenant $tenant, DueLifecycleReminder $reminder, CarbonImmutable $now): int
    {
        $tenancy = tenancy();
        $previous = $tenancy->initialized ? $tenancy->tenant : null;

        try {
            // Inside the try: a bootstrapper that fails half-way still gets the context restored.
            $tenancy->initialize($tenant);

            return $this->write($tenant, $reminder, $now);
        } finally {
            if ($previous instanceof TenantContract) {
                if ($previous->getTenantKey() !== $tenant->getTenantKey()) {
                    $tenancy->initialize($previous);
                }
            } else {
                $tenancy->end();
            }
        }
    }

    private function write(Tenant $tenant, DueLifecycleReminder $reminder, CarbonImmutable $now): int
    {
        $userIds = User::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereHas('roles', function (Builder $roles): void {
                    $roles->where('name', 'admin')
                        ->orWhereHas('permissions', fn (Builder $permissions) => $permissions->where('name', 'billing.manage'));
                })->orWhereHas('permissions', fn (Builder $permissions) => $permissions->where('name', 'billing.manage'));
            })
            ->orderBy('id')
            ->pluck('id')
            ->all();

        if ($userIds === []) {
            return 0;
        }

        $data = json_encode([
            'kind' => $reminder->kind->value,
            'stage' => $reminder->stage(),
            'days_left' => $reminder->daysLeft,
            'ends_at' => $reminder->endsAt->toIso8601String(),
            'ends_on' => $reminder->targetDate,
            'grace_ends_at' => $reminder->graceEndsAt?->toIso8601String(),
            'action_path' => self::ACTION_PATH,
        ], JSON_THROW_ON_ERROR);

        $morphClass = (new User)->getMorphClass();
        $timestamp = $now->toDateTimeString();
        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'id' => Uuid::uuid5(Uuid::NAMESPACE_URL, implode(':', [
                    self::UUID_NAMESPACE, (string) $tenant->getKey(), $reminder->key(), $reminder->targetDate, (string) $userId,
                ]))->toString(),
                'type' => self::TYPE,
                'notifiable_type' => $morphClass,
                'notifiable_id' => (int) $userId,
                'data' => $data,
                'read_at' => null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        DatabaseNotification::query()->insertOrIgnore($rows);

        return count($rows);
    }
}
