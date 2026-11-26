<?php

declare(strict_types=1);

namespace App\Actions\Central\Notifications;

use App\Models\CentralUser;
use App\Models\PlatformNotification;
use App\Models\PlatformNotificationRead;
use App\Support\Notifications\VisiblePlatformNotifications;

/**
 * #232 UI §4.2: one operator marks one visible item read (idempotent; 404 for an item of a kind
 * they may not see, or an unknown id). Returns the item with `is_read` = true.
 */
final class MarkPlatformNotificationReadAction
{
    public function execute(CentralUser $operator, int $notificationId): PlatformNotification
    {
        $notification = VisiblePlatformNotifications::for($operator)->whereKey($notificationId)->firstOrFail();

        PlatformNotificationRead::query()->firstOrCreate(
            ['platform_notification_id' => (int) $notification->getKey(), 'central_user_id' => (int) $operator->getKey()],
            ['read_at' => now()],
        );

        $notification->setAttribute('is_read', true);

        return $notification;
    }
}
