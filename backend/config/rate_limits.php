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

        // SEC-05 per-account lockout: this many failures for one tenant + login (any IP)
        // locks that login for the given minutes, even for the correct password.
        'account_lockout_attempts' => (int) env('RATE_LIMIT_TENANT_LOGIN_LOCKOUT_ATTEMPTS', 5),
        'account_lockout_minutes' => (int) env('RATE_LIMIT_TENANT_LOGIN_LOCKOUT_MINUTES', 15),
    ],

    // SELL-01: POST /api/v1/auth/change-password (signed-in tenant users).
    'tenant_password_change' => [
        'per_ip_per_minute' => (int) env('RATE_LIMIT_TENANT_PASSWORD_CHANGE_PER_IP', 30),
        // One user, in one tenant, from one IP.
        'per_user_per_minute' => (int) env('RATE_LIMIT_TENANT_PASSWORD_CHANGE_PER_USER', 10),
    ],

    // SIGN-15: POST /api/v1/auth/forgot-password and /auth/reset-password (public, shop host).
    // Per client IP, plus an hourly cap per tenant + email that ignores the IP (mail bombing).
    'tenant_password_reset' => [
        'per_ip_per_minute' => (int) env('RATE_LIMIT_TENANT_PASSWORD_RESET_PER_IP', 5),
        'per_email_per_hour' => (int) env('RATE_LIMIT_TENANT_PASSWORD_RESET_PER_EMAIL_HOURLY', 5),
    ],

    // SET-02 / SEC-21 review: POST /api/v1/auth/unlock-screen. Its own named limiter (was the
    // anonymous throttle:10,1, shared with every throttle:N,1 route and, on a shared cache store,
    // with the same user id in other tenants). Wrong secrets also spend the password lockout or
    // the manager-PIN buckets, which answer before these caps are reached.
    'tenant_unlock_screen' => [
        'per_ip_per_minute' => (int) env('RATE_LIMIT_TENANT_UNLOCK_SCREEN_PER_IP', 30),
        // One user in one tenant, whatever the IP.
        'per_user_per_minute' => (int) env('RATE_LIMIT_TENANT_UNLOCK_SCREEN_PER_USER', 10),
    ],

    // PRD-17: POST /api/v1/settings/data-export (a full shop export is heavy) and its download.
    'tenant_data_export' => [
        'per_user_per_hour' => (int) env('RATE_LIMIT_TENANT_DATA_EXPORT_PER_USER_HOURLY', 5),
        'per_ip_per_minute' => (int) env('RATE_LIMIT_TENANT_DATA_EXPORT_PER_IP', 10),
        'downloads_per_user_per_minute' => (int) env('RATE_LIMIT_TENANT_DATA_EXPORT_DOWNLOADS_PER_USER', 10),
    ],

    // Central (platform operator) login, used by IDEN-1.3.
    'central_login' => [
        'per_ip_per_minute' => (int) env('RATE_LIMIT_CENTRAL_LOGIN_PER_IP', 10),
        'per_email_per_minute' => (int) env('RATE_LIMIT_CENTRAL_LOGIN_PER_EMAIL', 5),
        // Per email AND client IP (security audit, W2 lane 3I): one source cannot lock an
        // operator out everywhere.
        'per_email_ip_per_hour' => (int) env('RATE_LIMIT_CENTRAL_LOGIN_PER_EMAIL_IP_HOURLY', 20),
        // Ignores the IP, deliberately looser: slows distributed guessing against one account.
        'per_email_per_hour' => (int) env('RATE_LIMIT_CENTRAL_LOGIN_PER_EMAIL_HOURLY', 100),
    ],

    // Public, unauthenticated endpoints (ping, app version/update/APK, translations, auth options),
    // counted per endpoint and client IP.
    'public_api' => [
        'per_minute' => (int) env('RATE_LIMIT_PUBLIC_API', 60),
    ],

    // Release binary downloads (GET /api/v1/app/download-apk and /download-latest-apk), L2:
    // one bucket per client IP across both routes. Much tighter than public_api: every hit
    // streams a 20-150 MB file. A shop updating many devices behind one NAT can raise it.
    // An empty env value keeps the default (an empty string must never become a limit of 1).
    'app_download' => [
        'per_hour' => (int) (env('RATE_LIMIT_APP_DOWNLOAD_HOURLY') ?: 10),
    ],

    // Central workspace resolver (shop-code lookup) and the tenant-miss bucket of
    // ThrottleTenantMisses: caps workspace-code enumeration per client IP.
    // CTO decision W1 Q1 (2026-10-09): 30 per minute (was 10), so a shop installing
    // several devices behind one NAT address is not locked out.
    'tenant_resolve' => [
        'per_minute' => (int) env('RATE_LIMIT_TENANT_RESOLVE', 30),
    ],

    // POST /api/v1/billing/invoices/{id}/payments (receipt upload), per tenant + user + IP, and
    // (security review S2) per tenant whatever the user / IP.
    'billing_receipt_upload' => [
        'per_hour' => (int) (env('RATE_LIMIT_BILLING_RECEIPT_HOURLY') ?: 20),
        'per_tenant_per_hour' => (int) (env('RATE_LIMIT_BILLING_RECEIPT_TENANT_HOURLY') ?: 30),
    ],

    // POST /api/v1/billing/payments/{id}/cancel (shop withdraws its own receipt), security review S2:
    // per tenant + user + IP, and per tenant whatever the user / IP.
    'billing_payment_cancel' => [
        'per_hour' => (int) (env('RATE_LIMIT_BILLING_PAYMENT_CANCEL_HOURLY') ?: 20),
        'per_tenant_per_hour' => (int) (env('RATE_LIMIT_BILLING_PAYMENT_CANCEL_TENANT_HOURLY') ?: 30),
    ],

    // POST /api/v1/billing/renewal-invoice, per tenant + user + IP.
    'billing_renewal_invoice' => [
        'per_hour' => (int) (env('RATE_LIMIT_BILLING_RENEWAL_HOURLY') ?: 10),
    ],

    // POST /api/v1/billing/change-invoice (#232 self-service upgrade / subscribe), per tenant + user + IP.
    'billing_change_invoice' => [
        'per_hour' => (int) (env('RATE_LIMIT_BILLING_CHANGE_INVOICE_HOURLY') ?: 10),
    ],

    // POST /api/v1/billing/plan-change-requests, per tenant + user + IP.
    'billing_plan_change_request' => [
        'per_hour' => (int) (env('RATE_LIMIT_BILLING_PLAN_CHANGE_HOURLY') ?: 10),
    ],

    // Public assisted-signup form (POST /api/v1/public/signup-requests), PRD-05: strict, per client IP (accepted submissions only, see AppServiceProvider).
    'signup_requests' => [
        'per_hour' => (int) (env('RATE_LIMIT_SIGNUP_HOURLY') ?: 3),
        'per_day' => (int) (env('RATE_LIMIT_SIGNUP_DAILY') ?: 10),
        // Every attempt (valid or not) per IP and hour; the two limits above count accepted submissions only.
        'attempts_per_hour' => (int) (env('RATE_LIMIT_SIGNUP_ATTEMPTS_HOURLY') ?: 30),
        // Accepted submissions across ALL client IPs (one shared bucket); IPv6 clients are keyed by their /64.
        'global_per_hour' => (int) (env('RATE_LIMIT_SIGNUP_GLOBAL_HOURLY') ?: 30),
        'global_per_day' => (int) (env('RATE_LIMIT_SIGNUP_GLOBAL_DAILY') ?: 200),
    ],

    // MOB-05: public account-deletion request form (POST /api/v1/public/account-deletion-requests),
    // per client network; the strict budgets count accepted submissions only (see AppServiceProvider).
    'account_deletion_requests' => [
        'per_hour' => (int) (env('RATE_LIMIT_ACCOUNT_DELETION_HOURLY') ?: 3),
        'per_day' => (int) (env('RATE_LIMIT_ACCOUNT_DELETION_DAILY') ?: 10),
        'attempts_per_hour' => (int) (env('RATE_LIMIT_ACCOUNT_DELETION_ATTEMPTS_HOURLY') ?: 30),
        'global_per_hour' => (int) (env('RATE_LIMIT_ACCOUNT_DELETION_GLOBAL_HOURLY') ?: 30),
    ],

    // SIGN-11: public self-signup (POST /api/v1/signup), per client network like signup_requests:
    // accepted signups only for the strict budgets, every attempt for the looser ceiling.
    'self_signup' => [
        'per_hour' => (int) (env('RATE_LIMIT_SELF_SIGNUP_HOURLY') ?: 5),
        'per_day' => (int) (env('RATE_LIMIT_SELF_SIGNUP_DAILY') ?: 20),
        'attempts_per_hour' => (int) (env('RATE_LIMIT_SELF_SIGNUP_ATTEMPTS_HOURLY') ?: 60),
        'global_per_hour' => (int) (env('RATE_LIMIT_SELF_SIGNUP_GLOBAL_HOURLY') ?: 200),
        // POST /api/v1/signup/verify and /resend, per client network (the code itself allows 5 tries).
        'verify_per_minute' => (int) (env('RATE_LIMIT_SELF_SIGNUP_VERIFY_PER_MINUTE') ?: 20),
        'resend_per_hour' => (int) (env('RATE_LIMIT_SELF_SIGNUP_RESEND_HOURLY') ?: 20),
        // GET /api/v1/signup/status (polled by the "preparing your shop" screen).
        'status_per_minute' => (int) (env('RATE_LIMIT_SELF_SIGNUP_STATUS_PER_MINUTE') ?: 60),
    ],

];
