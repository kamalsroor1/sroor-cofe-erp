<?php

/*
|--------------------------------------------------------------------------
| Assisted signup (PRD-05)
|--------------------------------------------------------------------------
*/

return [

    /*
    | Subdomains nobody may request: platform hosts and obviously confusing names.
    | Matched case-insensitively against the requested workspace code.
    */
    'reserved_subdomains' => [
        'www', 'admin', 'api', 'app', 'apps', 'mail', 'smtp', 'ftp', 'cdn', 'static', 'assets',
        'support', 'help', 'status', 'billing', 'portal', 'dashboard', 'console', 'login',
        'register', 'signup', 'pricing', 'blog', 'docs', 'demo', 'test', 'staging', 'dev',
        'central', 'root', 'system', 'sroor', 'null', 'undefined',
        'webmail', 'ns1', 'ns2', 'imap', 'pop', 'autodiscover', 'cpanel', 'secure', 'pay', 'auth',
        'sso', 'oauth', 'telescope', 'pulse', 'horizon', 'monitor', 'm',
    ],

    /*
    | Backlog protection: once this many requests are pending, new public submissions are
    | refused with a translated 503 (the console is not reviewing them fast enough).
    */
    'max_pending' => (int) (env('SIGNUP_MAX_PENDING') ?: 500),

    /*
    | From this many pending requests on, the per-request Telegram message is replaced by at
    | most one backlog summary per hour.
    */
    'quiet_above_pending' => (int) (env('SIGNUP_QUIET_ABOVE_PENDING') ?: 50),

    /*
    | Current legal documents version recorded when an applicant accepts Terms, Privacy Policy
    | and DPA on signup (PRD-08).
    */
    'legal_version' => (string) (env('SIGNUP_LEGAL_VERSION') ?: '2026-11-draft-v1'),

    /*
    | Telegram notification to the platform owner on a new request. Uses the platform bot
    | (services.telegram.bot_token); the chat falls back to services.telegram.chat_id.
    | Empty token/chat = no notification (never an error).
    */
    'notify' => [
        'telegram_chat_id' => env('SIGNUP_NOTIFY_TELEGRAM_CHAT_ID'),
    ],
];
