<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\QuickLoginGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defense in depth for the testing-only quick login: even when the routes are
 * registered (or a stale route cache still has them), answer 404 unless the
 * runtime gate allows it.
 */
final class EnsureQuickLoginAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! QuickLoginGate::allowed()) {
            abort(404);
        }

        return $next($request);
    }
}
