# Backend architecture, code quality & performance — Score 3/10

> Branch `feature/multi-tenant` · 2026-10-07 · read-only multi-agent analysis · secrets redacted

## Executive summary

This analysis ran on the SaaS branch, feature/multi-tenant, with a clean working tree. No files were changed.

**What is solid.** The internal engineering is good:
- Money and quantity columns are DECIMAL(12,3) in all 35 tenant migrations.
- The stock and balance services use DB::transaction, lockForUpdate and bcmath, and lock in a consistent order.
- Write paths mostly go FormRequest -> DTO -> single-method Action -> Resource.
- Every index endpoint paginates with a capped per_page.
- New tenants are provisioned cleanly, with no coffee or demo seed data.
- 305 of 306 PHPUnit tests pass.

**Why it cannot be sold yet.** The SaaS layer has several platform-level holes, each confirmed against the code by adversarial verification:

1. **Unauthenticated login as any user.** POST /api/v1/auth/quick-login is public, has no throttle, and needs no password. It returns a full-scope Sanctum token for any user, identified by id, phone or email. GET /api/v1/auth/workspace-users, also public, lists the targets.
2. **Super-admin access through tenant data.** Gate::before grants everything to two hardcoded phone numbers, or to any user holding a super_admin role. That role is seeded into every tenant database. The /super-admin routes never check that tenancy is off. So any tenant user can reach central control:
   - a tenant admin by assigning the role or the phone;
   - even a cashier, through PUT /profile with one of the phone numbers.
   From there they can edit other tenants' DB credentials, run migrations, change plans and override features.
3. **Anonymous invoice printing.** The print routes in tenant.php and web.php sit outside auth. Anyone who knows a tenant's domain can enumerate invoices with customer PII and balances.
4. **No branch isolation.** The client's X-Store-Id / store_id is trusted everywhere. StoreAccess middleware is registered but never attached.
5. **Scheduler runs only in central context.** Tenant Telegram reports fail every run. No tenant database is ever backed up. The central DB (password hashes, the api_token column, possibly tenant DB passwords) is sent nightly as an unencrypted gzip to Telegram, with a caption claiming it is encrypted.

**Money-correctness bugs on core flows:**
- The POS split payments and additional expenses are dropped by StoreSalesInvoiceRequest / CreateInvoiceDTO, so treasury channels and the Z-report are wrong.
- Deleting, restoring or force-deleting a return changes the document without reversing stock or balance, which breaks the no-hard-delete rule.
- Customer and supplier opening balances are overwritten on the first recalculation. In the UI they are dropped even earlier, because the form sends initial_balance and the backend expects opening_balance.

**Code defects:**
- DeleteTenantAction has a PHP syntax error, so tenant delete fatals.
- AuthApiTest locks in the passwordless login as expected behaviour.

**Score: 3/10.** The domain core would earn about 6-7, but cross-tenant privilege escalation and unauthenticated takeover are disqualifying for a multi-tenant SaaS. Most critical fixes are small (S/M effort). The score could reach about 6 once the auth and super-admin boundary, branch scoping and the scheduler are fixed.

**Open questions for ops:**
- Does the production central DB still hold legacy single-tenant tables? If so, the unauthenticated web.php routes leak live data.
- Which Telegram chat is configured, and has it already received central dumps?
- Is CACHE_STORE=database guaranteed? Cache isolation for the P&L/ABC reports depends on it.
- Who holds the admin role in the central users table? ApiTokenAuth's phone fallback trusts it.
- Is quick-login a real product requirement (shared POS terminal)?

