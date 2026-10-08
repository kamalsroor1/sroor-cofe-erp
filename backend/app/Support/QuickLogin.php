<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Single source of truth for the testing-only passwordless quick login (IDEN-4.1).
 *
 * allowed() is true only when BOTH hold:
 *  - config('auth.quick_login.enabled') is strictly true (QUICK_LOGIN_ENABLED, off by default),
 *  - APP_ENV is one of ALLOWED_ENVIRONMENTS (local / testing). Staging, production and any
 *    unknown environment are closed whatever the flag says.
 *
 * Request-time checks that also need a tenant context live in QuickLoginGate, which builds on this.
 * guardAgainstProduction() is the boot-time fail-fast (CTO Q-B11).
 */
final class QuickLogin
{
    /** @var list<string> */
    public const ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    /** 8 hours (IDEN-4.2). */
    public const DEFAULT_TOKEN_TTL_MINUTES = 480;

    /** @phpstan-impure Reads runtime config / environment. */
    public static function enabled(): bool
    {
        return config('auth.quick_login.enabled') === true;
    }

    /** @phpstan-impure Reads runtime config / environment. */
    public static function allowed(): bool
    {
        if (! self::enabled()) {
            return false;
        }

        $app = app();

        if ($app->isProduction()) {
            return false;
        }

        return $app->environment(self::ALLOWED_ENVIRONMENTS) === true;
    }

    /** @phpstan-impure Reads runtime config / environment. */
    public static function tokenTtlMinutes(): int
    {
        $ttl = (int) config('auth.quick_login.token_ttl_minutes', self::DEFAULT_TOKEN_TTL_MINUTES);

        return $ttl > 0 ? $ttl : self::DEFAULT_TOKEN_TTL_MINUTES;
    }

    /**
     * Refuse to serve HTTP in production while the quick-login flag is on.
     *
     * Console processes (artisan, queue workers) still boot so an operator can fix the
     * environment and run `php artisan config:clear` / `config:cache`. The message is
     * operator-facing (logs / boot failure), never rendered to end users (APP_DEBUG=false).
     *
     * @throws RuntimeException
     */
    public static function guardAgainstProduction(Application $app): void
    {
        if (! self::enabled() || $app->environment('production') !== true || $app->runningInConsole()) {
            return;
        }

        throw new RuntimeException(
            'Refusing to boot: QUICK_LOGIN_ENABLED=true while APP_ENV=production. '
            .'Quick login is a testing-only feature. Set QUICK_LOGIN_ENABLED=false (or remove it), '
            .'then run "php artisan config:cache" (or "config:clear") and reload the application.'
        );
    }
}
