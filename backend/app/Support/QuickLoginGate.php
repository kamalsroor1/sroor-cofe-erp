<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Runtime (request-time) gate for the testing-only passwordless quick login.
 *
 * Allowed only when ALL hold:
 *  - QuickLogin::allowed(): flag QUICK_LOGIN_ENABLED strictly true AND APP_ENV is local/testing
 *    (never production, never staging),
 *  - a tenant context is initialised (never on the central host, never for central users).
 */
final class QuickLoginGate
{
    public static function allowed(): bool
    {
        if (! QuickLogin::allowed()) {
            return false;
        }

        return function_exists('tenancy') && tenancy()->initialized === true;
    }

    public static function tokenTtlMinutes(): int
    {
        return QuickLogin::tokenTtlMinutes();
    }
}