## Top risks
- CRITICAL - Unauthenticated account takeover: routes/api.php:28-29 expose POST /api/v1/auth/quick-login and GET /auth/workspace-users outside ApiTokenAuth, with no throttle. ApiQuickLoginAction.php:26-30 matches the user by phone/email/id and issues createToken(['*']) with no credential. It also writes the plaintext token to users.api_token (L57). One request with X-Tenant:<slug> and {login:'1'} returns that tenant's admin token. With no tenant header it resolves against the central users table. tests/Feature/Api/AuthApiTest.php:287 asserts this behaviour.
- CRITICAL - Tenant users can escalate to platform super-admin:
- AppServiceProvider.php:38 (Gate::before) and :49 (viewPulse) trust two hardcoded phone numbers, which are also personal data committed to source.
- The same phones appear in OverrideTenantFeatureRequest:13, ToggleTenantStatusRequest:13, UserResource:19, TelescopeServiceProvider and web.php:38.
- PermissionsSeeder.php:70 seeds the super_admin role into every tenant DB.
- The /api/v1/super-admin/* group (api.php:196) never checks that tenancy is not initialized.
- Tenant models use the central connection, so SuperAdminApiController acts on the central DB.
- Any cashier (UpdateProfileRequest/UpdateProfileAction.php:26 set the phone) or tenant admin (StoreUserRequest role exists:roles) gains control of other tenants: update-db-config, run-migrations, override-feature, plans.
- HIGH - Anonymous invoice PII leak: the closures at routes/tenant.php:60-74, and duplicates at web.php:56-65, sit outside the auth group. They run Invoice::findOrFail($id) with no authorization. Anyone can enumerate sequential IDs and harvest customer names, phones, addresses and balances.
- HIGH - No branch (store) isolation in the API:
- StoreAccess middleware is never attached to any route.
- ApiTokenAuth.php:83-86 stores any X-Store-Id in the session.
- InvoiceController:40, TreasuryController:34, ReportController:37 + ReportFilterDTO and others trust the client's store_id, and 'all' means every store.
- The same unchecked header feeds write paths such as StorePOSInvoiceRequest:23.
- HIGH - The scheduler is not tenant-aware (routes/console.php:25-38):
- The notify:* commands query tenant tables on the central connection and fail every run.
- backup:telegram dumps the central DB in plaintext gzip (DatabaseBackupService) and posts it to Telegram. The caption at TelegramService.php:383 claims it is encrypted.
- No tenant database is ever backed up.
- HIGH - POS split payments and expenses silently dropped:
- PosView.vue:707-724 sends `payments` and `expenses`.
- StoreSalesInvoiceRequest.php:39-57 has no rules for those keys, and CreateInvoiceDTO has no payments field.
- So InvoiceService L130/L225 never run.
- The result is one payment on one channel, wrong treasury and Z-report figures, and a mismatch between the screen total and the server's net_total.
- HIGH - Return deletion breaks stock and balance integrity:
- DeleteReturnAction.php:16 only soft-deletes, with no reversal.
- RestoreTrashRecordAction and ForceDeleteTrashRecordAction restore or hard-delete returns. return_items cascade on delete and StockMovements are left orphaned.
- This violates AGENTS.md rule 5.
- HIGH - Opening balances are lost:
- CreateCustomerAction.php:24 and CreateSupplierAction.php:24 write opening_balance into current_balance.
- CustomerBalanceService.php:16-44 and SupplierBalanceService rebuild current_balance without it.
- The Vue forms send initial_balance, which the backend ignores.
- Receivables and payables disappear without a trace.
- MEDIUM - DeleteTenantAction.php:12-16 has a syntax error (the $tenant variables are missing), so DELETE /super-admin/tenants/{id} fatals. routes/api.php has no throttle on any route except the password-login FormRequests.

## Quick wins
- Delete or disable the quick-login and workspace-users routes (routes/api.php:28-29). Flip AuthApiTest:287 so it expects 404/401. About 1 hour.
- Remove the hardcoded phone allow-list from these files and rely on the central super_admin role only:
- AppServiceProvider.php:38 and :49
- OverrideTenantFeatureRequest
- ToggleTenantStatusRequest
- UserResource:19
- TelescopeServiceProvider
- web.php:38
About 2 hours.
- Add an EnsureCentralContext middleware to the /super-admin group that aborts with 403 when tenancy()->initialized is true. Stop seeding the super_admin role in tenant DBs by filtering PermissionsSeeder when it runs under tenant->run(). Whitelist the assignable roles in StoreUserRequest and UpdateUserRequest. About half a day.
- Move the invoice print routes in tenant.php:60-74 behind auth, or switch them to URL::temporarySignedRoute links returned by the API. Delete the duplicate closures in web.php:56-65. About half a day.
- Add throttle middleware (for example throttle:10,1) to the public auth routes, and a default api rate limiter in bootstrap/app.php. About 1 hour.
- Unschedule backup:telegram and the notify:* commands in routes/console.php until they are tenant-aware, and rotate any credentials that may already have been sent. About 1 hour.
- Fix the PHP syntax error in DeleteTenantAction.php:12-16. About 15 minutes.
- Add the missing rules to StoreSalesInvoiceRequest:
- `payments.*.method|amount`
- `additional_expenses.*.*`
In prepareForValidation, map `expenses` to `additional_expenses`. Add a payments property to CreateInvoiceDTO, and a feature test that posts the exact PosView payload. About 1 day.
- Block DeleteReturnAction, and block trash restore/force-delete for 'returns', until a cancellation flow exists. About 1 hour.

## Strategic items
- Separate central and tenant identity:
- Super-admins authenticate only on central domains, against a central-only guard and model.
- Tenant tokens can never pass super_admin.* abilities.
- Review the ApiTokenAuth step-3 fallback (lines 50-60), which maps a central admin token to tenant users by phone.
- Add tests that prove a tenant token gets 403 on every /super-admin route.
- If shared-terminal cashier switching is a product requirement, rebuild quick-login as a device-pairing flow:
- an authenticated terminal token;
- a hashed per-user PIN, throttled and scoped to the store;
- a tenant setting to enable it;
- never available in central context.
Drop the plaintext users.api_token column.
- Add a ResolveActiveStore middleware for the api group:
- validate X-Store-Id against the user's assigned stores, with an admin bypass;
- allow 'all' only with a cross-store permission;
- bind the resolved store into the container.
Refactor the controllers and DTOs to use it, and add isolation tests for every list and write endpoint.
- Make scheduling tenant-aware:
- run notifications per tenant with tenancy()->runForMultiple(), or one queued job per tenant, using each tenant's own Telegram settings;
- replace the Telegram SQL dump with encrypted per-tenant-DB backups (mysqldump) to private storage, with restore drills.
- Financial document lifecycle:
- add a status to returns and a CancelReturnAction (transaction + lockForUpdate + reverse movements + recompute balances);
- forbid force-delete of any financial document in the trash module;
- make restore re-apply effects through the services, or forbid it.
- Opening balances as first-class data: add an opening_balance DECIMAL(12,3) column or an opening-balance ledger document, include it in every recalculation and statement, fix the initial_balance/opening_balance mismatch between the Vue form and the API, and audit existing customers and suppliers whose balances may already have been lost.
- Add a route-security CI gate: script `php artisan route:list` (it boots on sqlite :memory:) to fail the build when a route lacks auth or a can: middleware, unless it is on an explicit allowlist. Add contract tests that post real SPA payloads to the money endpoints.
- Verify cache and Pulse isolation per tenant:
- set CACHE_STORE and DB_CACHE_CONNECTION explicitly;
- set PULSE_DB_CONNECTION to central;
- confirm the order of the StoreScope and InitializeTenancyByDomain middleware in tenant.php.

## Strengths
- Every money and quantity column in the tenant migrations is DECIMAL(12,3) with decimal:3 casts, and no float or double is used anywhere in the migrations. Report and accessor math uses bcmath at scale 3.
- StockService::deductStock/addStock and StockTransferService use DB::transaction + lockForUpdate with a consistent lock order (Item, then source StoreStock, then destination StoreStock), which reduces deadlocks. Balance recalculation runs under a lock inside the outer transactions.
- Clean layering on most write paths: FormRequest -> DTO::fromArray(validated()) -> single-method Action -> API Resource. No $guarded=[], no mass-assigned $request->all(), and no client-chosen sort columns.
- Performance hygiene: every index endpoint clamps per_page (200, or 500 for items). Eager loading selects only needed columns (InvoiceController@index). Resources use whenLoaded/whenCounted. The POS bootstrap loads stock with one LEFT JOIN and COALESCE. ExportService streams CSV.
- Sound tenancy foundations: DB-per-tenant with stancl v3, QueueTenancyBootstrapper and FilesystemTenancyBootstrapper enabled, and a tenant-prefixed Setting cache key that is invalidated on write. Provisioning seeds only permissions, the main store, the admin and settings, with generic item schema and no coffee leftovers.
- The API surface is consistent: everything is under /api/v1, every route is named, responses use a {success, data, meta} envelope, and error and 401 messages are translated. Password login uses RateLimiter, and logout revokes Sanctum tokens.
- Inertia and Livewire leftovers are removed, so the architecture is now a clean Laravel API + Vue 3 SPA.
- Test suite health: 305 of 306 PHPUnit tests pass on sqlite :memory:. The one failure, the AppUpdate APK test, is likely caused by a local APK file in the environment.

## Findings

### 1. [CRITICAL] Unauthenticated passwordless login: anyone can mint a Sanctum token for any user, including admin
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\AuthController.php:115`
- **Effort:** M
- **Evidence:** routes/api.php:27-28 registers GET /api/v1/auth/workspace-users and POST /api/v1/auth/quick-login outside the ApiTokenAuth group. Only ResolveApiTenancy runs on them, and there is no throttle. AuthController@workspaceUsers (L93) returns every active user's id, name and phone-or-email. AuthController@quickLogin (L115) calls $request->validate(['login'=>required]) and then ApiQuickLoginAction::execute, which matches the user by phone OR email OR id (ApiQuickLoginAction.php:26-30) and returns createToken($tokenName, ['*']) with no password, PIN, device binding or per-tenant setting check. It also writes the plaintext token to users.api_token (L57). tests/Feature/Api/AuthApiTest.php:287 test_quick_login_succeeds_without_password asserts this works, and I ran it: it passes.
- **Impact:** An attacker sends X-Tenant: <slug> (or uses the tenant subdomain) and POSTs {"login":"1"}. They get a full-scope bearer token for user #1, normally the tenant admin. That gives full read/write over invoices, treasury, users and roles in any tenant whose slug they can guess. On a central host with no X-Tenant header, the same call resolves against the central users table, which risks a super-admin token. This is a remote takeover of a multi-tenant SaaS.
- **Recommendation:** Remove quick-login from the public group now. If cashier quick-switch is needed, require an authenticated admin session plus a per-user PIN (hashed), a tenant setting that enables it, and rate limiting. Never accept a bare id. Remove workspace-users from guest access, or return names only behind a device-pairing token. Update the test to assert 401/422 without a credential.
- **Verification:** The finding holds up against the code. I found no guard anywhere in the request path that blocks it.

1. **Routes are public.** In backend/routes/api.php, lines 28-29 register POST /auth/quick-login and GET /auth/workspace-users inside the v1 group, which only has ResolveApiTenancy. They sit outside the ApiTokenAuth group that starts at L33. Neither route has throttle or any other middleware.
2. **No global guard.** bootstrap/app.php adds only StoreScope, and only to the web group. It adds nothing to the api group, and no global rate limiter applies.
3. **The tenancy middleware does not authenticate.** ResolveApiTenancy only selects the database. Any X-Tenant header, tenant query parameter or tenant subdomain initializes that tenant. On a central host with no header, the request falls through to the default (central) connection.
4. **The user list is exposed to guests.** AuthController::workspaceUsers (L93-110) returns id, name and phone-or-email for every active user, with no auth.
5. **The login has no checks.** AuthController::quickLogin (L115-133) only validates that 'login' is a string. ApiQuickLoginAction::execute then matches the user by phone, email or id (L26-30) and checks only is_active. It issues createToken($tokenName, ['*']) with no password, PIN, device binding, role restriction or tenant setting check. It also stores the plaintext token in users.api_token (L57-60). A failed attempt is only logged; nothing locks the account or rate-limits the caller.

So anyone who can reach the API can enumerate a tenant's users and get a full-scope bearer token for any active user, including the admin, by posting {"login":"<id>"}. That is a remote, unauthenticated takeover of the account.

One part of the impact is speculative: whether the central-host path gives a super-admin token depends on what lives in the default connection's users table. The tenant-takeover path alone is enough to keep the severity at critical.

### 2. [CRITICAL] Hardcoded phone numbers in Gate::before give any tenant user full super-admin rights
- **Location:** `D:\projects\sroor\backend\app\Providers\AppServiceProvider.php:38`
- **Effort:** S
- **Evidence:** Gate::before returns true when `in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]'])`, and it runs before the `super_admin.` deny branch. viewPulse (line 49) uses the same check. Users live in each tenant's own DB. A tenant admin can set any phone through CreateUserAction/UpdateUserAction ('phone' => $dto->phone), and phone is only unique inside that tenant. The super-admin API (routes/api.php:196, `can:super_admin.access`) is in the same ApiTokenAuth group that tenant users authenticate against.
- **Impact:** A tenant admin creates or edits a user with phone [REDACTED_PHONE], logs in through the tenant API, and passes `can:super_admin.access`. That user can then list, create and modify every tenant, plan and subscription, and reach impersonation. This breaks isolation across all tenants. It also leaks real staff phone numbers in source code.
- **Recommendation:** Remove the phone allow-list completely. Give super-admin rights only to users in the central DB that have the super_admin role, and allow super-admin routes only on central domains with tenancy not initialized.
- **Verification:** I confirmed this from the code, and it is worse than claimed. In backend/app/Providers/AppServiceProvider.php:38, Gate::before returns true when the user has the super_admin role OR when `in_array($user->phone, [two hardcoded numbers])`. That check runs before the deny branch for `super_admin.*` at line 41. viewPulse at line 49 uses the same phone check.

How a tenant user reaches the super-admin routes:
- The super-admin group (routes/api.php:196, `can:super_admin.access`) sits inside the same `v1` group as ResolveApiTenancy and ApiTokenAuth (api.php:19 and 33). Only `api/v1/central/*` skips tenancy.
- With an X-Tenant header or a tenant host, ApiTokenAuth (app/Http/Middleware/ApiTokenAuth.php:32-47) logs in a user from that tenant's own DB.
- The Gate then checks that tenant user's `phone` column, which the tenant controls.
- SuperAdminApiController queries `Tenant::findOrFail` etc. Tenant extends stancl's BaseTenant, which pins the central connection, so those queries hit the central DB even while tenancy is active.
- I found no guard in the controller (no tenant()/abort check) or in middleware that blocks tenant context.

Making it worse: you don't need to be a tenant admin. UpdateProfileRequest::authorize() (app/Http/Requests/UpdateProfileRequest.php:12-15) only checks `user() !== null`. It accepts `phone`, unique only within that tenant's users table. UpdateProfileAction.php:26 writes `$user->phone = $validated['phone']`. So any logged-in user in any tenant (cashier included) can PUT /api/v1/profile with one of the hardcoded numbers. They then pass `can:super_admin.access` and can list, create and delete tenants, change DB config, run migrations, edit plans and platform settings, and pass every other gate check. A tenant admin can do the same through StoreUserRequest/UpdateUserRequest (phone unique per tenant only).

The hardcoded phone numbers are also personal data committed to source (line 38 and line 49). Severity stays critical.

### 3. [CRITICAL] Unauthenticated passwordless login on /api/v1/auth/quick-login lets anyone take over any tenant or the central super-admin
- **Location:** `D:/projects/sroor/backend/app/Actions/Auth/ApiQuickLoginAction.php:26`
- **Effort:** M
- **Evidence:** routes/api.php:28 registers POST /auth/quick-login outside the ApiTokenAuth group. ApiQuickLoginAction::execute() looks up User where phone = $login OR email = $login OR id = $login, checks only is_active, then issues createToken($tokenName, ['*']). There is no password, PIN, device binding or throttle. routes/api.php:29 /auth/workspace-users is also public and returns id, name and login (phone/email) for every active user. ResolveApiTenancy picks the tenant from an X-Tenant header, a ?tenant= parameter or the subdomain. User has no connection override, so when no tenant is resolved (central host, no header) the lookup runs against the CENTRAL users table. tests/Feature/Api/AuthApiTest.php:287 test_quick_login_succeeds_without_password locks this behaviour in, and AuthApiTest passes 12/12.
- **Impact:** One request takes over a whole tenant: POST /api/v1/auth/quick-login with X-Tenant: <slug> and {"login":"1"} returns a full-scope bearer token for that tenant's first user, normally the admin. Sending the same request to the central host with no X-Tenant returns a token for central user id 1, which passes can:super_admin.access, so the caller gets the whole platform: list, delete or reconfigure every tenant, run migrations, edit plans. This is an internet-reachable remote compromise.
- **Recommendation:** Remove quick-login and workspace-users from the public group now. If the business really needs fast cashier switching, require an authenticated terminal/device token plus a per-user PIN (hashed, throttled, store-scoped), and never allow it in central context. Replace the test with negative tests: 401 without a device token, 422 with a wrong PIN, and no central-context login.
- **Verification:** I confirmed this in the code and found no guard anywhere that blocks it.

**The login endpoint**
- routes/api.php:28 registers POST /api/v1/auth/quick-login inside the `v1` group. That group only applies the ResolveApiTenancy middleware; it sits outside the ApiTokenAuth group that starts at line 32.
- There is no throttle anywhere. bootstrap/app.php adds no rate limiting for the api group and the route has none.
- AuthController::quickLogin (around line 115) only checks that `login` is present and is a string.

**The login action**
- ApiQuickLoginAction::execute (lines 26-30) finds the user with `phone = $login OR email = $login OR id = $login`.
- It then checks only `is_active` (line 47).
- It issues `createToken($tokenName, ['*'])` (line 55). There is no password, PIN, device binding or IP check.

**The user list endpoint**
- routes/api.php:29 also exposes GET /auth/workspace-users without a token.
- AuthController::workspaceUsers (lines 93-110) returns the id, name and login (phone or email) of every active user.
- So an attacker does not have to guess who to log in as.

**How the tenant is chosen**
- ResolveApiTenancy takes the tenant from the X-Tenant header, `?tenant=`, the `tenant` input, or the host / subdomain.
- If none of these resolves to a tenant (central host, no header), it never initializes a tenant and the User lookup runs on the default central connection.

**Why the tokens work**
- ApiTokenAuth accepts any Sanctum token whose user is active.
- In central context it also sets the super_admin and central guards (shown in the middleware).
- The super-admin routes (routes/api.php:196 onward) sit inside the same protected group, behind `can:super_admin.access`.
- The Gate::before rule in AppServiceProvider.php:38 lets that check pass for any user with the super_admin role, or whose phone is one of two hardcoded numbers.
- So a quick-login on the central host as a super_admin user (picked from workspace-users) gets a token that reaches tenant list, create and delete, update-db-config, run-migrations, plans and platform settings.
- Inside a tenant, the same request returns a full-scope token for any user, including the admin.

**Tests**
- tests/Feature/Api/AuthApiTest.php has `test_quick_login_succeeds_without_password`, which locks in this behaviour.

**Points I could not confirm**
- The finding assumes central user id 1 is a super admin. That depends on the seed data. It does not change the outcome, because workspace-users lists every active central user and the gate trusts hardcoded phone numbers.

**Related issue (separate from this finding)**
- The Gate::before check on hardcoded phone numbers (AppServiceProvider.php:38) is a problem on its own.
- Any tenant user whose phone matches one of those numbers passes `super_admin.access`.

Severity stays critical: one unauthenticated request lets anyone on the internet take over a tenant or the whole platform.

### 4. [CRITICAL] Tenant admin can escalate to platform super-admin by assigning the 'super_admin' role (or a backdoor phone number) inside their own tenant
- **Location:** `D:/projects/sroor/backend/app/Providers/AppServiceProvider.php:38`
- **Effort:** M
- **Evidence:** Gate::before returns true when $user->hasRole('super_admin') || in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]']). TenantProvisionerService::provision runs PermissionsSeeder inside tenant->run(), and that seeder creates the 'super_admin' role in every tenant DB (PermissionsSeeder.php:70). StoreUserRequest only validates role with 'exists:roles,name' and phone with 'unique:users,phone', and both rules are per tenant DB. The /api/v1/super-admin/* group (api.php:196) is guarded only by can:super_admin.access and runs in whatever tenancy context the request has. SuperAdminApiController never checks that tenancy is NOT initialized, and Tenant (stancl BaseTenant) always uses the central connection.
- **Impact:** A paying tenant admin sends POST /api/v1/users {role:'super_admin', ...} or {phone:'[REDACTED_PHONE]', ...}, logs in as that user and calls GET/DELETE /api/v1/super-admin/tenants/{id}, update-db-config, run-migrations or override-feature against the central DB. The result is cross-tenant data destruction and DB-credential tampering. The same hardcoded phones also appear in UserResource.php:19, Toggle/OverrideTenant*Request authorize(), TelescopeServiceProvider and routes/web.php:38.
- **Recommendation:** Remove every hardcoded phone/email allowlist. Stop seeding the super_admin role and the super_admin.access permission into tenant DBs. Restrict assignable roles in StoreUserRequest/UpdateUserRequest to a whitelist. Add a middleware on the super-admin group that rejects any request where tenancy()->initialized is true and authenticates against a central-only guard/model.
- **Verification:** The finding holds up against the code. I could not find a guard anywhere in the chain that stops it.

**How the escalation works:**

1. **The `super_admin` role exists in every tenant database.** `TenantProvisionerService.php:85-87` runs `PermissionsSeeder` inside `$tenant->run()`. That seeder calls `Role::firstOrCreate(['name'=>'super_admin'])` at `PermissionsSeeder.php:70`, so the role is created in each tenant DB.

2. **A tenant admin can create a user with that role or with a backdoor phone.**
   - `POST /api/v1/users` (`routes/api.php:168`) has no route middleware.
   - `StoreUserRequest::authorize()` passes for any tenant user with `hasRole('admin')`.
   - Its rules are `'role' => exists:roles,name` and `'phone' => unique:users,phone`. Both are checked against the tenant DB.
   - `CreateUserAction` then calls `syncRoles([$dto->role])` without filtering anything.
   - The only place `super_admin` is excluded is the role list in `UserController::index` (line 62), which is just a display filter.

3. **Logging in as that user stays in tenant context.** `ResolveApiTenancy` starts tenancy from the X-Tenant header or the host. `ApiTokenAuth` then resolves the user from the tenant DB.

4. **The gate lets that user through.** `Gate::before` in `AppServiceProvider.php:38` returns true if `hasRole('super_admin')` or the phone is in the hardcoded list. Nothing checks that tenancy is *not* initialized. So the `can:super_admin.access` middleware on the `/api/v1/super-admin/*` group (`api.php:196`) passes.

5. **`SuperAdminApiController` acts on the central DB.** It never checks tenancy state. It uses `Tenant::findOrFail($id)`, and stancl's `BaseTenant` uses `CentralConnection`, so lookups and writes go to the central `tenants` table.

That gives working cross-tenant reach through:
- `update-db-config` (overwrites another tenant's DB credentials)
- `toggle-status`
- `override-feature`
- `run-migrations` (runs `tenants:migrate` for any tenant)
- `app-versions` create/delete
- platform settings and units

**Two refinements:**

- **DELETE tenant is broken, not exploitable.** `app/Actions/Tenants/DeleteTenantAction.php:12-16` has a syntax error: all the `$tenant` variables are missing (`execute(Tenant ): void`, `->domains()->delete();`). Resolving the action would fatal, so `DELETE /super-admin/tenants/{id}` currently errors instead of deleting.
- **A plain `admin` cannot pass the route gate directly.** `Gate::before` returns false for `super_admin.*` abilities unless the user has the `super_admin` role or a backdoor phone. Some FormRequests do accept `hasRole('admin')` (`OverrideTenantFeatureRequest:13`, `ToggleTenantStatusRequest:13`, `UpdateTenantDatabaseConfigRequest:13`), but those `authorize()` checks only run after the route gate.

So the first step — assigning the role or the phone — is required, but it is trivial for any tenant admin.

**Hardcoded phones confirmed** in `AppServiceProvider.php:38` and `:49`, `OverrideTenantFeatureRequest.php:13` and `ToggleTenantStatusRequest.php:13`.

**Verdict:** this is a real, low-effort privilege escalation from a paying tenant to platform-wide control with central DB-credential tampering. Severity stays critical.

### 5. [HIGH] The main SPA POS sends split payments and additional expenses to POST /invoices, and the FormRequest/DTO silently drops them
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StoreSalesInvoiceRequest.php:39`
- **Effort:** M
- **Evidence:** resources/js/views/POS/PosView.vue:707-724 posts to /invoices with `expenses: additionalExpenses.value` and `payments: multiPayments.value`. StoreSalesInvoiceRequest::rules() validates only `additional_expenses` (an array with no child rules) and has no `payments` or `expenses` key, so $request->validated() excludes both. CreateInvoiceDTO (DTOs/Invoices/CreateInvoiceDTO.php) has no `payments` property at all. InvoiceService::confirmInvoice reads $data['payments'] (L225) and $data['additional_expenses'] (L130), so those branches never run from the SPA.
- **Impact:** Example: a cashier splits a 1,000 sale into 600 cash + 400 InstaPay. The server records one Payment of 1,000 with the single payment_method, so the treasury per-channel balances and the shift Z-report are wrong. Customer-paid shipping or service expenses added in the POS are lost: the screen total (PosView.vue:467 adds expenses) differs from the server net_total, and the receipt does not match the money collected.
- **Recommendation:** Add `payments`, `payments.*.method|amount` and `additional_expenses.*.*` rules to StoreSalesInvoiceRequest. Normalise `expenses` to `additional_expenses` in prepareForValidation, or rename the key in the SPA. Add a typed `payments` array to CreateInvoiceDTO and pass it through toArray(). Add feature tests that post the exact PosView payload and assert the Payment rows per method and the net_total.
- **Verification:** I checked this against the code and the finding is real.

**The request path:**
- The POS screen (PosView.vue, routed at router line 210) posts to `/invoices` through `api.js`, whose baseURL is `/api/v1`. The payload is built at PosView.vue:707-722 and includes `expenses: additionalExpenses.value` and `payments: multiPayments.value`.
- That request reaches routes/api.php:105, then `InvoiceController::store` (L141-149), which calls `CreateInvoiceDTO::fromArray($request->validated(), ...)`.

**Why both fields are lost:**
- `StoreSalesInvoiceRequest::rules()` (L38-57) has no rule for `payments` or `expenses`. It only has `additional_expenses`, which is a different key from the `expenses` key the SPA sends. So `validated()` leaves both out.
- `CreateInvoiceDTO` has no `payments` property. Its `toArray()` only passes on `additional_expenses`, which will always be empty here.
- `CreateSalesInvoiceAction` passes `$dto->toArray()` to `InvoiceService::confirmInvoice`. There:
  - The expenses branch (L130) gets an empty array, so nothing is added.
  - The split-payment branch (L225) never runs. The `elseif` at L240 creates one Payment for the full `paid_amount`, using the single `payment_method`.
- No middleware or provider renames or maps these keys (grep found nothing). No feature test covers this path.

**What this means for money:**
- A split payment is recorded as one payment on one channel, so the per-channel treasury balances and shift totals are wrong.
- Customer-paid expenses are dropped from the server's net_total. The screen total (PosView.vue:466-471) includes them, and in cash mode the SPA sends `paid_amount = cartNetTotal`, which includes them. So the paid amount sent is more than the server's net total, and the receipt does not match the money collected.

High severity is justified, because money records are silently wrong on the main POS screen.

### 6. [HIGH] Deleting a posted return soft-deletes it without reversing stock or balance; trash restore and force-delete also bypass reversal
- **Location:** `D:\projects\sroor\backend\app\Actions\Returns\DeleteReturnAction.php:16`
- **Effort:** M
- **Evidence:** DeleteReturnAction::execute only does ReturnDocument::findOrFail($id)->delete(). There is no transaction, no StockService call, no balance recompute and no status check. ReturnService::create (L41-94, L113-148) adds or deducts stock and updates customer/supplier balances when the return is created. RestoreTrashRecordAction and ForceDeleteTrashRecordAction (Actions/Trash/*) call ->restore() / ->forceDelete() on 'returns', 'items', 'stores' and the others with no re-validation. returns_items is cascadeOnDelete (tenant migration 2026_08_08_160008:28), so force-delete physically removes the lines.
- **Impact:** Example: a sales return of 5 kg adds +5 kg to stock and credits the customer. ReturnController@destroy then hides the document while the +5 kg stock and the StockMovement stay. Force-delete then hard-deletes the document lines and leaves orphan movements. Stock and customer balance can no longer be reconciled with the documents, and AGENTS.md rule 5 (no physical deletion of approved documents) is broken.
- **Recommendation:** Replace delete with CancelReturnAction. In one DB::transaction with lockForUpdate it reverses the stock movements and the balance and sets status=cancelled. Block force-delete for financial documents. Make restore re-apply effects through the service, or forbid it for posted documents.
- **Verification:** The finding holds up against the code. DeleteReturnAction.php:14-18 only runs ReturnDocument::findOrFail($id)->delete(). It has no transaction, no StockService call and no balance recompute. ReturnDocument (app/Models/ReturnDocument.php) uses SoftDeletes, has no deleting/deleted hooks, and I found no observer registered for it in app/. The returns table (tenant migration *160008*) has no status column, so every return is effectively posted at creation. The return paths in ReturnService call addStock (around L77) and deductStock (around L148) inside DB::transaction and update customer/supplier balances (L93-94, L163-170). Nothing reverses these on delete, restore or force-delete. RestoreTrashRecordAction and ForceDeleteTrashRecordAction call ->restore() and ->forceDelete() on 'returns' with no reversal or re-validation. return_items.return_id is cascadeOnDelete, so a force-delete physically removes the lines. The StockMovement rows linked through morphMany 'source' are left orphaned. There is also a silent drift: the supplier balance is rebuilt from ReturnDocument::sum, which skips soft-deleted rows. After a delete, the next recompute changes the balance while the stock effect stays, so the documents can no longer be reconciled with stock or balances. The only guards are permissions: returns.manage or the admin role on destroy (ReturnController.php:138-145, plus can:returns.manage in routes/tenant.php:203) and can:trash.access on the trash routes. These limit who can do it but do not protect data integrity. The legacy routes in routes/api.php:153 and 192-193 have no route-level can: middleware, but the controller checks the permission itself. This breaks the rule against hard-deleting approved documents and the rule that stock must be reversed. High severity is justified.

### 7. [HIGH] Invoice print routes in tenant.php are outside the auth group (anonymous access to any invoice)
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:60`
- **Effort:** M
- **Evidence:** tenant.php:60-74 defines /invoices/{id}/print, /print/thermal and /print/a4 as closures doing Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id). They sit before `Route::middleware('auth')->group` (L77), so only 'web' and the tenancy middleware apply. The SPA opens them with window.open (InvoicesView.vue:193, PosView.vue:801) because it uses bearer tokens, not a session.
- **Impact:** Anyone on the internet who knows a tenant subdomain can iterate /invoices/1..N/print and harvest customer names, phones, prices and totals, which leaks tenant PII and business data.
- **Recommendation:** Move printing behind auth. Either render the receipt client-side from the authenticated /api/v1/invoices/{id} response, or issue short-lived signed URLs (URL::temporarySignedRoute) that the API returns with the invoice. Move the closures into a thin InvoicePrintController.
- **Verification:** The finding holds up. In backend/routes/tenant.php, lines 60-74 register GET /invoices/{id}/print, /print/thermal and /print/a4 inside the outer group, which uses only the 'web', InitializeTenancyByDomain and PreventAccessFromCentralDomains middleware. They come before Route::middleware('auth')->group at L77, so login is never required. Each route is a closure that runs Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id). The closures do no authorization and no store filtering, and app/Models/Invoice.php has no global scope.

I looked for guards elsewhere and found none:
- bootstrap/app.php only appends StoreScope to the web group. StoreScope does nothing unless Auth::check() is true, so it blocks no one.
- TenancyServiceProvider::mapRoutes loads tenant.php with no extra middleware, and bootstrap/providers.php registers that provider.
- No policy or `can:` middleware applies to these routes.

The frontend confirms the routes are used this way. InvoicesView.vue:193 and :217 (bulk print) and PosView.vue:801 open them with window.open.

The views expose personal and financial data. print-a4.blade.php:207-218 prints the customer's name, phone and address. print-thermal.blade.php:95 prints the name and :178 the customer's current_balance.

IDs are sequential integers, so anyone who knows a tenant's domain can loop through them and collect every invoice. This also crosses the store isolation layer inside a tenant.

There is an aggravating factor. backend/routes/web.php:56-65 defines the same /print/thermal and /print/a4 closures with no auth at all. That is a second copy of the same problem, though on central hosts it would query the central database.

The impact is limited to one tenant per domain, and an attacker must know or guess the tenant subdomain, so this does not leak across tenants. Even so, unauthenticated mass exposure of customer contact details and balances justifies High.

### 8. [HIGH] No branch (store) authorisation on API endpoints: X-Store-Id / store_id is trusted; StoreAccess middleware is never attached
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\InvoiceController.php:40`
- **Effort:** L
- **Evidence:** `grep store.access|StoreAccess routes/*` returns nothing, so middleware/StoreAccess.php is unused. The controllers take the store from client input without checking the user's assignment: InvoiceController@index L40 (missing or 'all' means every store), ExpenseController@index L48, TreasuryController@summary L34 (falls back to store 1), ReportController::buildDTO L37 + ReportFilterDTO store_id, StoreController@stocks L195, ItemController@lowStock L274, PurchaseController@smartReorder L175, ReportController@itemCard L205. Only StoreController@switchStore (L229-241) checks assignment.
- **Impact:** A cashier restricted to Branch A with invoices.view or reports.view can send X-Store-Id: B or store_id=all and read Branch B's invoices, P&L, treasury and stock. A sellable multi-branch product promises per-branch isolation.
- **Recommendation:** Create one ResolveActiveStore middleware for the api group. It validates X-Store-Id against user->stores (admin bypass), resolves 'all' only for users with a cross-store permission, and binds the result into the container. Controllers and DTOs then read the resolved store instead of raw input. Add isolation tests per list endpoint.
- **Verification:** The finding holds up against the code. In bootstrap/app.php:28, StoreAccess is only registered as the alias 'store.access'. No route in routes/api.php, tenant.php or web.php uses that alias or the class. StoreScope is appended only to the 'web' group (bootstrap/app.php:19-21). Even there it only checks that the session store exists and is active. It never checks whether the user is assigned to that store. The /api/v1 group (routes/api.php:19,33) runs only ResolveApiTenancy and ApiTokenAuth. ApiTokenAuth.php:83-86 takes any numeric X-Store-Id and writes it into session('current_store_id') with no check against the user's stores. Controllers then use the client value directly: - InvoiceController.php:40-58: store_id or X-Store-Id; 'all' or a missing value gives no store filter, and the only gate is invoices.view / pos.access. - TreasuryController.php:34: header, then input, then session, then 1. - ReportController.php:37-43 + ReportFilterDTO.php:44-45: the store_id request param overrides the header and 'all' gives null. FilterReportRequest::authorize checks only reports.view, and store_id is validated as just 'nullable'. Same pattern in ExpenseController:48, ItemController:274, PurchaseController:175 and StoreController:195. Models have no store global scope; the only scope is app/Scopes/TenantScope.php, which is tenant-level, not branch-level. The one assignment check is StoreController@switchStore (~L229-241), which confirms that per-user store assignment is an intended concept. A non-admin user with invoices.view or reports.view can therefore read other branches' data. Limitations: the leak stays inside one tenant (DB-per-tenant holds), and it needs an authenticated user with the relevant view permission. That is insider, branch-level disclosure, not cross-tenant. I keep it high rather than lowering it for two reasons. First, the same unchecked header feeds write paths (StorePOSInvoiceRequest.php:23 and the Shift/Return/StockTransfer/Purchase controllers). Second, .claude/rules/multi-tenancy.md explicitly requires a 403 for unassigned stores.

### 9. [HIGH] Scheduled Telegram jobs and backup run in central context: tenants get no reports, and the central DB (with secrets) is sent to Telegram
- **Location:** `D:\projects\sroor\backend\routes\console.php:28`
- **Effort:** L
- **Evidence:** Schedule runs notify:daily-summary, notify:low-stock, notify:overdue-shifts and backup:telegram directly. None of these commands initializes tenancy or loops over tenants (SendDailyTelegramSummaryCommand.php:13 calls TelegramService directly). TelegramService::sendDailySummaryNotification (line 133) queries Invoice:: on the default (central) connection, where no invoices table exists (database/migrations has no invoices migration). DatabaseBackupService::createSqlGzBackup dumps `SHOW TABLES` of DB::connection(), which is central in the scheduler. That includes users, personal_access_tokens, api_token columns, telescope_entries and the tenants table. TenantProvisionerService stores tenancy_db_password in the tenant data. The dump is written as plaintext SQL to storage/app/backups and then sent to the Telegram chat from config. The caption says it is 'encrypted' (TelegramService.php:383).
- **Impact:** For a SaaS: (1) daily summary, low-stock and overdue-shift alerts fail every run with a missing-table QueryException, or report nothing, so tenants never get them. (2) No tenant database is ever backed up, but operators think backups exist. (3) Every night, an unencrypted dump with password hashes, bearer tokens and tenant DB credentials goes to a third-party chat.
- **Recommendation:** Run the notifications with tenancy()->runForMultiple() (or one queued job per tenant), and use each tenant's own Telegram settings. Move backups to a platform tool such as mysqldump per tenant DB, with encryption, stored on private storage and not in chat. Leave secret-bearing central tables out of anything sent outside.
- **Verification:** I confirmed this from the code. One part is overstated.

**The scheduled commands run in central context.**
- backend/routes/console.php:25-38 schedules notify:daily-summary, notify:low-stock, notify:overdue-shifts and backup:telegram as plain commands.
- There is no tenants:run, no tenant loop and no tenancy()->initialize anywhere in these commands or the providers. SendDailyTelegramSummaryCommand.php:13-18 and SendTelegramDatabaseBackupCommand.php:41-46 call TelegramService directly.

**The tenant alerts fail.**
- TelegramService::sendDailySummaryNotification (line 127+) runs Invoice::where(...), Expense::, CashShift:: and Store:: on the default connection, with no try/catch.
- The invoices and settings tables exist only in database/migrations/tenant (2026_08_08_160005, 2026_08_10_221000). No central migration creates them.
- So the daily summary throws a QueryException on every run. Low-stock and overdue-shift alerts have the same pattern (Item, StoreStock and CashShift queries, no tenant init).
- Tenant-level Telegram settings in each tenant's settings table are never read.

**The backup dumps the central database.**
- DatabaseBackupService::createSqlGzBackup uses DB::connection() and SHOW TABLES, so it dumps the central DB. That covers users, personal_access_tokens, users.api_token (migration 2026_08_15_160000), telescope_entries, tenants and impersonation tokens.
- No tenant database is ever backed up.
- The data is only gzipped. It is not encrypted, but the caption at TelegramService.php:383 claims it is.
- Setting::get swallows exceptions (Setting.php:35-43), so in central context getBotToken() and getDefaultChatId() fall back to config('services.telegram.*'), i.e. the TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID env values. When those are set, the central dump is uploaded to that chat every night.

**What is overstated.**
- The upload only happens if TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID are set in the central env.
- The chat belongs to the operator, not an arbitrary third party.
- TenantProvisionerService.php:51-52 stores tenancy_db_password in the tenant's data JSON only when a super-admin supplies custom DB credentials.
- personal_access_tokens holds SHA-256 hashes, not raw bearer tokens.

Even so, the result is no tenant backups or reports in a multi-tenant SaaS, plus a nightly plaintext export of central credential material (password hashes, the api_token column, possibly tenant DB passwords) to Telegram. High severity is justified.

### 10. [HIGH] Customer and supplier opening balance is erased on the next balance recalculation
- **Location:** `D:\projects\sroor\backend\app\Services\CustomerBalanceService.php:16`
- **Effort:** M
- **Evidence:** CreateCustomerAction.php:24 and CreateSupplierAction.php:24 write `'current_balance' => $dto->opening_balance`. There is no opening_balance column (grep of tenant migrations finds none) and no opening document. updateBalance recalculates current_balance = invoices - payments - returns (CustomerBalanceService lines 21-42, SupplierBalanceService lines 23-44, and the copy in ReturnService.php:164-170). None of them adds an opening amount.
- **Impact:** A customer is created with an opening debt of 1000.000. Then a 200.000 credit invoice is confirmed, and InvoiceService:255 calls updateBalance. current_balance becomes 200.000, so 1000.000 of receivables disappears without any trace. Suppliers have the same problem with payables.
- **Recommendation:** Store opening_balance in its own decimal(12,3) column, or as an opening-balance ledger document. Include it in every recalculation and in the ledger statement. Add a regression test: create with an opening balance, then invoice, then assert the balance.
- **Verification:** Confirmed in code, with one correction to how the bug is reached.

**What the backend does:**
- `CreateCustomerAction.php:24` and `CreateSupplierAction.php:24` store `$dto->opening_balance` straight into `current_balance`.
- `StoreCustomerRequest.php:23` validates `opening_balance` as nullable numeric.
- The DTOs (`CustomerDTO.php:26`, `SupplierDTO.php:26`) default it to '0.000'.
- No opening balance is kept anywhere else. The grep finds `opening_balance` only in `TreasuryService` (cash accounts) and in a `deposit_type` enum value for stock deposits.
- `CustomerBalanceService::updateBalance` (lines 16-44) rebuilds `current_balance` from scratch as confirmed invoices minus payments minus sales returns, then overwrites the stored value. `SupplierBalanceService::updateBalance` (lines 17-48) does the same for purchases.
- Nothing adds the opening amount back. The only observer is `TenantObserver`, so no observer or other guard does it either.
- Callers that trigger the overwrite: `InvoiceService.php` lines 255/316/575/578/649, `PaymentService.php` lines 64/120, `PurchaseService.php` lines 224/311/404/445, and `ReturnService.php:94`. The first invoice, payment, purchase or return erases the opening balance.
- The ledgers (`getCustomerLedger`, `getSupplierLedger`) also start the running balance at '0.000' and have no opening line. The statement never shows the amount, even before it is erased.

**Correction to the claimed path:**
The shipped Vue UI never sends `opening_balance` with a real value.
- `CustomerFormModal.vue:50-51` and `SupplierFormModal.vue:49-50` bind the input to `form.initial_balance`.
- `useCustomers.js` and `useSuppliers.js` keep `opening_balance: '0.000'` and post the whole form.
- The backend ignores `initial_balance` (grep finds it nowhere in `app/`).

So through the UI, the opening balance the user types is dropped at creation, before any recalculation happens. The exact "create with 1000 then lose it after one invoice" scenario only happens when a client calls the API with `opening_balance` directly.

**Why severity stays high:**
Every path ends the same way: customer and supplier opening balances cannot persist, and receivables/payables disappear with no trace in the ledger. That is high-impact for a SaaS where tenants need to bring in their existing balances when they start.

### 11. [HIGH] deductStock silently skips the store-level deduction when the store_stocks row is missing
- **Location:** `D:\projects\sroor\backend\app\Services\StockService.php:59`
- **Effort:** S
- **Evidence:** The store_stock row is fetched with lockForUpdate and the sufficiency check and the bcsub run only `if ($storeStock)`. The master item check (line 47) uses the global current_stock, which is the sum across all branches. The movement is still recorded against $storeId.
- **Impact:** An item has 10 kg in Branch B and no row in Branch A. A POS sale of 8 kg in Branch A passes the global check, so item.current_stock drops to 2, Branch A has no record and Branch B stays at 10. This oversells across branches, and SUM(store_stocks) no longer equals items.current_stock.
- **Recommendation:** When a store is resolved, treat a missing store_stock row as zero and throw an insufficient-stock error. Never fall back to the global balance for branch sales.
- **Verification:** I confirmed this in the code. In backend/app/Services/StockService.php, line 47 checks only the global Item.current_stock. Lines 59-62 then fetch the StoreStock row with lockForUpdate()->first(). The per-store sufficiency check (line 64) and the bcsub (lines 70-72) both run only inside `if ($storeStock)`. When the row is missing, the code still lowers items.current_stock (lines 77-80) and records a StockMovement with store_id = $storeId (lines 82-95). This contradicts hasAvailableStock() in the same file (lines 21-25), which returns false when the store row is missing.

I looked for guards that would prevent it and found none that work:
(1) CreateItemAction (lines 42-49) creates zero-quantity rows only for stores that exist when the item is created. CreateStoreAction does not backfill store_stocks for existing items. So any branch added after its items exist, which is the normal case when a SaaS tenant opens a new branch, has no rows and reaches this path.
(2) StorePOSInvoiceRequest and StoreSalesInvoiceRequest validate only `numeric|min:0.001` on quantity.
(3) ProcessPOSInvoiceAction and CreateSalesInvoiceAction run no stock check of their own.
(4) InvoiceService (lines 61-108 for create, line 404 for update) locks the item and calls deductStock directly.

The same path is reached from PurchaseService:286 and ReturnService:148. The claimed scenario is therefore reproducible: Branch A was created after the item and has no row, Branch B holds 10, and selling 8 in A passes the check. Afterwards items.current_stock is 2, SUM(store_stocks) is 10, and the movement is attributed to A, so stock oversells across branches.

The severity of high stands. It is a silent integrity break in stock that does not depend on unusual data, only on the normal workflow of adding a branch.

### 12. [HIGH] Shift closing: expected cash counts all branches' payments, there is no row lock, and Telegram HTTP runs inside the transaction
- **Location:** `D:\projects\sroor\backend\app\Services\ShiftService.php:102`
- **Effort:** M
- **Evidence:** Payment queries for customer receipts (line 102) and supplier cash payments (line 120) are not scoped by store. The payments table has no store_id column at all (tenant payments migration). Refunds count every sales_return, not only cash ones. CloseShiftAction:23 reads the open shift outside the transaction, and closeShift does not lock it or re-check its status. When there is a discrepancy, sendShiftDiscrepancyNotification makes a synchronous Http::timeout(10) call at line 199, inside DB::transaction.
- **Impact:** Branch A closes its shift while Branch B collected 5,000 in cash receipts. A's expected cash is inflated by 5,000, which produces a false deficit alert and a wrong recorded cash_difference. Two concurrent close requests both succeed and the second overwrites the totals. A slow Telegram call keeps the transaction open for up to 10s per chat.
- **Recommendation:** Add store_id (and shift_id) to payments, scope every cash flow to the shift, lockForUpdate the shift and re-check status='open' inside the transaction, and queue the Telegram alert after commit (DB::afterCommit or a queued job).
- **Verification:** I confirmed every part of the finding in the code, and the payments problem is worse than claimed.

1. **Receipts and supplier payments are not filtered by branch.** In ShiftService.php, the customer-receipt query (lines 102-105) and the supplier-payment query (lines 120-123) filter only on created_at, customer_id/supplier_id and payment_method = cash. The payments table has no store_id column. The tenant migration 2026_08_08_160007_create_payments_table.php does not create one. 2026_08_11_180002_add_store_id_to_existing_tables.php adds store_id only to invoices, purchases, expenses, returns, cash_shifts and stock_movements. The Payment model has no global scope. So cash payments from every branch are counted in every shift.

2. **Cash sales are counted twice (worse than the finding said).** When InvoiceService.php issues an invoice (lines 229-251), it creates a Payment row with customer_id = $customer->id and payment_method 'cash' by default. calculateShiftTotals already counts invoices.paid_amount as cash sales (lines 81-99). It then adds every cash Payment that has a customer_id (lines 102-111). So money paid on an invoice is counted twice in the same branch, as well as being counted again in other branches.

3. **Refunds include every sales return.** Lines 126-129 sum all sales_return rows. The returns table has no column for the refund method, so credit returns are subtracted as if cash left the drawer.

4. **No lock on the shift when closing.** CloseShiftAction.php line 23 loads the open shift outside any transaction. closeShift() (line 159) opens DB::transaction but never calls lockForUpdate() or re-checks the status. Two concurrent close requests can both pass and the second overwrites the totals.

5. **Telegram is called inside the transaction.** sendShiftDiscrepancyNotification is called inside the transaction at line 199. It calls TelegramService::sendMessage, which makes a synchronous Http::timeout(10) POST for each chat ID (TelegramService.php line 74). Nothing uses afterCommit or a queued job. This breaks the project rule "no external I/O inside transaction".

Severity stays high: the expected cash and the recorded cash_difference are wrong for every shift, and that sets off false shortage alerts.

### 13. [HIGH] The two P&L reports give different numbers, and the print/ABC version recalculates historical cost using today's item cost
- **Location:** `D:\projects\sroor\backend\app\Services\ProfitLossService.php:74`
- **Effort:** M
- **Evidence:** ProfitLossService::getProfitLossReport works out COGS per invoice line as `$item->item?->weighted_avg_cost ?: $item->item?->cost_price` times quantity. That is the item's CURRENT cost, even though invoice_items.cost_price (the historical cost) and invoices.total_cost exist. Returns are handled the same way (line 92). The API version (app/Actions/Reports/GetProfitLossReportAction.php:37) instead sums invoices.total_cost and does not subtract sales returns at all. ReportPrintController@printSalesReport uses invoices.total_cost too.
- **Impact:** Gross and net profit for a closed month change every time a purchase updates weighted_avg_cost. Example: sell 10 kg at cost 100, later buy at 150, and the September P&L now shows COGS 1500 instead of 1000. The API P&L and the printed P&L for the same period and store also disagree: one deducts returns and one does not. Owners of a paid SaaS will see two different profit figures.
- **Recommendation:** Use one P&L implementation. Base it on the historical invoice_items.cost_price (or invoices.total_cost), compute it in SQL (SUM(quantity*cost_price) grouped by store), and subtract return COGS from return_items. Add exact-decimal tests that change item cost after the sale and check that P&L does not move.
- **Verification:** I confirmed this in the code; it is not refuted.

1. **COGS uses today's cost.** `ProfitLossService.php:74` takes `$item->item?->weighted_avg_cost ?: cost_price` from the Item model, which is the cost now, not the cost at the time of sale. The return lines do the same at line 93.
2. **The historical cost is stored and ignored.** `InvoiceService.php:82-93` saves the cost at sale time in `invoice_items.cost_price` (the effective weighted average cost then) and adds it into `invoices.total_cost`. The P&L service never reads either column.
3. **The cost it reads keeps changing.** `PurchaseService.php:126` and `:359` overwrite `Item->weighted_avg_cost` on every purchase. So COGS for a closed period shifts every time a new purchase comes in. The only damper is a 15-minute cache (line 31).
4. **The two P&L versions disagree.**
   - API (`GetProfitLossReportAction.php:21-38`): sums `invoices.total_cost` and never subtracts sales returns (no `ReturnDocument` query at all).
   - Print (`ReportPrintController::printProfitLossReport`, lines 537-542): calls `ProfitLossService`, which subtracts returns and uses today's cost.
   - Same period and store, therefore two different revenue, COGS and profit figures.
   - `printSalesReport` (lines 62 and 97) and `printStoresReport` (line 226) use the historical `total_cost`, which adds further inconsistency.
5. **No guard covers it.** Nothing in observers, scopes or DB constraints compensates.

**Extra issues seen while checking (not part of the claim):**
- `clearCache()` forgets the key `erp_pnl_{id}`, but the report stores under `erp_pnl_{id}_{from}_{to}`. Invalidation never matches.
- The return query does not filter on return status.

**Severity:** This is a reporting-layer bug. Stored data is correct and the fix is to read `invoice_items.cost_price`. But it gives owners wrong and conflicting core profit figures in a paid accounting SaaS, so high stands. It could be argued down to medium only because nothing is corrupted.

### 14. [HIGH] The payments table has no store_id, so per-store treasury balances include other stores' customer and supplier payments
- **Location:** `D:\projects\sroor\backend\app\Services\TreasuryService.php:34`
- **Effort:** M
- **Evidence:** Migration 2026_08_11_180002_add_store_id_to_existing_tables only adds store_id to ['invoices','purchases','expenses','returns','cash_shifts','stock_movements']. Payments was never given one, and 2026_08_08_160007_create_payments_table has no store_id either. In TreasuryService::getBalances, Payment inflows (customer_id) and outflows (supplier_id, PAY-EXP) have no store filter, while Expense and TreasuryTransfer use `->when($storeId, fn($q) => $q->where('store_id', $storeId))`.
- **Impact:** For a tenant with 2 branches, branch A's cash/instapay balance includes payments collected and paid by branch B, but subtracts only branch A's expenses. Per-branch liquidity and shift reconciliation figures are wrong as soon as a tenant has more than one store.
- **Recommendation:** Add a nullable, indexed store_id (with an FK) to payments in a new tenant migration. Backfill it from invoice/purchase.store_id, set it in PaymentService, and apply the store filter in TreasuryService. Add a two-store isolation test.
- **Verification:** The finding holds. The tenant migration backend/database/migrations/tenant/2026_08_08_160007_create_payments_table.php creates `payments` with no store_id. A grep of the tenant migrations finds no later migration that adds store_id to payments; the only later ones change soft deletes and payment_method. The Payment model (backend/app/Models/Payment.php) has no store_id in $fillable, no global scope and no store scope.

In TreasuryService::getBalances (backend/app/Services/TreasuryService.php), three Payment sums run with no store filter:
- customer inflows at lines 34-37
- supplier outflows at lines 40-43
- PAY-EXP expense payments at lines 52-55

Expense (line 47), TreasuryTransfer (lines 61, 67, 73) and CashShift (line 85) are filtered with `->when($storeId, ...)`. getTreasuryReport has the same mismatch: lines 207-225 and 258-280 have no store filter on Payment, while Expense and transfers are filtered.

This is internally inconsistent. buildLedgerEntries (lines 384-387 and 413-416) does try to filter payments by store through invoice->store_id or purchase->store_id. So for a tenant with more than one branch, the ledger lines and the per-account totals on the same report disagree.

Callers that pass a store id and so get mixed per-branch figures:
- TreasuryController line 71: getBalances($storeId)
- GetSystemContextAction line 87
- GetTreasuryReportAction and ReportPrintController line 424: getTreasuryReport

There is also a money-control effect the finding did not mention. TreasuryService::transfer (lines 142-149) checks whether the source account has enough balance using getBalances(storeId). Because that balance includes other branches' payments, a branch can transfer money it does not have, or be wrongly blocked from a valid transfer.

One part of the claimed impact is overstated. Shift reconciliation does not use TreasuryService: ShiftService computes total_payments_collected itself (ShiftService.php lines 148 and 171). So the claim that shift reconciliation is wrong is not supported by this code path; only treasury balances, the treasury report and the transfer sufficiency check are affected.

Severity stays high because of the wrong per-branch liquidity figures plus the incorrect transfer guard, but the shift-reconciliation part of the impact should be dropped.

### 15. [HIGH] Invoice print routes are reachable without authentication (sequential-ID IDOR inside a tenant)
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:60`
- **Effort:** S
- **Evidence:** `/invoices/{id}/print`, `/invoices/{id}/print/thermal` and `/invoices/{id}/print/a4` are declared inside the tenancy group but BEFORE `Route::middleware('auth')->group(...)` at line 77. Each one does `Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id)` with no auth, policy or store check. `php artisan route:list --path=print` confirms they have only the web and tenancy middleware.
- **Impact:** Anyone who knows a tenant's domain can enumerate /invoices/1/print/a4, /2, ... and read every invoice: customer names and phones, prices, costs (if the view renders them) and balances. Any authenticated user can also read invoices from stores they are not assigned to.
- **Recommendation:** Move these routes into the auth group with `can:invoices.view`, plus a store-access policy check. If the cashier popup needs tokenless access, use signed URLs (URL::temporarySignedRoute).
- **Verification:** The finding holds. In D:\projects\sroor\backend\routes\tenant.php, lines 60-74 declare GET /invoices/{id}/print, /print/thermal and /print/a4. They sit inside a group whose only middleware is 'web', InitializeTenancyByDomain and PreventAccessFromCentralDomains (lines 20-24). They come before Route::middleware('auth')->group at line 77. Each closure runs Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id) with no auth, no policy or 'can:' middleware, and no store filter.

Nothing elsewhere guards these routes:
- The Invoice model (app/Models/Invoice.php) uses only HasFactory and SoftDeletes. It has no global scope.
- App\Scopes\TenantScope exists but nothing in app/ references it, and it would not check auth anyway.
- TenancyServiceProvider::mapRoutes (lines 115-122) registers tenant.php without adding any middleware.

Invoice IDs are sequential, and the views expose real data. print-a4.blade.php:209-212 renders the customer phone, and print-thermal.blade.php:168-178 renders the customer's current_balance (controlled by a setting that defaults to on). Customer names, line prices and totals are also rendered. No item cost was found in the grepped lines, so the "costs" part of the impact is unproven.

Isolation between tenants still holds, because the tenant comes from the domain. So the exposure is anonymous enumeration within one tenant, plus cross-store reads by any user, which ignores the X-Store-Id / StoreAccess layer. That is a real, easily exploited exposure of personal and financial data, and high is justified.

Related: routes/web.php:56-65 declares the same unauthenticated print routes, and the /daily-journal/print route at line 68 is unauthenticated too. On a tenant domain the tenant.php routes, registered later, probably replace them. Either way none of them requires auth.

### 16. [HIGH] Unauthenticated invoice print routes on tenant domains (IDOR / customer data exposure)
- **Location:** `D:/projects/sroor/backend/routes/tenant.php:60`
- **Effort:** S
- **Evidence:** /invoices/{id}/print, /invoices/{id}/print/thermal and /invoices/{id}/print/a4 sit outside the auth group (the auth group starts at line 77). The closure runs Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id) and renders the full invoice. route:list confirms the middleware is only web, PreventAccessFromCentralDomains and InitializeTenancyByDomain.
- **Impact:** Anyone who knows a tenant subdomain can iterate /invoices/1..N/print/a4 and pull every invoice: customer names and phones, prices, totals, payment status. For a sellable SaaS this is a confidentiality and privacy breach.
- **Recommendation:** Move the print routes inside the auth group with can:invoices.view and store-scope checks, or use signed, expiring URLs for popup printing. Move the closure logic into a controller and Action.
- **Verification:** The finding holds. In backend/routes/tenant.php, lines 60-74 define GET /invoices/{id}/print, /print/thermal and /print/a4 inside the tenant group, which only has the 'web', InitializeTenancyByDomain and PreventAccessFromCentralDomains middleware. They come before the Route::middleware('auth') group that starts at line 77, and none of them has its own auth, can: or signed middleware. TenancyServiceProvider::mapRoutes (lines 117-123) loads this file, so the routes are live. Each closure calls Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id) with no ownership or store check. app/Models/Invoice.php has no global scope (no booted() and no addGlobalScope, only HasFactory and SoftDeletes), so nothing filters by user or store. Invoice IDs are sequential integers, so they are easy to enumerate. resources/views/layouts/print-a4.blade.php lines 204-218 print the customer's name, phone and address, along with line items and totals. The SPA opens these URLs directly (InvoicesView.vue:193 uses window.open(`/invoices/${id}/print`)), which suggests auth was left off on purpose so the popup works, not that a guard exists somewhere else. The tenant database is chosen by domain, so the exposure stays inside one tenant, but anyone on the internet who knows a tenant's subdomain can read all of that tenant's invoices and customer PII. Within a tenant, the per-store isolation rule is also bypassed. Related: routes/web.php lines 56-65 repeat the same unauthenticated print routes, and lines 67+ have an unauthenticated /daily-journal/print on the central domain, which is not inside any auth group. There tenancy is not initialized, so the impact depends on the central DB schema. High severity is justified for a multi-tenant SaaS.

### 17. [HIGH] Plan features, including blender.access, are never enforced anywhere
- **Location:** `D:\projects\sroor\backend\app\Services\TenantFeatureManager.php:13`
- **Effort:** M
- **Evidence:** isFeatureEnabled() and resolveAllFeatures() have no callers in app/, routes/ or bootstrap/. The only consumer is OverrideTenantFeatureAction, which toggles the override. PlansAndFeaturesSeeder.php:25/80/121 sets 'blender.access' => false on the Free and Basic plans, yet routes/api.php:162-163 and routes/tenant.php:216-217 expose /coffee-blender with no feature middleware. The SPA route resources/js/router/index.js:258-265 only checks permission 'items.create', and modules.json:98 hardcodes enabled:true.
- **Impact:** Each tenant gets every module (blender, transfers, purchases, treasury, reports.advanced, API access) whatever plan it pays for. Plan tiers therefore can't be used as a sales tool, and the super-admin 'override feature' toggle has no effect.
- **Recommendation:** Add a 'feature:{key}' route middleware that resolves tenant() and calls TenantFeatureManagerInterface::isFeatureEnabled. Apply it to every module route group, starting with blender.access. Expose resolveAllFeatures to the SPA so the router and menu hide disabled modules. Add tests that a Free-plan tenant gets 403 on /coffee-blender.
- **Verification:** I confirmed this from the code. TenantFeatureManager::isFeatureEnabled() and resolveAllFeatures() (backend/app/Services/TenantFeatureManager.php:13,51) have no callers. The only consumer of the service is OverrideTenantFeatureAction, which calls toggleFeatureOverride. The same unused pattern exists in two other places:
- In Tenant.php, hasFeature() (line 67), getFeatureLimit(), checkLimit() (line 103) and getAllFeatures() are never called. Plan::hasFeature (Plan.php:56) is never called either.
- So plan limits (limits.users, limits.stores, limits.items) are not enforced either.

No guard exists anywhere else:
- bootstrap/app.php registers only the spatie role/permission aliases plus store.scope and store.access. There is no feature or plan middleware.
- app/Http/Middleware contains only ApiTokenAuth, ResolveApiTenancy, StoreAccess and StoreScope.
- CoffeeBlenderController has no plan or feature check.

The routes match the claim:
- routes/api.php:162-163 has no middleware at all on the blender routes.
- routes/tenant.php:216-217 has only can:items.create.

On the frontend:
- resources/js/Components/FeatureGate.vue exists but no file references it.
- The router entry for /coffee-blender (index.js:258-265) checks only permission items.create.
- modules.json:98 has enabled:true hardcoded.

The seeder sets blender.access to false for the lower plans (PlansAndFeaturesSeeder.php:80,121). The super-admin override endpoint (api.php:204) only writes enabled_features and nothing reads it for enforcement. The impact is overstated in one way: tenants still need the matching spatie permission, so access is not open to everyone. But plan tiers, plan limits and overrides have no effect at all. In a SaaS sold by plan, that bypasses monetization, so high severity stands.

### 18. [HIGH] tenant:populate-realistic-data wipes all operational data unconditionally; default tenant is '2m' with a production domain
- **Location:** `D:\projects\sroor\backend\app\Console\Commands\PopulateRealisticTenantDataCommand.php:120`
- **Effort:** S
- **Evidence:** The signature is `{tenant=2m} {--fresh}`, but option('fresh') is never read. Lines 120-134 always truncate invoices, payments, purchases, stock_movements, store_stocks, items, customers, suppliers and activity_logs with FK checks disabled. There is no app()->environment() guard or confirm(). The tenant lookup also matches domain LIKE '{arg}.%'. The auto-create branch registers '2m.baraa-solutions.com'.
- **Impact:** Running `php artisan tenant:populate-realistic-data` on the production host with no arguments irreversibly destroys the books of the tenant whose slug or domain starts with '2m' (or of any tenant slug passed). This contradicts the no-hard-delete rule.
- **Recommendation:** Abort in production unless an explicit --force-production flag is given. Require --fresh plus an interactive confirm before any truncate. Remove the default tenant argument. Move the command to a dev-only service provider.
- **Verification:** I confirmed this from the code. In backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php, line 39 sets the default tenant argument to `{tenant=2m}`. Line 40 declares `--fresh`, but nothing in the file ever reads option('fresh'); grepping for "fresh" finds only the signature and an unrelated `$c->refresh()`. There is no app()->environment() guard and no confirm() call; the only "confirm" match is invoiceService->confirmInvoice.

The tenant is looked up at lines 56-62 by id, by slug, or by a domain equal to the argument or LIKE "{arg}.%". If none matches, lines 66-88 create the tenant and register the domains '2m.baraa-solutions.com' and '2m.localhost'. config/tenancy.php lists baraa-solutions.com as the central domain, so this is the production domain.

Lines 112-134 always turn off FK checks and truncate these tables: invoice_items, payments, additional_expenses, invoices, purchase_items, purchases, stock_movements, expenses, cash_shifts, store_stocks, items, categories, customers, suppliers and activity_logs. TRUNCATE skips soft deletes, so this is a hard delete of approved documents, which breaks the no-hard-delete rule.

It is worse than claimed: the repo-root deploy_root_baraa.py:129 runs `artisan tenant:populate-realistic-data 2m` against production as part of its deploy steps. So every run of that deploy script wipes and reseeds the 2m tenant's books, not only an accidental manual run. The same command also resets admin and cashier users to Hash::make('password') at lines 144-175.

The impact and severity hold, so it stays high.

### 19. [MEDIUM] /pos/checkout drops the invoice discount: the request validates discount_amount but the DTO reads discount_value/discount_type (and types money as float)
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StorePOSInvoiceRequest.php:69`
- **Effort:** S
- **Evidence:** The rules contain 'discount_amount' but neither 'discount_type' nor 'discount_value'. They validate items.*.discount, which POSInvoiceItemDTO ignores. POSInvoiceDTO::fromArray($request->validated()) reads $data['discount_value'] ?? 0 and $data['discount_type'] ?? 'fixed', so both are always the defaults. POSInvoiceDTO.php:17-18 and POSInvoiceItemDTO.php:9-10 declare discountValue, paidAmount, quantity and unitPrice as `float` and cast with (float), then toArray() turns them back into strings for bcmath. PosApiTest only checks out without a discount. POSSolidArchitectureTest builds the DTO directly, which bypasses the FormRequest, so the gap is untested. The endpoint is still called by PosView.legacy.grid.vue:1399 and by POSController@store (tenant.php:84).
- **Impact:** Example: a 10% discount on a POS checkout through /pos/checkout or /pos/invoices is saved as 0, so the customer is charged the full price or the cash drawer does not reconcile. Float DTOs break the DECIMAL+bcmath rule. A float stringified in exponent form (e.g. 1.0E-5) makes bcmath throw ValueError.
- **Recommendation:** Validate discount_type (in:fixed,percentage) and discount_value (numeric|min:0|decimal:0,3). Change both POS DTOs to string money props built from (string) input. Add a FormRequest-level test with a discount. Better: retire /pos/checkout and POSController@store and keep one checkout path (see the duplicate-controllers finding).
- **Verification:** The code defect is real, but fewer clients reach it than the finding claims.

Confirmed in code:
- StorePOSInvoiceRequest::rules() (lines 62-86) validates `discount_amount` (line 70) and `items.*.discount` (line 84). It has no `discount_type`, `discount_value` or `additional_expenses` rule.
- `$request->validated()` therefore never contains discount_type or discount_value. POSInvoiceDTO::fromArray (backend/app/DTOs/POSInvoiceDTO.php) always falls back to 'fixed' and 0.
- POSInvoiceItemDTO keeps only item_id, quantity and unit_price, so any per-line discount is dropped too.
- ProcessPOSInvoiceAction passes dto->toArray() to InvoiceService::confirmInvoice. That method reads `discount_type` and `discount_value` (InvoiceService.php ~lines 114-122 and 418-425), which arrive as defaults, so the invoice discount is always 0.
- The same gap drops `additional_expenses`: the legacy client sends that key, but only `expenses` is validated.
- Both DTOs type money and quantities as `float` with (float) casts and stringify them back in toArray(). This breaks the DECIMAL+bcmath rule, and the exponent-form risk is real.
- Both entry points use this path: Api\PosController::checkout (api.php:110) and POSController::store (tenant.php:84).

Why the severity is lower:
- The routed POS screen is PosView.vue (router/index.js:210), and it submits via `api.post('/invoices', payload)` at line 724, not /pos/checkout.
- PosView.legacy.grid.vue, the only file posting to /pos/checkout (line 1399), is not referenced by the router.
- posService.js (posts to /pos/invoices) is not imported anywhere in resources/js.
- No desktop, e2e or Android source calls these endpoints; only backend tests do.

So the discount is silently lost only for direct API callers or if the legacy view is revived. The current SPA checkout does not go through this path. It is a latent but live API defect plus a rule violation (float DTOs), not an active production loss for the shipped POS screen.

### 20. [MEDIUM] Customer receipt can settle another customer's invoice: invoice_id is not bound to customer_id
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\PaymentController.php:87`
- **Effort:** S
- **Evidence:** PaymentController@customerReceipt passes $request->validated() straight to PaymentService::recordCustomerPayment, with no DTO or Action. StoreCustomerPaymentReceiptRequest:23 has 'invoice_id' => nullable|integer|exists:invoices,id and no check that the invoice belongs to customer_id or is not cancelled. PaymentService.php:31-48 locks that invoice and raises its paid_amount and remaining_amount, then creates the Payment under customer A and recomputes only A's balance (L64).
- **Impact:** A receipt for customer A with invoice_id of customer B's invoice marks B's invoice paid. The cash is booked to A, A's balance drops, and B's balance goes stale. Overpayment beyond remaining_amount is also not rejected, and payment against a cancelled invoice is allowed.
- **Recommendation:** Add a Rule::exists('invoices','id')->where('customer_id', $this->customer_id)->where('status','confirmed') rule plus an amount <= remaining check inside the locked transaction. Route this through a CollectCustomerPaymentDTO/Action (one already exists) instead of a raw array.
- **Verification:** The core finding holds, but the impact is overstated.

What the code shows:
- **Request rule:** StoreCustomerPaymentReceiptRequest.php:23 only checks `'invoice_id' => nullable|integer|exists:invoices,id`. Nothing ties the invoice to customer_id, checks its status, or caps the amount.
- **Controller:** PaymentController.php:87-92 passes `$request->validated()` straight to PaymentService::recordCustomerPayment. There is no DTO or Action.
- **Service:** PaymentService.php:33 loads the invoice with only `Invoice::where('id', $invoiceId)->lockForUpdate()->firstOrFail()`. It does not filter by customer_id or status. Lines 34-48 add the amount to paid_amount and set remaining_amount and payment_status to 'paid' or 'partially_paid'. Lines 51-64 create the Payment under customer A and recompute only A's balance.
- **No other guard:** the route (routes/api.php:116) has no extra middleware or policy. The Invoice model has only SoftDeletes and no global scope. There is no Invoice or Payment observer; only TenantObserver exists.

So a receipt for customer A can mark customer B's invoice paid or partially paid. paid_amount can also go above net_total, because only remaining_amount is clamped to 0 (lines 37-38). A cancelled invoice can still receive a payment.

Where the claim is overstated:
- **B's balance does not go stale.** CustomerBalanceService::updateBalance computes the balance as the sum of confirmed invoice net_total, minus payments by customer_id, minus returns. It never reads paid_amount or remaining_amount. B's balance and ledger stay correct, and A's balance correctly drops by the cash A paid.
- **The damage is at invoice level:** B's invoice shows the wrong payment_status, paid_amount and remaining_amount. Anything that reads those fields is affected, such as outstanding or receivables lists by invoice, and invoice status in the UI. This is a real cross-record integrity bug, but customer and treasury balances are not corrupted.
- **Who can trigger it:** only an authenticated tenant user with admin, customers.manage or daily_journal.view, inside the same tenant. It is not a cross-tenant issue.

Related weakness: the authorize() check lets a user with only the read permission daily_journal.view create receipts.

Verdict: real, but medium rather than high.

### 21. [MEDIUM] web.php print/export routes and ExportController have no auth and no tenancy initialisation
- **Location:** `D:\projects\sroor\backend\routes\web.php:278`
- **Effort:** M
- **Evidence:** web.php has no middleware group. Routes /items/{id}/export-movements-csv, /customers/{id}/export-csv, /suppliers/{id}/export-csv, /items/export-csv, /activity-logs/export-csv (L278-282), /invoices/{id}/print/*, /daily-journal/print, /items/{id}/movements/print and /reports/print (L56-252) run with only the 'web' and StoreScope middleware. ExportController (all 4 methods) does Customer/Supplier/Item::findOrFail($id) with no permission check. bootstrap/app.php only appends StoreScope to 'web'.
- **Impact:** On a host where these routes resolve, anonymous users can download customer and supplier statements and full inventory valuation as CSV. If tenancy is not initialised they query the central default connection. Depending on what the production central DB contains (the repo has restore_sroor_db.py), this is either a 500 or a data leak.
- **Recommendation:** Delete the duplicated web.php business routes, which overlap tenant.php. Move the exports under the authenticated API (/api/v1/.../export) with permission checks and tenancy, returning streamed downloads. Keep web.php for the SPA shell, manifest and sw.js only.
- **Verification:** The core claim holds but is overstated. Some routes it lists are overridden, and the data-leak scenario depends on how production is deployed.

Confirmed in code:
- backend/routes/web.php has no auth middleware anywhere.
- backend/bootstrap/app.php only appends StoreScope to the 'web' group. StoreScope (app/Http/Middleware/StoreScope.php) does nothing when Auth::check() is false.
- No global tenancy middleware exists. InitializeTenancyByDomain is applied only inside the group in routes/tenant.php.
- ExportController (app/Http/Controllers/ExportController.php) runs Customer::findOrFail, Supplier::findOrFail, Item::withTrashed()->findOrFail and exportInventory with no permission or authorize check.
- ExportService has no auth checks.

Partly wrong:
- tenant.php is loaded in $app->booted() (app/Providers/TenancyServiceProvider.php:117-124), after web.php. RouteCollection replaces a route registered earlier with the same method and URI.
- So these web.php routes are replaced by the tenant.php versions, which do run under tenancy: /invoices/{id}/print/thermal, /invoices/{id}/print/a4, /daily-journal/print (tenant.php:97, inside auth) and /activity-logs/export-csv (tenant.php:228, auth plus can:logs.view).
- Six routes really run with no auth and no tenancy:
  - the four ExportController routes (web.php:278-281)
  - /items/{id}/movements/print
  - /reports/print
- The Vue SPA does not reference any of these six URLs, so they are leftover endpoints that can still be reached.

Impact:
- Without tenancy, queries use the central connection (config/tenancy.php:45). The central migrations in database/migrations contain only users, plans, tenants, subscriptions, domains, permissions, activity_logs, telescope, pulse and app_versions. There are no customers, suppliers or items tables.
- On a correctly built central DB these routes therefore return a 500, not data.
- A real anonymous leak needs a central DB that still holds legacy single-tenant tables, which the code cannot confirm. The 'high' rating assumes that case, so I lowered it to medium.

Related issue that supports keeping this open: tenant.php:60-75 defines /invoices/{id}/print, /print/thermal and /print/a4 inside the tenancy group but before the auth group. Anyone without a login can print any tenant invoice by guessing its sequential id on a tenant domain.

### 22. [MEDIUM] Manual stock adjustment (the active path) duplicates StockService and allows negative stock
- **Location:** `D:\projects\sroor\backend\app\Actions\Items\AdjustItemStockAction.php:38`
- **Effort:** M
- **Evidence:** `$newStoreQty = bcsub($currentStoreQty, $adjustQty, 3);` has no bccomp check before saving. The request allows waste_out/stock_adjustment_out with quantity min:0.001 and no upper bound. ItemController:223 uses this Action. The guarded version, StockService::adjustStock (line 238, which rejects negative stock), and StockService::depositStock are never called. stock_before/stock_after here are store-level quantities, while StockService records item-level current_stock. The document number is `count()+1` per day, which gives duplicates under concurrency.
- **Impact:** A store has 5.000 kg and a user records waste_out of 50 kg. Store stock becomes -45.000 and item current_stock goes negative. Later sales checks pass against corrupted totals. The stock card mixes two meanings of stock_before/after, so the item ledger cannot be reconciled.
- **Recommendation:** Send all adjustments through one StockService method that locks and rejects negative results. Delete the duplicate Action logic and the unused service methods, and agree on one meaning for stock_before/after (store-level).
- **Verification:** I confirmed this from the code, but the impact is overstated, so I lowered it from high to medium.

**What the code shows**
- **No negative-stock guard.** In `backend/app/Actions/Items/AdjustItemStockAction.php`, line 38 runs `bcsub($currentStoreQty, $adjustQty, 3)`. The result is saved straight to `StoreStock` at lines 61-62, and `item.current_stock` is set to the sum of store quantities at lines 65-67. There is no `bccomp` check anywhere in between.
- **The request does not block it.** `backend/app/Http/Requests/AdjustStockRequest.php` accepts `waste_out` and `stock_adjustment_out` with `quantity` set to `numeric|min:0.001` and no upper bound.
- **The database does not block it.** `store_stocks.quantity` is a plain `decimal(12,3)` with no unsigned flag or check constraint (`tenant/2026_08_11_180000`). No observer guards it either.
- **This is the live path.** `ItemController::adjustStock` (line 218) calls only this Action. The route is `routes/api.php:93`, and the UI calls it from `ItemsView.vue:243`.
- **The guarded versions are unused in app code.** `StockService::adjustStock` (line 238, which throws when stock would go negative) and `StockService::depositStock` are called only from tests (`StockAdjustmentFeatureTest`, `FractionalWeightSaleTest`). This breaks the rule against duplicating stock math inside an Action.
- **The two paths record different numbers.** This Action records store-level `stock_before`/`stock_after`. `StockService` records item-level `current_stock` for the same fields.
- **The document number can repeat.** It is built from a per-day `count()+1`. Nothing locks it and the column has no unique index (`document_number` is a nullable string), so two concurrent adjustments can get the same number.

**Why the impact is overstated**
- **Only privileged users can trigger it.** `authorize()` requires the admin role or the `items.manage` permission.
- **It does not unlock later sales.** A negative store quantity makes sales stock checks fail, not pass. The "later sales checks pass" part of the claim does not follow.
- **It can be undone.** Another adjustment fixes the numbers. Nothing financial is posted directly.

**A related issue not in the original finding.** The frontend sends `store_id` as the item's first `store_stocks` entry, or `1` if there is none, instead of the active store. Combined with the missing guard, a user can make a store's stock negative without meaning to.

It remains a real breach of the stock-integrity rules (no negative stock, reuse `StockService`, lock document numbers), so I kept it as a valid finding at medium.

### 23. [MEDIUM] web.php export and report-print routes have no auth and no tenancy initialization
- **Location:** `D:\projects\sroor\backend\routes\web.php:278`
- **Effort:** S
- **Evidence:** Lines 278-281 register ExportController (customers/{id}/export-csv, suppliers/{id}/export-csv, items/export-csv, items/{id}/export-movements-csv), and lines 130 and 180 register `/items/{id}/movements/print` and `/reports/print` closures. All are top-level in web.php with no middleware group. route:list shows only `web` middleware: no Authenticate, no InitializeTenancyByDomain. No equivalent routes exist in tenant.php or api.php.
- **Impact:** These routes are unauthenticated and run against the central connection (tenancy is never initialized). If the central database still holds legacy single-tenant data (the repo has restore_sroor_db.py scripts), anyone could download customer statements and inventory valuation without logging in. Otherwise the routes return 500 and the export feature is silently broken for tenants. In both cases tenant data exports do not work.
- **Recommendation:** Move the export and report-print routes into the tenant.php auth group (or api v1 with ApiTokenAuth), with permission middleware and store scoping. Remove them from web.php.
- **Verification:** I confirmed this in the code, but the impact is overstated.

What is true:
- backend/routes/web.php:278-281 registers four ExportController routes with no middleware: items/{id}/export-movements-csv, customers/{id}/export-csv, suppliers/{id}/export-csv and items/export-csv.
- web.php:282 does the same for activity-logs/export-csv, which the finding does not list.
- web.php:130 (/items/{id}/movements/print) and web.php:180 (/reports/print) are bare closures at the top level of the file.
- web.php is loaded through bootstrap/app.php withRouting(web:), so these routes only get the `web` group. The only middleware this project appends to that group is StoreScope, which checks Auth::check() but never blocks a request.
- None of the following add a check: ExportController (backend/app/Http/Controllers/ExportController.php) just calls findOrFail and ExportService, with no authorize or permission call; no policy covers these routes; no route-level `can:` is set.
- Tenancy is not initialized. InitializeTenancyByDomain is applied only inside routes/tenant.php. TenancyServiceProvider::mapRoutes registers tenant.php in a booted() callback, after web.php, so web.php routes with the same URI are matched first, even on a tenant domain.
- tenant.php has no equivalent export or movements/print routes. It only has reports/export-abc and activity-logs/export-csv, both behind auth + can:.
- So these routes run unauthenticated on the central connection (tenancy.central_connection = DB_CONNECTION).

Why I lowered the severity:
1. The central migrations (backend/database/migrations, 18 files outside tenant/) only create users, plans, tenants, subscriptions, domains, permissions, activity_logs, pulse, telescope and app_versions. They create no customers, suppliers, items or stock_movements tables. On a schema built from these migrations, the four ExportController routes and the movements/reports print routes would hit missing tables and return 500 instead of leaking data. Leaking data needs a central DB that still holds legacy single-tenant tables, which I cannot confirm from code.
   - Exception: activity-logs/export-csv (web.php:282) is also unguarded, and activity_logs is a central table. That route may expose real central data and deserves its own check.
2. The SPA (backend/resources/js) never calls any of these URLs; a grep finds no references to export-csv, movements/print or reports/print. These are leftover legacy routes, not a user-facing export feature that is "silently broken".

The underlying problem is real and is a pattern across web.php: unauthenticated, tenant-unaware routes registered ahead of tenant.php. The same applies to /invoices/{id}/print/thermal and /a4 at web.php:56-64, and to /daily-journal/print. Fix by deleting these routes, or by moving them into tenant.php under auth + can:.

### 24. [MEDIUM] ApiTokenAuth silently maps a central 'admin' token onto any tenant user with the same phone (unaudited cross-tenant impersonation); tokens also accepted from the query string and stored in plaintext
- **Location:** `D:/projects/sroor/backend/app/Http/Middleware/ApiTokenAuth.php:50`
- **Effort:** M
- **Evidence:** Fallback 3: if no tenant user matches the token, it resolves the token in the central DB, and if the central user hasRole('admin') it logs in as User::where('phone', $centralUser->phone) in the current tenant (the tenant is chosen by the caller's X-Tenant header). Line 21 accepts the token from bearer, the X-API-TOKEN header or ?api_token=. ApiLoginAction:89 and ApiQuickLoginAction:59 copy the plaintext Sanctum token into users.api_token, and fallback 2 authenticates by plain column equality, which skips Sanctum hashing and expiry.
- **Impact:** Any central account with the 'admin' role can act as an arbitrary tenant's user in any tenant just by setting a header, with no impersonation record. A DB read or backup leak (backups are sent to Telegram nightly) exposes live bearer tokens directly. Tokens in URLs end up in access logs and Referer headers.
- **Recommendation:** Drop fallback 3 and use the existing stancl UserImpersonation tokens with audit logging. Remove the api_token column auth path and the query-string token. Rely on hashed Sanctum PATs with expiration. Replace the custom middleware with auth:sanctum.
- **Verification:** The mechanics are in the code, but the impersonation part is overstated.

What the code confirms:
- **Fallback 3 exists.** `backend/app/Http/Middleware/ApiTokenAuth.php:50-62` runs when tenant lookups fail. It resolves the token in the central DB (Sanctum `findToken` or the plain `api_token` column). If the central user `hasRole('admin')`, it authenticates as `User::where('phone', $centralUser->phone)` in the current tenant.
- **The caller picks the tenant.** `ResolveApiTenancy.php:30-38` takes it from the `X-Tenant` header, `?tenant` or a body param.
- **Tokens are read from three places.** Line 21 accepts bearer, the `X-API-TOKEN` header or `?api_token=`.
- **Plaintext tokens are stored.** `ApiLoginAction.php:89` and `ApiQuickLoginAction.php:59` copy the plaintext Sanctum token into `users.api_token`. The tenant and central migrations define it as `string(80)`, unique, not hashed.
- **Fallback 2 skips Sanctum.** It authenticates by plain column equality (lines 44-47).
- **Telescope has the same pattern.** `routes/web.php:17-28` and `TelescopeServiceProvider.php:63-69` also look up `?token=` against plaintext `api_token`.
- **No audit record.** Nothing in this path writes an audit or impersonation log.

Why it is overstated:
1. **Not "any tenant user".** The match is by phone, and tenant `users.phone` is unique. In practice only the central admin's own mirrored account is reached, or a tenant user who happens to share that phone.
2. **Only platform super-admins qualify.** The central `admin` role is held by the seeded platform super-admins (`DatabaseSeeder.php:17-42`), who hold both `super_admin` and `admin`. I found no central signup that grants `admin`.
3. **It is a designed backdoor, not a new hole.** `ApiLoginAction.php:36-59` and `LoginAction.php:41-64` already let a central admin's password create or sync an admin user in any tenant via `firstOrCreate` + `syncRoles(admin)`. The middleware fallback only extends that to tokens.

What remains is still a real issue:
- Unaudited operator access across tenants, which goes against multi-tenancy rule 3.
- Plaintext reusable bearer tokens stored in the DB, so a DB or backup leak exposes live tokens.
- Tokens accepted in URLs, which can end up in logs.

Two smaller corrections:
- **"Skips expiry"** is mostly moot. I found no `config/sanctum.php`, so Sanctum's default of no expiry applies anyway.
- **Revocation does leak.** Logout clears the column, but any other Sanctum token revocation would leave the plaintext column token working as long as `is_active` is true.

On balance this is medium, not high: the cross-tenant reach requires an already-privileged platform super-admin account and is limited to the phone-matched user.

### 25. [MEDIUM] web.php exposes unauthenticated, tenancy-less export/print/session routes
- **Location:** `D:/projects/sroor/backend/routes/web.php:278`
- **Effort:** M
- **Evidence:** route:list shows these routes with only the 'web' middleware (no auth, no InitializeTenancyByDomain): customers/{id}/export-csv, suppliers/{id}/export-csv, items/export-csv, items/{id}/export-movements-csv (ExportController has no auth checks), items/{id}/movements/print (web.php:130), reports/print (web.php:180), store/switch (web.php:258, which writes an arbitrary store_id into the session) and logout. Copies of daily-journal/print, invoices/*/print and stores/switch in web.php are shadowed by the tenant.php versions, so they are dead duplicates.
- **Impact:** On any domain these routes query the default (central) connection without authentication. If the central DB still holds legacy single-tenant tables (the root restore_sroor_db.py suggests it might), customer and supplier statements and full inventory are downloadable by anyone. Otherwise the features return 500s. In both cases tenant-aware CSV export is broken for real users. /store/switch lets anyone set an unvalidated store_id in the session.
- **Recommendation:** Delete the duplicate closures. Move exports and prints into the tenant.php auth group (or the API with ApiTokenAuth) with permission and store-scope checks. Remove /store/switch and keep the validated tenant.store.switch / API switch.
- **Verification:** The routes are real, but the impact is overstated.

