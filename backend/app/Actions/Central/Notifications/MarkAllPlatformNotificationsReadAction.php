<?php

declare(strict_types=1);

namespace App\Actions\Central\Notifications;

use App\Models\CentralUser;
use App\Models\PlatformNotificationRead;
use App\Support\Notifications\VisiblePlatformNotifications;
use Illuminate\Support\Facades\DB;

/**
 * #232 UI §4.2: one operator marks every visible unread item read. Returns how many were marked.
 * One central transaction; the unique (notification, operator) key keeps it idempotent.
 */
final class MarkAllPlatformNotificationsReadAction
{
    public function execute(CentralUser $operator): int
    {
        $connection = DB::connection((string) (new PlatformNotificationRead)->getConnectionName());

        return $connection->transaction(function () use ($operator): int {
            $ids = VisiblePlatformNotifications::unread(VisiblePlatformNotifications::for($operator), $operator)->pluck('id');
            $now = now();

            foreach ($ids->chunk(500) as $chunk) {
                PlatformNotificationRead::query()->insertOrIgnore($chunk->map(static fn (mixed $id): array => [
                    'platform_notification_id' => (int) $id,
                    'central_user_id' => (int) $operator->getKey(),
                    'read_at' => $now,
                ])->values()->all());
            }

            return $ids->count();
        });
    }
}
