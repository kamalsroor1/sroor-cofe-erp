<?php

declare(strict_types=1);

namespace App\Services\Entitlements;

use App\Contracts\TenantFeatureManagerInterface;
use App\Models\Tenant;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Event;
use Laravel\Pennant\Feature;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

/**
 * Pennant as an API layer only (Q-E1, CTO 2026-10-08): every feature key of
 * config('entitlements.features') (and every legacy alias) is defined in Pennant with a
 * resolver that asks TenantEntitlementService. Nothing is stored: config/pennant.php pins
 * the `array` store, which only memoises a value in memory. That memo is flushed whenever
 * tenancy is initialized or ended and on every entitlement bump (EntitlementsCacheObserver,
 * TenantEntitlementService::forget()), on top of Pennant's own flushes (Octane request,
 * queued job), so a long-lived process never answers from a value older than the service's
 * versioned cache.
 *
 * Scope = the initialized Tenant. Outside tenancy the scope is null and every feature is
 * off (default deny); central code that needs an answer passes the tenant explicitly:
 * Feature::for($tenant)->active('reports.advanced').
 */
final class EntitlementFeatures
{
    public function __construct(private readonly Container $container) {}

    public function register(): void
    {
        Feature::resolveScopeUsing(static function (): ?Tenant {
            $tenant = tenancy()->initialized ? tenant() : null;

            return $tenant instanceof Tenant ? $tenant : null;
        });

        Event::listen([TenancyInitialized::class, TenancyEnded::class], static function (): void {
            self::flush();
        });

        foreach ($this->keys() as $key) {
            Feature::define($key, fn (?Tenant $tenant): bool => $tenant instanceof Tenant
                && $this->container->make(TenantFeatureManagerInterface::class)->isFeatureEnabled($tenant, $key));
        }
    }

    /** Drop Pennant's in-memory values: the next check asks the entitlement service again. */
    public static function flush(): void
    {
        Feature::flushCache();
    }

    /**
     * Canonical keys plus legacy aliases, without duplicates.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        $keys = array_merge(
            (array) config('entitlements.features', []),
            array_keys((array) config('entitlements.aliases', [])),
        );

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $key): string => is_string($key) ? $key : '', $keys),
            static fn (string $key): bool => $key !== '',
        )));
    }
}
