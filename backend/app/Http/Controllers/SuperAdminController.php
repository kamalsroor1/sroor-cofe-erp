<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Plans\GetSuperAdminPlansDataAction;
use App\Actions\Plans\UpdatePlanAction;
use App\Actions\SuperAdmin\ImpersonateTenantAction;
use App\Actions\Tenants\GetTenantDetailsAction;
use App\Actions\Tenants\GetTenantsIndexDataAction;
use App\Actions\Tenants\OverrideTenantFeatureAction;
use App\Actions\Tenants\ProvisionTenantAction;
use App\Actions\Tenants\ToggleTenantStatusAction;
use App\Contracts\SuperAdminDashboardAnalyticsInterface;
use App\DTOs\CreateTenantDTO;
use App\Http\Requests\ImpersonateTenantRequest;
use App\Http\Requests\OverrideTenantFeatureRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\ToggleTenantStatusRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class SuperAdminController extends Controller
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
        protected ImpersonateTenantAction $impersonateTenantAction
    ) {}

    /**
     * Dashboard Overview of the Multi-Tenant Platform
     */
    public function dashboard(): Response
    {
        return Inertia::render('SuperAdmin/Dashboard', [
            'metrics'        => Inertia::defer(fn() => $this->analyticsService->getPlatformMetrics(), 'superAdminDashboard'),
            'plan_stats'     => Inertia::defer(fn() => $this->analyticsService->getPlanStatistics(), 'superAdminDashboard'),
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
            'plans'   => PlanResource::collection($plans)->resolve(),
            'filters' => [
                'search'  => (string)$request->query('search', ''),
                'status'  => (string)$request->query('status', 'all'),
                'plan_id' => (string)$request->query('plan_id', 'all'),
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
            'plans'          => PlanResource::collection($plans)->resolve(),
            'central_domain' => config('tenancy.central_domains.0', 'makhzani.test'),
        ]);
    }

    /**
     * Store and Auto-Provision New Tenant
     */
    public function storeTenant(StoreTenantRequest $request): RedirectResponse
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
    public function overrideFeature(OverrideTenantFeatureRequest $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        $featureKey = (string)$request->validated('feature_key');

        $this->overrideFeatureAction->execute($tenant, $featureKey);

        return back()->with('success', __('super.feature_updated_success', ['feature' => $featureKey]));
    }

    /**
     * Toggle Tenant Account Status (Active, Suspended, Trial, Expired)
     */
    public function toggleStatus(ToggleTenantStatusRequest $request, string $id): RedirectResponse
    {
        $tenant = Tenant::findOrFail($id);
        $status = (string)$request->validated('status');
        $extendDays = (int)($request->validated('extend_days') ?? 0);

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
    public function updatePlan(UpdatePlanRequest $request, int $id): RedirectResponse
    {
        $plan = Plan::findOrFail($id);
        $this->updatePlanAction->execute($plan, $request->validated());

        return back()->with('success', __('super.plan_updated_success', ['name' => $plan->name]));
    }

    /**
     * Impersonate / Fast Login into a Tenant Account
     */
    public function impersonateTenant(ImpersonateTenantRequest $request, string $id): SymfonyResponse|RedirectResponse
    {
        try {
            $targetUserId = $request->validated('user_id') ? (int)$request->validated('user_id') : null;
            $redirectUrl = $this->impersonateTenantAction->execute($id, $targetUserId);

            return Inertia::location($redirectUrl);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
