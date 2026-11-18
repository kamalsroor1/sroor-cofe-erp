<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Landing;

use App\Actions\Signup\ResendSelfSignupCodeAction;
use App\Actions\Signup\StartSelfSignupAction;
use App\Actions\Signup\VerifySelfSignupAction;
use App\DTOs\Signup\SelfSignupTokenDTO;
use App\DTOs\Signup\StartSelfSignupDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Signup\ResendSelfSignupCodeRequest;
use App\Http\Requests\Signup\StartSelfSignupRequest;
use App\Http\Requests\Signup\VerifySelfSignupRequest;
use App\Http\Resources\Signup\SelfSignupStartedResource;
use App\Http\Resources\Signup\SelfSignupStateResource;
use Illuminate\Http\JsonResponse;

/**
 * SIGN-11: public self-signup with an emailed code (central DB, no tenancy, routes/api.php).
 *
 *   POST /api/v1/signup          throttle:self-signup         201 {signup_token, email_masked, ...}
 *   POST /api/v1/signup/verify   throttle:self-signup-verify  200 {status, ...} | 422 code
 *   POST /api/v1/signup/resend   throttle:self-signup-resend  200 | 409 | 422 token | 429
 * All three answer 503 while self-signup is switched off (EnsureSelfSignupEnabled).
 */
final class SelfSignupController extends Controller
{
    public function __construct(
        private readonly StartSelfSignupAction $startSelfSignup,
        private readonly VerifySelfSignupAction $verifySelfSignup,
        private readonly ResendSelfSignupCodeAction $resendSelfSignupCode,
    ) {}

    public function store(StartSelfSignupRequest $request): JsonResponse
    {
        /** @var array{business_name: string, owner_name: string, email: string, phone: string, password: string, business_type: string, city?: string|null, subdomain?: string|null, plan_code?: string|null, locale?: string|null, website?: string|null} $data */
        $data = $request->validated();

        $started = $this->startSelfSignup->execute(
            StartSelfSignupDTO::fromArray($data, $request->ip(), $request->userAgent()),
        );

        return response()->json([
            'success' => true,
            'message' => __('self_signup.code_sent'),
            'data' => (new SelfSignupStartedResource($started))->resolve($request),
        ], 201, ['Cache-Control' => 'no-store']);
    }

    public function verify(VerifySelfSignupRequest $request): JsonResponse
    {
        /** @var array{token: string, code: string} $data */
        $data = $request->validated();

        $signup = $this->verifySelfSignup->execute(SelfSignupTokenDTO::fromArray($data));

        return response()->json([
            'success' => true,
            'message' => __('self_signup.verified'),
            'data' => (new SelfSignupStateResource($signup))->resolve($request),
        ]);
    }

    public function resend(ResendSelfSignupCodeRequest $request): JsonResponse
    {
        /** @var array{token: string} $data */
        $data = $request->validated();

        $signup = $this->resendSelfSignupCode->execute(SelfSignupTokenDTO::fromArray($data));

        return response()->json([
            'success' => true,
            'message' => __('self_signup.code_resent'),
            'data' => (new SelfSignupStateResource($signup))->resolve($request),
        ]);
    }
}
