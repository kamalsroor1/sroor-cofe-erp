# SaaS & multi-tenancy core — Score 2/10

> Branch `feature/multi-tenant` · 2026-10-07 · read-only multi-agent analysis · secrets redacted

## Executive summary

The design underneath is sound. It uses stancl/tenancy v3 with a separate database per tenant. Tenant and Domain models are pinned to the central connection. Provisioning is a layered pipeline (Request, DTO, Action, TenantProvisionerService), Sanctum tokens live in each tenant's own database, and the central schema already has plans, plan_features, subscriptions and tenant status/trial fields. That is a reasonable base for a generic retail/wholesale/by-weight SaaS.

The way it is wired today, though, the platform is not safe to sell. Three verified critical problems break the tenancy boundary.

**1. Anyone can log in as anyone, with no password.**
- POST /api/v1/auth/quick-login and GET /api/v1/auth/workspace-users sit outside ApiTokenAuth (routes/api.php:28-29). They have no throttle.
- ApiQuickLoginAction.php:26-55 issues a ['*'] token for any active user found by phone, email or id. It checks no password, PIN or device.
- So an anonymous attacker who sends X-Tenant:<any> with login '1' gets that tenant's admin.
- On the central host it yields the seeded super_admin.
- A test locks this behaviour in (AuthApiTest.php:287), and the UI defaults to it (LoginView.vue:284).