What the code confirms:
- backend/bootstrap/app.php:10 loads routes/web.php with only the 'web' group, plus StoreScope appended (line 19).
- web.php has no auth middleware and no InitializeTenancyByDomain anywhere.
- These routes are reachable without auth or tenancy:
  - the CSV exports at web.php:278-281 (ExportController, no checks)
  - /items/{id}/movements/print at :130
  - /reports/print at :180
  - /store/switch at :258, which writes the raw request store_id into the session with no validation
  - /logout at :270
- Tenancy is only set up in routes/tenant.php:20-24, which TenancyServiceProvider::mapRoutes loads in booted(), after web.php. So the web.php copies of /invoices/{id}/print/thermal and /a4, /daily-journal/print and /stores/switch use the same URIs and get overwritten by the tenant.php versions. That confirms they are dead duplicates.

Why the impact is lower than claimed:
1. The leak scenario is not supported by the code. The central migrations (backend/database/migrations: users, cache, jobs, plans, tenants, subscriptions, domains, tokens, permissions, activity_logs, pulse, telescope, impersonation tokens, app_versions) create no customers, suppliers, items or stock_movements tables. On a clean central DB these routes return 500s, not data. The legacy-data theory rests only on restore_sroor_db.py, which restores some MySQL DB whose role is not provable from code. Side note: that file has a plaintext database password at restore_sroor_db.py:11 (DB credential, value not repeated here).
2. "Tenant-aware CSV export is broken for real users" is misleading. No file in resources/js references export-csv, export-movements, movements/print, reports/print or /store/switch. These are orphaned routes, not a feature users rely on.
3. /store/switch is a POST in the web group, so CSRF protection blocks cross-site use. It can only change the caller's own session. Session current_store_id is no more trusted than the client-controlled X-Store-Id header, which ItemController:274, TreasuryController:34 and ApiTokenAuth:85 already accept.

