<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a platform operator (IDEN-1.3) on the central control plane.
 *
 * Accepted ONLY when every condition holds, otherwise 401:
 *  - the token comes from `Authorization: Bearer` (no query string, no X-API-TOKEN, no cookie);
 *  - it resolves in `central_personal_access_tokens` (CentralPersonalAccessToken), never in
 *    Sanctum's tenant `personal_access_tokens`;
 *  - it carries the exact `central:*` ability (a Sanctum wildcard `*` is not enough);
 *  - it has an `expires_at` in the future (an expired row is deleted);
 *  - its owner is an active App\Models\CentralUser.
 *
 * The `central` guard (driver sanctum) is NOT used to authenticate: Sanctum's guard resolves
 * the global token model, so `auth:central` would never find a CentralUser. This middleware
 * only places the resolved user on that guard and makes it the default for the request.
 *
 * Runs after EnsureCentralContext (tenant host => 404 before any auth).
 */
final class AuthenticateCentral
{
    /** Write `last_used_at` at most once per this many seconds per token. */
    private const LAST_USED_RESOLUTION_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if (! is_string($plainToken) || $plainToken === '') {
            return $this->reject('central_auth.unauthenticated');
        }

        $accessToken = CentralPersonalAccessToken::findToken($plainToken);

        if (! $accessToken instanceof CentralPersonalAccessToken) {
            return $this->reject('central_auth.unauthenticated');
        }

        if ($accessToken->expires_at === null) {
            return $this->reject('central_auth.unauthenticated');
        }

        if ($accessToken->expires_at->isPast()) {
            $accessToken->delete();

            return $this->reject('central_auth.session_expired');
        }

        if (! in_array($this->requiredAbility(), (array) $accessToken->abilities, true)) {
            return $this->reject('central_auth.unauthenticated');
        }

        $user = $accessToken->tokenable;

        if (! $user instanceof CentralUser || ! $user->is_active) {
            return $this->reject('central_auth.unauthenticated');
        }

        $this->touch($accessToken);

        $user->withAccessToken($accessToken);

        Auth::guard('central')->setUser($user);
        Auth::shouldUse('central');
        $request->setUserResolver(static fn (): CentralUser => $user);

        return $next($request);
    }

    private function touch(CentralPersonalAccessToken $accessToken): void
    {
        $lastUsed = $accessToken->last_used_at;

        if ($lastUsed !== null && $lastUsed->diffInSeconds(now(), true) < self::LAST_USED_RESOLUTION_SECONDS) {
            return;
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();
    }

    private function requiredAbility(): string
    {
        return (string) config('central.token_ability', 'central:*');
    }

    private function reject(string $messageKey): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __($messageKey),
        ], 401);
    }
}
