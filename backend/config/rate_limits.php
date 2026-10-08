<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Rate limits (IDEN-4.6)
|--------------------------------------------------------------------------
|
| Every named limiter is registered in AppServiceProvider::registerRateLimiters()
| and reads its numbers from here. Limiter keys use $request->ip(): behind a
| reverse proxy TrustProxies must be configured, otherwise every client shares
| one bucket. The cache store is shared by all tenants (CacheTenancyBootstrapper
| is off), so tenant-sensitive keys embed the tenant id explicitly.
|
*/

return [

    // POST /api/v1/auth/login (tenant users).
    'tenant_login' => [
        // Ceiling for one client IP across every login and every tenant. A shop with
        // many cashiers behind one NAT address can raise it.
        'per_ip_per_minute' => (int) env('RATE_LIMIT_TENANT_LOGIN_PER_IP', 30),

        // One login string, in one tenant, from one IP.
        'per_login_per_minute' => (int) env('RATE_LIMIT_TENANT_LOGIN_PER_LOGIN', 10),

        // ApiLoginRequest failure counter (answers 422 auth.throttle). Keep it below
        // per_login_per_minute so the counter answers before the limiter's 429.
        'max_failed_attempts' => (int) env('RATE_LIMIT_TENANT_LOGIN_MAX_FAILURES', 6),
    ],

    // Central (platform operator) login, used by IDEN-1.3.
    'central_login' => [
        'per_ip_per_minute' => (int) env('RATE_LIMIT_CENTRAL_LOGIN_PER_IP', 10),
        'per_email_per_minute' => (int) env('RATE_LIMIT_CENTRAL_LOGIN_PER_EMAIL', 5),
        // Ignores the IP: stops distributed guessing against one operator account.
        'per_email_per_hour' => (int) env('RATE_LIMIT_CENTRAL_LOGIN_PER_EMAIL_HOURLY', 20),
    ],

    // Public, unauthenticated endpoints (ping, app version/update/APK, translations, auth options),
    // counted per endpoint and client IP.
    'public_api' => [
        'per_minute' => (int) env('RATE_LIMIT_PUBLIC_API', 60),
    ],

    // Central workspace resolver: low cap to stop workspace-code enumeration.
    'tenant_resolve' => [
        'per_minute' => (int) env('RATE_LIMIT_TENANT_RESOLVE', 10),
    ],

];
