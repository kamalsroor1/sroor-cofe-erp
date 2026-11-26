<?php

declare(strict_types=1);

namespace App\Actions\Central\Notifications;

use App\Models\CentralUser;
use App\Models\PlatformNotification;
use App\Support\Notifications\VisiblePlatformNotifications;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * #232 UI §4.2: the bell list of one operator, newest first, each item flagged read / unread for
 * THAT operator (`is_read` via withExists), plus the operator's unread count.
 */
final class ListPlatformNotificationsAction
{
    /**
     * @return array{items: LengthAwarePaginator<int, PlatformNotification>, unread_count: int}
     */
    public function execute(CentralUser $operator, bool $unreadOnly, int $perPage): array
    {
        $query = VisiblePlatformNotifications::for($operator)
            ->withExists(['reads as is_read' => static fn (Builder $reads) => $reads->where('central_user_id', $operator->getKey())])
            ->orderByDesc('id');

        if ($unreadOnly) {
            VisiblePlatformNotifications::unread($query, $operator);
        }

        return [
            'items' => $query->paginate(max(1, min(100, $perPage))),
            'unread_count' => VisiblePlatformNotifications::unread(VisiblePlatformNotifications::for($operator), $operator)->count(),
        ];
    }
}
