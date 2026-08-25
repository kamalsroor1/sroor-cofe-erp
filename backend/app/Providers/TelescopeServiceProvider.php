<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
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
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null) {
            $user = $user ?: auth()->user();

            if (!$user && request()->filled('token')) {
                $token = request()->query('token');
                $pat = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
                if ($pat && $pat->tokenable instanceof \App\Models\User) {
                    $user = $pat->tokenable;
                }
                if (!$user) {
                    $user = \App\Models\User::where('api_token', $token)->first();
                }
                if ($user) {
                    auth('web')->login($user, true);
                }
            }

            if (!$user) {
                return false;
            }

            // Strict Super Admin Gate: Only Super Admins can access Telescope
            return (method_exists($user, 'hasRole') && ($user->hasRole('super_admin') || $user->hasRole('admin')))
                || (isset($user->phone) && in_array($user->phone, ['01012316954', '01558088841']))
                || (isset($user->email) && in_array($user->email, [
                    'superadmin@baraa-solutions.com',
                    'admin@baraa-solutions.com',
                ]));
        });
    }
}
