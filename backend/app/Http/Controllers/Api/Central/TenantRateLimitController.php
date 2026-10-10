<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Tenants\GetTenantRateLimitAction;
use App\Actions\Tenants\RaiseTenantRateLimitAction;
use App\DTOs\Central\RaiseTenantRateLimitDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\RaiseTenantRateLimitRequest;
use App\Http\Resources\Central\TenantRateLimitOverrideResource;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

/**
 * Temporary per-tenant rate-limit raise (IDEN-4.6 ext), routes/central.php:
 *   GET  /api/v1/super-admin/tenants/{id}/rate-limits   super_admin.tenants.view
 *   POST /api/v1/super-admin/tenants/{id}/rate-limits   super_admin.tenants.manage + step-up
 */
final class TenantRateLimitController extends Controller
{
    public function __construct(
        private readonly GetTenantRateLimitAction $getAction,
        private readonly RaiseTenantRateLimitAction $raiseAction,
    ) {}

    public function show(string $id): JsonResponse
    {
        ['defaults' => $defaults, 'override' => $override] = $this->getAction->execute($this->tenant($id));

        return response()->json([
            'success' => true,
            'data' => [
                'defaults' => $defaults,
                'override' => $override !== null ? (new TenantRateLimitOverrideResource($override))->resolve() : null,
            ],
        ]);
    }

    public function store(RaiseTenantRateLimitRequest $request, string $id): JsonResponse
    {
        $tenant = $this->tenant($id);
        $operator = $request->user();
        abort_unless($operator instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        $override = $this->raiseAction->execute(
            RaiseTenantRateLimitDTO::fromArray((string) $tenant->getKey(), $request->validated()),
            $operator,
        );

        return (new TenantRateLimitOverrideResource($override))
            ->additional(['success' => true, 'message' => __('super.rate_limit_raised_success')])
            ->response()
            ->setStatusCode(201);
    }

    private function tenant(string $id): Tenant
    {
        $tenant = Tenant::query()->find($id);
        abort_unless($tenant instanceof Tenant, 404, __('super.tenant_not_found'));

        return $tenant;
    }
}
