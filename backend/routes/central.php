<?php

declare(strict_types=1);

use App\Enums\CentralPermission;
use App\Http\Controllers\Api\Central\CentralAuthController;
use App\Http\Controllers\Api\Central\CentralPasswordResetController;
use App\Http\Controllers\Api\Central\CentralTwoFactorController;
use App\Http\Controllers\Api\Central\TenantRateLimitController;
use App\Http\Controllers\Api\SuperAdminApiController;
use App\Http\Controllers\Api\V1\SuperAdmin\SuperAdminAppVersionController;
use App\Http\Controllers\Api\V1\SuperAdmin\TelescopeLinkController;
use App\Http\Middleware\AuthenticateCentral;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\RequireRecentTwoFactor;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Central (control-plane) API routes
|--------------------------------------------------------------------------
|
| Loaded from bootstrap/app.php under the `api` group with the `/api` prefix, WITHOUT
| ResolveApiTenancy / ApiTokenAuth: these routes never initialise tenancy and never
| accept a tenant token. Order: EnsureCentralContext (tenant host => 404) → throttle
| (public endpoints) → AuthenticateCentral (Bearer central token) → throttle (2FA).
|
| Never use `auth:central`: the sanctum driver resolves the global token model and
| would never authenticate a CentralUser.
|
| IDEN-1.4: EVERY /api/v1/super-admin/* route lives here (routes/api.php has none), with the
| Phase 0 paths and names kept (`api.super_admin.*`). Stack per route:
|   EnsureCentralContext → AuthenticateCentral → can:<CentralPermission> [→ RequireRecentTwoFactor]
| `super_admin` holds every CentralPermission; `support` only the read-only ones (GETs).
| The `can:` abilities resolve through Gate::before → PlatformSuperAdmin::can (central guard).
|
| IDEN-1.11: EnsureCentralContext answers only on config('central.admin_domains').
|
| IDEN-1.12 (mandatory 2FA):
|  - AuthenticateCentral            full `central:*` token only (a setup token => 403
|                                   central_auth.two_factor_setup_required);
|  - AuthenticateCentral:setup      also admits the `central:2fa-setup` token that login
|                                   issues to an operator without confirmed 2FA;
|  - RequireRecentTwoFactor         step-up (2FA proof on this token <= 15 min). Add it
|                                   AFTER AuthenticateCentral on sensitive routes
|                                   (impersonation, tenant archive/purge, DB config,
|                                   billing activation). Mounted on: tenant destroy,
|                                   update-db-config, run-migrations, rate-limit raise,
|                                   toggle-status, override-feature, tenant update-units,
|                                   plan update, platform settings update and app-version
|                                   store / toggle-active / destroy (security audit, W2 3I).
|                                   The `can:` check runs first: a read-only operator
|                                   still gets a plain 403, never a step-up prompt.
|
*/

Route::prefix('v1/super-admin/auth')
    ->name('central.auth.')
    ->middleware(EnsureCentralContext::class)
    ->group(function (): void {
        Route::post('/login', [CentralAuthController::class, 'login'])
            ->middleware('throttle:central-login')
            ->name('login');

        Route::post('/two-factor-challenge', [CentralAuthController::class, 'twoFactorChallenge'])
            ->middleware('throttle:central-two-factor')
            ->name('two-factor-challenge');

        Route::post('/forgot-password', [CentralPasswordResetController::class, 'forgot'])
            ->middleware('throttle:central-password-reset')
            ->name('forgot-password');

        Route::post('/reset-password', [CentralPasswordResetController::class, 'reset'])
            ->middleware('throttle:central-password-reset')
            ->name('reset-password');

        Route::middleware(AuthenticateCentral::class.':'.AuthenticateCentral::SCOPE_SETUP)
            ->prefix('two-factor')
            ->name('two-factor.')
            ->group(function (): void {
                Route::post('/enable', [CentralTwoFactorController::class, 'enable'])
                    ->middleware('throttle:central-two-factor')
                    ->name('enable');
                Route::post('/confirm', [CentralTwoFactorController::class, 'confirm'])
                    ->middleware('throttle:central-two-factor')
                    ->name('confirm');
                // Step-up for operators with confirmed 2FA is enforced in GetCentralRecoveryCodesAction
                // (setup-token users may still read them during enrollment); throttled and audited.
                Route::get('/recovery-codes', [CentralTwoFactorController::class, 'recoveryCodes'])
                    ->middleware('throttle:central-two-factor')
                    ->name('recovery-codes');
            });

        Route::middleware(AuthenticateCentral::class)->group(function (): void {
            Route::get('/me', [CentralAuthController::class, 'me'])->name('me');
            Route::post('/logout', [CentralAuthController::class, 'logout'])->name('logout');
            Route::post('/step-up', [CentralTwoFactorController::class, 'stepUp'])
                ->middleware('throttle:central-two-factor')
                ->name('step-up');
        });
    });

