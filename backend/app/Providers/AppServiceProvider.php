<?php

namespace App\Providers;

use App\Contracts\SuperAdminDashboardAnalyticsInterface;
use App\Contracts\TenantFeatureManagerInterface;
use App\Contracts\TenantProvisionerInterface;
use App\Models\Tenant;
use App\Observers\TenantObserver;
use App\Services\SuperAdminAnalyticsService;
use App\Services\TenantFeatureManager;
use App\Services\TenantProvisionerService;
use App\Support\PlatformSuperAdmin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TenantProvisionerInterface::class,
            TenantProvisionerService::class
        );

        $this->app->bind(
            TenantFeatureManagerInterface::class,
            TenantFeatureManager::class
        );

        $this->app->bind(
            SuperAdminDashboardAnalyticsInterface::class,
            SuperAdminAnalyticsService::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Implicitly grant Super Admin all permissions, and Store Admin all standard ERP permissions
        Gate::before(function ($user, $ability) {
            if (PlatformSuperAdmin::check($user)) {
                return true;
            }
            // Platform-only abilities: a store `admin` must never pass these through the
            // blanket grant below (Telescope/Pulse expose cross-tenant data).
            if (str_starts_with((string) $ability, 'super_admin.')
                || in_array($ability, ['viewTelescope', 'viewPulse'], true)) {
                return false;
            }

            return $user->hasRole('admin') ? true : null;
        });

        // Restrict Laravel Pulse dashboard strictly to Super Admin users
        Gate::define('viewPulse', function ($user) {
            return PlatformSuperAdmin::check($user);
        });

        // Register Model Observers
        Tenant::observe(TenantObserver::class);

        // Testing-only quick login (AUTH-1a): per tenant + IP.
        RateLimiter::for('quick-login', function (Request $request) {
            $tenantKey = function_exists('tenant') && tenant() ? (string) tenant()->getTenantKey() : 'central';

            return Limit::perMinute(max(1, (int) config('auth.quick_login.per_minute', 5)))
                ->by('quick-login|'.$tenantKey.'|'.$request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'message' => __('auth.too_many_requests'),
                ], 429, $headers));
        });

        // Public, unauthenticated configuration endpoints (e.g. /auth/options).
        RateLimiter::for('public-config', function (Request $request) {
            return Limit::perMinute(60)
                ->by('public-config|'.$request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'success' => false,
                    'message' => __('auth.too_many_requests'),
                ], 429, $headers));
        });

        $this->registerPublicRateLimiters();
    }

    /**
     * AUTH-2: named limiters for unauthenticated endpoints.
     *
     * Keys use $request->ip(): behind a reverse proxy TrustProxies must be configured,
     * otherwise every client shares one bucket.
     */
    private function registerPublicRateLimiters(): void
    {
        $tooMany = fn (Request $request, array $headers) => response()->json([
            'success' => false,
            'message' => __('auth.too_many_requests'),
        ], 429, $headers);

        // Login: an IP-wide cap (credential spraying across many logins) plus a
        // per-identifier cap. The identifier cap stays above ApiLoginRequest's
        // 6-failure counter so that counter still answers first with its 422.
        RateLimiter::for('auth-login', function (Request $request) use ($tooMany) {
            $identifier = $request->input('login') ?? $request->input('phone') ?? $request->input('email') ?? '';
            $identifier = is_scalar($identifier) ? mb_strtolower(trim((string) $identifier)) : '';

            return [
                Limit::perMinute(20)->by('auth-login-ip|'.$request->ip())->response($tooMany),
                Limit::perMinute(10)->by('auth-login-id|'.$identifier.'|'.$request->ip())->response($tooMany),
            ];
        });

        // Public translation dictionary.
        RateLimiter::for('public-translations', fn (Request $request) => Limit::perMinute(60)
            ->by('public-translations|'.$request->ip())
            ->response($tooMany));

        // Central workspace resolver: low cap to stop workspace-code enumeration.
        RateLimiter::for('tenant-resolve', fn (Request $request) => Limit::perMinute(10)
            ->by('tenant-resolve|'.$request->ip())
            ->response($tooMany));
    }
}
