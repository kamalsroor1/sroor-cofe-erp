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

        return $next($request);
    }
}
