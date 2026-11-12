<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Tenants\ResetTenantOwnerPasswordAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ResetTenantOwnerPasswordRequest;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;

/**
 * SELL-01, routes/central.php:
 *   POST /api/v1/super-admin/tenants/{id}/reset-owner-password
 *        super_admin.tenants.manage + recent 2FA (step-up), throttle:6,1
 *
 * 200 with the owner's login identifiers, how the owner was matched (`matched_by`) and a temporary
 * password shown ONCE (never stored in plain, never audited, `Cache-Control: no-store`); the owner
 * must change it at the next sign-in. 404 unknown tenant,
 * 409 workspace not ready / no admin account.
 */
final class TenantOwnerPasswordController extends Controller
{
    public function __construct(
        private readonly ResetTenantOwnerPasswordAction $resetOwnerPassword,
    ) {}

    public function reset(ResetTenantOwnerPasswordRequest $request, string $id): JsonResponse
    {
        $operator = $request->user();
        abort_unless($operator instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        $result = $this->resetOwnerPassword->execute($id, $operator);

        return response()->json([
            'success' => true,
            'message' => __('super.owner_password_reset_success'),
            'data' => [
                'tenant_id' => $id,
                'owner' => [
                    'id' => (int) $result->user->id,
                    'name' => $result->user->name,
                    'email' => $result->user->email,
                    'phone' => $result->user->phone,
                ],
                // `email`: the admin with the shop's email; `fallback`: the oldest active admin.
                'matched_by' => $result->matchedBy,
                'must_change_password' => true,
                'temporary_password' => $result->temporaryPassword,
            ],
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
