<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

final class ApiTokenAuth
{
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

        // Set store context from header
        $storeHeader = $request->header('X-Store-Id');
        if ($storeHeader && is_numeric($storeHeader)) {
            session(['current_store_id' => (int) $storeHeader]);
        }

        return $next($request);
    }
}