Net result: dead, unauthenticated, tenancy-less routes. That is a latent data-exposure risk if the central DB ever holds business tables, plus code hygiene debt. Medium, not high.

Related issue spotted, outside this finding: tenant.php:60-74 (/invoices/{id}/print, /print/thermal, /print/a4) sits outside the auth group. With tenancy initialised, those pages render any invoice by sequential id with no login.

### 26. [MEDIUM] Tenant routes point to 15 controller methods that do not exist
- **Location:** `D:/projects/sroor/backend/routes/tenant.php:90`
- **Effort:** S
- **Evidence:** I cross-checked route:list --json (run with in-memory sqlite) against the controller sources. Missing methods: InvoiceController@edit/update/destroy/restore (tenant.php:90-94), PurchaseController@create, ReturnController@create, DailyJournalController@openShift/closeShift/storeExpense (222-224), and SettingController@sendDailySummaryTelegram/sendLowStockTelegram/sendOverdueShiftTelegram/sendBackupTelegram/downloadBackup/clearCache (240-245).
- **Impact:** Each of these URLs throws BadMethodCallException and returns a 500. Because no generic JSON handler exists, debug environments may return a stack trace. They are also leftover API surface that confuses the contract.
- **Recommendation:** Delete the dead routes, or implement them in routes/api.php behind ApiTokenAuth. Add a test that asserts every registered route action method exists.
- **Verification:** The finding is confirmed from the code. All 15 routes exist in backend/routes/tenant.php, and none of the target methods exist in their controllers.

