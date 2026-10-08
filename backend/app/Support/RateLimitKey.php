<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

/**
 * IDEN-4.6: building blocks for rate-limiter keys.
 *
 * The cache store is shared by every tenant (CacheTenancyBootstrapper is off), so a
 * limiter that must be independent per tenant has to embed the tenant id itself.
 * Scope layout mirrors TenantCache: "t:{tenantId}" for a tenant, "c" for central,
 * so no tenant id (even one literally named "c" or "central") collides with central.
 */
final class RateLimitKey
{
    /** Scope of the current request: the initialized tenant, else central. */
    public static function scope(): string
    {
        $tenant = tenancy()->initialized ? tenant() : null;

        return $tenant !== null ? 't:'.$tenant->getTenantKey() : 'c';
    }

    /** Case/space-insensitive, transliterated identifier (login, phone or email). */
    public static function identifier(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return Str::transliterate(Str::lower(trim((string) $value)));
    }
}
