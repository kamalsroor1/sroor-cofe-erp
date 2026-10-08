<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Tenant-scoped cache key helper.
 *
 * The cache store (database/file) is shared by every tenant because the
 * CacheTenancyBootstrapper is disabled (those stores do not support tags).
 * Every tenant-data cache key must therefore embed the tenant id.
 *
 * Three scopes:
 *  - Implicit: key()/version()/bump() derive the scope from the current context
 *    (the initialized tenant, else central). Use them only for data read and
 *    invalidated inside the same request context.
 *  - Explicit tenant: keyFor()/versionFor()/bumpFor($tenantId). Any invalidation
 *    of tenant data from a central context (super-admin, subscription change,
 *    queued central job) MUST use these; bump() there would move the central
 *    version and leave the tenant's cache stale.
 *  - Explicit central: centralKey()/centralVersion()/centralBump(). Platform data
 *    that is read inside tenant requests (branding, plans) MUST use these, so the
 *    key is identical with tenancy initialized or ended.
 *
 * Key layout: tenant scope "t:{tenantId}:{key}", central scope "c:{key}". The
 * distinct prefix means no tenant id (even one literally named "central") can
 * ever collide with the central scope.
 */
final class TenantCache
{
    private const TENANT_PREFIX = 't:';

    private const CENTRAL_PREFIX = 'c:';

    /**
     * Key in the current context's scope (initialized tenant, else central).
     */
    public static function key(string $key): string
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        return $tenant !== null
            ? self::keyFor((string) $tenant->getTenantKey(), $key)
            : self::centralKey($key);
    }

    /**
     * Current version token of a cache namespace (part of every key in it).
     */
    public static function version(string $namespace): string
    {
        return self::readVersion(self::key($namespace.':v'));
    }

    /**
     * Invalidate every key of a namespace for the current context by moving its version.
     */
    public static function bump(string $namespace): void
    {
        self::writeVersion(self::key($namespace.':v'));
    }

    /**
     * Key in an explicit tenant's scope, independent of the current context.
     * Equals key($key) while that tenant is initialized.
     */
    public static function keyFor(string $tenantId, string $key): string
    {
        $tenantId = trim($tenantId);

        if ($tenantId === '') {
            throw new InvalidArgumentException('TenantCache::keyFor() requires a non-empty tenant id.');
        }

        return self::TENANT_PREFIX.$tenantId.':'.$key;
    }

    public static function versionFor(string $tenantId, string $namespace): string
    {
        return self::readVersion(self::keyFor($tenantId, $namespace.':v'));
    }

    /**
     * Invalidate a namespace of one tenant from any context (e.g. central super-admin code).
     */
    public static function bumpFor(string $tenantId, string $namespace): void
    {
        self::writeVersion(self::keyFor($tenantId, $namespace.':v'));
    }

    /**
     * Key in the platform (central) scope; identical inside and outside tenancy.
     */
    public static function centralKey(string $key): string
    {
        return self::CENTRAL_PREFIX.$key;
    }

    public static function centralVersion(string $namespace): string
    {
        return self::readVersion(self::centralKey($namespace.':v'));
    }

    /**
     * Invalidate a platform namespace for every context (central and all tenant requests).
     */
    public static function centralBump(string $namespace): void
    {
        self::writeVersion(self::centralKey($namespace.':v'));
    }

    private static function readVersion(string $versionKey): string
    {
        return (string) Cache::get($versionKey, '1');
    }

    /**
     * Uses forever() instead of increment() because the database store needs an existing row to increment.
     */
    private static function writeVersion(string $versionKey): void
    {
        Cache::forever($versionKey, (string) now()->getTimestampMs().'-'.Str::random(6));
    }
}
