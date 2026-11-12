<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * SEC-21: parses the CORS origin lists of config/cors.php from the environment. Nothing here
 * knows a domain: every origin comes from env (CORS_ALLOWED_ORIGINS / CORS_ALLOWED_ORIGIN_PATTERNS).
 *
 *  - origins: comma-separated exact origins ("https://app.example.com"), or "*" alone. An
 *    entry with a wildcard inside is ignored here. A list without any usable entry falls back to
 *    the caller's default;
 *  - patterns: comma-separated host wildcards ("https://*.example.com", optional ":port"), each
 *    turned into a strict regex where "*" is ONE DNS label ([a-z0-9-]+), so "*.example.com"
 *    never matches "evil.com/.example.com", "a.b.example.com" or "example.com.evil.com".
 *    Anything else (paths, query strings, a "*" elsewhere) is ignored.
 */
final class CorsOrigins
{
    /**
     * @param  list<string>  $default  used when the variable is unset or holds no entry
     * @return list<string>
     */
    public static function origins(mixed $raw, array $default): array
    {
        $origins = [];
        foreach (self::split($raw) as $entry) {
            if ($entry === '*') {
                $origins[] = '*';
            } elseif (! str_contains($entry, '*')) {
                // A wildcard here would become php-cors' loose ".*" pattern: wildcards belong in
                // the patterns list, which builds a strict one.
                $origins[] = rtrim($entry, '/');
            }
        }

        return $origins === [] ? $default : array_values(array_unique($origins));
    }

    /**
     * @return list<string>
     */
    public static function patterns(mixed $raw): array
    {
        $patterns = [];
        foreach (self::split($raw) as $entry) {
            $pattern = self::toRegex($entry);
            if ($pattern !== null) {
                $patterns[] = $pattern;
            }
        }

        return array_values(array_unique($patterns));
    }

    public static function toRegex(string $entry): ?string
    {
        if (preg_match('#^(https?)://((?:\*\.)?[a-z0-9.-]+)(:\d{1,5})?$#i', $entry, $m) !== 1) {
            return null;
        }

        $host = preg_quote(strtolower($m[2]), '#');
        $host = str_replace('\*\.', '[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.', $host);
        $port = isset($m[3]) ? preg_quote($m[3], '#') : ''; // an unmatched trailing group is absent

        return '#^'.strtolower($m[1]).'://'.$host.$port.'\z#i';
    }

    /**
     * @return list<string>
     */
    private static function split(mixed $raw): array
    {
        if (! is_string($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw)), static fn (string $v): bool => $v !== ''));
    }
}
