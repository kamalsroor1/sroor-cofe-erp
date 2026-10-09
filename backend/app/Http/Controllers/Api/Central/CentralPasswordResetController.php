<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Central\Data\ResetCentralPasswordDTO;
use App\Actions\Central\PasswordReset\ResetCentralPasswordAction;
use App\Actions\Central\PasswordReset\SendCentralPasswordResetLinkAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\ForgotCentralPasswordRequest;
use App\Http\Requests\Central\ResetCentralPasswordRequest;
use Illuminate\Http\JsonResponse;

/**
 * Operator password reset (IDEN-1.12), routes/central.php, central hosts only:
 * POST /api/v1/super-admin/auth/forgot-password, POST …/reset-password.
 */
final class CentralPasswordResetController extends Controller
{
    public function __construct(
        private readonly SendCentralPasswordResetLinkAction $sendLinkAction,
        private readonly ResetCentralPasswordAction $resetAction,
    ) {}

    /** Always the same 200, whether or not the email belongs to an operator. */
    public function forgot(ForgotCentralPasswordRequest $request): JsonResponse
    {
        $this->sendLinkAction->execute((string) $request->validated('email'));

        return response()->json([
            'success' => true,
            'message' => __('central_auth.password_reset_link_sent'),
        ]);
    }

    public function reset(ResetCentralPasswordRequest $request): JsonResponse
    {
        /** @var array{email: string, token: string, password: string} $data */
        $data = $request->safe()->only(['email', 'token', 'password']);

        $this->resetAction->execute(ResetCentralPasswordDTO::fromArray($data));

        return response()->json([
            'success' => true,
            'message' => __('central_auth.password_reset_success'),
        ]);
    }
}
