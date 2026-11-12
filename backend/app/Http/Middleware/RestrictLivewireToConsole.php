<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * SEC-21 (4): Livewire is installed only as a dependency of the Pulse dashboard, which lives on the
 * platform console. Its global endpoints (`/livewire-<hash>/update`, `/upload-file`,
 * `/preview-file/*`, the scripts) are registered without a host, so they answered on every shop
 * domain too. They are now 404 anywhere the control plane may not answer (same rule as Pulse's own
 * routes: EnsureCentralContext::allowsControlPlane, and never with a tenant identifier).
 *
 * Global middleware: runs before routing, so a refused request never reaches Livewire.
 */
final class RestrictLivewireToConsole
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::isLivewirePath($request) && (
            ! EnsureCentralContext::allowsControlPlane($request) || EnsureCentralContext::namesTenant($request)
        )) {
            abort(404);
        }

        return $next($request);
    }

    public static function isLivewirePath(Request $request): bool
    {
        $path = self::normalisedPath($request);
        $prefix = strtolower(class_exists(EndpointResolver::class) ? EndpointResolver::prefix() : '/livewire');

        // The hashed v4 prefix, and the classic "/livewire/..." paths of older releases.
        return $path === $prefix || str_starts_with($path, $prefix.'/')
            || $path === '/livewire' || str_starts_with($path, '/livewire/');
    }

    /**
     * The path the router will match, made comparable: percent-decoded (the router matches the
     * DECODED path, so "/%6Civewire-<hash>/update" must count), decoded again while it still
     * changes (double encoding, bounded), lower-cased, slashes collapsed. Stricter than the router
     * on purpose: a path that only LOOKS like Livewire after extra decoding is refused too.
     */
    private static function normalisedPath(Request $request): string
    {
        $path = $request->decodedPath();

        for ($i = 0; $i < 3; $i++) {
            $decoded = rawurldecode($path);
            if ($decoded === $path) {
                break;
            }
            $path = $decoded;
        }

        $path = (string) preg_replace('#/+#', '/', '/'.ltrim(str_replace('\\', '/', $path), '/'));

        return strtolower($path);
    }
}
