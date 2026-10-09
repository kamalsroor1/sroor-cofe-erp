<?php

use App\Models\CentralUser;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        // IDEN-1.1: platform operators (App\Models\CentralUser, `central_users`).
        // `central` = Bearer API (tokens in `central_personal_access_tokens`, resolved by
        // AuthenticateCentral, IDEN-1.3); `central_web` = session for Pulse/Telescope
        // (IDEN-1.7). There is no `super_admin` guard any more.
        'central' => [
            'driver' => 'sanctum',
            'provider' => 'central_users',
        ],
        'central_web' => [
            'driver' => 'session',
            'provider' => 'central_users',
        ],
        'tenant' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],
        'central_users' => [
            'driver' => 'eloquent',
            'model' => CentralUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
        // IDEN-1.12: platform operators (CentralUser). Tokens live in the CENTRAL
        // `central_password_reset_tokens`; CentralFortifyServiceProvider pins `connection`
        // to tenancy.database.central_connection at boot (this file loads before tenancy.php).
        // Reset links are built from central.password_reset_url, never from the Host header.
        'central_users' => [
            'provider' => 'central_users',
            'table' => 'central_password_reset_tokens',
            'expire' => 30,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Quick Login (testing only)
    |--------------------------------------------------------------------------
    |
    | Passwordless "pick a user" login for local/testing tenants only. It is off
    | by default and honoured only when APP_ENV is local or testing (see
    | App\Support\QuickLogin). With the flag on, APP_ENV=production refuses to
    | serve HTTP at boot (fail-fast). Issued tokens carry only the `quick-login`
    | ability and expire after `token_ttl_minutes` (default 8 hours).
    |
    */

    'quick_login' => [
        'enabled' => filter_var(env('QUICK_LOGIN_ENABLED', false), FILTER_VALIDATE_BOOL),
        'token_ttl_minutes' => (int) (env('QUICK_LOGIN_TOKEN_TTL') ?: 480),
        'per_minute' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | API token lifetimes
    |--------------------------------------------------------------------------
    |
    | Every personal access token is issued with its own `expires_at`; that is
    | the single source of truth (config/sanctum.php keeps `expiration` null).
    | Tenant tokens: 30 days, renewed on use (CTO Q-B10, enforced by IDEN-2.2).
    | Central (operator) token TTL lives in config/central.php (IDEN-1.1).
    |
    */

    'tokens' => [
        'tenant_ttl_minutes' => (int) (env('TENANT_TOKEN_TTL_MINUTES') ?: 43200),
    ],

];
