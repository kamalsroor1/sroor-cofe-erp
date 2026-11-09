<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Security\ContentSecurityPolicy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-07: Content-Security-Policy for the tenant and central-hub SPA shell.
 *
 * Route middleware (alias `spa.headers`) attached ONLY to the routes that render the
 * `app` / `spa` view, so JSON APIs, Pulse/Telescope, the manifest, sw.js and the Blade print
 * pages (CDN scripts, inline handlers) never receive it. The platform-console host is skipped:
 * AdminSecurityHeaders (global) already sends its own policy there.
 *
 * The nonce is issued BEFORE the view renders so @vite tags and the inline scripts of
 * app.blade.php carry it. Auth tokens live in localStorage, so a script-injection barrier
 * is the main defence.
 *
 * Not sent here: HSTS (nginx sends it) and X-Frame-Options (nginx sends SAMEORIGIN;
 * frame-ancestors 'none' in the CSP covers framing).
 */
final class SpaSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        if (EnsureCentralContext::isAdminHost($request)) {
            return $next($request);
        }

        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if ($response->isRedirection()) {
            return $response;
        }

        $name = config('security.csp.report_only') === true
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $headers = $response->headers;
        $headers->set($name, ContentSecurityPolicy::spa($nonce, $this->isCentralHub($request)));
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('X-Content-Type-Options', 'nosniff');
        // Nothing in the SPA uses camera/mic/geolocation today; a future web barcode scanner must lift camera=().
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        return $response;
    }

    /** The platform's own (non-tenant) host, where the workspace-connect page lives. */
    private function isCentralHub(Request $request): bool
    {
        /** @var list<string> $central */
        $central = (array) config('tenancy.central_domains', []);

        return in_array(strtolower($request->getHost()), $central, true);
    }
}
