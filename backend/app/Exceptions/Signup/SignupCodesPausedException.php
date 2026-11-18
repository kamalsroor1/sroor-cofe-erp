<?php

declare(strict_types=1);

namespace App\Exceptions\Signup;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Security review (SIGN-11): the platform-wide daily ceiling of verification-code emails
 * (signup.max_codes_per_day) is reached. 503 in the API envelope with Retry-After (seconds until
 * the next Cairo day) and a translated, generic message.
 */
final class SignupCodesPausedException extends HttpException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct(503, (string) __('self_signup.errors.codes_paused'), null, ['Retry-After' => (string) max(1, $retryAfter)]);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
            'retry_after' => max(1, $this->retryAfter),
        ], 503, $this->getHeaders());
    }
}
