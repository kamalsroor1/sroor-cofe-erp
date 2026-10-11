<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Tenants\RetryTenantProvisioningAction;
use App\Enums\CentralPermission;
use App\Http\Controllers\Controller;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Support\PlatformSuperAdmin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Queued tenant provisioning (OPS-2), routes/central.php:
 *   POST /api/v1/super-admin/tenants/{id}/retry-provisioning
 *        super_admin.tenants.manage, throttle:6,1
 *
 * 202 + the new status (`pending`); 409 `provisioning.retry_not_allowed` unless the
 * provisioning failed or is stale (pending/running without activity for the unique window,
 * config tenancy.provisioning.unique_for); 409 `provisioning.retry_unavailable` without a stored seed; 404 unknown.
 */
final class TenantProvisioningController extends Controller
{
    public function __construct(
        private readonly RetryTenantProvisioningAction $retryAction,
    ) {}

    public function retry(Request $request, string $id): JsonResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof CentralUser, 401, __('central_auth.unauthenticated'));
        abort_unless(PlatformSuperAdmin::can($operator, CentralPermission::TenantsManage), 403, __('provisioning.forbidden'));
        abort_unless(Tenant::query()->whereKey($id)->exists(), 404, __('super.tenant_not_found'));

        $tenant = $this->retryAction->execute($id, $operator);

        return response()->json([
            'success' => true,
            'message' => __('provisioning.retry_queued'),
            'data' => [
                'tenant_id' => (string) $tenant->getTenantKey(),
                'provisioning_status' => $tenant->provisioningStatus()->value,
                'provisioning_attempts' => (int) $tenant->provisioning_attempts,
            ],
        ], 202);
    }
}
