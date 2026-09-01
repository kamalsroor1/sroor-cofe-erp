<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Tenant;

class ResolveApiTenancy
{
    /**
     * Handle incoming API request and dynamically initialize tenant context if requested
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 0. Bypass tenancy initialization for central platform routes
        if ($request->is('api/v1/central/*') || $request->is('api/central/*')) {
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

        if (!in_array($host, $centralDomains, true)) {
            // A. Search by domain record
            $tenant = Tenant::whereHas('domains', fn ($q) => $q->where('domain', $host))->first();

            // B. Search by slug if host is a subdomain like 2m.baraa-solutions.com
            if (!$tenant) {
                $subdomain = explode('.', $host)[0] ?? null;
                if ($subdomain && !in_array($subdomain, ['www', 'mail', 'cpanel', 'webmail'], true)) {
                    $tenant = Tenant::find($subdomain) ?? Tenant::where('slug', $subdomain)->first();
                }
            }

            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }

        return $next($request);
    }
}
