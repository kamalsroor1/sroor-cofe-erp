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

];
