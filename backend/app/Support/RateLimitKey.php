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

    /**
     * Limiter identity of a client address: IPv4 as is, IPv6 collapsed to its /64 prefix
     * (one subscriber owns a whole /64, so per-address buckets are trivially rotated).
     */
    public static function clientNetwork(string $ip): string
    {
        if (! str_contains($ip, ':')) {
            return $ip;
        }

        $packed = @inet_pton($ip);
        if ($packed === false || strlen($packed) !== 16) {
            return $ip;
        }

        return bin2hex(substr($packed, 0, 8)).'::/64';
    }

    /**
     * Case/space-insensitive, transliterated identifier (login, phone or email).
     *
     * SEC-05: every separator, control and format character (spaces, NBSP, zero-width
     * U+200B/U+FEFF…) is removed before AND after transliteration, so padding a login with
     * characters a case-insensitive DB collation ignores cannot mint a fresh limiter key.
     */
    public static function identifier(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $strip = static fn (string $s): string => (string) preg_replace('/[\p{Z}\p{Cc}\p{Cf}\s]+/u', '', $s);

        return $strip(Str::transliterate(Str::lower($strip((string) $value))));
    }
}
