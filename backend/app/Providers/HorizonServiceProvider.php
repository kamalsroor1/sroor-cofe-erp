<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Support\PlatformSuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

/**
 * Horizon dashboard access (W1 hardening note 4).
 *
 * Horizon shows every queued job of every tenant, so it is a platform tool:
 *  - host: only a central (admin) host, never a tenant host. config/horizon.php adds
 *    EnsureCentralContext to the route middleware (tenant host = 404); the auth callback
 *    below re-checks the host as defence in depth.
 *  - user: only the platform super admin (viewHorizon → PlatformSuperAdmin). Unlike the
 *    package default there is NO `local` environment bypass.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(static function (Request $request): bool {
            // The gate is evaluated for the request's own user (session on the central host).
            return self::isCentralHost($request)
                && Gate::forUser($request->user())->check('viewHorizon');
        });
    }

    protected function gate(): void
    {
        Gate::define('viewHorizon', static function (?User $user = null): bool {
            return PlatformSuperAdmin::check($user);
        });
    }

    private static function isCentralHost(Request $request): bool
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return false;
        }

        $centralDomains = config('tenancy.central_domains', []);

        return in_array($request->getHost(), is_array($centralDomains) ? $centralDomains : [], true);
    }
}
