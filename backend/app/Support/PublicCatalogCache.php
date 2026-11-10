<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * PRD-07: cache of the public pricing payload (GET /api/v1/public/plans).
 *
 * Central scope (TenantCache::central*): bumping the version drops every locale at once.
 * The short TTL also covers writes that fire no model events (the catalog seeders use the
 * query builder) and the founder-slot counter.
 */
final class PublicCatalogCache
{
    public const NAMESPACE = 'public_catalog';

    public const TTL_SECONDS = 300;

    public static function key(string $locale): string
    {
        return TenantCache::centralKey(self::NAMESPACE.':'.TenantCache::centralVersion(self::NAMESPACE).':'.$locale);
    }

    public static function forget(): void
    {
        try {
            TenantCache::centralBump(self::NAMESPACE);
        } catch (Throwable) {
            // Cache store unavailable: nothing cached can be served stale either.
        }
    }

    /**
     * @param  callable(): array<string, mixed>  $build
     * @return array<string, mixed>
     */
    public static function remember(string $locale, callable $build): array
    {
        try {
            $key = self::key($locale);
            $cached = Cache::get($key);
            if (is_array($cached)) {
                /** @var array<string, mixed> $cached */
                return $cached;
            }
        } catch (Throwable) {
            return $build();
        }

        $payload = $build();

        try {
            Cache::put($key, $payload, self::TTL_SECONDS);
        } catch (Throwable) {
            // A cache write failure only costs a rebuild on the next request.
        }

        return $payload;
    }
}
