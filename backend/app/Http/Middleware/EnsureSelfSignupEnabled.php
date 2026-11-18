<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Branding\PlatformBranding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SIGN-11: the public self-signup writes (start / verify / resend) answer a translated 503 while
 * the platform setting `self_signup_enabled` is off. Runs before validation, so a closed signup
 * never reports field errors. The status endpoint stays open (already verified owners keep their
 * progress screen).
 */
final class EnsureSelfSignupEnabled
{
    public function __construct(private readonly PlatformBranding $platform) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->platform->get()->selfSignupEnabled) {
            return response()->json([
                'success' => false,
                'message' => __('self_signup.errors.disabled'),
            ], 503, ['Retry-After' => '3600']);
        }

        return $next($request);
    }
}
