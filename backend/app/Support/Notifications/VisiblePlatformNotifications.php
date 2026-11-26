<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\CentralPermission;
use App\Enums\PlatformNotificationType;
use App\Models\CentralUser;
use App\Models\PlatformNotification;
use App\Support\PlatformSuperAdmin;
use Illuminate\Database\Eloquent\Builder;

/**
 * #232 UI §4.2: the panel notification items an operator may see (kinds whose permission they hold),
 * and the per-operator "unread" condition. Query building shared by the notification Actions.
 */
final class VisiblePlatformNotifications
{
    /**
     * @return Builder<PlatformNotification>
     */
    public static function for(CentralUser $operator): Builder
    {
        $types = PlatformNotificationType::visibleWith(
            static fn (CentralPermission $permission): bool => PlatformSuperAdmin::can($operator, $permission),
        );

        return PlatformNotification::query()->whereIn('type', $types === [] ? ['-'] : $types);
    }

    /**
     * @param  Builder<PlatformNotification>  $query
     * @return Builder<PlatformNotification>
     */
    public static function unread(Builder $query, CentralUser $operator): Builder
    {
        return $query->whereDoesntHave('reads', static fn (Builder $reads) => $reads->where('central_user_id', $operator->getKey()));
    }
}
