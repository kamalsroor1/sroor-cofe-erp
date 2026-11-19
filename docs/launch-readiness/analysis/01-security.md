# Sroor ERP: production-readiness security audit (multi-tenant SaaS launch)

Scope: `feature/multi-tenant` at `2547eb90` plus the uncommitted working tree (PurchaseItem/PurchaseService cost snapshot, which has no security impact). Method: read-only code review, `composer audit`, `npm audit --omit=dev` (backend and desktop). No request was sent to any live host. Secret values are never reproduced. They appear only as `file:line (redacted)`.
Baseline: the SEC-1 design review (`docs/reviews/2026-10-11-sec-1-security-design-review.md`). Lane 6A items M1, M3–M7 and L1–L8, L11 and L13 were re-checked and are fixed. L12 is only partly fixed (see SEC-04). Items still open from that review are marked "(SEC-1 Mx/Lx, still open)".

## Executive summary
1. The platform layer is solid: control-plane isolation, operator 2FA and step-up, token storage per tenant DB, central models pinned, cache keys tenant-scoped, uploads re-encoded, encrypted backups, and a guarded purge. No cross-tenant read path was found.
2. Two launch blockers: (a) suspended, cancelled, expired and archived tenants keep full API access, because the IDEN-3.5 middleware does not exist; (b) production credentials sit in tracked files of a public repo and have not been rotated (C1).
3. Before the first paying customers, the gaps that need closing are in tenant-user auth: tokens never expire and survive password changes, the password policy is 6 characters, there is no per-account brute-force cap, and there is no CSP on tenant hosts while tokens live in localStorage.
4. One privilege-escalation path remains: anyone holding `roles.manage` can grant their own role every permission. Plan features and limits are enforced only in the frontend. There are known-vulnerable Symfony and Livewire packages.
5. Effort to reach a launchable state: roughly 6–9 dev-days for P0 and P1 plus the CTO's rotation work. The P2 items (audit trail, tenant 2FA, API throttling, mobile shell, webhook removal) fit into the first month after launch.

## Findings

### SEC-01: Suspended, cancelled, expired and archived tenants keep full API access
- **Severity:** P0 (launch blocker). (SEC-1 M2, still open)
- **Evidence:** `backend/app/Http/Middleware/ResolveApiTenancy.php:70-103` refuses only `isPurged()` and `! isProvisioned()`. `Tenant::isSuspended()` (`backend/app/Models/Tenant.php:533`) is called only by the workspace resolver (`backend/app/Actions/Tenants/ResolveTenantWorkspaceAction.php:68`). `Tenant.php:526` says the `EnsureTenantActive` middleware (IDEN-3.5) is still missing, and grep finds no such class. `ApiLoginAction` never checks tenant status.
- **Scenario / impact:** A tenant whose trial or subscription ended, or who was suspended for non-payment or abuse, keeps logging in and writing, either by sending `X-Tenant: <id>` or by using its subdomain. Only the shop-code lookup screen is blocked. An archived ("deleted") tenant stays writable after its "verified final backup", so that backup is no longer final. Billing cannot be enforced, and offboarding a customer does not cut off its staff.
- **Fix:** Add an `EnsureTenantActive` step inside `ResolveApiTenancy`, right after `tenancy()->initialize()` on every resolution path (header, host, already-initialised). Map `TenantLifecyclePolicy` access levels: Blocked returns 403 with a stable `error_code` (keep `api.auth.options` and branding allowed), and read_only returns 423 for non-GET requests. Check it again in `ApiLoginAction`. Add feature tests for each status, for the header path and for the host path.
- **Effort:** M