**Missing methods:**
- Api/InvoiceController only defines index, show, store and cancel (lines 33-164). The routes to edit, update, destroy and restore are at tenant.php:90-94.
- Api/PurchaseController has no create; the route is at tenant.php:194.
- Api/ReturnController has no create; the route is at tenant.php:201.
- Api/DailyJournalController only has index. The routes to openShift, closeShift and storeExpense are at tenant.php:222-224.
- Api/SettingController only has index, update and sendTestTelegram. The six telegram, backup and clear-cache routes are at tenant.php:240-245.

**Nothing catches the missing calls.** The base App\Http\Controllers\Controller is empty, and no controller has `__call` or a trait that could supply these methods. TenancyServiceProvider.php:119-121 does load routes/tenant.php, so the routes are live. A request that gets past the auth and `can:` middleware ends in a BadMethodCallException and a 500 error. .env.example sets APP_DEBUG=true. bootstrap/app.php only adds custom handlers for auth, permission and validation errors, so under that setting a 500 can show a stack trace.

**Users can actually hit some of these:**
- resources/js/Components/Settings/BackupTab.vue:31 uses a plain link (`href="/settings/backup/download"`), so the backup download is broken for users.
- The SPA navigates to /purchases/create and /returns/create (router/index.js:155 and :228). The matching backend GET routes take priority, so reloading or deep-linking those pages would hit the missing methods and return a 500.

**Why medium, not high:** every route requires an authenticated tenant user with the right permission. The failures are broken features and an unclear API, not a security or data-integrity problem.

### 27. [MEDIUM] Blender calculator quotes a cardamom surcharge that the issued invoice never charges or deducts from stock
- **Location:** `D:\projects\sroor\backend\app\Actions\Blends\CreateBlenderInvoiceAction.php:19`
- **Effort:** S
- **Evidence:** CalculateBlendCostAction.php:118-123 adds cardamomGrams*1.5 to cost and cardamomGrams*2.5 to price as hardcoded EGP constants with native float math. useCoffeeBlender.js:90-99 does the same on the client. CreateBlenderInvoiceAction only creates invoice lines from $dto->components. cardamom_grams only reaches the free-text note at line 35 ('حبهان: …جم'). tests/Feature/Api/CoffeeBlenderApiTest.php:217-242 sends cardamom_grams=5 and asserts net_total 160.000, while the calculator would show 172.500.
- **Impact:** Each blend sold with cardamom is under-invoiced by grams*2.5 compared with the price shown to the cashier and customer. The cardamom stock is never decremented, so inventory and COGS drift. Other tenants also see a 'cardamom' input priced at a fixed EGP rate that has nothing to do with their catalogue.
- **Recommendation:** Remove the special cardamom field. Treat any add-on as a normal component line that references a real Item, so its price and stock come from the item table. If a fixed surcharge is truly needed, make it a configurable, bcmath-computed invoice line.
- **Verification:** The finding holds up against the code. Only some of the cited line numbers are wrong.

1. **The calculator adds a hardcoded cardamom surcharge using float math.** In backend/app/Actions/Blends/CalculateBlendCostAction.php:61-67 (not 118-123 as claimed), it adds `(string)($cardamomGrams * 1.5)` to cost and `(string)($cardamomGrams * 2.5)` to price. The amounts are fixed EGP-per-gram constants and the multiplication is native float math before it goes into bcadd. Line 17 also casts the input to float.
2. **The client does the same.** backend/resources/js/Composables/useCoffeeBlender.js:90-92 and 98-100 add the same `*1.5` and `*2.5` to the cost and price the cashier sees.
3. **The invoice ignores cardamom.** CreateBlenderInvoiceAction.php:24-34 builds invoice lines only from `$dto->components`. At lines 37-39, `cardamom_grams` only goes into the free-text note (`حبهان: Xجم`). It is never priced and never deducted from stock.
4. **The tests show the gap.**
   - The calculate test (CoffeeBlenderApiTest.php:158-195) asserts total_price 330.0, which is 305 for the components plus 25 for 10 g of cardamom.
   - The invoice test (209-250) sends `cardamom_grams=5` and asserts net_total 160.000, and checks stock only for the two coffee components.
   - For that same blend, the client preview would show 172.50, so 12.50 is never billed.
5. **Nothing else covers it.** A grep of backend/app finds `cardamom` only in the two actions, the DTO and the FormRequests. No guard, observer or service adds a cardamom line or stock movement.

**Why medium, not high:**
- The money involved per sale is small (2.5 EGP per gram).
- The calculator is a preview, and the invoice comes from the server and is internally consistent, with no partial writes and stock updated for the items it bills.
- This is a legacy coffee-only module.

The real impact is revenue leakage and a mismatch between the price shown and the price billed. Cardamom stock and its cost are also never recorded. Using hardcoded constants also breaks the generic multi-tenant model, and the float math breaks the rule that money is calculated with bcmath.

### 28. [MEDIUM] Blend engine assumes every component is priced per kilogram (grams/1000) and ignores the item's unit
- **Location:** `D:\projects\sroor\backend\app\Actions\Blends\CreateBlenderInvoiceAction.php:23`
- **Effort:** L
- **Evidence:** `$kg = bcdiv((string)$comp['grams'], '1000', 4);` is sent as the invoice quantity regardless of $item->unit. CalculateBlendCostAction.php:34-40 does the same. CreateBlenderInvoiceRequest only validates components.*.grams. Units are free text: the items.unit default is 'كجم' (migration 160001:17) and the settings list includes 'جرام', 'لتر' and 'قطعة'.
- **Impact:** For an item whose unit is 'جرام', 250 g becomes quantity 0.25, so the sale is charged and deducted 1000x too low. For a 'قطعة' item, InvoiceService:66-69 throws a DomainException. For 'لتر', mass is silently treated as volume. Only kilo-priced goods work correctly, which rules out a generic recipe or composite feature.
- **Recommendation:** Generalize this into a 'composite/recipe item' feature. Express component quantities in each item's own base unit, or add a units table with conversion factors and a dimension (mass/volume/count). Reject components whose unit is incompatible.
- **Verification:** The finding holds. In CreateBlenderInvoiceAction.php:25, `bcdiv(grams,'1000',4)` becomes the invoice quantity, and $item->unit is never read. CalculateBlendCostAction.php:34-41 does the same and multiplies kg by cost_price and selling_price, which only makes sense if both are priced per kg. CreateBlenderInvoiceRequest.php:50-52 only checks item_id, grams and unit_price; nothing restricts the item's unit. The frontend does not guard it either: useCoffeeBlender.js:113 loads `/items?per_page=100` with no unit filter, and every item, whatever its unit, is offered as a component. CoffeeBlenderFormulationCard.vue:48-49 simply labels every price and stock figure as per kg (unit_weight_short). Downstream, InvoiceService::confirmInvoice uses the quantity as given for the line total (bcmul qty*unit_price), the cost and deductStock, so a 'جرام' item really is charged and deducted 1000x too little. The discrete-unit check at InvoiceService.php:66-69 rejects fractional quantities for 'قطعة' and similar units, as claimed, but that branch fails safely: the transaction rolls back and nothing is corrupted. Units are configurable, and 'جرام' and 'لتر' are offered (SuperAdminApiTest.php:259 and the units settings translations), so the gram case is reachable in a real tenant. I lowered the severity from high to medium because the damage is limited to the coffee-specific blender feature. A user also has to pick a non-kg item while the UI labels everything per kg. Only the gram case silently corrupts money and stock; the piece case is just an error. It is still a real money and stock bug, and a design limit for a generic SaaS. One more point: CalculateBlendCostAction also uses float/round and a hardcoded cardamom price (lines 16-17, 31-34, 61-64), which breaks the bcmath rule but is outside this finding.

