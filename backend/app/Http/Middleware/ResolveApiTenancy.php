<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\TenantProvisioningException;
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
     * (central-safe, no tenant data). Everything else there is 404. `api.branding` (BRND-3)
     * answers there with platform branding only (tenant = null).
     */
    public const ADMIN_HOST_ROUTES = ['api.ping', 'api.system.translations', 'api.branding'];

    /**
     * SEC-1 (M1): the only routes of this group that answer when NO tenant was resolved (central
     * host without X-Tenant, or an unknown host): the admin-host allowlist plus the public app
     * update endpoints (central `app_versions`) and the login-options probe. Every other route
     * here is tenant API and is 404 without a tenant: it would otherwise run against the CENTRAL
     * database (central `users`, central personal_access_tokens).
     */
    public const CENTRAL_SAFE_ROUTES = [
        ...self::ADMIN_HOST_ROUTES,
        'api.app.version',
        'api.app.check_update',
        'api.app.download_apk',
        'api.app.download_latest_apk',
        'api.auth.options',
    ];

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
            $current = tenancy()->tenant;
            if ($current instanceof Tenant && $current->isPurged()) {
                return $this->notFound();
            }
            if ($current instanceof Tenant && ! $current->isProvisioned()) {
                return $this->notReady($current);
            }

            return $next($request);
        }

        // 2. Check X-Tenant header or query parameter
        $tenantIdentifier = $request->header('X-Tenant') ?: $request->query('tenant') ?: $request->input('tenant');

        if ($tenantIdentifier) {
            $tenant = Tenant::find($tenantIdentifier)
                ?? Tenant::whereHas('domains', fn ($q) => $q->where('domain', $tenantIdentifier))->first();

            if ($tenant) {
                // OPS-9: a purged (or purging) workspace no longer exists for clients.
                if ($tenant->isPurged()) {
                    return $this->notFound();
                }

                // OPS-2: a workspace still being provisioned (or failed) has no usable
                // database yet: refuse before initializing tenancy.
                if (! $tenant->isProvisioned()) {
                    return $this->notReady($tenant);
                }

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
                if ($tenant->isPurged()) {
                    return $this->notFound();
                }

                if (! $tenant->isProvisioned()) {
                    return $this->notReady($tenant);
                }

                tenancy()->initialize($tenant);
            }
        }

        // 4. SEC-1 (M1): no tenant resolved => only the central-safe routes answer.
        if (! tenancy()->initialized && ! $request->routeIs(self::CENTRAL_SAFE_ROUTES)) {
            return $this->notFound();
        }

        return $next($request);
    }

    /** OPS-2: 503 `provisioning.workspace_not_ready` (+ Retry-After while in progress). */
    private function notReady(Tenant $tenant): Response
    {
        return TenantProvisioningException::workspaceNotReady($tenant->provisioningStatus())->render();
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
