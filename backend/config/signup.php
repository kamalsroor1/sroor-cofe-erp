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
        // SIGN-13: platform brands, operator areas and mail/ops names.
        'super', 'superadmin', 'super-admin', 'ibraa', 'baraa', 'email', 'beta', 'sandbox', 'preview',
        'files', 'media', 'img', 'images', 'download', 'downloads', 'update', 'updates', 'health',
        'account', 'accounts', 'tenant', 'tenants', 'webhook', 'webhooks', 'noreply', 'no-reply',
        'postmaster', 'hostmaster', 'abuse', 'security', 'legal', 'privacy', 'terms', 'info', 'contact',
        'sales', 'marketing', 'mobile', 'android', 'ios', 'desktop', 'internal', 'ops', 'backup', 'backups',
    ],

    /*
    | SIGN-13: disposable / throw-away email domains refused by the public self-signup (the domain
    | and any subdomain of it, case-insensitive). Starter list of well-known providers; extend it
    | here (or override the whole list in a published config) as abuse shows up.
    */
    'blocked_email_domains' => [
        'mailinator.com', 'guerrillamail.com', 'guerrillamail.net', 'guerrillamail.org', 'sharklasers.com',
        'grr.la', '10minutemail.com', '10minutemail.net', 'tempmail.com', 'temp-mail.org', 'temp-mail.io',
        'tempmailo.com', 'tempail.com', 'throwawaymail.com', 'yopmail.com', 'yopmail.net', 'yopmail.fr',
        'getnada.com', 'nada.email', 'maildrop.cc', 'mailnesia.com', 'mintemail.com', 'trashmail.com',
        'trashmail.net', 'fakeinbox.com', 'dispostable.com', 'mohmal.com', 'emailondeck.com',
        'mailcatch.com', 'spamgourmet.com', 'mytemp.email', 'tempr.email', 'discard.email',
        'moakt.com', 'burnermail.io', 'inboxkitten.com', 'mail.tm', 'emailfake.com', 'fakemail.net',
        'getairmail.com',
    ],

    /*
    | Backlog protection: once this many requests are pending, new public submissions are
    | refused with a translated 503 (the console is not reviewing them fast enough).
    */
    'max_pending' => (int) (env('SIGNUP_MAX_PENDING') ?: 500),

    /*
    | PLAN-10 defaults of the platform settings `self_signup_enabled` and `signup_daily_cap`
    | (the super-admin console overrides them in `platform_settings`).
    */
    'self_signup_enabled' => (bool) env('SIGNUP_SELF_SIGNUP_ENABLED', true),
    'daily_cap' => (int) (env('SIGNUP_DAILY_CAP') ?: 100),

    /*
    | SIGN-11: self-signup email verification. A 6-digit code, stored as a keyed hash, valid
    | `code_ttl_minutes`; `max_attempts` wrong tries burn it; a new one may be requested after
    | `resend_after_seconds`, at most `codes_per_email_per_hour` codes per email address.
    */
    'verification' => [
        'code_ttl_minutes' => (int) (env('SIGNUP_CODE_TTL_MINUTES') ?: 15),
        'max_attempts' => (int) (env('SIGNUP_CODE_MAX_ATTEMPTS') ?: 5),
        'resend_after_seconds' => (int) (env('SIGNUP_CODE_RESEND_SECONDS') ?: 60),
        // Security review: code budgets keyed on the CANONICAL email (gmail dots / +tags fold
        // into one mailbox). Per email + client network (the normal case), plus looser per-email
        // ceilings so rotating networks cannot bomb one inbox.
        'codes_per_email_network_per_hour' => (int) (env('SIGNUP_CODES_PER_EMAIL_NETWORK_HOURLY') ?: 5),
        'codes_per_email_per_hour' => (int) (env('SIGNUP_CODES_PER_EMAIL_HOURLY') ?: 10),
        'codes_per_email_per_day' => (int) (env('SIGNUP_CODES_PER_EMAIL_DAILY') ?: 15),
    ],

    /*
    | Security review: platform-wide ceiling of verification-code emails per Cairo day (protects
    | the sender reputation). When reached, start / resend answer 503 with Retry-After until
    | Cairo midnight and the platform is alerted on Telegram once that day.
    */
    'max_codes_per_day' => (int) (env('SIGNUP_MAX_CODES_PER_DAY') ?: 1000),

    /*
    | Security review: at most `per_day` AUTOMATIC provisionings per client network per Cairo day
    | (IPv4 /ipv4_prefix, IPv6 /ipv6_prefix). Over it, a verified signup waits in the manual
    | `pending` queue (like the global daily cap), it is never refused.
    */
    'network_cap' => [
        'per_day' => (int) (env('SIGNUP_NETWORK_CAP_PER_DAY') ?: 3),
        'ipv4_prefix' => (int) (env('SIGNUP_NETWORK_IPV4_PREFIX') ?: 24),
        'ipv6_prefix' => (int) (env('SIGNUP_NETWORK_IPV6_PREFIX') ?: 48),
    ],

    /*
    | Security review: the browser token of a self-signup stops working this many minutes after
    | the row reached a final state (provisioned / approved / rejected / failed); secrets and the
    | client IP of final rows are scrubbed by `signup:prune-unverified` after `scrub_after_days`.
    */
    'token_ttl_after_final_minutes' => (int) (env('SIGNUP_TOKEN_TTL_AFTER_FINAL_MINUTES') ?: 60),
    'scrub_after_days' => (int) (env('SIGNUP_SCRUB_AFTER_DAYS') ?: 30),

    /*
    | From this many pending requests on, the per-request Telegram message is replaced by at
    | most one backlog summary per hour.
    */
    'quiet_above_pending' => (int) (env('SIGNUP_QUIET_ABOVE_PENDING') ?: 50),

    /*
    | SIGN-12: from this many self-signups verified within the last hour on, the per-signup
    | Telegram message is replaced by at most one summary per hour.
    */
    'self_signup_quiet_above_per_hour' => (int) (env('SIGNUP_SELF_QUIET_ABOVE_HOURLY') ?: 20),

    /*
    | Telegram notification to the platform owner on a new request. Uses the platform bot
    | (services.telegram.bot_token); the chat falls back to services.telegram.chat_id.
    | Empty token/chat = no notification (never an error).
    */
    'notify' => [
        'telegram_chat_id' => env('SIGNUP_NOTIFY_TELEGRAM_CHAT_ID'),
    ],
];