### 29. [MEDIUM] 15 routes point to controller methods that do not exist (500 at runtime), including UI-linked backup download
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:90`
- **Effort:** S
- **Evidence:** I reflected over `php artisan route:list --json` (311 routes) and these are missing: InvoiceController@edit/update/destroy/restore (tenant.php:90-94), DailyJournalController@openShift/closeShift/storeExpense, PurchaseController@create, ReturnController@create, SettingController@downloadBackup/clearCache/sendBackupTelegram/sendDailySummaryTelegram/sendLowStockTelegram/sendOverdueShiftTelegram (tenant.php:240-245). resources/js/Components/Settings/BackupTab.vue:31 links href="/settings/backup/download".
- **Impact:** The Backup download button and the related settings actions return a 500 BadMethodCallException. Route names like invoices.update suggest invoice edit or restore exists when it does not.
- **Recommendation:** Delete the dead route lines, or implement them as API endpoints with FormRequest + Action. Add a CI test that asserts method_exists for every route action.

### 30. [MEDIUM] 21 Policy classes are effectively dead; authorisation is copy-pasted inline 52 times
- **Location:** `D:\projects\sroor\backend\app\Policies\ItemPolicy.php:11`
- **Effort:** M
- **Evidence:** There are 0 calls to $this->authorize()/Gate:: in app/Http/Controllers. Only StoreCategoryRequest and UpdateCategoryRequest use can('create'/'update', model). 52 methods repeat `if ($user && !$user->hasRole('admin') && !$user->can('x') ...) return 403`, e.g. ItemController L42/148/182/201/238/270, and the permission sets drift between methods. API routes (api.php) carry no `can:` middleware except activity-logs and super-admin. Three FormRequests return true (StoreTenantRequest, UpdatePlanRequest, ResolveTenantWorkspaceRequest); the first two are covered by the route-level can:super_admin.access.
- **Impact:** Permission logic is duplicated and inconsistent. For example, ItemPolicy::viewAny equals the inline check today but will diverge as the copies drift. The `$user &&` guard fails open if a route is ever mounted without auth. The policy unit tests give false confidence.
- **Recommendation:** Use the policies everywhere. Put $this->authorize('viewAny', Item::class) or FormRequest::authorize() on reads too (e.g. an IndexItemsRequest), or add route `can:` middleware. Then delete the inline ladders.

### 31. [MEDIUM] Fat index methods filter inline; Pipeline filters exist only for tenants
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\ItemController.php:39`
- **Effort:** L
- **Evidence:** Inline filter if-ladders and queries, with no FormRequest or Pipeline: ExpenseController@index L33 (100 lines, 8 ifs, 9 queries); ItemController@index L39 (87 lines, 14 ifs, 17 queries); InvoiceController@index L33 (85 lines, 9 ifs, 15 queries); StockTransferController@index L30 (80 lines); TreasuryController@summary L27 (73 lines, about 20 queries); PurchaseController@index L31 (72); UserController@index L36 (70); ReturnController@index L28 (68); CustomerController@index L38 (59); SupplierController@index L38 (57); PaymentController@index L27 (56); CategoryApiController@index L29 (52); StoreController@index L40 (48); ShiftController@index L34; ReportController@itemCard L197. app/Filters/Tenants is the only Pipeline use (GetTenantsIndexDataAction). Filter values (status, payment_type, category) are bound parameters but not whitelisted. No endpoint accepts a client sort column, so there is no sort-injection surface, but there is no sorting feature either.
- **Impact:** The same search/date/store/status logic is reimplemented in about 13 places. Bugs differ per list: ItemController resolves $storeId (L53) but never filters by it, and its stock filters use the global items.current_stock instead of per-store stock. Every list change touches the controller.
- **Recommendation:** Create app/Filters/Common (SearchFilter with a column list, DateRangeFilter, StoreFilter, StatusFilter(enum whitelist)) and domain filters. Use a List<Noun>Request that validates filters with `in:` rules and a Get<Noun>ListAction that runs the Pipeline and paginates. Move summary aggregates into the Action or a service.

### 32. [MEDIUM] ReportController builds its DTO from $request->all(), bypassing FormRequest validation
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\ReportController.php:42`
- **Effort:** S
- **Evidence:** buildDTO(FilterReportRequest|Request $request) calls ReportFilterDTO::fromArray($request->all(), ...). topItems (L185) passes a plain Request with no validation at all. FilterReportRequest declares 'store_id' => ['nullable'] with no type or exists rule, and 'period', 'tab', 'treasury_method' and 'stock_filter' are free strings. ReportController@itemCard returns a raw Item model and StockMovement collection (L216-220).
- **Impact:** Unvalidated keys and values reach the report Actions. store_id is not checked against the user's branches (see the store-authorisation finding). Raw model serialisation exposes every column, such as cost fields, to anyone with reports.view.
- **Recommendation:** Use $request->validated() and tighten the rules (store_id integer|exists, period in:today,yesterday,this_week,this_month,this_year,custom). Give topItems and itemCard their own FormRequests and Resources.

### 33. [MEDIUM] Some Actions accept Illuminate\Http\Request or use request(); every Action has a single public execute()
- **Location:** `D:\projects\sroor\backend\app\Actions\Tenants\GetTenantsIndexDataAction.php:20`
- **Effort:** S
- **Evidence:** execute(Request $request) appears in GetTenantsIndexDataAction:20, GetSystemContextAction:28 and ApiMeAction:19. ImpersonateTenantAction:45,52 calls request()->header('Host') and request()->getScheme(). LoginAction and SuperAdminLoginAction call Auth::attempt/Auth::login, which is acceptable for session login. A scan of all Actions found none with more than one public method besides __construct. GetTenantsIndexDataAction also returns 'plans', which the controller ignores before re-querying Plan (SuperAdminApiController:80).
- **Impact:** These Actions cannot be reused from jobs or commands and are hard to unit-test. HTTP concerns leak into the domain layer.
- **Recommendation:** Pass a filters DTO or plain values (host, scheme, user) from the controller. Remove the duplicate Plan query.

### 34. [MEDIUM] SuperAdminApiController holds business logic and manual tenancy switching inline
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\SuperAdminApiController.php:183`
- **Effort:** M
- **Evidence:** updateTenantUnits (L183-217) mutates $tenant->data, then calls Tenancy::initialize($tenant) / Setting::set / Tenancy::end() inside try with no finally, so a thrown exception skips end(). runTenantMigrations (L222) runs Artisan tenants:migrate synchronously inside the HTTP request and returns its raw output. updatePlatformSettings, getPlatformSettings, getUnits and updateUnits (L323-397) write Settings directly with hardcoded Arabic defaults and a support email/phone. dashboard (L55) runs a raw DB::select('SELECT VERSION()'). 16 public methods, 7 without a FormRequest and 15 returning raw arrays/models (storeTenant returns the raw $tenant model at L102).
- **Impact:** Tenancy context can leak for the rest of the request after an exception. Long migrations block a PHP worker and can time out halfway. Returning the raw Tenant model may expose internal data columns such as DB credentials stored in tenant data, depending on $hidden. These are product-critical super-admin flows with no Action, so they are untested.
- **Recommendation:** Extract UpdateTenantUnitsAction (use $tenant->run(fn () => ...)), a queued RunTenantMigrationsJob, and Get/UpdatePlatformSettingsAction with a DTO. Return a TenantResource. Move defaults to config and lang.

### 35. [MEDIUM] Duplicate and dead controllers or endpoints for the same operations
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\ReportPrintController.php:17`
- **Effort:** M
- **Evidence:** ReportPrintController (597 lines, 9 protected report builders) is referenced by no route (route:list). It duplicates the Report Actions and web.php:180's /reports/print closure. POSController (session, tenant.php:82-86) duplicates Api\PosController (api.php:109-112) and both share the float POSInvoiceDTO. Two checkout paths exist (POST /invoices via StoreSalesInvoiceRequest and POST /pos/checkout via StorePOSInvoiceRequest) with different validation contracts. Customer receipts go through both PaymentController@customerReceipt and CustomerController@collectPayment, and supplier payments through PaymentController@supplierVoucher and SupplierController@pay. web.php and tenant.php both define invoice print and daily-journal print closures (60-line copies with inline bcmath). Http/Resources/CashShiftResource and Http/Resources/Api/CashShiftResource are two different shapes of the same model.
- **Impact:** Fixes land in one copy and miss the other. The discount and split-payment bugs above exist because two contracts diverged. Dead code inflates the review surface.
- **Recommendation:** Keep one checkout (CreateSalesInvoiceAction + a single FormRequest/DTO). Delete POSController, ReportPrintController and the web.php business closures. Keep one payment endpoint per party. Merge the CashShiftResources.

### 36. [MEDIUM] FormRequests create a customer in prepareForValidation (before authorize) with a hardcoded Arabic name
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StorePOSInvoiceRequest.php:34`
- **Effort:** S
- **Evidence:** StorePOSInvoiceRequest:34-49 and StoreSalesInvoiceRequest:18-34 look up a 'walk-in customer' by the names 'نقدي عام'/'عميل نقدي' or the phone '0000000000', and call Customer::create([...]) if none exists. Laravel runs prepareForValidation before passesAuthorization. StorePOSInvoiceRequest also falls back to session('current_store_id') and Store::first() for store_id. StoreSalesInvoiceRequest::authorize ends with `?? true`.
- **Impact:** An unauthorised or invalid request can still write a customer row. Walk-in detection depends on an Arabic display name, so tenants that rename it or use English get duplicates. Silent store fallback contradicts the rule against defaulting silently to the first store.
- **Recommendation:** Move walk-in resolution into the Action (a WalkInCustomerResolver keyed by a flag column or setting, not by name). Make store_id required from the resolved active store. Fix the `?? true` to `?? false`.

### 37. [MEDIUM] Money is emitted as JSON floats across Resources and controller summaries
- **Location:** `D:\projects\sroor\backend\app\Http\Resources\InvoiceResource.php`
- **Effort:** M
- **Evidence:** There are 76 `(float)` casts in Http/Resources (InvoiceResource 15, CashShiftResource 9, ItemResource 8, POSItemResource 7, PurchaseResource 6 ...). Controllers cast summaries too: InvoiceController:94-96 does (float)sum then bcsub on (string)float, TreasuryController:78-95, CustomerController:77, SupplierController:75, PurchaseController:81-82, ReturnController:73, ItemController:106, POSController:49-52, PaymentController:71-124.
- **Impact:** Totals above about 2^53 are not a concern here, but values like 0.1+0.2 accumulate in client-side arithmetic, and InvoiceController round-trips sums through float before bcsub, so total_due can differ by fractions from the true decimal. The API contract also disagrees with the string-money rule that the DTOs follow.
- **Recommendation:** Emit money as decimal strings ('1234.500') from Resources, with a shared MoneyCast or a helper. Format on the client with useMoney. Remove the (float) casts in summaries.

### 38. [MEDIUM] TreasuryController@summary mixes store-filtered sales with all-store payments
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\TreasuryController.php:46`
- **Effort:** M
- **Evidence:** Sales and expenses are filtered by $storeId (L38-55), but $todayReceipts (L46) and $todaySupplierPaid (L49) query Payment with no store filter before feeding net_cash (L59-61). $storeId defaults to session or 1 (L34). There is about 20 lines of inline query and bcmath logic with no Action or Resource. The web.php/tenant.php daily-journal print closures have the same mix (tenant.php:123,132).
- **Impact:** Branch A's 'net cash today' includes supplier payments and customer receipts made at Branch B, so the drawer reconciliation figure is wrong for every multi-branch tenant.
- **Recommendation:** Move the logic into a GetTreasurySummaryAction that reuses TreasuryService and filters payments by store, joining via invoice/purchase store or a payments.store_id column. Add a two-store test.

### 39. [MEDIUM] Telescope access bridge in web.php: token via query string, hardcoded phone/email allowlist, any 'admin' role accepted
- **Location:** `D:\projects\sroor\backend\routes\web.php:17`
- **Effort:** S
- **Evidence:** /telescope-access?token=... resolves a Sanctum token or the legacy users.api_token, then grants Telescope if the user hasRole('super_admin') or hasRole('admin'), or the phone is in a hardcoded list of 2 personal phone numbers, or the email ends with a hardcoded company domain. It then calls auth('web')->login($user, true). The literal values are PII and are not reproduced here.
- **Impact:** Any account with role 'admin' in the DB the route resolves against gets a remember-me web session and Telescope, which records request payloads and headers, possibly including bearer tokens. Tokens in URLs end up in logs. Personal phone numbers are committed to the repo.
- **Recommendation:** Gate Telescope with the super-admin gate only (viewTelescope in TelescopeServiceProvider). Remove this bridge and the hardcoded identities. Consider disabling Telescope in production.

### 40. [MEDIUM] P&L and ABC report caches have tenant-agnostic keys and are never invalidated
- **Location:** `D:\projects\sroor\backend\app\Services\ProfitLossService.php:29`
- **Effort:** S
- **Evidence:** Cache keys are `erp_pnl_{store|all}_{from}_{to}` (and `erp_abc_...` in InventoryAnalyticsService:29), with no tenant id. CacheTenancyBootstrapper is commented out in config/tenancy.php:36. CACHE_STORE=database, and CacheManager binds the DB connection the first time the store is resolved, so isolation depends on resolution order and on long-running workers. clearCache() forgets `erp_pnl_all`, which never matches a real key, and it is never called (grep finds no callers). Setting::getCacheKey does include the tenant id correctly.
- **Impact:** The P&L/ABC report stays stale for up to 15 minutes after invoices or returns. If the cache store is resolved on the central connection, two tenants who ask for 'all stores' for the same period could read each other's figures.
- **Recommendation:** Enable CacheTenancyBootstrapper (it needs a taggable store, such as redis) or prefix every key with tenant('id'). Invalidate with a version or tag that is bumped on invoice/return/expense writes, or remove the cache.

### 41. [MEDIUM] Two profit services compute COGS differently
- **Location:** `D:\projects\sroor\backend\app\Services\ProfitLossService.php:75`
- **Effort:** M
- **Evidence:** ProfitService::getPeriodicProfits uses the historical invoices.total_cost and ignores returns. ProfitLossService uses the item's *current* weighted_avg_cost/cost_price times quantity, and subtracts returns. Dashboard actions use ProfitService. ReportPrintController:541 uses ProfitLossService.
- **Impact:** The dashboard and the printed P&L show different gross profit for the same period. Past P&L figures change whenever an item's cost changes, so closed periods cannot be reproduced.
- **Recommendation:** Use one COGS source, the cost snapshot on invoice_items at sale time, inside one profit service. Make the dashboard and the reports both call it.

### 42. [MEDIUM] Purchase return reads undefined $purchase and recalculates the supplier balance a second way
- **Location:** `D:\projects\sroor\backend\app\Services\ReturnService.php:118`
- **Effort:** S
- **Evidence:** `$storeId = $data['store_id'] ?? ($purchase?->store_id ?? ...)` uses $purchase, but it is never assigned in createPurchaseReturn. Laravel turns the PHP 8 undefined-variable warning into an ErrorException. Lines 164-170 rebuild the supplier balance formula inline instead of calling SupplierBalanceService::updateBalance.
- **Impact:** A purchase return without store_id crashes, and the transaction rolls back. Any later change to the balance formula (for example, the opening-balance fix) will be missed in this copy.
- **Recommendation:** Load the purchase with lockForUpdate when purchase_id is given. Replace the inline formula with $this->supplierBalanceService->updateBalance().

### 43. [MEDIUM] Tenant provisioning is synchronous and has no rollback for partial failures
- **Location:** `D:\projects\sroor\backend\app\Services\TenantProvisionerService.php:55`
- **Effort:** M
- **Evidence:** Tenant::create fires the JobPipeline CreateDatabase+MigrateDatabase with shouldBeQueued(false) (TenancyServiceProvider.php:31) inside the HTTP request. Domain creation, Subscription::create and the $tenant->run() seeding follow, with no try/catch or compensation. env('CENTRAL_DOMAIN') is read at runtime (line 58), and that returns null once config is cached.
- **Impact:** If the domain is already taken (unique) or seeding fails, the tenant row and its MySQL database already exist, but there is no domain, subscription or admin. A retry with the same slug fails on the duplicate id, and support has to clean up by hand. With config:cache enabled, subdomains are always created under the hardcoded default.
- **Recommendation:** Validate domain uniqueness before creating. Wrap the central rows in a transaction, provision through a queued, idempotent pipeline that compensates (deletes the DB and tenant) on failure, and move CENTRAL_DOMAIN into config/tenancy.php.

### 44. [MEDIUM] Telegram settings fall back to the platform bot/chat, so tenant data can reach the platform operator
- **Location:** `D:\projects\sroor\backend\app\Services\TelegramService.php:20`
- **Effort:** S
- **Evidence:** getBotToken()/getDefaultChatId() return Setting::get(...) ?: config('services.telegram.*'). Shift discrepancy alerts (cashier name, store, amounts, free-text notes) are sent with parse_mode HTML, and $shift->notes is not escaped (line 416).
- **Impact:** A tenant that never set up Telegram still has its cash discrepancies sent to the platform's global chat. Cashier notes containing '<' break the message, or can inject markup.
- **Recommendation:** In tenant context, use only tenant-level settings and do not fall back to global config. Escape user text with htmlspecialchars.

### 45. [MEDIUM] Report caches are not tenant-prefixed, CacheTenancyBootstrapper is disabled, and cache invalidation never runs
- **Location:** `D:\projects\sroor\backend\app\Services\ProfitLossService.php:15`
- **Effort:** S
- **Evidence:** config/tenancy.php:36 has `// Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class` commented out. The cache key is `"erp_pnl_" . ($storeId ?? 'all') . "_{$fromDate}_{$toDate}"` with no tenant id (InventoryAnalyticsService:29 builds `erp_abc_...` the same way). clearCache() forgets only `erp_pnl_{store}` and `erp_pnl_all`, which never match the real keys because those carry date suffixes. grep shows no caller of ProfitLossService::clearCache or InventoryAnalyticsService::clearCache anywhere in app/. Tenant isolation currently depends only on CACHE_STORE=database (.env and .env.example) with a null DB_CACHE_CONNECTION, so the cache table follows the tenant default connection. By contrast, Setting::getCacheKey() is tenant-prefixed.
- **Impact:** (a) After an invoice is cancelled, edited or returned, the P&L and ABC reports keep showing the old figures for up to 15 minutes. (b) If ops switches CACHE_STORE to redis or file, or anything resolves the cache store before tenancy starts (a queue worker or long-lived process), tenant A's `erp_pnl_all_2026-10-01_2026-10-07` is served to tenant B: a cross-tenant leak of financial data.
- **Recommendation:** Enable CacheTenancyBootstrapper (it needs a tag-capable store such as redis) or prefix every key with tenant('id'). Use tags or a version counter per tenant/store, and bump it from InvoiceService/ReturnService/ExpenseService after commit, so that invalidation actually matches the keys.

