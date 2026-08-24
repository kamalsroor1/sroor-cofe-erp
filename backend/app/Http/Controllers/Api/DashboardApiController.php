<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\GetDashboardOverviewAction;
use App\Http\Controllers\Controller;
use App\Models\Store;
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
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['success' => false, 'message' => __('auth.unauthorized')], 401);
        }

        $storeId = $request->header('X-Store-Id')
            ?: $request->input('store_id')
            ?: $user->getCurrentStore()?->id
            ?: Store::getMainStore()?->id;

        $storeId = $storeId ? (int)$storeId : null;

        $data = $this->getDashboardOverviewAction->execute($user, $storeId);

        return response()->json([
            'success' => true,
            'data'    => $data,
            'metrics' => $data['metrics'] ?? [],
        ], 200);
    }
}
