<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\SuperAdmin\GetPlatformSystemUnitsAction;
use App\Actions\SuperAdmin\UpdatePlatformSystemUnitsAction;
use App\Actions\Tenants\UpdateTenantUnitsAction;
use App\Exceptions\TenantProvisioningException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSystemUnitsRequest;
use App\Http\Requests\UpdateTenantUnitsRequest;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Platform unit catalog and per-tenant units (moved out of SuperAdminApiController, BRND-2),
 * routes/central.php, paths/names/contracts unchanged:
 *   GET  /api/v1/super-admin/units                       super_admin.settings.view
 *   POST /api/v1/super-admin/units                       super_admin.settings.manage + step-up
 *   POST /api/v1/super-admin/tenants/{id}/update-units   super_admin.tenants.manage + step-up
 */
final class PlatformUnitsController extends Controller
{
    public function __construct(
        private readonly GetPlatformSystemUnitsAction $getAction,
        private readonly UpdatePlatformSystemUnitsAction $updateAction,
        private readonly UpdateTenantUnitsAction $updateTenantAction,
    ) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'units' => $this->getAction->execute(),
        ]);
    }

    public function update(UpdateSystemUnitsRequest $request): JsonResponse
    {
        /** @var list<string> $units */
        $units = array_values($request->validated('units'));

        return response()->json([
            'success' => true,
            'message' => __('super.units_updated_success'),
            'units' => $this->updateAction->execute($units, $this->operator($request)),
        ]);
    }

    public function updateTenant(UpdateTenantUnitsRequest $request, string $id): JsonResponse
    {
        $tenant = Tenant::query()->find($id);
        abort_unless($tenant instanceof Tenant, 404, __('super.tenant_not_found'));

        /** @var list<string> $units */
        $units = array_values($request->validated('units'));

        try {
            $units = $this->updateTenantAction->execute($tenant, $units, $this->operator($request));
        } catch (TenantProvisioningException $e) {
            throw $e; // 409 provisioning.workspace_not_ready renders itself
        } catch (Throwable $e) {
            // Nothing was saved (one central transaction); the raw error is logged, never returned.
            Log::error('Tenant units update failed', ['tenant' => $tenant->getKey(), 'exception' => $e]);

            return response()->json([
                'success' => false,
                'message' => __('super.units_save_failed'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('super.units_saved_success'),
            'allowed_units' => $units,
        ]);
    }

    /** AuthenticateCentral guarantees an active CentralUser on every routes/central.php route. */
    private function operator(Request $request): CentralUser
    {
        $user = $request->user();
        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
