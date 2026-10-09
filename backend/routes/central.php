<?php

declare(strict_types=1);

use App\Http\Controllers\Api\Central\CentralAuthController;
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
| (login only) → AuthenticateCentral (Bearer central token, ability central:*).
|
| Never use `auth:central`: the sanctum driver resolves the global token model and
| would never authenticate a CentralUser.
|
| IDEN-1.3 adds /auth/* only. The legacy /api/v1/super-admin/* group in routes/api.php
| keeps serving the current SPA until IDEN-1.4 (W2-B3) moves it here.
|
*/

Route::prefix('v1/super-admin/auth')
    ->name('central.auth.')
    ->middleware(EnsureCentralContext::class)
    ->group(function (): void {
        Route::post('/login', [CentralAuthController::class, 'login'])
            ->middleware('throttle:central-login')
            ->name('login');

        Route::middleware(AuthenticateCentral::class)->group(function (): void {
            Route::get('/me', [CentralAuthController::class, 'me'])->name('me');
            Route::post('/logout', [CentralAuthController::class, 'logout'])->name('logout');
        });
    });
