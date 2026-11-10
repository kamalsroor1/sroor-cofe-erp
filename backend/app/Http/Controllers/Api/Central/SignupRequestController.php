<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Signup\ApproveSignupRequestAction;
use App\Actions\Signup\ListSignupRequestsAction;
use App\Actions\Signup\RejectSignupRequestAction;
use App\DTOs\Signup\ApproveSignupRequestDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signup\ApproveSignupRequestRequest;
use App\Http\Requests\Signup\RejectSignupRequestRequest;
use App\Http\Resources\Central\SignupRequestResource;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * PRD-05: platform console review of public signup requests (routes/central.php).
 *
 *   GET  /api/v1/super-admin/signup-requests               super_admin.tenants.view
 *   POST /api/v1/super-admin/signup-requests/{id}/approve  super_admin.tenants.manage + recent 2FA
 *   POST /api/v1/super-admin/signup-requests/{id}/reject   super_admin.tenants.manage
 */
final class SignupRequestController extends Controller
{
    public function __construct(
        private readonly ListSignupRequestsAction $listSignupRequests,
        private readonly ApproveSignupRequestAction $approveSignupRequest,
        private readonly RejectSignupRequestAction $rejectSignupRequest,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        return SignupRequestResource::collection($this->listSignupRequests->execute($request));
    }

    public function approve(ApproveSignupRequestRequest $request, int $id): JsonResponse
    {
        /** @var array{subdomain: string, plan_code?: string|null, owner_email: string, owner_password: string} $data */
        $data = $request->validated();

        $signup = $this->approveSignupRequest->execute(
            ApproveSignupRequestDTO::fromArray($id, $data),
            $this->operator($request),
        );

        return response()->json([
            'success' => true,
            'message' => __('signup.approved'),
            'data' => (new SignupRequestResource($signup))->resolve(),
        ]);
    }

    public function reject(RejectSignupRequestRequest $request, int $id): JsonResponse
    {
        $signup = $this->rejectSignupRequest->execute($id, (string) $request->validated('reason'), $this->operator($request));

        return response()->json([
            'success' => true,
            'message' => __('signup.rejected'),
            'data' => (new SignupRequestResource($signup))->resolve(),
        ]);
    }

    private function operator(Request $request): CentralUser
    {
        $user = $request->user();
        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
