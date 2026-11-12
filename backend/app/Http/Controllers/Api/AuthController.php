<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Auth\ApiLoginAction;
use App\Actions\Auth\ApiLogoutAction;
use App\Actions\Auth\ApiMeAction;
use App\Actions\Auth\ChangePasswordAction;
use App\Actions\Auth\DispatchAccountLockAlertAction;
use App\Actions\Auth\ListQuickLoginUsersAction;
use App\Actions\Auth\QuickLoginAction;
use App\DTOs\Auth\ApiLoginDTO;
use App\DTOs\Auth\ChangePasswordDTO;
use App\DTOs\Auth\QuickLoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ApiLoginRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\QuickLoginRequest;
use App\Http\Resources\QuickLoginUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\QuickLoginGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly ApiLoginAction $loginAction,
        private readonly ActivityLogService $activityLogService,
        private readonly ApiLogoutAction $logoutAction,
        private readonly ApiMeAction $meAction,
        private readonly DispatchAccountLockAlertAction $lockAlertAction,
        private readonly QuickLoginAction $quickLoginAction,
        private readonly ListQuickLoginUsersAction $listQuickLoginUsersAction,
        private readonly ChangePasswordAction $changePasswordAction,
    ) {}

    /**
     * Authenticate User via API & Issue Sanctum Bearer Token
     */
    public function login(ApiLoginRequest $request): JsonResponse
    {
        $request->ensureIsNotRateLimited();

        $dto = ApiLoginDTO::fromRequest($request);

        try {
            $result = $this->loginAction->execute($dto);
            $request->clearRateLimit();

            return response()->json([
                'success' => true,
                'message' => __('auth.login_success'),
                'data' => $result,
            ], 200);
        } catch (ValidationException $e) {
            $request->hitRateLimit();

            if ($request->hitAccountFailure()) {
                $this->activityLogService->log(
                    module: 'auth',
                    action: 'account_locked',
                    description: __('auth.activity_account_locked', ['login' => $request->accountIdentifier()]),
                    properties: ['login' => $request->accountIdentifier(), 'ip' => $request->ip()],
                );
                $this->lockAlertAction->execute($request->submittedLogin(), $request->ip());
            }

            throw $e;
        }
    }

    /**
     * Get Current Authenticated User Profile, Store Context & Permissions
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        $result = $this->meAction->execute($user, $request);

        return response()->json([
            'success' => true,
            'data' => $result,
        ], 200);
    }

    /**
     * Logout and Revoke Sanctum Access Token
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user) {
            $this->logoutAction->execute($user);
        }

        return response()->json([
            'success' => true,
            'message' => __('auth.logout_success'),
        ], 200);
    }

    /**
     * SELL-01: replace the signed-in user's own password; clears `must_change_password` and ends
     * every other session (the current token stays valid).
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401, __('auth.unauthorized'));

        /** @var array{current_password: string, password: string} $data */
        $data = $request->validated();
        $user = $this->changePasswordAction->execute(ChangePasswordDTO::fromArray($data), $user);

        return response()->json([
            'success' => true,
            'message' => __('auth.password_changed_success'),
            'data' => [
                'must_change_password' => false,
                'user' => (new UserResource($user))->resolve(),
            ],
        ], 200);
    }

    /**
     * Testing-only passwordless login (gated by QuickLoginGate + EnsureQuickLoginAllowed).
     */
    public function quickLogin(QuickLoginRequest $request): JsonResponse
    {
        $dto = QuickLoginDTO::fromArray([
            ...$request->validated(),
            'device_ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => __('auth.login_success'),
            'data' => $this->quickLoginAction->execute($dto),
        ], 200);
    }

    /**
     * Testing-only picker list for quick login (id + name only).
     */
    public function quickLoginUsers(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => QuickLoginUserResource::collection($this->listQuickLoginUsersAction->execute())->resolve(),
        ], 200);
    }

    /**
     * Public: which login methods the login screen may offer.
     */
    public function authOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'quick_login' => QuickLoginGate::allowed(),
                'default_method' => 'password',
            ],
        ], 200);
    }
}
