<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * IDEN-1.11: browser hardening on the platform-console (admin) host only.
 *
 * Runs on every request (global middleware) and does nothing unless the request host is
 * one of config('central.admin_domains') (EnsureCentralContext::isAdminHost). There it:
 *  - issues a per-request CSP nonce BEFORE the response is rendered (Vite::useCspNonce),
 *    so @vite tags and the nonce'd inline scripts of app.blade.php are allowed;
 *  - sends a strict Content-Security-Policy: scripts only from 'self' + the nonce (no
 *    'unsafe-inline', no 'unsafe-eval'), frame-ancestors 'none', object-src 'none',
 *    base-uri / form-action 'self'. Styles allow 'unsafe-inline' (inline style attributes
 *    of the boot splash and runtime-injected component styles; no script execution);
 *  - Referrer-Policy no-referrer, HSTS (1 year + subdomains), X-Frame-Options DENY,
 *    X-Content-Type-Options nosniff, Cross-Origin-Opener-Policy same-origin and a
 *    restrictive Permissions-Policy.
 *
 * Local development only: when the Vite dev server is running (public/hot) and the app is
 * in the `local` environment, its origin (and its websocket for HMR) is added.
 */
final class AdminSecurityHeaders
{
    private const HSTS = 'max-age=31536000; includeSubDomains';

    public function handle(Request $request, Closure $next): Response
    {
        if (! EnsureCentralContext::isAdminHost($request)) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('Content-Security-Policy', $this->policy($nonce));
        $headers->set('Referrer-Policy', 'no-referrer');
        $headers->set('Strict-Transport-Security', self::HSTS);
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        return $response;
    }

    private function policy(string $nonce): string
    {
        [$devScript, $devConnect] = $this->viteDevServerSources();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => array_merge(["'self'", "'nonce-{$nonce}'"], $devScript),
            'style-src' => array_merge(["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'], $devScript),
            'font-src' => ["'self'", 'https://fonts.gstatic.com', 'data:'],
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => array_merge(["'self'"], $devConnect),
            'worker-src' => ["'self'"],
            'manifest-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return implode('; ', array_map(
            static fn (string $name, array $sources): string => $name.' '.implode(' ', $sources),
            array_keys($directives),
            $directives,
        ));
    }

    /**
     * @return array{0: list<string>, 1: list<string>} script/style sources and connect sources
     */
    private function viteDevServerSources(): array
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
