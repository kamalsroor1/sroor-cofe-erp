<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Plans\GetSuperAdminPlansDataAction;
use App\Actions\Plans\UpdatePlanAction;
use App\Actions\Tenants\GetTenantDetailsAction;
use App\Actions\Tenants\GetTenantsIndexDataAction;
use App\Actions\Tenants\OverrideTenantFeatureAction;
use App\Actions\Tenants\ProvisionTenantAction;
use App\Actions\Tenants\RunTenantMigrationsAction;
use App\Actions\Tenants\ToggleTenantStatusAction;
use App\Actions\Tenants\UpdateTenantDatabaseConfigAction;
use App\Contracts\SuperAdminDashboardAnalyticsInterface;
use App\DTOs\CreateTenantDTO;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\OverrideTenantFeatureRequest;
use App\Http\Requests\StoreTenantRequest;
use App\Http\Requests\ToggleTenantStatusRequest;
use App\Http\Requests\UpdatePlanRequest;
use App\Http\Requests\UpdateTenantDatabaseConfigRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\TenantResource;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Legacy platform-console endpoints, mounted by routes/central.php (IDEN-1.4) behind
 * EnsureCentralContext → AuthenticateCentral → granular `can:` (CentralPermission).
 * The authenticated user is always an App\Models\CentralUser. Errors are translated; raw
 * exception messages are logged, never returned.
 */
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
        private readonly UpdatePlanAction $updatePlanAction,
        private readonly CentralAuditLogger $auditLogger,
    ) {}

    /**
     * Platform Executive Dashboard Overview
     */
    public function dashboard(Request $request): JsonResponse
    {
        $mysqlVersion = '8.0';
        try {
            $mysqlVersion = DB::select('SELECT VERSION() as v')[0]->v ?? '8.0';
        } catch (Throwable $e) {
        }

        return response()->json([
            'success' => true,
            'metrics' => $this->analyticsService->getPlatformMetrics(),
            'plan_stats' => $this->analyticsService->getPlanStatistics(),
            'recent_tenants' => $this->analyticsService->getRecentTenants(),
            'system_info' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'db_driver' => config('database.default'),
                'mysql_version' => $mysqlVersion,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Hostinger Cloud / LiteSpeed',
                'storage_writable' => is_writable(storage_path()),
            ],
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
            'plans' => PlanResource::collection($plans)->resolve(),
            'tenants' => $data['tenants'],
        ]);
    }

    /**
     * Store and Auto-Provision New Tenant
     */
    public function storeTenant(StoreTenantRequest $request): JsonResponse
    {
        // Tenant roles/permissions pin the `web` guard (PermissionsSeeder::GUARD), so the
        // `central` default guard of this request does not leak into the tenant DB.
        try {
            $dto = CreateTenantDTO::fromArray($request->validated());
            $tenant = $this->provisionTenantAction->execute($dto);
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed', ['exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => $this->formatTenantException($e),
            ], 422);
        }

        $this->auditLogger->record(
            CentralAuditEvent::TenantCreated,
            ['slug' => $tenant->slug, 'plan_id' => $dto->planId, 'custom_domain' => $dto->customDomain],
            actor: $this->operator($request),
            subject: $tenant,
        );

        // A resource, never the raw model: Tenant carries virtual `tenancy_db_*` attributes.
        return response()->json([
            'success' => true,
            'message' => __('super.tenant_created_success', ['name' => $tenant->name]),
            'tenant' => (new TenantResource($tenant->loadMissing(['plan', 'domains'])))->resolve(),
        ], 201);
    }

    /**
     * Show Tenant Details & Feature Overrides Matrix
     */
    public function showTenant(string $id): JsonResponse
    {
        $tenant = $this->findTenant($id);

        return response()->json([
            'success' => true,
            'data' => $this->getTenantDetailsAction->execute((string) $tenant->getKey()),
        ]);
    }

    /**
     * Legacy status toggle, routed through the tenant state machine (ToggleTenantStatusAction).
     * Refusals render themselves (TenantLifecycleException: 403/409/422 with error_code).
     */
    public function toggleStatus(ToggleTenantStatusRequest $request, string $id): JsonResponse
    {
        $tenant = $this->findTenant($id);
        $reason = $request->validated('reason');
        $note = $request->validated('note');

        $tenant = $this->toggleStatusAction->execute(
            (string) $tenant->getKey(),
            TenantStatus::from((string) $request->validated('status')),
            $this->operator($request),
            (int) ($request->validated('extend_days') ?? 0),
            is_string($reason) ? TenantSuspensionReason::from($reason) : null,
            is_string($note) ? $note : null,
        );

        return response()->json([
            'success' => true,
            'message' => __('super.status_updated_success'),
            'data' => [
                'id' => (string) $tenant->getKey(),
                'status' => (string) $tenant->status,
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'subscription_ends_at' => $tenant->subscription_ends_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Toggle Manual Feature Override for Tenant
     */
    public function overrideFeature(OverrideTenantFeatureRequest $request, string $id): JsonResponse
    {
        $tenant = $this->findTenant($id);
        $featureKey = (string) $request->validated('feature_key');

        $overrides = $this->overrideFeatureAction->execute($tenant, $featureKey);

        $this->auditLogger->record(
            CentralAuditEvent::TenantFeatureOverridden,
            ['feature_key' => $featureKey, 'overrides' => $overrides],
            actor: $this->operator($request),
            subject: $tenant,
        );

        return response()->json([
            'success' => true,
            'message' => __('super.feature_updated_success', ['feature' => $featureKey]),
        ]);
    }

    /**
     * Run migrations specifically for this tenant. Returns a status only: the console
     * output (paths, SQL, connection names) is logged, never returned.
     */
    public function runTenantMigrations(Request $request, string $id, RunTenantMigrationsAction $action): JsonResponse
    {
        $tenant = $this->findTenant($id);

        if (! $action->execute($tenant, $this->operator($request))) {
            return response()->json([
                'success' => false,
                'message' => __('super.migrations_failed'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('super.migrations_completed_success'),
        ]);
    }

    /**
     * Tenant deletion is disabled (OPS-11 / Q-B13): always 403, nothing is deleted.
     * Super admins must suspend the tenant via toggle-status instead.
     */
    public function destroyTenant(string $id): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('super.tenant_delete_disabled'),
        ], 403);
    }

    /**
     * Update Tenant Database Credentials
     */
    public function updateDatabaseConfig(UpdateTenantDatabaseConfigRequest $request, string $id, UpdateTenantDatabaseConfigAction $action): JsonResponse
    {
        $tenant = $this->findTenant($id);
        $action->execute($tenant, $request->validated(), $this->operator($request));

        return response()->json([
            'success' => true,
            'message' => __('super.db_config_updated_success'),
        ]);
    }

    /**
     * Plans and Pricing Management
     */
    public function plans(): JsonResponse
    {
        $data = $this->getPlansDataAction->execute();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Update Plan Details & Features
     */
    public function updatePlan(UpdatePlanRequest $request, int $id): JsonResponse
    {
        $plan = Plan::query()->find($id);
        abort_unless($plan instanceof Plan, 404, __('super.plan_not_found'));

        $validated = $request->validated();
        $this->updatePlanAction->execute($plan, $validated);

        $this->auditLogger->record(
            CentralAuditEvent::PlanUpdated,
            ['changed' => array_keys($validated), 'values' => $validated],
            actor: $this->operator($request),
            subject: $plan,
        );

        return response()->json([
            'success' => true,
            'message' => __('super.plan_updated_success', ['name' => $plan->name]),
        ]);
    }

    /**
     * Map a provisioning failure to a translated, operator-facing message. The raw exception
     * (SQL, credentials, paths) is logged, never returned.
     */
    private function formatTenantException(Throwable $e): string
    {
        $message = $e->getMessage();

        return (string) match (true) {
            str_contains($message, 'Unknown database') || str_contains($message, '1049') => __('super.tenant_db_missing'),
            str_contains($message, 'Access denied') || str_contains($message, '1044') || str_contains($message, '1045') => __('super.tenant_db_access_denied'),
            str_contains($message, 'Duplicate entry') || str_contains($message, 'UNIQUE constraint') => __('super.tenant_identifier_taken'),
            str_contains($message, 'Connection refused') || str_contains($message, '2002') => __('super.tenant_db_unreachable'),
            default => __('super.tenant_provisioning_failed'),
        };
    }

    /** Translated 404 for an unknown tenant id (never the model class or query in the message). */
    private function findTenant(string $id): Tenant
    {
        $tenant = Tenant::query()->find($id);
        abort_unless($tenant instanceof Tenant, 404, __('super.tenant_not_found'));

        return $tenant;
    }

    /** AuthenticateCentral guarantees an active CentralUser on every routes/central.php route. */
    private function operator(Request $request): CentralUser
    {
        $user = $request->user();
        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
