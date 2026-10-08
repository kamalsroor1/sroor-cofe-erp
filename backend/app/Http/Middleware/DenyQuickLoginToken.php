<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tokens issued by the testing-only quick login carry only the `quick-login`
 * ability. They must never reach administrative areas (users, roles, settings…).
 * Must run after ApiTokenAuth so the current access token is attached.
 */
final class DenyQuickLoginToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof User ? $user->currentAccessToken() : null;

        if ($token instanceof PersonalAccessToken && $token->can('quick-login') && ! $token->can('*')) {
            return response()->json([
                'success' => false,
                'message' => __('auth.quick_login_token_forbidden'),
            ], 403);
        }

        return $next($request);
    }
}
