<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Auth\ResetTenantPasswordAction;
use App\Actions\Auth\SendTenantPasswordResetLinkAction;
use App\DTOs\Auth\ResetTenantPasswordDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotTenantPasswordRequest;
use App\Http\Requests\Auth\ResetTenantPasswordRequest;
use Illuminate\Http\JsonResponse;

/**
 * SIGN-15: a shop user's own "forgot password" by email, on the shop host (routes/api.php, inside
 * ResolveApiTenancy, public + `throttle:tenant-password-reset`):
 *   POST /api/v1/auth/forgot-password  200 always (never reveals an account) | 422 bad email | 429
 *   POST /api/v1/auth/reset-password   200 | 422 (validation, or bad / used / expired token) | 429
 */
final class TenantPasswordResetController extends Controller
{
    public function __construct(
        private readonly SendTenantPasswordResetLinkAction $sendLinkAction,
        private readonly ResetTenantPasswordAction $resetAction,
    ) {}

    public function forgot(ForgotTenantPasswordRequest $request): JsonResponse
    {
        $this->sendLinkAction->execute((string) $request->validated('email'), app()->getLocale());

        return response()->json([
            'success' => true,
            'message' => __('auth.password_reset.link_sent'),
        ]);
    }

    public function reset(ResetTenantPasswordRequest $request): JsonResponse
    {
        /** @var array{email: string, token: string, password: string} $data */
        $data = $request->safe()->only(['email', 'token', 'password']);

        $this->resetAction->execute(ResetTenantPasswordDTO::fromArray($data));

        return response()->json([
            'success' => true,
            'message' => __('auth.password_reset.success'),
        ]);
    }
}
