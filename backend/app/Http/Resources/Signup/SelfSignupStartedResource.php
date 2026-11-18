<?php

declare(strict_types=1);

namespace App\Http\Resources\Signup;

use App\DTOs\Signup\SelfSignupStarted;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SIGN-11: response of POST /api/v1/signup. `signup_token` is returned once (only its hash is
 * stored) and hidden from Telescope.
 *
 * @mixin SelfSignupStarted
 */
final class SelfSignupStartedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SelfSignupStarted $started */
        $started = $this->resource;

        return [
            'signup_token' => $started->token,
            'email_masked' => $started->maskedEmail,
            'subdomain' => $started->subdomain,
            'code_expires_in' => $started->codeExpiresIn,
            'resend_available_in' => $started->resendAvailableIn,
        ];
    }
}
