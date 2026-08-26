<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Auth\ApiLoginAction;
use App\Actions\Auth\ApiLogoutAction;
use App\Actions\Auth\ApiMeAction;
use App\DTOs\Auth\ApiLoginDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ApiLoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    public function __construct(
        private readonly ApiLoginAction $loginAction,
        private readonly ApiLogoutAction $logoutAction,
        private readonly ApiMeAction $meAction,
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
                'data'    => $result,
            ], 200);
        } catch (ValidationException $e) {
            $request->hitRateLimit();
            throw $e;
        }
    }

    /**
     * Get Current Authenticated User Profile, Store Context & Permissions
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        $result = $this->meAction->execute($user, $request);

        return response()->json([
            'success' => true,
            'data'    => $result,
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
     * Get list of active workspace users for quick login selection (Guest allowed)
     */
    public function workspaceUsers(Request $request): JsonResponse
    {
        $users = \App\Models\User::query()
            ->where('is_active', true)
            ->select(['id', 'name', 'phone', 'email'])
            ->orderBy('id')
            ->get()
            ->map(fn ($u) => [
                'id'    => $u->id,
                'name'  => $u->name,
                'login' => $u->phone ?: $u->email,
            ]);

        return response()->json([
            'success' => true,
            'data'    => $users,
        ], 200);
    }
}
