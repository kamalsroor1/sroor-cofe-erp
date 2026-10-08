<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\ResolveApiTenancy;
use App\Http\Middleware\SetRequestLocale;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * SETG-3: wires per-request locale resolution for the API.
 *
 * Kept out of bootstrap/app.php and routes/api.php on purpose (hot files owned by other
 * tracks in Wave 1, phase-1-plan §3.1). The HTTP kernel is configured by bootstrap/app.php
 * in an afterResolving callback; ours is registered later, so it runs after it and the
 * `api` group / priority list it builds are not overwritten.
 */
final class LocalizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(Authenticated::class, [SetRequestLocale::class, 'onAuthenticated']);

        if ($this->app->resolved(HttpKernelContract::class)) {
            $this->registerMiddleware($this->app->make(HttpKernelContract::class));

            return;
        }

        $this->app->afterResolving(HttpKernelContract::class, function (mixed $kernel): void {
            $this->registerMiddleware($kernel);
        });
    }

    private function registerMiddleware(mixed $kernel): void
    {
        if (! $kernel instanceof HttpKernel) {
            return;
        }

        // Run after the tenant is initialized (tenant default locale) and before auth;
        // the user preference is applied on the Authenticated event.
        $kernel->addToMiddlewarePriorityAfter(ResolveApiTenancy::class, SetRequestLocale::class);
        $kernel->appendMiddlewareToGroup('api', SetRequestLocale::class);
    }
}
