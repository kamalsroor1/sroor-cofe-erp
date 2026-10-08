<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route group (the /api/v1/super-admin control plane) to the central context:
 * the request must arrive on a configured central host and tenancy must not be initialised.
 *
 * Anything else is answered with 404 so the control plane is invisible from tenant hosts.
 * This must run BEFORE authentication, so a guest on a tenant host gets 404 rather than 401.
 *
 * A tenant identifier (X-Tenant header / ?tenant=) never initialises tenancy here — these
 * routes do not pass through ResolveApiTenancy. An identifier that matches no tenant is still
 * rejected with 404, mirroring ResolveApiTenancy, so a bogus workspace never reaches the
 * control plane.
 */
final class EnsureCentralContext
{
    public function handle(Request $request, Closure $next): Response
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            abort(404);
        }

        if (! in_array($request->getHost(), config('tenancy.central_domains', []), true)) {
            abort(404);
        }

        $tenantIdentifier = $request->header('X-Tenant') ?: $request->query('tenant');

        if (is_string($tenantIdentifier) && $tenantIdentifier !== '' && ! $this->tenantExists($tenantIdentifier)) {
            abort(404);
        }

        return $next($request);
    }

    private function tenantExists(string $identifier): bool
    {
        return Tenant::query()->whereKey($identifier)->exists()
            || Tenant::query()->whereHas('domains', fn ($q) => $q->where('domain', $identifier))->exists();
    }
}
