<?php

declare(strict_types=1);

namespace App\Support;

use App\Http\Middleware\EnsureCentralContext;

/**
 * The platform's public hosts and URLs, from configuration only (never env() at runtime,
 * never a hardcoded domain), so they survive `config:cache` and work on any topology:
 * production (https, baraa-solutions.com) and the local Laragon setup (http, sroor.test).
 *
 *  - tenant base domain: config('tenancy.central_domain') (env CENTRAL_DOMAIN);
 *    a tenant's default host is "<slug>.<base domain>";
 *  - scheme (and port): taken from config('app.url'); production is always https;
 *  - platform console: the first config('central.admin_domains') host, falling back to the
 *    base domain when none is configured (local/testing only; production then 404s).
 */
final class PlatformHosts
{
    /** Landing page of the platform console (SPA route, resources/js/router). */
    public const CONSOLE_TENANTS_PATH = '/super-admin/tenants';

    private const DEFAULT_BASE_DOMAIN = 'baraa-solutions.com';

    /** The domain tenant subdomains hang off, e.g. "baraa-solutions.com" or "sroor.test". */
    public static function tenantBaseDomain(): string
    {
        $domain = config('tenancy.central_domain');
        $domain = is_string($domain) ? strtolower(trim($domain)) : '';

        return $domain !== '' ? $domain : self::DEFAULT_BASE_DOMAIN;
    }

    /** Default host of a tenant: "<slug>.<base domain>". */
    public static function tenantHost(string $slug): string
    {
        return strtolower(trim($slug)).'.'.self::tenantBaseDomain();
    }

    /** "http" or "https" from config('app.url'); always "https" in production. */
    public static function scheme(): string
    {
        if (app()->isProduction()) {
            return 'https';
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME);

        return is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true)
            ? strtolower($scheme)
            : 'https';
    }

    /**
     * Absolute URL on $host with the platform scheme. The port of config('app.url') is kept
     * (e.g. `php artisan serve` on :8000) unless $host already carries one.
     */
    public static function url(string $host, string $path = '/'): string
    {
        $host = strtolower(trim($host));
        $port = parse_url((string) config('app.url'), PHP_URL_PORT);

        if (is_int($port) && ! str_contains($host, ':')) {
            $host .= ':'.$port;
        }

        return self::scheme().'://'.$host.'/'.ltrim($path, '/');
    }

    /** Origin (no trailing slash) of a tenant host, e.g. "https://shop.baraa-solutions.com". */
    public static function origin(string $host): string
    {
        return rtrim(self::url($host, ''), '/');
    }

    /** Host of the platform console. */
    public static function consoleHost(): string
    {
        return EnsureCentralContext::adminHosts()[0] ?? self::tenantBaseDomain();
    }

    /** Absolute URL of a platform-console page (defaults to the tenants list). */
    public static function consoleUrl(string $path = self::CONSOLE_TENANTS_PATH): string
    {
        return self::url(self::consoleHost(), $path);
    }

    /**
     * Central (non-tenant) hosts the tenant SPA may treat as the workspace hub. The admin
     * hosts are excluded on purpose: this list is rendered into every tenant page.
     *
     * @return list<string>
     */
    public static function publicCentralDomains(): array
    {
        $central = config('tenancy.central_domains', []);
        $admin = EnsureCentralContext::adminHosts();

        $hosts = array_map(
            static fn (mixed $host): string => is_string($host) ? strtolower(trim($host)) : '',
            is_array($central) ? $central : [],
        );

        return array_values(array_unique(array_filter(
            $hosts,
            static fn (string $host): bool => $host !== '' && ! in_array($host, $admin, true),
        )));
    }
}
