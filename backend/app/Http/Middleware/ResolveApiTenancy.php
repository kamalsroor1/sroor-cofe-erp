<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiTenancy
{
    /**
     * IDEN-1.4: the ONLY routes of this group that never initialise tenancy: the public
     * workspace resolver (shop-code lookup, central DB, own throttle). Matched by route
     * name, so no other `api/v1/central/*` path can ride on the exception. The un-versioned
     * alias (/api/central/tenants/resolve) runs the same stack (security audit, W2 lane 3I).
     */
    public const PUBLIC_CENTRAL_ROUTES = ['api.central.tenants.resolve', 'api.central.tenants.resolve.alias'];

    /**
     * IDEN-1.11: the only routes of this group that answer on the platform-console host
     * (central-safe, no tenant data). Everything else there is 404.
     */
    public const ADMIN_HOST_ROUTES = ['api.ping', 'api.system.translations'];

    /**
     * Handle incoming API request and dynamically initialize tenant context if requested
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 0. IDEN-1.11: on the admin host no tenant is ever resolved and the tenant API is
        //    refused; only a few central-safe public endpoints answer, without X-Tenant.
        if (EnsureCentralContext::isAdminHost($request)) {
            if (! $request->routeIs(self::ADMIN_HOST_ROUTES) || $this->namesTenant($request)) {
                return $this->notFound();
            }

            return $next($request);
        }

        // 0b. Explicit exception: the public workspace resolver never initialises tenancy.
        if ($request->routeIs(self::PUBLIC_CENTRAL_ROUTES)) {
            return $next($request);
        }

        // 1. Check if tenancy is already initialized (e.g. by domain)
        if (function_exists('tenancy') && tenancy()->initialized) {
            return $next($request);
        }

        // 2. Check X-Tenant header or query parameter
        $tenantIdentifier = $request->header('X-Tenant') ?: $request->query('tenant') ?: $request->input('tenant');

        if ($tenantIdentifier) {
            $tenant = Tenant::find($tenantIdentifier)
                ?? Tenant::whereHas('domains', fn ($q) => $q->where('domain', $tenantIdentifier))->first();

            if ($tenant) {
                tenancy()->initialize($tenant);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.tenant_not_found'),
                ], 404);
            }
        }

        // 3. Resolve by Request Host (Subdomains and Custom Domains)
        $host = $request->getHost();
        $centralDomains = config('tenancy.central_domains', [
            '127.0.0.1',
            'localhost',
            'baraa-solutions.com',
            'www.baraa-solutions.com',
        ]);

        if (! in_array($host, $centralDomains, true)) {
            // A. Search by domain record
            $tenant = Tenant::whereHas('domains', fn ($q) => $q->where('domain', $host))->first();

            // B. Search by slug if host is a subdomain like 2m.baraa-solutions.com
            if (! $tenant) {
                $subdomain = explode('.', $host)[0] ?? null;
                if ($subdomain && ! in_array($subdomain, ['www', 'mail', 'cpanel', 'webmail'], true)) {
                    $tenant = Tenant::find($subdomain) ?? Tenant::where('slug', $subdomain)->first();
                }
            }

            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }

        return $next($request);
    }

    private function namesTenant(Request $request): bool
    {
        return (bool) ($request->header('X-Tenant') ?: $request->query('tenant') ?: $request->input('tenant'));
    }

    private function notFound(): Response
    {
        return response()->json([
            'success' => false,
            'message' => __('auth.tenant_not_found'),
        ], 404);
    }
}