### SEC-02: Production credentials in tracked files of a public repository (C1 not rotated)
- **Severity:** P0 (launch blocker). (SEC-1 C1, still open)
- **Evidence:** About 60 tracked root scripts carry SSH, DB or app passwords, for example `deploy_root_baraa.py:12` (redacted), `deploy_root_baraa.py:99` DB password (redacted), `seed_fresh_direct.py:14`, `verify_seeded_data.py:14`, `test_live_login.py:6`, `deploy_with_mysql.py:9`, `sync_sroor.py:6`, `check_live_db.py:5` (all redacted). `.github/workflows/deploy.yml:49` puts the deploy-webhook token in a URL (redacted). `test_val.php:15` has a hardcoded user password (redacted). The full inventory is in `docs/07-operations/secrets.md`.
- **Scenario / impact:** Anyone who reads the public repo or its history can SSH into the Hostinger host. That host runs staging and the live shop on `main`. The attacker can then read and alter that shop's database, plant code, or pivot. Launching a paid SaaS while an existing customer's server is open to the public also damages trust and legal standing. Credential reuse by the same owner (Google, GitHub, registrar) makes the risk larger.
- **Fix:** (1) Rotate now: SSH (switch to key-only and disable passwords), MySQL users, `APP_KEY` with a planned re-encryption, the webhook secret, and the Telegram bot token on Hostinger. (2) Delete `deploy.yml` (or move it to `${{ secrets.* }}` with a POST+HMAC call). (3) `git rm` the root `*_live*`, `check_*`, `deploy_*`, `fix_*`, `seed_*` and `sync_*` scripts, or move them to a private ops repo. (4) Make the repos private. Optionally purge history with `git filter-repo` after rotation. gitleaks in CI already blocks new leaks. (5) Confirm that no value is reused on the VPS (production-ops rule 9).
- **Effort:** S (rotation, CTO) + M (repo clean-up)

### SEC-03: Tenant API tokens never expire and survive password changes
- **Severity:** P1. (SEC-1 M8 token part, still open)
- **Evidence:** `backend/app/Actions/Auth/ApiLoginAction.php:67` calls `createToken($tokenName, ['*'])` with no `expiresAt`. `config/auth.php:175` defines `tokens.tenant_ttl_minutes` (30 days), but nothing in `app/` reads it. `config/sanctum.php` `expiration => null`. On password change, `app/Actions/Profile/UpdateProfileAction.php:24` and `app/Actions/Users/UpdateUserAction.php:41` (admin reset) do not revoke tokens. `ApiLogoutAction` deletes only the current token. Tokens are kept in `localStorage` (`resources/js/stores/auth.js:98`, `Services/api.js:31`).
- **Scenario / impact:** A token taken from a shared POS tablet, an Android backup (see SEC-13), an XSS (see SEC-07) or a leaked HAR file stays valid forever. A user who changes their password, or whose admin resets it, does not log out the attacker. Only deactivating the user cuts access.
- **Fix:** Issue tokens with `now()->addMinutes(config('auth.tokens.tenant_ttl_minutes'))` and extend the expiry on use in `ApiTokenAuth` (IDEN-2.2). Delete all other tokens on password change, admin reset and role change. Add a "log out all devices" endpoint. Schedule `sanctum:prune-expired` per tenant (`tenants:run`). Add tests.
- **Effort:** S–M

### SEC-04: Privilege escalation through the role-permission editor
- **Severity:** P1 (High: in-tenant privilege escalation). L12 is only partly fixed.
- **Evidence:** `backend/app/Http/Requests/UpdateRolePermissionsRequest.php:11-13` lets in anyone with `roles.manage`. `backend/app/Actions/Roles/UpdateRolePermissionsAction.php` syncs any permission except `super_admin.*`. It does not check that the actor holds the permissions they grant, and it does not stop the actor editing a role they hold. The L12 guards exist only in `UpdateUserAction` and `StoreUserRequest`.
- **Scenario:** The owner delegates `roles.manage` to a manager or accountant. That user sends `PUT /api/v1/roles/{id-of-own-role}/permissions` with every permission (`settings.manage`, `trash.access`, `logs.view`, `items.view_cost`, `purchases.delete`, `expenses.manage`…). They are now admin in all but name. They can force-delete expenses and returns (SEC-11) and change the Telegram target, and none of this is audited (SEC-09).
- **Fix:** A non-admin actor may (a) only grant permissions they already hold, (b) never edit a role they hold, and (c) never grant `roles.manage` or `settings.*` (SETG-9 condition). Return 403 with a translated message. Add audit-log entries and feature tests.
- **Effort:** S

