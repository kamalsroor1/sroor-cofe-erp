<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Tenants\GetTenantOverridesAction;
use App\Actions\Tenants\RemoveTenantFeatureOverrideAction;
use App\Actions\Tenants\RemoveTenantLimitOverrideAction;
use App\Actions\Tenants\SetTenantFeatureOverrideAction;
use App\Actions\Tenants\SetTenantLimitOverrideAction;
use App\DTOs\Central\SetTenantFeatureOverrideDTO;
use App\DTOs\Central\SetTenantLimitOverrideDTO;
use App\Enums\LimitResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RemoveTenantFeatureOverrideRequest;
use App\Http\Requests\Central\RemoveTenantLimitOverrideRequest;
use App\Http\Requests\Central\SetTenantFeatureOverrideRequest;
use App\Http\Requests\Central\SetTenantLimitOverrideRequest;
use App\Http\Resources\Central\TenantOverrideResource;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * PLAN-12: per-shop limit overrides and feature grant / revoke, routes/central.php:
 *   GET    /api/v1/super-admin/tenants/{id}/overrides                   tenants.view
 *   PUT    /api/v1/super-admin/tenants/{id}/limit-overrides/{resource}  tenants.manage + step-up
 *   DELETE /api/v1/super-admin/tenants/{id}/limit-overrides/{resource}  tenants.manage + step-up
 *   PUT    /api/v1/super-admin/tenants/{id}/feature-overrides/{feature} tenants.manage + step-up
 *   DELETE /api/v1/super-admin/tenants/{id}/feature-overrides/{feature} tenants.manage + step-up
 */
final class TenantOverridesController extends Controller
{
    public function __construct(
        private readonly GetTenantOverridesAction $getAction,
        private readonly SetTenantLimitOverrideAction $setLimitAction,
        private readonly RemoveTenantLimitOverrideAction $removeLimitAction,
        private readonly SetTenantFeatureOverrideAction $setFeatureAction,
        private readonly RemoveTenantFeatureOverrideAction $removeFeatureAction,
    ) {}

    public function index(string $id): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->getAction->execute($this->tenant($id))]);
    }

    public function setLimit(SetTenantLimitOverrideRequest $request, string $id): JsonResponse
    {
        $tenant = $this->tenant($id);

        $override = $this->setLimitAction->execute(
            SetTenantLimitOverrideDTO::fromArray((string) $tenant->getKey(), $request->validated()),
            $this->operator($request),
        );

        return (new TenantOverrideResource($override))
            ->additional(['success' => true, 'message' => __('plan_overrides.limit_saved')])
            ->response()
            ->setStatusCode(200); // PUT upsert: always 200 (a bare JsonResource answers 201 for a new row)
    }

    public function removeLimit(RemoveTenantLimitOverrideRequest $request, string $id): JsonResponse
    {
        $tenant = $this->tenant($id);

        $removed = $this->removeLimitAction->execute(
            (string) $tenant->getKey(),
            LimitResource::from((string) $request->validated('resource')),
            $this->operator($request),
        );

        return $this->removedResponse($removed);
    }

    public function setFeature(SetTenantFeatureOverrideRequest $request, string $id): JsonResponse
    {
        $tenant = $this->tenant($id);

        $override = $this->setFeatureAction->execute(
            SetTenantFeatureOverrideDTO::fromArray((string) $tenant->getKey(), $request->validated()),
            $this->operator($request),
        );

        return (new TenantOverrideResource($override))
            ->additional(['success' => true, 'message' => __('plan_overrides.feature_saved')])
            ->response()
            ->setStatusCode(200); // PUT upsert: always 200 (a bare JsonResource answers 201 for a new row)
    }

    public function removeFeature(RemoveTenantFeatureOverrideRequest $request, string $id): JsonResponse
    {
        $tenant = $this->tenant($id);

        $removed = $this->removeFeatureAction->execute(
            (string) $tenant->getKey(),
            (string) $request->validated('feature'),
            $this->operator($request),
        );

        return $this->removedResponse($removed);
    }

    private function removedResponse(bool $removed): JsonResponse
    {
        if (! $removed) {
            return response()->json(['success' => false, 'message' => __('plan_overrides.not_found')], 404);
        }

        return response()->json(['success' => true, 'message' => __('plan_overrides.removed')]);
    }

    private function operator(Request $request): CentralUser
    {
        $operator = $request->user();
        abort_unless($operator instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $operator;
    }

    private function tenant(string $id): Tenant
    {
        $tenant = Tenant::query()->find($id);
        abort_unless($tenant instanceof Tenant, 404, __('super.tenant_not_found'));

        return $tenant;
    }
}
