<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Tenant-scoped cache key helper.
 *
 * The cache store (database/file) is shared by every tenant because the
 * CacheTenancyBootstrapper is disabled (those stores do not support tags).
 * Every tenant-data cache key must therefore embed the tenant id.
 */
final class TenantCache
{
    public static function key(string $key): string
    {
        $scope = tenancy()->initialized && tenant()
            ? (string) tenant()->getTenantKey()
            : 'central';

        return 't:'.$scope.':'.$key;
    }

    /**
     * Current version token of a cache namespace (part of every key in it).
     */
    public static function version(string $namespace): string
    {
        return (string) Cache::get(self::key($namespace.':v'), '1');
    }

    /**
     * Invalidate every key of a namespace for the current tenant by moving its version.
     * Uses forever() instead of increment() because the database store needs an existing row to increment.
     */
    public static function bump(string $namespace): void
    {
        Cache::forever(self::key($namespace.':v'), (string) now()->getTimestampMs().'-'.Str::random(6));
    }
}
