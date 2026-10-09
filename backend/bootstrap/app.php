<?php

use App\Http\Middleware\AuthenticateCentral;
use App\Http\Middleware\EnsureCentralContext;
use App\Http\Middleware\ResolveApiTenancy;
use App\Http\Middleware\StoreAccess;
use App\Http\Middleware\StoreScope;
use App\Http\Middleware\ThrottleTenantMisses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // IDEN-1.3: central control-plane API (/api/v1/super-admin/auth/*). Only the `api`
        // group: no ResolveApiTenancy, no ApiTokenAuth (see routes/central.php).
        then: function (): void {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/central.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->web(append: [
            StoreScope::class,
        ]);

        // IDEN-4.6: the tenant must be resolved before any named limiter runs (tenant-login and
        // quick-login key by tenant) and before route-model binding. Without this entry the
        // framework priority sorts ThrottleRequests and SubstituteBindings ahead of the v1
        // group's ResolveApiTenancy. Tenancy → auth → throttle → bindings.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: ResolveApiTenancy::class,
        );

        // ResolveApiTenancy answers 404 for an unknown tenant before any named limiter, so the
        // per-IP miss counter must run ahead of it (closes the workspace-code enumeration oracle).
        $middleware->prependToPriorityList(
            before: ResolveApiTenancy::class,
            prepend: ThrottleTenantMisses::class,
        );

        // IDEN-1.3: on central routes the context check runs before everything else (tenant
        // host => 404, never 401/429), then operator authentication, then named limiters and
        // route-model binding. Neither ever shares a group with ResolveApiTenancy.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnsureCentralContext::class,
        );
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: AuthenticateCentral::class,
        );

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'store.scope' => StoreScope::class,
            'store.access' => StoreAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.unauthorized'),
                ], 401);
            }
        });

        $exceptions->render(function (UnauthorizedException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('auth.unauthorized').' - '.$e->getMessage(),
                ], 403);
            }
        });

        // IDEN-4.6: one localized 429 for every named limiter, keeping Retry-After and the
        // X-RateLimit-* headers set by ThrottleRequests.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $headers = $e->getHeaders();
                $retryAfter = $headers['Retry-After'] ?? null;

                return response()->json([
                    'success' => false,
                    'message' => __('auth.too_many_requests'),
                    'retry_after' => is_numeric($retryAfter) ? (int) $retryAfter : null,
                ], 429, $headers);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
