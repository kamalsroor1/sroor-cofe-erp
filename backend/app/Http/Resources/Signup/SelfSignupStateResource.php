<?php

declare(strict_types=1);

namespace App\Http\Resources\Signup;

use App\Models\SignupRequest;
use App\Services\Signup\SignupVerificationCodeIssuer;
use App\Support\Signup\SignupSecrets;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * SIGN-11: a self-signup as its owner (token holder) sees it: status, masked email and the code
 * timers. Never the email in full, a hash, the plan internals or the operator fields.
 *
 * @mixin SignupRequest
 */
final class SelfSignupStateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $expiresAt = $this->verification_code_expires_at;

        return [
            'status' => $this->status->value,
            'email_masked' => SignupSecrets::maskEmail((string) $this->email),
            'subdomain' => $this->preferred_subdomain,
            'code_expires_in' => $expiresAt === null ? 0 : max(0, $expiresAt->getTimestamp() - now()->getTimestamp()),
            'resend_available_in' => SignupVerificationCodeIssuer::resendAvailableIn($this->resource),
        ];
    }
}
