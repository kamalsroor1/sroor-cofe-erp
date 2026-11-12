<?php

use App\Support\Http\CorsOrigins;

/*
|--------------------------------------------------------------------------
| Cross-Origin Resource Sharing (CORS)
|--------------------------------------------------------------------------
|
| SEC-21: the allowed origins come from the environment only, never a list in code.
|
|  CORS_ALLOWED_ORIGINS          comma-separated exact origins, e.g. "https://app.example.com"
|  CORS_ALLOWED_ORIGIN_PATTERNS  comma-separated host wildcards, e.g. "https://*.example.com"
|                                ("*" = exactly one DNS label; see App\Support\Http\CorsOrigins)
|
| When BOTH are empty: production allows no cross-origin caller at all (the SPA, the Android and
| the desktop shells all load the API from the same origin, so they never need CORS); every other
| environment keeps "*" so local/dev origins (Vite dev server, emulators) keep working.
|
| Authentication is a Bearer token, never a cookie: credentials stay off.
|
*/

$origins = env('CORS_ALLOWED_ORIGINS');
$patterns = env('CORS_ALLOWED_ORIGIN_PATTERNS');
$nothingConfigured = trim((string) $origins) === '' && trim((string) $patterns) === '';

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => CorsOrigins::origins(
        $origins,
        $nothingConfigured && env('APP_ENV', 'production') !== 'production' ? ['*'] : [],
    ),

    'allowed_origins_patterns' => CorsOrigins::patterns($patterns),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
