<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Central\Data\CentralAuthSettings;
use App\Actions\Central\Exceptions\CentralAuthException;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up guard for sensitive central operations (IDEN-1.12).
 *
 * Lets the request through only when the CURRENT central token carries a second-factor
 * proof (`two_factor_verified_at`) no older than central.step_up_ttl_minutes (15). A full
 * token is stamped when it is issued after the 2FA challenge, and re-stamped by
 * POST /api/v1/super-admin/auth/step-up. Otherwise:
 *
 *   403 { "success": false, "message": "...", "error_code": "central_auth.step_up_required" }
 *
 * The SPA then asks for a code, calls /auth/step-up and retries.
 *
 * Hook (no route uses it yet): register it AFTER AuthenticateCentral, e.g.
 *   Route::middleware([AuthenticateCentral::class, RequireRecentTwoFactor::class])
 * for impersonation (IDEN-2.6), tenant archive/purge and DB-config changes (OPS-9),
 * billing activation and manual invoices (ENTI-3.4 / ENTI-3.10).
 */
final class RequireRecentTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user instanceof CentralUser ? $user->currentAccessToken() : null;

        if (! $token instanceof CentralPersonalAccessToken
            || ! $token->twoFactorVerifiedWithin(CentralAuthSettings::stepUpTtlMinutes())) {
            return CentralAuthException::stepUpRequired()->render();
        }

        return $next($request);
    }
}
