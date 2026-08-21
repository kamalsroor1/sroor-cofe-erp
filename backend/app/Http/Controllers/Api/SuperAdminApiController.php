<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Plans\GetSuperAdminPlansDataAction;
use App\Actions\Plans\UpdatePlanAction;
use App\Actions\Tenants\GetTenantDetailsAction;
use App\Actions\Tenants\GetTenantsIndexDataAction;
use App\Actions\Tenants\OverrideTenantFeatureAction;
use App\Actions\Tenants\ProvisionTenantAction;
use App\Actions\Tenants\ToggleTenantStatusAction;
use App\Contracts\SuperAdminDashboardAnalyticsInterface;
use App\DTOs\CreateTenantDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\OverrideTenantFeatureRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\ToggleTenantStatusRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

final class SuperAdminApiController extends Controller
{
    public function __construct(
        private readonly SuperAdminDashboardAnalyticsInterface $analyticsService,
        private readonly GetTenantsIndexDataAction $getTenantsIndexAction,
        private readonly GetTenantDetailsAction $getTenantDetailsAction,
        private readonly ProvisionTenantAction $provisionTenantAction,
        private readonly ToggleTenantStatusAction $toggleStatusAction,
        private readonly OverrideTenantFeatureAction $overrideFeatureAction,
        private readonly GetSuperAdminPlansDataAction $getPlansDataAction,
        private readonly UpdatePlanAction $updatePlanAction
    ) {}

    /**
     * Platform Executive Dashboard Overview
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'success'        => true,
            'metrics'        => $this->analyticsService->getPlatformMetrics(),
            'plan_stats'     => $this->analyticsService->getPlanStatistics(),
            'recent_tenants' => $this->analyticsService->getRecentTenants(),
        ]);
    }

    /**
     * List all Multi-Tenant Instances
     */
    public function tenants(Request $request): JsonResponse
    {
        $plans = Plan::select('id', 'name', 'slug')->get();
        $data = $this->getTenantsIndexAction->execute($request);

        return response()->json([
            'success' => true,
            'plans'   => PlanResource::collection($plans)->resolve(),
            'tenants' => $data['tenants'],
        ]);
    }

    /**
     * Store and Auto-Provision New Tenant
     */
    public function storeTenant(StoreTenantRequest $request): JsonResponse
    {
        try {
            $dto = CreateTenantDTO::fromArray($request->validated());
            $tenant = $this->provisionTenantAction->execute($dto);

            return response()->json([
                'success' => true,
                'message' => __('super.tenant_created_success', ['name' => $tenant->name]) ?: 'تم إنشاء وتهيئة المستأجر بنجاح ✓',
                'tenant'  => $tenant,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'فشل إنشاء المستأجر: ' . $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Show Tenant Details & Feature Overrides Matrix
     */
    public function showTenant(string $id): JsonResponse
    {
        try {
            $data = $this->getTenantDetailsAction->execute($id);

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'تعذر العثور على المستأجر: ' . $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Toggle Tenant Account Status (Active, Suspended, Trial, Expired)
     */
    public function toggleStatus(ToggleTenantStatusRequest $request, string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $status = (string)$request->validated('status');
            $extendDays = (int)($request->validated('extend_days') ?? 0);

            $this->toggleStatusAction->execute($tenant, $status, $extendDays);

            return response()->json([
                'success' => true,
                'message' => __('super.status_updated_success') ?: 'تم تحديث حالة المستأجر بنجاح ✓',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Toggle Manual Feature Override for Tenant
     */
    public function overrideFeature(OverrideTenantFeatureRequest $request, string $id): JsonResponse
    {
        try {
            $tenant = Tenant::findOrFail($id);
            $featureKey = (string)$request->validated('feature_key');

            $this->overrideFeatureAction->execute($tenant, $featureKey);

            return response()->json([
                'success' => true,
                'message' => __('super.feature_updated_success', ['feature' => $featureKey]) ?: 'تم تحديث الميزة بنجاح ✓',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Plans and Pricing Management
     */
    public function plans(): JsonResponse
    {
        $data = $this->getPlansDataAction->execute();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Update Plan Details & Features
     */
    public function updatePlan(UpdatePlanRequest $request, int $id): JsonResponse
    {
        try {
            $plan = Plan::findOrFail($id);
            $this->updatePlanAction->execute($plan, $request->validated());

            return response()->json([
                'success' => true,
                'message' => __('super.plan_updated_success', ['name' => $plan->name]) ?: 'تم تحديث بيانات الباقة بنجاح ✓',
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
