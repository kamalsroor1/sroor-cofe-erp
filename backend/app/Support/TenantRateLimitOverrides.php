<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\TenantRateLimitOverride;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Read side of the temporary per-tenant rate-limit raise (IDEN-4.6 ext, CTO W1 Q1).
 *
 * The named limiters (AppServiceProvider::registerRateLimiters) ask here for the active
 * override of the tenant a request targets. All active overrides are cached together in
 * the CENTRAL scope (TenantCache::centralKey, identical inside and outside tenancy) for
 * CACHE_SECONDS; RaiseTenantRateLimitAction calls flush() after commit, and expiry is
 * re-checked on every read, so an expired override never applies.
 *
 * Fail-safe: a missing table or an unreachable central DB means "no override" (the
 * default limits apply); it never breaks a login.
 */
final class TenantRateLimitOverrides
{
    private const CACHE_NAMESPACE = 'rate-limit-overrides';

    private const CACHE_SECONDS = 60;

    /**
     * Active raised limits of a tenant, keyed by override column; null when none.
     *
     * @return array<string, int>|null
     */
    public static function forTenant(string $tenantId): ?array
    {
        foreach (self::active() as $entry) {
            if ($entry['tenant_id'] === $tenantId) {
                return $entry['limits'];
            }
        }

        return null;
    }

    /**
     * The override matching a workspace code (tenant id or slug, case-insensitive), as used
     * by the public tenant resolver.
     *
     * @return array{tenant_id: string, limits: array<string, int>}|null
     */
    public static function forWorkspaceCode(mixed $code): ?array
    {
        if (! is_string($code)) {
            return null;
        }

        $code = mb_strtolower(trim($code));

        if ($code === '' || mb_strlen($code) > 255) {
            return null;
        }

        foreach (self::active() as $entry) {
            if ($entry['tenant_id_lower'] === $code || ($entry['slug'] !== null && $entry['slug'] === $code)) {
                return ['tenant_id' => $entry['tenant_id'], 'limits' => $entry['limits']];
            }
        }

        return null;
    }

    /** Make every limiter see a new or ended override on its next request. */
    public static function flush(): void
    {
        TenantCache::centralBump(self::CACHE_NAMESPACE);
    }

    /**
     * @return list<array{tenant_id: string, tenant_id_lower: string, slug: string|null, expires_at: int, limits: array<string, int>}>
     */
    private static function active(): array
    {
        $now = now()->getTimestamp();

        return array_values(array_filter(
            self::cached(),
            static fn (array $entry): bool => $entry['expires_at'] > $now,
        ));
    }

    /**
     * @return list<array{tenant_id: string, tenant_id_lower: string, slug: string|null, expires_at: int, limits: array<string, int>}>
     */
    private static function cached(): array
    {
        try {
            $key = TenantCache::centralKey(self::CACHE_NAMESPACE.':'.TenantCache::centralVersion(self::CACHE_NAMESPACE));

            /** @var list<array{tenant_id: string, tenant_id_lower: string, slug: string|null, expires_at: int, limits: array<string, int>}> $entries */
            $entries = Cache::remember($key, self::CACHE_SECONDS, static fn (): array => self::load());

            return $entries;
        } catch (Throwable $e) {
            report($e);

            return [];
        }
    }

    /**
     * Newest active override per tenant.
     *
     * @return list<array{tenant_id: string, tenant_id_lower: string, slug: string|null, expires_at: int, limits: array<string, int>}>
     */
    private static function load(): array
    {
        $entries = [];
        $seen = [];

        $overrides = TenantRateLimitOverride::query()
            ->active()
            ->with('tenant:id,slug')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        foreach ($overrides as $override) {
            $tenantId = (string) $override->tenant_id;

            if (isset($seen[$tenantId]) || $override->limits() === []) {
                continue;
            }

            $seen[$tenantId] = true;
            $slug = $override->tenant?->slug;

            $entries[] = [
                'tenant_id' => $tenantId,
                'tenant_id_lower' => mb_strtolower($tenantId),
                'slug' => is_string($slug) && $slug !== '' ? mb_strtolower($slug) : null,
                'expires_at' => $override->expires_at->getTimestamp(),
                'limits' => $override->limits(),
            ];
        }

        return $entries;
    }
}
