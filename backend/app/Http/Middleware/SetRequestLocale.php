<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\RequestLocaleResolver;
use Closure;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * SETG-3: answers every API request in the caller's language (users.locale → X-Locale
 * → tenant default → 'ar', see RequestLocaleResolver).
 *
 * Registered on the `api` group by App\Providers\LocalizationServiceProvider and ordered
 * right after ResolveApiTenancy in the middleware priority, so the tenant default is
 * readable. The user is authenticated later (ApiTokenAuth), so the user preference is
 * applied by onAuthenticated() when the guard fires Authenticated — but only for requests
 * that passed through this middleware (never for console, queue or web requests).
 */
final class SetRequestLocale
{
    /** Request attribute marking a request whose locale is managed here. */
    public const ATTRIBUTE = 'setg3.locale_managed';

    public function __construct(
        private readonly RequestLocaleResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set(self::ATTRIBUTE, true);

        $user = Auth::hasUser() ? Auth::user() : null;
        $this->resolver->apply($request, $user instanceof User ? $user : null);

        $response = $next($request);

        $response->headers->set('Content-Language', app()->getLocale());

        return $response;
    }

    /**
     * Listener for Illuminate\Auth\Events\Authenticated (fired by Auth::setUser in ApiTokenAuth).
     */
    public function onAuthenticated(Authenticated $event): void
    {
        if (! app()->bound('request')) {
            return;
        }

        $request = request();

        if ($request->attributes->get(self::ATTRIBUTE) !== true) {
            return;
        }

        if ($event->user instanceof User) {
            $this->resolver->apply($request, $event->user);
        }
    }
}
