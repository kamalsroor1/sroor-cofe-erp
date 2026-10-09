<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Central\GetCentralProfileAction;
use App\Actions\Central\LoginCentralUserAction;
use App\Actions\Central\LogoutCentralUserAction;
use App\DTOs\Central\CentralLoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\CentralLoginRequest;
use App\Http\Resources\Central\CentralLoginResource;
use App\Http\Resources\Central\CentralUserResource;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-operator authentication (IDEN-1.3), routes/central.php:
 * POST /api/v1/super-admin/auth/login, GET …/me, POST …/logout.
 */
final class CentralAuthController extends Controller
{
    public function __construct(
        private readonly LoginCentralUserAction $loginAction,
        private readonly GetCentralProfileAction $profileAction,
        private readonly LogoutCentralUserAction $logoutAction,
    ) {}

    public function login(CentralLoginRequest $request): JsonResponse
    {
        /** @var array{email: string, password: string, device_name?: string|null} $data */
        $data = $request->validated();

        $result = $this->loginAction->execute(CentralLoginDTO::fromArray($data), $request->ip());

        return (new CentralLoginResource($result))
            ->additional([
                'success' => true,
                'message' => __($result->twoFactorRequired ? 'central_auth.two_factor_required' : 'central_auth.login_success'),
            ])
            ->response();
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->operator($request);

        return (new CentralUserResource($this->profileAction->execute($user)))
            ->additional(['success' => true])
            ->response();
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logoutAction->execute($this->operator($request));

        return response()->json([
            'success' => true,
            'message' => __('central_auth.logout_success'),
        ]);
    }

    /** AuthenticateCentral guarantees an active CentralUser; anything else is a wiring bug. */
    private function operator(Request $request): CentralUser
    {
        $user = $request->user();

        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
