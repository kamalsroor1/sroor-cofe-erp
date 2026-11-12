<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ActiveStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class ApiTokenAuth
{
    /** @var list<string> routes where a stale X-Store-Id is ignored, see isStoreRecoveryRoute() */
    private const STORE_RECOVERY_ROUTES = ['api.auth.me', 'api.auth.logout', 'api.stores.index', 'api.stores.switch'];

    /**
     * Authenticate an API request with a Sanctum personal access token.
     *
     * The token is read from the `Authorization: Bearer` header or the `X-API-TOKEN`
     * header only. Query-string tokens and the legacy plaintext `users.api_token`
     * column are deliberately not accepted (AUTH-3).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-API-TOKEN');

        if (! is_string($token) || $token === '') {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        $user = null;

        // 1. Resolve through Sanctum PersonalAccessToken (hashed, revocable).
        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken && $this->isExpired($accessToken)) {
            // Expired (e.g. testing-only quick-login tokens): delete the dead row and stop here.
            $accessToken->delete();

            return response()->json([
                'success' => false,
                'message' => __('auth.session_expired'),
            ], 401);
        }

        if ($accessToken) {
            $tokenable = $accessToken->tokenable;
            if ($tokenable instanceof User && $tokenable->is_active) {
                $user = $tokenable;
                $accessToken->forceFill(['last_used_at' => now()])->save();
                $user->withAccessToken($accessToken);
            }
        }

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.session_expired'),
            ], 401);
        }

        // Set authenticated user for this request across guards
        Auth::setUser($user);
        // IDEN-1.4: tenant users only. Platform operators authenticate on routes/central.php
        // through AuthenticateCentral; no token of this middleware reaches the control plane.
        if (function_exists('tenant') && tenant()) {
            Auth::guard('tenant')->setUser($user);
        }
        $request->setUserResolver(fn () => $user);

        // STOR-1 (security): X-Store-Id is trusted only after an access check. An explicit header
        // the user may not use is a 403, never a silent fallback; without the header nothing is
        // written and readers fall back to the user's own default store.
        if (! $this->isStoreRecoveryRoute($request)) {
            $storeHeader = $request->header('X-Store-Id');

            if (is_string($storeHeader) && trim($storeHeader) !== '') {
                $storeId = ActiveStore::parseHeader($storeHeader);

                if ($storeId === ActiveStore::ALL) {
                    if (! ActiveStore::canViewAll($user)) {
                        return ActiveStore::accessDeniedResponse($user);
                    }
                } elseif (! is_int($storeId) || ! ActiveStore::canAccess($user, $storeId)) {
                    return ActiveStore::accessDeniedResponse($user);
                } else {
                    session(['current_store_id' => $storeId]);
                }
            }
        }

        // SELL-01: chained here (not mounted per route) so no route behind this middleware can
        // skip the forced password change.
        return app(EnsurePasswordChanged::class)->handle($request, $next);
    }

    /**
     * SEC-03: a token dies at its own `expires_at`, and never later than the tenant TTL counted
     * from its creation (quick-login tokens excepted). The second rule also ends tokens minted before the 12h fixed TTL
     * (30-day or null `expires_at`) without a data migration.
     */
    private function isExpired(PersonalAccessToken $accessToken): bool
    {
        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return true;
        }

        // Quick-login tokens carry their own, separately configured TTL in `expires_at`.
        if (in_array('quick-login', (array) $accessToken->abilities, true)) {
            return false;
        }

        $ttlMinutes = max(1, (int) config('auth.tokens.tenant_ttl_minutes', 720));

        return $accessToken->created_at === null
            || $accessToken->created_at->copy()->addMinutes($ttlMinutes)->isPast();
    }

    /**
     * Routes the SPA needs to RECOVER from a stale X-Store-Id (a store the user lost access to):
     * who am I, which stores may I use, switch, log out. The header is ignored there (not
     * trusted, not written to the session); each of them resolves the store from the user's own
     * accessible stores, so ignoring it never exposes another branch's data.
     */
    private function isStoreRecoveryRoute(Request $request): bool
    {
        return $request->routeIs(self::STORE_RECOVERY_ROUTES);
    }
}
