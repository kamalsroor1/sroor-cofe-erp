<?php

namespace App\Providers;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /** Session guard of platform operators (App\Models\CentralUser), opened by /telescope-access. */
    public const GUARD = 'central_web';

    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters(['_token']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    /**
     * Security audit (W2 lane 3I): replaces Laravel's default, which lets anyone in when
     * APP_ENV=local. The viewTelescope gate always applies, unless the environment is local
     * AND config('telescope.local_open') (TELESCOPE_LOCAL_OPEN) is explicitly true.
     *
     * W2-B3 lane 3L: the operator is read from the `central_web` session guard (the one the
     * signed /telescope-access link signs into), never the tenant-side default `web` guard,
     * whose id may collide with a central_users id. Mirrors HorizonServiceProvider.
     */
    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(static function (Request $request): bool {
            return self::localOpen()
                || Gate::forUser(self::operator($request))->check('viewTelescope');
        });
    }

    /** The CentralUser (or null) on the central_web session guard. */
    private static function operator(Request $request): mixed
    {
        // A request built outside the HTTP kernel has no user resolver; read the guard directly.
        return $request->user(self::GUARD) ?? Auth::guard(self::GUARD)->user();
    }

    /** True only when the environment is local AND TELESCOPE_LOCAL_OPEN=true. */
    public static function localOpen(): bool
    {
        return app()->environment('local') && config('telescope.local_open') === true;
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        // Session user only. A bearer token in the query string is never accepted here;
        // browsers get a session through the signed /telescope-access link (AUTH-4).
        // IDEN-1.4: the operator is an App\Models\CentralUser holding super_admin.monitoring.view
        // on the `central` guard. PlatformSuperAdmin::can() takes any value and is false for
        // everything else (null, a tenant/legacy User, an inactive operator, inside tenancy).
        Gate::define('viewTelescope', static function (mixed $user = null): bool {
            return PlatformSuperAdmin::can($user, CentralPermission::MonitoringView);
        });
    }
}
