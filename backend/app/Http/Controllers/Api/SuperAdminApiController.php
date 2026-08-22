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
use App\Models\Setting;
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
            \Illuminate\Support\Facades\Log::error('Tenant Provisioning Failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => $this->formatTenantException($e),
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

    /**
     * Get Central Platform Branding & Settings
     */
    public function getPlatformSettings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'platform_name'     => Setting::get('platform_name') ?: Setting::get('app_name') ?: config('app.name', 'منظومة ERP السحابية'),
                'platform_subtitle' => Setting::get('platform_subtitle', 'منظومة سحابية متكاملة لإدارة المبيعات والمخزون والفروع'),
                'support_email'     => Setting::get('support_email', 'support@baraa-solutions.com'),
                'support_phone'     => Setting::get('support_phone', '01000000000'),
            ],
        ]);
    }

    /**
     * Update Central Platform Branding & Settings
     */
    public function updatePlatformSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform_name'     => ['required', 'string', 'max:100'],
            'platform_subtitle' => ['nullable', 'string', 'max:255'],
            'support_email'     => ['nullable', 'email', 'max:100'],
            'support_phone'     => ['nullable', 'string', 'max:50'],
        ]);

        Setting::set('platform_name', $validated['platform_name']);
        Setting::set('app_name', $validated['platform_name']);
        if (isset($validated['platform_subtitle'])) {
            Setting::set('platform_subtitle', $validated['platform_subtitle']);
        }
        if (isset($validated['support_email'])) {
            Setting::set('support_email', $validated['support_email']);
        }
        if (isset($validated['support_phone'])) {
            Setting::set('support_phone', $validated['support_phone']);
        }

        Setting::clearCache();

        return response()->json([
            'success' => true,
            'message' => __('common.success') ?: 'تم حفظ إعدادات واسم المنصة بنجاح ✓',
            'data'    => [
                'platform_name'     => Setting::get('platform_name'),
                'platform_subtitle' => Setting::get('platform_subtitle'),
                'support_email'     => Setting::get('support_email'),
                'support_phone'     => Setting::get('support_phone'),
            ],
        ]);
    }

    /**
     * تحويل الأخطاء البرمجية وقواعد البيانات إلى رسائل عربية واضحة ومفهومة للمستخدم
     */
    private function formatTenantException(\Throwable $e): string
    {
        $message = $e->getMessage();

        // 1. Unknown Database (قاعدة البيانات غير موجودة في هوستنجر)
        if (str_contains($message, 'Unknown database') || str_contains($message, '1049')) {
            preg_match("/database '([^']+)'/", $message, $matches);
            $dbName = $matches[1] ?? 'المحددة';
            return "قاعدة البيانات ($dbName) غير موجودة في MySQL على هوستنجر. يرجى إنشاؤها أولاً من لوحة الاستضافة (Databases) والتأكد من تطابق الاسم.";
        }

        // 2. Access Denied / Missing Privileges (الصلاحيات غير ممنوحة)
        if (str_contains($message, 'Access denied') || str_contains($message, '1044') || str_contains($message, '1045')) {
            return "تعذر الاتصال بقاعدة البيانات بسبب عدم منح الصلاحيات لمستخدم MySQL. يرجى التأكد من ربط المستخدم بالقاعدة في هوستنجر واختيار (All Privileges).";
        }

        // 3. Duplicate domain or slug
        if (str_contains($message, 'Duplicate entry') || str_contains($message, 'UNIQUE constraint')) {
            return "اسم النطاق أو المعرف البرمجي مستخدم بالفعل لمستأجر آخر. يرجى اختيار اسم معرف مختلف.";
        }

        // 4. Connection refused
        if (str_contains($message, 'Connection refused') || str_contains($message, '2002')) {
            return "تعذر الاتصال بخادم MySQL. يرجى التحقق من حالة خادم قواعد البيانات.";
        }

        // Generic fallback without raw SQL keywords
        if (str_contains($message, 'SQLSTATE')) {
            return 'حدث خطأ أثناء إعداد قاعدة بيانات المستأجر. يرجى التأكد من إنشاء قاعدة البيانات في هوستنجر وربط المستخدم بها.';
        }

        return 'تعذر إتمام تهيئة المستأجر: ' . $message;
    }
}
