<?php

namespace App\Providers;

use App\Contracts\SuperAdminDashboardAnalyticsInterface;
use App\Contracts\TenantFeatureManagerInterface;
use App\Contracts\TenantProvisionerInterface;
use App\Enums\CentralPermission;
use App\Models\Addon;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Observers\Billing\EntitlementsCacheObserver;
use App\Observers\TenantObserver;
use App\Services\Branding\PlatformBranding;
use App\Services\Entitlements\EntitlementFeatures;
use App\Services\Entitlements\TenantEntitlementService;
use App\Services\SuperAdminAnalyticsService;
use App\Services\TenantProvisionerService;
use App\Support\PlatformSuperAdmin;
use App\Support\QuickLogin;
use App\Support\RateLimitKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Stancl\Tenancy\Events\TenancyEnded;
use Stancl\Tenancy\Events\TenancyInitialized;

class AppServiceProvider extends ServiceProvider
{
    /**
     * CTO decision W1 Q1 (2026-10-09): budget of the tenant-resolve limiter and of the
     * tenant-miss bucket (ThrottleTenantMisses), per client IP and minute. Fallback when
     * config/rate_limits.php does not provide a value.
     */
    public const TENANT_RESOLVE_PER_MINUTE = 30;

    /** Platform monitoring dashboards (cross-tenant data): never granted to a store admin. */
    private const MONITORING_ABILITIES = ['viewTelescope', 'viewPulse', 'viewHorizon'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            TenantProvisionerInterface::class,
            TenantProvisionerService::class
        );

        // ENTI-2.2: the entitlement engine is the single source of truth for features/limits.
        $this->app->bind(
            TenantFeatureManagerInterface::class,
            TenantEntitlementService::class
        );

        $this->app->bind(
            SuperAdminDashboardAnalyticsInterface::class,
            SuperAdminAnalyticsService::class
        );

