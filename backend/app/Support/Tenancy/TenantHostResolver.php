<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Http\Middleware\EnsureCentralContext;
use App\Models\Tenant;
use App\Support\PlatformHosts;
use Illuminate\Http\Request;

/**
 * BRND-5: resolves the tenant of a public, pre-login asset request from the request
 * HOST only.
 *
 * Unlike ResolveApiTenancy it never reads `X-Tenant`, `?tenant=` or the body: a public
 * asset URL must not let any client pick another shop by changing a header. Returns null
 * (the caller answers 404) for:
 *  - a platform-console (admin) host or any configured central domain;
 *  - a host that is neither a tenant domain record nor "<slug>.<tenant base domain>";
 *  - a tenant whose provisioning is not finished (OPS-2, Tenant::isProvisioned()).
 */
final class TenantHostResolver
{
    public function resolve(Request $request): ?Tenant
    {
        return $this->resolveHost($request->getHost());
    }

    public function resolveHost(string $host): ?Tenant
    {
        $host = strtolower(trim($host));

        if ($host === '' || $this->isCentralHost($host)) {
            return null;
        }

        $tenant = Tenant::query()->whereHas('domains', fn ($query) => $query->where('domain', $host))->first()
            ?? $this->bySubdomain($host);

        return $tenant !== null && $tenant->isProvisioned() ? $tenant : null;
    }

    private function isCentralHost(string $host): bool
    {
        if (in_array($host, EnsureCentralContext::adminHosts(), true)) {
            return true;
        }

        $central = config('tenancy.central_domains', []);
        $central = array_map(
            static fn (mixed $domain): string => is_string($domain) ? strtolower(trim($domain)) : '',
            is_array($central) ? $central : [],
        );

        return in_array($host, $central, true);
    }

    /** "<slug>.<tenant base domain>" (the default host of a tenant without a domain record). */
    private function bySubdomain(string $host): ?Tenant
    {
        $suffix = '.'.PlatformHosts::tenantBaseDomain();

        if (! str_ends_with($host, $suffix)) {
            return null;
        }

        $label = substr($host, 0, -strlen($suffix));

        if ($label === '' || str_contains($label, '.')) {
            return null;
        }

        return Tenant::query()->where('slug', $label)->first()
            ?? Tenant::query()->whereKey($label)->first();
    }
}