**2. Super-admin is not a central-only identity.** Three things combine:
- Gate::before (AppServiceProvider.php:37-45) allows everything for hasRole('super_admin') or for two hardcoded phone numbers. The phone check runs before the deny for 'super_admin.*'.
- PermissionsSeeder (lines 61-77) seeds the super_admin role and the super_admin.access permission into every tenant DB (TenantProvisionerService.php:87). StoreUserRequest:23 and UpdateUserRequest:26 accept any role that exists.
- The /super-admin/* group (api.php:196) sits inside the ResolveApiTenancy and ApiTokenAuth group, guarded only by can:super_admin.access, and acts on the central tenants table through CentralConnection.

Any tenant admin can give a user role=super_admin. Any tenant user, even a cashier, can set their own phone to an allowlisted number through PUT /profile. Either way they take over the control plane:
- list and view every tenant
- suspend other tenants, or extend their own subscription by up to 3650 days
- override paid features
- create tenants and run migrations

Delete-tenant and update-db-config are currently broken only by parse errors. That is accidental protection, not a control.

**3. The cache is shared across tenants.**
- CacheTenancyBootstrapper is disabled (config/tenancy.php:36).
- The database cache store is created at boot, through Gate::before and Spatie's PermissionRegistrar, while the default connection is still central. So every tenant reads and writes the central cache table.
- The inventory/ABC report key erp_abc_{store}_{from}_{to}_{sort} (InventoryAnalyticsService.php:29) has no tenant id, and the default filters collide across tenants. One tenant's item names, revenue, COGS and profit are served to other tenants for 15 minutes.
- The Spatie permission cache uses one global key ('spatie.permission.cache'), so role and permission maps can cross tenants.

The test suite can catch none of this. It runs on a single sqlite :memory: database with CACHE_STORE=array, and one test enforces the passwordless login. Billing entitlements also cannot be trusted while any tenant can make itself super-admin.

The score is 2/10. The structure is good and the fixes are mostly small and well localized, but today the platform is open to unauthenticated full compromise and leaks data across tenants in normal use.

## Top risks
- CRITICAL: Anyone can log in without a password and list users. POST /api/v1/auth/quick-login and GET /api/v1/auth/workspace-users are guest routes (backend/routes/api.php:28-29). ApiQuickLoginAction.php:26-55 issues a ['*'] Sanctum token for any active user found by phone, email or id, with no password, PIN, device binding or throttle. Sending X-Tenant:<any tenant> with login '1' takes over that tenant's admin. Sending it to the central host with no tenant header and the seeded super-admin's phone or id (DatabaseSeeder.php:17-41) gives a central super_admin token.
- CRITICAL: Tenant users can escalate to super-admin through a hardcoded phone allowlist. Gate::before (backend/app/Providers/AppServiceProvider.php:38, and line 49 for viewPulse) returns true for two hardcoded phone numbers, checked against whichever users table is active. The same allowlist appears in TelescopeServiceProvider.php:82, UserResource.php:19, routes/web.php:38, ToggleTenantStatusRequest:13 and OverrideTenantFeatureRequest:13. Any tenant user, even a cashier, can set that phone through PUT /profile (UpdateProfileRequest.php:13,23; UpdateProfileAction.php:26). A tenant admin can do the same through POST/PUT /users. That user then passes can:super_admin.access.
- CRITICAL: Every tenant DB contains an assignable 'super_admin' role. PermissionsSeeder.php:61-77 runs per tenant (TenantProvisionerService.php:87) and creates the role with every permission. StoreUserRequest.php:23 and UpdateUserRequest.php:26 validate the role only with exists:roles,name, and CreateUserAction:29 and UpdateUserAction:35 call syncRoles with no filter. A tenant admin can therefore promote themselves or a new user to platform super-admin.
- CRITICAL: The super-admin control plane is reachable from tenant context. The /api/v1/super-admin/* group (routes/api.php:196) sits inside the ResolveApiTenancy and ApiTokenAuth groups, guarded only by can:super_admin.access, and SuperAdminApiController never checks tenancy()->initialized. Because Tenant uses CentralConnection, an escalated tenant user can list and view every tenant, suspend other tenants, extend their own subscription by up to 3650 days (a billing bypass), override features, create tenants and run migrations. StoreTenantRequest::authorize returns true.
- CRITICAL: Cross-tenant cache leaks. CacheTenancyBootstrapper is commented out (config/tenancy.php:36) and CACHE_STORE=database. The store is created at boot, on the central connection, via AppServiceProvider::boot calling Gate::before, which resolves Spatie's PermissionRegistrar, which calls initializeCache. The tenant cache tables are never used. The key erp_abc_{store}_{from}_{to}_{sort} (backend/app/Services/InventoryAnalyticsService.php:29), behind /api/v1/reports/inventory and /comprehensive, serves one tenant's item, revenue, COGS and profit data to others for 15 minutes. Default filters (main store id 1, this month) make collisions near-certain.
- HIGH: The Spatie permission cache is global. The key 'spatie.permission.cache' (config/permission.php:209) holds one roles/permissions map for all tenants, rebuilt from whichever tenant loaded or flushed it last (UpdateRolePermissionsAction.php:26). Authorization decisions can be computed from another tenant's role map for up to 24h.
- HIGH: Two destructive super-admin actions currently fail php -l because their variables were stripped: backend/app/Actions/Tenants/DeleteTenantAction.php:12 and UpdateTenantDatabaseConfigAction.php:11. Tenant deletion and DB re-pointing therefore throw. Once someone repairs these files, the escalation paths above can drop any customer's database or repoint its DB config.
- MEDIUM: Plan, Subscription, PlanFeature and Setting do not pin the central connection. Super-admin and billing code running while a tenant is initialized, or when the SPA sends X-Tenant on super-admin calls, can hit the tenant DB, either failing with 'table not found' or reading and writing the wrong data. POST /super-admin/settings has no central settings migration behind it.
- MEDIUM: Token handling is weak. ApiTokenAuth accepts tokens from the ?api_token= query string, where they leak into logs and Referer headers. Per the sub-agent notes, ApiTokenAuth.php:51-63 and ApiLoginAction.php:31-53 also have central-DB fallbacks that let central 'admin' users act as a master key across tenants. This was not independently verified as critical and needs confirmation.
- MEDIUM: Tests give false confidence. phpunit.xml uses a single sqlite :memory: DB with CACHE_STORE=array, so cross-tenant cache and auth bleed cannot be caught. AuthApiTest::test_quick_login_succeeds_without_password actively enforces the vulnerability. No test proves that a tenant user gets 403 or 404 on /super-admin/*.

## Quick wins
- Remove POST /auth/quick-login and GET /auth/workspace-users from the guest group in backend/routes/api.php:28-29, either deleting them or moving them behind ApiTokenAuth. Invert AuthApiTest::test_quick_login_succeeds_without_password so it asserts 404/401 for guests, and switch LoginView.vue:284 to default to password mode.
- Delete the hardcoded phone allowlist everywhere it appears: AppServiceProvider.php:38 and 49, TelescopeServiceProvider.php:82, UserResource.php:19, routes/web.php:38, ToggleTenantStatusRequest:13, OverrideTenantFeatureRequest:13 and any other FormRequest that uses it. Super-admin must depend only on role plus central context.
- Add an EnsureCentralContext middleware to the /super-admin/* group (routes/api.php:196) that aborts with 404 when tenancy()->initialized, and also check it in Gate::before for super_admin.* abilities. Add a regression test: a tenant user with role super_admin, or with one of the old allowlisted phone numbers, gets 403 or 404 on /super-admin/tenants.
- Stop seeding the 'super_admin' role and the 'super_admin.access' permission into tenant DBs by splitting PermissionsSeeder into central and tenant parts. Add Rule::notIn(['super_admin']) plus an allowlist of assignable roles to StoreUserRequest:23 and UpdateUserRequest:26. Write a one-off tenant command to remove the role from existing tenant DBs, run only after review.
- Add tenant('id') to the report cache keys in InventoryAnalyticsService.php:29 and ProfitLossService.php:29. Fix clearCache() so it matches the real date-suffixed keys, and call it from stock and sales events.
- Make the Spatie permission cache per tenant. Listen for TenancyBootstrapped and RevertedToCentralContext, set config('permission.cache.key') to 'spatie.permission.cache.'.tenant('id'), call Cache::forgetDriver(), then call PermissionRegistrar::initializeCache() and clearPermissionsCollection().
- Add throttle middleware to every auth route, and stop accepting ?api_token= in ApiTokenAuth.
- Ops follow-up for the CTO, not for automation: rotate the seeded super-admin credentials and all super-admin tokens, then check production access logs for POST /api/v1/auth/quick-login and /super-admin/* calls from unknown IPs.

## Strategic items
- Split identity and context properly. Make super-admin a central-only identity: a central guard and CentralUser, with routes physically separated from tenant routes, for example a central admin host or the api/v1/central prefix that already bypasses tenancy. Tenant routes should require an initialized tenant and return 404 otherwise. Decide explicitly whether any tenant API call on the central host is ever valid.
- Enable Stancl CacheTenancyBootstrapper on a taggable store (redis on the target hosting), or else build a single tenant-aware cache-key helper that all Cache:: usage goes through. Make sure the cache store is not resolved before tenancy starts, or purge it on every tenancy switch.
- If fast cashier switching is a real POS requirement, redesign it as a PIN switch that needs an already-authenticated, device-bound and store-bound terminal token. The PIN should be hashed and throttled, and the switch should be refused for admin and super_admin roles and in central context.
- Pin every platform model to the central connection: Plan, Subscription, PlanFeature, platform Setting and AppVersion (already done). Add central migrations for any platform settings table. Then build a plan-entitlement middleware on TenantFeatureManagerInterface that enforces plan limits (max_users, max_stores, max_items, max_invoices_per_month, max_storage_mb) and feature flags server-side, along with subscription expiry and suspension.
- Build a real multi-tenant test harness. Use two file-based sqlite (or MySQL) tenant DBs, a real cache store and real tenancy initialization. Cover token replay across tenants, report and permission cache bleed, super-admin isolation and provisioning. Write test tenant DBs to a temp dir, because the current SuperAdminApiTest run creates backend/database/tenant_wadi-elbon.sqlite in the repo.
- Lifecycle robustness: run tenant migrations for all tenants on deploy (currently only '2m' is migrated), and queue provisioning. Fix the corrupted DeleteTenantAction and UpdateTenantDatabaseConfigAction, but only after the escalation fixes land. Make tenant deletion a soft-suspend with a grace period and an audited hard-drop, and resolve the Hostinger pre-created-DB versus CREATE DATABASE question.
- Audit the central DB in production for leftover single-shop operational tables and data (invoices, customers, items, settings). Then close the 'no tenant resolved' fallthrough and the unauthenticated web.php export and print routes that may expose them.
- Put Telescope and Pulse behind central-only super-admin access, and confirm APP_ENV, APP_DEBUG and TELESCOPE_ENABLED in production. Treat earlier Telegram DB backup dumps as compromised secrets (tokens, tenant DB passwords) and rotate them.

## Strengths
- The isolation model is real: one database per tenant using stancl/tenancy v3, with the Database, Filesystem and Queue bootstrappers enabled (config/tenancy.php:34-39). Tenant DBs are created and migrated through stancl's JobPipeline with an explicit tenant migration path.
- Tenant and Domain extend stancl's base models (CentralConnection), and AppVersion, failed_jobs, job_batches and Telescope storage are pinned to the central connection. That is the right instinct for platform data.
- Sanctum tokens live in each tenant DB and are checked by id plus hash after tenancy is switched, so a token from tenant A cannot authenticate in tenant B through X-Tenant.
- Provisioning has clean layers: StoreTenantRequest, CreateTenantDTO, ProvisionTenantAction, then TenantProvisionerService behind an interface. Seeding runs inside $tenant->run(), an initial Subscription row is created, trial dates are bounded, and Hostinger MySQL errors are mapped to readable Arabic messages.
- The central data model is already SaaS-shaped: plans with per-tier limits and a features JSON, a typed plan_features registry, tenant status, trial and subscription dates, a subscriptions table with billing cycle, amount and payment fields, and TenantFeatureManagerInterface merging plan features with overrides.
- Impersonation uses stancl's single-use ImpersonationToken with a 60-second TTL and a tenant-match check, stored centrally with a cascading foreign key to tenants.
- Setting::getCacheKey() (backend/app/Models/Setting.php:18-22) already puts tenant('id') in the key, which is the pattern to extend to all cache keys.
- The password login path is rate-limited through its FormRequest. Central api/v1/central/* routes deliberately bypass tenancy. The resolver blocks suspended tenants, and web tenant routes use InitializeTenancyByDomain plus PreventAccessFromCentralDomains with tenant-DB sessions.
- Gate::before already denies super_admin.* abilities to a plain tenant 'admin'. Once the phone allowlist and the tenant-seeded super_admin role are removed, that guard does its job.
- TenantResource exposes no DB credentials. SuperAdminAnalyticsService runs only cheap central aggregates and computes MRR with bcmath. ToggleTenantStatusAction extends from max(now, current end) so early renewals keep their days.

## Findings

### 1. [CRITICAL] Passwordless guest login (quick-login) plus guest user listing gives full takeover of any tenant and of the central super-admin account
- **Location:** `D:\projects\sroor\backend\app\Actions\Auth\ApiQuickLoginAction.php:26`
- **Effort:** S
- **Evidence:** routes/api.php:28-29 register POST /api/v1/auth/quick-login and GET /api/v1/auth/workspace-users OUTSIDE the ApiTokenAuth group. AuthController::quickLogin (AuthController.php:115-133) validates only 'login'. ApiQuickLoginAction::execute (lines 26-30) looks the user up with where('phone',$login)->orWhere('email',$login)->orWhere('id',$login), checks only is_active, and issues a Sanctum token with ['*'] abilities (line 53). No password, PIN, device binding, or throttle. AuthController::workspaceUsers (lines 93-110) returns id, name and phone/email for every active user to anonymous callers. LoginView.vue:284 makes 'quick' the default login mode, and AuthApiTest::test_quick_login_succeeds_without_password locks this behaviour in. On the central host with no X-Tenant, ResolveApiTenancy lets the request through on the central DB. There, DatabaseSeeder.php:17-37 creates the super-admin users with roles super_admin+admin and phone [REDACTED_PHONE].
- **Impact:** Anyone on the internet can (a) send POST /api/v1/auth/quick-login {login:'1'} with X-Tenant:<any tenant id/slug>, get that tenant's owner/admin token, and read, change or cancel all invoices, treasury and stock. (b) Send the same call on baraa-solutions.com with no tenant header, using login '[REDACTED_PHONE]' or '1', get a central super_admin token, and from there call /api/v1/super-admin/* to delete tenants, rewrite tenant DB credentials and run migrations. This is a full remote compromise of the platform and every customer.
- **Recommendation:** Remove the quick-login and workspace-users routes from the guest group now. If a POS fast-switch is a product need, re-design it as a PIN-based switch that requires an already-authenticated terminal token (device-bound, store-bound, throttled, hashed PIN). Never allow it on central context or for admin/super_admin roles. Delete or invert test_quick_login_succeeds_without_password and add tests that assert 404/401 for guests.
- **Verification:** I confirmed the finding from the code and found no guard that blocks it.

**The unauthenticated routes:**
- backend/routes/api.php:28-29 registers POST /v1/auth/quick-login and GET /v1/auth/workspace-users.
- Both routes sit inside the group that only has ResolveApiTenancy. They are outside the ApiTokenAuth group that starts at line 32.
- No throttle middleware exists. bootstrap/app.php adds none to the api group, and no route declares one.

**The login logic:**
- AuthController::quickLogin validates only `login` and the optional `device_name`.
- ApiQuickLoginAction::execute (lines 26-30) looks the user up with `where phone OR email OR id = $login`.
- It checks only `is_active` (line 47) and then issues `createToken(..., ['*'])` (line 55). It never checks a password, PIN or device.

**The user listing:**
- AuthController::workspaceUsers returns the id, name and phone-or-email of every active user, with no authentication.

**Tenant selection:**
- ResolveApiTenancy initializes any tenant named in the X-Tenant header, `?tenant=` query parameter or body field, looked up by id or domain.
- On a central host with no tenant identifier, it falls through to the central DB.
- So attack (a) works: any tenant's admin can be impersonated.

**Path to the central super-admin:**
- DatabaseSeeder.php:21-41 seeds two central users with roles super_admin and admin.
- The super-admin route group (api.php:196) is guarded only by `can:super_admin.access`.
- AppServiceProvider.php:37-38 has a Gate::before that returns true for the super_admin role, or for any user whose phone is in a hardcoded list of the two seeded numbers.
- So a quick-login token for that user passes every /super-admin/* route: destroyTenant, update-db-config and run-migrations.
- The FormRequests for those routes also authorize by role or by the same phone list.
- Attack (b) is therefore real.

**The behaviour is deliberate:**
- AuthApiTest::test_quick_login_succeeds_without_password (tests/Feature/Api/AuthApiTest.php:287) covers it.
- LoginView.vue:284 defaults to `loginMode = 'quick'`.

**Things that worsen it:**
- The Gate::before phone allowlist means any tenant-DB user whose phone matches one of those numbers also gets every gate check.
- ApiTokenAuth also accepts tokens from the `?api_token=` query string.

The only limit is that the central path depends on the seeded super-admin users existing in production with `is_active=true`. Even without them, any tenant can be taken over by guessing `login: '1'`, so critical stands.

### 2. [CRITICAL] Hardcoded phone allowlist in Gate::before: any tenant user who sets their own phone to [REDACTED_PHONE] becomes platform super-admin
- **Location:** `D:\projects\sroor\backend\app\Providers\AppServiceProvider.php:38`
- **Effort:** M
- **Evidence:** Gate::before returns true when $user->hasRole('super_admin') || in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]']). It runs for whatever User the request resolved, including tenant-DB users. UpdateProfileRequest.php:13 authorize() = user !== null, and line 23 allows any phone that is unique only within the tenant's own users table. UpdateProfileAction.php:26 writes $user->phone. The super-admin routes (api.php:196-221) sit inside the same ResolveApiTenancy+ApiTokenAuth group and are guarded only by can:super_admin.access. SuperAdminApiController uses Tenant::findOrFail (stancl CentralConnection), so tenants list, destroyTenant (DeleteTenantAction deletes the tenant, which fires DeleteDatabase), updateDatabaseConfig, toggleStatus and runTenantMigrations all work from inside a tenant context. The same allowlist also appears in viewPulse (line 49), TelescopeServiceProvider.php:82, UserResource.php:19 and several FormRequests (ToggleTenantStatusRequest, OverrideTenantFeatureRequest).
- **Impact:** Any authenticated tenant user, even a cashier, can run PUT /api/v1/profile {phone:'[REDACTED_PHONE]',...} and then DELETE /api/v1/super-admin/tenants/{otherTenantId}. That user can drop other customers' databases, repoint their DB config, or suspend them. This is cross-tenant data destruction and full platform takeover. The quick-login finding above makes it reachable without any credentials.
- **Recommendation:** Remove every phone and email literal from authorization code. Make super-admin a property of a central-DB identity only: authenticate super-admin with the central guard and CentralUser, and in Gate::before return true for super_admin abilities only when tenancy()->initialized === false and the user is a CentralUser. Move /super-admin/* into a central-only route group that refuses requests where tenancy is initialized (abort 404). Add a regression test in which a tenant user with phone [REDACTED_PHONE] gets 403 or 404 on /super-admin/tenants.
- **Verification:** The core finding holds. I checked each step in the code.

**How a tenant user escalates**
- AppServiceProvider.php:37-45 (Gate::before) returns true when `$user->phone` is in ['[REDACTED_PHONE]','[REDACTED_PHONE]']. That check runs before the guard on line 41 that returns false for `super_admin.*` abilities, so the phone match wins over it.
- ApiTokenAuth resolves the token against the current connection (the tenant DB when tenancy is initialised). It then runs Auth::setUser($user), so the gate sees the tenant-DB User.
- UpdateProfileRequest.php:13: authorize() only checks `user() !== null`.
- UpdateProfileRequest.php:23: the phone rule is `Rule::unique('users','phone')`, which only checks the tenant's own users table. No current password is needed unless the user also sets new_password.
- UpdateProfileAction.php:26 writes the phone directly.
- PUT /profile (api.php:183) has no extra permission middleware.

**Which super-admin routes are reachable**
- The super-admin group (api.php:196) sits under the same ResolveApiTenancy and ApiTokenAuth groups. Its only guard is `can:super_admin.access`, and that is satisfied once the phone is changed.
- The stancl base Tenant model uses Concerns\CentralConnection (vendor .../Models/Tenant.php:24). So `Tenant::findOrFail($id)` in SuperAdminApiController reads the central tenants table even from inside a tenant context.
- ToggleTenantStatusRequest and OverrideTenantFeatureRequest also allow the phone allowlist. UserResource.php:19 sets `is_super_admin` the same way, and the allowlist also appears in routes/web.php:38, viewPulse and Telescope.

**Where the claimed impact is overstated**
Two of the destructive paths currently fail to run. `php -l` reports parse errors in:
- app/Actions/Tenants/DeleteTenantAction.php:12
- app/Actions/Tenants/UpdateTenantDatabaseConfigAction.php:11

In both files the `$tenant` variable names are missing. So DELETE /super-admin/tenants/{id} (which would fire DeleteDatabase) and update-db-config fail right now. That is a separate bug, and the attack becomes live as soon as those files are fixed.

**What still works today**
- toggleStatus: suspend any tenant, or extend its subscription.
- override-feature and update-units.
- run-migrations against any tenant.
- Listing and viewing all tenants.
- storeTenant.
- Editing plans and platform settings.
- Managing app-versions.
- Pulse and Telescope access.

Any authenticated tenant user, a cashier included, can do all of this after one profile update. That is a cross-tenant takeover of the whole platform, so the severity stays critical.

### 3. [CRITICAL] Tenant admins can assign the seeded 'super_admin' role inside their tenant and escalate to platform super-admin
- **Location:** `D:\projects\sroor\backend\database\seeders\PermissionsSeeder.php:70`
- **Effort:** S
- **Evidence:** PermissionsSeeder (run per tenant by TenantProvisionerService.php:87) creates Role 'super_admin' and the permission 'super_admin.access' in every tenant DB, and syncs all permissions to it (lines 62, 70, 77). StoreUserRequest.php:23 and UpdateUserRequest.php:26 validate 'role' => exists:roles,name, so 'super_admin' passes. CreateUserAction.php:29 and UpdateUserAction.php:35 call syncRoles([$dto->role]). The role is hidden only from listings (UserController.php:62, GetRolesMatrixAction.php:21). Gate::before (AppServiceProvider.php:38) treats hasRole('super_admin') as allow-all, including super_admin.access.
- **Impact:** A legitimate tenant admin, or anyone who got an admin token through quick-login, can create a user with role=super_admin and reach /api/v1/super-admin/* against the central tenants table. That means cross-tenant deletion and reconfiguration.
- **Recommendation:** Do not seed 'super_admin' or 'super_admin.access' into tenant DBs (split the seeder into a central part and a tenant part). Add a Rule::notIn(['super_admin']) and a whitelist of assignable roles in the user requests. Pair this with the central-only super-admin route group from the previous finding.
- **Verification:** I confirmed this from the code and found no guard anywhere along the path.

1. The tenant DB gets the role. TenantProvisionerService.php:87 runs PermissionsSeeder inside $tenant->run(). PermissionsSeeder.php:61 adds 'super_admin.access', line 70 creates Role 'super_admin', and line 77 syncs Permission::all() to it.

2. Nothing blocks assigning it. StoreUserRequest and UpdateUserRequest validate 'role' only with exists:roles,name. Their authorize() accepts hasRole('admin'), users.manage or roles.manage. CreateUserAction:29 and UpdateUserAction:35 call syncRoles([$dto->role]) with no denylist. The only 'super_admin' filters are in listings (UserController.php:62, GetRolesMatrixAction.php:21). An admin can also send PUT /users/{own id} with role=super_admin to promote themselves.

3. The super-admin routes are not separated from tenant context. In routes/api.php:196 the super-admin group sits inside the same v1 group as everything else, which uses ResolveApiTenancy and ApiTokenAuth. ResolveApiTenancy only skips tenancy for api/v1/central/*. So a tenant user's token (tenant set by X-Tenant or host) reaches the 'can:super_admin.access' middleware. Gate::before (AppServiceProvider.php:38) returns true for hasRole('super_admin'), and the role is checked against the tenant DB.

4. The controller acts on the central DB. SuperAdminApiController uses Tenant::findOrFail for destroyTenant, updateDatabaseConfig, toggleStatus, runTenantMigrations and similar actions. App\Models\Tenant extends the stancl base Tenant, which uses Concerns\CentralConnection (vendor/stancl/tenancy/src/Database/Models/Tenant.php:24). Those queries hit the central tenants table even while tenancy is initialized, so cross-tenant delete and reconfigure is possible.

Two more escalation paths make it worse:
- Gate::before and viewPulse also allow users by hardcoded phone numbers (AppServiceProvider.php:38,49). Phone uniqueness is only per tenant DB (unique:users,phone), so a tenant admin could create a user with one of those phone numbers and pass the gate.
- Several super-admin form requests also authorize hasRole('admin'), for example ToggleTenantStatusRequest:13, OverrideTenantFeatureRequest:13 and UpdateTenantDatabaseConfigRequest:13. The route-level gate still blocks a plain admin, though, because Gate::before returns false for super_admin.* abilities.

The critical rating stands.

### 4. [CRITICAL] Cache is not tenant-scoped: default cache store is pinned to the central DB at boot, and report and permission cache keys carry no tenant id
- **Location:** `D:\projects\sroor\backend\app\Services\InventoryAnalyticsService.php:29`
- **Effort:** M
- **Evidence:** config/tenancy.php:36 has CacheTenancyBootstrapper commented out, and CACHE_STORE=database (.env:41). CacheManager::createDatabaseDriver (vendor .../Cache/CacheManager.php:200) binds the connection object when the store is first resolved. That happens during boot: AppServiceProvider::boot calls Gate::before, which resolves Gate, which constructs Spatie PermissionRegistrar (PermissionServiceProvider.php:51-55, spatie v8.3.0), and its initializeCache() calls cacheManager->store(). All of this runs before ResolveApiTenancy, so every later Cache:: call in a tenant request writes to the central 'mysql' cache table. Keys without a tenant id: InventoryAnalyticsService.php:29 "erp_abc_{store|all}_{from}_{to}_{sort}" (15-min TTL, used by GetInventoryValuationReportAction.php:99 behind /api/v1/reports/inventory), ProfitLossService.php:29 "erp_pnl_..." (used by ReportPrintController) and config/permission.php cache.key 'spatie.permission.cache'. Setting::getCacheKey() (Setting.php:19-22) is the only key that is tenant-scoped.
- **Impact:** When tenant B asks for the ABC/inventory report for the same date range within 15 minutes of tenant A, B gets A's item names, sales velocity, profit and dead-stock data. That is a cross-tenant financial data leak. The Spatie permission/role map is one global entry rebuilt from whichever tenant refreshed it last. A tenant that edits role permissions (UpdateRolePermissionsAction calls forgetCachedPermissions) changes effective authorization for every other tenant until the cache expires (24h). Tests can't catch this: phpunit.xml uses CACHE_STORE=array and a single sqlite DB.
- **Recommendation:** Enable CacheTenancyBootstrapper with a taggable store (redis), or keep the database store but prefix keys: set permission.cache.key per tenant and call PermissionRegistrar::initializeCache()/clearPermissionsCollection() on TenancyBootstrapped and RevertedToCentralContext. Also purge the resolved cache store (Cache::forgetDriver) on tenancy switch. Prefix erp_abc_/erp_pnl_ keys with tenant('id'), or better, use a single helper that builds tenant-aware keys. Add a two-tenant test on file-based sqlite tenant DBs that asserts no report or permission bleed.
- **Verification:** I confirmed this from the code and found no guard anywhere that stops it.

**1. Cache tenancy is off.** In config/tenancy.php:36, `CacheTenancyBootstrapper` is commented out. A grep of app/, config/, bootstrap/ and routes/ finds no `forgetDriver`, `purge`, `Cache::store`, cache-prefix switching or `setPermissionsTeamId`.

**2. The cache store is bound to the central DB during boot.**
- `.env:41` sets `CACHE_STORE=database`, and `config/cache.php:44` leaves `connection` null, so the store uses whatever the default connection is when it is first created.
- `CacheManager::createDatabaseDriver` (vendor CacheManager.php:200) calls `$this->app['db']->connection(null)` and passes the result into `DatabaseStore`. `DatabaseStore::table()` (line 480) reuses that stored connection, and `CacheManager` memoizes the store.
- The store is created during boot: `AppServiceProvider::boot` (line 37) calls `Gate::before`, which resolves `Illuminate\Contracts\Auth\Access\Gate`.
- Spatie 8.3.0 (composer.lock) registers `callAfterResolving(Gate::class)` in `PermissionServiceProvider` (lines 51-55). `register_permission_check_method` is true in config/permission.php:121, so that callback creates `PermissionRegistrar`. Its constructor calls `initializeCache()`, which calls `getCacheStoreFromConfig()`, which calls `$this->cacheManager->store()` (config/permission.php:217 sets store to `default`).
- All of this runs before `ResolveApiTenancy` (routes/api.php:19) runs `tenancy()->initialize()`. `DatabaseTenancyBootstrapper` only switches the default DB connection; it does not rebuild cache stores.
- The central database has its own cache table (database/migrations/0001_01_01_000001_create_cache_table.php), so writes succeed silently.

**3. The cache keys carry no tenant id.**
- `InventoryAnalyticsService.php:29` builds `erp_abc_{store|all}_{from}_{to}_{sort}`, cached for 15 minutes.
- That service is called from `GetInventoryValuationReportAction.php:99`, behind `/api/v1/reports/inventory` (routes/api.php:144), and from `ReportPrintController.php:481`.
- `ProfitLossService.php:29` builds `erp_pnl_{store|all}_{from}_{to}`.
- Spatie uses the single global key `spatie.permission.cache` (config/permission.php:209).
- Only `Setting::getCacheKey()` is tenant-scoped.

**Why this is likely, not theoretical:** each tenant has its own database, so store IDs auto-increment per tenant and `store_id=1` or `all` collide across tenants. Default date ranges such as "this month" also collide. So normal use, with no attacker, can serve tenant A's item names, sales velocity, profit and dead-stock figures to tenant B.

**Permission cache:** the global permission entry is rebuilt from whichever tenant's DB loaded or flushed it last (`UpdateRolePermissionsAction.php:26` calls `forgetCachedPermissions`). If tenants' role and permission IDs or mappings differ, authorization decisions cross tenants.

**Caveats:**
- Production's `.env` may set a different `CACHE_STORE`. With file or redis the keys would still be global because cache tenancy is off, so the leak remains.
- The test setup (`CACHE_STORE=array`, one sqlite DB) cannot surface this.

The claimed critical severity stands.

### 5. [CRITICAL] The whole application cache uses the CENTRAL database because the store is created at boot, so every tenant shares one cache table
- **Location:** `backend/app/Providers/AppServiceProvider.php:37`
- **Effort:** M
- **Evidence:** AppServiceProvider::boot() calls Gate::before(). That resolves the Gate, and Spatie's callAfterResolving(Gate) (vendor/spatie/laravel-permission/src/PermissionServiceProvider.php:51-56) builds PermissionRegistrar. Its constructor runs initializeCache(), which calls cacheManager->store() (PermissionRegistrar.php:78/94). CacheManager::createDatabaseDriver binds DatabaseStore to db->connection(null), which at boot is the central connection, and then memoizes it. I checked this read-only with tinker: before any app code runs, CacheManager::$stores already holds [database] bound to the central connection (sqlite locally, DB_CONNECTION in prod). config/cache.php:44 sets DB_CACHE_CONNECTION=null and the CacheTenancyBootstrapper is commented out at config/tenancy.php:36. The tenant `cache` tables (database/migrations/tenant/0001_01_01_000001_create_cache_table.php) are therefore never used.
- **Impact:** Every Cache::/RateLimiter/Spatie call made while a tenant is active reads and writes one shared central table. Any key without a tenant id is shared by all tenants (see the two findings below). Isolation holds only where the key embeds tenant('id') (Setting::getCacheKey).
- **Recommendation:** Turn on Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper. It needs a cache store that supports tags, so move to redis, or memcached, or the array store for local dev; the database store does not support tags. If you stay on the database store, prefix every key with tenant('id') and set the Spatie key per tenant on TenancyInitialized: set config permission.cache.key, then call app(PermissionRegistrar::class)->initializeCache() and forgetCachedPermissions() in memory. Add a feature test with two tenants that proves the cache is isolated.
- **Verification:** I could not refute this finding. Every link in the chain checks out against the code.

**How the central cache gets bound at boot**
- backend/app/Providers/AppServiceProvider.php:37 calls Gate::before() inside boot(), which resolves the Gate.
- vendor/spatie/laravel-permission/src/PermissionServiceProvider.php:51-56 registers callAfterResolving(Gate). It runs because config/permission.php:121 sets register_permission_check_method=true. That callback builds the PermissionRegistrar.
- The registrar's initializeCache() calls getCacheStoreFromConfig(), which calls cacheManager->store().
- CacheManager::createDatabaseDriver (vendor/laravel/framework/src/Illuminate/Cache/CacheManager.php:200) builds DatabaseStore with db->connection(null). At boot that is the central default connection, and the result is memoized in CacheManager::$stores.

**Read-only tinker check**
Right after boot, CacheManager::$stores already holds 'database' => DatabaseStore on the 'sqlite' connection (the central one locally).

**No guard resets it**
- config/cache.php:44 sets connection to env('DB_CACHE_CONNECTION'), which is unset. CACHE_STORE=database in .env and .env.example.
- CacheTenancyBootstrapper is commented out at config/tenancy.php:36.
- stancl's DatabaseManager (connectToTenant / setDefaultConnection) only changes the default DB connection. It never calls forgetDriver or purge on the cache.
- Nothing in app/, config/, bootstrap/ or routes/ calls forgetDriver, purge, setConnection or PermissionRegistrar::initializeCache on TenancyInitialized.
- So the tenant cache tables are never used, and every tenant shares the central cache table.

**Concrete leaks across tenants (why it stays critical)**
- **Profit & loss report:** app/Services/ProfitLossService.php:28 uses the key "erp_pnl_{storeId|all}_{from}_{to}" with no tenant id. A tenant requesting the same date range within 15 minutes gets another tenant's P&L figures.
- **ABC analysis:** app/Services/InventoryAnalyticsService.php:28 has the same problem with key "erp_abc_...".
- **Spatie permissions:** the cache key 'spatie.permission.cache' (config/permission.php:209) is global. All tenants share one cached roles/permissions collection, even though role/permission IDs are per tenant database. Tenant A's permission map can be served while authorizing users of tenant B.
- **Settings are safe:** Setting::getCacheKey (app/Models/Setting.php:18-21) embeds tenant('id'), so settings stay isolated.

**Two limits on the claim**
- The impact only applies while CACHE_STORE=database (or any shared store). I did not see the production value of DB_CONNECTION, but central is the default either way.
- Rate-limiter keys are probably keyed by phone/IP, so mixing tenants there matters less.

### 6. [CRITICAL] Cross-tenant financial data leak: the ABC/inventory analysis cache key has no tenant id
- **Location:** `backend/app/Services/InventoryAnalyticsService.php:29`
- **Effort:** S
- **Evidence:** $cacheKey = "erp_abc_" . ($storeId ?? 'all') . "_{$fromDate}_{$toDate}_{$sortBy}"; Cache::remember($cacheKey, 15 min, ...) returns item names, codes, revenue, COGS and gross profit for each item. The live callers are GetInventoryValuationReportAction:99 (via ReportController::inventory and comprehensive, which serve /api/v1/reports/inventory and /reports/comprehensive) and the tenant web route /reports/export-abc (routes/tenant.php:207). ReportFilterDTO.php:22-23 defaults from_date to '' and to_date to today, and ReportController::buildDTO:37-39 defaults the store to the main store, which has id 1 in every tenant.
- **Impact:** Tenant A opens the inventory report with the default filters and its whole item list, sales and profit go into the shared central cache under erp_abc_1__<today>_profit. For the next 15 minutes, every other tenant that opens the same report gets Tenant A's data. Collisions are near-certain because the default filters are the same everywhere. ProfitLossService:29 has the same pattern (erp_pnl_*), but its only caller, ReportPrintController, is not routed, so that path is dormant.
- **Recommendation:** Add tenant('id') to every report cache key now, and fix the shared-cache root cause in the finding above. Also fix invalidation: clearCache() forgets 'erp_abc_all' and 'erp_abc_{id}', which never match the real date-suffixed keys, and nothing calls it. Stock and sales changes only show after the 15-minute TTL expires.
- **Verification:** I confirmed this from the code. backend/app/Services/InventoryAnalyticsService.php:29 builds the key "erp_abc_{store}_{from}_{to}_{sortBy}" with no tenant id. Lines 31 and onward cache item name, code, revenue, COGS and gross_profit for 15 minutes.

I looked for anything that would keep tenants apart and found nothing:
(1) config/tenancy.php:36 has CacheTenancyBootstrapper commented out. Only the Database, Filesystem and Queue bootstrappers are active.
(2) CACHE_STORE=database (.env:41 and config/cache.php:18). CACHE_PREFIX is commented out, so the prefix is the same for every tenant.
(3) The database store fixes its connection when it is created (vendor CacheManager::createDatabaseDriver:200; DatabaseStore keeps the $connection object). It is created at boot, before tenancy starts. AppServiceProvider:37 calls Gate::before. That resolves Gate, which fires spatie's callAfterResolving(Gate) (PermissionServiceProvider:51-56). That builds PermissionRegistrar, whose constructor calls cacheManager->store(), while the default connection is still the central one. DatabaseTenancyBootstrapper later switches the default connection to 'tenant', but the store already holds the central connection object. So every tenant's Cache::remember reads and writes the one central `cache` table. The tenant migration that creates a per-tenant cache table does not help, because that table is never used.

The callers are live:
- ReportController::inventory and comprehensive (routes/api.php:139 and :144, behind ResolveApiTenancy and ApiTokenAuth) call GetInventoryValuationReportAction:99, which calls getAbcAnalysis.
- routes/tenant.php:207 /reports/export-abc also calls ReportController::inventory.

Collisions are near-certain:
- ReportController::buildDTO:37-39 falls back to the X-Store-Id header, then the user's current store, then Store::getMainStore(). Each tenant has its own database, so the main store usually has id 1.

One detail in the claim is wrong. ReportFilterDTO::fromArray replaces an empty from_date with the start of the month for the default this_month period, so the key is erp_abc_1_<month-start>_<today>_profit, not erp_abc_1__<today>_profit. That key is still the same in every tenant on a given day, so the impact holds: one tenant's item list, revenue and profit are served to any other tenant that opens the inventory or comprehensive report within 15 minutes.

Spatie's permission cache uses one shared key in the same central store, so it is not separated by tenant either. That is a separate issue that points the same way.

### 7. [CRITICAL] Hardcoded phone numbers in Gate::before grant every ability, including super_admin.access, to a user in any tenant DB with that phone
- **Location:** `backend/app/Providers/AppServiceProvider.php:38`
- **Effort:** S
- **Evidence:** Gate::before returns true if $user->hasRole('super_admin') || in_array($user->phone, [two hardcoded numbers]). The same list appears at line 49 for viewPulse. The check reads $user->phone from whichever users table is active, including tenant DBs. Super-admin routes (routes/api.php:196) are only guarded by can:super_admin.access, inside the ResolveApiTenancy group. (This is outside my background sub-area; I am passing it to the lead because it is a cross-tenant escalation.)
- **Impact:** A tenant admin creates a user in their own tenant with one of those phone numbers, logs in on the tenant, and passes every gate, including the super-admin tenant CRUD, run-migrations and impersonation. That is full control of the platform.
- **Recommendation:** Remove the phone allowlist. Allow super-admin only for central-context users (!tenancy()->initialized and a central users guard) with the super_admin role.
- **Verification:** I confirmed this from the code; nothing elsewhere guards against it. backend/app/Providers/AppServiceProvider.php:38 has `Gate::before` returning true when `$user->hasRole('super_admin') || in_array($user->phone, [2 hardcoded phone numbers])`. That check runs before the line-41 branch that otherwise denies `super_admin.*` abilities. Line 49 (`viewPulse`) uses the same list.

The super-admin group at routes/api.php:196 is inside the `/v1` group that applies `ResolveApiTenancy` (line 19) and inside `ApiTokenAuth`. Its only guard is `can:super_admin.access`. It is not under the `central/*` bypass in ResolveApiTenancy.php:20. So it runs with tenancy initialized via the X-Tenant header or the request host, and the user is then resolved from the tenant DB (ApiTokenAuth step 1/2). `$user->phone` is the tenant user's phone.

Tenant users have a free-form `phone` column (database/migrations/tenant/0001_01_01_000000_create_users_table.php:17). Its only uniqueness is per tenant DB, against that tenant's own `users` table. StoreUserRequest.php:20 and UpdateUserRequest.php:23 validate the phone only as `required|string|max:20|unique:users,phone`. Any tenant user with the `admin` role or `users.manage`/`roles.manage` can call POST /users or PUT /users/{id} with one of those numbers, including on their own account. This works because StoreUserRequest::authorize and UserController lines 39/113/161/186 accept that role or those permissions. The hardcoded numbers are not reserved or blocked anywhere.

SuperAdminApiController (dashboard, storeTenant, runTenantMigrations, destroyTenant, updateDatabaseConfig, etc.) never checks `tenancy()->initialized` or whether the caller is a central user. Tenant models use the central connection, so cross-tenant CRUD works from a tenant request.

Result: a tenant admin can grant themselves every gate, including platform-wide tenant deletion and DB config changes. That is a real, low-effort, cross-tenant privilege escalation, so critical severity is justified.

### 8. [CRITICAL] Tenant admin can grant themself platform super-admin and run tenant lifecycle operations (create, suspend, extend, migrate) on every tenant
- **Location:** `D:/projects/sroor/backend/app/Http/Requests/StoreUserRequest.php:23`
- **Effort:** M
- **Evidence:** TenantProvisionerService:87 runs PermissionsSeeder inside each new tenant DB. That seeder (PermissionsSeeder.php:62,70,77) creates the 'super_admin' role with every permission, including super_admin.access, in the tenant DB. StoreUserRequest:23 and UpdateUserRequest:26 accept any 'role' that passes 'exists:roles,name'. super_admin is not excluded: it is only hidden from the list in UserController:62 and GetRolesMatrixAction:21. AppServiceProvider:37-43 Gate::before returns true when hasRole('super_admin'), or when the phone is one of two hardcoded numbers ([REDACTED_PHONE], [REDACTED_PHONE]). The api/v1/super-admin/* routes (routes/api.php:196) only check can:super_admin.access. They sit inside the ResolveApiTenancy group, and Tenant uses stancl's CentralConnection, so they act on the central tenants table.
- **Impact:** Any tenant 'admin' can POST /api/v1/users with role=super_admin, or give a user one of the hardcoded phone numbers. That user can then GET /super-admin/tenants and /tenants/{id}, which exposes every customer's name, email, phone and stats. They can also extend their own subscription by up to 3650 days, suspend another tenant, override features, create tenants and run migrations. DELETE and update-db-config would work the same way once their parse errors are fixed. This is a cross-tenant compromise of the whole SaaS control plane.
- **Recommendation:** Do not seed a super_admin role or the super_admin.access permission into tenant DBs. Keep platform admins only in central users, and in the Gate allow super-admin only when tenancy()->initialized is false. Remove the hardcoded phone bypass from Gate::before, Pulse and the Request authorize() methods. Add Rule::notIn(['super_admin']) to the user role rules. Move the super-admin routes out of the ResolveApiTenancy group, or add a middleware that rejects them when a tenant is initialized. Add a feature test proving a tenant admin gets 403 on /super-admin/*.
- **Verification:** I could not refute this. The code shows the full escalation path.

1. **The role exists in every tenant DB.** TenantProvisionerService.php:87 runs PermissionsSeeder in the new tenant DB. That seeder creates permission 'super_admin.access' and role 'super_admin', then calls syncPermissions(Permission::all()) on it.

2. **A tenant admin can assign it.**
   - StoreUserRequest:13 (and the same line in UpdateUserRequest) authorizes hasRole('admin'), can('users.manage') or can('roles.manage').
   - The only rule on 'role' is 'exists:roles,name', checked against the tenant DB, so 'super_admin' passes.
   - CreateUserAction calls syncRoles([$dto->role]) with no filter.
   - super_admin is only removed from the role list returned by UserController::index (line 62). Nothing stops it being submitted.

3. **The gate accepts it.**
   - AppServiceProvider:37-45 Gate::before returns true when hasRole('super_admin'), or when phone is one of two hardcoded numbers ([REDACTED_PHONE], [REDACTED_PHONE]).
   - phone is only checked as unique:users,phone inside the tenant DB, so a tenant admin can also create a user with one of those numbers.
   - The line 41 deny for 'super_admin.*' abilities runs after that check, so it only blocks a plain admin.

4. **The routes have no other guard.**
   - routes/api.php:196 protects the super-admin group only with can:super_admin.access.
   - That group sits inside the ResolveApiTenancy + ApiTokenAuth group (lines 19 and 33), and I found no middleware that refuses these routes when a tenant is active.
   - Tenant extends stancl's BaseTenant, which pins the central connection, so Tenant::findOrFail in toggleStatus, overrideFeature, updateTenantUnits and runTenantMigrations acts on the central tenants table for any tenant ID.
   - StoreTenantRequest::authorize returns true.
   - ToggleTenantStatusRequest and OverrideTenantFeatureRequest go further: they also authorize a plain hasRole('admin') and the hardcoded phones, though the route gate still applies first.
   - extend_days allows up to 3650.

**The one thing that overstates it:** Plan, Subscription and PlanFeature do not pin the central connection. So GET /super-admin/tenants, which queries Plan, and other actions that touch Plan or Subscription may fail with "table not found" in tenant context. Even so, suspend/extend, feature override, units, migrations and tenant creation through Tenant (central) still work. That is a cross-tenant takeover of the control plane. Severity stays critical.

### 9. [CRITICAL] Tenant admin can make itself platform super admin (via the tenant-local 'super_admin' role or the hardcoded phone allowlist) and then extend its own subscription
- **Location:** `backend/app/Providers/AppServiceProvider.php:38`
- **Effort:** M
- **Evidence:** Gate::before returns true when $user->hasRole('super_admin') || in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]']). Spatie roles live in each tenant DB. TenantProvisionerService:87 runs PermissionsSeeder inside $tenant->run(), and that seeder creates the 'super_admin' role in every tenant DB (PermissionsSeeder ~line 70). StoreUserRequest:23 and UpdateUserRequest:26 accept any 'role' that passes exists:roles,name, and the phone is only unique per tenant DB (StoreUserRequest:20). The super-admin routes (routes/api.php:196) use only can:super_admin.access and sit in the tenancy-resolving group. The Tenant model extends stancl BaseTenant, which uses Concerns\CentralConnection, so the super-admin actions write to the central tenants table even when a tenant is initialised. The same allowlist appears in ToggleTenantStatusRequest:13, OverrideTenantFeatureRequest:13 and UserResource:19. Those requests also accept hasRole('admin'), but the route gate blocks plain admins.
- **Impact:** Any paying or trial tenant admin can create a user in its own tenant with role=super_admin, or with one of the allowlisted phones. That user then passes can:super_admin.access and can call /api/v1/super-admin/tenants/{own}/toggle-status with status=active and extend_days=3650 (free service for life), override-feature for any paid module, and list, delete, re-point the DB config of, or migrate every other tenant. This is a full billing bypass and a cross-tenant compromise. This finding overlaps the lead's auth area and is reported here because it defeats every billing control.
- **Recommendation:** Treat super admin as a central-only identity. Authenticate super-admin routes against central users only, behind a middleware that rejects the request when tenancy()->initialized. Move super-admin routes out of the ResolveApiTenancy group. Stop seeding 'super_admin' and 'super_admin.access' into tenant DBs, and block assigning them through StoreUserRequest/UpdateUserRequest (Rule::notIn). Remove the hardcoded phone allowlist everywhere. Add a test proving a tenant user with role super_admin gets 403 on /super-admin/*.
- **Verification:** I confirmed every step of the chain in code and found no guard that stops it.
(1) backend/app/Providers/AppServiceProvider.php:37-39: Gate::before returns true for hasRole('super_admin') or for a phone in a hardcoded two-number allowlist. Both checks run before the super_admin.* deny branch at line 41.
(2) backend/database/seeders/PermissionsSeeder.php:62 and :70 create the 'super_admin.access' permission and the 'super_admin' role. TenantProvisionerService runs that seeder inside $tenant->run(), so every tenant DB holds a 'super_admin' role.
(3) StoreUserRequest:23 and UpdateUserRequest:26 accept any role that passes 'exists:roles,name'. That check resolves against the tenant DB because tenancy is initialised. Their authorize() lets admin through. CreateUserAction and UpdateUserAction call syncRoles([$dto->role]) with no filter. UserController::index (line 62) hides super_admin from the role list for tenants, but that only affects the UI and blocks nothing. A tenant admin can therefore PUT /api/v1/users/{own id} with role=super_admin, or create a user with an allowlisted phone. Phone uniqueness is only checked within the tenant DB.
(4) routes/api.php:19 puts the whole v1 group behind ResolveApiTenancy, which initialises tenancy from the X-Tenant header or the host. ApiTokenAuth then resolves the user from the tenant DB. The super-admin prefix at routes/api.php:196 has only 'can:super_admin.access', which Gate::before now grants. I found no middleware that requires a central context or rejects an initialised tenant.
(5) App\Models\Tenant extends the stancl BaseTenant, which uses Concerns\CentralConnection (I checked vendor/stancl/tenancy/src/Database/Models/Tenant.php:24). So Tenant::findOrFail($id) in SuperAdminApiController::toggleStatus (line 136) reads and writes the central tenants table even while a tenant is initialised.
(6) ToggleTenantStatusRequest allows status=active and extend_days up to 3650. ToggleTenantStatusAction writes status and subscription_ends_at directly.
The other super-admin routes sit behind the same single gate: destroyTenant, updateDatabaseConfig, runTenantMigrations, overrideFeature, plans and platform settings. That makes this a cross-tenant platform takeover and not only a billing bypass. Critical is justified.

### 10. [CRITICAL] Passwordless /auth/quick-login gives a full Sanctum token for any user, including the platform super admin
- **Location:** `backend/app/Actions/Auth/ApiQuickLoginAction.php:26`
- **Effort:** M
- **Evidence:** routes/api.php:28 registers POST /api/v1/auth/quick-login outside ApiTokenAuth, with no throttle. ApiQuickLoginAction::execute looks up the user with User::where(phone=$login)->orWhere(email=$login)->orWhere(id=$login)->first(), checks only is_active, then calls $user->createToken($tokenName, ['*']) and saves it into api_token. No password, PIN, device binding or setting gate is checked. GET /api/v1/auth/workspace-users (AuthController.php:93-110) is also public and returns every active user's id, name and phone/email. The SPA calls both from LoginView.vue:304 and :331.
- **Impact:** Anyone on the internet can send {"login":"1"} to the central host (no X-Tenant) and get a token for central user #1, normally the platform owner. Gate::before passes for super_admin, so that token reaches every api/v1/super-admin/* endpoint: list, create or suspend tenants, extend subscriptions, run migrations, and destroy tenants once the action file is fixed. With X-Tenant or ?tenant=<slug>, the same call takes over any tenant admin account. It is a full remote compromise of the platform and of every tenant.
- **Recommendation:** Remove quick-login, or limit it to a per-device PIN/password and a device-bound trusted token that an authenticated admin issues. Never match on id. Put it behind throttle and disable it in the central context. Require auth for workspace-users, or return only display names behind a device token. Rotate all existing tokens after the fix.
- **Verification:** The finding is confirmed in the code, and I found no guard that blocks it.

1. **Public, no rate limit:** backend/routes/api.php:28-29 registers POST /auth/quick-login and GET /auth/workspace-users inside the v1 group, which only runs ResolveApiTenancy. Both sit outside the ApiTokenAuth group that starts at line 33. bootstrap/app.php adds no api throttle or other api middleware.

2. **No credential check:** AuthController::quickLogin (AuthController.php:115-133) validates only that `login` is a string and calls ApiQuickLoginAction::execute. The action (lines 26-30) looks the user up by phone, email or id, rejects only inactive users (line 47), then issues `createToken($tokenName, ['*'])` (line 55) and stores it in api_token. There is no password, PIN, device, setting or feature gate. tests/Feature/Api/AuthApiTest.php:287 (`test_quick_login_succeeds_without_password`) asserts this behaviour, so it is deliberate, not accidental.

3. **Account list is public:** workspaceUsers (AuthController.php:93-110) returns id, name and phone or email for every active user, so targets are easy to find. The SPA uses both endpoints (LoginView.vue:304, stores/auth.js:76).

4. **Central context is reachable:** ResolveApiTenancy does not reject requests that have no tenant. On a central host with no X-Tenant header it just continues, so User queries hit the central DB. With X-Tenant or ?tenant=, it initialises any tenant chosen by the caller.

5. **Token is accepted everywhere:** the issued Sanctum token passes ApiTokenAuth through PersonalAccessToken::findToken.

6. **Super-admin access follows:** the super-admin route group (api.php:196) uses only `can:super_admin.access`. The Gate::before callback in AppServiceProvider.php:37-46 returns true for the super_admin role, and also for two hardcoded phone numbers. Any account with that role or one of those phones can therefore reach dashboard, tenant create/destroy/toggle-status, run-migrations, plans, settings and app-versions.

One part is assumed, not proven: that user id 1 is the platform owner. That depends on seeded data. It does not lower the severity, because the public workspace-users list exposes every account, including privileged ones, in whichever context the caller selects. Any tenant's admin can also be taken over by setting X-Tenant.

Result: unauthenticated remote takeover of any account, central or tenant. Severity stays critical.

### 11. [CRITICAL] A tenant admin can make themselves platform super admin: the tenant DB has a 'super_admin' role and Gate::before trusts it, plus hardcoded phones
- **Location:** `backend/app/Providers/AppServiceProvider.php:37`
- **Effort:** L
- **Evidence:** Gate::before returns true when $user->hasRole('super_admin') || in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]']). TenantProvisionerService.php:87 runs PermissionsSeeder inside every tenant DB, and that seeder creates Role 'super_admin' with all permissions, including super_admin.access (PermissionsSeeder.php:62,70-74). StoreUserRequest.php:23 accepts role 'required|exists:roles,name', checked against the tenant DB, and CreateUserAction.php:29 calls syncRoles([$dto->role]) with no denylist. UserController.php:62 only hides super_admin from the dropdown. Phone uniqueness is per tenant DB (StoreUserRequest.php:20). ApiTokenAuth resolves the user from the tenant DB when X-Tenant is set, and the super-admin group (routes/api.php:196) is only guarded by can:super_admin.access, which is evaluated against the tenant-DB user. Tenant extends stancl BaseTenant (CentralConnection), so Tenant::findOrFail in SuperAdminApiController operates on the central tenants table.
- **Impact:** Any tenant admin, or anyone who took over one through quick-login, can POST /api/v1/users with role=super_admin or phone=[REDACTED_PHONE]. They then log in with X-Tenant: <own tenant> and call /api/v1/super-admin/tenants/{any}/toggle-status to suspend competitors or extend their own subscription by 3650 days for free. They can also call override-feature to unlock any paid feature, run-migrations on any tenant, and update-units, which initializes another tenant and writes into its settings table. That is cross-tenant tampering and billing bypass. The hardcoded phones also bypass every permission in every tenant.
- **Recommendation:** Make platform authority central-only. Use a separate central guard/model (CentralUser on the central connection) and a middleware that rejects super-admin routes when tenancy()->initialized or an X-Tenant header is present. Give that middleware its own route group outside ResolveApiTenancy. Stop seeding 'super_admin' and 'super_admin.access' into tenant DBs, and add a migration that removes them from existing tenant DBs. Remove the phone/email backdoors from Gate::before, UserResource:19, the FormRequests and the Telescope gate. Validate roles with Rule::notIn(['super_admin']).
- **Verification:** I confirmed this from the code and found no guard elsewhere that blocks it.
(1) AppServiceProvider.php:37-45: Gate::before returns true when the user has the super_admin role or when the user's phone is one of two hardcoded numbers. For a plain admin it returns false only for super_admin.* abilities.
(2) TenantProvisionerService.php:85-87: the provisioner runs PermissionsSeeder inside each tenant DB. PermissionsSeeder.php:62-75 creates Role 'super_admin' and gives it all permissions, super_admin.access included. The tenant owner gets 'admin' (TenantProvisionerService.php:122-123).
(3) StoreUserRequest.php:11-26: authorize() passes for hasRole('admin'). The rules are 'role' => exists:roles,name with no denylist, and phone is only unique:users,phone, which is checked in the tenant DB.
(4) CreateUserAction.php:29: syncRoles([$dto->role]) is called with no filter. UserController::store (lines 128-139) adds no check. UpdateUserRequest has the same open role rule (line 26), so an admin could also promote an existing user. The only guard I found is UserController.php:62, which hides super_admin from the role dropdown. No observer touches it.
(5) routes/api.php:195: the super-admin group sits inside the same ResolveApiTenancy + ApiTokenAuth group and is guarded only by can:super_admin.access. ResolveApiTenancy sets up the tenant from X-Tenant and skips only /central/* paths, so the user and their roles are resolved from the tenant DB.
(6) The Form Requests are even looser: ToggleTenantStatusRequest and OverrideTenantFeatureRequest accept hasRole('admin') or the hardcoded phones, and UpdateTenantUnitsRequest accepts admin. Only the route middleware stops a plain admin.
(7) Tenant extends stancl's BaseTenant, which uses Concerns\CentralConnection, so Tenant::findOrFail($id) in SuperAdminApiController (lines 139, 163, 186, ...) can reach any tenant row in the central DB. updateTenantUnits calls Tenancy::initialize on the target tenant and writes Setting rows there.
Net effect: a tenant admin (role admin) can POST /api/v1/users with role=super_admin, or with one of the hardcoded phones. They then call /api/v1/super-admin/* with X-Tenant set to their own tenant and change other tenants' status, subscriptions, features, units and migrations. That is a cross-tenant privilege escalation and a billing bypass. The hardcoded phones are a backdoor of their own in every tenant DB. Critical is justified.

### 12. [HIGH] No route-level guard requires tenancy: tenant API routes run on the central DB when nothing resolves, and suspended or expired tenants are never blocked
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ResolveApiTenancy.php:72`
- **Effort:** M
- **Evidence:** When there is no X-Tenant/tenant param and the host is in central_domains (lines 48-55), or the host is a non-central host that matches no domain or slug (lines 55-70), the middleware simply calls $next($request). api.php:19-222 puts all tenant routes (stores, invoices, items, treasury, users, roles, trash) in the same group as the super-admin routes, with no 'tenant required' middleware. Neither middleware reads $tenant->status or isSuspended(). The only suspended check is ResolveTenantWorkspaceAction.php:48, and it runs only on the resolver endpoint. ApiTokenAuth.php:76-78 then sets the central and super_admin guards to the user.
- **Impact:** A central-DB token, for example a super-admin or a central user obtained through quick-login, can call /api/v1/users, /roles, /trash, /settings and the rest against the central DB. That mutates central users and roles, which drive super-admin authorization, or causes 500s that leak SQL if APP_DEBUG is on. A typo'd subdomain silently lands on central. A suspended or unpaid tenant keeps full API access with existing tokens and can still log in by sending X-Tenant directly, so subscription enforcement is impossible.
- **Recommendation:** Split routes into two groups: (1) a central group (auth, super-admin, resolver, app versions) with a middleware that aborts if tenancy is initialized, and (2) a tenant group with an EnsureTenancyInitialized middleware that returns 404 when tenant() is null and 403 (or 402) when the tenant is suspended or its subscription/trial has expired. Return 404 for non-central hosts that resolve no tenant instead of falling through.
- **Verification:** I confirmed this from the code.

1. **No tenant is required.** In `backend/app/Http/Middleware/ResolveApiTenancy.php`, if there is no X-Tenant header or tenant param and the host is in `central_domains`, it falls through to `$next($request)` at line 72. It does the same when a non-central host matches no domain record and no slug (lines 55-70). Only an X-Tenant value that matches nothing is rejected (404, lines 38-43).
2. **Tenant and super-admin routes share one group.** `backend/routes/api.php:19-222` wraps everything in `[ResolveApiTenancy]` + `ApiTokenAuth`: stores, items, invoices, users, roles, trash, settings and the super-admin routes alike. There is no "tenancy required" or "prevent central" middleware. `bootstrap/app.php` adds nothing to the api group. `routes/tenant.php` does use `InitializeTenancyByDomain` and `PreventAccessFromCentralDomains`, but only for web routes, not `/api/v1`.
3. **Central context gets privileged guards.** `ApiTokenAuth.php:76-78` sets the `super_admin` and `central` guards when no tenant is active.
4. **The central DB has the tables these routes write to.** `database/migrations` contains users, `permission_tables` and `personal_access_tokens`. So `/users`, `/roles` and `/trash` run against the central users and roles that drive super-admin authorization.
5. **Most of these routes check permissions in the controller, but super_admin and admin pass.** `UserController`, `RoleController` and `TrashController` check `hasRole('admin')` or the matching permission. `Gate::before` in `AppServiceProvider.php:37-44` returns true for super_admin, for two hardcoded phone numbers, and for admin on any non-`super_admin.*` ability.
6. **Suspension and expiry are never enforced.** The only status check is `ResolveTenantWorkspaceAction.php:48`, and it runs only on the resolver endpoint. `Tenant::isSuspended()`, `Tenant.php:176`, is never called by any middleware, by `ApiTokenAuth`, or by `ApiLoginAction` / `ApiQuickLoginAction`. A suspended tenant's existing tokens keep working, and anyone can still log in by sending X-Tenant directly.

**Overstated parts:**
- The "typo'd subdomain" case is mostly harmless. A tenant token will not be found in the central DB, so the request gets a 401.
- Abusing the central DB through these routes needs a central account with the admin or super_admin role.

That second point is weaker than it looks. `ApiQuickLoginAction.php:26-55` issues a Sanctum token from phone, email or ID alone, with no password. On a central host with no X-Tenant it runs against central users, which makes this exposure worse (that should be reported as its own critical finding).

**Why high stands:** the missing subscription and suspension enforcement alone breaks the SaaS billing model, and nothing isolates tenant routes from the central DB.

### 13. [HIGH] Invoice print routes on tenant domains are public (no auth), allowing anonymous enumeration of every invoice
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:60`
- **Effort:** S
- **Evidence:** tenant.php:60-74 define GET /invoices/{id}/print, /invoices/{id}/print/thermal and /invoices/{id}/print/a4 inside the tenant group (web + InitializeTenancyByDomain) but before Route::middleware('auth') at line 77. Each does Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id) with no auth or store check. Because tenant routes are registered in app->booted (TenancyServiceProvider.php:116-123) after web.php, they override the identical URIs in web.php:56-65.
- **Impact:** Anyone who knows a tenant's subdomain can loop /invoices/1..N/print/a4 and download every invoice, including customer names, phones, balances, prices and discounts. This is a data breach for every tenant.
- **Recommendation:** Move the print routes inside the auth group with can:invoices.view and a store-access check. If prints must open in a popup from the token-based SPA, use short-lived signed URLs (URL::temporarySignedRoute) issued by an authenticated API call.
- **Verification:** The finding holds. In backend/routes/tenant.php, lines 60-74 register GET /invoices/{id}/print, /print/thermal and /print/a4 inside the group that uses only 'web', InitializeTenancyByDomain and PreventAccessFromCentralDomains. They sit outside the Route::middleware('auth') group that starts at line 77. Each closure calls Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id) without checking auth, permission or store.

I looked for guards elsewhere and found none:
- The Invoice model (app/Models/Invoice.php) uses only HasFactory and SoftDeletes. It has no global or store scope.
- The only middleware appended to the web group is StoreScope (bootstrap/app.php:19-21). It does nothing unless Auth::check() is true, so guests pass straight through.
- No controller, policy or Gate check applies, because the routes are closures.
- PreventAccessFromCentralDomains only blocks central hosts, so tenant hosts still get through.

App\Providers\TenancyServiceProvider is registered in bootstrap/providers.php, and its mapRoutes() (line 98, defined at 116-123) loads routes/tenant.php in app->booted. That means these routes are live on tenant domains.

The views expose sensitive data. For example, print-a4.blade.php:209-212 prints the customer's phone, and the line items include prices and discounts. Invoice ids are sequential integers, so anyone who knows a tenant's domain can enumerate them.

web.php:56-65 also defines the same print URIs with no auth. On central domains those run against the central or default database, which is a separate issue. The 'override' part of the claim does not change the conclusion.

Impact is limited to one tenant per known subdomain, but subdomains are easy to guess or discover. It is unauthenticated access to PII and financial data, so high severity is justified.

### 14. [HIGH] Central-token fallback and central login fallback act as a master key into every tenant, and silently create admin users in customer DBs
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:51`
- **Effort:** M
- **Evidence:** ApiTokenAuth.php:51-63: when the token is not found in the tenant DB, it looks up the token in the central DB, and if that central user hasRole('admin') (checked against central roles, since the model keeps its central connection) it authenticates as the tenant user with the same phone: User::where('phone', $centralUser->phone). No check ties the token to the resolved tenant, nothing is audited, and there is no impersonation flag. If the central phone is null, Laravel turns where('phone', null) into whereNull and picks the first active tenant user with no phone. ApiLoginAction.php:31-53 does the matching login-side step: a central admin's password logs into ANY tenant and User::firstOrCreate(['phone'=>...]) plus syncRoles(['admin']) permanently adds an admin account to the customer's DB, without consent or audit. A tenant user's token cannot be replayed into another tenant through this path (the token rows are DB-local), but any central admin token, which leaks via ?api_token, /telescope-access?token and Telescope, unlocks all tenants.
- **Impact:** One leaked central admin token or password gives read/write access to every customer's books. Platform staff accounts persist as hidden admins inside tenant DBs after support sessions. For a sellable SaaS this is a compliance and trust problem (no customer-visible audit, no revocation).
- **Recommendation:** Remove both fallbacks. Use the existing stancl impersonation flow (ImpersonateTenantAction, which is currently not routed) to issue a short-lived, audited, tenant-bound token for support access. Log impersonation in the tenant's activity log and mark the session as impersonated. Never create users in tenant DBs implicitly.
- **Verification:** The finding holds up in the code, though two details are overstated.

**What the code shows**
- `ApiTokenAuth.php:50-63` protects every tenant route under `/api/v1` (`routes/api.php:33`). Here is what it does when a token is not found in the tenant DB:
  - It looks the token up in the central DB inside `tenancy()->central()`, accepting either a Sanctum token or the `api_token` column.
  - If that central user `hasRole('admin')`, it logs the request in as the tenant user whose phone matches: `User::where('phone', $centralUser->phone)`.
  - `User` has no pinned connection. A model hydrated inside `central()` keeps the central connection name, so `hasRole` is checked against central roles.
  - Nothing ties the token to the tenant being accessed. This path writes no log entry and sets no impersonation flag.
- Tokens are also accepted from the query string at line 21 (`?api_token`).
- Central platform staff really do hold the role `admin`: `DatabaseSeeder.php:30,42` gives the two seeded admins both `super_admin` and `admin`. The tenant-side fallback therefore works for real platform accounts.
- `ApiLoginAction.php:36-59` does the same on login. A central admin's password lets that person log into any tenant. `User::firstOrCreate(['phone' => ...])` then creates a permanent account in the customer's DB, and `syncRoles(['admin'])` makes it an admin there.
- The web `LoginAction.php:60-61` repeats the same pattern, so there are two entry points, not one.

**What is overstated**
- "Nothing is audited" is only true of the token path. The login path does write an `api_login` entry to the tenant's activity log (`ApiLoginAction.php:106-113`).
- The null-phone case is a minor edge. The central admins are seeded with phones, and `phone` is nullable and unique.
- A compromised central admin can already control the whole platform, for example through `update-db-config` and `run-migrations`. So "one central credential reaches every tenant" is partly built into any SaaS design.

**Why I kept high**
- The extra risk is real: tenant data can be read and changed without the customer's consent, and the hidden admin accounts stay in the customer's DB.
- Revocation does not carry over. The tenant copy is created with the central password hash, and login step 1 then checks the tenant copy's own hash. Rotating or disabling the central password therefore does not lock out the copies already sitting in tenant DBs.
- Tokens can leak through the `?api_token` query string, which makes the token fallback easier to abuse.

### 15. [HIGH] Branch (store) isolation layer is not enforced on the API: StoreAccess is never attached and X-Store-Id is trusted as-is
- **Location:** `D:\projects\sroor\backend\bootstrap\app.php:28`
- **Effort:** L
- **Evidence:** 'store.access' => StoreAccess is only aliased (bootstrap/app.php:27-28). A grep of routes and app shows it is not applied to any route. StoreScope is appended to the web group only. ApiTokenAuth.php:83-86 writes the X-Store-Id header into session('current_store_id') without checking that the user may access that store. GetTenantDashboardAnalyticsAction.php:37 and GetPOSBootstrapDataAction.php:21 then read it. Controllers take the header or query value directly, for example InvoiceController.php:40-56 (store_id is optional, so a non-admin with invoices.view sees all stores when it is omitted), PosController.php:99-102 and ItemController.php:274.
- **Impact:** Within a tenant, a cashier assigned to branch A can read invoices, reports, stock and journals of branch B, and post documents to branch B, by changing X-Store-Id or omitting it. This breaks the second isolation layer that multi-branch customers pay for.
- **Recommendation:** Create one API middleware (or a resolver service) that resolves the active store from X-Store-Id, verifies $user->stores() or the admin role, returns 403 otherwise, and binds the result into the container. Controllers then use only that binding. For non-admins, force-scope list queries to their allowed store ids.
- **Verification:** I confirmed this in the code and could not refute it.

- **`StoreAccess` is never applied.** It is only declared as the alias 'store.access' (backend/bootstrap/app.php:28). A grep of backend/routes and backend/app finds no route or group that uses it.
- **`StoreScope` covers the web group only** (app.php:19-21). It only fills in a default session store and does not check access.
- **No store check on the API.** The /api/v1 protected group (routes/api.php:19,33) uses only `ResolveApiTenancy` and `ApiTokenAuth`.
- **`ApiTokenAuth` trusts the header.** At ApiTokenAuth.php:83-86 it copies any numeric X-Store-Id into session('current_store_id') without checking the user's stores.
- **Controllers trust it too.** They take the header or query value directly, e.g. TreasuryController:34, ItemController:274, PosController:99-102, InvoiceController:143.
- **`InvoiceController::index` returns all stores by default** (lines 40-58). It filters only when store_id or X-Store-Id is present. If both are missing or set to 'all', a non-admin with invoices.view or pos.access gets every store's invoices and the summary totals.
- **Invoice writes accept any store.** `StorePOSInvoiceRequest` (lines 22-26, 66) validates store_id only with `exists:stores,id`, not with membership.
- **No other guard catches this.**
  - The `Invoice` model has no global scope.
  - The Actions/Invoices code never checks the user's stores.
  - The only membership checks are in `StorePolicy` and in `StoreController::switchStore` (lines 230-231). Neither protects other endpoints, because a client can skip the switch and just send the header.

This breaks the rule in .claude/rules/multi-tenancy.md that a user without access to a store must get a 403. The attacker must be an authenticated user of the same tenant who also holds the module permission, so the damage stays inside one tenant. It still allows reading and writing invoices, stock, treasury and shifts across branches, so high severity stands.

### 16. [HIGH] One global Spatie permission cache key shared by all tenants mixes role-to-permission mappings between tenants
- **Location:** `backend/config/permission.php:209`
- **Effort:** M
- **Evidence:** The key is 'spatie.permission.cache' and the store is 'default' (lines 209, 217), which is the central-bound database store from the first finding. PermissionRegistrar::getSerializedPermissionsForCache() caches Permission::with('roles'), all permissions with their role ids, from whichever DB is active when the cache is first filled. hasPermissionViaRole() (HasPermissions.php:307) then runs $this->hasRole($permission->roles) using the cached role ids. UpdateRolePermissionsAction.php:26 calls forgetCachedPermissions(), which clears that one global key for everyone.
- **Impact:** Each tenant DB has its own roles and role_has_permissions. When a tenant customises roles (UpdateRolePermissionsAction) or role ids drift between tenants, users in other tenants are checked against that tenant's mapping for up to 24 hours. They may gain abilities they should not have, such as void, discount or reports, or lose abilities they need. If super-admin traffic fills the cache first, central permissions are served to tenants.
- **Recommendation:** Use a separate permission cache per tenant: set permission.cache.key to 'spatie.permission.cache.tenant.'.tenant('id') and re-run initializeCache() on TenancyInitialized/TenancyEnded, or enable the cache bootstrapper with a store that supports tags. Add a test where two tenants have different role mappings.
- **Verification:** The finding is real. I lowered it from critical to high because admins skip this check and no tenant data crosses over.

How it happens:
- config/permission.php:209 sets the key to 'spatie.permission.cache' and line 217 sets the store to 'default'.
- config/tenancy.php:36 has CacheTenancyBootstrapper commented out. Nothing scopes cache keys per tenant, and the TenancyServiceProvider TenancyInitialized/TenancyEnded listeners do nothing for the permission registrar.
- CACHE_STORE=database (.env:41), and DB_CACHE_CONNECTION is unset.
- Spatie v8.3.0 (PermissionServiceProvider:51-59) registers PermissionRegistrar as a singleton and builds it inside callAfterResolving(Gate). AppServiceProvider::boot already resolves Gate through Gate::before.
- So the registrar builds its cache store during boot, before tenancy starts. That store is a DatabaseStore on the central connection.
- loadPermissions (PermissionRegistrar.php:207) runs Permission::with('roles') on the active default connection. The Spatie models have no fixed connection, so inside a tenant request this reads the tenant DB.
- The result goes into one central key that every tenant reads.
- Spatie v8 hasPermissionViaRole compares the cached role ids with the user's own tenant roles. That means tenant B's users are checked against tenant A's role-to-permission mapping.

How it can be triggered:
- UpdateRolePermissionsAction is reachable per tenant through PUT /roles/{id} (routes/tenant.php:254, middleware can:roles.manage).
- Its forgetCachedPermissions() clears the shared key.
- The next request from any tenant refills the key with that tenant's mapping, and it stays for up to 24h.
- For example, one tenant admin who widens the 'cashier' role can give cashiers in every other tenant extra abilities (void, discount, reports) or take abilities away from them.
- Role and permission ids can also differ between tenant DBs, so the cached ids may point to the wrong roles even without anyone customising roles.

Why high, not critical:
- AppServiceProvider Gate::before returns true for super_admin and admin by checking the user's own roles relation, without the cache. Tenant admins are not affected.
- The effect is wrong authorization inside each user's own tenant. No other tenant's data is exposed.
- When all tenants keep the default seeded roles (PermissionsSeeder firstOrCreate), the mappings mostly match. The damage needs customisation or id drift.

Still, any tenant can change other tenants' cashier/staff permissions, so this is well above medium.

### 17. [HIGH] The scheduled Telegram reports and backup run only on the central DB; no command loops over tenants
- **Location:** `backend/routes/console.php:25`
- **Effort:** M
- **Evidence:** notify:daily-summary, notify:low-stock, notify:overdue-shifts and backup:telegram are plain Schedule::command calls (lines 25-39). Their handlers (SendDailyTelegramSummaryCommand.php:18 etc.) call TelegramService directly, with no tenancy()->runForMultiple, tenants:run or tenant->run. Central migrations (database/migrations/*) have no invoices, expenses, cash_shifts, store_stocks or settings tables. Those exist only in database/migrations/tenant. The four Jobs (CheckLowStockAlertJob etc.) are never dispatched (grep found no dispatch/::dispatch in app/ or routes/).
- **Impact:** On a clean SaaS central DB, these commands throw QueryException every day, and no tenant ever gets its daily summary, low-stock or overdue-shift alert. If the production central DB is the old single-shop database, they report that old shop's data to the platform owner's chat. Either way, tenants are not served.
- **Recommendation:** Wrap each command in tenancy()->runForMultiple(Tenant::where('status','active')->cursor(), fn($t) => ...). Better, dispatch one queued job per tenant, which also needs the queue connection fix below. Skip tenants with no Telegram config of their own, and catch failures per tenant so one tenant cannot block the rest.
- **Verification:** The finding holds. backend/routes/console.php:25-38 schedules notify:daily-summary, notify:low-stock, notify:overdue-shifts and backup:telegram as plain Schedule::command calls. bootstrap/app.php does not run them per tenant, and no tenant loop exists anywhere. The only tenancy()->initialize / $tenant->run calls are in PopulateRealisticTenantDataCommand, ImpersonateTenantAction, TenantProvisionerService and the ResolveApiTenancy middleware. The four handlers in app/Console/Commands call TelegramService directly.

What happens to each command:
- notify:daily-summary, notify:low-stock, notify:overdue-shifts: TelegramService queries Invoice (:133), Expense (:149), CashShift (:156, :264) and StoreStock (:204) on the default connection, with no try/catch around them. Those tables exist only in database/migrations/tenant (invoices, cash_shifts, expenses, settings, stores/stocks). The central migrations hold only users, tenants, plans, subscriptions, domains, permissions, logs, pulse, telescope and app_versions. On a clean central DB these three commands throw a QueryException.
- Setting::get does not fail: it swallows errors and falls back to config('services.telegram.*'). So the bot token and chat ID come from the platform-level config, not from each tenant's own settings.
- backup:telegram: it does not throw. DatabaseBackupService runs SHOW TABLES on DB::connection() (the central DB), so the daily backup contains only central data and no tenant database is ever backed up. sendDatabaseBackupNotification catches every error and reports success.
- The four Jobs in app/Jobs are never referenced or dispatched anywhere in app/, routes/, config/ or bootstrap/.

Severity: this is not a security or data-integrity bug unless the central DB is still the old single-shop database. I am keeping it at high because every scheduled tenant alert is broken and, more importantly, the scheduled backup leaves every tenant's data unbacked-up.

### 18. [HIGH] Tenant alerts fall back to the platform owner's bot token and chat id (env), so tenant data reaches the platform owner by default
- **Location:** `backend/app/Services/TelegramService.php:22`
- **Effort:** S
- **Evidence:** getBotToken() returns Setting::get('telegram_bot_token') ?: config('services.telegram.bot_token') (lines 22-23). getDefaultChatId() does the same for chat_id (lines 31-32). isEnabled() defaults to true when the setting is missing (lines 40-44). config/services.php:39-40 maps these to env TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID. backend/.env:68-69 contains a Telegram bot token and a chat id (values not reproduced). ShiftService::closeShift:197-199 sends sendShiftDiscrepancyNotification from tenant HTTP context.
- **Impact:** Every tenant that has not set up its own Telegram sends cashier names, shift numbers, expected and actual cash, deficits and cashier notes to the platform owner's private chat. That is cross-tenant PII and financial disclosure, it happens without the tenant's consent, and it mixes all tenants' alerts together. A tenant can also save only a chat_id through /settings/telegram/test (SettingController.php:107-117) and so send messages to any chat using the platform's bot.
- **Recommendation:** Use the env token and chat id only in central context (!tenancy()->initialized). For tenants, require both values from tenant settings, and default telegram_notifications_enabled to false for tenants.
- **Verification:** I confirmed this from the code. backend/app/Services/TelegramService.php:22-23 and :31-32 fall back to config('services.telegram.bot_token' / 'chat_id') when the tenant setting is empty. Those keys map to the env vars TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID (config/services.php:38-42). isEnabled() at :38-45 returns config('services.telegram.enabled', true) when the setting is missing, so it is on by default. Nothing makes this config tenant-aware: config/tenancy.php enables only the Database, Filesystem and Queue bootstrappers, and the TenantConfig feature is commented out at line 173. backend/.env:68-69 holds a real-looking bot token and chat id; I did not reproduce the values. The Setting model (app/Models/Setting.php) reads the tenant DB, because the settings table exists only in database/migrations/tenant. So a tenant with no Telegram settings falls straight through to the platform owner's bot and chat. ShiftService.php:196-201 sends the shift discrepancy alert from tenant request context. The real exposure is worse than the finding says. routes/tenant.php:243 lets a tenant admin trigger /settings/telegram/backup. sendDatabaseBackupNotification (TelegramService.php:368-394) builds a full gzipped SQL dump and sends it through the same fallback, so an unconfigured tenant's whole database would go to the platform owner's chat. The daily-summary, low-stock and overdue-shift routes at tenant.php:240-242 behave the same way. The claim about sendTestTelegram (SettingController.php:100-122) also holds: a tenant user with roles.manage can save only a chat_id and send through the platform's bot. That abuse is limited to the fixed test message. Mitigating factors: it needs a tenant admin or the roles.manage permission, and the leak only happens if production .env sets these vars, which the local .env does. Even so, cross-tenant financial data and full DB dumps going to the platform owner by default justifies keeping the severity at high.

### 19. [HIGH] backup:telegram dumps the central DB, including live super-admin bearer tokens and tenant DB passwords, unencrypted to Telegram; the caption claims it is encrypted
- **Location:** `backend/app/Services/DatabaseBackupService.php:39`
- **Effort:** M
- **Evidence:** createSqlGzBackup() dumps every table of DB::connection(), which is central when the scheduler runs (lines 39-90), using plain gzencode (line 97). TelegramService::sendDatabaseBackupNotification:368-387 uploads the file through sendDocument to api.telegram.org, and the caption at line 380 says 'نسخة مشفرة ومؤمنة بالكامل' (fully encrypted and secured). The central dump includes: users.api_token, which holds the plaintext Sanctum token (ApiLoginAction.php:85-89 stores plainTextToken in users.api_token, and ApiTokenAuth.php:45 accepts it as-is); tenants.data with tenancy_db_password in plaintext (TenantProvisionerService.php:52); tenant_user_impersonation_tokens; sessions; telescope_entries; and pulse tables.
- **Impact:** Anyone with access to the Telegram chat or the bot can sign in as super-admin by replaying the api_token, and gets the credentials of every tenant database. That is a full platform compromise from a single leaked chat. The data goes to a third party and is not encrypted, against what the caption says.
- **Recommendation:** Stop putting secrets in the dump: hash or remove users.api_token (use Sanctum's hashed PAT only), and encrypt tenant DB credentials (an encrypted cast or Laravel Crypt). Encrypt backups (age/GPG/openssl AES-256 with an offline key) before they leave the server, send them to storage you control (S3 with SSE and object lock), exclude telescope/pulse/sessions/cache, and fix the misleading caption.
- **Verification:** I confirmed this from the code, but I rate it high rather than critical.

What the code shows:
- **Scheduled on the central DB.** routes/console.php:37 runs `Schedule::command('backup:telegram')->dailyAt('00:05')`. The command at app/Console/Commands/SendTelegramDatabaseBackupCommand.php:18 calls TelegramService::sendDatabaseBackupNotification, and nothing initializes tenancy first, so DB::connection() is the central connection.
- **Every table is dumped in plaintext.** DatabaseBackupService::createSqlGzBackup (lines 39-90) runs SHOW TABLES and dumps every row of every table with no exclude list or column redaction. Line 97 only applies gzencode, which is compression, not encryption.
- **The file is uploaded and the caption is false.** TelegramService.php:372 creates the file and line 384 sends it with sendDocument to api.telegram.org/bot{token}/sendDocument (around line 336). The caption at line 382, not 380, says the file is fully encrypted and secured, which is untrue.
- **The tokens are replayable.** The Sanctum plainTextToken is written into users.api_token at ApiLoginAction.php:85-89 and ApiQuickLoginAction.php:59. The column exists centrally (migration 2026_08_15_160000). ApiTokenAuth.php:45 accepts a direct `User::where('api_token', $token)` match. That fallback skips the Sanctum hash, expiry and abilities checks, and the token only stops working when the user logs out (ApiLogoutAction:29). personal_access_tokens holds hashes and is safe, but the api_token column cancels that out.
- **Tenant DB passwords are in the dump.** TenantProvisionerService.php:52 puts tenancy_db_password into the tenant data. Tenant.php leaves it out of getCustomColumns and has no encrypted cast, so it sits in plaintext JSON in tenants.data.
- **Other sensitive tables are central too.** Central migrations exist for tenant_user_impersonation_tokens, telescope_entries, pulse tables and sessions, so all of them end up in the dump.
- **No guard stops it.** The settings table exists centrally. config/services.php falls back to the TELEGRAM_* env values, and enabled defaults to true. The local backend/.env has a non-empty Telegram bot token (I did not reproduce the value). The only gate is whether a token and chat ID are configured. SendTelegramDatabaseBackupJob is a second path into the same dump.

Why I lowered it from critical to high: someone needs access to the Telegram chat or to the bot token before they can exploit this; a remote or unauthenticated attacker cannot. It is still a serious secrets leak to a third party. It exposes replayable admin bearer tokens and every tenant's DB credentials, and the caption misleads people by calling the file encrypted.

### 20. [HIGH] There is no per-tenant backup, restore or retention, and tenant deletion drops the DB with no final backup
- **Location:** `backend/app/Providers/TenancyServiceProvider.php:39`
- **Effort:** L
- **Evidence:** The only backup path is DatabaseBackupService, central DB only, run from the scheduler. No command or job iterates tenant databases, and none restores one. TenantDeleted runs Jobs\DeleteDatabase synchronously (lines 39-45) with no backup step first. Backups go to Telegram, which caps bot uploads at 50 MB, and the whole dump is loaded into memory (file_get_contents + gzencode, lines 96-97). Values are escaped with addslashes() (line 82), which is not a safe SQL literal escape for binary or multibyte data. There is no retention policy and no restore test. DeleteTenantAction.php and UpdateTenantDatabaseConfigAction.php do not currently parse (php -l: 'unexpected token' at lines 12 and 11; the variables were stripped out), so DELETE /super-admin/tenants/{id} and update-db-config fail with a fatal error today.
- **Impact:** All customer data (invoices, stock, money) sits in tenant DBs that are never backed up by the app. Losing a tenant DB, or deleting a tenant once DeleteTenantAction is fixed, is unrecoverable. The central backup will silently start failing once it passes 50 MB or the PHP memory limit.
- **Recommendation:** Add a tenants:backup command (mysqldump --single-transaction per tenant DB, encrypt, keep N daily and M monthly copies off-host) and a documented, tested restore. Run a mandatory final backup in the TenantDeleted pipeline, or soft-delete or suspend tenants instead of dropping the DB. Restore the two corrupted Action files from git history and add php -l to CI.
- **Verification:** I checked the code and the finding holds.
(1) Tenant deletion: backend/app/Providers/TenancyServiceProvider.php:39-45 maps TenantDeleted to a JobPipeline that runs only Jobs\DeleteDatabase, with shouldBeQueued(false). Nothing backs up the database first, and DeletingTenant (line 38) has no listeners.
(2) Only one backup path exists, and it covers the central DB only. routes/console.php:37 schedules 'backup:telegram' daily at 00:05. SendTelegramDatabaseBackupCommand calls TelegramService::sendDatabaseBackupNotification, which calls DatabaseBackupService::createSqlGzBackup on DB::connection(). Scheduled commands run in central context. No code in app/Console, app/Jobs or routes/console.php loops over tenants or calls tenancy()->initialize or $tenant->run for a backup; the only initialize is in PopulateRealisticTenantDataCommand. No restore command exists.
(3) The tenant-side manual backup does not work either. routes/tenant.php:243-244 points to SettingController::sendBackupTelegram and ::downloadBackup, but SettingController only defines index, update and sendTestTelegram, and it uses no traits. Both endpoints would error. The finding missed this, and it makes the "tenants are never backed up" claim stronger.
(4) The details are accurate:
- addslashes() is used for value escaping (DatabaseBackupService.php:82).
- The whole dump is loaded into memory with file_get_contents + gzencode (lines 96-97), and TelegramService::sendDocument reads the file into memory again with file_get_contents for attach().
- The gz file is unlinked after sending, so nothing is kept locally and there is no retention.
- Telegram's 50 MB bot upload limit applies.
(5) php -l fails on DeleteTenantAction.php:12 ('unexpected token ")"') and UpdateTenantDatabaseConfigAction.php:11 ('unexpected token ","'). The $variables are stripped out (e.g. 'execute(Tenant ): void', '->domains()->delete();'), so both super-admin endpoints currently hit a fatal parse error.
The claimed high severity is appropriate. All tenant business data has no app-level backup or restore, and fixing the delete action makes tenant deletion permanently destroy data.

### 21. [HIGH] tenant:populate-realistic-data truncates live tenant tables unconditionally; the --fresh flag is ignored and the default target is the real '2m' tenant
- **Location:** `backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php:120`
- **Effort:** S
- **Evidence:** The signature defaults to tenant=2m and declares --fresh (lines 38-40), but option('fresh') is never read. After tenancy()->initialize, lines 120-134 always truncate invoice_items, payments, invoices, purchases, stock_movements, expenses, cash_shifts, store_stocks, items, categories, customers, suppliers and activity_logs, with FK checks off. There is no environment guard and no confirm(). The tenant has domain 2m.baraa-solutions.com (line 84).
- **Impact:** Running the command by mistake on production (php artisan tenant:populate-realistic-data) permanently wipes a real tenant's financial and stock history. There is no per-tenant backup to restore from.
- **Recommendation:** Refuse to run in production (app()->isProduction()), require an explicit tenant argument with no default, only truncate when --fresh is set and after confirm(), and move the command to a dev-only provider.
- **Verification:** I confirmed this from the code. In backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php, lines 38-40 set the tenant argument to default to `2m` and declare `--fresh`. That option is mentioned only in the signature on line 40; a grep finds no `option('fresh')` anywhere in the file. After `tenancy()->initialize($tenant)` on line 102, lines 112-118 turn off FK checks (both MySQL and SQLite branches). Lines 120-134 then always truncate InvoiceItem, Payment, AdditionalExpense, Invoice, PurchaseItem, Purchase, StockMovement, Expense, CashShift, StoreStock, Item, Category, Customer, Supplier and activity_logs.

I found no guard of any kind: no confirm(), no environment or production check, and no ConfirmableTrait. Nothing in app/, bootstrap/ or config/ calls `prohibitDestructiveCommands`, and that would not cover a custom command doing truncate() anyway. The tenant's domain is 2m.baraa-solutions.com (line 84).

It is worse than the finding says. deploy_root_baraa.py line 129 runs `artisan tenant:populate-realistic-data 2m` on the production host as part of every deploy (step 6, right after migrations). So the wipe of tenant 2m's invoices, payments, stock and customers happens on every deploy, not only when someone runs the command by mistake.

One thing lowers it a little. The command's own description ("Wipe and generate ... for tenant 2M") and the fact that it creates the 2m tenant if missing suggest 2m may be meant as a demo or showcase tenant, so it may not always hold real customer data. It still lives on a public production domain. The `--fresh` flag gives a false sense of safety, and any real activity on that tenant is lost with no restore path.

A related problem on the same code path: lines 144-160 run User::updateOrCreate for staff and reset the admin's password to a known default on every run. I'm keeping the severity at high.

### 22. [HIGH] public/update_webhook.php holds a committed deploy secret and runs git reset --hard and central-only migrations
- **Location:** `backend/public/update_webhook.php:4`
- **Effort:** S
- **Evidence:** Line 4 hardcodes a deploy-webhook shared secret (a static token string; value not reproduced). The script accepts it through $_GET or $_POST and compares with !== (lines 6-9), then runs 'git reset --hard origin/main' and 'artisan migrate --force' (lines 31-36). It never runs tenants:migrate. The file is tracked in git and sits in the web root. public/hot (pointing to localhost:5173) is also present in public/.
- **Impact:** Anyone with repo access, or anyone who reads access logs where the token appears in the query string, can trigger a production redeploy and migration at will. Deploys never migrate tenant DBs, so tenant schemas drift from the code.
- **Recommendation:** Remove the file from the web root, rotate the secret, use signed POST requests (HMAC, hash_equals) or CI-based deploys, and add tenants:migrate --force to the deploy steps. Make sure public/hot is never deployed.
- **Verification:** I checked the code and the finding holds. Line 4 of backend/public/update_webhook.php hardcodes a static shared-secret token. I did not reproduce its value. Running `git ls-files` shows the file is tracked. Lines 6-9 accept the token from $_GET or $_POST and compare it with a non-constant-time `!==`. Lines 32-37 run `git fetch --all`, `git reset --hard origin/main`, `artisan migrate --force` and `optimize:clear`, then echo the full command output, which leaks paths and the PHP binary, back to the caller. No step ever runs `tenants:migrate`, even though there are 35 tenant migrations under backend/database/migrations/tenant. So tenant databases are never migrated on this deploy path, and their schemas drift from the code.

Nothing else guards the endpoint. backend/public/.htaccess sends only non-existent files to index.php (`!-f`), so Apache executes this file directly. Laravel middleware, rate limiting and IP allowlists never apply to it. Because the token is accepted in the query string, it will end up in access logs and proxy logs.

One more problem: the script hard-resets to origin/main, while the SaaS code lives on feature/multi-tenant. A webhook call could therefore roll production back to the non-tenant code.

The side claim is also confirmed: backend/public/hot is tracked and contains `http://localhost:5173`. That is a deploy hygiene problem.

Severity stays at high rather than critical. Triggering the webhook needs the token, which means repo access or log access. It does not give arbitrary code execution beyond redeploying the repo's own code, but it does give remote control of production deploys and migrations.

### 23. [HIGH] DeleteTenantAction and UpdateTenantDatabaseConfigAction are unparseable PHP (variables stripped), so delete and update-db-config always fatal
- **Location:** `D:/projects/sroor/backend/app/Actions/Tenants/DeleteTenantAction.php:12`
- **Effort:** S
- **Evidence:** The file contains `public function execute(Tenant ): void { DB::transaction(function () use () { ->domains()->delete(); ->delete(); }); }`. UpdateTenantDatabaseConfigAction.php:11-26 has the same corruption (`execute(Tenant , array )`, ` = [];`). `php -l` reports 'Parse error: unexpected token ")", expecting variable' for both. Commit 606da74b ('fix(actions): ensure DeleteTenantAction has clean UTF-8 without BOM') introduced it, apparently through shell $-interpolation. The controller injects both through the method signature (SuperAdminApiController:247,268), so the ParseError fires during container resolution, outside the try/catch. No test calls DELETE or update-db-config.
- **Impact:** DELETE /api/v1/super-admin/tenants/{id} and POST /tenants/{id}/update-db-config return 500 in every environment. Super admins cannot remove a tenant or fix a tenant's DB credentials from the panel. Failed provisions cannot be cleaned up through the UI. The full suite still passes, which hides the problem.
- **Recommendation:** Restore both files from 2340148f, or rewrite them properly with strict_types and real variables. Add feature tests for both endpoints. Add a CI step that runs `php -l` on app/ (or `composer dump-autoload -o` plus a smoke test that resolves every route's controller dependencies).
- **Verification:** The finding holds, and I found no guard anywhere that would refute it. The git working tree is clean, so the broken code is what HEAD contains. I checked both files and the routes that use them.

**DeleteTenantAction.php:12-17.** The file reads `execute(Tenant ): void` and `use ()`, and the method body is `->domains()->delete(); ->delete();`. Every `$tenant` variable has been removed.

**UpdateTenantDatabaseConfigAction.php:11-26.** It has the same damage: `execute(Tenant , array )`, ` = [];` and `->update();`.

**php -l.** Linting fails on both files. DeleteTenantAction gives "unexpected token ")", expecting variable" on line 12. UpdateTenantDatabaseConfigAction gives "unexpected token ",", expecting variable" on line 11.

**Git history.** The last commit to touch them is 606da74b, "fix(actions): ensure DeleteTenantAction has clean UTF-8 without BOM", which fits the claim that the damage came from shell `$` interpolation.

**Controller.** SuperAdminApiController injects both classes through the method signature: `destroyTenant(string $id, DeleteTenantAction $action)` at line 247 and `updateDatabaseConfig(..., UpdateTenantDatabaseConfigAction $action)` at line 268. Autoloading either class throws a ParseError while the container resolves the method's arguments. That happens before the method body runs, so no try/catch inside the method can catch it.

**Routes.** Both routes exist in routes/api.php: `DELETE /tenants/{id}` at line 201 and `POST /tenants/{id}/update-db-config` at line 202.

**Tests.** No file under tests/ refers to either class.

As a result, the delete-tenant and update-db-config endpoints return 500 every time they are called. Nothing else in the code provides another way to do these two things.

I kept the severity at high and did not raise it to critical, because:
- the failure is total, but it causes no data corruption and no isolation breach;
- it only affects these two super-admin features;
- the rest of the app keeps working, because only these two classes are loaded lazily on these routes.

### 24. [HIGH] tenant:populate-realistic-data wipes operational data unconditionally and plants a god-mode user with password 'password', and the production deploy runs it
- **Location:** `D:/projects/sroor/backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php:120`
- **Effort:** S
- **Evidence:** handle() truncates invoice_items, payments, invoices, purchases, stock_movements, expenses, cash_shifts, store_stocks, items, categories, customers, suppliers and activity_logs (lines 120-134) with foreign keys disabled. It never reads the declared --fresh option, has no environment guard and no confirm(). Lines 143-151 updateOrCreate a user with phone [REDACTED_PHONE] and Hash::make('password'), and Gate::before (AppServiceProvider:37) treats that phone as super-admin. deploy_root_baraa.py:129 runs `artisan tenant:populate-realistic-data 2m` on Hostinger with APP_ENV=production on every deploy.
- **Impact:** Every deploy destroys all real sales, purchase, stock and treasury data of tenant '2m' and replaces it with fake data. It also creates or resets a platform-wide super-admin login with a publicly guessable password. If the argument is any real tenant slug, that customer's books are wiped.
- **Recommendation:** Remove the command from the deploy script now. Guard it with `if (app()->isProduction()) abort` plus an explicit --fresh and confirm(). Never set known passwords. Move it to a dev-only seeder.
- **Verification:** I confirmed this in the code, but the impact is somewhat overstated. In backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php, lines 38-40 declare a `--fresh` option. handle() never reads it. On MySQL, lines 113 and 120-134 turn off FOREIGN_KEY_CHECKS and then truncate InvoiceItem, Payment, AdditionalExpense, Invoice, PurchaseItem, Purchase, StockMovement, Expense, CashShift, StoreStock, Item, Category, Customer, Supplier and activity_logs. There is no environment check, no confirm() call and no isProduction() guard. Lines 144-153 call updateOrCreate on the user with phone [REDACTED_PHONE], set the password to Hash::make('password') and assign the admin role. Two cashier users get the same password. In AppServiceProvider.php, Gate::before at lines 37-39 returns true for every ability when the phone is [REDACTED_PHONE] or [REDACTED_PHONE], so that user bypasses all permission checks. The same phone list appears in routes/web.php:38, TelescopeServiceProvider.php:82, UserResource.php:19 and the ToggleTenantStatus and OverrideTenantFeature requests. deploy_root_baraa.py is tracked in git and sets APP_ENV=production at line 84. It runs `artisan tenant:populate-realistic-data 2m` right after migrations and seeders, so every run wipes that tenant and resets the password. Two things reduce the impact. First, deploy_root_baraa.py is a manual ad-hoc script, not CI, so the wipe happens only when someone runs it. Second, the command itself creates tenant '2m' as a demo store (lines 64-89), which suggests 2m is meant as a demo or showcase tenant rather than a paying customer, so wiping it may be intended. The 'any real tenant slug' scenario needs an operator to pass that slug by hand. What remains serious: an unguarded destructive command in production code, and a guessable password ('password') on a hardcoded god-mode phone, reset on production every time the script runs. I rated this high rather than critical.

### 25. [HIGH] DatabaseSeeder (the configured tenant seeder) creates god-mode users with known passwords
- **Location:** `D:/projects/sroor/backend/database/seeders/DatabaseSeeder.php:20`
- **Effort:** S
- **Evidence:** Lines 20-38 updateOrCreate users with phone [REDACTED_PHONE] (bcrypt('password')) and [REDACTED_PHONE] (bcrypt('[REDACTED_PASSWORD]')), then call TenantSampleSeeder. config/tenancy.php sets seeder_parameters '--class' => 'DatabaseSeeder', so `php artisan tenants:seed` puts these users into every tenant DB. The phones are hardcoded in Gate::before.
- **Impact:** Any run of `db:seed` on central or `tenants:seed` on tenants creates backdoor super-admin accounts with trivial passwords. That breaks tenant isolation and allows platform takeover.
- **Recommendation:** Split dev seeders from production seeders. Point tenancy.seeder_parameters at a TenantBootstrapSeeder that seeds only permissions, roles (without super_admin), units and settings. Take super-admin credentials from env or interactive input at install time.
- **Verification:** The core finding holds, but two details in the claim are wrong or overstated.

What the code confirms:
- In backend/database/seeders/DatabaseSeeder.php, lines 21-42 create two users with hardcoded phone numbers and trivial bcrypt passwords ('password' and '[REDACTED_PASSWORD]'). Both get syncRoles([super_admin, admin]).
- Line 45 then calls TenantSampleSeeder, which provisions a demo tenant whose admin uses phone [REDACTED_PHONE] with password 'password' (TenantSampleSeeder.php:28-29).
- The same two phone numbers are hardcoded as an unconditional bypass in several places:
  - Gate::before at app/Providers/AppServiceProvider.php:38 (returns true for every ability)
  - viewPulse gate at AppServiceProvider.php:49
  - TelescopeServiceProvider.php:82
  - routes/web.php:38
  - OverrideTenantFeatureRequest.php:13 and ToggleTenantStatusRequest.php:13
  - UserResource.php:19 (sets is_super_admin)
- config/tenancy.php:200 does set seeder_parameters '--class' => 'DatabaseSeeder'.
- The production path is real. Repo-root deploy scripts run `php artisan db:seed --force` against the live server (deploy_all_locations.py:46, deploy_live_and_seed.py:63). So on a fresh or new environment, the central DB gets two super_admin accounts with publicly known passwords. That is a platform-takeover path.

Where the claim is overstated:
1. The seeder uses firstOrCreate, not updateOrCreate. If those phone numbers already exist, the password is not reset. The risk applies to fresh environments, or to accounts whose default password was never changed.
2. "Puts these users into every tenant DB" is not shown:
   - Normal tenant provisioning (TenantProvisionerService::provision) does not run DatabaseSeeder. It only runs PermissionsSeeder and creates the DTO's admin.
   - TenancyServiceProvider only wires the CreateDatabase and MigrateDatabase jobs, not SeedDatabase.
   - Nothing in the repo invokes `tenants:seed`.
   - If someone did run `tenants:seed`, it would first run PlansAndFeaturesSeeder in tenant context. The Plan and PlanFeature models do not pin the central connection, and there is no plans migration in database/migrations/tenant. So it would most likely fail before creating the users.
   - The tenant-wide propagation is therefore theoretical. The central db:seed path is the confirmed one.

Related root cause: the hardcoded phone bypass in Gate::before is not tied to a password at all. Any user in any tenant DB with one of those phone numbers gets full god mode in that tenant, including the seeded demo tenant admin.

Severity stays high because the deploy scripts actually run this seeder against production.

### 26. [HIGH] Literal production credentials committed in a tracked deploy script
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:12`
- **Effort:** M
- **Evidence:** Lines 9-12 hold the SSH host, port, user and a password literal (not read from env). Line 85 holds a literal Laravel APP_KEY (base64), and line 99 a literal DB_PASSWORD inside the heredoc .env. git ls-files confirms the file is tracked. The script also runs `git add . && commit && push`.
- **Impact:** Anyone with repo access has SSH and DB access to production and the APP_KEY, which allows decrypting sessions, cookies and encrypted values and forging signed URLs. Every tenant's data is exposed.
- **Recommendation:** Rotate the SSH password, DB password and APP_KEY. Remove them from the script and from git history (filter-repo or BFG). Load secrets from environment or a vault, and keep production .env out of the repo.
- **Verification:** Confirmed in code. git ls-files lists D:/projects/sroor/deploy_root_baraa.py as tracked, and 13 commits touch it. It is on feature/multi-tenant and also on remotes/origin/feature/multi-tenant and origin/feature/api-migration (origin = github.com/kamalsroor1/sroor-cofe-erp), so it has been pushed. Lines 9-12 hard-code the SSH host, port 65002, the hosting account user, and a literal SSH password in PASS. Nothing is read from the environment. Inside the heredoc that writes the production .env, line 85 holds a literal base64 APP_KEY and line 99 a literal DB_PASSWORD for the central DB user. Line 101 sets TENANT_DB_PREFIX on the same hosting account, so the same DB credentials very likely reach the tenant databases too. Lines 30-32 run `git add .`, then `git commit`, then `git push` on every deploy, which keeps re-committing whatever sits in the working tree. No guard covers this: the values are in git history, so deleting or .gitignoring the file now would not remove them. Severity stays high, not critical, because exposure needs read access to the GitHub repo, which looks private. The claimed impact is otherwise accurate: SSH and DB access to all tenants' data, plus the APP_KEY lets an attacker decrypt and forge encrypted cookies and values and forge signed URLs. The SSH password, DB password and APP_KEY need to be rotated, and git history purged. Values are deliberately not reproduced here.

### 27. [HIGH] Commercial controls are stored but never enforced: suspended, expired, trial-ended and cancelled tenants keep full API and web access
- **Location:** `backend/app/Http/Middleware/ResolveApiTenancy.php:36`
- **Effort:** M
- **Evidence:** ResolveApiTenancy calls tenancy()->initialize($tenant) at lines 37 and 68 without checking $tenant->status, isSuspended(), isOnTrial() or subscription_ends_at. routes/tenant.php:20-24 uses only InitializeTenancyByDomain and PreventAccessFromCentralDomains. bootstrap/app.php registers no subscription middleware. A grep across app/, routes/, config/ and bootstrap/ finds only one caller of isSuspended(): ResolveTenantWorkspaceAction:48, which runs only for the central workspace-lookup endpoint (CentralTenantResolverController). Nothing outside Tenant.php calls isOnTrial() or isActive().
- **Impact:** A tenant whose trial ended, whose subscription lapsed, or whom the super admin set to 'suspended' or 'cancelled' can keep working through its subdomain, the X-Tenant header, or a token the app already holds. Only the mobile 'find workspace' step is blocked. Suspension is not a real kill switch, so non-payment has no effect.
- **Recommendation:** Add an EnsureTenantSubscriptionActive middleware that runs right after tenancy is initialised, on both the API group and the tenant web group. Return 402/403 with __('auth.workspace_suspended') when status is suspended or cancelled, or when trial/subscription end dates are past a configurable grace period. Allow a small set of exempt routes (logout, system/context, a billing-status endpoint). Cache the check per request. Add feature tests for each state.
- **Verification:** The code confirms this finding. backend/app/Http/Middleware/ResolveApiTenancy.php runs tenancy()->initialize($tenant) at line 37 (X-Tenant header, tenant query or input) and at line 68 (host or subdomain). It never checks status, isSuspended(), isOnTrial(), isActive() or subscription_ends_at.

Nothing else guards it:
- ApiTokenAuth.php, the only auth middleware for /api/v1 (routes/api.php:19,33), checks only the token and user->is_active.
- bootstrap/app.php registers only the StoreScope web append and spatie and store aliases. There is no subscription middleware.
- routes/tenant.php:20-24 uses only 'web', InitializeTenancyByDomain and PreventAccessFromCentralDomains.
- TenancyServiceProvider's TenancyInitialized event runs only BootstrapTenancy, with no status listener.
- TenantFeatureManager has no status or expiry logic.

A grep over app/, routes/, config/ and bootstrap/ finds one reader of tenant status: ResolveTenantWorkspaceAction.php:48, the central workspace lookup. isOnTrial() and isActive() (Tenant.php:156-170) have no callers. The super admin's ToggleTenantStatusAction only writes status and subscription_ends_at, and the only other reader is the analytics count in SuperAdminAnalyticsService.php:22.

So suspended or expired tenants keep full API and web access. One small overstatement: ToggleTenantStatusRequest allows only active, trial, suspended and expired, so 'cancelled' is not a status the panel can set. That does not change the core issue.

High severity stands. This is a revenue and contract-enforcement failure, not cross-tenant data exposure, so it is not critical.

### 28. [HIGH] Plan limits (users, stores, items, invoices/month, storage) are never checked, and the only helper reads the wrong keys and a non-existent column
- **Location:** `backend/app/Models/Tenant.php:103`
- **Effort:** M
- **Evidence:** Nothing calls checkLimit(), getFeatureLimit(), getAllLimits() or getAllFeatures() (grep over app, routes, resources/js and tests). checkLimit reads getFeatureLimit('limits.users'), 'limits.stores' and 'limits.items' from the plan's features JSON, but the plan stores limits in max_users/max_stores/max_items columns. It also counts User::where('tenant_id', ...), Store::where('tenant_id', ...) and Item::where('tenant_id', ...), but in DB-per-tenant those tables have no tenant_id. CreateUserAction, CreateStoreAction, StoreController::store (line 92), UserController::store (line 128) and ItemController::store (line 130) do no limit checks. max_invoices_per_month and max_storage_mb are not counted anywhere.
- **Impact:** A 'Basic' plan customer can create unlimited users, branches, items and invoices, so tiered pricing cannot be sold. If someone later wires checkLimit as it stands, it will either throw (unknown column tenant_id) or return false for everyone, because the limit key resolves to 0 and blocks all creation.
- **Recommendation:** Create a central PlanLimitService that reads tenant()->plan->max_* and counts rows in the current tenant DB without a tenant_id filter. Call it inside the Create{User,Store,Item}Action transactions and the POS/invoice create path for the monthly quota. Lock or count inside the transaction to avoid racing past the limit. Throw a domain exception mapped to 403/422 with a translated plan_limit_reached key. Delete or rewrite Tenant::checkLimit and getFeatureLimit.
- **Verification:** I confirmed this in the code. backend/app/Models/Tenant.php:103-111 `checkLimit()` calls `getFeatureLimit('limits.users' / 'limits.stores' / 'limits.items')`. That method (lines 89-98) reads `$plan->features[$key]` and returns 0 when the key is missing. The plan `features` JSON in database/seeders/PlansAndFeaturesSeeder.php only holds boolean flags such as 'pos.access' and 'invoices.create'. No 'limits.*' key appears anywhere in app, database, routes or config. The real limits are stored in the max_users, max_stores, max_items, max_invoices_per_month and max_storage_mb columns, created in database/migrations/2019_09_15_000005_create_plans_and_features_tables.php:19-23. `checkLimit` also queries `User`, `Store` and `Item` with `where('tenant_id', ...)`, but no tenant migration (database/migrations/tenant) has a tenant_id column. Wiring it in as written would therefore fail with an unknown-column error. I found no guards anywhere else. Nothing in app/Http/Middleware (ApiTokenAuth, ResolveApiTenancy, StoreAccess, StoreScope) mentions plans or limits. Searching app/Actions for limit, max_ or count checks found nothing. The only places in app/Http that mention max_* are PlanResource and UpdatePlanRequest, which display and edit plans. TenantFeatureManager handles boolean feature flags only and has no quantity limits. The four helpers are never called; the only matches are their own definitions in Tenant.php. One small overstatement: `getAllLimits()` (lines 133-147) reads the correct max_* columns, so not every helper uses the wrong keys, but it is also unused. Plan-level feature flags do exist (hasFeature/TenantFeatureManager), so plans are not fully meaningless. Even so, quantity tiers (users, branches, items, invoices per month, storage) are not enforced at all. For a product sold as a multi-tenant SaaS with tiered plans, high severity is justified, though this is a missing business control rather than a security hole.

### 29. [HIGH] Impersonation is unwired; the de facto path is an unaudited central-admin fallback that silently creates or upgrades tenant admins
- **Location:** `backend/app/Actions/Auth/ApiLoginAction.php:37`
- **Effort:** M
- **Evidence:** ImpersonateTenantAction and ImpersonateTenantRequest are referenced nowhere (grep). No route issues a token, and the SPA 'impersonate' button only does window.open(`http://${tenant.value.domain}`) (useSuperAdminTenantShow.js:150-154). The /impersonate/{token} route (routes/tenant.php:33-36) uses stancl makeResponse. That gives a 60s TTL, a tenant match and single-use deletion, which is fine, but it logs into the web session guard while the SPA authenticates by bearer token (ApiTokenAuth), so the flow cannot work. It also sets session is_impersonating=true before the token is validated, so any visitor can set that flag on an invalid token. Instead, ApiLoginAction.php:37-56 lets any central user with role 'admin' log into ANY tenant with their central password: firstOrCreate a tenant user by phone, then syncRoles(['admin']) before the password check at line 61. ApiTokenAuth.php:50-61 maps a central 'admin' token to the tenant user with the same phone. ImpersonateTenantRequest also authorizes on hasRole('admin'), not on a platform role.
- **Impact:** Support access to tenant data has no consent, no time box and no record of 'impersonated by X'. It permanently plants admin accounts in customer DBs. A same-phone cashier in a tenant can be promoted to admin just because a central admin attempted a login. For a sellable SaaS this is a compliance and trust problem.
- **Recommendation:** Remove the central-admin fallbacks in ApiLoginAction and ApiTokenAuth. Add a super-admin-only endpoint that calls ImpersonateTenantAction and issues a short-lived, single-use, abilities-scoped Sanctum token (e.g. ['impersonated']) with an expiry. Log it in central and tenant audit logs, show a banner, and revoke the token on leave. Set is_impersonating only after a successful token exchange.
- **Verification:** I confirmed this from the code, but one sub-claim is narrower than stated.

1. **Impersonation is unwired.** `ImpersonateTenantAction` and `ImpersonateTenantRequest` are only defined; nothing in `app/` or `routes/` references them. The impersonate button in `useSuperAdminTenantShow.js:150-154` only calls `window.open('http://'+domain)`.
   - `ImpersonateTenantRequest::authorize` checks `hasRole('admin')`, not `super_admin`.
   - In `routes/tenant.php:33-36`, the `/impersonate/{token}` route sets `is_impersonating` in the session before `UserImpersonation::makeResponse` checks the token. Any visitor can set that flag, but it only affects their own session's UI context, which is low impact.

2. **Central-admin fallback** (`ApiLoginAction.php:37-59`). This runs in tenant context when no tenant user matches the login.
   - It checks the central password and central role `'admin'` (not `super_admin`).
   - It then calls `firstOrCreate` on a tenant user by the central user's phone and `syncRoles(['admin'])`. This is outside any transaction and before the final password check at line 63.
   - The success log at lines 106-113 is a normal `api_login` with no "impersonated by" marker.
   - `ApiTokenAuth.php:50-61` lets a central-admin token act as the tenant user with the same phone.
   - The central `DatabaseSeeder` gives platform users both `super_admin` and `admin`, so this path is live for platform staff.
   - Result: any central 'admin' credential opens every tenant as admin, leaves a permanent admin account in the tenant DB, and has no consent, time box or impersonation audit.

3. **Overstated part: the "same-phone cashier gets promoted" claim.** If the login value is the phone, the tenant lookup at line 31 already finds the cashier, so the fallback never runs. Promotion only happens in two cases:
   - The central admin logs in by email, and the tenant has no user with that email but has a user with the admin's phone. That user is promoted even if the later password check fails.
   - The central phone is null, which could match a tenant user with a null phone.

Severity stays high because a single central credential reaches every tenant with persistent, unaudited admin access. The cashier-promotion vector is an edge case.

### 30. [HIGH] Unauthenticated invoice and report print routes (IDOR) on tenant and central hosts
- **Location:** `backend/routes/tenant.php:60`
- **Effort:** S
- **Evidence:** routes/tenant.php:60-74 (/invoices/{id}/print, /print/thermal, /print/a4) sit outside the Route::middleware('auth') group that starts at line 77 and run Invoice::with(['customer',...])->findOrFail($id). routes/web.php:56-68, 130 and 180 (thermal, a4, daily-journal/print, item movements, reports/print) and 278-282 (CSV exports) have no auth middleware at all.
- **Impact:** Anyone who knows a tenant subdomain can enumerate sequential invoice IDs and read customer names, amounts and balances. The daily journal and reports prints expose a full day's sales. This is a confidentiality breach per tenant.
- **Recommendation:** Move the print/export routes under auth plus a permission (invoices.view / reports.view) and store-scope checks, or use signed, short-lived URLs (URL::temporarySignedRoute) for popup printing.
- **Verification:** The core claim holds, but the evidence overstates its reach. I checked the code and resolved routes against the booted router (no DB access).

CONFIRMED (tenant host):
- backend/routes/tenant.php:65-68 (GET /invoices/{id}/print/thermal) and :71-74 (GET /invoices/{id}/print/a4) sit outside the auth group that starts at :77.
- On a tenant host they resolve with middleware [web, InitializeTenancyByDomain, PreventAccessFromCentralDomains] only. There is no auth, no `can:` check and no policy.
- They run `Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id)`. app/Models/Invoice.php has no global or store scope.
- StoreScope (bootstrap/app.php web append) acts only when `Auth::check()`.
- Result: anyone who knows a tenant subdomain can request sequential IDs and get printed invoices (customer, line items, amounts) from that tenant's DB. This is a real per-tenant confidentiality breach (IDOR without login). It also breaks the store-isolation rule: no store check is done even for logged-in users.

OVERSTATED or WRONG parts:
1. tenant.php:60-63 (/invoices/{id}/print) is unreachable. The web.php catch-all `/{any?}` is registered first and matches it, so it serves the SPA view.
2. Same URI and method routes from web.php are replaced by the later-registered tenant.php routes on every host:
   - web.php:56-65 (thermal/a4) is effectively dead. On the central host the tenant.php version is blocked by PreventAccessFromCentralDomains.
   - web.php:68 (daily-journal/print) is replaced by tenant.php:97, which is inside `auth` and has `can:daily_journal.view`.
3. The remaining web.php routes have no auth and no tenancy middleware, on central and tenant hosts alike:
   - items/{id}/movements/print (:130) and the CSV exports (:278-281)
   - These query the default (central) connection. Central migrations (database/migrations) have no invoices, items, customers or stock tables, so on a correctly built central DB they fail with 500 instead of leaking data. They would leak only if the production central DB still holds legacy single-tenant business data, which the code cannot confirm.
   - reports/print (:180) returns only hard-coded placeholder rows, no real data.
   - activity-logs export-csv (:282) is protected: FilterActivityLogsRequest::authorize() returns false without a user.

Severity stays high because of the confirmed unauthenticated tenant-host invoice IDOR. The central-host and "full day's sales via daily journal" parts of the impact are not supported.

### 31. [MEDIUM] web.php exposes unauthenticated print and CSV export routes with no tenancy initialization (they run on the central DB)
- **Location:** `D:\projects\sroor\backend\routes\web.php:130`
- **Effort:** S
- **Evidence:** web.php:130-177 (/items/{id}/movements/print), 180-252 (/reports/print), 278-282 (ExportController customer and supplier statements, inventory and item-movement CSV, activity-logs CSV) and 258-268 (/store/switch, /stores/switch writing arbitrary session store ids) have no auth middleware and no tenancy middleware. ExportController.php has no authorization. Of these URIs, only /activity-logs/export-csv, /stores/switch and daily-journal print are overridden by the authenticated versions in tenant.php. The rest stay public on both central and tenant hosts and query the default (central) connection.
- **Impact:** Today these routes either 500 (tenant tables are missing from central) or, if the central DB still holds the legacy single-tenant Sroor data, publicly dump customer and supplier statements, inventory valuation and stock ledgers. Either way, unauthenticated financial export endpoints are live in production routing.
- **Recommendation:** Delete the legacy web.php business routes, or move them into tenant.php under auth plus can:* plus a store check. Keep web.php limited to the SPA shell, manifest and sw.js. Confirm what data the production central DB actually contains.
- **Verification:** The finding is real, but its impact depends on something the code can't show, so I lowered it from high to medium.

**What I confirmed in the code:**
- `bootstrap/app.php` loads `routes/web.php` with only the plain `web` group, plus `StoreScope` appended.
  - `StoreScope` checks nothing: if the user is not logged in it simply lets the request through.
  - No global middleware sets up tenancy. `ResolveApiTenancy` is used only in `api.php`. `InitializeTenancyByDomain` is used only in the `tenant.php` group.
- These routes in `web.php` have no `auth`, `can` or tenancy middleware:
  - `/items/{id}/movements/print` (line 130): queries `Item`, `StockMovement` and `StoreStock`.
  - `/reports/print` (line 180).
  - `/store/switch` (line 258): writes any `store_id` sent in the request into the session.
  - The `ExportController` routes at lines 278-281: customer and supplier statements, inventory CSV and item-movements CSV.
- `ExportController.php` does no authorization. It calls `Customer::findOrFail`, `Supplier::findOrFail`, and `ExportService::exportInventory()` (`Item::active()->get()`). None of these models pins a connection, so they all use the default (central) connection.
- `tenant.php` is registered later, inside `app->booted` in `TenancyServiceProvider`, and its routes have no domain, so routes with the same URI replace the `web.php` ones. Only `/daily-journal/print`, `/stores/switch`, `/activity-logs/export-csv` and the invoice print routes are replaced, which matches the claim. The rest stay public on every host, and none of them initializes tenancy.

**Why I lowered it to medium:**
- `database/migrations` (the central migrations) creates no `items`, `customers`, `invoices` or stock tables. On a central DB built from these migrations, the routes fail with a server error and leak nothing.
- Data is exposed only if the production central DB still holds the old single-tenant data, and nothing in the code shows that.
- The Vue SPA has no references to these URLs, so they look like leftover dead routes.
- `/reports/print` builds only titles, the store name and hard-coded 0.000 rows. It reads only `Store::find`, so it exposes almost nothing.
- `/store/switch` only writes to the requester's own session.

**What remains:** public financial and stock export endpoints with no authorization, running against the wrong connection. If the central DB holds the old data, the risk goes back up to high: `/items/export-csv` needs no ID, and the customer and supplier IDs are sequential and easy to guess.

### 32. [MEDIUM] No tests exercise real tenant isolation; the test harness collapses central and tenant into one sqlite DB
- **Location:** `D:\projects\sroor\backend\tests\TestCase.php:13`
- **Effort:** L
- **Evidence:** TestCase::setUp runs the tenant migrations onto the single default sqlite :memory: connection. phpunit.xml:26,32 set CACHE_STORE=array and SESSION_DRIVER=array. Only CentralTenantResolverApiTest, SuperAdminApiTest and SuperAdminSolidTest mention tenants, and none initializes two tenant DBs. A grep for X-Tenant/tenancy()->initialize in tests finds only the resolver test. AuthApiTest asserts that passwordless quick-login succeeds.
- **Impact:** None of the critical issues above (quick-login, phone allowlist, super_admin role assignment, central fallback, cache bleed, tenancy-less requests, suspended tenant access, store-header bypass) can be caught by CI. Isolation regressions will ship silently.
- **Recommendation:** Add a TenancyTestCase that provisions 2 tenants on file-based sqlite DBs (stancl supports a .sqlite suffix). Minimum tests: tenant-A token + X-Tenant:B gives 401; a tenant request with no tenant on the central host gives 404; a suspended tenant gives 403; a tenant user with the allowlisted phone or the super_admin role gets 403/404 on /super-admin/*; a guest gets 404 on quick-login and workspace-users; a guest gets 401/403 on /invoices/{id}/print; the ABC report and permission cache do not bleed across tenants; a cashier gets 403 with another branch's X-Store-Id.
- **Verification:** I checked this against the code and it holds. The finding is real, but it is a test-coverage gap, not an exploitable defect, so I've lowered it from high to medium.

1. **One database for everything.** In backend/tests/TestCase.php:13-14, `setUp()` runs `migrate --path=database/migrations/tenant` on the default sqlite connection. phpunit.xml sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `CACHE_STORE=array` and `SESSION_DRIVER=array`. Central and tenant tables therefore share one in-memory database.

2. **No test switches into a tenant.** A grep of backend/tests for `tenancy()`, `X-Tenant`, `initialize(` and `InitializeTenancy` finds nothing in the PHP tests (the only hits are minified Playwright report files). `Tenant::create` appears only in:
   - CentralTenantResolverApiTest
   - SuperAdminApiTest
   - SuperAdminSolidTest

   CentralTenantResolverApiTest uses `Event::fake` on `TenantCreated`, `CreatingDatabase` and `MigratingDatabase`, so no tenant database is ever created. No test runs two tenant databases, makes a request through the tenancy middleware, or checks that a suspended tenant is blocked. SuperAdminSolidTest and SuperAdminApiTest only check that the status field changes.

3. **One test locks in risky behaviour.** AuthApiTest.php:287 is `test_quick_login_succeeds_without_password`, so the suite asserts that passwordless login works.

4. **CI does run these tests.** .github/workflows/deploy.yml has a `test-and-qa` job that runs `php artisan test` before deploy. Isolation regressions would pass CI because nothing covers them.

**Why it's overstated:** missing tests don't create a vulnerability. Their value depends on the separate runtime findings (quick-login, central fallback and the rest), which have to be confirmed on their own. Store-level isolation is partly covered: MultiStorePhase1Test and MultiStorePhase2Test use two stores, and `X-Store-Id` is used in 8 places across the tests. Only tenant-level isolation has no coverage at all. That makes this a medium process/QA gap.

### 33. [MEDIUM] Telescope gate grants any 'admin' role and accepts a token in the query string, then creates a remembered web session
- **Location:** `backend/app/Providers/TelescopeServiceProvider.php:62`
- **Effort:** S
- **Evidence:** Lines 62-73: when ?token= is present, the gate looks it up as a Sanctum PAT, falls back to User::where('api_token', $token), then calls auth('web')->login($user, true). Lines 81-86 let in any user with hasRole('admin'), plus a hardcoded allowlist of phones and emails. telescope.enabled defaults to true (telescope.php:19), and Telescope stores entries for all tenants in the central telescope_entries table: queries with bindings, requests, exceptions. telescope:prune is not scheduled (routes/console.php), so the table grows without limit and is included in the Telegram backup.
- **Impact:** Any central 'admin' user, not only super_admin, can read query bindings and request payloads for every tenant. Bearer tokens in URLs end up in access logs and browser history, and the gate turns them into long-lived remember-me sessions.
- **Recommendation:** Restrict the gate to the super_admin role only, remove the ?token= login and the hardcoded identities, disable Telescope in production or keep its watchers minimal, and schedule telescope:prune --hours=48.
- **Verification:** Confirmed in the code, but the impact is overstated.

What the code shows:
- **Token in the query string.** `backend/app/Providers/TelescopeServiceProvider.php:59-87`: the `viewTelescope` gate takes `$user = null`, so guests reach it. When `?token=` is present it resolves a Sanctum personal access token, falls back to `User::where('api_token', $token)`, then calls `auth('web')->login($user, true)`, which creates a remember-me session.
- **Any `admin` gets in.** Line 81 accepts `hasRole('admin')`, plus hardcoded phone and email allowlists. Removing `admin` from line 81 alone would not close this. `AppServiceProvider.php:36-44` has a `Gate::before` that returns true for `admin` on every ability except `super_admin.*`, and `viewTelescope` is not a `super_admin.*` ability. So `admin` passes no matter what the gate itself says.
- **A second, wider entry point.** `routes/web.php:17-48` (`/telescope-access`) does the same `?token=` login with remember-me. It also admits ANY email ending in `@baraa-solutions.com` (`str_ends_with`).
- **Telescope is on in production.** It is registered in `bootstrap/providers.php:7-8`, and `config/telescope.php:19` defaults `enabled` to true. Storage uses `env('DB_CONNECTION')`, the central connection, so entries from every tenant land in the one central `telescope_entries` table.
- **No pruning, and the table is backed up.** `routes/console.php` schedules no `telescope:prune`. `DatabaseBackupService.php:43` dumps every table from `SHOW TABLES`, so `telescope_entries` goes into the Telegram backup.

Why the claim is overstated:
1. **Production does not record routine queries or requests.** `TelescopeServiceProvider::register()` (lines 24-31) has a filter: outside `local`, only reportable exceptions, failed (5xx) requests, failed jobs, scheduled tasks and monitored tags are kept. "Queries with bindings and request payloads for every tenant" is therefore wrong for production. Cross-tenant data exposed there is limited to exception context and 5xx request payloads. Telescope's default hidden headers and parameters (authorization, password) still apply.
2. **Only central-database users can use this.** The web and Telescope routes do not initialize tenancy, so the token lookup and role check run against the central database. A tenant's own `admin` cannot log in this way. The exposure is to central-database `admin` users and the email/phone allowlist, not to arbitrary tenant admins.
3. **A valid admin token is required.** The token-in-URL path does not bypass authentication. The real problems are tokens leaking into access logs and browser history, and a short-lived bearer token being turned into a long-lived remember-me session.

This is a real access-control and hygiene weakness: the super-admin boundary is not enforced for Telescope, tokens are accepted in URLs and turned into sessions, the table is never pruned, and it is included in backups. Given the production filter and the central-only reach, medium is the right severity rather than high.

### 34. [MEDIUM] Provisioning is not atomic: a failed DB create, migrate or seed leaves orphan tenant, domain and subscription rows that block a retry
- **Location:** `D:/projects/sroor/backend/app/Services/TenantProvisionerService.php:55`
- **Effort:** M
- **Evidence:** Tenant::create($tenantData) (line 55) fires TenantCreated. That event runs a synchronous JobPipeline [CreateDatabase, MigrateDatabase] (TenancyServiceProvider:26-32, shouldBeQueued(false)). Next come the domains (60-69), the Subscription (72-82) and the inline seeding in $tenant->run (85-132). None of it is wrapped in try/catch, compensation or a status flag. SafeMySQLDatabaseManager::createDatabase (lines 19-24) swallows every CREATE error and returns true, so a missing DB first shows up as a MigrateDatabase failure. On any exception the controller (SuperAdminApiController:104-110) only logs and returns 422. StoreTenantRequest:18-19 requires unique slug and email.
- **Impact:** Example: a super admin creates a tenant on Hostinger before pre-creating the DB in hPanel, or migration #20 fails. The tenant row (and possibly domains and subscription) stays in central, pointing at a half-migrated or missing DB. The domain resolves, but the tenant has no admin user. Re-submitting with the same slug and email fails validation. Delete is broken (see the parse error finding), so cleanup needs a manual DB edit or the tinker one-liner in the deploy script.
- **Recommendation:** Add a provisioning_status ('pending', 'ready', 'failed') column. Create the tenant row as pending, run the DB create, migrate and seed steps in a try/catch, and on failure either drop the DB and delete the row or mark it failed with the error. Offer a 'retry provisioning' action. Make createDatabase rethrow unless the DB exists and is reachable with the tenant credentials. For SaaS scale, queue the pipeline and expose the status to the UI.
- **Verification:** The finding holds up against the code, but its impact is limited to super-admin operations, so I lowered it from high to medium.

What the code shows:
- `TenantProvisionerService::provision()` (`backend/app/Services/TenantProvisionerService.php`) runs these steps one after another with no `DB::transaction`, no try/catch, no compensation and no status flag:
  - `Tenant::create` (line 55)
  - primary and custom domains (lines 60-69)
  - `Subscription::create` (lines 72-82)
  - the `$tenant->run()` seed: permissions, main store, admin user, role and settings (lines 85-132)
- `ProvisionTenantAction` (`backend/app/Actions/Tenants/ProvisionTenantAction.php`) only delegates to the service and adds no transaction.
- `TenancyServiceProvider` binds `TenantCreated` to a synchronous `JobPipeline` of `CreateDatabase` and `MigrateDatabase` with `shouldBeQueued(false)`. That event fires after the tenant row is inserted, so the row stays if the DB create or migrate fails.
- `SafeMySQLDatabaseManager::createDatabase` catches every `Throwable` and returns true. A failed CREATE DATABASE therefore only shows up later, when the migrations fail.
- `SuperAdminApiController::storeTenant` (lines 93-110) catches `Throwable`, logs it and returns 422, with no cleanup.
- `StoreTenantRequest` requires `unique:tenants,slug`, `unique:tenants,email` and `unique:domains,domain`, so resubmitting with the same values fails validation.

Cleanup is also blocked. `php -l` shows parse errors in both:
- `backend/app/Actions/Tenants/DeleteTenantAction.php` line 12
- `backend/app/Actions/Tenants/UpdateTenantDatabaseConfigAction.php` line 11

So `destroyTenant` cannot fix an orphaned tenant through the app.

Why medium and not high:
- Only a super admin can trigger it.
- There is no security exposure and no tenant business data is lost.
- A retry with a different slug and email still works.
- The orphan rows can be removed by hand.

The real harm is in operations and data consistency. The central DB keeps an active or trial tenant with a domain and a subscription, but its database is missing or half-migrated and it has no admin user.

### 35. [MEDIUM] Tenant DB credentials are stored in plaintext in tenants.data and echoed in the create response
- **Location:** `D:/projects/sroor/backend/app/Http/Controllers/Api/SuperAdminApiController.php:102`
- **Effort:** S
- **Evidence:** TenantProvisionerService:43-53 sets tenancy_db_name, tenancy_db_username and tenancy_db_password as tenant attributes. They are not in getCustomColumns() (Tenant.php:22-36), so stancl's VirtualColumn writes them to the tenants.data JSON with no encryption cast. The Tenant model has no $hidden. storeTenant returns `'tenant' => $tenant` (the raw model, not TenantResource), so toArray() includes tenancy_db_password and the other data keys. TenantResource itself excludes them, which is good.
- **Impact:** The MySQL password of each tenant DB sits in cleartext in the central DB, in any central backup or SQL dump, and in the 201 JSON response, where browser devtools, proxies and log tools can capture it. Combined with the super-admin escalation above, a tenant admin could read or submit DB credentials.
- **Recommendation:** Return TenantResource in storeTenant. Add protected $hidden = ['data', 'tenancy_db_password', 'tenancy_db_username'] to Tenant. Encrypt the password at rest: override DatabaseConfig::getPassword, or use a custom internal-key accessor with Crypt::encryptString. Validate tenancy_db_name and tenancy_db_username with a strict regex such as ^[A-Za-z0-9_]{1,64}$.
- **Verification:** The mechanics are confirmed in code. TenantProvisionerService.php:43-53 puts tenancy_db_name, tenancy_db_username and tenancy_db_password on the tenant. Tenant.php:22-36 getCustomColumns() does not list them, so stancl VirtualColumn writes them into the tenants.data JSON column (migration: $table->json('data')). Tenant.php has no 'encrypted' cast for these keys. VirtualColumn supports encrypted castables, but none are declared. The model has no $hidden. SuperAdminApiController.php:102 returns 'tenant' => $tenant (the raw model) in the 201 response, so after the save the decoded attributes are serialized, including the password. Other endpoints do use TenantResource (GetTenantDetailsAction.php:61).

Why I lowered it from high to medium:
(1) The credentials are optional. They are stored only when the super-admin types them into StoreTenantRequest (nullable) or the update-db-config endpoint. config/tenancy.php has PermissionControlledMySQLDatabaseManager commented out, so stancl does not generate per-tenant passwords automatically. In the default flow no password is stored at all.
(2) The response echo goes only to the authenticated super-admin (route group middleware can:super_admin.access, routes/api.php:196-199), who just submitted that same value. The extra exposure is limited to devtools, proxies and logs, not other users.
(3) The tenancy layer needs these credentials in a recoverable form, so they cannot be hashed. The real gap is the missing at-rest encryption (an encrypted cast or APP_KEY encryption) plus the raw-model response. That is a real hardening issue, but not high on its own. The 'tenant admin could read it' impact depends on a separate escalation finding, not on this one.

Side note: when I printed app/Actions/Tenants/UpdateTenantDatabaseConfigAction.php with cat, every $variable was missing, so the file may have been corrupted by PowerShell string interpolation. I did not confirm this with the Read tool.

### 36. [MEDIUM] tenancy_db_name is free text used in raw CREATE and DROP DATABASE, and can point a tenant at the central DB or another tenant's DB
- **Location:** `D:/projects/sroor/backend/app/Services/Tenancy/SafeMySQLDatabaseManager.php:20`
- **Effort:** S
- **Evidence:** `CREATE DATABASE IF NOT EXISTS `{$database}` ...` is built by interpolation, and parent::deleteDatabase does the same with DROP DATABASE. Validation in StoreTenantRequest:25 and UpdateTenantDatabaseConfigRequest:19 is only 'nullable|string|max:100'. On create, stancl's ensureTenantCanBeCreated rejects a DB the central user can see, but update-db-config (UpdateTenantDatabaseConfigAction, currently broken) has no such check. Delete runs DeleteDatabase synchronously (TenancyServiceProvider:39-45).
- **Impact:** Once update-db-config is repaired, setting tenant A's tenancy_db_name to tenant B's DB, or to the central DB, would give A's users B's data (a cross-tenant leak). A later DELETE of A would DROP B's or the central database. A backtick in the name allows statement injection into DDL. Today this is reachable by super admins, and through the escalation above by tenant admins.
- **Recommendation:** Whitelist the DB name format. Refuse names equal to the central DB or already used by another tenant (check tenants.data). Before DROP, verify that the target DB name matches prefix+tenant_id or is recorded as owned by this tenant. Log every credential change with the actor.
- **Verification:** The weakness is real, but the impact claimed is broader than what can be reached today.

What the code confirms:
- SafeMySQLDatabaseManager.php:20 builds `CREATE DATABASE IF NOT EXISTS `{$database}`` by interpolating the name, and swallows any error.
- The parent stancl MySQLDatabaseManager does the same for `DROP DATABASE` and for `databaseExists` (a single-quoted `'$name'` inside a SELECT).
- `tenancy_db_name` is validated only as `nullable|string|max:100` in StoreTenantRequest.php:25 and UpdateTenantDatabaseConfigRequest.php:19. There is no regex, no prefix rule, and no check that the name is unique across tenants.
- TenantProvisionerService.php:43-44 passes the value straight into `Tenant::create`.
- config/tenancy.php:65-66 maps mysql and mariadb to SafeMySQLDatabaseManager.

What lowers the severity:
1. **Update path is dead.** backend/app/Actions/Tenants/UpdateTenantDatabaseConfigAction.php fails `php -l` with a parse error (all variables were stripped). The cross-tenant re-pointing scenario cannot happen today.
2. **Delete path is also dead.** backend/app/Actions/Tenants/DeleteTenantAction.php fails `php -l` the same way, so `destroyTenant` throws and the TenantDeleted → DeleteDatabase pipeline is never reached. "A later DELETE drops B's or the central database" is not reachable today either.
3. **Create path has a guard.** stancl's CreateDatabase job calls `ensureTenantCanBeCreated` before `createDatabase`. A plain name equal to the central DB, or to any DB the central user can see, throws TenantDatabaseAlreadyExistsException.
4. **Actor is privileged.** All of these routes sit under `super-admin` with `can:super_admin.access` (routes/api.php:194). Gate::before in AppServiceProvider.php:36-42 denies `super_admin.*` to tenant `admin`. The tenant-admin escalation is a separate finding: the hardcoded super-admin phone numbers in Gate::before.

What remains exploitable now:
- A super admin creating a tenant can supply a name containing a quote or backtick. That gives SQL injection into the `databaseExists` SELECT and into the CREATE DDL.
- On shared hosting, the central user may not see other tenants' DBs, so the existence guard would not stop pointing a new tenant at another tenant's DB. The tenant would still need that DB's credentials to connect.

Fix: whitelist the name (e.g. `^[A-Za-z0-9_]+$` plus the required prefix) and reject the central DB name and names used by other tenants. Do this before UpdateTenantDatabaseConfigAction or DeleteTenantAction is repaired, because either repair raises this back to high.

### 37. [MEDIUM] Tenant deletion is an immediate hard delete: the DB is dropped synchronously, with no backup, grace period, confirmation or audit, and DROP failures are hidden
- **Location:** `D:/projects/sroor/backend/app/Providers/TenancyServiceProvider.php:39`
- **Effort:** M
- **Evidence:** TenantDeleted triggers JobPipeline[DeleteDatabase] with shouldBeQueued(false). The intended DeleteTenantAction deletes domains and then $tenant->delete(), with no soft-delete column on tenants (migration 2019_09_15_000010 has no deleted_at) and no 'cancelled' status transition. The subscriptions FK cascadeOnDelete (subscriptions migration:14) also removes billing history. SafeMySQLDatabaseManager::deleteDatabase (27-33) swallows every exception and returns true. TenantObserver::deleted only logs 'Marked for Deletion', which is misleading. The deploy script deploy_root_baraa.py:124 already runs `Tenant::where('id','tenant_sroor')->delete()` through tinker on every deploy.
- **Impact:** A single click (or a script) irreversibly destroys a customer's whole accounting, inventory and invoice history, including financial records the rules forbid hard-deleting, plus their billing history. If DROP fails (common on shared hosting), the panel reports success and the data stays as an orphan DB with no owner record.
- **Recommendation:** Make deletion two-phase. First set status=cancelled with a deleted_at or scheduled_purge_at (for example +30 days) and block access. Then a scheduled purge job takes a mysqldump or backup, records an audit row, drops the DB and surfaces any failure. Require typed-slug confirmation. Keep subscriptions (use restrictOnDelete or soft delete). Remove the tinker delete from the deploy script.
- **Verification:** Confirmed as a design problem, but two of the specific claims are wrong and the main delete path does not currently work.

Confirmed in code:
- backend/app/Providers/TenancyServiceProvider.php:39-45: TenantDeleted runs JobPipeline[Jobs\DeleteDatabase] with shouldBeQueued(false). No backup, grace period or cancelled-status step comes before it.
- backend/app/Services/Tenancy/SafeMySQLDatabaseManager.php:27-34: deleteDatabase wraps parent::deleteDatabase in try/catch(Throwable) and returns true, so a failed DROP is hidden. It is registered for mysql and mariadb in config/tenancy.php:65-66.
- The central tenants table has no deleted_at, and the Tenant model does not use SoftDeletes.
- 2019_09_15_000015_create_subscriptions_table.php:14 sets tenant_id to cascadeOnDelete, so billing history goes with the tenant. Line 15 sets plan_id to cascadeOnDelete too, so deleting a plan also deletes subscriptions.
- TenantObserver::deleted only logs "Tenant Marked for Deletion", which is misleading.

Overstated or wrong:
1. The "single click" path is broken today. backend/app/Actions/Tenants/DeleteTenantAction.php does not parse. The `$tenant` variables were stripped, leaving `execute(Tenant ): void` and `->domains()->delete();`. `php -l` reports "unexpected token ')'" on line 12, and HEAD (commit 606da74b) has the same broken text. SuperAdminApiController::destroyTenant (line 247, route DELETE /api/v1/super-admin/tenants/{id} at routes/api.php:201) injects this class, so resolving it throws a ParseError before the try block runs. The panel cannot delete a tenant right now. The risk is latent: fixing the typo turns it back into a one-click irreversible DROP, behind super-admin auth only, with no confirmation or audit.
2. The deploy-script claim is wrong about what it does. deploy_root_baraa.py:124 runs `Tenant::where('id','tenant_sroor')->delete()`. That is a query-builder mass delete, which fires no Eloquent model events. TenantDeleted is never dispatched, so the database is not dropped. It does delete the central tenant row and cascades its subscriptions and domains, which leaves the tenant DB orphaned. That is still bad, but it is not the destruction of a whole database.

Because the destructive path is currently unreachable from the panel and the script orphans the database rather than dropping it, severity is lowered from high to medium. It stays a real latent risk, and the hidden DROP failure and the subscription cascade are real now.

### 38. [MEDIUM] No safe migration rollout across tenants: deploy migrates only tenant '2m', and the run-migrations endpoint likely no-ops in production while reporting success
- **Location:** `D:/projects/sroor/backend/app/Http/Controllers/Api/SuperAdminApiController.php:226`
- **Effort:** M
- **Evidence:** runTenantMigrations calls Artisan::call('tenants:migrate', ['--tenants'=>[$id]]) inside the web request, without --force, a lock, a timeout or an audit log. stancl's Migrate::handle (vendor/stancl/tenancy/src/Commands/Migrate.php:47) calls confirmToProceed(). With APP_ENV=production (deploy_root_baraa.py:84) and no --force, it should refuse in a non-interactive web call. The response still says 'تم تشغيل وتحديث ميجريشن المستأجر بنجاح ✓' (migrations ran and updated successfully) with 'Command cancelled' in the output. The deploy runs `tenants:migrate --tenants=2m --force || true` (deploy_root_baraa.py:125), so it migrates one tenant and ignores errors. No command or table records per-tenant migration status, and nothing loops over all tenants.
- **Impact:** After a deploy that adds tenant migrations (for example the 2026_08_25 POS columns), every tenant except 2m runs new code on an old schema and gets SQL errors in POS and items. The panel's migrate button probably gives a false success. A long migration inside an HTTP request can also hit the PHP or LiteSpeed timeout and leave a half-applied schema with no record.
- **Recommendation:** Add a `tenants:migrate-all` Artisan command (or use `tenants:migrate --force` with no --tenants) that iterates tenants, records version, status, error and duration in a central tenant_migration_runs table, continues past failures and exits non-zero on any failure. Run it in deploy without `|| true`. Turn the endpoint into a queued job with --force, cache-lock the tenant, log the actor, and return the job id and status.
- **Verification:** The finding is partly confirmed and partly refuted.

Refuted: the claim that the endpoint does nothing in production while reporting success. backend/config/tenancy.php:190-194 sets 'migration_parameters' => ['--force' => true, '--path' => database/migrations/tenant, '--realpath' => true]. stancl's Migrate::handle (vendor/stancl/tenancy/src/Commands/Migrate.php) copies these into the input before it calls confirmToProceed(). So '--force' is always set, the confirmation passes, and SuperAdminApiController::runTenantMigrations (line 222-242) really migrates the tenant. If a migration throws, the exception reaches the catch block and the endpoint returns success=false with HTTP 422. It does not report a false success.

Confirmed:
- The only deploy script that migrates tenant databases is deploy_root_baraa.py:125. It runs `tenants:migrate --tenants=2m --force || true`, so it migrates one hard-coded tenant and ignores errors.
- deploy.sh:32 only runs central `migrate --force --seed || true`.
- .github/workflows/deploy.yml has no tenant migration step.
- No command, scheduled job or deploy step runs tenants:migrate over all tenants. The only other call site is PopulateRealisticTenantDataCommand:106, for a single tenant.
- No table or record tracks migration status per tenant.
- The panel endpoint migrates one tenant at a time, synchronously inside the HTTP request, with no lock, no set_time_limit or timeout handling, and no audit log.

So after a deploy that adds tenant migrations, every tenant except 2m stays on the old schema until a super-admin clicks migrate for each one. The risk of a long migration timing out inside the request is plausible. A per-tenant workaround exists and errors are reported honestly, so I lowered the severity from high to medium.

Side note, out of scope: deploy_root_baraa.py:12 has a hardcoded plaintext SSH/server password, along with the host and user in the lines above it. Each deploy also deletes tenant 'tenant_sroor' through tinker (line 124) and runs tenant:populate-realistic-data on 2m (line 129).

### 39. [MEDIUM] Feature flags are not gated on the backend, and the SPA FeatureGate component is unused and fails open
- **Location:** `backend/app/Services/TenantFeatureManager.php:13`
- **Effort:** M
- **Evidence:** Nothing calls TenantFeatureManager::isFeatureEnabled or Tenant::hasFeature (grep). No middleware, policy or route uses feature keys such as reports.advanced, blender.access, transfers.manage or api.access, which are defined in PlansAndFeaturesSeeder. On the SPA side, resources/js/Components/FeatureGate.vue is not imported anywhere (grep 'FeatureGate' finds no consumers), and it returns true when there is no tenant, when the key is missing, and as its final fallback (lines 25-27 and 46). router/index.js has no feature meta. GetSystemContextAction exposes the plan features only indirectly, through TenantResource -> PlanResource.
- **Impact:** Every module is available to every plan. Even if the SPA hid modules, a user could call the API directly. Upselling 'advanced reports' or 'transfers' gives nothing extra to customers who pay more.
- **Recommendation:** Add a 'feature:{key}' route middleware backed by TenantFeatureManagerInterface and apply it to route groups per module (reports, transfers, purchases, expenses and so on). Map plan feature keys to route groups in one config file. On the SPA, expose a resolved features map from /system/context (resolveAllFeatures) and add router meta.feature guards that fail closed. Keep the backend as the source of truth.
- **Verification:** The finding holds up against the code; I lowered the severity because the impact is lost plan revenue, not a security or data-isolation breach.

Backend:
- `TenantFeatureManager::isFeatureEnabled` (`backend/app/Services/TenantFeatureManager.php:13`) is only bound in `AppServiceProvider.php:21-22` and injected into `OverrideTenantFeatureAction`, the super-admin toggle. Nothing uses it to block a request.
- `Tenant::hasFeature` (`backend/app/Models/Tenant.php:67`) and `Plan::hasFeature` (`Plan.php:56`) have no callers anywhere in `backend/app` or `backend/routes`.
- `backend/app/Http/Middleware` has no "feature" or "plan" logic. `backend/routes` mentions features only in the super-admin endpoints `override-feature` and `plans` (`routes/api.php:204-208`).
- The feature keys `transfers.manage`, `blender.access` and `api.access` appear only in `PlansAndFeaturesSeeder` and tests. `reports.advanced` does show up in `ReportPolicy.php:15,22`, `FilterReportRequest.php:13` and `ReportController.php:181,200`, but always as a spatie role permission (`$user->can(...)`), not as a plan feature. A tenant admin with that permission gets the reports whatever the plan says.

SPA:
- `backend/resources/js/Components/FeatureGate.vue` is not imported by any file. A grep for `FeatureGate` in `resources/js` only finds the component itself.
- It does fail open: it returns true when there is no feature prop (line 21), no tenant (line 26), or the key is missing (final return, line 46).
- `useModules.js` reads the static `config/modules.json` and also returns true when a module or route is not listed (lines 6 and 16). It never looks at the tenant's plan.
- The SPA reads `plan.features` and `enabled_features` only in the super-admin screens (`useSuperAdminTenantShow.js`, `useSuperAdminPlans.js`, `SuperAdminTenantShowView.vue`).

Why medium and not high: one tenant cannot reach another tenant's data through this. The gap is that plan tiers mean nothing in practice. Customers on cheaper plans can use paid modules (transfers, blender, advanced reports, API) through the UI or the API directly, which undercuts the SaaS business model. `.claude/rules/multi-tenancy.md` rule 7 also requires gating through `TenantFeatureManager` and `FeatureGate.vue`. It is a real, confirmed gap in how plans are enforced.

### 40. [MEDIUM] Subscriptions table is written once at provisioning, so renewals, extensions and plan changes leave no ledger and MRR is wrong
- **Location:** `backend/app/Actions/Tenants/ToggleTenantStatusAction.php:11`
- **Effort:** M
- **Evidence:** Subscription::create is called only in TenantProvisionerService:72, with payment_method 'manual' and amount = plan price_monthly. ToggleTenantStatusAction updates only tenants.status and subscription_ends_at. It creates no Subscription row, records no amount, payment method or actor, and runs without a DB::transaction. No action changes a tenant's plan_id (grep plan_id in app/Actions and Requests shows it only on StoreTenantRequest). SuperAdminAnalyticsService:25-34 computes MRR from Subscription where status='active', but 'trialing' rows never move to active and expired rows never move out of active.
- **Impact:** There is no history of who extended which tenant, by how much, or for what payment, so revenue cannot be reconciled and free extensions cannot be audited. The super-admin MRR figure is wrong: converted trials count as 0, and lapsed or cancelled tenants keep counting as revenue.
- **Recommendation:** Add a RenewTenantSubscriptionAction inside DB::transaction that locks the tenant row, closes the previous subscription, inserts a new subscription row (plan, cycle, amount as string, payment_method, payment reference, created_by, notes) and updates tenants.subscription_ends_at and status. Add a ChangeTenantPlanAction. Compute MRR from current-period subscriptions only.
- **Verification:** I confirmed this from the code. The only place a subscription row is ever created is TenantProvisionerService.php:72, with status 'trialing' or 'active', payment_method 'manual' and amount = plan price_monthly. A grep for Subscription:: across app/ finds only the provisioner and SuperAdminAnalyticsService.

ToggleTenantStatusAction.php:9-22 updates only tenants.status and subscription_ends_at. It does not log the amount, payment method or actor. SuperAdminApiController::toggleStatus (line 136-143) adds no logging either. Nothing in app/ uses LogsActivity or an audit trail.

Nothing changes a tenant's plan_id after creation. plan_id appears only in StoreTenantRequest, CreateTenantDTO, PlanFilter and the provisioner, and the controller has no update-plan endpoint for tenants.

Nothing moves subscription status from 'trialing' to 'active', or from 'active' to cancelled or expired. No Console command, Job or Observer touches Subscription.

SuperAdminAnalyticsService.php:25-35 sums amount where status='active', so the claimed MRR errors are real:
- Trials that become paying through toggle/extend count as 0.
- Lapsed or suspended tenants whose provisioning row was 'active' keep counting.

I am lowering the severity from high to medium:
- This is a missing billing ledger / reporting feature. It does not corrupt money or stock data.
- Payments are manual and happen outside the system, so no real charge is lost or doubled.
- The impact is a wrong dashboard KPI and no audit trail for extensions done by a super-admin.

The "no DB::transaction" point is minor, because the action runs a single UPDATE statement.

### 41. [MEDIUM] No expiry, reminder or grace-period automation; no payment gateway, subscription invoices or tenant billing page
- **Location:** `backend/routes/console.php`
- **Effort:** XL
- **Evidence:** routes/console.php schedules only queue:work, queue:restart, pulse:clear, notify:* Telegram commands and backup:telegram. app/Console/Commands and app/Jobs have nothing about subscriptions or trials. A grep for paymob, fawry, stripe, paypal, kashier and webhook in app, routes, config and composer.json finds nothing related to billing. There are no tenant-facing billing or subscription routes in routes/api.php, and no notification classes for trial ending or payment due.
- **Impact:** Moving trial -> paid -> expired -> suspended depends entirely on a super admin remembering to act by hand. Tenants get no warning before expiry and have no self-service way to pay or upgrade. Revenue leaks and churn handling is manual.
- **Recommendation:** Add a daily central command (no tenancy loop needed) that sends reminders at T-7/T-3/T-1 days, moves tenants to past_due on expiry, and moves them to suspended after N grace days, each step recorded in subscriptions. Expose GET /api/v1/billing/status to tenants (plan, limits, usage, days left). Then integrate a local gateway such as Paymob or Fawry with signed webhooks and idempotent payment records, or at least a manual payment-receipt workflow that creates subscription rows.
- **Verification:** The finding holds up in the code, with one partial guard that makes it slightly overstated.

**What the code shows:**
- `backend/routes/console.php` schedules only these commands:
  - `queue:work` and `queue:restart`
  - `pulse:clear`
  - `notify:daily-summary`, `notify:low-stock` and `notify:overdue-shifts`
  - `backup:telegram`
- `app/Console/Commands` contains only Telegram, export, populate and sync-hosts commands. `app/Jobs` contains only low-stock, overdue-shift, daily-summary and backup jobs. Nothing touches trial, subscription expiry, reminders or grace periods.
- `composer.json` has no stripe, cashier, paymob, paypal or fawry package.
- `routes/api.php` and `routes/tenant.php` have no billing or subscription routes.
- `Subscription` (`app/Models/Subscription.php`) stores `payment_method` and `payment_details` that the super admin fills in by hand. Nothing in the code changes them on its own.
- The `Tenant` model has helpers for each state: `isOnTrial`, `isActive` and `isSuspended`. `isSuspended` returns true when `subscription_ends_at` is in the past.

**The partial guard:**
- `isSuspended()` is called in only one place: `app/Actions/Tenants/ResolveTenantWorkspaceAction.php:48`, the central workspace-code resolver. There it returns a 403 for a suspended tenant or one whose paid subscription has expired.
- So paid expiry is not purely manual. It is checked lazily when a client looks up the workspace.

**Gaps around that guard, which back up the finding:**
- `isSuspended()` ignores `trial_ends_at`. A tenant with status `trial` whose trial has ended is never blocked. Only manual action by the super admin moves it out of trial.
- `app/Http/Middleware/ResolveApiTenancy.php` starts the tenant context from the X-Tenant header, the query or the host without checking status or expiry. Clients that already resolved the workspace or hold a token keep working after expiry.
- Nothing sends a warning before expiry, nothing applies a grace period, nothing changes the status automatically, there is no payment gateway, and tenants have no billing or upgrade page.

**Severity:** this is a missing product and revenue feature, not a security or data-integrity bug. Some lazy enforcement exists for paid expiry. I am lowering it from high to medium. The lack of any status or expiry check in `ResolveApiTenancy` is arguably worth its own finding.

### 42. [MEDIUM] No audit trail or throttle on platform-operator actions
- **Location:** `backend/app/Http/Controllers/Api/SuperAdminApiController.php:135`
- **Effort:** M
- **Evidence:** grep found no ActivityLog/activityLog usage in app/Actions/Tenants, app/Actions/Plans, SuperAdminApiController or TenantProvisionerService. toggleStatus, overrideFeature, updateTenantUnits, runTenantMigrations, destroyTenant and updatePlan write central state with no log. routes/api.php has no throttle middleware anywhere (grep 'throttle' returned nothing).
- **Impact:** Suspensions, free subscription extensions, feature unlocks and tenant deletions cannot be traced to a person. Combined with the two escalation paths above, abuse would go undetected, and a SaaS cannot settle a billing dispute without these records.
- **Recommendation:** Write a central, append-only platform_audit_logs entry (actor central id, tenant id, action, before/after, IP) from each super-admin Action. Add throttle:api, plus a stricter limit on auth routes.
- **Verification:** The finding holds, but it overstates the gap a little. Confirmed: SuperAdminApiController.php toggleStatus (line 135), overrideFeature, updateTenantUnits, runTenantMigrations, destroyTenant, updateDatabaseConfig and updatePlan never call ActivityLogService or ActivityLog. The only ActivityLogService use in the super-admin area is login, in app/Actions/Auth/SuperAdminLoginAction.php. No route uses throttle middleware. bootstrap/app.php never calls throttleApi() and adds no throttle to the api group. The only rate limiting is RateLimiter inside LoginRequest and ApiLoginRequest. The super-admin group in routes/api.php (lines 196-220) is guarded only by can:super_admin.access.

What the finding misses: app/Observers/TenantObserver.php is registered in AppServiceProvider.php:53. It writes plain Log:: lines to the file log for tenant status changes, enabled_features changes and tenant deletion. Those lines do not record who acted. They also do not cover subscription extensions (extend_days), when the status itself stays the same. Plan, unit, DB-config and migration actions are not logged at all. So some trace exists, but it is not an audit trail tied to a person, and the impact is still real.

Why medium and not high: missing throttling on authenticated super-admin endpoints adds little risk, and the core issue is weak accountability and billing-dispute evidence, not a direct way to exploit the system. The 'abuse goes undetected' impact depends on the separate escalation findings and does not stand on its own.

### 43. [MEDIUM] DeleteTenantAction and UpdateTenantDatabaseConfigAction are committed with PHP parse errors; their endpoints always fatal
- **Location:** `backend/app/Actions/Tenants/DeleteTenantAction.php:12`
- **Effort:** S
- **Evidence:** `php -l` reports 'syntax error, unexpected token ")"' on DeleteTenantAction.php:12 and 'unexpected token ","' on UpdateTenantDatabaseConfigAction.php:11. All $variables were stripped, e.g. `public function execute(Tenant ): void { DB::transaction(function () use () { ->domains()->delete(); ->delete(); }); }`. It looks like a shell $-expansion accident at commit 606da74b. These are the only broken files under app/, routes/ and config/. SuperAdminApiController::destroyTenant and updateDatabaseConfig inject them per method, so the 13 SuperAdmin tests still pass (I ran them: 13 passed, 55 assertions). No test calls these routes.
- **Impact:** DELETE /super-admin/tenants/{id} and POST update-db-config return 500 (ParseError), so operators cannot change tenant DB credentials from the panel. If destroy is fixed naively, note that tenant->delete() fires TenantDeleted -> DeleteDatabase (TenancyServiceProvider.php:39-44). That is an irreversible hard drop of the tenant DB with no soft-delete, grace period or backup, which is a money/data-loss risk.
- **Recommendation:** Restore both files from intent and add feature tests for both routes. Change destroy into a soft 'archive' status with a delayed, backed-up purge, and require confirmation (typed slug) and an audit entry.
- **Verification:** I confirmed this from the code. Both action files are committed with every $variable stripped out. backend/app/Actions/Tenants/DeleteTenantAction.php:12-16 reads `execute(Tenant ): void`, `use ()`, `->domains()->delete(); ->delete();`. UpdateTenantDatabaseConfigAction.php:11-26 reads `execute(Tenant , array )`, ` = [];`, `->update();`, `return ;`. HEAD has the same broken content (git show), so this is not a local working-tree change. Running `php -l` on each file gives a parse error: 'unexpected token ")"' at line 12 and 'unexpected token ","' at line 11. The file history fits the theory that commit 606da74b ("ensure DeleteTenantAction has clean UTF-8 without BOM") rewrote the file and lost its variables.

The routes are live. routes/api.php:201-202 maps DELETE /tenants/{id} and POST /tenants/{id}/update-db-config to SuperAdminApiController::destroyTenant (line 247) and updateDatabaseConfig (line 268). Each method takes its action as a method parameter, so the container autoloads the broken class while resolving the parameters. That is before the method's try/catch(Throwable) runs, so the ParseError is not caught and the request returns a 500. No other part of the app references these classes, so the rest of the app and the existing tests are unaffected.

The latent hard-drop risk is also real. TenancyServiceProvider.php:39-44 maps TenantDeleted to the DeleteDatabase job, synchronously, with no soft delete and no backup step.

I lowered the severity from high to medium. Both endpoints are unusable, which is a real gap in super-admin operations. But nothing leaks across tenants and nothing corrupts money data. The break actually blocks the destructive delete path for now. The data-loss concern only applies if someone repairs the file without adding safeguards. Two smaller issues: the controller returns hardcoded Arabic messages and puts the raw $e->getMessage() in the response, but these are separate findings.

### 44. [MEDIUM] Per-tenant branding is incomplete: logos are global public files, uploads are discarded, and manifest/Android/desktop are hardcoded to Sroor/2m.baraa-solutions.com
- **Location:** `backend/app/Actions/Settings/UpdateSettingsAction.php:16`
- **Effort:** L
- **Evidence:** UpdateSettingsRequest.php:39-41 validates logo_file, logo_light_file and logo_dark_file, but UpdateSettingsAction skips them ($excludeKeys) and nothing stores them. GetSystemContextAction.php:134-136 always returns '/logo-light.png', '/logo-dark.png' and '/logo.png' from public/, and print-thermal.blade.php:69 embeds public_path('logo.png'), so every tenant shares one logo. Branding that IS per-tenant (tenant DB settings): company_name, company_subtitle, company_phone/address, invoice_footer_note, invoice_primary_color (amber/emerald/blue/slate), system_theme_color and print toggles. No custom domain management endpoint exists. Static public/manifest.json is 'سرور كوفي', id 'sroor-coffee-pos-app', and it shadows the dynamic /manifest.json route (web.php:285) on any web server that serves static files first. capacitor.config.json pins server.url to https://2m.baraa-solutions.com with cleartext:true, appId com.sroor.cofe.erp. android strings.xml:3 is 'سرور كوفي ERP'. desktop/main.js:195,349,481,540 fall back to https://2m.baraa-solutions.com and builds URLs as `${tenant}.baraa-solutions.com` (no custom domains). app.blade.php:97 hardcodes 'سرور كوفي ERP & POS'.
- **Impact:** The product cannot be sold as white-label or generic. Customers see another company's logo and name on receipts and in the installed PWA/APK. The Android build is tied to one specific tenant. A tenant's logo upload silently does nothing.
- **Recommendation:** Store logos per tenant with the tenant-aware filesystem (Storage disk under tenant storage) and serve them through a tenant route. Remove public/manifest.json and keep the dynamic manifest, resolved per tenant. Make the Capacitor app a generic shell with a workspace-connect screen (no server.url, no cleartext). Parameterize the Electron base domain and support custom domains. Add a domains management API for super admins.
- **Verification:** I confirmed this in the code. UpdateSettingsRequest.php:39-41 validates logo_file, logo_light_file and logo_dark_file. UpdateSettingsAction.php:16 skips them through $excludeKeys. SettingController::update (line 79-82) only passes $request->validated() to that action. No storeAs or move call for logos exists anywhere in app/; the only storeAs is for APKs. So the BrandingTab.vue upload (line 23) is silently thrown away. GetSystemContextAction.php:134-136 always returns the shared /logo*.png paths; only the ?v= cache-buster is a per-tenant setting. print-thermal.blade.php:69, print-a4 and print-daily-journal-a4 embed public_path('logo.png'). app.blade.php:97 hardcodes 'سرور كوفي ERP & POS'. public/manifest.json has id 'sroor-coffee-pos-app' and the Sroor coffee name; the dynamic /manifest.json route at web.php:285 does read platform_name from settings. capacitor.config.json pins server.url to https://2m.baraa-solutions.com, sets cleartext:true and uses appId com.sroor.cofe.erp. The Android strings.xml:3-4 name is 'سرور كوفي ERP'. desktop/main.js:195/349/481/540 fall back to 2m.baraa-solutions.com and build `${tenant}.baraa-solutions.com` URLs. Two small mitigations exist. ResolveTenantWorkspaceAction.php:73 reads a per-tenant settings['logo_url'], but nothing ever writes it. Domains can only be created from a console command (PopulateRealisticTenantDataCommand); there is no custom-domain endpoint. I lowered the severity to medium. This is a product-readiness and functional bug (a silently ignored upload plus white-label gaps), not a data-isolation or security problem. No tenant data leaks: all tenants just see the same vendor logo. The hardcoded mobile and desktop app IDs and URLs are per-build packaging config, not runtime tenant logic. One thing is overstated: the claim that the static manifest shadows the dynamic route depends on how the web server is configured.

### 45. [MEDIUM] Bearer tokens are accepted and propagated through query strings, stored in plaintext, never expire, and are captured by Telescope
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:21`
- **Effort:** M
- **Evidence:** ApiTokenAuth.php:21 accepts ?api_token= as a token source. SuperAdminLayout.vue:33 builds /telescope-access?token=<token>, which web.php:17-48 and the TelescopeServiceProvider.php:59-71 gate consume, logging the user into the web guard with remember=true. ApiLoginAction and ApiQuickLoginAction store the full plaintext token in users.api_token, and ApiTokenAuth.php:44-48 authenticates by plaintext equality. There is no config/sanctum.php, so expiration is null, and ApiTokenAuth calls PersonalAccessToken::findToken without checking expires_at. TelescopeServiceProvider.php:45-49 hides cookie/csrf headers but not 'authorization'. Telescope records failed requests in production (lines 24-31) into the central DB (config/telescope.php:62), so failed requests from all tenants land in one store.
- **Impact:** Tokens end up in web-server and proxy access logs, browser history and Referer headers, and in Telescope's central table, which is readable by any central 'admin'. A DB dump or read-only SQL access exposes usable tokens directly. Tokens stay valid forever unless the user logs out.
- **Recommendation:** Drop query-string token support and the legacy api_token column lookup (migrate to Sanctum-only). Set a sanctum expiration and enforce it in the middleware, or switch to auth:sanctum. Add 'authorization' and 'x-api-token' to Telescope hideRequestHeaders, and add api_token and token to hideRequestParameters. Replace /telescope-access?token with a central session login.

### 46. [MEDIUM] Tenant/domain resolution quirks: hardcoded and positional central domains, header overridden by host, LIKE wildcards, unthrottled tenant enumeration
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ResolveApiTenancy.php:48`
- **Effort:** S
- **Evidence:** The fallback central list is hardcoded with baraa-solutions.com (ResolveApiTenancy.php:48-53, config/tenancy.php:19-26 including 'sroor.test'). ResolveTenantWorkspaceAction.php:62 builds tenant URLs from config('tenancy.central_domains.2') and ImpersonateTenantAction uses central_domains.0 ('127.0.0.1'), so both depend on array order. After X-Tenant initializes tenant B (lines 30-37), step 3 still runs on a non-central host and re-initializes to the host's tenant, which silently overrides the header. The subdomain fallback (lines 60-64) takes the first label of ANY non-central host (for example a staging or api subdomain, or an attacker-supplied Host), and the exclusion list is only www/mail/cpanel/webmail. ResolveTenantWorkspaceAction.php:35 uses like "{$code}.%" with unescaped %/_. The resolver route (api.php:22, 226) has no throttle and returns tenant name and status to anyone. $request->input('tenant') (line 30) also reads body fields named 'tenant'.
- **Impact:** Moving to a new brand or domain needs code edits. Reordering the config breaks the URLs the mobile app uses. Typo or staging hosts fall back to central (see the tenancy-guard finding). Anyone can enumerate tenant ids, slugs and names. Ambiguous header/host precedence makes behaviour hard to reason about.
- **Recommendation:** Read central domains only from config/env (for example CENTRAL_DOMAINS comma-separated) and use a named key (tenancy.primary_domain) instead of positional indexes. Choose one precedence (host first; reject a mismatched X-Tenant on a tenant host with 400). Resolve subdomains only when the host ends with a configured central domain. Escape LIKE input, and throttle the resolver.

### 47. [MEDIUM] Default seeded super-admin credentials documented in source; personal phone numbers hardcoded as auth principals
- **Location:** `D:\projects\sroor\backend\database\seeders\DatabaseSeeder.php:16`
- **Effort:** S
- **Evidence:** The comment at DatabaseSeeder.php:16 states the super-admin login phone together with its default password (type: default plaintext password in a code comment). Lines 22 and 33 set seeded passwords for the two super-admin accounts (type: hardcoded default credential). The same two phone numbers are hardcoded as authorization principals in AppServiceProvider.php:38,49, TelescopeServiceProvider.php:82, web.php:38, UserResource.php:19 and several FormRequests. Values are not reproduced here.
- **Impact:** If production was seeded and the password was never rotated, the super-admin account can be logged into directly. Combined with quick-login, the phone alone is enough. The phone numbers are personal data committed to the repo.
- **Recommendation:** Rotate the production super-admin passwords. Make the seeder read credentials from env (or generate them randomly and print once). Remove phone and email literals from all authorization code.

### 48. [MEDIUM] Telegram calls are made synchronously inside the shift-close DB transaction
- **Location:** `backend/app/Services/ShiftService.php:199`
- **Effort:** S
- **Evidence:** closeShift() opens DB::transaction at line 161 and calls TelegramService::sendShiftDiscrepancyNotification at line 199, before the closure returns at line 205. sendMessage loops over chat ids with Http::timeout(10) (TelegramService.php:73-74).
- **Impact:** A slow or unreachable Telegram API holds the shift row locks and a DB connection for up to 10 seconds per chat id while the cashier waits. Messages can also be sent for a transaction that later rolls back.
- **Recommendation:** Dispatch a job after commit (DB::afterCommit, or a job with ->afterCommit()), once the tenant-safe queue fix below is in place.

### 49. [MEDIUM] Jobs dispatched inside a tenant go into the tenant DB jobs table, which the central worker never reads
- **Location:** `backend/config/queue.php:40`
- **Effort:** S
- **Evidence:** 'connection' => env('DB_QUEUE_CONNECTION') is null, and QueueManager has no connection memoized at boot (checked with tinker: connections []). A dispatch made while a tenant is active therefore resolves DatabaseQueue on the 'tenant' connection, and the tenant migrations include a jobs table (database/migrations/tenant/0001_01_01_000002_create_jobs_table.php). The worker started by routes/console.php:12 (queue:work from schedule:run) runs in central context and reads the central jobs table. failed_jobs and job_batches are pinned to DB_CONNECTION (queue.php:106,125), so they are central. QueueTenancyBootstrapper (config/tenancy.php:38) adds tenant_id to the payload and initializes tenancy on JobProcessing, but this only helps if the job reaches a worker.
- **Impact:** This is dormant today because no job is dispatched, but the first tenant-side ShouldQueue (notifications, exports, the fix suggested above) will sit in tenant DBs and never run, with no error.
- **Recommendation:** Set queue.connections.database.connection to the central connection (config('tenancy.database.central_connection')), and stop creating jobs/failed_jobs/job_batches tables in tenant migrations with a new migration (never edit a shipped one). Add a test that dispatches from tenant context and asserts the row lands centrally with tenant_id.

### 50. [MEDIUM] Pulse silently records nothing for tenant traffic; Pulse and Telescope can store Telegram bot tokens found in URLs
- **Location:** `backend/config/pulse.php:67`
- **Effort:** S
- **Evidence:** pulse.storage.database.connection = env('PULSE_DB_CONNECTION') is null. DatabaseStorage::connection() (vendor/laravel/pulse/src/Storage/DatabaseStorage.php:850) resolves it when ingesting at terminate time, which is the 'tenant' connection, and tenant DBs have no pulse_* tables. Pulse::rescue() swallows the error because handleExceptionsUsing is null (Pulse.php:594-600). SlowOutgoingRequests has no 'groups' or 'ignore' rules (lines 182-194), and TelegramService builds https://api.telegram.org/bot{token}/... URLs (lines 73, 334). The Telescope ClientRequestWatcher is on (telescope.php:146), and its storage is pinned to central DB_CONNECTION (telescope.php:62).
- **Impact:** Operators see no Pulse data for any tenant. Telegram calls slower than 1 s (sendDocument often is) store the full bot URL, token included, in central pulse tables, which the dashboard shows. Telescope does the same in a local environment or for tagged entries. Guzzle exception messages logged at TelegramService.php:89 can also put the URL into laravel.log.
- **Recommendation:** Set PULSE_DB_CONNECTION to the central connection, and add a groups/ignore regex that strips '/bot[^/]+/'. Do the same in Telescope with Telescope::filter/tag, and redact tokens before logging exceptions.

### 51. [MEDIUM] The public disk is tenant-suffixed but its URL is not, and APK uploads and downloads depend on which tenant context the request happens to be in
- **Location:** `backend/app/Actions/AppVersions/CreateAppVersionAction.php:31`
- **Effort:** M
- **Evidence:** config/tenancy.php:107-121 suffixes the 'local' and 'public' roots (storage/tenant<id>/app/public), but filesystems.php:44 keeps url = APP_URL/storage, there is no url_override, and asset_helper_tenancy is false. AppVersion rows are pinned to the central DB (AppVersion.php:18), but the file is written with storeAs(..., 'public') and read with Storage::disk('public') in DownloadLatestApkAction.php:26/58. Both routes run inside ResolveApiTenancy (routes/api.php:19-26, 217-220), so the X-Tenant header or a tenant subdomain switches the disk root. On a miss, the download falls back to legacy files (public/sroor-cofe-erp-2m.apk, app.apk). The tenant 'local' root becomes storage/tenantX/app/ while the central root is app/private. There is no public/storage symlink in the dev checkout, and nothing creates tenant storage directories on TenantCreated.
- **Impact:** Clients downloading from a tenant host or with X-Tenant never find the uploaded APK and get a stale legacy build, or a 404. An upload from a super-admin session that carries X-Tenant is stored in that tenant's folder and is invisible centrally. Any future tenant upload (logos, exports) would produce /storage/... URLs that 404 or point at central files.
- **Recommendation:** Store platform artifacts on a dedicated non-tenant disk (for example 'central_public', not in tenancy.filesystem.disks), and put app-version and super-admin routes outside ResolveApiTenancy. For tenant files, serve through tenant_asset() or the /tenancy/assets route, or use S3 with a per-tenant prefix. Remove the legacy APK fallbacks.

### 52. [MEDIUM] Super-admin impersonation is not audit-logged
- **Location:** `backend/app/Actions/SuperAdmin/ImpersonateTenantAction.php:38`
- **Effort:** S
- **Evidence:** tenancy()->impersonate() creates the token, and the tenant-side /impersonate/{token} route (routes/tenant.php:33-36) only sets session flags. Neither side calls ActivityLogService. ActivityLog has no connection set, so it follows the active connection: tenant actions go to the tenant activity_logs and only SuperAdminLoginAction writes central rows. No other super-admin action (toggle-status, override-feature, run-migrations, delete) logs anything either.
- **Impact:** Changes made while impersonating are recorded in the tenant's activity log as the tenant admin, and the platform keeps no record of who impersonated whom, or of super-admin tenant changes. That is a compliance and dispute-resolution gap for a SaaS that sells to tenants.
- **Recommendation:** Write a central audit row for every super-admin mutation and impersonation, and tag tenant activity_logs.properties with impersonated_by while the session flag is set.

### 53. [MEDIUM] Onboarding seeds only the minimum and is super-admin-only, with no self-service signup, welcome flow, units or reserved-slug checks
- **Location:** `D:/projects/sroor/backend/app/Services/TenantProvisionerService.php:85`
- **Effort:** L
- **Evidence:** The tenant seed is PermissionsSeeder, one main Store (MAIN-01), one admin user, and Settings company_name, company_subtitle (hardcoded Arabic) and company_phone. It does not set inventory_units, even though GetTenantDetailsAction:31 falls back to a hardcoded list, and seeds no default category or payment methods. No register, signup or onboard route exists in routes/*.php. Slug validation is alpha_dash only, with no reserved list (www, admin, super, api), and uppercase is allowed while subdomains are case-insensitive. If an existing user matches the email, or the fallback phone '01000000000' (lines 99-121), that user's password is overwritten and the user is made admin, which matters when a pre-existing Hostinger DB is adopted. No welcome email or credential delivery exists. The password is set by the super admin with min:6.
- **Impact:** Every customer must be onboarded by hand by the platform owner, which does not scale for a sellable SaaS. Tenants start without configured units or settings. A slug like 'www' or 'admin' collides with central host routing. Adopting a pre-existing DB can silently take over an existing account.
- **Recommendation:** Add a TenantBootstrapSeeder (permissions, roles, units from the global list or plan, default settings, currency, walk-in customer). Add slug rules: lowercase, a reserved-word blacklist, and DNS label length. Add a public signup flow (rate-limited, email-verified, trial plan) that queues provisioning, plus a welcome or invite email so the super admin never chooses the tenant password.

### 54. [MEDIUM] Toggle-status accepts 'expired', which is not in the tenants.status enum, and cannot set 'cancelled'
- **Location:** `D:/projects/sroor/backend/app/Http/Requests/ToggleTenantStatusRequest.php:19`
- **Effort:** S
- **Evidence:** The rule is 'in:active,trial,suspended,expired'. The migration 2019_09_15_000010_create_tenants_table.php:28 defines enum('status', ['active','trial','suspended','cancelled']).
- **Impact:** Choosing 'expired' in the panel fails with a DB error on MySQL strict mode or the SQLite CHECK, shown as a 422 with the raw SQL message. 'cancelled', the natural soft-delete state, cannot be set at all.
- **Recommendation:** Align the enum and the rule. Add a migration that alters the enum to include 'expired', or drop 'expired' from the rule and add 'cancelled'. Keep the status values in one PHP enum used by both.

### 55. [MEDIUM] Central/tenant schema split is never tested: tests merge tenant migrations into the central sqlite DB and fake TenantCreated
- **Location:** `D:/projects/sroor/backend/tests/TestCase.php:13`
- **Effort:** M
- **Evidence:** TestCase::setUp runs `migrate --path=database/migrations/tenant` on the same :memory: DB as the central migrations. SuperAdminApiTest:33-39 fakes TenantCreated, CreatingDatabase and MigratingDatabase, so the CreateDatabase and MigrateDatabase pipeline never runs. As a result, central code that uses tenant-only tables passes. For example, SuperAdminApiController::updatePlatformSettings calls Setting::set in the central context, but no central migration creates a 'settings' table (only tenant 2026_08_10_221000 does). Plan and Subscription lack CentralConnection, so they query the tenant DB if they run while tenancy is initialized.
- **Impact:** Bugs that only appear with real DB-per-tenant (missing central tables, wrong connection, failed provisioning) reach production. POST /super-admin/settings likely throws 'no such table: settings' on a clean central DB.
- **Recommendation:** Add an isolation test suite that uses a file or second sqlite connection for central and lets the TenantCreated pipeline run (SQLite manager). Assert provisioning creates a separate DB with an admin user, and that cross-tenant reads are impossible. Add a central settings (platform_settings) migration, or move platform settings to a dedicated central model with CentralConnection. Put CentralConnection on Plan, PlanFeature, Subscription and Domain.

### 56. [MEDIUM] Status values disagree across DB enum, validation and UI, so 'expired' crashes and 'pending'/'cancelled' are rejected
- **Location:** `backend/app/Http/Requests/ToggleTenantStatusRequest.php:19`
- **Effort:** S
- **Evidence:** The DB enum is ['active','trial','suspended','cancelled'] (2019_09_15_000010_create_tenants_table.php). The request validates in:active,trial,suspended,expired. The SPA modals EditTenantStatusModal.vue and TenantStatusModal.vue (statusOptions ~line 73) offer active, suspended, pending and cancelled, and TenantsTable.vue:205 renders an 'expired' badge.
- **Impact:** Choosing 'cancelled' or 'pending' in the super-admin UI returns 422. Sending 'expired' through the API passes validation, then MySQL strict mode rejects it with a data truncation error, and the client gets a raw message or a 500. A tenant cannot be marked cancelled or expired, and trial cannot be set from the UI.
- **Recommendation:** Define a TenantStatus backed enum (trial, active, past_due, suspended, cancelled, expired). Add a guarded tenant-status migration that alters the enum, or switch the column to string. Use Rule::enum in the request and source the SPA options from the same list via the API.

### 57. [MEDIUM] Super-admin plan management is update-only, with weak validation and no propagation semantics
- **Location:** `backend/app/Http/Requests/UpdatePlanRequest.php:11`
- **Effort:** M
- **Evidence:** routes/api.php has only GET /super-admin/plans and PUT /super-admin/plans/{id}; there is no create, archive or delete endpoint. UpdatePlanRequest::authorize() returns true, so it relies only on the route gate. 'features' => 'required|array' does not validate keys against plan_features or require boolean values, while Tenant::hasFeature uses a strict === true check and TenantFeatureManager casts with (bool), so the two disagree on values like '1'. 'price_monthly' => 'numeric' feeds a DECIMAL(10,2) column. UpdatePlanAction does a plain $plan->update($data) with no transaction or audit trail. OverrideTenantFeatureRequest:18 accepts any feature_key string up to 100 characters, with no exists:plan_features,key check.
- **Impact:** Plans can only be edited from the seeded set. A typo in a feature key is saved silently. Editing a plan instantly changes entitlements and prices for every existing tenant on it, with no grandfathering or record of the change. Overrides can only add features, never remove one the plan grants.
- **Recommendation:** Add create and archive endpoints (is_active=false instead of delete, because tenants and subscriptions reference the plan). Validate features.* keys against plan_features and require boolean values. Make price fields decimal:0,3 strings. Snapshot price on subscription rows so plan edits do not rewrite existing contracts. Support explicit enable/disable overrides.

### 58. [MEDIUM] isSuspended() semantics are inconsistent with the status field
- **Location:** `backend/app/Models/Tenant.php:176`
- **Effort:** S
- **Evidence:** isSuspended() returns true for status 'suspended' or a past subscription_ends_at, but ignores 'cancelled' and trial_ends_at. ToggleTenantStatusAction lets a super admin set status 'active' without extend_days, which leaves a past subscription_ends_at. The provisioner sets subscription_ends_at equal to trial_ends_at for trials, and to now()+1 month for non-trial tenants even when nothing was paid.
- **Impact:** The UI can show a tenant as 'active' while the workspace resolver rejects it as suspended, and a 'cancelled' tenant passes the resolver. Support teams will get conflicting states.
- **Recommendation:** Put the state in a single computed method, for example Tenant::accessState() returning trial, active, grace, expired, suspended or cancelled, and use it in the resolver, the new middleware and the super-admin UI. Require an end date when a tenant is moved to active.

### 59. [MEDIUM] No tests cover enforcement, limits, expiry or tenant self-escalation
- **Location:** `backend/tests/Feature/Api/SuperAdminApiTest.php:157`
- **Effort:** M
- **Evidence:** The only billing-related test file is SuperAdminApiTest. It checks that toggle-status writes 'suspended' to the DB (line 178) and that a plan update persists (line 217). Nothing asserts that a suspended or expired tenant is blocked, that limits are enforced, that features are gated, or that a tenant-level super_admin role is rejected.
- **Impact:** Regressions in the commercial model, or in the escalation path above, would ship unnoticed.
- **Recommendation:** Add feature tests for each lifecycle state (blocked or allowed per route), each plan limit at the boundary, feature-gated routes, and tenant-user-with-super_admin-role getting 403 on /super-admin/*.

### 60. [MEDIUM] Tenant DB credentials leak in the storeTenant response and are stored in plaintext in central tenants.data
- **Location:** `backend/app/Http/Controllers/Api/SuperAdminApiController.php:101`
- **Effort:** S
- **Evidence:** storeTenant returns `'tenant' => $tenant`, the raw model, not TenantResource. TenantProvisionerService.php:44-52 puts tenancy_db_name, tenancy_db_username and tenancy_db_password into the tenant's virtual data. App\Models\Tenant has no $hidden. stancl VirtualColumn decodes data back onto attributes after save, so toArray() includes tenancy_db_password. showTenant/tenants use TenantResource, which is clean. showTenant (line 129) and the other catches echo $e->getMessage() back to the client.
- **Impact:** The MySQL password for a tenant DB is returned in JSON, ends up in browser devtools, proxies and Telescope request recording (Telescope is enabled by default, config/telescope.php:19), and sits unencrypted in the central DB. If any of those leak, an attacker gets direct DB access to that tenant.
- **Recommendation:** Return TenantResource from storeTenant. Add `protected $hidden = ['tenancy_db_password', 'tenancy_db_username', 'data']` to Tenant. Encrypt the stored password (stancl supports a custom DB manager / encrypted cast). Return generic error messages.

### 61. [MEDIUM] Platform settings and global units write to the 'settings' table, which only exists in tenant DBs
- **Location:** `backend/app/Http/Controllers/Api/SuperAdminApiController.php:343`
- **Effort:** M
- **Evidence:** getPlatformSettings, updatePlatformSettings, getUnits and updateUnits use App\Models\Setting, which has no connection override and uses the default connection. The only migration creating 'settings' is database/migrations/tenant/2026_08_10_221000_create_settings_table.php. None of the 17 central migrations creates it. Setting::get swallows Throwable and returns defaults, while Setting::set would throw a QueryException on a fresh central DB. SuperAdminApiTest passes only because it migrates the tenant migrations into the same sqlite DB (setUp line 41). GetSystemContextAction.php:127 and app.blade.php:15 read platform_name in tenant context, from the tenant DB, so the central platform name never reaches tenants. A tenant admin can also overwrite inventory_units via UpdateSettingsRequest.php:35, which defeats the super admin's per-tenant allowed_units (updateTenantUnits writes both tenant->data and the tenant setting).
- **Impact:** On a clean SaaS install, saving platform branding returns 500. In production it may only 'work' because the central DB is a legacy single-tenant DB with a settings table. Per-tenant unit restrictions are advisory only.
- **Recommendation:** Add a central platform_settings table and model on the central connection (or store it in config/DB via a CentralSetting model). Read platform-level values with tenancy()->central(). Treat tenant->data['allowed_units'] as the authority and validate tenant inventory_units against it.

### 62. [MEDIUM] Telescope gate admits any 'admin', accepts tokens in the query string, and creates a remember-me session; has hardcoded phone/email backdoors
- **Location:** `backend/app/Providers/TelescopeServiceProvider.php:59`
- **Effort:** S
- **Evidence:** The viewTelescope gate reads request()->query('token'), resolves a PAT or api_token, calls auth('web')->login($user, true), and allows hasRole('admin'), two hardcoded phones or two @baraa-solutions.com emails. routes/web.php:17-49 /telescope-access does the same, and also allows any email ending '@baraa-solutions.com'. Telescope is enabled by default (config/telescope.php:19).
- **Impact:** Telescope exposes request payloads, queries and exceptions for the whole platform. Tokens in URLs end up in access logs and Referer headers. A plain 'admin' (not a platform role) gets platform-wide observability data.
- **Recommendation:** Restrict to a central-only platform role, drop query-string tokens and backdoors, and disable Telescope in production or bind it to an IP allowlist.

### 63. [MEDIUM] TenantPolicy is unused and would grant tenant 'admin' platform rights; FormRequests also authorize hasRole('admin')
- **Location:** `backend/app/Policies/TenantPolicy.php:14`
- **Effort:** S
- **Evidence:** Every TenantPolicy method returns hasRole('super_admin') || hasRole('admin') || can('super_admin.access'). grep finds no usage. ToggleTenantStatusRequest:13, OverrideTenantFeatureRequest:13, UpdatePlatformSettingsRequest, UpdateSystemUnitsRequest, UpdateTenantDatabaseConfigRequest and UpdateTenantUnitsRequest all authorize hasRole('admin') plus hardcoded phones. Only the route-level can:super_admin.access (with Gate::before returning false for non-super admins) stops tenant admins. SuperAdminLoginAction.php:43 also lets any 'admin' log into the super-admin web login.
- **Impact:** There is a single layer of defence. If anyone moves a route out of the group or reuses these requests, every store admin gains platform control.
- **Recommendation:** Make TenantPolicy and the FormRequests check only a central platform role, and call $this->authorize(...) in each action.

### 64. [MEDIUM] Super-admin tests never exercise tenant context, escalation or destructive routes
- **Location:** `backend/tests/Feature/Api/SuperAdminApiTest.php:41`
- **Effort:** M
- **Evidence:** setUp migrates the tenant migrations into the single sqlite DB and seeds PermissionsSeeder there, so central and tenant are conflated. The negative case is only a user with no role at all (line 97). There is no test with X-Tenant, a tenant user holding role super_admin or a hardcoded phone, quick-login, destroy, update-db-config (both would fail on parse errors), run-migrations, update-units or impersonation. SuperAdminSolidTest only unit-tests actions (index filters, toggle status, override feature, update plan).
- **Impact:** The critical escalations above and the broken endpoints ship undetected, even though the suite passes (13/13).
- **Recommendation:** qa-tester should add: a tenant admin creating a super_admin-role user gets 422; a tenant-context token on /super-admin/* gets 403; quick-login is rejected; storeTenant does not return the DB password; destroy/update-db-config smoke tests; impersonation token single-use and TTL.

### 65. [LOW] Impersonation flow is half-wired: action not routed, session flag set before token validation
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:33`
- **Effort:** M
- **Evidence:** ImpersonateTenantAction (app/Actions/SuperAdmin/ImpersonateTenantAction.php:38) calls tenancy()->impersonate(), but no route or controller references the action. Its URL uses config('tenancy.central_domains.0') = '127.0.0.1' (line 42). The /impersonate/{token} closure (tenant.php:33-36) writes is_impersonating/impersonated_by_super into the session before UserImpersonation::makeResponse validates the token (stancl checks TTL and tenant match).
- **Impact:** Support staff fall back to the unsafe central-token and phone fallbacks instead of the audited, TTL-bound stancl path. A failed impersonation attempt can still leave the session flagged as impersonating.
- **Recommendation:** Wire ImpersonateTenantAction to a central-only super-admin endpoint and set session flags only after makeResponse succeeds. Note that makeResponse logs in via a session guard while the SPA is token-based, so issue a short-lived tenant Sanctum token instead. Log the event in both central and tenant activity logs.

### 66. [LOW] Legacy App\Scopes\TenantScope is dead code with an unsafe session fallback
- **Location:** `D:\projects\sroor\backend\app\Scopes\TenantScope.php:37`
- **Effort:** S
- **Evidence:** A grep shows no model or provider references TenantScope (it is never added via addGlobalScope or ScopedBy), and nothing writes session('current_tenant_id'). The scope falls back to session('current_tenant_id'), then auth()->user()->tenant_id, which is a single-DB pattern that contradicts DB-per-tenant.
- **Impact:** No runtime impact today. A future developer could attach it and trust a client-influenced session value for isolation.
- **Recommendation:** Delete TenantScope (and any docs that recommend it). Isolation is provided by the per-tenant DB connection.

### 67. [LOW] Login throttle buckets and queue:work overlap locks are shared across tenants in the central cache
- **Location:** `backend/app/Http/Requests/Auth/ApiLoginRequest.php:76`
- **Effort:** S
- **Evidence:** throttleKey = lower(login)|ip, with no tenant id, stored in the central cache (finding 1). routes/console.php:12-14 uses withoutOverlapping() with the default 24h mutex in the same cache.
- **Impact:** Failed logins for a phone number in one tenant lock out the same phone and IP in other tenants, which matters for shops behind one NAT or a shared POS network. If the host kills the worker process (kill -9), the overlap mutex can block queue processing for up to 24 hours.
- **Recommendation:** Include tenant('id') in throttle keys, and use withoutOverlapping(5) or a supervisor-managed worker instead of schedule-driven queue:work.

### 68. [LOW] Tenant migration hygiene: duplicated framework tables, two unguarded alters, no hasTable guards on creates
- **Location:** `D:/projects/sroor/backend/database/migrations/tenant/2026_08_25_190100_add_pos_display_fields_to_items_table.php:11`
- **Effort:** S
- **Evidence:** Tables created in both central and tenant sets: users (plus the phone, theme_preference, show_print_subtitle and api_token alters), cache, cache_locks, jobs, job_batches, failed_jobs, sessions, password_reset_tokens, personal_access_tokens, the permission tables and activity_logs. 2026_08_25_190000_add_color_to_categories_table and 2026_08_25_190100_add_pos_display_fields_to_items_table call Schema::table add-column with no hasColumn guard, while the other alters are guarded. Every tenant migration has a down(). 2026_08_18_220000 down() does not revert the payments ->change(). Create migrations (items, invoices and others) have no hasTable guard, which matters when adopting pre-created or legacy DBs on Hostinger.
- **Impact:** Re-running against a partially migrated or adopted DB fails at these files, which stops tenants:migrate for that tenant. Tenant-local jobs and sessions tables mean a database queue or session driver used in tenant context writes to the tenant DB, where a central worker never reads.
- **Recommendation:** Add new guarded migrations (do not edit shipped ones) or wrap future alters in hasColumn. Decide explicitly which framework tables live where. Sessions, jobs and failed_jobs should normally be central, with queue and session connections pinned to the central connection. Document this in docs/03-architecture/database-schema.md.

### 69. [LOW] tenancy:sync-hosts edits the Windows hosts file and runs ipconfig, which makes it a dev-only command registered in all environments
- **Location:** `D:/projects/sroor/backend/app/Console/Commands/SyncTenantsToHostsCommand.php:17`
- **Effort:** S
- **Evidence:** It reads all central Domain rows and appends them to C:\Windows\System32\drivers\etc\hosts, then shell_exec('ipconfig /flushdns'). The default domains are makhzani.test. It has no environment guard.
- **Impact:** Harmless on Linux production, because the path does not exist and it returns FAILURE. It is still dev tooling shipped and auto-registered in production, and it relies on shell_exec.
- **Recommendation:** Register it only when app()->environment('local'), or move it to a dev script.

### 70. [LOW] Money columns for plans and subscriptions use DECIMAL(10,2) and are serialised as float
- **Location:** `backend/database/migrations/2019_09_15_000005_create_plans_and_features_tables.php:17`
- **Effort:** S
- **Evidence:** price_monthly and price_yearly are decimal(10,2), and subscriptions.amount is decimal(10,2) in 2019_09_15_000015. PlanResource:45-46 casts prices to (float), and SuperAdminAnalyticsService returns 'mrr' => (float)$mrr and price_monthly as (float).
- **Impact:** This breaks the project rule (DECIMAL(12,3), strings, bcmath). The rounding risk at these magnitudes is small, but it is inconsistent with the rest of the money model and with future invoicing.
- **Recommendation:** In a new central migration, alter the columns to decimal(12,3) and return string values from the resources.

### 71. [LOW] Tenancy switching in super-admin actions has no try/finally
- **Location:** `backend/app/Actions/Tenants/GetTenantDetailsAction.php:33`
- **Effort:** S
- **Evidence:** Tenancy::initialize($tenant) ... Tenancy::end() sits inside a try, and the catch only logs. The same pattern appears in SuperAdminApiController::updateTenantUnits (lines 205-212). On exception, tenancy stays initialized, so the following Setting::get('global_system_units') (line 57) and the rest of the request run against the tenant DB. The dashboard service (SuperAdminAnalyticsService) does NOT loop tenant DBs. It uses only central counts and sums, which is cheap, but MRR is cast to float (line 42) and tenant stats use number_format((float)...) (GetTenantDetailsAction.php:48).
- **Impact:** Wrong-DB reads/writes after a tenant DB error, and float rounding in revenue figures. Limited because it only affects the super-admin request.
- **Recommendation:** Use $tenant->run(fn () => ...), which restores context, or try/finally with tenancy()->end(). Keep money as strings via bcmath.

### 72. [INFO] TenantObserver is log-only, and its delete message is misleading
- **Location:** `D:/projects/sroor/backend/app/Observers/TenantObserver.php:35`
- **Effort:** S
- **Evidence:** created() logs 'Provisioned Successfully' when the row is inserted, which is before the DB is created, migrated and seeded. updated() checks isDirty('status') in the updated hook, where dirty state is already synced, so it should use wasChanged(); the log may never fire. deleted() logs 'Marked for Deletion' although the DB is hard-dropped.
- **Impact:** The audit trail of the tenant lifecycle is unreliable: it reports success before provisioning finishes and misses status changes. There is no activity_log row naming the super admin who acted.
- **Recommendation:** Use wasChanged(). Log through ActivityLogService to a central audit table with the actor id. Emit 'provisioned' only after seeding completes.

## Open questions
- Does the production central DB still contain the legacy single-tenant Sroor operational tables and data (invoices, customers, items)? If yes, the unauthenticated web.php export and print routes and the 'no tenant resolved' fallthrough expose real data, not just 500s.
- Has the production super-admin password been rotated away from the seeded default, and is quick-login reachable in production today? Check web-server logs for POST /api/v1/auth/quick-login from unknown IPs and for super-admin DELETE/update-db-config calls.
- Is DB_CACHE_CONNECTION or CACHE_STORE set differently in production .env (for example redis or file)? The cross-tenant cache bleed analysis assumes the database store resolved at boot on the central connection. A two-tenant test should confirm this empirically.
- Is the quick-login (passwordless) feature a deliberate product requirement for POS kiosks? If so, it needs a PIN-plus-device-bound redesign rather than removal.
- Which central users hold the 'admin' role in production? Each of them is currently a master key to every tenant through ApiTokenAuth.php:51-63 and ApiLoginAction.php:31-53.
- Should a tenant API request on the central host with no tenant ever be valid (for example a super-admin managing central users)? This decides whether the tenant-required guard returns 404 or the routes are physically split.
- Is the production central DB a clean SaaS central DB, or the old single-shop database with invoices and settings tables? That decides whether the scheduled notify:* commands fail every day or report the old shop's data to the owner's chat.
- What are CACHE_STORE, DB_CACHE_CONNECTION, PULSE_DB_CONNECTION, TELESCOPE_ENABLED and APP_ENV on production? The local backend/.env has APP_ENV=local and APP_DEBUG=true; if production matches, Telescope records every query and request for every tenant.
- Do any central users hold the 'admin' role? If so, they can open Telescope through the gate at TelescopeServiceProvider.php:81.
- Does the mobile or desktop client send X-Tenant, or use a tenant subdomain, when calling /api/v1/app/download-apk? That decides whether uploaded APKs are ever served.
- Is the public/storage symlink created on the Hostinger deployment, and does any tenant feature plan to upload files (logos, attachments, exports)?
- Has the Telegram chat that received earlier backup:telegram dumps been treated as holding live super-admin api_tokens and tenant DB passwords? If so, rotate them.
- DeleteTenantAction.php and UpdateTenantDatabaseConfigAction.php fail php -l (their variables appear stripped, probably by shell interpolation in commit 606da74b or earlier). The lead should confirm whether this corruption is also deployed.
- Does the production central DB actually have a 'settings' table, perhaps created out of band or left over from the legacy single-DB install? No central migration creates it, so POST /super-admin/settings should fail on a clean central DB.
- On Hostinger, does the central DB user see pre-created tenant DBs in INFORMATION_SCHEMA? If it does, stancl's ensureTenantCanBeCreated throws TenantDatabaseAlreadyExistsException and the pre-create-in-hPanel flow cannot work. If it does not, CREATE fails silently and migration relies entirely on the tenancy_db_username and password override. Which one happens in practice?
- How many tenants exist in production besides '2m'? Each one is likely behind on tenant migrations, because the deploy migrates only 2m.
- Does the SPA send X-Tenant or ?tenant on super-admin calls? If it does, Plan, Subscription and Setting (no CentralConnection) would hit the tenant DB during provisioning.
- Side note on verification: I ran `php artisan test --filter=SuperAdminApiTest` (9 passed, 44 assertions) against the phpunit sqlite :memory: config. The run created or updated the untracked file backend/database/tenant_wadi-elbon.sqlite, because provisioning's $tenant->run() opens a file-based SQLite tenant DB even in tests. backend/database/database.sqlite (the local dev DB) also shows a modification time from the same minute. I could not confirm whether the test or another local process wrote it. Please check: if the test did it, tests are leaking into the dev DB. git status remained clean.
- Should tenant-level user management ever be allowed to assign 'super_admin'? Would the business prefer the super-admin identity to live only in the central users table with its own guard?
- Which payment rails should be supported first (Paymob, Fawry, Vodafone Cash/InstaPay manual receipts)? The subscriptions.payment_method comment suggests manual cash or transfer collection.
- What grace period and data-retention policy apply after expiry: read-only mode, or a full block? Should a suspended tenant still be able to export its data?
- Should plan edits apply immediately to existing tenants, or should prices and limits be snapshotted per subscription (grandfathering)?
- Are invoices-per-month and storage quotas meant to be hard blocks or soft warnings for the by-weight retail use case?
- Is ResolveTenantWorkspaceAction (the central workspace lookup) the only entry point mobile/desktop clients use, or can they reach the tenant API host directly, which would bypass even the current suspension check?
- Does the production central DB (Hostinger) still contain a legacy 'settings' table and real ERP data from the single-tenant era? That decides whether platform settings 'work' today and how much the unauthenticated web.php print/report routes expose.
- Who is user id 1 and which users hold the 'admin' role in the production central DB? Both decide the blast radius of quick-login and the central-admin fallback login.
- Is quick-login meant as a kiosk/cashier feature? If so, what device-trust model (PIN, registered device) does the product owner want instead of no password?
- Should tenant deletion be a hard DB drop at all, or an archive state with a retention period that the subscription contract defines?
- Is the Android APK meant to be a single-tenant build per customer (then appId/server.url should be generated per tenant) or one generic store app with workspace selection?