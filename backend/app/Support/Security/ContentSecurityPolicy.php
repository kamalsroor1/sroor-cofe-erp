<?php

declare(strict_types=1);

namespace App\Support\Security;

use Illuminate\Support\Facades\Vite;

/**
 * Builds the Content-Security-Policy header value for the SPA shell.
 *
 * Shared by AdminSecurityHeaders (platform console host) and SpaSecurityHeaders (tenant and
 * central-hub shell). Scripts are restricted to 'self' plus the per-request nonce issued by
 * Vite::useCspNonce(); there is no 'unsafe-inline' / 'unsafe-eval' for scripts. Styles keep
 * 'unsafe-inline' for now (boot-splash style attributes and the <style> SweetAlert2 injects).
 */
final class ContentSecurityPolicy
{
    /** Platform console (admin host): exact policy shipped with IDEN-1.11. */
    public static function admin(string $nonce): string
    {
        return self::render(self::baseDirectives($nonce, ["'self'", 'data:', 'blob:']));
    }

    /**
     * Tenant / central-hub SPA shell.
     *
     * img-src additionally allows the APP_URL origin (platform assets under /central-assets).
     * On the central hub (workspace-connect page) the tenant logo is a cross-origin image on
     * the tenant's own host, which can be a custom domain, so a host wildcard is not enough
     * and the hub allows any https: image (images cannot execute code; script-src stays strict).
     * Tenant hosts only ever load their logo from their own origin.
     */
    public static function spa(string $nonce, bool $centralHub): string
    {
        $img = ["'self'", 'data:', 'blob:'];

        $appOrigin = self::origin((string) config('app.url'));
        if ($appOrigin !== null) {
            $img[] = $appOrigin;
        }

        if ($centralHub) {
            $img[] = 'https:';
            if (! app()->isProduction()) {
                $img[] = 'http:';
            }
        }

        $directives = self::baseDirectives($nonce, array_values(array_unique($img)));
        $directives['frame-src'] = ["'none'"];

        return self::render($directives);
    }

    /**
     * @param  list<string>  $img
     * @return array<string, list<string>>
     */
    private static function baseDirectives(string $nonce, array $img): array
    {
        [$devScript, $devConnect] = self::viteDevServerSources();

        return [
            'default-src' => ["'self'"],
            'script-src' => array_merge(["'self'", "'nonce-{$nonce}'"], $devScript),
            'style-src' => array_merge(["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'], $devScript),
            'font-src' => ["'self'", 'https://fonts.gstatic.com', 'data:'],
            'img-src' => $img,
            'connect-src' => array_merge(["'self'"], $devConnect),
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];
    }

    /**
     * @param  array<string, list<string>>  $directives
     */
    private static function render(array $directives): string
    {
        return implode('; ', array_map(
            static fn (string $name, array $sources): string => $name.' '.implode(' ', $sources),
            array_keys($directives),
            $directives,
        ));
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return strtolower($parts['scheme']).'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /**
     * Local development only: the Vite dev server origin (and its websocket for HMR).
     *
     * @return array{0: list<string>, 1: list<string>} script/style sources and connect sources
     */
    private static function viteDevServerSources(): array
    {
        if (! app()->environment('local') || ! Vite::isRunningHot()) {
            return [[], []];
        }

        $hot = @file_get_contents(public_path('hot'));
        $origin = is_string($hot) ? rtrim(trim($hot), '/') : '';
        $parts = $origin !== '' ? parse_url($origin) : false;

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return [[], []];
        }

        $hostPort = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $ws = ($parts['scheme'] === 'https' ? 'wss' : 'ws').'://'.$hostPort;

        return [[$origin], [$origin, $ws]];
    }
}