### 46. [MEDIUM] Hot tenant tables lack composite indexes, and whereDate() makes the existing date indexes unusable
- **Location:** `D:\projects\sroor\backend\database\migrations\tenant\2026_08_11_180002_add_store_id_to_existing_tables.php:19`
- **Effort:** M
- **Evidence:** store_id was added later as `unsignedBigInteger('store_id')->nullable()->index()`: single-column, nullable, with no FK constraint. invoices has separate single indexes on invoice_date, status, payment_status and net_total (an index on a money column that nothing filters on), and no composite (store_id,status,invoice_date). The same applies to expenses (store_id, expense_date), returns (store_id, return_date; returns also gets filtered by return_type), purchases, cash_shifts (store_id,status) and stock_movements (item_id,store_id,created_at). stock_movements and audit_logs index source_type/source_id and auditable_type/auditable_id separately instead of as a composite morph index. softDeletes() columns from 2026_08_11_200000 are unindexed. Across the codebase, 142 whereDate() calls (for example ProfitLossService:62, DashboardAnalyticsService:42, GetProfitLossReportAction:22, InvoiceController:76) compile on MySQL to `date(invoice_date) >= ?`, which cannot use an index.
- **Impact:** Once a tenant has a few hundred thousand invoices or stock movements, every dashboard load, report and invoice list filters by store+status+date with at best one low-selectivity index (status or store_id) and scans the rest. On Hostinger shared MySQL this means slow pages and lock contention with POS writes. Nullable store_id with no FK also lets orphan or NULL rows silently drop out of per-store reports.
- **Recommendation:** Add a new tenant migration with composite indexes: invoices(store_id,status,invoice_date), expenses(store_id,expense_date), returns(store_id,return_type,return_date), purchases(store_id,status,purchase_date), cash_shifts(store_id,status), stock_movements(item_id,store_id,created_at) and (source_type,source_id). Drop the index on invoices.net_total. Replace whereDate on DATE columns with where/whereBetween using plain dates. Add FKs on store_id once the data is backfilled.

### 47. [MEDIUM] Reports and dashboards load whole tables into PHP and aggregate in loops, synchronously in the request
- **Location:** `D:\projects\sroor\backend\app\Services\DashboardAnalyticsService.php:49`
- **Effort:** L
- **Evidence:** DashboardAnalyticsService loads all period invoices with ->get(), then filters per day in PHP (line 62, O(days*invoices)) and loops per hour. ProfitService::getPeriodicProfits (line 45) loads the month's invoices again on every dashboard call. ProfitLossService loads `Invoice::with(['items.item'])` per store, then `ReturnDocument::with(['items.item'])` and all Expenses. GetProfitLossReportAction:26 and :81 load all invoices and all active items. GetItemMovementsAction:48 loads every movement with eager-loaded user and store just to total in/out before paginating. ExportService:60/74/88/141/221/293 uses ->get() inside streamDownload, which removes the benefit of streaming. No chunk(), cursor(), lazy() or queued job exists for any report or export.
- **Impact:** Memory and time grow linearly with tenant history. A year of POS sales (about 100k invoice lines) loaded with items.item for a yearly P&L, or a full item-movement stats call, will hit PHP memory_limit or max_execution_time on shared hosting, and each dashboard refresh pulls the month's invoices twice.
- **Recommendation:** Push aggregation into SQL: SUM/COUNT with GROUP BY DATE(invoice_date) or HOUR(created_at), and SUM(quantity*cost_price) joins. Use selectRaw sums for movement stats. Use cursor()/lazy() in ExportService. Queue large exports and P&L ranges, and cache the results with tenant-aware keys.

### 48. [MEDIUM] Money totals use float arithmetic through Collection::sum() and (float) casts
- **Location:** `D:\projects\sroor\backend\app\Services\DashboardAnalyticsService.php:33`
- **Effort:** M
- **Evidence:** `(string)($todayInvoices->sum('net_total') ?: '0.000')` (lines 33, 50, 63, 110-114). Collection::sum adds decimal strings with PHP `+`, which yields floats. More examples: ReportPrintController:158-160 `(float)($itemsData->sum('total_revenue') - $itemsData->sum('total_cost_amount'))` and :225-228. GetExpensesSummaryAction:19-20 `(float)(...)->sum('amount')`. InvoiceController:94-96 `(float)` totals followed by bcsub on a float. GetProfitLossReportAction returns all summary money as (float).
- **Impact:** This breaks the project's bcmath-only rule. Totals over thousands of 3-decimal rows can drift (for example 0.1+0.2 style errors that show up as 0.001 discrepancies), and the dashboard, invoice list and P&L can disagree by a few piasters with the bcmath-based reports and shift close.
- **Recommendation:** Sum in SQL and keep results as strings, or reduce with bcadd. Return money as decimal strings in the API and format only in the UI.

### 49. [MEDIUM] Document number generation with count()+1 collides once a same-day record is soft-deleted (and under concurrency)
- **Location:** `D:\projects\sroor\backend\app\Actions\Expenses\CreateExpenseAction.php:23`
- **Effort:** S
- **Evidence:** `$count = Expense::whereDate('created_at', now()->toDateString())->count() + 1;` builds `EXP-ymd-NNNN`. Expense uses SoftDeletes, so count() excludes trashed rows, and expenses.expense_number is unique (migration 2026_08_10_160011 line 13). AdjustItemStockAction:42 uses the same pattern for ADJ numbers. Neither reads under lockForUpdate.
- **Impact:** Create EXP-...-0001 and -0002, then soft-delete -0001. count is now 1, so the next expense gets -0002 and hits a unique violation (500). Every later attempt that day fails the same way until midnight. Two concurrent creations also collide.
- **Recommendation:** Generate numbers from a locked per-tenant sequence/counter table (or max(id) withTrashed under lockForUpdate) inside the transaction, and add a test that deletes and then recreates.

### 50. [MEDIUM] InvoiceResource reads a non-existent `balance` attribute, so customer_balance is always 0
- **Location:** `D:\projects\sroor\backend\app\Http\Resources\InvoiceResource.php:20`
- **Effort:** S
- **Evidence:** `'customer_balance' => $this->customer ? (float)$this->customer->balance : 0` and `'balance' => ... $this->customer->balance` at line 25. App\Models\Customer only defines current_balance (cast decimal:3) and has no balance accessor. InvoiceController eager-loads `customer:id,name,phone,current_balance`. The frontend uses `customerInfo?.balance` (resources/js/Components/Invoices/Show/InvoiceShowInteractiveView.vue:10).
- **Impact:** The invoice show screen always displays a customer balance of 0, even for customers who owe money.
- **Recommendation:** Use current_balance and return it as a decimal string. Add a resource test that asserts the value.

### 51. [MEDIUM] tenant.php JSON routes shadow Vue SPA history-mode paths, so deep links and refreshes break on tenant domains
- **Location:** `D:/projects/sroor/backend/routes/tenant.php:160`
- **Effort:** S
- **Evidence:** The router uses createWebHistory() with paths /items, /customers, /invoices and /settings (resources/js/router/index.js:55,105,176,308). On a tenant domain, tenant.php registers GET /items, /customers, /invoices, /suppliers, /settings, /users, /roles and others to Api controllers that return JSON under session 'auth'. These routes are registered before the web.php catch-all /{any?}, so they win.
- **Impact:** A user who refreshes or bookmarks https://shop.example.com/invoices gets a login redirect or a raw JSON payload instead of the SPA. The SPA authenticates with bearer tokens, not the web session.
- **Recommendation:** Remove the duplicated session-based JSON routes from tenant.php. Keep only the SPA host, login/impersonation and authenticated print routes there, and put all data in /api/v1.

