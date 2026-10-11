<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts the control plane (routes/central.php, /api/v1/super-admin/*) to the platform
 * console host and the central context (IDEN-1.4 / IDEN-1.11). Anything else is 404, so
 * the control plane is invisible elsewhere. Runs BEFORE authentication: a guest on a wrong
 * host gets 404, not 401.
 *
 *  - tenancy initialised                                        → 404;
 *  - host not allowed for the control plane (allowsControlPlane) → 404;
 *  - any tenant identifier (X-Tenant header, ?tenant=)           → 404: the console never
 *    carries tenant context, whether or not the identifier names a real tenant.
 *
 * Host rule: config('central.admin_domains') (env CENTRAL_ADMIN_DOMAINS). When it is empty,
 * production fails closed and local/testing fall back to tenancy.central_domains.
 *
 * The static helpers are shared with ResolveApiTenancy (tenant API refused on the admin
 * host), AdminSecurityHeaders and routes/web.php (which SPA context to serve).
 */
final class EnsureCentralContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            abort(404);
        }

        if (! self::allowsControlPlane($request)) {
            abort(404);
        }

        if (self::namesTenant($request)) {
            abort(404);
        }

        return $next($request);
    }

    /**
     * The configured platform-console hosts (lower-case, never hardcoded).
     *
     * @return list<string>
     */
    public static function adminHosts(): array
    {
        $hosts = config('central.admin_domains', []);

        if (! is_array($hosts)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $host): string => is_string($host) ? strtolower(trim($host)) : '',
            $hosts,
        )));
    }

    /** True when admin hosts are configured and the request arrives on one of them. */
    public static function isAdminHost(Request $request): bool
    {
        return in_array(strtolower($request->getHost()), self::adminHosts(), true);
    }

    /**
     * May the control plane answer on this host? Configured admin hosts only; with none
     * configured, never in production and the central domains elsewhere.
     */
    public static function allowsControlPlane(Request $request): bool
    {
        if (self::adminHosts() !== []) {
            return self::isAdminHost($request);
        }

        if (app()->isProduction()) {
            return false;
        }

        $central = config('tenancy.central_domains', []);

        return in_array($request->getHost(), is_array($central) ? $central : [], true);
    }

    /** The request selects a tenant the way ResolveApiTenancy would (header or query). */
    public static function namesTenant(Request $request): bool
    {
        $identifier = $request->header('X-Tenant') ?: $request->query('tenant');

        return is_string($identifier) && trim($identifier) !== '';
    }
}
