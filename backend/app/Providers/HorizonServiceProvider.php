<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\CentralPermission;
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
 *  - user: only the platform operator signed in on the `central_web` session guard (the
 *    same session the Telescope signed link opens, IDEN-1.7) holding
 *    `super_admin.monitoring.view` on the `central` guard. Never the tenant-side `web`
 *    guard, whose id may collide with a central_users id. Unlike the package default
 *    there is NO `local` environment bypass.
 */
class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /** Session guard of platform operators (App\Models\CentralUser). */
    public const GUARD = 'central_web';

    protected function authorization(): void
    {
        $this->gate();

        Horizon::auth(static function (Request $request): bool {
            return self::isCentralHost($request)
                && Gate::forUser($request->user(self::GUARD))->check('viewHorizon');
        });
    }

    protected function gate(): void
    {
        // PlatformSuperAdmin::can is false for null, a tenant/legacy User, an inactive
        // operator, or inside tenancy — never a TypeError.
        Gate::define('viewHorizon', static function (mixed $user = null): bool {
            return PlatformSuperAdmin::can($user, CentralPermission::MonitoringView);
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
