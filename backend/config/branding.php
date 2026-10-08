<?php

/*
|--------------------------------------------------------------------------
| Platform branding — FALLBACK values (BRND-1)
|--------------------------------------------------------------------------
|
| The platform (SaaS operator) brand: name, logos, support contacts. The
| super-admin can override every value at runtime; those overrides live in the
| CENTRAL `platform_settings` table. Read the effective values ONLY through
| App\Services\Branding\PlatformBranding — never config('branding.*'),
| config('app.name') or env('APP_NAME') directly.
|
| These defaults are deliberately neutral (CTO Q-R1: the commercial name is not
| decided yet). Set them per environment through .env. They are also the source
| for native builds (BRND-8), whose installed name cannot change from the DB.
|
| Shop (tenant) branding is separate: see TenantBranding (BRND-5).
|
*/

return [

    'name' => env('BRANDING_NAME', 'Retail ERP'),

    'short_name' => env('BRANDING_SHORT_NAME', 'Retail ERP'),

    'subtitle' => env('BRANDING_SUBTITLE', ''),

    'legal_name' => env('BRANDING_LEGAL_NAME', ''),

    /* Hex #rrggbb used by platform screens (login before choosing a shop, super-admin). */
    'primary_color' => env('BRANDING_PRIMARY_COLOR', '#059669'),

    'support_email' => env('BRANDING_SUPPORT_EMAIL', ''),

    'support_phone' => env('BRANDING_SUPPORT_PHONE', ''),

    'website_url' => env('BRANDING_WEBSITE_URL', ''),

    /*
    | "Powered by <platform>" line on shop receipts (Q-R4).
    | TODO(CTO): Q-R4 suggests "optional, on by default in free/basic"; per-plan
    | defaults belong to entitlements (BRND-12). This is only the platform-wide switch.
    */
    'powered_by_enabled' => filter_var(env('BRANDING_POWERED_BY_ENABLED', true), FILTER_VALIDATE_BOOL),

    /*
    | Default platform assets: public URL paths of the shared files in public/.
    | A super-admin upload (BRND-2) stores a path on the central public disk instead.
    */
    'assets' => [
        'logo_light' => env('BRANDING_LOGO_LIGHT', '/logo-light.png'),
        'logo_dark' => env('BRANDING_LOGO_DARK', '/logo-dark.png'),
        'favicon' => env('BRANDING_FAVICON', '/favicon.ico'),
        'app_icon' => env('BRANDING_APP_ICON', '/logo.png'),
    ],

    /*
    | Safety TTL of the cached overrides. Correctness does not depend on it: every
    | save bumps the central cache version, so a rename is visible on the next request.
    */
    'cache_ttl_seconds' => (int) env('BRANDING_CACHE_TTL_SECONDS', 3600),

];
