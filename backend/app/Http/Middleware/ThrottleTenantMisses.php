<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Providers\AppServiceProvider;
use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * IDEN-4.6: per-IP cap on unknown-workspace probes.
 *
 * ResolveApiTenancy answers 404 for an unknown X-Tenant / ?tenant= before any named limiter
 * runs (tenancy must be resolved first so tenant-scoped limiter keys work). Without this
 * middleware that 404 is an unthrottled oracle for which workspace codes exist, and each
 * probe costs central DB lookups. It must run before ResolveApiTenancy (see the priority
 * list in bootstrap/app.php).
 *
 * Only misses are counted, using the tenant-resolve budget (rate_limits.tenant_resolve) in a
 * separate bucket. Once the budget is spent, every request from that IP that names a tenant
 * is refused with 429 (also for real tenants), so the answer never reveals whether a code exists.
 */
final class ThrottleTenantMisses
{
    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->namesTenant($request)) {
            return $next($request);
        }

        $key = 'tenant-miss|'.$request->ip();
        $maxAttempts = $this->maxAttempts();

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = max(1, RateLimiter::availableIn($key));

            throw new ThrottleRequestsException(__('auth.too_many_requests'), null, [
                'Retry-After' => $retryAfter,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($retryAfter)->getTimestamp(),
            ]);
        }

        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND && ! tenancy()->initialized) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
        }

        return $response;
    }

    /**
     * True when the request selects a tenant the same way ResolveApiTenancy does
     * (header, query, body, or a non-central host). The public workspace resolver is skipped
     * by route name, mirroring ResolveApiTenancy::PUBLIC_CENTRAL_ROUTES (it has its own
     * `tenant-resolve` limiter). The control plane (routes/central.php) never runs this
     * middleware, and its admin hosts are listed in tenancy.central_domains (IDEN-1.11).
     */
    private function namesTenant(Request $request): bool
    {
        if ($request->routeIs(ResolveApiTenancy::PUBLIC_CENTRAL_ROUTES)) {
            return false;
        }

        if ($request->header('X-Tenant') || $request->query('tenant') || $request->input('tenant')) {
            return true;
        }

        // A platform-console host is never a tenant lookup (ResolveApiTenancy step 0).
        if (EnsureCentralContext::isAdminHost($request)) {
            return false;
        }

        $centralDomains = config('tenancy.central_domains', []);

        return ! in_array($request->getHost(), is_array($centralDomains) ? $centralDomains : [], true);
    }

    private function maxAttempts(): int
    {
        $value = config('rate_limits.tenant_resolve.per_minute', AppServiceProvider::TENANT_RESOLVE_PER_MINUTE);

        return max(1, is_numeric($value) ? (int) $value : AppServiceProvider::TENANT_RESOLVE_PER_MINUTE);
    }
}
