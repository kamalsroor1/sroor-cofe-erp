<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Dashboard\GetDashboardApiOverviewAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\DashboardOverviewResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardApiController extends Controller
{
    public function __construct(
        protected GetDashboardApiOverviewAction $getDashboardOverviewAction
    ) {}

    /**
     * Get complete consolidated dashboard data in 1 single fast action using API Resources
     */
    public function index(Request $request): JsonResponse
    {
        $storeId = $request->header('X-Store-Id') ?: $request->input('store_id') ?: session('current_store_id');
        $storeId = $storeId ? (int)$storeId : null;

        $data = $this->getDashboardOverviewAction->execute($storeId);

        return (new DashboardOverviewResource($data))->response();
    }
}