/*
| IDEN-1.4: the platform console API (formerly the Phase 0 group in routes/api.php).
*/
Route::prefix('v1/super-admin')
    ->name('api.super_admin.')
    ->middleware([EnsureCentralContext::class, AuthenticateCentral::class])
    ->group(function (): void {
        $can = static fn (CentralPermission $permission): string => 'can:'.$permission->value;

        Route::get('/dashboard', [SuperAdminApiController::class, 'dashboard'])
            ->middleware($can(CentralPermission::DashboardView))->name('dashboard');

        // Tenants
        Route::get('/tenants', [SuperAdminApiController::class, 'tenants'])
            ->middleware($can(CentralPermission::TenantsView))->name('tenants');
        Route::post('/tenants', [SuperAdminApiController::class, 'storeTenant'])
            ->middleware($can(CentralPermission::TenantsManage))->name('tenants.store');
        Route::get('/tenants/{id}', [SuperAdminApiController::class, 'showTenant'])
            ->middleware($can(CentralPermission::TenantsView))->name('tenants.show');
        Route::delete('/tenants/{id}', [SuperAdminApiController::class, 'destroyTenant'])
            ->middleware([$can(CentralPermission::TenantsManage), RequireRecentTwoFactor::class])->name('tenants.destroy');
        Route::post('/tenants/{id}/update-db-config', [SuperAdminApiController::class, 'updateDatabaseConfig'])
            ->middleware([$can(CentralPermission::TenantsManage), RequireRecentTwoFactor::class])->name('tenants.update_db_config');
        Route::post('/tenants/{id}/toggle-status', [SuperAdminApiController::class, 'toggleStatus'])
            ->middleware([$can(CentralPermission::TenantsManage), RequireRecentTwoFactor::class])->name('tenants.toggle_status');
        Route::post('/tenants/{id}/override-feature', [SuperAdminApiController::class, 'overrideFeature'])
            ->middleware([$can(CentralPermission::TenantsManage), RequireRecentTwoFactor::class])->name('tenants.override_feature');
        Route::post('/tenants/{id}/update-units', [SuperAdminApiController::class, 'updateTenantUnits'])
            ->middleware([$can(CentralPermission::TenantsManage), RequireRecentTwoFactor::class])->name('tenants.update_units');
        Route::post('/tenants/{id}/run-migrations', [SuperAdminApiController::class, 'runTenantMigrations'])
            ->middleware([$can(CentralPermission::TenantsManage), RequireRecentTwoFactor::class])->name('tenants.run_migrations');

        // IDEN-4.6 ext: temporary per-tenant rate-limit raise (expiring, audited).
        Route::get('/tenants/{id}/rate-limits', [TenantRateLimitController::class, 'show'])
            ->middleware($can(CentralPermission::TenantsView))->name('tenants.rate_limits.show');
        Route::post('/tenants/{id}/rate-limits', [TenantRateLimitController::class, 'store'])
            ->middleware([$can(CentralPermission::TenantsManage), RequireRecentTwoFactor::class])->name('tenants.rate_limits.store');

        // Plans
        Route::get('/plans', [SuperAdminApiController::class, 'plans'])
            ->middleware($can(CentralPermission::PlansView))->name('plans');
        Route::put('/plans/{id}', [SuperAdminApiController::class, 'updatePlan'])
            ->middleware([$can(CentralPermission::PlansManage), RequireRecentTwoFactor::class])->name('plans.update');

        // Telescope: short-lived single-use signed link (IDEN-1.7 moves the web bridge to central_web).
        Route::post('/telescope-link', TelescopeLinkController::class)
            ->middleware([$can(CentralPermission::MonitoringView), 'throttle:10,1'])->name('telescope_link');

        // Platform settings & units
        Route::get('/settings', [SuperAdminApiController::class, 'getPlatformSettings'])
            ->middleware($can(CentralPermission::SettingsView))->name('settings.get');
        Route::post('/settings', [SuperAdminApiController::class, 'updatePlatformSettings'])
            ->middleware([$can(CentralPermission::SettingsManage), RequireRecentTwoFactor::class])->name('settings.update');
        Route::get('/units', [SuperAdminApiController::class, 'getUnits'])
            ->middleware($can(CentralPermission::SettingsView))->name('units.get');
        Route::post('/units', [SuperAdminApiController::class, 'updateUnits'])
            ->middleware($can(CentralPermission::SettingsManage))->name('units.update');

        // App versions & APK releases
        Route::get('/app-versions', [SuperAdminAppVersionController::class, 'index'])
            ->middleware($can(CentralPermission::AppVersionsView))->name('app_versions.index');
        Route::post('/app-versions', [SuperAdminAppVersionController::class, 'store'])
            ->middleware([$can(CentralPermission::AppVersionsManage), RequireRecentTwoFactor::class])->name('app_versions.store');
        Route::patch('/app-versions/{appVersion}/toggle-active', [SuperAdminAppVersionController::class, 'toggleActive'])
            ->middleware([$can(CentralPermission::AppVersionsManage), RequireRecentTwoFactor::class])->name('app_versions.toggle_active');
        Route::delete('/app-versions/{appVersion}', [SuperAdminAppVersionController::class, 'destroy'])
            ->middleware([$can(CentralPermission::AppVersionsManage), RequireRecentTwoFactor::class])->name('app_versions.destroy');
    });