### 52. [MEDIUM] Inconsistent error envelope: only 401/403/422 are normalized; 404/500/ModelNotFound/QueryException/business errors leak raw messages
- **Location:** `D:/projects/sroor/backend/bootstrap/app.php:31`
- **Effort:** M
- **Evidence:** withExceptions renders only AuthenticationException, Spatie UnauthorizedException (which appends the raw English $e->getMessage() to the translated text) and ValidationException. There is no handler for ModelNotFoundException/NotFoundHttpException, AuthorizationException, QueryException or a domain exception base class (no custom Exception classes in app/). Separately, 19 catch blocks in controllers return $e->getMessage() to the client: PaymentController:104,129, TrashController:68,93, UserController:175,201, SettingController:92, POSController:66, and many in SuperAdminApiController (128, 152, 175, 214, 239, 260, 281, 315). .env.example ships with APP_DEBUG=true.
- **Impact:** A unique-constraint or connection failure returns SQL text (table and column names, sometimes the DB host) to the browser. Clients must handle {success,message}, {status,message} (6 occurrences, e.g. tenant.php:263,285) and the default Laravel {message} shapes. A 404 from findOrFail (41 call sites) does not carry success:false.
- **Recommendation:** Add one renderer in bootstrap/app.php for api/* that maps ModelNotFound to 404, AuthorizationException to 403, a DomainException base class to 422/409, and everything else to 500 with a generic __('errors.server') message and a log/trace id. Remove the controller try/catch blocks that echo getMessage().

### 53. [MEDIUM] Telescope enabled by default in production with a token-in-URL login bridge and over-broad gate
- **Location:** `D:/projects/sroor/backend/routes/web.php:17`
- **Effort:** S
- **Evidence:** /telescope-access?token= looks up a Sanctum PAT or a plaintext users.api_token and then calls auth('web')->login($user, true). It allows hasRole('admin'), two hardcoded phones, or any email ending in @baraa-solutions.com. TelescopeServiceProvider::gate() repeats the query-token auto-login. config/telescope.php:19 sets 'enabled' => env('TELESCOPE_ENABLED', true). composer.json pins laravel/sanctum and laravel/telescope to "*" (the lock has sanctum v4.3.3 and telescope v5.22.1), and Telescope is a production dependency registered in bootstrap/providers.php.
- **Impact:** Telescope stores exceptions, failed requests and job payloads from every tenant. Any central 'admin' user, or anyone holding a leaked token URL, gets a persistent remember-me web session that can read them. The "*" constraints let a `composer update` pull a breaking major version into production.
- **Recommendation:** Default TELESCOPE_ENABLED to false in production or move Telescope to require-dev with conditional registration. Delete the /telescope-access bridge and token logins. Gate on a central-only super_admin. Pin sanctum to ^4.3 and telescope to ^5.22.

### 54. [MEDIUM] Blend quantities use 4 decimal places and float rounding, but columns hold 3, so line totals and stock drift
- **Location:** `D:\projects\sroor\backend\app\Actions\Blends\CreateBlenderInvoiceAction.php:23`
- **Effort:** S
- **Evidence:** Quantity is computed at bcdiv scale 4 (for example 62.5 g gives 0.0625) and passed to InvoiceService, which does bcmul($qty,$unitPrice,3) on the 4-dp value. invoice_items.quantity and stock columns are DECIMAL(12,3). components.*.grams has min:0.1, which gives 0.0001 kg and stores as 0.000. CalculateBlendCostAction uses round(float,2) for grams and returns float money (lines 33-44, 63-78). DTO cardamom_grams is a float.
- **Impact:** The stored quantity (0.063) times the unit price no longer equals the stored line total. Stock movements can round differently from the invoice. A 0.1 g line can be invoiced at a non-zero price with zero stored quantity. This breaks the project's DECIMAL(12,3)+bcmath rule.
- **Recommendation:** Normalize every quantity to scale 3 with bcmath before pricing. Validate that the converted quantity is at least 0.001. Return strings, not floats, from the calculate endpoint. Use string or decimal types in the DTO.

### 55. [MEDIUM] Coffee-specific domain attributes hardcoded into persisted invoice notes and DTO defaults
- **Location:** `D:\projects\sroor\backend\app\DTOs\Blends\CreateBlenderInvoiceDTO.php:13`
- **Effort:** M
- **Evidence:** The DTO defaults are roast_type='وسط' and grind_level='تركي ناعم', and target_weight_grams defaults to 250 as an int. CreateBlenderInvoiceAction.php:30-38 writes 'درجة التحميص … الطحن … حبهان … خلطة وتوليفة مخصوصة' into invoices.notes in Arabic only. CreateBlenderInvoiceRequest accepts roast_type/grind_level. Class, route and permission names are CoffeeBlender*, api.coffee_blender.*, /coffee-blender and useCoffeeBlender.js. Meanwhile lang/ar/inventory.php:105-160 was already reworded generically ('تحضير', 'تركيبة').
- **Impact:** Each tenant using the composite feature (gift sets, spice mixes, kits) gets roast and grind text on its official invoices and prints, in Arabic even with the English locale. The labels and the persisted data no longer agree.
- **Recommendation:** Structural fix: replace roast_type/grind_level with a generic key/value 'attributes' array, or per-tenant configurable recipe attributes. Build the note from translation keys. Cosmetic fix: rename to CompositeItem/Recipe (controller, routes, composable, view) while keeping the old routes as aliases during migration.

### 56. [MEDIUM] Blender 'production' wording promises a finished-goods item, but the action only sells raw components
- **Location:** `D:\projects\sroor\backend\lang\ar\inventory.php:117`
- **Effort:** XL
- **Evidence:** The keys 'produce_blend_now' and 'blend_production_success' say the finished product is deposited in the warehouse. CreateBlenderInvoiceAction only calls InvoiceService::confirmInvoice with the component items. No finished-good Item, production order or stock-in movement exists. There is no recipe/BOM table in the tenant migrations.
- **Impact:** Recipes can't be saved or reused, and pre-produced composite stock (for example packing 1 kg bags in advance) is impossible. Reports show sales of raw components, not of the product the customer bought. This is a real gap for the wholesale and by-weight shops the SaaS targets.
- **Recommendation:** Introduce item_type (simple/composite) plus an item_components table (component_id, qty in base unit). Add a ProduceCompositeAction that consumes components and adds finished stock in one transaction with lockForUpdate, and allow selling composites as normal invoice lines.

### 57. [MEDIUM] Unit of measure is a free-text Arabic string; piece-vs-weight rules depend on a hardcoded word list
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:66`
- **Effort:** L
- **Evidence:** `$discreteUnits = ['قطعة','حبة','علبة',…,'piece','pcs',…]` is used to block fractional quantities, and only in confirmInvoice. updateInvoice at about line 376 has no such check. The items.unit default is 'كجم' (migration 160001:17). ItemDTO.php:13/28, ItemResource.php:28, POSItemResource.php:29, PurchaseItemResource.php:19, ReturnItemResource.php:19, StockTransferItemResource.php:19, ReportPrintController.php:186 and GetDashboardOverviewAction.php:133/169 all fall back to 'كجم'. InvoiceResource.php:53 and GetPOSBootstrapDataAction.php:94 fall back to 'قطعة'. The unit list lives in settings strings (SettingController.php:50, SuperAdminApiController.php:374, GetTenantDetailsAction.php:31/57).
- **Impact:** A tenant-defined unit such as 'زجاجة' or 'Bottle' allows fractional piece sales. Editing an invoice skips the integer check entirely. Different screens show conflicting default units. There is no multi-unit item (carton = 12 pieces) and no conversion, which wholesale needs.
- **Recommendation:** Add a units table (name key, is_fractional/allow_decimal, dimension) with items.unit_id, plus optional item_units for pack conversions. Base the integer check on unit.allow_decimal and apply it in create, update, purchases, returns and transfers. Remove the 'كجم' fallbacks.

### 58. [MEDIUM] Wholesale price is an alias for min_selling_price (the price floor), and the floor is never enforced server-side
- **Location:** `D:\projects\sroor\backend\app\Models\Item.php:44`
- **Effort:** M
- **Evidence:** getPriceWholesaleAttribute returns min_selling_price ?? selling_price. GetPOSBootstrapDataAction.php:89 does the same. Items have no wholesale_price column. customers.price_tier is a string limited to retail/wholesale. The wholesale price is applied on the client in usePOSCart.js:54. InvoiceService accepts any client unit_price; grep finds min_selling_price only in item create/update and POS bootstrap.
- **Impact:** A shop can't set a wholesale price different from its minimum allowed price. Any API client or blender payload can sell below the floor or cost, since components.*.unit_price is client-supplied and accepts min:0. Pricing tiers don't generalize beyond two hardcoded strings.
- **Recommendation:** Add items.wholesale_price, or better a price_lists/item_prices table keyed by tier. Resolve price server-side in a pricing service (CustomerPricingHelper is the natural home). Enforce the min_selling_price floor in InvoiceService, with a permission-based override.

### 59. [LOW] Treasury transfer checks the balance outside the transaction with no lock (double-spend race)
- **Location:** `D:\projects\sroor\backend\app\Services\TreasuryService.php:142`
- **Effort:** M
- **Evidence:** `$balances = $this->getBalances(...)` and the sufficiency bccomp run before `DB::transaction(` at line 151. Nothing is locked, so two concurrent requests both read the same available balance.
- **Impact:** The cash drawer has 1000. Two 800 transfers to the bank are submitted at the same time. Both pass the check and both are recorded, so the cash balance becomes -600. The balance is also checked 'as of $date', so a back-dated transfer can overdraw later days.
- **Recommendation:** Move the check inside the transaction and serialize per store and account. For example, lock a treasury/account row (or the store row) with lockForUpdate, then recompute the balance before inserting.
- **Verification:** The code defect is real, but nothing can trigger it today. In D:\projects\sroor\backend\app\Services\TreasuryService.php, transfer() calls getBalances() at line 142 and runs the bccomp check at line 146, both before DB::transaction at line 151. There is no lockForUpdate or advisory lock anywhere, and the check uses the `$date` the caller passes in. The balance is also a sum over payments, expenses and transfers rather than a stored row, so lockForUpdate could not protect it anyway. Two concurrent transfers would both pass the check, as claimed.

However, I found no caller of TreasuryService::transfer() anywhere:
- routes/api.php only has GET /treasury/summary and GET /reports/treasury.
- routes/web.php and routes/tenant.php have no treasury transfer route.
- TreasuryController only has summary().
- No Action, Job or Command calls ->transfer(). The only other TreasuryService calls are getBalances() and getTreasuryReport().
- The Vue SPA (resources/js) has no treasury-transfer API call. The old Livewire UI that likely called it was removed.

So the double-spend and back-dated overdraw scenarios cannot be reached through any endpoint right now. This is a latent money-integrity bug in dead code. It becomes high severity as soon as a transfer endpoint is wired up. Any fix needs a mutex, such as a lockForUpdate on a per-store/per-method row or an advisory lock, around both the check and the insert inside one transaction. Back-dated transfers also need a check against the current balance, not only the balance as of the chosen date.

Smaller issues on the same path: it falls back to `Auth::id() ?? 1`, its error messages are hard-coded Arabic strings, and it uses float number_format.

### 60. [LOW] Write endpoints without a FormRequest, and unvalidated input that persists settings
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\SettingController.php:100`
- **Effort:** S
- **Evidence:** SettingController@sendTestTelegram (L100-123) persists telegram_bot_token and chat_id from raw $request->input() inside a 'test' endpoint. PurchaseController@cancel (L145) builds CancelPurchaseDTO from $request->input('reason', '<Arabic literal>') with no FormRequest, while InvoiceController@cancel and StockTransferController@cancel do use one. AuthController@quickLogin uses $request->validate. The other writes without a FormRequest are toggle and destroy endpoints (Customer, Supplier, Item, Store, User, Expense, Category, Return, Trash, AppVersion).
- **Impact:** Unvalidated reason length/type reaches the DB. A test call silently overwrites production notification credentials. Validation and authorisation are inconsistent across similar verbs.
- **Recommendation:** Add CancelPurchaseRequest and SendTestTelegramRequest (validate, and don't persist; persist only through UpdateSettingsRequest). Optionally add a generic ToggleActiveRequest/DestroyRequest whose authorize() uses the policy.

### 61. [LOW] Hardcoded user-facing Arabic and coffee-specific copy in controllers; `__() ?: 'literal'` fallbacks are dead code
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\ExpenseController.php:95`
- **Effort:** M
- **Evidence:** There are 63 occurrences of `__('key') ?: '<Arabic literal>'`. __() returns the key when the translation is missing, so the fallback never fires and the code only hides missing keys. Literal-only messages: PaymentController:97,122 (concatenated Arabic + number_format), SuperAdminApiController:128,208,214,233,239,255,260,276,394,410-433, and the ExpenseController index cost-centre and quick-category lists (L95-117), which include coffee-specific 'صيانة مطاحن ومعدات' and 'أكواب ورقية'. ReportController:176 has the docblock 'Coffee Items'.
- **Impact:** English-locale tenants see Arabic text. Generic retail and wholesale tenants see coffee-shop expense categories, which conflicts with the generic-SaaS positioning.
- **Recommendation:** Move the lists to tenant settings or seeders with lang keys. Use __('...') only, with keys in both lang/ar and lang/en, and drop the ?: fallbacks.

### 62. [LOW] Jobs are dead code and are not production-ready
- **Location:** `D:\projects\sroor\backend\app\Jobs\SendTelegramDatabaseBackupJob.php:11`
- **Effort:** S
- **Evidence:** None of the 4 jobs is dispatched anywhere (grep for dispatch finds no callers). The schedule calls the commands synchronously. The jobs set no $tries/$backoff/$timeout/ShouldBeUnique. QueueTenancyBootstrapper is enabled, but the scheduler would dispatch them in central context. The queue worker is started with `queue:work --stop-when-empty` every minute from the scheduler (console.php:10).
- **Impact:** Developers may assume background processing exists when it does not. If the jobs are wired up as they are, a backup or summary job runs without a tenant and retries with no backoff.
- **Recommendation:** Either delete the jobs or make them the real path: one job per tenant, dispatched within tenant context, with tries/backoff/timeout and uniqueness, and a supervised worker.

### 63. [LOW] Dead or duplicated service code: TenantScope, StockService::adjustStock/depositStock, ActivityLogService alongside AuditLogService
- **Location:** `D:\projects\sroor\backend\app\Scopes\TenantScope.php:9`
- **Effort:** S
- **Evidence:** TenantScope is not applied to any model (grep finds no usage), and it filters by a tenant_id column, which does not fit DB-per-tenant. StockService::adjustStock/depositStock have no callers. TreasuryService::transfer and ReturnService write both AuditLog and ActivityLog for the same event. ActivityLogService::log adds 1-2 extra existence queries per call. `Auth::id() ?? 1` appears in StockService, ReturnService and TreasuryService, which credits system actions to user #1.
- **Impact:** Confusing architecture, misleading audit trails (actions without a real user are credited to user 1), and extra queries on every money write.
- **Recommendation:** Delete TenantScope and the unused stock methods. Decide whether AuditLog (data diff) or ActivityLog (human feed) is the source of truth and document it. Use a nullable user_id instead of falling back to 1.

### 64. [LOW] POSItemResource runs one StoreStock query per item (N+1)
- **Location:** `D:\projects\sroor\backend\app\Http\Resources\POSItemResource.php:14`
- **Effort:** S
- **Evidence:** `\App\Models\StoreStock::where('store_id', $storeId)->where('item_id', $this->id)->value('quantity')` runs inside toArray. It is used by GetTenantDashboardAnalyticsAction:164 for low-stock items (take(6)). That low-stock query itself filters on global items.current_stock rather than store stock.
- **Impact:** Currently bounded at 6 extra queries per dashboard load, but it becomes a real N+1 if the resource is reused for lists. The low-stock radar is also not store-aware.
- **Recommendation:** Join or eager-load store_stocks the way GetPOSBootstrapDataAction already does (COALESCE join), and filter low stock on store_stocks.quantity <= min_stock.

### 65. [LOW] ReportPrintController (597 lines) is unreachable dead code, and its inventory tab ignores the store filter
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\ReportPrintController.php:349`
- **Effort:** S
- **Evidence:** grep finds no route for ReportPrintController (web.php:180 `/reports/print` is a separate closure). printInventoryReport($storeId, ...) values `Item::active()->...->get()` with items.current_stock and never uses $storeId. The file is full of hardcoded Arabic strings, emoji labels and `(float)` money formatting.
- **Impact:** This is maintenance debt and a trap: anyone who wires it up gets a wrong per-store inventory valuation, and logic is duplicated against the web.php closures and the Report Actions.
- **Recommendation:** Delete the controller, or fold it into the Report Actions with a single print route under tenant auth.

### 66. [LOW] Central billing money columns use decimal(10,2), and subscriptions cascade-delete with their plan
- **Location:** `D:\projects\sroor\backend\database\migrations\2019_09_15_000015_create_subscriptions_table.php:15`
- **Effort:** S
- **Evidence:** `$table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();` and `$table->decimal('amount', 10, 2)`. plans.price_monthly and price_yearly are decimal(10,2) too (2019_09_15_000005 lines 17-18). No index on subscriptions(tenant_id,status,ends_at) beyond the FK. There is currently no plan-delete action (app/Actions/Plans has only Get/Update).
- **Impact:** If plan deletion is ever added, or someone runs a manual SQL delete, every subscription and payment-history row for that plan disappears. The money precision is inconsistent with the decimal(12,3) standard.
- **Recommendation:** Change the FK to restrictOnDelete (soft-delete plans instead) and align the money columns to decimal(12,3) in a new central migration.

### 67. [LOW] Routing/versioning is split three ways with 28 closures and an unversioned alias
- **Location:** `D:/projects/sroor/backend/routes/api.php:226`
- **Effort:** M
- **Evidence:** routes/api.php is fully under /api/v1 except the unversioned alias GET /api/central/tenants/resolve (line 226). Only SuperAdminAppVersionController lives in the Api\V1 namespace. route:list counts 28 Closure actions, including heavy business logic in closures: daily-journal print money math (tenant.php:97-157), reports print with hardcoded Arabic titles and fake 0.000 treasury rows (web.php:192-246), theme toggle and store switch with hardcoded Arabic messages (tenant.php:280-287). Several routes have no name (e.g. POST /login, tenant.php:169 stores/switch). Super-admin endpoints share the tenant API group instead of a central-only route file.
- **Impact:** Closures block route:cache, are untestable as units and duplicate money calculations outside Services. The reports print renders fabricated zero treasury balances. Hardcoded Arabic breaks the en locale.
- **Recommendation:** Move closures into controllers and Actions, put the super-admin API in its own central route file and middleware, drop the unversioned alias once clients are updated, and replace hardcoded strings with __() keys.

### 68. [LOW] Dead code and dev-only tooling shipped in the production app
- **Location:** `D:/projects/sroor/backend/app/Http/Controllers/ReportPrintController.php:15`
- **Effort:** S
- **Evidence:** ReportPrintController (597 lines) is not referenced by any route (grep across routes/ and app/ finds only its own class declaration). POSController@index and AuthenticatedSessionController::create just return view('app'). PopulateRealisticTenantDataCommand ('tenant:populate-realistic-data --fresh') TRUNCATEs invoices, payments, stock_movements, items, customers, suppliers and activity_logs with FOREIGN_KEY_CHECKS=0 and has no environment or confirm() guard. Livewire is still installed as a transitive dependency of laravel/pulse (composer.lock:1486, 2421; LivewireServiceProvider is in bootstrap/cache/services.php), even though the stack rule says no Livewire. package.json is clean of @inertiajs and livewire.
- **Impact:** Running the populate command by mistake against a production tenant wipes all approved financial documents, which breaks the no-hard-delete rule. The unused controller adds maintenance noise. Pulse keeps Livewire in the production stack.
- **Recommendation:** Delete ReportPrintController (or wire it in place of the web.php closures). Guard the populate command with app()->isProduction() abort plus confirm(), or move it to a dev-only package. Decide whether Pulse (and therefore Livewire) is worth keeping.

### 69. [LOW] Hardcoded user-facing Arabic strings and inline money formatting in controller responses
- **Location:** `D:/projects/sroor/backend/app/Http/Controllers/Api/PaymentController.php:97`
- **Effort:** S
- **Evidence:** 17 'message' => '<Arabic literal>' responses in controllers and actions, e.g. PaymentController:97,122 build a message with number_format((float)$validated['amount'], 2) . ' ج.م'. SuperAdminApiController:233 has a success message with a check-mark character. ApiQuickLoginAction:93 falls back to 'مؤسسة تجارية'.
- **Impact:** The en locale shows Arabic. The float cast and 2-decimal rounding of a DECIMAL(12,3) amount violate the money rule (display only, but 1.2345 is shown as 1.23). The EGP currency is hardcoded, which a generic SaaS cannot assume.
- **Recommendation:** Use __() keys with :amount placeholders present in both lang/ar and lang/en, format with a bcmath-based money formatter, and take the currency from tenant settings.

### 70. [LOW] Seeders and plan copy are coffee-branded (mills/roasters); demo seeders contain coffee catalogues
- **Location:** `D:\projects\sroor\backend\database\seeders\PlansAndFeaturesSeeder.php:61`
- **Effort:** S
- **Evidence:** Plan descriptions mention 'المطاحن' at lines 61, 102 and 143 ('استوديو خلط البن'). Feature blender.access is named 'استوديو توليف وخلاط البن' with a coffee-cup icon (line 25). RealisticEnterpriseDataSeeder.php:64-181 and RichDemoDataSeeder.php:44-192 seed coffee stores, categories and items. CoffeeItemsSeeder is a disabled stub. ExpenseController.php:114 hardcodes the quick expense category 'صيانة مطاحن ومعدات'. Comments in api.php:78/161, tenant.php:215, GetTenantDashboardAnalyticsAction.php:121 and ReportController.php:176 say 'Coffee'. DownloadLatestApkAction.php:34-37 falls back to 'sroor-cofe-erp-2m.apk' and 'sroor-coffee-erp-v1.0.apk'. Good news: TenantProvisionerService only seeds permissions, the main store, the admin user and company settings, with no coffee data.
- **Impact:** This is cosmetic, but it is visible on the pricing page and super-admin panel, and in the expense quick-picks shown to every tenant (a phone-accessories shop sees 'grinder maintenance'). It weakens the generic SaaS positioning.
- **Recommendation:** Rewrite plan and feature copy generically and move it to translation keys. Make expense quick categories tenant settings. Rename CoffeeItemsSeeder and the demo seeders into per-vertical demo packs (coffee, accessories) that are selected explicitly.

### 71. [LOW] Dashboard top-selling ranks items by SUM(quantity) across mixed units
- **Location:** `D:\projects\sroor\backend\app\Actions\Dashboard\GetTenantDashboardAnalyticsAction.php:135`
- **Effort:** S
- **Evidence:** InvoiceItem aggregation `DB::raw('SUM(invoice_items.quantity) as total_qty') … ->orderByDesc('total_qty')` mixes kg, pieces and cartons in one ranking.
- **Impact:** In a mixed catalogue, 3 kg of a bulk item ranks below 4 phone cables. The 'top sellers' widget is misleading for shops that sell by weight and by piece.
- **Recommendation:** Rank by revenue (total_price) or gross profit, and show quantity with its unit only as secondary info.

### 72. [LOW] CreateBlenderInvoiceRequest creates a customer record during validation using hardcoded Arabic names
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\CreateBlenderInvoiceRequest.php:43`
- **Effort:** S
- **Evidence:** prepareForValidation() looks up a customer named 'نقدي عام' or 'عميل نقدي' or with phone '0000000000', and calls Customer::create(...) if none exists, outside any transaction, before authorization or validation of the other fields. The controller line 52 also has a hardcoded Arabic fallback after __() (with an emoji), and __() never returns falsy, so that branch is dead.
- **Impact:** A request that later fails validation still leaves a side-effect write. The walk-in customer is found by a localized name, which breaks for English tenants or renamed customers and can match a real customer whose phone is a placeholder.
- **Recommendation:** Store the walk-in customer id as a tenant setting created at provisioning, and resolve it in the DTO or Action. Remove writes from the FormRequest and the dead fallback string.

### 73. [INFO] Per-controller conformance summary (148 public controller methods scanned by reflection)
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers`
- **Effort:** XL
- **Evidence:** Totals: 148 public methods. 85 have no FormRequest, 92 return no Resource (raw arrays/models or views/files), and 56 have neither. Write paths are mostly conformant (FormRequest -> DTO::fromArray(validated) -> Action -> Resource): Customer/Supplier/Item/Store/User/Expense store and update, Invoice/StockTransfer store and cancel, Shift open/close, Purchase/Return store. Per controller (methods / without FR / without Resource / worst inline method): ActivityLog 2/0/2 (route can:logs.view); AppUpdate 2/0/file; Auth 5/4/5 (quickLogin, workspaceUsers public); CategoryApi 4/2/4 (index L29 52 ln); CoffeeBlender 2/0/1; Customer 8/4/3 (index L38); DailyJournal 1/0/1 (+3 dead routes); DashboardApi 1/1/1; Expense 5/2/1 (index L33 100 ln); Invoice 4/2/0 (index L33 85 ln, +4 dead routes); Item 8/5/3 (index L39 87 ln, lowStock L267 43 ln); Payment 3/1/3 (no DTO/Action, index L27); Permission 1/1/1; Pos 4/2/3; Profile 2/1/0; Purchase 5/4/1 (index L31 72 ln); Report 10/2/10 (all raw arrays; itemCard inline); Return 4/3/1 (index L28); Role 2/1/2; Setting 3/2/3 (index L27 48 ln, +7 dead routes); Shift 5/3/1; StockTransfer 4/2/0 (index L30 80 ln); Store 9/5/2 (show has no authz at all, L107); SuperAdminApi 16/7/15; Supplier 8/4/3; SystemContext 2/2/2; Trash 3/3/3; Treasury 1/1/1 (summary 73 ln); User 6/4/3 (index L36 70 ln raw array); V1/SuperAdminAppVersion 4/3/4; Auth/AuthenticatedSession 3/2/3; Export 4/4/4 (no authz); POSController 4/3/4; ReportPrintController 1 public + 9 protected (unrouted). Controllers with no dedicated Resource: Report, Dashboard, Trash, Treasury, DailyJournal, Payment, Setting, Role, Permission, SystemContext, SuperAdminApi (only PlanResource on one call). ActivityLog has ActivityLogResource but the controller does not use it. Treasury has no Action. DTO money typing: every DTO uses string money except POSInvoiceDTO and POSInvoiceItemDTO (float). No FormRequest uses `decimal:0,3`.
- **Impact:** Gives the lead a baseline for tracking refactor progress. Read endpoints are where conformance breaks down.
- **Recommendation:** Prioritise in this order: (1) quick-login, (2) the POS contract bugs, (3) store authorisation middleware, (4) List<Noun>Request + Pipeline filters + Get<Noun>ListAction for the 13 index methods, (5) Resources for Report, Treasury, SuperAdmin and Payment.

### 74. [INFO] God-service candidates
- **Location:** `D:\projects\sroor\backend\app\Services\InvoiceService.php:1`
- **Effort:** L
- **Evidence:** InvoiceService has 716 lines, TreasuryService 525 and PurchaseService 500, and TelegramService (433) mixes HTTP transport, report queries and message formatting. Models are thin: no booted() hooks, no #[ObservedBy]. Accessors are pure (bcmath or labels). The only observer is TenantObserver, registered in AppServiceProvider:53, and it only logs.
- **Impact:** These services are hard to test and change, and report/query logic sits inside the notification transport.
- **Recommendation:** Split InvoiceService into create, update (reverse and reapply) and cancel actions that share StockService/CustomerBalanceService. Split TelegramService into a transport client and per-report builders.

### 75. [INFO] No automated tests cover the report, treasury and export services
- **Location:** `D:\projects\sroor\backend\tests\Feature`
- **Effort:** M
- **Evidence:** grep finds no test referencing ProfitLossService, InventoryAnalyticsService, TreasuryService, ExportService or reports/print. php artisan test (sqlite :memory:) gives 306 tests: 305 passed, 1 failed (Tests\Feature\Api\AppUpdateApiTest::test_download_apk_throws_404_when_valid_platform_has_no_apk_file expects 404 and receives 200). The sqlite test database cannot reveal index or whereDate performance problems.
- **Impact:** The P&L, treasury and cache-staleness bugs above went undetected, and any refactor of the reports has no safety net.
- **Recommendation:** Add exact-decimal feature tests for P&L (historical cost, returns), multi-store treasury, and export authorization and tenant isolation. Consider a MySQL CI job for index and EXPLAIN checks.

### 76. [INFO] Legacy tenant web route maps GET /coffee-blender to the POST calculate action
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:216`
- **Effort:** S
- **Evidence:** `Route::get('/coffee-blender', [CoffeeBlenderController::class, 'calculate'])` uses CalculateBlendCostRequest, which requires components. The POST /coffee-blender/invoice route is gated by can:items.create, while the API version uses invoices.create/pos.access.
- **Impact:** A GET page load always returns 422. Different permissions guard the same operation on the two route files.
- **Recommendation:** Delete the leftover web routes, since the SPA uses /api/v1, or align them with the API permissions.

## Open questions
- Does the production central database (default connection on the central host) contain legacy tenant tables (customers, invoices, items) from the pre-SaaS single-tenant install? If so, the unauthenticated web.php export/print routes leak data; if not, they only return 500.
- On tenant domains, which registration wins for the URIs defined in both web.php and tenant.php (e.g. /invoices/{id}/print/thermal, /daily-journal/print)? Run `php artisan route:list --path=invoices` on a staging tenant host to confirm whether the tenancy-unaware web.php closure is the one served.
- Is quick-login intended as a product feature (shared POS terminal)? If so, which credential (PIN, device pairing) should replace it before any tenant is sold?
- ApiTokenAuth step 3 (lines 50-60) maps a central 'admin' token to any tenant user with the same phone. Auth is outside this sub-area, so the auth/security owner should confirm whether that is a sanctioned cross-tenant bridge.
- Should the trash restore/force-delete feature cover financial documents (returns) at all, or only master data (items, customers, suppliers)?
- Does production set DB_CACHE_CONNECTION, or use a non-database CACHE_STORE? This decides whether the P&L/ABC cache keys can actually collide between tenants.
- Which TELEGRAM chat is configured in production .env, and has the nightly backup:telegram already been sending central DB dumps there?
- Is there an intended future per-tenant scheduler (tenants:run), or should tenant notifications be dropped from the central schedule?
- Should opening balances be migrated? Existing customers/suppliers created with an opening balance may already have lost it after their first transaction, so a data audit is needed.
- The user message 'انت شغلت برنش الساس' ('you ran the SaaS branch'): yes, this analysis ran on feature/multi-tenant.
- What does the production central database contain? If it is the restored legacy single-tenant Sroor database, the unauthenticated web.php export and report routes leak real data. If it is a clean central database, they just return 500. Only an ops check can confirm which.
- Is CACHE_STORE=database guaranteed on every environment (production, staging, queue workers)? P&L/ABC cache isolation currently depends on that setting and on DB_CACHE_CONNECTION being unset.
- Should a sales return reduce the P&L in the API version (GetProfitLossReportAction)? It currently ignores returns, while ProfitLossService subtracts them.
- Out of sub-area, noticed in passing: routes/api.php has no throttle or rate-limit middleware, including on the v1 login. This needs confirmation by the security sub-agent.
- Should the AppUpdateApiTest failure (download_apk returns 200 where 404 is expected) be treated as a regression? It may depend on an APK file present in the local workspace.
- Does the production CENTRAL database still contain legacy single-tenant tables (invoices, customers, items...)? If it does, the unauthenticated web.php export/print routes are a live data leak, not just broken features.
- Who holds the 'admin' role in the central users table in production? ApiTokenAuth fallback 3 and the Telescope/Pulse gates trust that role across all tenants.
- Pulse uses PULSE_DB_CONNECTION=null (the default connection). During tenant requests the default connection is 'tenant' and there are no pulse_* tables in database/migrations/tenant. Is Pulse ingest silently failing or erroring for every tenant request in production?
- StoreScope is appended to the 'web' group and calls Auth::check() and Store queries. In tenant.php the 'web' group runs before InitializeTenancyByDomain, so could it resolve the session user and stores against the central DB? Needs a runtime check of middleware priority.
- Is the quick-login feature a deliberate business requirement (shared POS terminal)? If so, what device-trust mechanism is acceptable (terminal pairing token, per-cashier PIN)?
- Is tenant '2m' (2m.baraa-solutions.com) a live paying tenant on Hostinger? If so, PopulateRealisticTenantDataCommand should be treated as critical and disabled on that host now.
- Should the blender/composite feature create a finished-good stock item (production), or stay a 'sell components as one invoice' tool? The translations promise production but the code doesn't do it.
- Is min_selling_price meant as the wholesale price or as a hard price floor? The code uses it as both.
- lang/ar/inventory.php defines roast_light, roast_medium, blend_notes, blender_title and others twice (for example lines 121 and 157, 138 and 174). Is lang/en/inventory.php in parity, and which definition is intended?
- DatabaseSeeder.php:21-42 creates two super-admin accounts with hardcoded weak default passwords and real-looking phone numbers (type: hardcoded default credentials). Has this seeder ever been run against production?