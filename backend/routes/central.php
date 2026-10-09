<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Central\CentralAuthController;
use App\Http\Controllers\Api\Central\CentralPasswordResetController;
use App\Http\Controllers\Api\Central\CentralTwoFactorController;
use App\Http\Middleware\AuthenticateCentral;
use App\Http\Middleware\EnsureCentralContext;
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
| IDEN-1.3 adds /auth/* only. The legacy /api/v1/super-admin/* group in routes/api.php
| keeps serving the current SPA until IDEN-1.4 (W2-B3) moves it here.
|
| IDEN-1.12 (mandatory 2FA):
|  - AuthenticateCentral            full `central:*` token only (a setup token => 403
|                                   central_auth.two_factor_setup_required);
|  - AuthenticateCentral:setup      also admits the `central:2fa-setup` token that login
|                                   issues to an operator without confirmed 2FA;
|  - RequireRecentTwoFactor         step-up (2FA proof on this token <= 15 min). Add it
|                                   AFTER AuthenticateCentral on sensitive routes
|                                   (impersonation, tenant archive/purge, DB config,
|                                   billing activation). No route uses it yet.
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
