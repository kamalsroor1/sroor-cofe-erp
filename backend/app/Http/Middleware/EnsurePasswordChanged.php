<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SELL-01: a tenant user flagged `must_change_password` (first owner after provisioning, a user
 * an admin reset, an owner reset from the platform console) may only pick a new password.
 *
 * Every other authenticated tenant API request answers
 *
 *   403 { "success": false, "message": "...", "error_code": "auth.password_change_required" }
 *
 * except ALLOWED_ROUTES: the change itself, logout, and the read-only bootstrap calls the SPA
 * makes to render the change-password screen (who am I, tenant context: theme, locale, labels).
 *
 * Not mounted per route: ApiTokenAuth hands every request it authenticates to this middleware,
 * so a route added later behind ApiTokenAuth can never skip it.
 *
 * Also mounted after the session `auth` middleware of the tenant print/export pages
 * (routes/tenant.php, routes/web.php) in case a session sign-in ever returns: there only the
 * SPA shell (WEB_ALLOWED_ROUTES) stays reachable and anything else is a plain 403 page.
 */
final class EnsurePasswordChanged
{
    public const ERROR_CODE = 'auth.password_change_required';

    /** @var list<string> */
    public const ALLOWED_ROUTES = [
        'api.auth.change_password',
        'api.auth.logout',
        'api.auth.me',
        'api.system.context',
    ];

    /** @var list<string> session-guard pages that only render the SPA shell (no data) */
    public const WEB_ALLOWED_ROUTES = ['dashboard'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || $user->must_change_password !== true) {
            return $next($request);
        }

        if (! $request->is('api/*') && ! $request->expectsJson()) {
            if ($request->routeIs(self::WEB_ALLOWED_ROUTES)) {
                return $next($request);
            }

            abort(Response::HTTP_FORBIDDEN, (string) __('auth.password_change_required'));
        }

        if (! $request->routeIs(self::ALLOWED_ROUTES)) {
            return response()->json([
                'success' => false,
                'message' => __('auth.password_change_required'),
                'error_code' => self::ERROR_CODE,
            ], 403);
        }

        return $next($request);
    }
}
