<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Tenants\RequestTenantArchiveAction;
use App\Actions\Tenants\UnarchiveTenantAction;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ArchiveTenantRequest;
use App\Http\Requests\Central\UnarchiveTenantRequest;
use App\Http\Resources\Central\TenantArchiveResource;
use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OPS-9 tenant archive (routes/central.php, EnsureCentralContext → AuthenticateCentral →
 * can:super_admin.tenants.manage → throttle:6,1 → RequireRecentTwoFactor):
 *
 *   DELETE /api/v1/super-admin/tenants/{id}            202: archive queued (ArchiveTenantJob)
 *   POST   /api/v1/super-admin/tenants/{id}/unarchive  200: back to the previous status
 *
 * "Delete" never drops anything: the database is only ever dropped by `tenants:purge` (CLI).
 * Refusals render themselves (TenantLifecycleException / TenantProvisioningException, 409).
 */
final class TenantArchiveController extends Controller
{
    public function __construct(
        private readonly RequestTenantArchiveAction $requestArchive,
        private readonly UnarchiveTenantAction $unarchive,
    ) {}

    public function destroy(ArchiveTenantRequest $request, string $id): JsonResponse
    {
        $tenant = $this->requestArchive->execute(
            (string) $this->tenant($id)->getKey(),
            $this->operator($request),
            TenantStatus::from((string) $request->validated('expected_status')),
            $this->note($request->validated('note')),
        );

        return $this->respond($tenant, (string) __('super.tenant_archive_queued'), 202);
    }

    public function unarchive(UnarchiveTenantRequest $request, string $id): JsonResponse
    {
        $tenant = $this->unarchive->execute(
            (string) $this->tenant($id)->getKey(),
            $this->operator($request),
            $this->note($request->validated('note')),
        );

        return $this->respond($tenant, (string) __('super.tenant_unarchived'), 200);
    }

    private function respond(Tenant $tenant, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'id' => (string) $tenant->getKey(),
                'status' => (string) $tenant->status,
                'archive' => (new TenantArchiveResource($tenant))->resolve(),
            ],
        ], $status);
    }

    private function note(mixed $note): ?string
    {
        return is_string($note) ? $note : null;
    }

    /** Translated 404 for an unknown tenant id. */
    private function tenant(string $id): Tenant
    {
        $tenant = Tenant::query()->find($id);
        abort_unless($tenant instanceof Tenant, 404, __('super.tenant_not_found'));

        return $tenant;
    }

    private function operator(Request $request): CentralUser
    {
        $user = $request->user();
        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
