<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| laravel/fortify, headless, CENTRAL operators only (IDEN-1.12)
|--------------------------------------------------------------------------
|
| Fortify is excluded from package auto-discovery (composer.json → extra.laravel
| .dont-discover) and its own FortifyServiceProvider is NOT registered: no Fortify
| route, view, session login or passkey is ever exposed. App\Providers\
| CentralFortifyServiceProvider binds only the TOTP provider; the 2FA, recovery-code
| and password-reset flows run through App\Actions\Central\* behind routes/central.php
| (Bearer tokens in central_personal_access_tokens, never a session).
|
| Tenant users never go through Fortify.
|
*/

return [

    // The operator guard. Only consulted by Fortify helpers that resolve a guard by name.
    'guard' => 'central',

    // Password broker of App\Models\CentralUser (config/auth.php → passwords.central_users).
    'passwords' => 'central_users',

    'username' => 'email',

    'email' => 'email',

    // Operator emails are stored and compared lowercase.
    'lowercase_usernames' => true,

    // Headless: Fortify renders nothing.
    'views' => false,

    'home' => '/',

    'prefix' => '',

    'domain' => null,

    'middleware' => [],

    'limiters' => [
        'login' => null,
        'passkeys' => null,
    ],

    'features' => [
        Features::resetPasswords(),
        Features::twoFactorAuthentication([
            // A secret only counts once a TOTP code proved the authenticator app works.
            'confirm' => true,
            // Token API: password re-confirmation is replaced by the step-up endpoint.
            'confirmPassword' => false,
            // Accept the current 30-second code and one step either side (clock drift).
            'window' => 1,
        ]),
    ],

];
