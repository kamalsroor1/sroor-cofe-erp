<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetTenantDashboardAnalyticsAction;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(
        protected GetTenantDashboardAnalyticsAction $getDashboardAnalyticsAction
    ) {}

    /**
     * Display the rich Inertia Vue 3 Dashboard with Deferred Props and API Resources
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Fast immediate metadata (Active Store)
        $storeId = session('current_store_id');
        $activeStore = null;
        if ($storeId) {
            $activeStore = Store::where('id', $storeId)->where('is_active', true)->first();
        }
        if (!$activeStore && $user) {
            $activeStore = $user->getCurrentStore();
        }

        return Inertia::render('Dashboard', [
            'active_store' => $activeStore ? (new StoreResource($activeStore))->resolve() : null,

            // Heavy calculations & analytics deferred into a single memoized 'dashboardData' group
            'metrics'           => Inertia::defer(fn() => $this->getDashboardAnalyticsAction->execute($user)['metrics'], 'dashboardData'),
            'analytics'         => Inertia::defer(fn() => $this->getDashboardAnalyticsAction->execute($user)['analytics'], 'dashboardData'),
            'recent_invoices'   => Inertia::defer(fn() => $this->getDashboardAnalyticsAction->execute($user)['recent_invoices'], 'dashboardData'),
            'low_stock_items'   => Inertia::defer(fn() => $this->getDashboardAnalyticsAction->execute($user)['low_stock_items'], 'dashboardData'),
            'top_selling_items' => Inertia::defer(fn() => $this->getDashboardAnalyticsAction->execute($user)['top_selling_items'], 'dashboardData'),
            'active_shift'      => Inertia::defer(fn() => $this->getDashboardAnalyticsAction->execute($user)['active_shift'], 'dashboardData'),
        ]);
    }
}
