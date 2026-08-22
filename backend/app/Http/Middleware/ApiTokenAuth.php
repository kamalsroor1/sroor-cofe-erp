<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenAuth
{
    /**
     * Handle an incoming API request using Sanctum and Token-based Auth.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $request->header('X-API-TOKEN') ?: $request->query('api_token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => __('auth.unauthorized'),
            ], 401);
        }

        $user = null;

        // 1. Try resolving through Sanctum PersonalAccessToken
        $accessToken = PersonalAccessToken::findToken($token);
        if ($accessToken) {
            $tokenable = $accessToken->tokenable;
            if ($tokenable instanceof User && $tokenable->is_active) {
                $user = $tokenable;
                $accessToken->forceFill(['last_used_at' => now()])->save();
                $user->withAccessToken($accessToken);
            }
        }

        // 2. Fallback to direct api_token column lookup
        if (!$user) {
            $user = User::where('api_token', $token)
                ->where('is_active', true)
                ->first();
        }

        // 3. Fallback: Check central DB if in tenant context
        if (!$user && function_exists('tenant') && tenant()) {
            $centralUser = tenancy()->central(function () use ($token) {
                $accessToken = PersonalAccessToken::findToken($token);
                if ($accessToken && $accessToken->tokenable instanceof User) {
                    return $accessToken->tokenable;
                }
                return User::where('api_token', $token)->where('is_active', true)->first();
            });

            if ($centralUser && $centralUser->is_active && $centralUser->hasRole('admin')) {
                $user = User::where('phone', $centralUser->phone)->where('is_active', true)->first();
            }
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => __('auth.session_expired'),
            ], 401);
        }

        // Set authenticated user for this request
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        // Set store context from header
        $storeHeader = $request->header('X-Store-Id');
        if ($storeHeader && is_numeric($storeHeader)) {
            session(['current_store_id' => (int)$storeHeader]);
        }

        return $next($request);
    }
}
