<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Signup\ProvisionVerifiedSignupAction;
use App\Actions\Signup\SuspendSignupTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signup\ProvisionSignupRequestRequest;
use App\Http\Requests\Signup\SuspendSignupTenantRequest;
use App\Http\Resources\Central\SignupRequestResource;
use App\Models\CentralUser;
use App\Models\SignupRequest;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SIGN-12: console actions on self-signups (routes/central.php), both super_admin.tenants.manage +
 * recent 2FA:
 *
 *   POST /api/v1/super-admin/signup-requests/{id}/provision       release a verified self-signup
 *        held in the manual queue (daily cap): same provisioning as the automatic path, owner's
 *        own password, the ready email follows. 409 when not a held self-signup.
 *   POST /api/v1/super-admin/signup-requests/{id}/suspend-tenant  {reason, note?}: suspend the
 *        shop created from the signup through the existing tenant suspension. 409 without a shop.
 */
final class SignupRequestProvisioningController extends Controller
{
    public function __construct(
        private readonly ProvisionVerifiedSignupAction $provisionVerifiedSignup,
        private readonly SuspendSignupTenantAction $suspendSignupTenant,
    ) {}

    public function provision(ProvisionSignupRequestRequest $request, int $id): JsonResponse
    {
        $signup = $this->provisionVerifiedSignup->execute($id, $this->operator($request));

        return response()->json([
            'success' => true,
            'message' => __('self_signup.console.provisioning_started'),
            'data' => (new SignupRequestResource($signup))->resolve($request),
        ]);
    }

    public function suspendTenant(SuspendSignupTenantRequest $request, int $id): JsonResponse
    {
        $note = $request->validated('note');

        $tenant = $this->suspendSignupTenant->execute(
            $id,
            TenantSuspensionReason::from((string) $request->validated('reason')),
            is_string($note) ? $note : null,
            $this->operator($request),
        );

        return response()->json([
            'success' => true,
            'message' => __('self_signup.console.tenant_suspended'),
            'data' => (new SignupRequestResource(SignupRequest::query()->findOrFail($id)))->resolve($request),
            'tenant' => ['id' => (string) $tenant->getTenantKey(), 'status' => (string) $tenant->status],
        ]);
    }

    private function operator(Request $request): CentralUser
    {
        $user = $request->user();
        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
