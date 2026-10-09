<?php

/*
|--------------------------------------------------------------------------
| Central (control-plane) identity
|--------------------------------------------------------------------------
|
| Settings for platform operators (App\Models\CentralUser, guard `central`).
| Operators never use tenant tokens, tenant guards or tenant tables.
|
| Later IDEN tasks append here (admin hosts IDEN-1.11, alerts IDEN-1.13).
|
*/

return [

    /*
    | Lifetime of a central Bearer token in minutes (CTO Q-B4: 4-hour session,
    | the SPA adds idle logout). Every central token is issued with this
    | `expires_at`; there is no "never expires" central token.
    */
    'token_ttl_minutes' => (int) (env('CENTRAL_TOKEN_TTL_MINUTES') ?: 240),

    /*
    | Ability stamped on every central token by default. AuthenticateCentral
    | (IDEN-1.3) rejects any token without it.
    */
    'token_ability' => 'central:*',

    /*
    | IDEN-1.12: mandatory TOTP two-factor for every operator.
    |
    | - An operator WITHOUT confirmed 2FA only ever receives a setup-scoped token
    |   (`two_factor_setup_ability`, short TTL) accepted on the 2FA enable / confirm /
    |   recovery-codes endpoints only. Confirming 2FA issues the full `central:*` token.
    | - An operator WITH confirmed 2FA gets a single-use challenge id from /auth/login
    |   (only its SHA-256 is kept in the central cache) and exchanges it, plus a TOTP or
    |   recovery code, at /auth/two-factor-challenge for the full token.
    | - Step-up: RequireRecentTwoFactor demands a 2FA proof on the current token no older
    |   than `step_up_ttl_minutes` (renewed by /auth/step-up).
    */
    'two_factor_setup_ability' => 'central:2fa-setup',

    'two_factor_setup_ttl_minutes' => (int) (env('CENTRAL_2FA_SETUP_TTL_MINUTES') ?: 15),

    'two_factor_challenge_ttl_seconds' => 300,

    'two_factor_challenge_max_attempts' => 5,

    'step_up_ttl_minutes' => (int) (env('CENTRAL_STEP_UP_TTL_MINUTES') ?: 15),

    /*
    | IDEN-1.12: operator password reset. The emailed link is built ONLY from this
    | configured URL of the platform console (never from the request Host header):
    | "<url>?token=...&email=...". Empty = no reset mail is sent (the endpoint still
    | answers uniformly and the attempt is logged).
    */
    'password_reset_url' => env('CENTRAL_PASSWORD_RESET_URL'),

    /*
    | Rate limits of the public 2FA-challenge / password-reset endpoints and of the
    | authenticated 2FA endpoints (limiters registered by CentralFortifyServiceProvider).
    */
    'rate_limits' => [
        'two_factor_per_ip_per_minute' => 10,
        'two_factor_per_subject_per_minute' => 5,
        'password_reset_per_ip_per_minute' => 5,
        'password_reset_per_email_per_hour' => 5,
    ],

];
