<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Runtime gate for the testing-only passwordless quick login.
 *
 * Allowed only when ALL hold:
 *  - config('auth.quick_login.enabled') is true (QUICK_LOGIN_ENABLED, off by default),
 *  - the application is not running in production,
 *  - a tenant context is initialised (never on the central host, never for central users).
 */
final class QuickLoginGate
{
    public static function allowed(): bool
    {
        if (config('auth.quick_login.enabled') !== true) {
            return false;
        }

        if (app()->isProduction()) {
            return false;
        }

        return function_exists('tenancy') && tenancy()->initialized === true;
    }

    public static function tokenTtlMinutes(): int
    {
        $ttl = (int) config('auth.quick_login.token_ttl_minutes', 480);

        return $ttl > 0 ? $ttl : 480;
    }
}