### SEC-05: Weak password policy and no per-account brute-force cap for tenant users
- **Severity:** P1
- **Evidence:** The minimum is `min:6` in `app/Http/Requests/StoreUserRequest.php:29`, `UpdateUserRequest.php:25`, `UpdateProfileRequest.php:27`, and `StoreTenantRequest.php:27` (the tenant owner's first password). The failure counter is keyed by tenant+login+IP: `ApiLoginRequest::throttleKey()` (6 failures per 60 s). The `tenant-login` limiter keys by IP and by login+IP (`app/Providers/AppServiceProvider.php:200-221`). No cap counts failures for an account across all IPs. Logins are phone numbers, which are easy to enumerate. Tenant users have no 2FA (SEC-10).
- **Scenario / impact:** An attacker who knows a shop owner's phone number spreads guesses over many IPs. That gives 6 tries per minute per IP with no global ceiling, against passwords as short as 6 characters (often numeric on POS). One success exposes the whole shop's sales, customers and cash.
- **Fix:** Use `Password::min(8)->letters()->numbers()->uncompromised()` (10+ for `admin` and role-managers), with a translated message. Add an account-wide failure counter (tenant+login, any IP) with progressive delay and lockout, and notify the tenant admin on lockout. Show a password-strength hint in the UI.
- **Effort:** S

### SEC-06: Plan features and limits are not enforced on the server
- **Severity:** P1 (entitlement bypass, revenue)
- **Evidence:** No feature middleware or `TenantFeatureManager`/entitlement check exists in `app/Http` or `routes/api.php` (grep). `max_users`/`max_stores` are validated only when an operator edits a plan (`app/Http/Requests/UpdatePlanRequest.php:38-39`). `CreateUserAction` and `CreateStoreAction` never check them. Gating lives only in `FeatureGate.vue`/`useModules`.
- **Scenario:** A tenant on the cheapest plan calls `/api/v1/coffee-blender/*`, `/reports/*` and `/transfers` directly, or creates unlimited users and branches, with an ordinary token.
- **Fix:** Add a `feature:<key>` route middleware backed by `TenantEntitlementService` on each module group. Check limits inside the Create* Actions under `lockForUpdate` on a counter. Return 403/402 with a stable error code. Add a feature test per gated module.
- **Effort:** M

### SEC-07: No Content-Security-Policy on tenant hosts while tokens are in localStorage
- **Severity:** P1. (SEC-1 M8 CSP part, still open)
- **Evidence:** `app/Http/Middleware/AdminSecurityHeaders.php` applies a CSP only on the admin host. `scripts/ops/templates/nginx-site.conf.tmpl:48-51` sets HSTS, nosniff, XFO and Referrer-Policy, but no CSP and no Permissions-Policy. Tokens are in `localStorage` (SEC-03).
- **Impact:** Any single XSS turns into theft of every logged-in cashier's permanent token: a future `v-html`, a compromised npm dependency, or injected content through the Capacitor remote URL.
- **Fix:** Add a nonce-based CSP for the tenant SPA, reusing the AdminSecurityHeaders builder: `script-src 'self' 'nonce-…'`, `object-src 'none'`, `base-uri 'self'`, `frame-ancestors 'none'`, and `connect-src` limited to self and the platform API. Ship it as Report-Only for one week, then enforce. Add Permissions-Policy.
- **Effort:** M

### SEC-08: Known-vulnerable PHP packages
- **Severity:** P1
- **Evidence:** `composer audit` (2026-11-09) reports:
  - `symfony/http-foundation` 7.4.16: 5 advisories, including a high ReDoS in `HeaderUtils::split()` (CVE-2026-108082), a `private_ranges` trusted-proxy bypass, client-chosen host in generated URLs, and path-rule bypass.
  - `symfony/http-kernel` 7.4.16: forwarded-header host/scheme spoofing.
  - `symfony/routing`: host placeholder matches path.
  - `symfony/mime`: CVE-2026-45070, email header injection.
  - `symfony/serializer` 7.4.20: 3 advisories, including arbitrary file read.
  - `livewire/livewire` (pulled in by `laravel/pulse`): authorization bypass in `#[Authorize]`.
- **Impact:** A remotely triggerable DoS on every request (header parsing). Host-header and proxy trust issues touch the tenant-by-host resolution and the password-reset links.
- **Fix:** Run `composer update "symfony/*" livewire/livewire` (Symfony ≥ 7.4.23) and run the suite. Add a failing `composer audit` step to CI.
- **Effort:** S

### SEC-09: No tenant-side audit trail for administrative and security actions
- **Severity:** P2
- **Evidence:** `ActivityLogService` is called from auth, sales, purchases, inventory, shifts and treasury Actions. Nothing in `app/Actions/Users`, `Roles`, `Settings`, `Trash`, `Stores` or `Profile` logs. There are no observers on User or Role (only `TenantObserver`).
- **Impact:** User creation, role and permission changes, password resets, settings changes (including the Telegram destination), store deletion and force-deletes of financial records leave no trace. Insider fraud and support disputes cannot be investigated.
- **Fix:** Log these Actions with actor, target and a before/after diff without secrets. Keep `activity_logs` append-only (no delete endpoint) and document retention. Add tests.
- **Effort:** M

### SEC-10: No second factor for tenant owners and admins
- **Severity:** P2
- **Evidence:** `ApiLoginAction` is password-only. TOTP exists only for central operators (Fortify, `routes/central.php`).
- **Impact:** The owner account sees all money data. Combined with SEC-05 it is the most valuable target.
- **Fix:** Optional TOTP for tenant users, which a tenant setting can make mandatory for the `admin` role. Reuse the central challenge store design.
- **Effort:** M–L

### SEC-11: Trash restore and force-delete ignore branch scope and hard-delete financial documents
- **Severity:** P2
- **Evidence:** `app/Http/Controllers/Api/TrashController.php:27,52,75` require only `trash.access`. `app/Actions/Trash/ForceDeleteTrashRecordAction.php:23-29` covers `expenses`, `returns` and `stores` with no `ActiveStore::canAccess` check.
- **Impact:** A `trash.access` holder in branch A can permanently erase branch B's expenses or returns, or restore them. The financial history is lost and nothing is logged.
- **Fix:** Scope store-bound types by accessible stores and return 404 otherwise. Allow force-delete of financial documents only for `admin`, or not at all, and audit it.
- **Effort:** S

### SEC-12: No rate limit on the authenticated tenant API (noisy neighbour, bulk exfiltration)
- **Severity:** P2
- **Evidence:** `bootstrap/app.php` does not call `throttleApi`, and the `ApiTokenAuth` group in `routes/api.php` has no throttle. Report endpoints take any date range. All tenants share one MySQL and PHP-FPM pool.
- **Impact:** One tenant's script, or a stolen token, can saturate the shared database and slow down every other customer. It can also pull the whole dataset without limit.
- **Fix:** Add a limiter keyed by tenant+user (e.g. 300/min) on the protected group, with tighter limits on `reports/*` and exports. Cap report date ranges. Queue heavy exports.
- **Effort:** S

### SEC-13: Mobile shell allows cleartext, is pinned to one tenant and allows backups
- **Severity:** P2. (SEC-1 L9, still open)
- **Evidence:** `backend/capacitor.config.json` sets `server.url` to a single tenant subdomain with `"cleartext": true`. `backend/android/app/src/main/AndroidManifest.xml:5` has `android:allowBackup="true"`.
- **Impact:** Cleartext is allowed for the WebView. The WebView `localStorage` (which holds the non-expiring token) can be pulled through ADB or cloud backup. Every SaaS customer's APK points at one shop's host.
- **Fix:** Set `cleartext: false` and add a `network_security_config` (no cleartext). Set `allowBackup="false"` or use `dataExtractionRules` that exclude WebView data. Point the shell at the platform host and its workspace picker.
- **Effort:** S

### SEC-14: Deploy webhooks in the web root
- **Severity:** P2. (SEC-1 L10, still open)
- **Evidence:** `backend/public/update_webhook.php` and root `update_webhook.php` run `git reset --hard origin/main` and `artisan migrate --force` and echo the command output. They are HMAC-signed with a 300 s window and no nonce, so a request can be replayed inside that window. `.github/workflows/deploy.yml:49` still calls the GET-with-token form (redacted). The VPS nginx serves only `index.php` (`nginx-site.conf.tmpl:85,120`), so the risk is limited to the Hostinger host.
- **Fix:** Delete both files and `deploy.yml` once `release.yml` replaces them. Until then, store used signatures (nonce) and stop echoing command output.
- **Effort:** S

### SEC-15: Frontend dependency advisories in runtime dependencies
- **Severity:** P2
- **Evidence:** `npm audit --omit=dev` in `backend/` reports 2 high (`follow-redirects`: credential-header leak on redirect; `vite`: launch-editor command injection) and 6 moderate (`@capacitor/cli`, `glob`, `minimatch`, `rimraf`, `uuid`, `xcode`). `vite` and `@capacitor/cli` are listed as runtime dependencies (SEC-1 L14). `desktop/` is clean.
- **Fix:** Move build tooling to `devDependencies`, run `npm audit fix`, and add an `npm audit --omit=dev --audit-level=high` gate to CI.
- **Effort:** S

### SEC-16: Wildcard version constraints
- **Severity:** P3. (SEC-1 L14)
- **Evidence:** `backend/composer.json:15-16` sets `laravel/sanctum: "*"` and `laravel/telescope: "*"`.
- **Fix:** Pin to `^<current major>`.
- **Effort:** S

### SEC-17: Platform internals shown to tenant admins
- **Severity:** P3
- **Evidence:** `app/Http/Controllers/Api/SettingController.php:83-96` `system_info` returns the PHP and Laravel versions, environment, DB driver and `SERVER_SOFTWARE` to every tenant with `settings.manage`.
- **Fix:** Remove it from the tenant API, or show it only in the central console.
- **Effort:** S

### SEC-18: Scheduled Telegram jobs run in central context, plus a dead DB-dump-to-Telegram path
- **Severity:** P3. (SEC-1 L15)
- **Evidence:** `routes/console.php:32-42` schedules `notify:daily-summary|low-stock|overdue-shifts`. `app/Console/Commands/SendDailyTelegramSummaryCommand.php` never initialises tenancy, so it queries the central DB. `app/Services/TelegramService.php:397` `sendDatabaseBackupNotification()` calls `DatabaseBackupService`, which builds an unencrypted `.sql.gz` of the whole connection, including password hashes and tokens. That code has no callers today.
- **Fix:** Iterate with `Tenant::cursor()` and `$tenant->run()`, each tenant using its own bot settings. Delete the dump-to-Telegram code and `DatabaseBackupService`, since backups go through `backup:tenants` only.
- **Effort:** S

### SEC-19: Dead export code with no authorization and no formula escaping
- **Severity:** P3
- **Evidence:** `app/Http/Controllers/ExportController.php` and `app/Services/ExportService.php:28-31` call raw `fputcsv`. They are not routed today.
- **Impact:** If someone re-wires them, they bring back CSV formula injection and cross-store statements.
- **Fix:** Delete both, or rebuild behind `/api/v1` with authorization, store scope and `neutralizeFormula`.
- **Effort:** S

### SEC-20: All tenant databases share one MySQL user
- **Severity:** P3 (defence in depth)
- **Evidence:** `config/tenancy.php:88` uses `SafeMySQLDatabaseManager`. `PermissionControlledMySQLDatabaseManager` is commented out (`:96`). The VPS runbook grants the app user rights on `tenant%`.
- **Impact:** No SQL injection exists today (only bindings, and the only `orderByRaw` uses a constant). But any future injection, or a bug that switches connections, could reach every tenant DB.
- **Fix:** Per-tenant DB users (stancl permission-controlled manager), provisioned by `TenantProvisionerService`. Or document the accepted risk.
- **Effort:** M

### SEC-21: CORS and proxy trust left at framework defaults
- **Severity:** P3
- **Evidence:** There is no `backend/config/cors.php`, so the framework default `allowed_origins: ['*']` applies on `api/*`. Credentials are not used, so the risk is low today. `bootstrap/app.php` does not configure `trustProxies`.
- **Impact:** If a CDN or load balancer is added, `$request->ip()` becomes the proxy for everyone. Every IP limiter, login brute-force counter and tenant-miss counter would then collapse into one shared bucket (lockout DoS), or be bypassed.
- **Fix:** Publish `cors.php` with platform and tenant origins plus the Capacitor/Electron origins. Configure `trustProxies` explicitly for the real proxy ranges (not `private_ranges`, see SEC-08).
- **Effort:** S

### SEC-22: Minor hardening items
- **Severity:** P3
- **Evidence and fixes:**
  - (a) `StoreController.php:70-71` returns the main store to a user who has no accessible store. Return an empty list instead.
  - (b) Raw Blade output: `resources/views/layouts/print-report-a4.blade.php:138` uses `{!! $cell['value'] !!}` (static values today), and `resources/views/app.blade.php:82` uses `json_encode` without `JSON_HEX_TAG`. Switch to `{{ }}` and `@json`.
  - (c) Tracked `.env.e2e` holds an `APP_KEY` (test-only, redacted). Generate it in CI and never reuse it.
  - (d) The login response tells "account disabled" apart from a wrong password, but only after a correct password. That is acceptable, but record it as a decision.
- **Effort:** S

### SEC-23: Tenant data lifecycle and privacy gaps
- **Severity:** P3
- **Evidence:** The purge is well guarded (`app/Console/Commands/PurgeTenantCommand.php:23-41`: archived ≥ 30 days, verified backup with restore drill, typed confirmation, CLI only). Gaps:
  - No owner self-service data export.
  - The retention and expiry of the `pre_purge` backup and the other backups of a purged tenant are not defined, so "deleted" data can live on in Drive indefinitely.
  - The shared daily log keeps login identifiers and IPs of all tenants for 14 days.
  - There is no privacy policy or DPA describing any of this.
- **Fix:** Add an owner export endpoint (queued, encrypted zip). Delete a purged tenant's backups after N days, with a ledger entry. Document retention in the terms of service.
- **Effort:** M

## Checked and OK
- Tenant token lookup runs on the tenant connection after tenancy is initialised. A token from tenant A sent with `X-Tenant: B` fails the hash check, so it cannot be used across tenants.
- Without a tenant, only allowlisted central-safe routes answer (SEC-1 M1 fixed). The admin host refuses the tenant API.
- Unknown-tenant probing is throttled (`ThrottleTenantMisses`).
- The control plane (`routes/central.php`) is separate: CentralUser tokens with expiry, mandatory 2FA, step-up on destructive routes, `Gate::before` denies tenant abilities to operators and monitoring abilities to tenant admins.
- Telescope, Pulse and Horizon sit behind the central guard. The Telescope link is signed and single-use. The production template sets `TELESCOPE_ENABLED=false`.
- Every central model pins the central connection (21 models). Cache keys go through `TenantCache`. `Setting` cache keys include the tenant id. Rate-limit keys are tenant-scoped.
- Store scoping: `X-Store-Id` is checked in `ApiTokenAuth` and `store.active`. Show, update, cancel and destroy on invoices, purchases, returns, expenses, transfers and shifts go through `find*Or404` with policies. Adjust-stock checks the body `store_id`.
- No `$request->all()` into models and no `$guarded = []`. All raw SQL is constant. Every FormRequest except the public auth/resolver ones has a real `authorize()`. User create/update guard the admin role.
- Uploads: magic bytes, re-encoding, server-generated names, private disks. The logo is served with `CSP sandbox`. APK downloads come from DB records, not user paths. stancl asset path traversal is guarded in v3.10.1.
- Production `.env` template: `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, `LOG_LEVEL=warning`, Sentry `send_default_pii=false` with no SQL bindings. Secrets are rendered from GitHub Environments and gitleaks runs in CI.
- Backups: AES-256 archives, and the job refuses to run without a password outside local. Purge is CLI-only with a restore drill.
- Electron: `contextIsolation`, `sandbox`, no `nodeIntegration`, navigation and window-open policy. Desktop npm audit is clean.
- Activity-log CSV neutralises formulas. Telegram bot tokens are masked in the settings response.
- The uncommitted diff (PurchaseItem `wac_before`/`cost_price_before`) is server-derived and fillable only from service code. No new security issue.

## Not covered
- Live server configuration (nginx/PHP/MySQL as actually deployed, real `.env` values, firewall), by rule.
- Git history secret scan beyond the working tree (gitleaks owns it). Whether the Hostinger credentials were already rotated cannot be verified from the repo.
- Dynamic testing (no requests sent), Android release signing and Play configuration, the desktop auto-update server side.
- A line-by-line read of every Action for business-logic authorization. Controllers and Requests were sampled, with a focus on write and destructive paths.
