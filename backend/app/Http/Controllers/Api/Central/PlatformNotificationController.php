<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Central\Notifications\ListPlatformNotificationsAction;
use App\Actions\Central\Notifications\MarkAllPlatformNotificationsReadAction;
use App\Actions\Central\Notifications\MarkPlatformNotificationReadAction;
use App\Http\Controllers\Api\Central\Concerns\ResolvesCentralOperator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ListPlatformNotificationsRequest;
use App\Http\Requests\Central\MarkPlatformNotificationsReadRequest;
use App\Http\Resources\Billing\PlatformNotificationResource;
use Illuminate\Http\JsonResponse;

/**
 * Super-admin panel notifications, the bell (#232 spec E / UI §4.2), routes/central.php:
 *   GET  /api/v1/super-admin/notifications             ?unread=1&per_page=20   super_admin.billing.view
 *   POST /api/v1/super-admin/notifications/{id}/read                           super_admin.billing.view
 *   POST /api/v1/super-admin/notifications/read-all                            super_admin.billing.view
 * Read marks are per operator.
 */
final class PlatformNotificationController extends Controller
{
    use ResolvesCentralOperator;

    public function __construct(
        private readonly ListPlatformNotificationsAction $listAction,
        private readonly MarkPlatformNotificationReadAction $markAction,
        private readonly MarkAllPlatformNotificationsReadAction $markAllAction,
    ) {}

    public function index(ListPlatformNotificationsRequest $request): JsonResponse
    {
        $result = $this->listAction->execute($this->operator($request), $request->unreadOnly(), $request->perPage());

        return PlatformNotificationResource::collection($result['items'])
            ->additional(['success' => true, 'unread_count' => $result['unread_count']])
            ->response();
    }

    public function read(MarkPlatformNotificationsReadRequest $request, int $id): JsonResponse
    {
        return (new PlatformNotificationResource($this->markAction->execute($this->operator($request), $id)))
            ->additional(['success' => true])
            ->response();
    }

    public function readAll(MarkPlatformNotificationsReadRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['marked' => $this->markAllAction->execute($this->operator($request))],
        ]);
    }
}
