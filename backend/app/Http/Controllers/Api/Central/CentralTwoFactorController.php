<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Central;

use App\Actions\Central\Data\TwoFactorCodeDTO;
use App\Actions\Central\TwoFactor\ConfirmCentralTwoFactorAction;
use App\Actions\Central\TwoFactor\ConfirmStepUpAction;
use App\Actions\Central\TwoFactor\EnableCentralTwoFactorAction;
use App\Actions\Central\TwoFactor\GetCentralRecoveryCodesAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Central\CentralTwoFactorCodeRequest;
use App\Http\Requests\Central\ConfirmCentralTwoFactorRequest;
use App\Http\Resources\Central\CentralLoginResource;
use App\Http\Resources\Central\TwoFactorSetupResource;
use App\Models\CentralUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Operator two-factor management (IDEN-1.12), routes/central.php:
 *
 *  - setup or full token (AuthenticateCentral:setup):
 *      POST /api/v1/super-admin/auth/two-factor/enable
 *      POST /api/v1/super-admin/auth/two-factor/confirm
 *      GET  /api/v1/super-admin/auth/two-factor/recovery-codes
 *  - full token only: POST /api/v1/super-admin/auth/step-up
 */
final class CentralTwoFactorController extends Controller
{
    public function __construct(
        private readonly EnableCentralTwoFactorAction $enableAction,
        private readonly ConfirmCentralTwoFactorAction $confirmAction,
        private readonly GetCentralRecoveryCodesAction $recoveryCodesAction,
        private readonly ConfirmStepUpAction $stepUpAction,
    ) {}

    public function enable(Request $request): JsonResponse
    {
        return (new TwoFactorSetupResource($this->enableAction->execute($this->operator($request))))
            ->additional(['success' => true, 'message' => __('central_auth.two_factor_enabled')])
            ->response();
    }

    public function confirm(ConfirmCentralTwoFactorRequest $request): JsonResponse
    {
        $result = $this->confirmAction->execute($this->operator($request), (string) $request->validated('code'), $request->ip());

        return (new CentralLoginResource($result))
            ->additional(['success' => true, 'message' => __('central_auth.two_factor_confirmed')])
            ->response();
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => ['recovery_codes' => $this->recoveryCodesAction->execute($this->operator($request))],
        ]);
    }

    public function stepUp(CentralTwoFactorCodeRequest $request): JsonResponse
    {
        /** @var array{code?: string|null, recovery_code?: string|null} $data */
        $data = $request->validated();

        $until = $this->stepUpAction->execute($this->operator($request), TwoFactorCodeDTO::fromArray($data));

        return response()->json([
            'success' => true,
            'message' => __('central_auth.step_up_confirmed'),
            'data' => ['two_factor_verified_until' => $until->toIso8601String()],
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
