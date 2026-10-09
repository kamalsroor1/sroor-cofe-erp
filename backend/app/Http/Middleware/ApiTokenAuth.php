<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ActiveStore;
use App\Support\ClientStoreGuard;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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
        if ($accessToken && $accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            // Expired (e.g. testing-only quick-login tokens): delete the dead row and stop here,
            // so no fallback below can resurrect the session.
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

        // 2. Fallback: central admin token mirrored onto the tenant user with the same phone.
        // TODO(Phase 1 central identity, docs/reviews/2026-10-07-saas-analysis/00-REPORT.md row 8): remove central master-key mirroring
        if (! $user && function_exists('tenant') && tenant()) {
            $centralUser = tenancy()->central(function () use ($token) {
                $centralToken = PersonalAccessToken::findToken($token);
                if (! $centralToken) {
                    return null;
                }

                if ($centralToken->expires_at !== null && $centralToken->expires_at->isPast()) {
                    $centralToken->delete();

                    return null;
                }

                return $centralToken->tokenable instanceof User ? $centralToken->tokenable : null;
            });

            if ($centralUser instanceof User && $centralUser->is_active && $centralUser->hasRole('admin')) {
                $user = User::where('phone', $centralUser->phone)->where('is_active', true)->first();
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
        // IDEN-1.1: the `super_admin` guard is gone and `central` belongs to CentralUser
        // only, so a legacy App\Models\User is never placed on an operator guard. The
        // Phase 0 central flow keeps working through the default guard until IDEN-1.3/1.4.
        if (function_exists('tenant') && tenant()) {
            Auth::guard('tenant')->setUser($user);
        }
        $request->setUserResolver(fn () => $user);

        // STOR-1 (security): X-Store-Id is trusted only after an access check. An explicit header
        // the user may not use is a 403, never a silent fallback; without the header nothing is
        // written and readers fall back to the user's own default store.
        if (! $this->isCentralRoute($request) && ! $this->isStoreRecoveryRoute($request)) {
            $storeHeader = $request->header('X-Store-Id');

            if (is_string($storeHeader) && trim($storeHeader) !== '') {
                $storeId = ActiveStore::parseHeader($storeHeader);

                if ($storeId === ActiveStore::ALL) {
                    if (! ActiveStore::canViewAll($user)) {
                        return $this->storeForbidden();
                    }
                } elseif (! is_int($storeId) || ! ActiveStore::canAccess($user, $storeId)) {
                    return $this->storeForbidden();
                } else {
                    session(['current_store_id' => $storeId]);
                }
            }
        }

        return $next($request);
    }

    /**
     * The /api/v1/super-admin control plane runs in the central DB (no `stores` table), so the
     * store header the SPA attaches to every call is ignored there.
     */
    private function isCentralRoute(Request $request): bool
    {
        $route = $request->route();

        return $route instanceof Route
            && in_array(EnsureCentralContext::class, $route->gatherMiddleware(), true);
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

    private function storeForbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('common.store_access_denied'),
            'error_code' => ClientStoreGuard::ERROR_CODE,
        ], 403);
    }
}