        $this->app->singleton(PlatformBranding::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // IDEN-4.1 / CTO Q-B11: quick login switched on in production = refuse to serve HTTP.
        QuickLogin::guardAgainstProduction($this->app);

        // Implicitly grant Super Admin all permissions, and Store Admin all standard ERP permissions
        Gate::before(function ($user, $ability) {
            // IDEN-1.2: a platform operator never holds a tenant ability. Only its own granular
            // CentralPermission abilities (and the monitoring gates defined below) fall through
            // to the regular checks; everything else is denied outright.
            if ($user instanceof CentralUser) {
                return self::isOperatorAbility((string) $ability) ? null : false;
            }

            // @deprecated removed in IDEN-1.4 (W2-B3): legacy App\Models\User super admin.
            if (PlatformSuperAdmin::check($user)) {
                return true;
            }
            // Platform-only abilities: a store `admin` must never pass these through the
            // blanket grant below (Telescope/Pulse/Horizon expose cross-tenant data).
            if (str_starts_with((string) $ability, 'super_admin.')
                || in_array($ability, self::MONITORING_ABILITIES, true)) {
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

        // ENTI-2.2: any change to the central rows entitlements derive from bumps the
        // affected tenants' entitlement cache (explicit TenantCache::bumpFor scope).
        foreach ([Tenant::class, Plan::class, Subscription::class, SubscriptionAddon::class, Addon::class] as $model) {
            $model::observe(EntitlementsCacheObserver::class);
        }

        // ENTI-2.2 / Q-E1: Pennant features resolve through the entitlement service (array store).
        $this->app->make(EntitlementFeatures::class)->register();

        $this->registerRateLimiters();

        $this->applyPlatformBranding();
    }

    /**
     * Abilities a CentralUser may be evaluated on: the granular CentralPermission values and
     * the monitoring dashboards' gates. Every other ability is a tenant ability.
     */
    private static function isOperatorAbility(string $ability): bool
    {
        return CentralPermission::tryFrom($ability) !== null
            || in_array($ability, self::MONITORING_ABILITIES, true);
    }

    /**
     * BRND-1: Laravel itself (mail from-name, notifications, Pulse/Telescope) reads
     * config('app.name') and config('mail.from.name'); both follow PlatformBranding.
     * Re-applied whenever tenancy is initialized or ended, so a long-lived worker (queue,
     * Octane) serves a super-admin rename on its next tenant job/request. PlatformBranding
     * reads a central-scoped cache and never throws (config fallback without a DB).
     */
    private function applyPlatformBranding(): void
    {
        $apply = fn () => $this->app->make(PlatformBranding::class)->applyToConfig();

        $apply();

        Event::listen(TenancyInitialized::class, $apply);
        Event::listen(TenancyEnded::class, $apply);
    }

    /**
     * IDEN-4.6: every named rate limiter, in one place. Numbers live in config/rate_limits.php
     * (quick-login keeps its IDEN-4.1 setting in config/auth.php).
     *
     * No limiter builds its own 429: ThrottleRequestsException is rendered once in
     * bootstrap/app.php as the localized JSON envelope with Retry-After.
     *
     * Keys use $request->ip(): behind a reverse proxy TrustProxies must be configured,
     * otherwise every client shares one bucket. The cache store is shared by all tenants,
     * so tenant-scoped keys embed RateLimitKey::scope().
     */
    private function registerRateLimiters(): void
    {
        // Tenant login: one ceiling per client IP across every login and tenant (credential
        // spraying), plus a cap per tenant + login + IP. The per-login cap stays above
        // ApiLoginRequest's failure counter so that counter still answers first with its 422.
        RateLimiter::for('tenant-login', function (Request $request): array {
            $identifier = RateLimitKey::identifier($request->input('login') ?? $request->input('phone') ?? $request->input('email'));

            return [
                Limit::perMinute($this->limit('rate_limits.tenant_login.per_ip_per_minute', 30))
                    ->by('tenant-login-ip|'.$request->ip()),
                Limit::perMinute($this->limit('rate_limits.tenant_login.per_login_per_minute', 10))
                    ->by('tenant-login-id|'.RateLimitKey::scope().'|'.$identifier.'|'.$request->ip()),
            ];
        });

        // Central (platform operator) login, consumed by IDEN-1.3. The hourly per-email cap
        // ignores the IP so a distributed attack on one operator account is still stopped.
        RateLimiter::for('central-login', function (Request $request): array {
            $email = RateLimitKey::identifier($request->input('email'));

            return [
                Limit::perMinute($this->limit('rate_limits.central_login.per_ip_per_minute', 10))
                    ->by('central-login-ip|'.$request->ip()),
                Limit::perMinute($this->limit('rate_limits.central_login.per_email_per_minute', 5))
                    ->by('central-login-id|'.$email.'|'.$request->ip()),
                Limit::perHour($this->limit('rate_limits.central_login.per_email_per_hour', 20))
                    ->by('central-login-email|'.$email),
            ];
        });

        // Every public, unauthenticated endpoint (ping, app version/update/APK, translations, auth options).
        // One bucket per endpoint and IP, so the connectivity heartbeat (/ping) of several devices
        // behind one shop NAT can never starve the translations or update check of the same shop.
        RateLimiter::for('public-api', function (Request $request): Limit {
            $route = $request->route();
            $endpoint = $route instanceof Route ? ($route->getName() ?? $route->uri()) : $request->path();

            return Limit::perMinute($this->limit('rate_limits.public_api.per_minute', 60))
                ->by('public-api|'.$endpoint.'|'.$request->ip());
        });

        // Central workspace resolver: caps workspace-code enumeration (CTO W1 Q1: 30/min per IP).
        RateLimiter::for('tenant-resolve', fn (Request $request): Limit => Limit::perMinute($this->limit('rate_limits.tenant_resolve.per_minute', self::TENANT_RESOLVE_PER_MINUTE))
            ->by('tenant-resolve|'.$request->ip()));

        // Testing-only quick login (AUTH-1a / IDEN-4.1): per tenant + IP.
        RateLimiter::for('quick-login', fn (Request $request): Limit => Limit::perMinute($this->limit('auth.quick_login.per_minute', 5))
            ->by('quick-login|'.RateLimitKey::scope().'|'.$request->ip()));
    }

    /** A positive per-period limit from config (a misconfigured 0 or negative never disables a limiter). */
    private function limit(string $key, int $default): int
    {
        $value = config($key, $default);

        return max(1, is_numeric($value) ? (int) $value : $default);
    }
}
