<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Contracts\SuperAdminDashboardAnalyticsInterface;
use App\Actions\Tenants\GetTenantsIndexDataAction;
use App\Actions\Tenants\GetTenantDetailsAction;
use App\Actions\Tenants\ProvisionTenantAction;
use App\Actions\Tenants\ToggleTenantStatusAction;
use App\Actions\Tenants\OverrideTenantFeatureAction;
use App\Actions\Plans\GetSuperAdminPlansDataAction;
use App\Actions\Plans\UpdatePlanAction;
use App\DTOs\CreateTenantDTO;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Models\Tenant;
use App\Models\Plan;

class SuperAdminController extends Controller
{
    public function __construct(
        protected SuperAdminDashboardAnalyticsInterface $analyticsService,
        protected GetTenantsIndexDataAction $getTenantsIndexAction,
        protected GetTenantDetailsAction $getTenantDetailsAction,
        protected ProvisionTenantAction $provisionTenantAction,
        protected ToggleTenantStatusAction $toggleStatusAction,
        protected OverrideTenantFeatureAction $overrideFeatureAction,
        protected GetSuperAdminPlansDataAction $getPlansDataAction,
        protected UpdatePlanAction $updatePlanAction,
        protected \App\Actions\SuperAdmin\ImpersonateTenantAction $impersonateTenantAction
    ) {}

    /**
     * Dashboard Overview of the Multi-Tenant Platform
     */
    public function dashboard(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            'metrics' => Inertia::defer(fn() => $this->analyticsService->getPlatformMetrics(), 'superAdminDashboard'),
            'plan_stats' => Inertia::defer(fn() => $this->analyticsService->getPlanStatistics(), 'superAdminDashboard'),
            'recent_tenants' => Inertia::defer(fn() => $this->analyticsService->getRecentTenants(), 'superAdminDashboard'),
        ]);
    }

    /**
     * List all Multi-Tenant Instances
     */
    public function tenants(Request $request): Response
    {
        $plans = Plan::select('id', 'name', 'slug')->get();

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'plans' => \App\Http\Resources\PlanResource::collection($plans)->resolve(),
            'filters' => [
                'search' => $request->query('search', ''),
                'status' => $request->query('status', 'all'),
                'plan_id' => $request->query('plan_id', 'all'),
            ],
            'tenants' => Inertia::defer(fn() => $this->getTenantsIndexAction->execute($request)['tenants'], 'tenantsData'),
        ]);
    }

    /**
     * Show Create Tenant Form
     */
    public function createTenant(): Response
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return Inertia::render('SuperAdmin/Tenants/Create', [
            'plans' => $plans,
            'central_domain' => env('CENTRAL_DOMAIN', 'makhzani.test'),
        ]);
    }

    /**
     * Store and Auto-Provision New Tenant
     */
    public function storeTenant(StoreTenantRequest $request)
    {
        $dto = CreateTenantDTO::fromArray($request->validated());
        $tenant = $this->provisionTenantAction->execute($dto);

        return redirect()->route('super.tenants.show', $tenant->id)
            ->with('success', __('super.tenant_created_success', ['name' => $tenant->name]));
    }

    /**
     * Tenant Details & Feature Overrides Matrix
     */
    public function showTenant(string $id): Response
    {
        $data = $this->getTenantDetailsAction->execute($id);

        return Inertia::render('SuperAdmin/Tenants/Show', $data);
    }

    /**
     * Toggle Manual Feature Override for Tenant
     */
    public function overrideFeature(Request $request, string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $featureKey = (string)$request->input('feature_key');

        $this->overrideFeatureAction->execute($tenant, $featureKey);

        return back()->with('success', __('super.feature_updated_success', ['feature' => $featureKey]));
    }

    /**
     * Toggle Tenant Account Status (Active, Suspended, Trial)
     */
    public function toggleStatus(Request $request, string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $status = (string)$request->input('status');
        $extendDays = (int)$request->input('extend_days', 0);

        $this->toggleStatusAction->execute($tenant, $status, $extendDays);

        return back()->with('success', __('super.status_updated_success'));
    }

    /**
     * Plans and Pricing Management
     */
    public function plans(): Response
    {
        $data = $this->getPlansDataAction->execute();

        return Inertia::render('SuperAdmin/Plans/Index', $data);
    }

    /**
     * Update Plan Details & Features
     */
    public function updatePlan(UpdatePlanRequest $request, int $id)
    {
        $plan = Plan::findOrFail($id);
        $this->updatePlanAction->execute($plan, $request->validated());

        return back()->with('success', __('super.plan_updated_success', ['name' => $plan->name]));
    }

    /**
     * Impersonate / Fast Login into a Tenant Account
     */
    public function impersonateTenant(Request $request, string $id)
    {
        try {
            $targetUserId = $request->input('user_id') ? (int)$request->input('user_id') : null;
            $redirectUrl = $this->impersonateTenantAction->execute($id, $targetUserId);

            return Inertia::location($redirectUrl);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
