<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\GetDashboardOverviewAction;
use App\Http\Controllers\Controller;
use App\Support\ActiveStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardApiController extends Controller
{
    public function __construct(
        private readonly GetDashboardOverviewAction $getDashboardOverviewAction
    ) {}

    /**
     * Get real-time consolidated dashboard analytics
     */
    public function index(Request $request, ActiveStore $activeStore): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 401);
        }

        // STOR-2: the active store (or an access-checked store_id); every store only for a verified `all`.
        $storeId = $activeStore->readFilter($request);

        $data = $this->getDashboardOverviewAction->execute($user, $storeId, allStores: $storeId === null);

        return response()->json([
            'success' => true,
            'data' => $data,
            'metrics' => $data['metrics'] ?? [],
        ], 200);
    }
}
