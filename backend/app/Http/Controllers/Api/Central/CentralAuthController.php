<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Central\Data\CentralSignInResult;
use App\Actions\Central\Data\TwoFactorChallengeDTO;
use App\Actions\Central\GetCentralProfileAction;
use App\Actions\Central\LoginCentralUserAction;
use App\Actions\Central\LogoutCentralUserAction;
use App\Actions\Central\TwoFactor\CompleteTwoFactorChallengeAction;
use App\DTOs\Central\CentralLoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\CentralLoginRequest;
use App\Http\Requests\Central\CentralTwoFactorChallengeRequest;
use App\Http\Resources\Central\CentralLoginResource;
use App\Http\Resources\Central\CentralUserResource;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Platform-operator authentication (IDEN-1.3, IDEN-1.12), routes/central.php:
 * POST /api/v1/super-admin/auth/login, POST …/two-factor-challenge, GET …/me, POST …/logout.
 */
final class CentralAuthController extends Controller
{
    public function __construct(
        private readonly LoginCentralUserAction $loginAction,
        private readonly GetCentralProfileAction $profileAction,
        private readonly LogoutCentralUserAction $logoutAction,
        private readonly CompleteTwoFactorChallengeAction $challengeAction,
    ) {}

    public function login(CentralLoginRequest $request): JsonResponse
    {
        /** @var array{email: string, password: string, device_name?: string|null} $data */
        $data = $request->validated();

        $result = $this->loginAction->execute(CentralLoginDTO::fromArray($data), $request->ip());

        return $this->signInResponse($result);
    }

    public function twoFactorChallenge(CentralTwoFactorChallengeRequest $request): JsonResponse
    {
        /** @var array{challenge_id: string, code?: string|null, recovery_code?: string|null} $data */
        $data = $request->validated();

        $result = $this->challengeAction->execute(TwoFactorChallengeDTO::fromArray($data), $request->ip());

        return $this->signInResponse($result);
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

    private function signInResponse(CentralSignInResult $result): JsonResponse
    {
        $message = match (true) {
            $result->twoFactorRequired => 'central_auth.two_factor_required',
            $result->isSetupToken() => 'central_auth.two_factor_setup_needed',
            default => 'central_auth.login_success',
        };

        return (new CentralLoginResource($result))
            ->additional(['success' => true, 'message' => __($message)])
            ->response();
    }

    /** AuthenticateCentral guarantees an active CentralUser; anything else is a wiring bug. */
    private function operator(Request $request): CentralUser
    {
        $user = $request->user();

        abort_unless($user instanceof CentralUser, 401, __('central_auth.unauthenticated'));

        return $user;
    }
}
