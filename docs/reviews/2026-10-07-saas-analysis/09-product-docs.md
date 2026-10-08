# Product scope, docs & roadmap alignment — Score 3/10

> Branch `feature/multi-tenant` · 2026-10-07 · read-only multi-agent analysis · secrets redacted

## Executive summary

Yes, this analysis ran on feature/multi-tenant, the SaaS branch. It was read-only: nothing was edited, committed, deployed or run against any remote. The branch has a solid single-tenant ERP core: DECIMAL(12,3) everywhere, bcmath, locked per-store stock, about 306 PHPUnit tests and 39 Playwright specs. It also has a good SaaS data model and provisioning layer: DB-per-tenant via stancl, plans, plan_features, subscriptions, TenantProvisionerService and a working super-admin SPA. As a product you could sell, it is not ready, for two reasons.

First, the control plane can be taken over. Any tenant user can become platform super-admin in two ways. One is to give themselves the tenant-local `super_admin` role, which PermissionsSeeder seeds into every tenant DB and Store/UpdateUserRequest accept. The other is simpler: set their own phone, through PUT /api/v1/profile, to one of two personal numbers hardcoded as an authorization bypass in Gate::before and five other places. Either way they get the /api/v1/super-admin/* routes, which act on central tenant data with no tenant-context guard.

Second, the monetisation model exists only on paper. Suspension and subscription expiry are not enforced on the API or tenant web routes. Plan limits (max_users, max_stores, max_items...) and feature flags (hasFeature, FeatureGate.vue) have no callers. There is no signup or payment flow. The automated backups and Telegram reports cover only the central DB, so customer DBs are never backed up. The tenant delete action has a PHP parse error, and if repaired as written it would hard-drop the tenant DB with no retention period.

Other gaps: unauthenticated invoice print routes on tenant domains (IDOR); branch isolation not enforced (StoreAccess is never applied, and X-Store-Id is trusted); and an unpushed local WIP commit (76f32ce0) that contains production SQL dumps with customer PII and password hashes, which the next push or deploy_root_baraa.py run would publish. The docs (CLAUDE.md, .claude/rules, AI_START.md) are mostly accurate, but the SaaS plan doc has no implementation tracking, and the older AGENTS.md content still describes Livewire.

## Top risks
- CRITICAL: Any tenant admin can escalate to platform super-admin. PermissionsSeeder.php:70/77 seeds `super_admin` (with super_admin.access) into every tenant DB, and StoreUserRequest/UpdateUserRequest only validate `exists:roles,name`. Gate::before (AppServiceProvider.php:37-38) honours the role, and the super-admin group (routes/api.php:196) runs inside ResolveApiTenancy with no tenant-context rejection. Tenant uses CentralConnection, so toggle-status, override-feature, run-migrations, update-db-config and delete all act on every tenant.
- CRITICAL: Hardcoded personal phone numbers act as a super-admin backdoor, and any user can claim them. They are in AppServiceProvider.php:38,49, UserResource.php:19, ToggleTenantStatusRequest.php:13, OverrideTenantFeatureRequest.php:13 and TelescopeServiceProvider.php:82. Phone is only unique per tenant DB, and PUT /api/v1/profile (UpdateProfileRequest, UpdateProfileAction.php:26) lets even a cashier set their own phone to one of them. web.php:38-39 adds Telescope access for any email at the company domain. The numbers are personal data and also ship in the built JS, the Android assets, e2e fixtures and lang files.
- HIGH: Unpushed WIP commit 76f32ce0 contains production DB dumps under backups/: two 21,806-line .sql files, plus .sql.gz copies and prod snapshots, with users/customers rows and bcrypt hashes. .gitignore has no backups/ or *.sql rule, and deploy_root_baraa.py:30-32 runs `git add . && commit && push`.
- HIGH: Subscription status, trial and expiry are never enforced. ResolveApiTenancy.php:37/68 initializes tenancy with no status check, login checks only user->is_active, and tokens survive suspension. Only ResolveTenantWorkspaceAction.php:48, which clients can skip, checks status, and no scheduled expiry job exists.
- HIGH: Plan limits and feature flags are display-only. max_* columns, Tenant::checkLimit and Tenant::hasFeature, Plan::hasFeature and TenantFeatureManager::isFeatureEnabled have no callers. checkLimit is also wrong: it reads 'limits.users' and filters by tenant_id inside a per-tenant DB. FeatureGate.vue is unused and defaults to allow. UpdatePlanRequest min:1 cannot express 'unlimited'.
- HIGH: There are no automated backups of tenant DBs. In routes/console.php:25-38, backup:telegram and notify:* run in central context, and DatabaseBackupService dumps the default (central) connection.
- HIGH: DeleteTenantAction.php:12 does not parse (its $tenant variables were stripped in commit 606da74b), so DELETE /super-admin/tenants/{id} returns 500. Restored as written, it would synchronously drop the tenant DB (TenancyServiceProvider DeleteDatabase, shouldBeQueued(false)) with no soft delete, export or retention.
- HIGH: Unauthenticated invoice print routes on tenant domains (routes/tenant.php:60-74, before the auth group at :77) expose customer names, items, amounts and balances by sequential ID. The same pattern appears in web.php:56-66, the /reports/print route at :180 and the CSV exports at :278-282.
- HIGH: Branch isolation is not enforced. The store.access alias (bootstrap/app.php:28) is never applied. ApiTokenAuth.php:83-86 trusts X-Store-Id, StorePOSInvoiceRequest only checks exists:stores,id, and InvoiceController returns invoices from all stores by default.

## Quick wins
- Before any push, rewrite the local unpushed commit 76f32ce0 to drop backups/** (dumps, CSVs, PDFs, screenshots). Add backups/, *.sql, *.sql.gz, *.apk, test-results/ to the root .gitignore. Replace `git add .` in deploy_root_baraa.py with explicit paths.
- Remove every hardcoded phone and email-domain check: AppServiceProvider.php:38,49, UserResource.php:19, ToggleTenantStatusRequest.php:13, OverrideTenantFeatureRequest.php:13, TelescopeServiceProvider.php:82 and web.php:38-39. Base super-admin status only on a central-DB role or flag.
- Add a middleware on the super-admin route group that returns 403 when tenancy()->initialized. Stop seeding super_admin and super_admin.access in tenant DBs, and reject `super_admin` in Store/UpdateUserRequest. Add a feature test for each of the two escalation paths.
- Move the tenant.php print routes (60-74) inside the auth group, and remove or protect the unauthenticated web.php print, reports and export routes. Add a guest-gets-401/302 test.
- Fix the DeleteTenantAction parse error by restoring $tenant, but have it only set status=cancelled until a proper retention and purge lifecycle exists.
- Make FeatureGate.vue deny by default, and add an EnsureTenantActive middleware after ResolveApiTenancy that blocks suspended or cancelled tenants and revokes tokens on suspend. The Tenant::isSuspended/isActive helpers already exist.

## Strategic items
- Split authentication between control plane and tenant: super-admins authenticate only against central users on the central domain, and the super-admin API lives outside the tenancy-resolved group. Commission a separate security review of ApiTokenAuth step 3, which maps a central token to a tenant user by phone. Re-enable CacheTenancyBootstrapper (config/tenancy.php:36) so the spatie permission cache is not shared across tenants.
- Subscription lifecycle: a daily scheduled command that moves trial/active tenants to past_due, then read-only, then suspended. Add the reminders the SaaS plan doc §6 promises (7, 3 and 1 days before expiry) and a read-only grace mode.
- Plan enforcement: a central PlanLimitGuard service (null = unlimited) called from the Create User, Store and Item actions and from invoice creation (monthly count), plus a `feature:<key>` route middleware mapped to the 24 PlanFeature keys. Expose the resolved features in /system/context and use them to gate router meta and menus. Rewrite or remove checkLimit and getFeatureLimit.
- Self-serve onboarding and billing: signup, a 14-day trial, and Paymob/Stripe with webhooks, all reusing TenantProvisionerService. Decide which pricing structure is the real one (seeder 4 tiers, SaaS doc 3, brochure 6) and whether add-ons are billed in-system.
- Per-tenant data protection: run backups for each tenant (runForEach or tenants:run) and store them off-box with a retention period. Add 'export my data' for tenant admins, and replace hard delete with a cancel, retain, export, purge flow behind a typed confirmation.
- Branch isolation: one store resolver middleware that validates the requested store against user->stores() for non-managers, applied to POS, invoices, shifts, transfers, reports and blender, with index queries scoped to allowed stores by default.
- Docs and roadmap alignment: add a SaaS section to docs/05-planning/tasks-breakdown.md with done, partial and missing status taken from the code. Mark docs/01-overview, 02-requirements and 05-planning as historical or rewrite them for the generic SaaS. Fix the stale Livewire-era AGENTS.md that sessions are still loading. Decide whether backend/docs, backend/e2e and the tests_e2e Python suite stay or go. Turn coffee-blender into a generic recipes/BOM add-on gated by blender.access.

## Strengths
- The SaaS data model is ready for billing. It has plans (DECIMAL prices, five max_* limits, JSON features), a plan_features registry, subscriptions (cycle, status, amount, period, payment method) and tenants (status, trial_ends_at, subscription_ends_at, enabled_features overrides).
- Provisioning is cleanly abstracted (TenantProvisionerInterface, TenantProvisionerService, ProvisionTenantAction). It creates the DB, migrates, sets domains, records a subscription, seeds roles, creates the main store and admin user, and applies branding, so a signup flow can reuse it.
- Each tenant has its own database, and central models use CentralConnection, so storage-level separation is sound. The escalation findings are authorization flaws, not storage leaks.
- TenantFeatureManager already merges plan features with overrides, and the super-admin SPA (dashboard, tenants, plans, app versions/OTA, branding) works through composables, Form Requests and Actions. Only enforcement is missing.
- The money and stock core is solid. Amounts are DECIMAL(12,3) throughout (59 columns, no float), arithmetic uses bcmath, and StockService locks rows with lockForUpdate inside DB::transaction. Cancellation reverses stock instead of deleting, there is per-store numbering, and decimal weight sales are supported.
- Test and route coverage is broad. There are about 306 PHPUnit methods, and all 39 SPA routes have views and Playwright specs. All API routes sit under ApiTokenAuth, and there are 35 tenant migrations that match the planned schema.
- The governance docs are mostly accurate. The CLAUDE.md stack table matches the manifests, AI_START.md paths all exist, and the rules are candid about legacy debt and keep docs/history immutable.
- ResolveTenantWorkspaceAction already checks for suspended or expired tenants, which is a pattern to reuse in the API middleware.

## Findings

### 1. [CRITICAL] [MUST-HAVE / security] Tenant admin can make their own user a platform super-admin by giving it the tenant-local `super_admin` role, which opens the central super-admin API to that user
- **Location:** `backend/database/seeders/PermissionsSeeder.php:70`
- **Effort:** M
- **Evidence:** `TenantProvisionerService::provision()` (app/Services/TenantProvisionerService.php ~L88) runs `PermissionsSeeder` inside `$tenant->run()`. That seeder creates `Role::firstOrCreate(['name'=>'super_admin'])` and grants it every permission in the tenant DB. `StoreUserRequest` (and `UpdateUserRequest:26`) only check `'role' => ['required','string','exists:roles,name']`, and that check runs against the tenant DB, so `super_admin` passes. Any tenant `admin` is authorized (`hasRole('admin')`). `Gate::before` (app/Providers/AppServiceProvider.php:37-38) returns true for `hasRole('super_admin')`. The `/api/v1/super-admin/*` group (routes/api.php:196) sits inside the `ResolveApiTenancy` group, which skips tenancy only for `api/v1/central/*`. So a request carrying `X-Tenant: <own tenant>` plus a token from the tenant DB passes `can:super_admin.access`. `Tenant` uses stancl's `CentralConnection` (vendor/.../Database/Models/Tenant.php:24), so list, toggle-status, override-feature, run-migrations and delete then act on all tenants. I worked this out from the code only. I did not run it.
- **Impact:** Any paying customer's admin can list every tenant (names, emails, phones), suspend competitors, give themselves any feature and trigger migrations. Once destroyTenant compiles, they could also delete other tenants' databases. This is a full cross-tenant takeover of the SaaS control plane.
- **Recommendation:** Stop seeding `super_admin` and `super_admin.access` into tenant DBs. Reject `super_admin` in Store/UpdateUserRequest. Add middleware on the super-admin group that rejects the request when `tenancy()->initialized`, and authenticate super-admins only against central users. Add a feature test that tries this escalation.
- **Verification:** Confirmed from the code. I found nothing that blocks this path.

(1) TenantProvisionerService.php:85-87 runs PermissionsSeeder inside $tenant->run(). PermissionsSeeder.php:70/77 then creates a `super_admin` role in every tenant DB and gives it every permission, including super_admin.access.

(2) StoreUserRequest.php:13,23 and UpdateUserRequest.php:14,26 authorize any tenant `admin` (or a user with users.manage/roles.manage). Their only role rule is `exists:roles,name`, which runs against the tenant DB. CreateUserAction.php:29 and UpdateUserAction.php:35 then call syncRoles([$dto->role]) without filtering. The only place `super_admin` is filtered out is the display list (UserController.php:62 and GetRolesMatrixAction.php:21). That is cosmetic and does not stop the write.

(3) AppServiceProvider.php:37-39: Gate::before returns true for hasRole('super_admin'), checked against the user's tenant DB roles.

(4) routes/api.php:19/33/196: the super-admin group sits inside ResolveApiTenancy + ApiTokenAuth with only `can:super_admin.access`. ResolveApiTenancy skips tenancy only for api/v1/central/*, so X-Tenant initializes the attacker's own tenant. ApiTokenAuth then resolves the token from the tenant DB.

(5) SuperAdminApiController has no `tenant()` guard. toggleStatus, overrideFeature, updateTenantUnits, runTenantMigrations, destroyTenant and updateDatabaseConfig all call Tenant::findOrFail($id). App\Models\Tenant extends the stancl base Tenant, which uses CentralConnection (vendor Tenant.php:24), so these calls reach every tenant. Some FormRequests even allow plain hasRole('admin'), but the route middleware is the effective gate.

Minor overstatement: Plan has no getConnectionName(). The `tenants` list (Plan::select in tenants()) and the plans endpoints may therefore fail with "table not found" in tenant context, so "list every tenant" may break. showTenant, toggle-status, override-feature, run-migrations, update-db-config and delete still work against central data. Severity stays critical.

There is also a simpler bypass of the same gate: Gate::before (AppServiceProvider.php:38, :49), UserResource.php:19, OverrideTenantFeatureRequest.php:13 and ToggleTenantStatusRequest.php:13 contain a hardcoded allowlist of two phone numbers. phone is unique only per tenant DB, so a tenant admin can create a local user with one of those phones and get super-admin directly, without the role at all.

Separately, CacheTenancyBootstrapper is commented out in config/tenancy.php:36, so the spatie permission cache is shared across tenants.

### 2. [CRITICAL] [MUST-HAVE / security] Hardcoded personal phone numbers act as a super-admin backdoor, and any tenant can claim them
- **Location:** `backend/app/Providers/AppServiceProvider.php:38`
- **Effort:** S
- **Evidence:** `Gate::before` returns true when `in_array($user->phone, [<2 hardcoded phone numbers>])` (PII, redacted). The same list appears at L49 (viewPulse), app/Http/Resources/UserResource.php:19 (is_super_admin), app/Http/Requests/OverrideTenantFeatureRequest.php:13 and ToggleTenantStatusRequest.php:13. Phone uniqueness is only enforced inside each tenant DB (`unique:users,phone` in StoreUserRequest), so a tenant admin can create a user with one of these phones in their own tenant.
- **Impact:** This is a second, simpler route to the same cross-tenant super-admin access. Those phone numbers also ship in source and in the built assets/API.
- **Recommendation:** Remove every phone-based bypass. Decide super-admin status only from a central-DB role or flag, and check it on the central connection.
- **Verification:** I confirmed this in the code, and it is worse than reported. In backend/app/Providers/AppServiceProvider.php:37-45, Gate::before returns true when the user has one of 2 hardcoded phone numbers, and this check runs before the rule that denies every super_admin.* ability. The same list appears at AppServiceProvider.php:49 (viewPulse), UserResource.php:19 (is_super_admin), OverrideTenantFeatureRequest.php:13, ToggleTenantStatusRequest.php:13 and TelescopeServiceProvider.php:82.

The super-admin routes are not separated from tenant traffic. In routes/api.php:196, `Route::prefix('super-admin')->middleware('can:super_admin.access')` sits inside the same v1 group as everything else, behind ResolveApiTenancy and ApiTokenAuth only. So a user from a tenant database who holds one of those phones passes the gate. The Tenant model extends the stancl BaseTenant (central connection), so that user can list, delete and toggle every tenant, and change its DB config.

Phone uniqueness is per tenant only. StoreUserRequest has `unique:users,phone` against the tenant DB, so a tenant admin can create a user with one of these phones. There is an even simpler route: UpdateProfileRequest.authorize() only checks `$this->user() !== null`, and its phone rule is just `Rule::unique('users','phone')->ignore($userId)`. UpdateProfileAction.php:26 saves the phone with no further check. So any logged-in tenant user, even a cashier, can change their own phone to one of these numbers through PUT /api/v1/profile and get platform-wide super-admin.

I found no other guard that blocks this: no middleware that rejects tenant context, and no check inside SuperAdminApiController. The phone numbers are also exposed outside the source code. They appear in the built JS (public/build/assets and the Android assets), in the e2e fixtures, in the seeders and in lang/*/auth.php. Severity stays critical.

### 3. [CRITICAL] Personal phone numbers and an email-domain allowlist hardcoded as authorization
- **Location:** `backend/routes/web.php:38`
- **Effort:** S
- **Evidence:** web.php:38-39 grants Telescope access when the user's phone is in a hardcoded list of 2 personal mobile numbers (values redacted) or the email ends with a specific company domain. The same pattern appears at AppServiceProvider.php:38,49, TelescopeServiceProvider.php:82, ToggleTenantStatusRequest.php:13 and OverrideTenantFeatureRequest.php:13. This was already reported as C2 in docs/reviews/2026-09-21 and is still present.
- **Impact:** Personal data (PII) sits in the code. If a user can change their own phone to one of these numbers (the review says PUT /profile allows it; I did not verify this path), they get super-admin powers. That is a remote-compromise class issue.
- **Recommendation:** Remove all identity-literal checks. Rely only on the super_admin role in the central DB. Move Telescope behind a central-domain-only guard.
- **Verification:** The finding is real, and the impact is understated. I checked the code directly.

1. **Hardcoded phone and email checks confirmed in all the listed places.**
   - backend/routes/web.php:38-39 lets a user into Telescope (/telescope-access logs them in and redirects) if their phone is in a hardcoded list of 2 personal mobile numbers, or their email ends with the company domain (any address at that domain counts, not one fixed address).
   - The same phone list appears in AppServiceProvider.php:38 (inside Gate::before, which returns true for every ability, including super_admin.*) and AppServiceProvider.php:49 (viewPulse).
   - It also appears in TelescopeServiceProvider.php:82-85 (phone list plus a company-domain email list), ToggleTenantStatusRequest.php:13 and OverrideTenantFeatureRequest.php:13.
   - Not in the original list: UserResource.php:19 sets is_super_admin from the same phone list.

2. **The profile path the review mentioned works, and I verified it.** PUT /api/v1/profile (routes/api.php:183) only needs a logged-in user: UpdateProfileRequest::authorize() returns `user !== null`. Its rules accept any phone with `Rule::unique('users','phone')->ignore($userId)`, plus any unique email. UpdateProfileAction.php:26 then sets `$user->phone` with no password check and no other guard.

3. **The uniqueness check does not protect anything across tenants.** Each tenant has its own database (ResolveApiTenancy switches to the tenant DB from the X-Tenant header or the host). So the unique rule only blocks a number already used inside that one tenant. In nearly every tenant the owner's numbers are free, so any user in any tenant, even a cashier, can set their phone to one of them.

4. **No other guard stops the escalation.** The super-admin routes (api.php:196-220) sit in the same tenancy-resolved, ApiTokenAuth group, guarded only by `can:super_admin.access`. Gate::before returns true for that phone before the super_admin.* deny branch runs, and ResolveApiTenancy blocks none of these routes in tenant context. The Tenant model reads from the central database, so the super-admin actions reach real platform data: listing, deleting and suspending tenants, update-db-config, run-migrations, plans and platform settings.

5. **The email check is a second hole.** Changing your email to any address at the company domain gives Telescope access through web.php:39. Telescope records requests, which can include tokens and personal data.

The personal-data point also stands: the 2 personal mobile numbers sit in the app code (and in tests and lang/en/auth.php).

**Verdict:** any tenant user can become platform super-admin in one self-service request. That is cross-tenant compromise of the whole SaaS, so I rate it critical rather than high.

### 4. [HIGH] Production DB dumps committed locally in the unpushed WIP commit, and .gitignore does not exclude backups/
- **Location:** `.gitignore:1`
- **Effort:** S
- **Evidence:** `git show --stat 76f32ce0` ("WIP: epitaxy pre-switch") adds backups/sroor_prod_2026-09-29.sql.gz, backups/sroor_prod_copy_before_recost_2026-09-29.sql.gz, backups/sroor_backup_20260831_151852.sql(.gz), backups/sroor_backup_latest.sql (21,806 lines each), cost-audit CSVs and screenshots/PDF. The root .gitignore has no `backups/` or `*.sql` pattern. `git status -sb` shows the branch is `[ahead 1]`, so the commit has not been pushed yet. .claude/rules/security-and-operations.md says "Never commit: ... backups/, *.sql dumps", and the sanctioned deploy script runs `git add .`.
- **Impact:** The next `git push` or `deploy_root_baraa.py` run (which does commit + push) would publish full production tenant databases, including customer data and password hashes, to the git remote, where they stay in history permanently. The same commit holds the only copy of the new CLAUDE.md, .claude/rules and AGENTS.md (see the next finding), so pushing the governance rewrite pushes the dumps with it.
- **Recommendation:** Before any push, rewrite the local WIP commit so backups/** is not in it (it is unpushed, so this is safe). Add `backups/`, `*.sql`, `*.sql.gz`, `*.apk`, `*.png` and `test-results/` to the root .gitignore. Re-commit only the governance files by explicit path.
- **Verification:** I checked this with read-only git commands and it holds up.

- **Dumps are in the commit:** `git show --stat 76f32ce0` adds these files under backups/:
  - backups/sroor_backup_latest.sql and backups/sroor_backup_20260831_151852.sql, 21,806 lines each, plus a .sql.gz copy of the second one.
  - backups/sroor_prod_2026-09-29.sql.gz (about 2.9 MB).
  - backups/sroor_prod_copy_before_recost_2026-09-29.sql.gz.
  - About 10 cost-audit and recost CSVs, an HTML report, audit.py, PNG screenshots and a PDF.
- **They hold real data:** sroor_backup_latest.sql has `INSERT INTO` statements for `users` and `customers`, and two bcrypt password hashes (`$2y$`).
- **Nothing in .gitignore excludes them:** there is no `backups/`, `*.sql` or `*.sql.gz` pattern (only `*.tar.gz`, `*.sqlite` and `.env.backup`), and `git check-ignore` matches nothing for backups/sroor_backup_latest.sql.
- **Not pushed yet:** `git status -sb` shows `[ahead 1]` against origin/feature/multi-tenant, and `git ls-tree` of origin/feature/multi-tenant lists 0 files under backups/.
- **The project's own rules forbid this:** .claude/rules/security-and-operations.md:32-33 says "never `git add .`" and "Never commit: ... `backups/`, `*.sql` dumps".
- **The deploy script would push it:** deploy_root_baraa.py:30-32, which is tracked in git, runs `git add .`, then `git commit`, then `git push`. Running it, or any plain `git push`, would publish this commit with the dumps.

Severity stays high. Pushed history is close to permanent, and the dumps contain customer PII and password hashes. The only thing limiting it is that the commit is still local, so it can be fixed now without rewriting shared history.

### 5. [HIGH] مسارات طباعة الفواتير على دومين المستأجر خارج مجموعة auth، فأي شخص يقدر يقرأ الفواتير بمجرد تغيير الـ ID
- **Location:** `backend/routes/tenant.php:59`
- **Effort:** S
- **Evidence:** في tenant.php السطور 59-73 فيها `GET /invoices/{id}/print` و`/print/thermal` و`/print/a4` جوّه مجموعة `web + InitializeTenancyByDomain` بس، وتنفّذ `Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id)` من غير أي auth. مجموعة `Route::middleware('auth')` مبتبدأش غير في السطر 77. قالب `print-thermal.blade.php:95` بيعرض اسم العميل، والسطر 168 بيعرض رصيده لما الإعداد يسمح. نفس المسارات متكررة في web.php:56-66 من غير auth كمان، وكذلك `/reports/print` (web.php:180) ومسارات تصدير CSV لكشوف حساب العملاء والموردين (web.php:278-282).
- **Impact:** أي حد يعرف دومين مستأجر يقدر يمرّ على الـ IDs بالتسلسل ويسحب كل فواتير المحل: أسماء العملاء والأصناف والمبالغ والأرصدة. ده تسريب بيانات عملاء في منتج SaaS بيتباع. التسريب جوّه نفس المستأجر، لكنه مفتوح للإنترنت كله.
- **Recommendation:** انقل مسارات الطباعة جوّه مجموعة `auth` (أو `ApiTokenAuth` مع token قصير العمر خاص بالطباعة) وطبّق Policy على `Invoice`. راجع مسارات web.php المكررة غير المحمية (print/reports/export) واحذفها أو احميها. وأضف Feature test يتأكد إن الطلب من غير مصادقة بيرجع 401 أو 302.
- **Verification:** I checked this against the code and it holds. In backend/routes/tenant.php, lines 60-74 define GET /invoices/{id}/print, /print/thermal and /print/a4. They sit inside the group that only uses 'web', InitializeTenancyByDomain and PreventAccessFromCentralDomains. The Route::middleware('auth') group starts at line 77. Each handler is a closure that calls Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id) with no auth check, no can: middleware and no policy.

I looked for a guard elsewhere and found none:
- The Invoice model (app/Models/Invoice.php) uses only HasFactory and SoftDeletes. It has no global or store scope.
- The only middleware appended to the 'web' group in bootstrap/app.php is StoreScope. That middleware only sets current_store_id in the session when Auth::check() is true, and it never blocks a guest.
- The view does expose data: resources/views/layouts/print-thermal.blade.php:95 prints $invoice->customer->name, and lines 167-168 read the thermal_show_customer_balance setting, which defaults to true.

So on any tenant domain, an unauthenticated user can walk through invoice IDs and read customer names, line items and amounts. The data stays inside one tenant, but anyone on the internet can reach it.

In web.php I confirmed the same unauthenticated routes:
- Lines 56-66 (print routes), with no group and no auth.
- /reports/print at line 180.
- ExportController's CSV routes at lines 278-282. ExportController has no middleware and no authorize call; it just does Customer::findOrFail and Supplier::findOrFail.

Two limits on the web.php part:
- web.php routes have no tenancy middleware. On a tenant domain, the tenant.php routes with the same URI are registered later, in TenancyServiceProvider::mapRoutes inside booted, so they win.
- The web.php-only routes, such as the CSV exports and /reports/print, run against the central or default connection, not a tenant DB. Their exposure is real but depends on what data that database holds.

The core claim about tenant.php is confirmed in full. High severity is justified: it is unauthenticated, sequential-ID access to customer financial data in a SaaS product that is sold to customers.

### 6. [HIGH] خطة التحويل لـ SaaS متوثقة كاملة، لكن حدود الباقات والاشتراك والميزات مش متطبّقة على أي request
- **Location:** `backend/app/Models/Tenant.php:141`
- **Effort:** L
- **Evidence:** `max_users` و`max_stores` بيظهروا بس في Plan model وUpdatePlanRequest وPlanResource وTenant.php:141. لا `CreateUserAction` ولا إنشاء الفروع بيتحققوا منهم. `ResolveApiTenancy.php` مفيهوش أي فحص لـ status أو suspended أو expired أو `subscription_ends_at`. الإيقاف بيتفحص بس وقت ربط الـ workspace في `ResolveTenantWorkspaceAction.php:48`. مكوّن `FeatureGate.vue` موجود ومحدش بيستخدمه، و`Tenant::hasFeature` ملوش أي caller. مفيش route لـ register أو signup، ومفيش أي بوابة دفع. وثيقة `saas-transformation-architecture-and-plan.md:157-193` بتوصف Paymob/Stripe وtrial 14 يوم ووضع read-only وonboarding، ومفيش أي مستند تخطيط بيتابع حالتهم.
- **Impact:** المستأجر الموقوف أو اللي اشتراكه خلص يفضل شغال طول ما معاه token. أي باقة تقدر تفتح مستخدمين وفروع بلا حد. الباقات عمليًا مجرد عرض، وده بيكسر نموذج الربح لـ SaaS بيتباع.
- **Recommendation:** أضف middleware بعد `ResolveApiTenancy` يمنع (أو يحوّل لوضع قراءة فقط) أي مستأجر status بتاعه مش active/trial أو اشتراكه منتهي. طبّق `max_users` و`max_stores` جوّه Actions الإنشاء، ووصّل `hasFeature` بـ middleware أو Policy. وافتح قسم SaaS حقيقي في `tasks-breakdown.md` بحالة done/partial/missing.
- **Verification:** I checked this against the code and it holds. 1) backend/app/Http/Middleware/ResolveApiTenancy.php (lines 17-73) starts the tenant with no check of status, suspension, expiry or subscription_ends_at. 2) The only place that checks suspension is ResolveTenantWorkspaceAction.php:48, which only CentralTenantResolverController calls. ApiLoginAction.php checks only $user->is_active (line 77), so a suspended or expired tenant can still log in by sending X-Tenant directly. ApiTokenAuth.php also checks only user is_active, so existing tokens keep working. ToggleTenantStatusAction.php only updates status and does not revoke tokens. 3) Tenant::isSuspended(), isActive() and isOnTrial() (Tenant.php:156-180) are not called from anywhere. The same goes for Tenant::hasFeature, checkLimit and getFeatureLimit, and for TenantFeatureManager::isFeatureEnabled. The only caller of TenantFeatureManager is OverrideTenantFeatureAction, which toggles overrides. The only references to max_users and max_stores are in Plan, UpdatePlanRequest, PlanResource, Tenant::getAllLimits:141, the super-admin composables and tests. 4) Nothing enforces limits on users or stores. checkLimit has no callers, and it reads 'limits.users' from features, not max_users. The POST /stores route only uses can:stores.manage. 5) FeatureGate.vue exists, but no .vue or .js file imports it (grep found only docs). 6) No register, signup, Paymob, Stripe or webhook routes exist in backend/routes. The plan doc is at docs/03-architecture/saas-transformation-architecture-and-plan.md. This is a missing feature against the roadmap, not a security bug in tenant isolation. Still, for the stated goal of a sellable SaaS, high fits: suspended or expired tenants keep full access, and plan limits are only shown, never enforced.

### 7. [HIGH] [MUST-HAVE] destroyTenant is broken: DeleteTenantAction has a PHP parse error. If fixed as written, it would hard-drop the tenant DB with no backup or grace period
- **Location:** `backend/app/Actions/Tenants/DeleteTenantAction.php:12`
- **Effort:** M
- **Evidence:** The file reads `public function execute(Tenant ): void { DB::transaction(function () use () { ->domains()->delete(); ->delete(); }); }`. The `$tenant` variables were stripped. `php -l` reports: `Parse error: syntax error, unexpected token ")", expecting variable ... on line 12`. The last commit to touch it was 606da74b ('ensure ... clean UTF-8 without BOM'). The SPA calls `DELETE /super-admin/tenants/{id}` from useSuperAdminTenants.js:186 and useSuperAdminTenantShow.js:198. TenancyServiceProvider.php:39-43 binds `TenantDeleted` to `Jobs\DeleteDatabase` synchronously. `subscriptions` and `domains` have cascadeOnDelete.
- **Impact:** Today the delete button returns a 500 (a fatal error when the container resolves the class, which happens outside the try/catch). If someone restores the variable, one click permanently drops the customer's whole DB along with subscription history. There is no soft delete, export, or retention window.
- **Recommendation:** Fix the syntax. Replace the hard delete with a lifecycle: status=cancelled, then read-only, then N-day retention with an automatic export/backup, then a scheduled purge behind a typed confirmation. Add a feature test for the endpoint.
- **Verification:** I confirmed this from the code. backend/app/Actions/Tenants/DeleteTenantAction.php:12-16 reads `execute(Tenant ): void` and `use ()`, with `->domains()->delete(); ->delete();`. `php -l` reports a parse error on line 12, "unexpected token ")", expecting variable". `git log` shows the original commit 2340148f had `Tenant $tenant` and `use ($tenant)`. The later commit 606da74b, a fix for a UTF-8 BOM, removed the `$tenant` variables. The route DELETE /api/v1/super-admin/tenants/{id} (routes/api.php:201, behind `can:super_admin.access`) calls SuperAdminApiController::destroyTenant (line 247), which takes DeleteTenantAction as a method parameter. When the container autoloads that class, the ParseError is thrown during dependency resolution, before the method's try/catch runs, so the request returns a 500. TenancyServiceProvider binds `TenantDeleted` to `JobPipeline[Jobs\DeleteDatabase]` with `shouldBeQueued(false)`, so the database is deleted synchronously. The Tenant model has no SoftDeletes. Restoring the original code would therefore drop the tenant database immediately, with no backup, soft delete or grace period. The original version also ran inside a central DB::transaction, which cannot roll back a dropped database. The only guard is the super-admin permission gate, and there is no confirmation token, archive step or retention window. The broken feature plus a latent irreversible data-loss path justifies high severity.

### 8. [HIGH] [MUST-HAVE] Subscription expiry and suspension are not enforced on the API or the tenant web routes
- **Location:** `backend/app/Http/Middleware/ResolveApiTenancy.php:34`
- **Effort:** M
- **Evidence:** `ResolveApiTenancy` calls `tenancy()->initialize($tenant)` (L34, L68) without checking `status`, `subscription_ends_at` or `trial_ends_at`. A grep for `isSuspended`, `isOnTrial` and `subscription_ends_at` outside the models finds only `ResolveTenantWorkspaceAction.php:48`, which is the workspace-code lookup used by the mobile login, plus the provisioner and the toggle action. routes/tenant.php uses only `InitializeTenancyByDomain` and `PreventAccessFromCentralDomains`. routes/console.php has no expiry or renewal command, and app/Console/Commands has none either.
- **Impact:** A suspended or expired tenant keeps full API and SPA access through existing tokens, the subdomain URL or the `X-Tenant` header. The 'suspend' action and trial end dates exist only on paper, so there is no lever to collect payment.
- **Recommendation:** Add an `EnsureTenantSubscriptionActive` middleware to the tenant API and tenant web routes. Block login and writes when suspended or cancelled, and allow read-only access during a grace window. Add a daily scheduled command that moves trial/active tenants to past_due and then suspended, and sends reminders (the SaaS plan doc §6 promises reminders at 7, 3 and 1 days plus read-only mode).
- **Verification:** I checked the code and the finding holds. In backend/app/Http/Middleware/ResolveApiTenancy.php, `tenancy()->initialize($tenant)` runs at L37 and L68 without any check on `status`, `subscription_ends_at` or `trial_ends_at`. The claimed line numbers are slightly off (L34 is the lookup), but that does not change the finding.

I looked for a guard anywhere else and found none:
- **Middleware:** app/Http/Middleware has only ApiTokenAuth, ResolveApiTenancy, StoreAccess and StoreScope. ApiTokenAuth checks only `user->is_active`.
- **Middleware registration:** bootstrap/app.php registers no tenant-status middleware or alias.
- **Tenancy events:** in TenancyServiceProvider, `TenancyInitialized` runs only `BootstrapTenancy`, and `InitializingTenancy` has no listeners.
- **Login:** ApiLoginAction, ApiQuickLoginAction and LoginAction check only the user's `is_active` flag, not the tenant's. Even new logins on a suspended or expired tenant succeed through the X-Tenant header or the subdomain.
- **Web routes:** routes/tenant.php uses only `web`, `InitializeTenancyByDomain` and `PreventAccessFromCentralDomains`.
- **Callers of the model helpers:** a grep shows `Tenant::isSuspended()`, `isActive()` and `isOnTrial()` are called only in ResolveTenantWorkspaceAction.php:48, the `/central/tenants/resolve` workspace-code lookup, which the client can skip.
- **Scheduled jobs:** routes/console.php schedules only queue, pulse, Telegram and backup commands. app/Console/Commands has no expiry or renewal command.

The impact is real for a SaaS whose business goal is selling subscriptions: suspend and expiry have no effect beyond the workspace lookup. I am keeping the severity at high.

### 9. [HIGH] [MUST-HAVE] Plan limits (max_users / max_stores / max_items / max_invoices_per_month / max_storage_mb) are never enforced
- **Location:** `backend/app/Actions/Users/CreateUserAction.php:19`
- **Effort:** M
- **Evidence:** `CreateUserAction::execute` creates the user and syncs the role with no limit check. A grep for `max_users|max_stores|max_items|max_invoices_per_month|max_storage_mb` across app/ and resources/js finds only Plan.php, UpdatePlanRequest.php:20-23, PlanResource.php:19-22 and Tenant.php:141-142 (`getAllLimits`, display only). `Tenant::checkLimit()` (Tenant.php ~L100) has no callers. It is also wrong: it filters `User::where('tenant_id', ...)` inside a per-tenant DB, and it reads `getFeatureLimit('limits.users')`, a key the PlansAndFeaturesSeeder never sets, so it would return 0 and block everything. UpdatePlanRequest uses `min:1`, so the 'unlimited' tier from the docs cannot be expressed.
- **Impact:** The free plan (seeded max_users=1, max_stores=1, max_items=50) is functionally the same as Enterprise. Every tier difference in the brochure and pricing study is unenforceable, and the add-ons sold in the study (extra branch or user) have nothing to meter.
- **Recommendation:** Add a central `PlanLimitGuard` service that reads `tenant()->plan->max_*` (null = unlimited) and counts rows in the tenant DB. Call it from the Create User, Store and Item actions and from invoice creation (monthly count), and return a translated 422 or 402 error. Remove or rewrite `checkLimit`/`getFeatureLimit`. Add tests.
- **Verification:** Confirmed in the code. backend/app/Actions/Users/CreateUserAction.php:17-33 runs User::create plus syncRoles inside a transaction and does no plan or limit check. I grepped backend/ (vendor, public and storage excluded) for max_users, max_stores, max_items, max_invoices_per_month, max_storage_mb, checkLimit, getFeatureLimit and getAllLimits. The only hits are the Plan model, the migration, the seeder, UpdatePlanRequest, PlanResource, Tenant.php, the super-admin Vue plan screens (display and edit), lang files and tests. No middleware, Observer, Action, FormRequest or Policy reads any of them, and app/Http/Middleware and app/Observers have no limit references at all. Tenant::checkLimit (Tenant.php:103-111) and getAllLimits (Tenant.php:133-147) have no callers anywhere. checkLimit is also broken in two ways. It reads getFeatureLimit('limits.users'/'limits.stores'/'limits.items'), which looks in $plan->features, but PlansAndFeaturesSeeder only puts boolean keys there (pos.access, invoices.create, ...) and stores the limits in the max_* columns, so the lookup returns 0 and `count < 0` blocks everything. It also filters `User::where('tenant_id', ...)` while running on a per-tenant DB. No invoice-per-month or storage metering exists either. UpdatePlanRequest.php:20-23 uses `min:1`, so 0 or null cannot be used to mean unlimited (the seeder fakes it with 999/99999). No frontend gate reads limits. The feature flags (hasFeature) are a separate mechanism and do not cover counts. This is a missing core SaaS capability rather than a data-integrity or security bug, and plan tiers cannot be told apart by quotas, so high fits the product-scope goal of a sellable multi-tenant SaaS.

### 10. [HIGH] [MUST-HAVE] Plan feature flags are never checked on the backend, and the only frontend gate is unused and lets everything through
- **Location:** `backend/app/Models/Tenant.php:67`
- **Effort:** L
- **Evidence:** `Tenant::hasFeature`, `Plan::hasFeature` and `TenantFeatureManager::isFeatureEnabled` have no callers in app/ or routes/. `TenantFeatureManager` is used only for `toggleFeatureOverride`. No middleware alias for features exists (bootstrap/app.php:23-29). resources/js/Components/FeatureGate.vue is not imported anywhere (grep for `FeatureGate` finds only the file itself), it returns `true` when the tenant or key is missing, and the router has no `meta.feature`. PlansAndFeaturesSeeder defines 24 keys (pos.access, transfers.manage, blender.access, purchases.manage, reports.advanced, reports.export, audit.logs, api.access, custom.domain, ...).
- **Impact:** Every route listed in routes/api.php is available to every plan, so the super-admin 'override feature' screen has no effect. Brochure tiers built on these features cannot be sold honestly.
- **Recommendation:** Add a `feature:<key>` route middleware backed by `TenantFeatureManager`, and apply it to the route groups that map to each PlanFeature key (transfers, purchases and reorder, expenses, treasury, returns, advanced reports, exports, activity logs, coffee-blender, telegram). Expose resolved features in `/system/context`. Gate router `meta` and menus, and make FeatureGate deny when the flag is missing.
- **Verification:** The finding holds up against the code. In backend/app and backend/routes, nothing outside the defining files calls Tenant::hasFeature (Tenant.php:67), Plan::hasFeature (Plan.php:56) or TenantFeatureManager::isFeatureEnabled (TenantFeatureManager.php:13). TenantFeatureManager is wired in only through AppServiceProvider and OverrideTenantFeatureAction, which toggles overrides.

There is no guard anywhere else that could make up for this:
- The middleware aliases in bootstrap/app.php:23-29 are only role, permission, role_or_permission, store.scope and store.access.
- None of the four middleware files (ApiTokenAuth, ResolveApiTenancy, StoreAccess, StoreScope) mention feature or plan.
- In routes/*.php, "feature" appears only in the super-admin route `override-feature` (api.php:204), which saves the override.

On the frontend, resources/js/Components/FeatureGate.vue is not imported anywhere in resources/js, and the router has no `meta.feature`. The component also lets everything through by default: it returns true when there is no feature prop (line 21), when there is no tenant (line 27), and when the key is in neither map (final `return true`).

The gap is wider than the finding says: plan limits are not enforced either. Tenant::checkLimit and getFeatureLimit (Tenant.php:93-110) have no callers, so limits.users, limits.stores and limits.items do nothing.

The impact is a broken monetization model rather than a security hole, since tenant isolation is separate. For a SaaS whose stated goal is selling plan tiers, high is still the right severity.

### 11. [HIGH] [SHOULD-HAVE] Scheduled backups and Telegram reports ignore tenant DBs
- **Location:** `backend/routes/console.php:36`
- **Effort:** M
- **Evidence:** `backup:telegram` (daily 00:05), `notify:daily-summary`, `notify:low-stock` and `notify:overdue-shifts` run from the central scheduler. A grep for `tenant|Tenant|runForMultiple` in SendTelegramDatabaseBackupCommand.php, DatabaseBackupService.php and SendDailyTelegramSummaryCommand.php finds nothing. DatabaseBackupService dumps `DB::connection()` (L39-57), which is the central DB when tenancy is not initialized.
- **Impact:** Customer DBs (all invoices and stock) have no automated backup, which is a data-loss risk for a paid SaaS. Per-tenant Telegram alerts do not fire per tenant. The brochure and SaaS doc §4.1 sell daily or real-time backups.
- **Recommendation:** Iterate `Tenant::all()->runForEach(...)` (or `tenants:run`) for the backup and notification commands. Store backups off-box per tenant with retention, and expose 'export my data' to tenant admins and super-admins.
- **Verification:** I confirmed this in the code. backend/routes/console.php lines 25-38 schedules notify:daily-summary, notify:low-stock, notify:overdue-shifts and backup:telegram from the central scheduler, which cron_schedule.sh runs as plain `artisan schedule:run`. Nothing wraps these commands in `tenants:run` or `runForMultiple`, and no tenant loop exists anywhere in app/routes/config.

1. SendTelegramDatabaseBackupCommand calls TelegramService::sendDatabaseBackupNotification, which calls DatabaseBackupService::createSqlGzBackup. That method uses the default connection: `DB::connection()->getDriverName()`, `SHOW TABLES` and `DB::table($table)` (L39-66). With no tenancy initialized, this is the central DB (tenants, domains, plans, users, telescope, pulse). That dump contains no customer invoices or stock, so no tenant DB is ever backed up automatically.
2. The central migrations have no invoices or settings tables; those live only in database/migrations/tenant. So sendDailySummaryNotification's `Invoice::where(...)` fails in central context. Setting::get swallows its exceptions and returns defaults, so the bot token and chat ID fall back to config('services.telegram.*'). The per-tenant daily summary, low-stock and overdue-shift alerts therefore never fire for any tenant.

Mitigations I found, which do not refute the finding:
- routes/tenant.php:243-244 has manual per-tenant endpoints, settings.telegram.backup and settings.backup.download (behind can:roles.manage), plus SendTelegramDatabaseBackupJob. When called inside tenant context, these would dump that tenant's DB.
- Hosting-level (Hostinger) backups may exist, but the code does not show them.

Automated backup of customer data is still absent, so high severity fits a paid DB-per-tenant SaaS.

### 12. [HIGH] Branch isolation is not enforced: the StoreAccess middleware is never applied, and the X-Store-Id header or store_id input is trusted
- **Location:** `backend/bootstrap/app.php:28`
- **Effort:** M
- **Evidence:** 'store.access' is registered as an alias at bootstrap/app.php:28, but a grep over routes/ and app/ finds no route that uses it. StoreScope is only appended to the web group (line 20). ApiTokenAuth.php:83-86 writes any numeric X-Store-Id header into session('current_store_id') with no assignment check. StorePOSInvoiceRequest.php:22-27 takes store_id from input or header and validates only 'exists:stores,id'. InvoiceController.php:40-58 returns invoices from every store when no store_id is sent. CoffeeBlenderController.php:41 trusts the header. The only place that checks assignment is StoreController switch (lines 223-237).
- **Impact:** A cashier assigned to branch A can send X-Store-Id for branch B. They can then sell from B's stock, open B's shift context, and list all branches' invoices. Per-branch permissions exist in the data model (store_user pivot) but do nothing at the API.
- **Recommendation:** Add one resolver (middleware on the tenant API group) that checks the requested store against user->stores()/default_store_id for anyone who is not an admin or stores.manage holder. Apply it to POS, invoices, shifts, transfers, reports and the blender. Scope index queries to the allowed stores by default.
- **Verification:** I confirmed this against the code. Each claimed piece holds, and I found no guard anywhere else that blocks it.

1. **The alias is never used.** backend/bootstrap/app.php:28 registers 'store.access' as an alias for StoreAccess. A grep for "store.access" and "StoreAccess" across routes/, app/ and bootstrap/ finds only that alias, the class itself, and a translation key in StoreController:236. No route applies it. Line 20 appends only StoreScope to the web group, and StoreScope (app/Http/Middleware/StoreScope.php) only checks that the session store exists and is active. It never checks whether the user is assigned to that store.

2. **The header is trusted.** ApiTokenAuth.php:82-86 writes any numeric X-Store-Id header into session('current_store_id') without checking assignment.

3. **Invoice creation trusts the client.** StorePOSInvoiceRequest.php:22-27 takes store_id from the input, then the header, then the session. The rules only require 'exists:stores,id', and authorize() checks only the role or permission. InvoiceService.php:34 then uses $data['store_id'] directly.

4. **The invoice list is not filtered by branch.** InvoiceController::index (lines 40-58) filters by store only when a numeric store_id is supplied, and the Invoice model has no global scope. Sending no store_id, or 'all', returns invoices from every store.

5. **The same pattern repeats elsewhere.** About 25 controller call sites take the store from the header or input, including CoffeeBlenderController:42 and the Shift, Purchase, Return, Expense, StockTransfer, Treasury, Report and Item controllers.

6. **The only check is the switch endpoint.** Assignment is checked only in StoreController::switchStore (lines 223-237). StorePolicy also checks it, but nothing calls that policy. The Gate::before hook in AppServiceProvider:37 only widens access (admin, super_admin and two hard-coded phone numbers). It never restricts by store.

Severity stays high rather than critical. The exposure stays inside one tenant, since the tenancy layer keeps a separate database per tenant. The attacker must already be a logged-in user with pos.access or invoices.* permission. Within those limits, the impact as described (selling from another branch's stock, using its shift context, and listing every branch's invoices) is real.

### 13. [HIGH] No tax/VAT or e-invoice support on sales documents
- **Location:** `backend/database/migrations/tenant/2026_08_08_160005_create_invoices_and_items_tables.php:16`
- **Effort:** XL
- **Evidence:** invoices only has subtotal, discount_*, net_total, paid/remaining and total_cost. invoice_items has no tax fields. A grep for vat, tax_rate, tax_amount or ضريبة in app/, the migrations and the views returns nothing. The only tax field is customers.tax_number. Hits for e-invoice/ETA/ZATCA were false positives (substring 'eta').
- **Impact:** In Egypt (ETA e-invoice/e-receipt) or KSA (ZATCA), a shop that must show VAT cannot use the system for legal invoicing. This blocks selling it as a generic SaaS in those markets.
- **Recommendation:** Add tax_rate and tax_amount per line (DECIMAL), plus tax_total and inclusive/exclusive mode on invoices. Add tenant tax settings and VAT summary reports. Plan the e-invoice integration as a feature-flagged module.
- **Verification:** The finding holds. In backend/database/migrations/tenant/2026_08_08_160005_create_invoices_and_items_tables.php, `invoices` (lines 11-30) has only subtotal, discount_type/value/amount, net_total, paid_amount, remaining_amount and total_cost. `invoice_items` (lines 32-42) has only quantity, cost_price, unit_price, discount_amount and total_price. No column in either table holds a tax rate or tax amount. This is the only tenant migration for invoices, and no later migration adds tax columns.

I grepped app/, database/, routes/, lang/, config/ and the Vue views, components and composables for vat, tax_rate, tax_amount, tax_total, taxable, the Arabic word for tax, zatca and einvoice. The only real hits are:
- customers.tax_number (tenant/2026_08_08_160002 line 16)
- tenants.tax_number (central 2019_09_15_000010 line 24)
- the DTOs, requests and resources that carry those two fields

Nothing calculates, stores or prints tax, and I found no guard or service elsewhere that does.

The gap is wider than the finding states:
- docs/02-requirements/functional-requirements.md:71 (REQ-023, "Must Have") requires the sales invoice to compute tax.
- docs/03-architecture/saas-transformation-architecture-and-plan.md:153 plans tenant-configurable VAT rates.
- database/seeders/PlansAndFeaturesSeeder.php:46 sells a plan feature `printing.a4` described as "tax invoices A4".

So the SaaS sells a "tax invoice" feature that the data model cannot produce. That supports high severity for a generic SaaS meant for Egypt and KSA. Two caveats:
- This is a missing feature, not a data-corruption bug.
- The claimed line 16 points at invoice_date; the relevant block is lines 11-42.

### 14. [HIGH] In-product backup is broken: 6 settings routes point to controller methods that don't exist, and the backup UI is orphaned
- **Location:** `backend/routes/tenant.php:240`
- **Effort:** L
- **Evidence:** tenant.php:240-245 route to SettingController::sendDailySummaryTelegram, sendLowStockTelegram, sendOverdueShiftTelegram, sendBackupTelegram, downloadBackup and clearCache. SettingController only defines index, update and sendTestTelegram (grep 'public function'). Components/Settings/BackupTab.vue links to /settings/backup/download and emits send-backup-telegram, but no file imports BackupTab or SystemTab. The only backup that actually runs is `Schedule::command('backup:telegram')->dailyAt('00:05')` (routes/console.php:37). It runs DatabaseBackupService::createSqlGzBackup() on DB::connection() with no tenancy initialized, so it dumps the central DB only. No restore or import route exists for tenant data.
- **Impact:** For a sellable DB-per-tenant SaaS, the product backs up no tenant databases. A tenant who clicks 'download backup' (if the tab is ever wired) gets a 500. Tenant data protection depends entirely on the hosting provider or the root ad-hoc scripts. The central dump also includes the tenants table (which can hold DB connection data) and goes to a Telegram chat.
- **Recommendation:** Remove the dead routes or implement them through Actions. Add a per-tenant backup job that iterates Tenant::all()->each(fn($t) => $t->run(...)), writes to tenant-scoped storage, and records success or failure in a central table. Document restore. Never send central dumps to Telegram.
- **Verification:** The finding holds up against the code.

1. **Six routes point to methods that do not exist.** backend/routes/tenant.php:240-245 send six routes to Api\SettingController: sendDailySummaryTelegram, sendLowStockTelegram, sendOverdueShiftTelegram, sendBackupTelegram, downloadBackup and clearCache. backend/app/Http/Controllers/Api/SettingController.php is a final class that only defines __construct, index, update and sendTestTelegram. It has no __call and no trait that could supply the missing methods. Calling any of the six routes throws BadMethodCallException, which returns a 500.

2. **The backup UI is not reachable.** BackupTab.vue and SystemTab.vue exist in resources/js/Components/Settings. A search for "BackupTab|SystemTab" across resources/js finds nothing outside those two files. TelegramTab.vue, which also emits send-backup-telegram, is not imported anywhere either. The live page, views/Settings/SettingsView.vue, imports only the Settings*Section components. Neither those components, SettingsView nor the stores call the backup, daily-summary, low-stock, overdue-shift or clear-cache endpoints.

3. **The only real backup runs on the central database.** routes/console.php:37 schedules backup:telegram daily at 00:05. SendTelegramDatabaseBackupCommand calls TelegramService::sendDatabaseBackupNotification (line 368), which calls DatabaseBackupService::createSqlGzBackup(). That method uses DB::connection(), SHOW TABLES and DB::table() (lines 39-67) and never initializes tenancy. None of these files loops over tenants; tenancy()->initialize appears only in an unrelated populate command. The dump therefore covers only the central DB.
   - There is a side bug in the same path: Setting::get('company_name') runs in central context, but the settings table exists only in the tenant migrations (database/migrations/tenant/..._create_settings_table.php). That lookup can fail or fall back to its default.

4. **No restore or import route.** Grepping the routes for backup turns up only the tenant.php entries and the console schedule.

**Severity:** the 500 can only be triggered by a direct API call, because no UI reaches these routes, so the user-facing impact is smaller than claimed. The core claim stands: a DB-per-tenant SaaS has no in-product backup or restore for tenant data, and it ships the central DB dump to Telegram. High remains justified.

### 15. [MEDIUM] [MUST-HAVE] There is no self-signup or onboarding flow. Tenants can only be created by a super-admin
- **Location:** `backend/routes/api.php:199`
- **Effort:** L
- **Evidence:** A grep for `register|signup` in routes/ returns nothing. Tenant creation exists only at `POST /api/v1/super-admin/tenants` → `TenantProvisionerService::provision`. That method creates the tenant (which synchronously triggers CreateDatabase and MigrateDatabase, see TenancyServiceProvider.php:26-31, with `shouldBeQueued(false)`), adds the `<slug>.<CENTRAL_DOMAIN>` domain and an optional custom domain, writes a Subscription row with payment_method 'manual', and seeds permissions, the main store, the admin user and company settings. routes/tenant.php only has guest login. docs/03-architecture/saas-transformation-architecture-and-plan.md §6 and the Gantt row 'شاشة Onboarding والتسجيل الذاتي' (§8) describe self-signup with a 14-day trial, but it is not built.
- **Impact:** Every sale needs manual work by the operator, so trials cannot convert themselves. The good news is that the provisioning logic already exists and can be reused.
- **Recommendation:** Add a rate-limited public `POST /api/v1/central/signup` (with captcha and email/phone verification) that calls `ProvisionTenantAction` with the default trial plan. Queue provisioning and add a status endpoint. Build a Vue onboarding wizard (business type preset, units, first store).
- **Verification:** I checked this against the code and the finding is accurate. Searching backend/routes for register, signup, onboard and trial finds nothing. The only route that creates a tenant is POST /api/v1/super-admin/tenants (backend/routes/api.php:199), which sits inside the `can:super_admin.access` middleware group. That route calls SuperAdminApiController, then ProvisionTenantAction, then TenantProvisionerService::provision; ProvisionTenantAction is used only by SuperAdminApiController. provision() does what the finding says: it creates the Tenant, which synchronously runs CreateDatabase and MigrateDatabase because TenancyServiceProvider has `shouldBeQueued(false)`. It then adds the `<slug>.<CENTRAL_DOMAIN>` domain and an optional custom domain, writes a Subscription with payment_method 'manual', and seeds permissions, the main store and the admin user. backend/routes/tenant.php only has guest login, impersonation and logout. In the frontend, the only 'register' matches are in LoginView.vue and they refer to registering biometrics, not accounts. docs/03-architecture/saas-transformation-architecture-and-plan.md plans self-signup with a 14-day trial (lines 18 and 162) and lists 'شاشة Onboarding والتسجيل الذاتي' in the Gantt chart (line 195). None of this is built.

I lowered the severity from high to medium for three reasons. First, this is a missing roadmap feature, not a defect or a security or data-integrity problem; the docs place it in a later phase (p5_1). Second, the super-admin can already give a trial, because provision() accepts trialDays and sets status 'trial', trial_ends_at and a 'trialing' subscription, so the manual sales process works end to end. Third, simply exposing provision() publicly would not be enough: it creates the database synchronously and has no rate limiting, CAPTCHA or email verification, so self-signup needs extra work beyond reusing it. That lowers how 'ready to reuse' the existing logic is, but does not change that the gap is real.

### 16. [MEDIUM] [MUST-HAVE] No billing: no payment gateway, no tenant invoices, no renewal or upgrade flow
- **Location:** `backend/database/migrations/2019_09_15_000015_create_subscriptions_table.php:15`
- **Effort:** XL
- **Evidence:** `subscriptions` has `billing_cycle enum(monthly,yearly)`, `status enum(active,past_due,cancelled,trialing)`, `amount decimal(10,2)`, `starts_at`, `ends_at`, `cancelled_at`, `payment_method`, `payment_details json` and `notes`. Only the provisioner writes to it (hard-coded `billing_cycle=monthly`, `payment_method=manual`). No controller, action or route reads or writes subscriptions afterwards. 'Renewal' is `ToggleTenantStatusAction`, which just adds `extend_days` to `tenants.subscription_ends_at` and creates no Subscription or payment record. No Paymob, Fawry, Stripe or webhook code exists, although the SaaS doc §6 and Gantt p4_2 promise them.
- **Impact:** There is no revenue record, MRR, receipts or audit trail of who paid what. Upgrades and downgrades (changing plan_id) are not modeled, and the super-admin 'extend' is untraceable.
- **Recommendation:** Phase 1 (manual, fits Egypt): add super-admin 'record payment / renew / change plan' actions that write Subscription rows plus a platform invoice, inside a DB transaction. Phase 2: add a gateway adapter (Paymob or Fawry) with signed webhooks that are idempotent.
- **Verification:** The finding is real. I checked it against the code.

What the code shows:
- **Only one place writes subscriptions.** `Subscription::create` appears only in `backend/app/Services/TenantProvisionerService.php:72`. It hard-codes `billing_cycle='monthly'` and `payment_method='manual'`, and sets `amount` to the plan's `price_monthly`.
- **The renewal action creates no record.** `backend/app/Actions/Tenants/ToggleTenantStatusAction.php:9-22` only changes `tenants.status` and adds `extendDays` to `subscription_ends_at`. It writes no Subscription or payment row and no audit entry.
- **Plan changes are not supported.** The only `plan_id` input is in `StoreTenantRequest.php:21` and `CreateTenantDTO.php:28`, which are used at tenant creation. No upgrade or downgrade action exists.
- **No billing routes or payment integrations.** No route in `routes/*` deals with subscriptions or billing. The only webhook is `public/update_webhook.php`, which is for deploys. Paymob, Fawry and Stripe appear only in `docs/03-architecture/saas-transformation-architecture-and-plan.md`.

What the finding gets wrong:
- **Subscriptions are read after creation.** `SuperAdminAnalyticsService.php:25-33` computes MRR from active subscriptions. `GetTenantsIndexDataAction.php:23` and `GetTenantDetailsAction.php:18` eager-load them. So the claim that nothing reads them is false.
- **That makes the impact a little worse.** Rows never change state: a trial never becomes active, and an extension never adds a row. So the super-admin MRR figure is stale or wrong, not just missing.

Why I lowered the severity to medium:
- The schema expects manual payment collection. The comment at migration line 22 lists cash, bank_transfer, vodafone_cash and instapay.
- A payment gateway is roadmap work, not a defect. The concrete gaps are:
  - no renewal or payment ledger
  - no plan-change flow
  - an untraceable "extend" action
  - an inaccurate MRR figure

Each is a product or roadmap gap, not data corruption or a security hole.

A side note on the cited migration: `amount` is `decimal(10,2)`, which also breaks the project's `DECIMAL(12,3)` rule.

### 17. [MEDIUM] Wholesale tier price is actually the minimum-price floor, and that floor defaults to cost, so wholesale sales can happen at zero margin
- **Location:** `backend/app/DTOs/Items/ItemDTO.php:30`
- **Effort:** M
- **Evidence:** There is no wholesale price column on items. ItemDTO:30 sets min_selling_price to cost_price when the caller does not send it. GetPOSBootstrapDataAction.php:89 and POSItemResource.php:24 then use 'price_wholesale' => min_selling_price when it is above 0, else selling_price. Item.php:44-47 does the same with the price_wholesale accessor. The active POS (PosView.vue:297-300) applies the same rule when the cashier switches the tier to 'wholesale'.
- **Impact:** Suppose an item is created with a cost and a retail price but no explicit minimum price. Every wholesale-tier sale of it goes out at cost, so the profit is zero. The column also means two different things: the minimum allowed price and the wholesale price. A retailer cannot set a wholesale price that differs from the price-control floor.
- **Recommendation:** Add a separate items.wholesale_price DECIMAL(12,3), or better a tenant price-list/tier table (item_prices: item_id, tier, price, optional store_id). Stop deriving the wholesale price from min_selling_price. Stop defaulting min_selling_price to cost_price in ItemDTO.
- **Verification:** The column conflation is real, but the zero-margin claim is overstated because it does not happen through the main UI.

Confirmed in code:
- There is no wholesale price column. The only extra price column is `min_selling_price` DECIMAL(12,3) default 0, added in `backend/database/migrations/tenant/2026_08_22_200000_add_min_selling_price_to_items_table.php:13`.
- `backend/app/DTOs/Items/ItemDTO.php:30` falls back to `cost_price` when `min_selling_price` is not in the data.
- Several places expose that same column as the wholesale price:
  - `GetPOSBootstrapDataAction.php:89`
  - `POSItemResource.php:24`
  - `Item.php:44-47`
  - `PosView.vue:299-300`, which uses it for `activePriceTier === 'wholesale'`
  - `ItemsTable.vue:57` and `:147`, which display it under the `wholesale_price` label
- The item form (`ItemFormModal.vue:71-72`) labels the same field `min_selling_price` ("أقل بيع", minimum sale). A tenant therefore cannot set a wholesale price that differs from the price floor. That is a real product and model gap for a SaaS sold to wholesale and by-kilo shops.

Why the impact is overstated:
- The only path that builds the DTO is `ItemController` (lines 132 and 166). The SPA form in `ItemsView.vue:115/168` always sends `min_selling_price: 0` by default.
- `isset(0)` is true, so the fallback to cost is not triggered from the UI. With 0, the POS wholesale tier falls back to `selling_price`, which is the retail price, not cost.
- Wholesale at cost (zero margin) therefore only happens for API clients that leave the field out entirely. That includes an update request without the field, which silently resets the floor and the wholesale price to cost. Or it happens when the user explicitly types cost into the "minimum sale" field.

Adjacent issues seen while verifying:
- When `min_selling_price` is 0, the edit form prefills it with `selling_price` (`ItemsView.vue:182`). Saving then makes the floor and the wholesale price equal to retail.
- `usePOSCart.js:54-55` reads `item.price_wholesale` for wholesale customers. The model accessor returns "0.000" rather than null when the floor is 0, so the `??` fallback does not apply. This is unverified at runtime.

Conclusion: real design flaw, medium severity, not high.

### 18. [MEDIUM] The server never enforces min_selling_price, so the line price is fully client-controlled
- **Location:** `backend/app/Services/InvoiceService.php:72`
- **Effort:** S
- **Evidence:** InvoiceService::confirmInvoice reads the price as $unitPrice = (string)$line['unit_price'] and never compares it with item->min_selling_price or selling_price. StorePOSInvoiceRequest.php:83 only checks 'items.*.unit_price' => ['required','numeric','min:0']. A grep for min_selling_price in app/ finds no comparison anywhere. The line 379 update path behaves the same way.
- **Impact:** A cashier with pos.access, or anyone calling the API directly, can sell any item at 0 or below cost. There is no approval step and no audit flag. For a sellable multi-branch SaaS this is a real money-leak and fraud gap.
- **Recommendation:** Inside the transaction in InvoiceService, reject or flag a line whose unit_price is below the item's effective floor (store custom price, then tier, then min_selling_price) unless the user has a 'pos.override_price' permission. Log every override in activity_logs.
- **Verification:** The core claim holds. backend/app/Services/InvoiceService.php:72 sets $unitPrice = (string)$line['unit_price'] and uses it directly at lines 76 and 94. It is never compared with $item->min_selling_price, selling_price or cost. The update path at line 379 does the same. The only checks on unit_price are 'numeric','min:0' in StorePOSInvoiceRequest.php:83, StoreSalesInvoiceRequest.php:53 and UpdateInvoiceRequest.php:30. A grep of backend/app for min_selling_price found no server-side floor check: the field appears only in models, DTOs, resources, POS bootstrap and the seed command. A search for bccomp on unit_price or "below cost" logic found nothing, and no observer or policy guards the price. So any user with pos.access or invoices.create can submit a price of 0 or below cost through the API. In the frontend, POSCartTable.vue only shows min_selling_price as a "wholesale price" button and does not enforce it either.

I lowered the severity from high to medium for three reasons:
(1) Only authenticated staff who already hold POS or invoice permissions can do this. It is a missing business control, not an auth bypass.
(2) "No audit flag" is overstated. Each invoice stores user_id (line 41), and each line stores both the unit_price and the cost_price, so a below-cost sale can be traced and shows up in the profit data.
(3) The product treats min_selling_price both as a floor and as the wholesale price (POSItemResource.php:24, GetPOSBootstrapDataAction.php:89, PosView.vue:299). The intended floor semantics are not established anywhere in the code.

This is a real price-floor and override-approval gap that a sellable SaaS should close, but it is not a high-severity exploit.

### 19. [MEDIUM] Items have a single free-text unit (default kg). There are no multi-unit conversions, variants, or dedicated or scale barcodes
- **Location:** `backend/database/migrations/tenant/2026_08_08_160001_create_items_table.php:16`
- **Effort:** XL
- **Evidence:** Columns: code (unique), unit string(50) default 'كجم', selling_price, cost_price, min_stock_level. Later migrations only add min_selling_price, category_id and the POS display fields (image, pos_sort_order, is_pos_pinned, pos_sales_count). There are no barcode, unit-conversion, packaging or variant tables. The item form labels the field 'code (barcode)' (ItemFormModal.vue:22). A grep finds no handling of scale or weight-embedded EAN prefixes. invoice_items stores no unit snapshot. Super-admin units are just a CSV of labels (SuperAdminApiController.php:183-199, 372-396), and StoreItemRequest.php:22 does not check the unit against that list.
- **Impact:** Generic retail and wholesale needs piece/carton/dozen with conversion factors, purchasing by carton and selling by piece, several barcodes per item, and label-scale barcodes for by-weight goods (deli, spices, nuts). None of this can be modelled today. Shops would have to create duplicate items per pack size, which splits stock.
- **Recommendation:** Add item_units (item_id, unit, factor_to_base, barcode, price, is_default_sale, is_default_purchase), an item_barcodes table, and a tenant setting for scale-barcode parsing (prefix, item-code length, weight/price digits). Snapshot unit and factor on invoice_items and purchase_items. Change the default unit to a neutral base unit.
- **Verification:** The code confirms the finding. backend/database/migrations/tenant/2026_08_08_160001_create_items_table.php:13-16 defines `code` string(50) unique and `unit` string(50) default 'كجم'. The only later item migrations add min_selling_price, category_id and the POS display fields. app/Models has no unit, barcode, variant or packaging model. A grep of app/ and database/migrations for barcode, conversion_factor, unit_id, item_units and variant returns nothing. invoice_items only has `unit_price`, with no unit snapshot (160005:38). The item form only labels `code` as the barcode (ItemFormModal.vue:22). "Barcode" appears only in UI and translation strings, and there is no logic for EAN or scale prefixes. StoreItemRequest.php:22 and UpdateItemRequest.php:24 only check `unit` as a required string of at most 50 characters, with no check against an allowed list. In SuperAdminApiController.php:181-209 and :370-395, units are just a comma-separated list of labels kept in Setting. I am lowering the severity from high to medium. This is a missing feature on the roadmap, not a defect, and nothing breaks for data integrity, money or security. Several things already work: selling by weight works through DECIMAL(12,3) quantities, every item has one scannable barcode through the unique `code`, and POS search covers it. The gap does matter for the stated goal of generic retail and wholesale (buy by carton and sell by piece, several barcodes per item, labels from a weighing scale). Today shops would need a duplicate item for each pack size, which splits stock. So it should be planned, but it is not high severity.

### 20. [MEDIUM] CSV export routes in routes/web.php have no auth and no tenancy middleware
- **Location:** `backend/routes/web.php:278`
- **Effort:** S
- **Evidence:** web.php:278-281 registers /items/{id}/export-movements-csv, /customers/{id}/export-csv, /suppliers/{id}/export-csv and /items/export-csv against ExportController. `php artisan route:list --path=export -v` shows only the `web` middleware on these four routes: no Authenticate, no can:, no InitializeTenancyByDomain. ExportController calls Customer::findOrFail($id) and similar with no authorization check. Compare the tenant.php versions of the activity-log and ABC exports, which have web + PreventAccessFromCentralDomains + InitializeTenancyByDomain + Authenticate + Authorize. The SPA never calls these four URLs (grep in resources/js: 0 hits).
- **Impact:** An anonymous GET runs ExportService on the default (central) connection. The central migrations have no items, customers or invoices tables, so on a clean central DB this is an unauthenticated 500. If the production central DB is the legacy single-store Sroor database (the root restore_sroor_db.py / backup_sroor_db.py hint at that; not verified), anyone can download full customer and supplier ledgers and the inventory valuation without logging in.
- **Recommendation:** Delete these four routes and ExportController, or move them into the tenant.php authenticated group with can:customers.view, can:items.view and similar, and add a policy check per record. Add a feature test that asserts an anonymous request gets 401/404.
- **Verification:** The missing guard is real, but the data leak only happens if the central database holds legacy tables, so I lowered the severity to medium.

What the code shows:
- **No auth or tenancy on the routes.** backend/routes/web.php:278-281 registers the four ExportController GET routes at top level. They have no middleware group, no auth, no can: check and no tenancy middleware.
- **Nothing in bootstrap adds a guard.** backend/bootstrap/app.php loads web.php through withRouting(web: ...) and only appends StoreScope to the web group. StoreScope (app/Http/Middleware/StoreScope.php) does nothing unless the user is already logged in, so it blocks no one.
- **The controller has no check either.** backend/app/Http/Controllers/ExportController.php has no constructor middleware and no authorize or policy call. It calls Customer::findOrFail, Supplier::findOrFail and Item::withTrashed()->findOrFail, then passes the result straight to ExportService. exportItemMovements also accepts store_id from the query string without checking it.
- **Tenant routes do not cover these four exports.** routes/tenant.php only defines the ABC and activity-log exports, so these four paths resolve only through web.php. web.php routes have no domain restriction, so they also match on tenant domains, with tenancy never started and the default (central) connection in use.
- **Not part of the finding:** the nearby /activity-logs/export-csv route on web.php:282 is protected. FilterActivityLogsRequest::authorize() returns false when there is no user.

Why I lowered it from high:
- The central migrations in backend/database/migrations (19 files: users, cache, jobs, plans, tenants, subscriptions, domains, tokens, permissions, activity_logs, pulse, telescope, impersonation, app_versions) create no items, customers, suppliers or invoices tables.
- On a central database built from the shipped migrations, an anonymous request therefore fails with a 500 (table not found) rather than leaking data.
- Real customer and supplier statements or inventory data only leak if the production central database is the legacy single-store database. Nothing in the code proves that.

So this is a confirmed broken-access-control defect that breaks the project's tenant-isolation and authorization rules. Its exploitable impact depends on how the production database is deployed.

### 21. [MEDIUM] Audit trail misses the sensitive actions: price changes, users and roles, settings, trash, and every super-admin action
- **Location:** `backend/app/Actions/SuperAdmin/ImpersonateTenantAction.php:16`
- **Effort:** M
- **Evidence:** Of the ~40 Actions checked, these have zero log calls: Items/UpdateItemAction (updates selling_price, cost_price and min_selling_price silently), Users/{Create,Update,Delete,ToggleActive}, Roles/UpdateRolePermissionsAction, Settings/UpdateSettingsAction, Expenses/*, Customers/*, Trash/{Restore,ForceDelete}, Tenants/{Toggle,OverrideFeature,Delete,UpdateDatabaseConfig,Provision}, Plans/UpdatePlanAction, SuperAdmin/ImpersonateTenantAction. tenant.php:33-35 (/impersonate/{token}) sets session flags but writes nothing. The ActivityLog model has a label for 'item_price_changed' (ActivityLog.php:103), but nothing ever writes it. What is logged: invoice confirm/cancel/update/delete, purchases, shifts, stock adjustment (StockService:314), transfers, treasury transfer, login/logout.
- **Impact:** A tenant cannot answer 'who changed this price', 'who gave the cashier admin rights' or 'who force-deleted this return'. Platform staff can impersonate any tenant admin with no trace. That is a trust and compliance blocker for a multi-tenant SaaS, and fraud by a privileged employee is invisible.
- **Recommendation:** Log in the Actions, or with model observers on Item price fields, User, Role and permission sync, Setting, and Trash restore/force-delete. Write super-admin actions (impersonate, toggle, override, delete, run-migrations, plan update) to the central activity_logs with the actor and the target tenant_id. Show impersonation sessions to the tenant.
- **Verification:** The finding holds up in the code, though one part is overstated. Nothing else writes these records: there are no model observers on Item, User or Setting, no booted() hooks, no listeners and no audit middleware. Only one observer is registered, Tenant::observe(TenantObserver) at AppServiceProvider.php:53.

What I confirmed:
- UpdateItemAction.php changes cost_price, min_selling_price and selling_price inside DB::transaction and logs nothing.
- 'item_price_changed' only shows up as a label in ActivityLog.php (lines 70 and 103). No code ever writes it.
- None of the Users, Roles, Settings, Trash, Plans, SuperAdmin, Expenses or Customers actions calls ActivityLogService or AuditLogService. A grep for log calls under those folders only matched GetTenantDetailsAction, which is a read action.
- The tenant.php route /impersonate/{token} only sets the session flags is_impersonating and impersonated_by_super, then calls UserImpersonation::makeResponse. ImpersonateTenantAction creates a stancl token and records nothing. A grep for 'impersonat' found no other place that persists anything.
- ActivityLog and AuditLog are written only for invoices, purchases, payments, returns, treasury, stock adjust and transfer, and auth login/logout.

Where it is overstated:
- TenantObserver writes plain file logs (Log::warning/info/alert) when a tenant's status or enabled_features change and when a tenant is deleted. These have no actor and are not a queryable audit trail, so the coverage is weak but not zero.
- SuperAdminLoginAction does log super-admin logins.

Why I lowered it to medium: this is a missing compliance and accountability feature, not a flaw anyone can exploit. Only already-authorized privileged users act without a trace, so it is serious for a multi-tenant SaaS but not an active vulnerability.

### 22. [MEDIUM] system-architecture-master.md audit table falsely marks 25 of 34 views as thin (< 80 lines)
- **Location:** `docs/system-architecture-master.md:15`
- **Effort:** M
- **Evidence:** There are 34 rows marked '✅ (< 80 سطر)'. Measured with wc -l: PosView.vue 839, ItemsView.vue 312, InvoicesView.vue 270, StoresView.vue 185, CategoriesView.vue 172, StockTransfersView.vue 172, DailyJournalView.vue 153, SettingsView.vue 145, ReportsView.vue 130 ... 25 rows are over 80. CLAUDE.md itself admits PosView is about 840 lines. The '7/7 ناجحة' test column has no linked evidence.
- **Impact:** The live audit-status table, which the rules call the system's source of truth, reports pages as audited and compliant when they are not. Planning and sales readiness built on it are wrong.
- **Recommendation:** Regenerate the table from real measurements (line counts, test runs with dates). Change unverified ticks to 'غير موثّق'.

### 23. [MEDIUM] frontend-architecture.md is entirely Livewire/Alpine/Blade guidance and is still indexed
- **Location:** `docs/03-architecture/frontend-architecture.md:3`
- **Effort:** M
- **Evidence:** Line 3 says the frontend integrates Livewire 4 + Blade + Alpine. Lines 17-45 give a Livewire-vs-Alpine decision tree, lines 49-54 an `app/Livewire/` component tree, and lines 97-114 Blade layouts and components. backend/app/Livewire does not exist. The real UI is backend/resources/js (Vue 3.5, Pinia 3, Tailwind 4, Vite 8). Only the print-*.blade.php layouts it lists still exist (routes/web.php:58-249).
- **Impact:** Current-guidance doc that is harmful: an engineer or AI following it will reintroduce removed frameworks. docs/README.md:38 still points to it as the frontend reference.
- **Recommendation:** Replace it with a Vue SPA architecture doc (views/Components/Composables/stores/router, Layouts SpaLayout/SuperAdminLayout, useTrans, Capacitor/Electron bridges). Keep only the print-layout section, rewritten for the Blade print routes.

### 24. [MEDIUM] backend-architecture.md describes a Livewire-to-Service layering with no Actions/DTOs/Policies/Resources
- **Location:** `docs/03-architecture/backend-architecture.md:13`
- **Effort:** M
- **Evidence:** The diagram at lines 13-24 shows 'Livewire 4 Components' calling Service classes. The sequence diagram at lines 113-140 has a Livewire participant, and line 61 is headed 'Proposed Service Classes'. The real app has 98 Action classes in 28 domains, 28 DTOs, 21 Policies, 31 Api controllers, Form Requests, API Resources, the ApiTokenAuth/ResolveApiTenancy/StoreScope/StoreAccess middleware and 25 Services. The doc never mentions Sanctum, tenancy or /api/v1.
- **Impact:** The backend reference doc does not describe the Request → FormRequest → DTO → Action → Resource flow that the golden rules require, and it says nothing about tenant or store scoping.
- **Recommendation:** Rewrite it around the real layering and tenancy/store middleware. Keep the still-valid transaction and lockForUpdate algorithm, but rehome it in Actions/StockService.

### 25. [MEDIUM] database-schema.md documents a pre-SaaS single-store schema (16 tables), missing about half of the 35 tenant migrations and all central tables
- **Location:** `docs/03-architecture/database-schema.md:45`
- **Effort:** M
- **Evidence:** The sections at lines 47-317 cover items, customers, suppliers, invoices, purchases, stock_movements, payments, returns, stock_deposits, users, audit_logs, permissions and a fictional `system_settings`/`backups_meta`. grep counts are 0 for stores, store_stocks, stock_transfers, categories, activity_logs, cash_shifts, treasury_transfers, expenses, additional_expenses, store_id, deleted_at, api_token, cost_center, min_selling_price, price_tier, tenants, plans, subscriptions and domains. All of these exist in backend/database/migrations/tenant (35 files) or in the central migrations.
- **Impact:** Schema decisions (store scoping, soft deletes, central vs tenant DB) are invisible in the reference, so tenant/store isolation reviews based on it miss most of the tables.
- **Recommendation:** Split it into central-schema and tenant-schema docs generated from the migrations, with an ERD covering store_id scoping and soft deletes.

### 26. [MEDIUM] SaaS transformation plan prescribes Vue + Inertia + PrimeVue and 'no REST API/tokens', the opposite of what was built
- **Location:** `docs/03-architecture/saas-transformation-architecture-and-plan.md:59`
- **Effort:** S
- **Evidence:** Line 15 gives the target frontend as 'Vue 3 + Inertia.js + Tailwind'. Lines 59-63 argue for Inertia and say 'لا حاجة لبناء REST API منفصل وإدارة Tokens'. Line 189 says to install Inertia + PrimeVue. Inertia was removed in 9b08be27. There is no primevue in backend/package.json. The real app is a token-based /api/v1 REST API (Sanctum plus the ApiTokenAuth fallback).
- **Impact:** The only SaaS roadmap or architecture doc is the main input for the sellable-SaaS work, and its architecture section contradicts the code. Its still-valid business targets (self-signup, payment gateway, 14-day trial) are mixed in with obsolete tech choices.
- **Recommendation:** Mark sections 3.x as superseded. Extract the business roadmap (signup, billing, plan limits) into a current SaaS roadmap that reflects the actual API/token architecture.

### 27. [MEDIUM] AGENTS.md page-audit step tells agents to edit the generated `default` translations, contradicting its own rule and .claude/rules/localization
- **Location:** `AGENTS.md:171`
- **Effort:** S
- **Evidence:** Line 171 says 'ربط كافة النصوص بمفاتيح الترجمة في `lang/ar`, `lang/en`, و `default`'. AGENTS.md:52 says JS translation files are never edited by hand. CLAUDE.md rule 6 and backend/AGENTS.md:19 say 'never hand-edit resources/js/helpers/defaultTranslations.*' (the file exists and is generated by lang:export).
- **Impact:** Agents following the page-audit protocol will hand-edit generated files, and `npm run build` will silently overwrite those edits or ar/en parity will drift.
- **Recommendation:** Drop '`default`' from step 5 and say 'then run php artisan lang:export'.

### 28. [MEDIUM] multi-tenancy rule #7 refers to backend feature gating and a FeatureGate.vue that do not exist
- **Location:** `.claude/rules/multi-tenancy.md:33`
- **Effort:** S
- **Evidence:** The rule says to 'check features through `TenantFeatureManager` (backend) and `FeatureGate.vue` / `useModules`'. grep finds no caller of TenantFeatureManager::isFeatureEnabled, Tenant::hasFeature or Plan::hasFeature in app/ or routes/. The only consumer, OverrideTenantFeatureAction, only toggles overrides. No FeatureGate.vue exists. Only useModules.js and the sidebar/nav components hide menu items.
- **Impact:** The rule implies that plan features are enforced server-side. In fact they are only hidden in the UI, so any tenant can call API endpoints for modules its plan does not include. This is a monetisation gap for the SaaS that the docs hide.
- **Recommendation:** Reword the rule to say gating is UI-only today. Track server-side enforcement (route middleware calling TenantFeatureManager) as a roadmap item, and remove the FeatureGate.vue reference.

### 29. [MEDIUM] AGENTS.md codifies the bearer token in the query string for Telescope as a mandatory rule
- **Location:** `AGENTS.md:48`
- **Effort:** S
- **Evidence:** AGENTS.md:48 mandates '/telescope-access?token=...'. routes/web.php:17-28 reads `$request->query('token')`, resolves Sanctum PersonalAccessToken::findToken, and falls back to `User::where('api_token', $token)`. ApiTokenAuth.php:21 also accepts `$request->query('api_token')`. .claude/rules/security-and-operations.md says 'Webhooks / tokens in URLs: don't add more.'
- **Impact:** Full API bearer tokens end up in server access logs, browser history and Referer headers. The governance doc presents this as a required pattern instead of debt.
- **Recommendation:** Reclassify it in AGENTS.md as known debt. Recommend a short-lived signed URL or a session exchange for Telescope, and removing query-string api_token support.

### 30. [MEDIUM] 13 مسار في tenant.php بتشاور على methods مش موجودة في الـ controllers
- **Location:** `backend/routes/tenant.php:90`
- **Effort:** M
- **Evidence:** اتأكدت بـ grep على `function <name>(` في `app/Http/Controllers/Api/*`. الناقص: `InvoiceController::edit/update/destroy/restore` (السطور 90-94)، `PurchaseController::create` (194)، `ReturnController::create` (201)، `DailyJournalController::openShift/closeShift/storeExpense` (222-224)، `SettingController::sendDailySummaryTelegram/sendBackupTelegram/downloadBackup/clearCache` (240-245). وكمان `Components/Settings/BackupTab.vue` بيربط على `/settings/backup/download` ومحدش بيعمل import للمكوّن ده.
- **Impact:** أي طلب على المسارات دي بيرجع 500 (BadMethodCallException). تنزيل النسخة الاحتياطية وإرسالها على Telegram من الإعدادات مش شغالين رغم إن المرحلة 5 في roadmap وproject-spec §29 مكتوب إنهم متنفذين. tenant.php بقى جدول مسارات legacy من أيام Livewire لسه بيتحمّل في `TenancyServiceProvider:119-121`.
- **Recommendation:** قرار معماري: إما تحذف مسارات الـ session القديمة من tenant.php وتسيب بس مسارات الطباعة وSPA host، أو تنفّذ الـ methods الناقصة. أضف test يمرّ على كل مسار مسجّل ويتأكد إن الـ action بتاعه موجود (`Route::getRoutes()` + `method_exists`).

### 31. [MEDIUM] tasks-breakdown.md مفيهوش ولا مهمة مفتوحة، ومش متابع شغل SaaS خالص
- **Location:** `docs/05-planning/tasks-breakdown.md:3`
- **Effort:** M
- **Evidence:** `grep -c "[ ]"` بيرجع 0 في tasks-breakdown وphases-roadmap وselected-features-implementation-plan. الملف بيوصف مهام Livewire وBlade (السطور 3 و10 و27-33 و56-59: `ItemList` و`PurchaseCreate` و`PaymentModal` و`InvoiceCreate` و`layouts/app.blade.php`)، ومفيش `app/Livewire` أصلًا. آخر مرحلة هي 15 (السطر 261)، ومفيش مرحلة لـ tenancy أو super-admin أو Capacitor أو Electron، مع إن الكود فيه 35 migration للمستأجر وsuper-admin API وAndroid وdesktop.
- **Impact:** أي مهندس أو AI session جاي هيفهم إن المنتج خلصان 100% وإن الواجهة Livewire، فبيفقد خريطة الفجوات الحقيقية (billing وsignup وتطبيق الحدود والباركود وتعدد الوحدات). وAGENTS.md خطوة 3 بتطلب تعليم المهام في الملف ده بالذات.
- **Recommendation:** جمّد الملف كأرشيف تاريخي (أو انقله لـ `docs/history`)، واعمل `docs/05-planning/roadmap-saas.md` بمصفوفة done/partial/missing مبنية على الكود (المصفوفة اللي في finding الـ info تنفع كبداية).

### 32. [MEDIUM] المرحلة 12 (التحويل بين الخزن) متعلّمة [x] لكن مفيش route ولا واجهة ولا test
- **Location:** `docs/05-planning/tasks-breakdown.md:204`
- **Effort:** M
- **Evidence:** `TreasuryService::transfer()` موجودة (TreasuryService.php:115) وجدول `treasury_transfers` موجود. لكن `TreasuryController` فيه `summary()` بس، وapi.php فيه `GET /treasury/summary` بس (السطر 135). مفيش أي Vue component في DailyJournal بيعمل transfer (البحث رجّع بس قيمة `bank_transfer` في QuickExpenseModal). و`TreasuryTransferFeatureTest` اللي السطر 211 بيقول إنه نجح 6/6 مش موجود في tests/.
- **Impact:** ميزة مالية متعلّن إنها اتسلّمت، لكن المستخدم مش هيقدر يوصلها. والحساب المالي (رسوم التحويل وكفاية الرصيد) ملوش أي تغطية اختبارات.
- **Recommendation:** إما تعرض `POST /treasury/transfers` عن طريق Action وForm Request مع test، أو تعلّم المهمة partial (الخدمة موجودة فقط).

### 33. [MEDIUM] مهام معلّمة [x] تنفيذها جزئي فقط (استعادة المشتريات، Excel/PDF، اختبار التزامن، الاختبارات المسمّاة)
- **Location:** `docs/05-planning/tasks-breakdown.md:152`
- **Effort:** M
- **Evidence:** `restorePurchase()` موجودة في PurchaseService.php:334، لكن مفيش route ليها (api.php:79-83 فيه index وshow وstore وcancel بس) ومفيش زرار في Vue. السطر 93 بيقول Excel/CSV، والموجود CSV بس عن طريق `ExportController`. composer.json مفيهوش أي مكتبة XLSX أو PDF، مع إن project-spec.md:546-547 وphases-roadmap.md:82 بيطلبوا XLSX وPDF. السطر 38 بيقول إن فيه محاكاة لجلستين متزامنتين، لكن `ConcurrencyTest.php` فيه test واحد متسلسل (`test_overselling_beyond_stock_fails...`) من غير تزامن حقيقي. الاختبارات المذكورة بالاسم `LandedCostAndAdditionalExpensesFeatureTest` (السطر 191) و`TreasuryTransferFeatureTest` (211) و`LivewirePagesTest` (232)، واختبارات selected-features-implementation-plan.md:115-118 (`AbcAnalysisFeatureTest` و`BranchProfitLossFeatureTest` و`SmartReorderFeatureTest` و`DashboardAnalyticsFeatureTest`) كلها مش موجودة. وUnit tests للخصومات (السطر 36) مش موجودة، لأن `tests/Unit` فيه ExampleTest بس.
- **Impact:** العلامات وأعداد الاختبارات المذكورة (100/125/131/140) مش قابلة للتحقق، ومنطق مالي زي landed cost وP&L وABC بيبان كأنه متغطي وهو مش متغطي.
- **Recommendation:** راجع كل [x] وصنّفه done/partial، واربط كل ادعاء اختبار باسم ملف موجود فعلًا. أضف tests لـ ProfitLossService وInventoryAnalyticsService وlanded cost.

### 34. [MEDIUM] جدول التدقيق في system-architecture-master.md بيقول 100% وكلامه مش مطابق للكود
- **Location:** `docs/system-architecture-master.md:4`
- **Effort:** S
- **Evidence:** بيقول إن كل الـ views 35 ملف وكل واحد أقل من 80/85 سطر (السطور 14-48 و54). اللي طلع بـ `wc -l`: `PosView.vue` 839 و`ItemsView.vue` 312 و`InvoicePrintView.vue` 289 و`InvoicesView.vue` 270 و`StoresView.vue` 185 و`SettingsView.vue` 145، و20+ view أكتر من 80 سطر. وفيه كمان `POS/PosView.legacy.grid.vue` (1452 سطر) ملف ميت. بيقول Zero Hardcoded 100% (السطر 57)، لكن PosView.vue:579 و764-785 فيهم HTML إيصال عربي ثابت و`ج.م` و`☕` و`parseFloat(...).toFixed(2)`، وInvoicesView.vue:221 و245 فيهم رأس CSV وسبب إلغاء عربي ثابت، وItemsView.vue:97 فيه قائمة وحدات ثابتة. المسارات غلط كمان: `/dashboard` (السطر 48) هو في الـ router `/`، و`/super-admin` (السطر 42) هو `/super-admin/dashboard`. الجدول ناقصه `/invoices/:id` مع إن `docs/pages/invoice-show.md` موجود، وناقصه `/connect` و`/login` و`/invoices/:id/print`. والإصدار مكتوب v1.0.80 (السطر 3) بينما commits الـ release وصلت v1.0.135.
- **Impact:** الوثيقة الرئيسية بتدّي حالة جودة كاذبة، فبيتفوّت إعادة هيكلة PosView وإصلاح الترجمة.
- **Recommendation:** أعد بناء الجدول من `router/index.js` (39 مسار) مع عدد الأسطر الفعلي ونتيجة فحص النصوص الثابتة، وحط حالة ⚠️ بدل 🟢 للـ views اللي فوق الحد.

### 35. [MEDIUM] توثيق صفحتي pos.md وinvoices.md بيذكر components وendpoints وActions مش موجودة
- **Location:** `docs/pages/pos.md:21`
- **Effort:** S
- **Evidence:** pos.md:6 بيذكر المسار `views/PosView.vue` (حوالي 70 سطر)، والحقيقي `views/POS/PosView.vue` وطوله 839 سطر. الشجرة في السطور 22-28 (`PosTopBar` و`PosCategoryPills` و`PosItemGrid` و`PosCartDrawer` و`PosCheckoutModal` و`PosHoldOrdersModal` و`PosShiftModal`) مش موجودة، والموجود `POSHeader` و`POSCategorySidebar` و`POSProductGrid` و`POSCartTable` و`POSCheckoutPanel` و`POSMultiPaymentModal`... السطر 47 بيذكر `POST /api/v1/cash-shifts/open|close`، والحقيقي `/shifts/open|close` (api.php:122-123). وماذكرش `/pos/bootstrap` و`/pos/checkout` (api.php:109-110) اللي هما فعلًا المستخدمين. الفواتير المعلقة (السطر 13) هي tabs في localStorage (`usePosOrders.js`). في invoices.md السطور 20-29: `InvoicesSearchFilterBar` و`InvoicesFilterPanel` و`InvoiceCancelModal` مش موجودين، والموجود `InvoicesQuickSearch` و`InvoicesFilterSidebar` و`InvoicesBulkActionsBar` و`InvoicesMetricsCards`. السطر 47 بيذكر `GET /api/v1/invoices/:id/print`، وده مش موجود في api.php. السطر 48 بيذكر `CreateInvoiceAction` و`CancelInvoiceAction`، والحقيقي `CreateSalesInvoiceAction` و`CancelSalesInvoiceAction` و`ProcessPOSInvoiceAction`. items.md وsettings.md دقيقين تقريبًا في الـ components والـ endpoints، بس عدد الأسطر غلط (items.md:6 بيقول حوالي 75 والفعلي 312، وsettings.md:4 بيقول حوالي 75 والفعلي 145)، وitems.md:34 بيذكر حقل باركود مش موجود في جدول `items` (فيه `code` بس).
- **Impact:** اللي هيعدّل الـ POS أو الفواتير بناءً على الوثائق هيدوّر على ملفات وendpoints مش موجودة.
- **Recommendation:** أعد كتابة pos.md وinvoices.md من الـ imports الفعلية والـ routes، وصحّح عدد الأسطر في items.md وsettings.md.

### 36. [MEDIUM] وثائق الموديولات بتقرر قواعد عمل مش متطبّقة في الكود (الحد الأدنى للسعر، الامتثال الضريبي)
- **Location:** `docs/modules/inventory.md:19`
- **Effort:** M
- **Evidence:** inventory.md:19 بيقول إن `min_selling_price` موجود علشان يمنع البيع بأقل من التكلفة. grep على Services وActions وRequests مالقاش أي مقارنة سعر البيع بـ `min_selling_price`. الحقل بيُستخدم بس كسعر جملة في `GetPOSBootstrapDataAction.php:89` و`Item::getPriceWholesaleAttribute` (Item.php:44-46). sales.md:4 بيقول امتثال ضريبي كامل وتسلسل دفاتر ضريبية (السطر 30)، ومفيش أعمدة tax في migrations المستأجر، و`InvoiceService.php:36` بيقبل `invoice_number` جاي من العميل. باقي القواعد اتأكدت: الإلغاء بيعيد المخزون وبيعيد حساب رصيد العميل (InvoiceService.php:315-316)، و`min_stock_level` موجود.
- **Impact:** الحقل نفسه بيستخدم لمعنيين: سعر الجملة وحد أدنى للبيع. الكاشير يقدر يبيع تحت التكلفة والوثائق بتقول إن ده ممنوع، والمنتج ممكن يتسوّق بامتثال ضريبي مش موجود.
- **Recommendation:** حدد معنى واحد للحقل. لو حد أدنى، أضف validation في `ProcessPOSInvoiceAction` و`CreateSalesInvoiceAction`. ولو سعر جملة، غيّر اسمه مع مستويات `price_tier`. وشيل ادعاء الضرايب من sales.md لحد ما تتنفذ.

### 37. [MEDIUM] project-spec.md وAGENTS.md وdocs/README.md لسه بيوصفوا Livewire كـ stack حالي وبيمنعوا Vue
- **Location:** `project-spec.md:10`
- **Effort:** M
- **Evidence:** project-spec.md:10 و35 و760 و818 و915 بيقولوا `Livewire 4`، والسطور 26-30 و834 بتقول ممنوع Vue وممنوع API Layer. السطر 20 بيقول Laravel Breeze، ومش موجود في composer.json. ونفس الكلام في AGENTS.md:15 (`Livewire 4 + Alpine.js + Blade`، ولا Vue ولا API). وقالب السجل في AGENTS.md (`session-NN` و`summary.md`) متعارض مع `.claude/rules/docs-and-history.md:24` (`NN-short-slug.md`). docs/README.md:3 و39 و61 و70 بيقولوا Livewire 4 وBreeze. والكود الحقيقي (composer.json): Laravel 13 وSanctum وstancl/tenancy وspatie، والواجهة Vue SPA. CLAUDE.md:20 نفسه بيمنع إعادة إدخال Livewire.
- **Impact:** أي AI agent بيقرأ AGENTS.md الأول (زي ما هو مكتوب فيه) هيتبع تعليمات مناقضة للـ stack، وممكن يرفض أو يكسر شغل Vue.
- **Recommendation:** زامن AGENTS.md مع `.claude/rules/` (لما يتطلب ده). أضف banner "وثيقة تاريخية – غير سارية" على project-spec.md، أو انقله لـ `docs/01-overview/legacy-spec.md` واكتب spec جديد للـ SaaS. وعدّل docs/README.md.

### 38. [MEDIUM] الـ imports بتستخدم `services/` بحروف صغيرة، والملف في git اسمه `Services/`، فالبناء هيقع على Linux
- **Location:** `backend/resources/js/Composables/useActivityLogs.js:2`
- **Effort:** S
- **Evidence:** `git ls-files` بيرجع `resources/js/Services/api.js` بس، و45 ملف بيعملوا import من `'../services/api'` أو `'../../services/api'`. وفي git `core.ignorecase=true`. Vite alias فيه `@` بس.
- **Impact:** `npm run build` على CI أو سيرفر Linux هيفشل في resolve الملف. البناء شغال حاليًا لأنه بيتعمل على Windows بس.
- **Recommendation:** وحّد اسم المجلد (`git mv Services services` بخطوتين) أو عدّل الـ imports، وضيف build على Linux في CI.

### 39. [MEDIUM] [MUST-HAVE] Tenant provisioning is not atomic and runs synchronously in the HTTP request
- **Location:** `backend/app/Services/TenantProvisionerService.php:55`
- **Effort:** M
- **Evidence:** `Tenant::create` fires `TenantCreated`, which runs CreateDatabase and MigrateDatabase synchronously. Domain creation, the Subscription insert and the `$tenant->run(...)` seeding happen afterwards with no transaction or compensation. Any failure leaves a tenant row plus a physical DB with no admin user or subscription. The controller only logs the error and returns 422 (SuperAdminApiController.php:104-109). The main store is seeded with `'type' => 'retail'` (~L93), but the stores migration expects `retail_shop/wholesale_van/main_warehouse` (database/migrations/tenant/2026_08_11_180000_create_stores_and_stocks_tables.php:20).
- **Impact:** A failed provisioning leaves orphaned DBs and half-built tenants that cannot log in. Retrying with the same slug fails on the unique check. A public signup built on this would hit request timeouts.
- **Recommendation:** Move provisioning into a queued job pipeline with a `provisioning_status`. On failure, compensate by deleting the tenant and DB. Fix the store type value.

### 40. [MEDIUM] [SHOULD-HAVE] Status values in ToggleTenantStatusRequest do not match the tenants.status enum
- **Location:** `backend/app/Http/Requests/ToggleTenantStatusRequest.php:19`
- **Effort:** S
- **Evidence:** The validation accepts `in:active,trial,suspended,expired`. The tenants migration defines `enum('status', ['active','trial','suspended','cancelled'])` (2019_09_15_000010_create_tenants_table.php). 'expired' is not in the enum, and 'cancelled' cannot be set from the API.
- **Impact:** Choosing 'expired' fails at the DB layer (MySQL strict mode or the sqlite CHECK), and the controller shows the raw exception text. Cancellation cannot be recorded.
- **Recommendation:** Align the two sets: add 'expired'/'past_due' to the enum with a migration, or drop 'expired' from the request, and allow 'cancelled'. Translate the error.

### 41. [MEDIUM] [SHOULD-HAVE] Super-admin panel lacks plan CRUD, subscription history, usage metrics, data export and working impersonation
- **Location:** `backend/routes/api.php:207`
- **Effort:** L
- **Evidence:** Plans have only `GET /plans` and `PUT /plans/{id}`, with no create, archive or delete, and no PlanFeature registry management. No endpoints exist for subscriptions or payments, per-tenant usage (users, stores, items, invoices per month, storage) or tenant export. `ImpersonateTenantAction` exists (app/Actions/SuperAdmin/ImpersonateTenantAction.php) but nothing calls it. The SPA 'impersonate' button only runs `window.open('http://'+tenant.domain)` (resources/js/Composables/useSuperAdminTenantShow.js:150-153). `POST /tenants/{id}/update-db-config` lets a super-admin rewrite tenant DB credentials from the UI. `run-migrations` returns raw Artisan output.
- **Impact:** The operator cannot launch the 5 or 6 tier structure from the pricing study, see who is over their limits, or support customers by impersonation. That impersonation is promised in SaaS doc §7.
- **Recommendation:** Add plan create and archive (soft, never delete a plan that has tenants), a subscriptions/payments ledger per tenant, a usage endpoint computed per tenant DB (cached), wiring of ImpersonateTenantAction to an endpoint with audit logging, and a per-tenant export.

### 42. [MEDIUM] [SHOULD-HAVE] Pricing exists in three or four conflicting versions (seeder, SaaS doc, pricing study, brochure)
- **Location:** `backend/database/seeders/PlansAndFeaturesSeeder.php:60`
- **Effort:** M
- **Evidence:** The seeder has 4 plans: free 0, basic 299, pro 599, enterprise 999 EGP/month, with mill and coffee wording in descriptions (L61, L102) and `blender.access` as a plan feature. saas-transformation-architecture-and-plan.md §4.2 has 3 plans: Starter 350, Pro 850, Enterprise 2,200. pricing-restructuring-and-packages-study.md §4 has 6 plans: 349, 499, 699, 899 (Vans), 1,099, 1,499, and says the current prices are 299, 699, 899, 1,499. docs/sroor-erp-pricing-and-features-brochure.html lists annual prices 3,490 through 14,990 (6 tiers, matching the study) plus add-ons of 100, 40 and 200 EGP. Add-ons such as extra branch, extra user, van, WhatsApp packs, e-invoice and subdomain have no model or table. The `api.access` description still mentions 'NativePHP / Flutter' (seeder L48).
- **Impact:** Sales material promises tiers, limits and add-ons that the product does not define or enforce. Which tier a customer is billed under is ambiguous.
- **Recommendation:** Choose one source of truth (the study's 6 tiers appear to be the latest). Update the seeder through a data migration, drop coffee wording from plan descriptions, model add-ons (for example `tenant_addons` with quantity, price and period), and mark the older doc sections as superseded.

### 43. [MEDIUM] customers.price_tier does not drive pricing at checkout in the active POS
- **Location:** `backend/resources/js/views/POS/PosView.vue:242`
- **Effort:** S
- **Evidence:** In PosView.vue:242-244, activePriceTier comes from the order state, which starts as 'retail' (usePosOrders.js:20). Only the POSHeader toggle (lines 219-229) changes it. PosView.vue never reads price_tier. Only the dead PosView.legacy.grid.vue:1064 and usePOSCart.js:54 switched the tier when a customer was selected, and usePOSCart is imported nowhere. On the server, neither InvoiceService nor CreateSalesInvoiceAction reads price_tier. CustomerPricingHelper::getRecommendedPrice ignores the tier.
- **Impact:** When the cashier selects a wholesale customer, retail prices are still used unless the cashier remembers to flip the toggle. The opposite also happens: any customer can be given wholesale prices by hand. The tier is cosmetic.
- **Recommendation:** Auto-set activePriceTier from selectedCustomer.price_tier, and have the server compute or validate the tier price. Delete usePOSCart.js and PosView.legacy.grid.vue if they are dead.

### 44. [MEDIUM] The active POS ignores per-store custom selling prices
- **Location:** `backend/app/Actions/POS/GetPOSBootstrapDataAction.php:44`
- **Effort:** S
- **Evidence:** The store-scoped query left-joins store_stocks only to get COALESCE(store_stocks.quantity, items.current_stock). It does not select store_stocks.custom_selling_price, and line 88 sets 'price_retail' => selling_price. Item::getEffectivePriceForStore and StoreStock's effective price exist, but this action does not use them. Reports (GetInventoryValuationReportAction:39, GetProfitLossReportAction:75) do use custom_selling_price.
- **Impact:** Branch-specific pricing (for example a van or kiosk price) never reaches the cashier, while the valuation and P&L reports assume it does. The reports and real sales therefore disagree.
- **Recommendation:** Select store_stocks.custom_selling_price in the bootstrap and in remote item search, and set price_retail to the store custom price when it is above 0, otherwise selling_price.

### 45. [MEDIUM] Fractional vs whole quantities are enforced by a hard-coded Arabic/English list of unit names
- **Location:** `backend/app/Services/InvoiceService.php:65`
- **Effort:** S
- **Evidence:** $discreteUnits = ['قطعة','حبة','علبة','باكت','كرتونة','شيكارة','طرد','دستة','جوال','piece','pcs','box','carton','pack','unit'] combined with fmod((float)$qty, 1.0). The super-admin unit list (for example 'زوج', 'طقم', 'باليتة', 'لتر', 'متر') does not match it.
- **Impact:** Units that are not in the list, such as 'زوج' (pair), 'طقم' (set) or 'كرتون', silently accept fractional sales. Tenants cannot configure this. The check also uses float arithmetic, which goes against the bcmath rule.
- **Recommendation:** Store an allows_fraction flag (or decimal places) per unit or item. Validate with bcmod or string scale checks instead of fmod.

### 46. [MEDIUM] The coffee blender is always enabled for every tenant. Its 'blender.access' plan feature exists but is never enforced
- **Location:** `backend/routes/api.php:162`
- **Effort:** M
- **Evidence:** The /coffee-blender/calculate and /coffee-blender/invoice routes have no feature or permission middleware. CreateBlenderInvoiceRequest::authorize only needs invoices.create or pos.access. PlansAndFeaturesSeeder.php:25 defines 'blender.access' ('استوديو توليف وخلاط البن', ☕), but nothing in app/ or routes/ references it, and TenantFeatureManager::isFeatureEnabled has no callers. The frontend toggle is resources/js/config/modules.json (coffee_blends enabled:true), a static file bundled at build time (useModules.js:1). The router meta (index.js:258-265) only needs items.create.
- **Impact:** A generic retail tenant always sees and can call a coffee-specific 'roast/grind/cardamom' invoicing tool. Plans cannot sell or hide it, so the feature-based plan model is not real.
- **Recommendation:** Add a tenant feature middleware (feature:blender.access) backed by TenantFeatureManager. Expose the resolved features to the SPA at login and drive the nav/router from them instead of modules.json.

### 47. [MEDIUM] Blender invoices hard-code grams-to-kg and coffee-specific notes, whatever the item's unit
- **Location:** `backend/app/Actions/Blends/CreateBlenderInvoiceAction.php:25`
- **Effort:** L
- **Evidence:** $kg = bcdiv((string)$comp['grams'], '1000', 4) is used as the invoice quantity for every component, ignoring item->unit. Notes are built from 'درجة التحميص' (roast degree), 'الطحن' (grind) and 'حبهان' (cardamom). Unit prices come from the client ($comp['unit_price']). The quantity has scale 4 but the column is DECIMAL(12,3).
- **Impact:** If a component item is stored in grams, pieces or liters, stock is deducted 1000x wrong, or the sale is rejected by the discrete-unit check. Sub-gram precision is also lost to rounding. As a generic 'assembly' feature it is unsafe.
- **Recommendation:** Convert using item units. Better still, replace it with a generic bill-of-materials / kit feature that has its own stock movement type. Keep the coffee wording only behind a tenant feature.

### 48. [MEDIUM] Customer credit limits are shown in the UI but have no column and no enforcement
- **Location:** `backend/app/Http/Resources/CustomerResource.php:21`
- **Effort:** S
- **Evidence:** CustomerResource outputs 'credit_limit' => (float)($this->credit_limit ?? 0), and translations exist (defaultTranslations.js:287). The customers migration has no credit_limit column, and Customer::$fillable does not include it. PopulateRealisticTenantDataCommand:352 passes 'credit_limit', which is silently dropped by mass assignment. InvoiceService credit sales (line 195) never check any limit.
- **Impact:** Wholesale credit sales have no ceiling. The UI shows 0 for every customer, which suggests a feature that does not exist.
- **Recommendation:** Add customers.credit_limit DECIMAL(12,3) nullable. Inside the locked transaction (the customer is already lockForUpdate'd at InvoiceService:29), block credit or partial sales that would exceed it, with a permission to override.

### 49. [MEDIUM] No offline POS and no promotions/loyalty
- **Location:** `backend/resources/js/Composables/useNativeBridge.js:34`
- **Effort:** XL
- **Evidence:** The only offline handling is the window 'offline' listener that sets isOnline=false. There is no IndexedDB, service worker or sale queue in resources/js. A grep for promotion, coupon, offer or loyalty in app/ and the tenant migrations returns nothing. Discounts exist only as a manual line discount_amount or an invoice-level fixed/percentage discount.
- **Impact:** A branch with flaky internet cannot sell at all. Retailers who expect buy-X-get-Y, time-boxed price offers or loyalty points have none.
- **Recommendation:** Put these on the roadmap: an offline sale queue with idempotent invoice numbers (Electron and Capacitor first), and a rules-based promotions table. Price lists will be needed anyway for the tier work above.

### 50. [MEDIUM] Two parallel audit tables: audit_logs is write-only, and payments and returns only go there
- **Location:** `backend/app/Services/AuditLogService.php:19`
- **Effort:** M
- **Evidence:** AuditLogService (audit_logs, tenant migration 2026_08_08_160009) is written from InvoiceService, PaymentService:66,122, PurchaseService, ReturnService:96,172 and TreasuryService:169. No controller, Action, Resource or Vue file reads AuditLog (grep 'AuditLog::' and 'audit_logs': only the nav translation key nav.audit_logs, which points to /activity-logs). InvoiceService, PurchaseService and TreasuryService write both tables for the same event. PaymentService and ReturnService write only audit_logs, so customer payments and sales returns never appear in the visible activity log. AuditLogService:19 uses `'user_id' => Auth::id() ?? 1`, which attributes system or queue writes to user #1 (the admin).
- **Impact:** Redundant writes inside money transactions. Old/new values are captured but nobody can see them. Payments and returns are missing from the only audit screen. Forensics may wrongly blame the admin account.
- **Recommendation:** Consolidate on one table. Either expose audit_logs (old/new diff) as a detail view under activity logs, or have ActivityLogService store old/new in properties and drop audit_logs. Route Payment and Return events to the visible log. Replace `?? 1` with null plus an actor_type of 'system'.

### 51. [MEDIUM] Trash force-delete hard-deletes return documents and items with no log
- **Location:** `backend/app/Actions/Trash/ForceDeleteTrashRecordAction.php:22`
- **Effort:** S
- **Evidence:** The match allows 'returns' => ReturnDocument::onlyTrashed()->findOrFail($id) followed by $model->forceDelete(). There is no transaction, no stock or balance check and no activity or audit log. The 2026-09-21 review (line 121) separately confirms that DeleteReturnAction only soft-deletes without reversing stock or balance.
- **Impact:** An approved financial document can be physically removed, which breaks the 'no hard delete of approved docs' rule. Combined with the non-reversing soft delete, stock and customer balance drift with no trail.
- **Recommendation:** Remove 'returns' (and any approved document) from force-delete. Log every restore and force-delete. Wrap them in DB::transaction.

### 52. [MEDIUM] No tenant-facing full data export (items, customers, invoices) and no import or offboarding path
- **Location:** `backend/app/Http/Controllers/ExportController.php:9`
- **Effort:** M
- **Evidence:** The only exports a tenant can reach from the SPA are the activity-logs CSV (api.php:179, tenant.php:228) and the reports export-abc route. ExportService has customer and supplier statements, inventory and item movements, but only through the unauthenticated, unused web.php routes. There is no invoices, customers-list or items-list CSV/Excel endpoint in api.php, and no import or restore route (grep: only trash and invoice restore).
- **Impact:** A paying tenant cannot get its own data out. That is a common procurement requirement and a churn/legal issue. Migrating a new tenant in from another system is also manual.
- **Recommendation:** Add authenticated, permission-gated, chunked CSV/XLSX export Actions for items, customers, suppliers and invoices (date range) under /api/v1, reusing ExportService::streamCsv. Later, add a per-tenant 'export everything' zip job.

### 53. [MEDIUM] Users and roles: no role create/delete, no password reset or invite flow
- **Location:** `backend/routes/api.php:174`
- **Effort:** M
- **Evidence:** RoleController only has index and updatePermissions (api.php:174-175, tenant.php:253-254). UpdateRolePermissionsAction always forces 'admin' to Permission::all(). There are no matches anywhere in app/ or routes/ for forgot, reset-password, Password::, invite or register. Users are created directly with a password by an admin (CreateUserAction).
- **Impact:** Tenants are stuck with the seeded roles and cannot model their own staff hierarchy. A forgotten password needs an admin, and a forgotten admin password needs platform support. There is no self-signup for tenants either.
- **Recommendation:** Add CreateRole and DeleteRole Actions (block system roles and roles in use), an admin-triggered password reset (temporary password or link), and optionally email or WhatsApp invites. Log all of these.

### 54. [MEDIUM] Self-reported review logs are unreliable: of 5 sampled 'done' claims, 1 is true, 2 partial, 2 false
- **Location:** `docs/controller-review-log.md:49`
- **Effort:** S
- **Evidence:** Sampled claims: (1) controller-review-log #20 SettingController 'Telegram ... مكتمل ومحصن': FALSE, 6 of its tenant routes point to missing methods. (2) The '+ Policy' claimed for nearly every controller: PARTIAL, the 21 Policy classes exist, but only CategoryPolicy is ever invoked (via the Store/UpdateCategoryRequest ->can()), so RolePolicy, TrashPolicy, SettingPolicy and the rest are dead code. (3) TrashController '9/9 Pass': PARTIAL, TrashApiTest.php has 6 tests (they pass). (4) UserController: 9 tests and the old controller deleted: TRUE, I ran the 5 sampled test files on sqlite :memory:: 39/39 passed, 181 assertions. (5) pages-audit-log.md:81-104 says PosView is '~70 lines' with '100% no hardcoded strings': FALSE, PosView.vue is 839 lines and has a hardcoded Arabic receipt at lines 764-785 (plus hardcoded Arabic in 6 POS components and a dead PosView.legacy.grid.vue). The external review docs/reviews/2026-09-21-full-project-review.md is accurate on the 3 points I checked: DeleteTenantAction.php:12 still fails `php -l` with a parse error, the login rate limit exists in ApiLoginRequest:49, and the hardcoded super-admin phone checks are still at AppServiceProvider:38,49 and web.php:38. i18n-review-log: app_update.php exists in both locales and ar/en have 25 files each (TRUE). Overall across 8 checks: 4 true, 2 partial, 2 false. The unreliable ones are the self-congratulatory '100%' logs.
- **Impact:** Future engineers and AI sessions will trust 'مكتمل ومحصن' and skip broken areas (backup, policies, POS i18n). Planning decisions rest on false status.
- **Recommendation:** Add a banner to controller-review-log.md, pages-audit-log.md and full-page-review-log.md: 'self-reported, unverified, superseded by docs/reviews/2026-09-21'. In the master audit-status table, require evidence (test file and count, command output) for any 'done'.

### 55. [MEDIUM] 117 ad-hoc production ops scripts committed at the repo root, undocumented
- **Location:** `deploy_remote.py`
- **Effort:** M
- **Evidence:** `git ls-files` shows 117 root-level *.py files tracked in git, plus 4 root *.php, including deploy_*.py (deploy_remote, deploy_live_and_seed, deploy_hostinger_php83/84 …), check_live_db.py, check_live_tinker.py, check_env_live.py, backup_sroor_db.py and restore_sroor_db.py. I did not open them, per instructions. The review docs do not describe a supported deploy or restore runbook.
- **Impact:** Undocumented, unreviewed paths to production (including seeding and restore) increase the risk of accidental data loss and of embedded credentials in git history. These scripts are also the only real backup/restore mechanism, and they are invisible to the product.
- **Recommendation:** Audit the scripts for embedded credentials (rotate if any are found), move the needed ones to an ops/ folder with a README runbook, delete the rest, and replace them with a CI/CD pipeline.

### 56. [LOW] New governance (CLAUDE.md, .claude/rules, rewritten AGENTS.md) exists only in the unpushed WIP commit; main and all 3 worktrees still carry the Livewire-era AGENTS.md
- **Location:** `AGENTS.md:18`
- **Effort:** S
- **Evidence:** `git show main:AGENTS.md`, `origin/main`, and the worktree heads fd85b8f1 and 0b497609 all contain 'Livewire 4 + Alpine.js + Blade' and have no CLAUDE.md. The on-disk AGENTS.md:18 says Livewire/Inertia/Alpine/NativePHP are removed, but only from commit 76f32ce0. The AGENTS.md text injected into this very session was the 2026-08-08 version (commit 5848806a): no Vue, session-NN template, points to docs/03-architecture/*.
- **Impact:** Any AI session on main or on another worktree/branch is told to build Livewire/Blade, not to use Vue, and to follow docs/03-architecture. That is harmful guidance for a Vue 3 SPA codebase.
- **Recommendation:** After removing the dumps, commit the governance files cleanly and land them on main. Check the tool that injects AGENTS.md is reading the current checkout and not a cached or other-worktree copy.
- **Verification:** I confirmed the facts in the finding, but the claimed impact is wrong.

What is true:
- CLAUDE.md, the eight .claude/rules/*.md files and the rewritten AGENTS.md (+43/-…) were all added only in local commit 76f32ce0. That is the "WIP: epitaxy pre-switch" commit, and feature/multi-tenant is [ahead 1] of origin.
- origin/feature/multi-tenant has no CLAUDE.md and no .claude/.
- main (6c43161f), origin/main and both worktree heads (fd85b8f1, 0b497609) still carry the AGENTS.md that says to use "Livewire 4 + Alpine.js + Blade" with no Vue (lines 16, 33, 116).

What is wrong or overstated:
1. main, origin/main, fd85b8f1 and 0b497609 are still Livewire codebases. Each has 0 .vue files and 36 Livewire/*.php files (checked with git ls-tree). So the Livewire guidance on those refs matches their own code. It is not harmful guidance for a Vue SPA. The Vue SPA exists only on the multi-tenant line.
2. On the SaaS branch that is already pushed (origin/feature/multi-tenant), AGENTS.md is already Vue-oriented. It has a "Vue 3 Component-Driven Standard" section, Vue i18n usage, and SpaLayout.vue/SuperAdminLayout.vue. It does not say Livewire. So a session that checks out the pushed SaaS branch is not told to build Livewire.

The real remaining problem is small. The newer CLAUDE.md and .claude/rules governance exists in only one local, unpushed WIP commit. If that commit is lost, or another clone is used, the governance is gone, and the pushed AGENTS.md lacks the explicit "Livewire/Inertia/Alpine/NativePHP removed" line. That is a process and housekeeping risk that one push fixes, not a high-severity issue.

### 57. [LOW] History-log naming and template conflict between AGENTS.md and the rules, and practice follows neither
- **Location:** `AGENTS.md:283`
- **Effort:** S
- **Evidence:** AGENTS.md:283 says `docs/history/YYYY-MM-DD/[page-name]-audit.md`. AGENTS §6 gives no filename. .claude/rules/docs-and-history.md requires `NN-short-slug.md`. The old AGENTS template used `session-01-feature-name.md`. Actual files: 52 are NN-*, 48 are session-*, and about 127 have free-form names, across 227 files. AGENTS template lines 245-249 is a pre-written checkbox list, while the rules template asks for real commands and results.
- **Impact:** Logs are hard to order and discover, and the pre-written checkbox template encourages ticking checks that were never run.
- **Recommendation:** Align AGENTS §6/§7 with the rules (NN-slug, no pre-written checkboxes). Leave old files untouched.

### 58. [LOW] docs/README.md index uses machine-local file:/// links, describes Livewire, and omits current docs
- **Location:** `docs/README.md:9`
- **Effort:** S
- **Evidence:** Every link is `file:///d:/projects/sroor/...` (lines 9-78). Line 39 says 'Livewire 4 مقابل Alpine.js ... wire:navigate'. Line 61 mentions Livewire components. Line 70 lists Livewire 4 and Breeze. The targets exist locally, but the index omits system-architecture-master.md, docs/modules, docs/pages, docs/reviews, desktop-architecture.md, docs/04-testing and the saas/pricing docs.
- **Impact:** Links break on GitHub and on other machines. Readers are pointed at the stale docs and not the maintained ones.
- **Recommendation:** Use relative links, list the current docs first, and mark the 01/02/03/05/06 legacy docs as historical.

### 59. [LOW] Root README.md is the stock Laravel boilerplate
- **Location:** `README.md:1`
- **Effort:** S
- **Evidence:** The file is the unmodified 'About Laravel' / Laravel Boost / Taylor Otwell security-contact text. It says nothing about the product, the stack or setup. AI_START.md carries the real quick start.
- **Impact:** The repo landing page tells a new engineer or buyer nothing and points security reports at Laravel's maintainer.
- **Recommendation:** Replace it with a short project README linking AI_START.md, CLAUDE.md and docs/README.md.

### 60. [LOW] e2e-testing-guide documents the legacy Python/pytest Livewire suite, not the Playwright JS suite in e2e/
- **Location:** `docs/04-testing/e2e-testing-guide.md:5`
- **Effort:** M
- **Evidence:** Line 5 says 'Python 3.13 + Playwright + Pytest + ... (Livewire 4 + Alpine.js)'. Line 22 covers Livewire/wire:confirm handling, and the commands use `python run_all_tests.py`. tests_e2e/helpers.py:32 defines `wait_for_livewire`. The real suite is e2e/ (auth, flows, crawlers) with playwright.config.js projects auth-setup/desktop/tablet/mobile, run via `npm run e2e:*` in backend/package.json.
- **Impact:** The testing guide steers people to a legacy suite that targets removed UI. The real suite is documented only in scattered page docs.
- **Recommendation:** Rewrite it for e2e/ and the npm scripts, and mark tests_e2e/ and run_all_tests.py as legacy (or delete them, if the owner agrees).

### 61. [LOW] Overview, requirements, planning and skills docs present Livewire/Blade/Alpine/Breeze/Flowbite/PWA and 'no API layer' as the stack
- **Location:** `docs/01-overview/project-overview.md:44`
- **Effort:** M
- **Evidence:** project-overview.md:29-37 (Livewire, Blade, Alpine, Flowbite, Breeze, PWA), :44 'لا توجد طبقة API Layer منفصلة', :58-70. glossary.md:71-73. functional-requirements.md:25. non-functional-requirements.md:11. required-skills.md:12-15,35. phases-roadmap.md:39,45. selected-features-implementation-plan.md:30-108 (app/Livewire/* paths). tasks-breakdown.md:10-31. testing-plan.md:14,20,63 (LivewirePagesTest). ui-design-system.md:9 (Flowbite) and :101 (Alpine Toast). No flowbite or breeze in package.json or composer.json. AGENTS.md:202 still tells the Docs role to maintain tasks-breakdown.md.
- **Impact:** These are mostly historical planning docs, but they are not labelled as such and the index presents them as current. The product overview and requirements describe a single-store coffee MVP, not the generic multi-tenant SaaS.
- **Recommendation:** Add a 'historical — superseded' banner to the 05-planning and 06 required-skills files. Rewrite project-overview, glossary and the requirements for the SaaS product. Drop the tasks-breakdown duty from AGENTS.md:202.

### 62. [LOW] docs/NATIVEPHP_MOBILE_V4_REFERENCE.md is obsolete
- **Location:** `docs/NATIVEPHP_MOBILE_V4_REFERENCE.md:1`
- **Effort:** S
- **Evidence:** The 102-line NativePHP Mobile v4 'SuperNative'/EDGE playbook for 'سرور كوفي ERP Mobile'. No nativephp package is in composer.json, package.json or config/. No non-history doc links to it. Mobile is Capacitor ^8.5 (backend/android, capacitor.config.json).
- **Impact:** Misleading if an AI or engineer finds it while working on mobile.
- **Recommendation:** Delete it or move it under docs/history/ with a superseded note. Add a short Capacitor/Electron mobile and desktop doc (desktop-architecture.md already exists).

### 63. [LOW] Rules describe Pipeline filters and Observers as pervasive, but they barely exist
- **Location:** `CLAUDE.md:32`
- **Effort:** S
- **Evidence:** CLAUDE.md:32,64 and AGENTS.md:31-34 describe `app/Filters/<Domain>/` Pipeline filters and Observers for audit and side effects. Reality: app/Filters only has Tenants/{PlanFilter,SearchFilter,StatusFilter}, Pipeline is used in a single Action (GetTenantsIndexDataAction), and app/Observers only has TenantObserver. Audit logging goes through AuditLogService/ActivityLogService.
- **Impact:** Small: the rules are aspirational. CLAUDE.md's legacy warning covers this partly, but the repo map implies these layers already exist.
- **Recommendation:** In the repo map, label Filters as 'Tenants only so far' and audit logging as service-based.

### 64. [LOW] Root package.json scripts target non-existent mobile/ and tests/e2e/specs
- **Location:** `package.json:5`
- **Effort:** S
- **Evidence:** `dev:mobile`, `build:mobile` → `npm --prefix mobile`, and `test:fresh`/`test:auth`/`test:pos`... → `tests/e2e/specs/*.spec.js --project=Mobile-Pixel-7`. Neither mobile/ nor tests/ exists. Playwright projects are auth-setup/desktop/tablet/mobile. The documented commands (CLAUDE.md:56, AI_START.md:56) are the working backend/package.json ones.
- **Impact:** Root `npm run dev` and `npm run build` fail and confuse newcomers. These are leftovers of a removed separate mobile app.
- **Recommendation:** Remove the dead root scripts, or point them at backend/ (config change, outside docs scope).

### 65. [LOW] Central billing money columns use DECIMAL(10,2), against the 'all money DECIMAL(12,3)' rule
- **Location:** `backend/database/migrations/2019_09_15_000005_create_plans_and_features_tables.php:17`
- **Effort:** S
- **Evidence:** plans.price_monthly and price_yearly are decimal(10,2) (lines 17-18). subscriptions.amount is decimal(10,2) (2019_09_15_000015 line 18). The other 59 money/qty columns are decimal(12,3). CLAUDE.md rule 1, AGENTS.md:25 and money-stock-integrity.md:16 state the rule with no exception.
- **Impact:** Small. Two decimals are fine for subscription prices, but the rules and the schema disagree, and a reviewer will either flag false positives or 'fix' it inconsistently.
- **Recommendation:** Write down the exception (central billing currency = 2 dp) in money-stock-integrity.md, or align the columns.

### 66. [LOW] شجرة وثائق مكررة في backend/docs وe2e مكرر في backend/e2e
- **Location:** `backend/docs`
- **Effort:** S
- **Evidence:** `git ls-files backend/docs` بيرجع 290 ملف، و`diff -rq docs backend/docs` طلع 79 اختلاف (فيهم pages وhistory). backend/e2e/flows فيه 10 specs، والجذر e2e/flows فيه 39، و`playwright.config.js` بيشاور على الجذر.
- **Impact:** ممكن يتعدّل المصدر الغلط، والتاريخ والوثائق يبقوا متضاربين.
- **Recommendation:** احذف `backend/docs` و`backend/e2e` بعد ما تتأكد إن كل محتواهم موجود في الجذر، أو حوّلهم لأرشيف واضح.

### 67. [LOW] مسار الطباعة الاحتياطي في useNativeBridge بيفتح URL مش موجود
- **Location:** `backend/resources/js/Composables/useNativeBridge.js:116`
- **Effort:** S
- **Evidence:** `window.open(`/invoices/${id}/print-thermal`)`، والمسارات الموجودة هي `/invoices/{id}/print/thermal` و`/invoices/{id}/print`.
- **Impact:** على الموبايل لما plugin الطابعة مش موجود، الطباعة بتفتح صفحة not-found.
- **Recommendation:** غيّره لـ `/invoices/${id}/print/thermal`.

### 68. [LOW] testing-plan.md بيعلّم البنود غير منجزة وفي نفس السطر بيقول إنها اتحققت
- **Location:** `docs/05-planning/testing-plan.md:262`
- **Effort:** S
- **Evidence:** `- [ ] كافة الـ 76 اختبار PHPUnit ناجحة 100% ← ✅ محقق`، والسطور 263-269 كمان غير معلّمة. العدد الحالي حوالي 306 test method (grep)، و`run_all_tests.py` مذكور في السطر 266 كأداة.
- **Impact:** مش واضح إيه اللي اتحقق فعلًا.
- **Recommendation:** حدّث الأرقام وحالة كل بند بعد تشغيل `php artisan test` فعليًا، وسجّل النتيجة في history.

### 69. [LOW] تغييرات معمارية كبيرة بعد 2026-08-26 ملهاش سجلات history
- **Location:** `docs/history`
- **Effort:** S
- **Evidence:** آخر مجلد في `docs/history` هو 2026-08-26. من 2026-08-27 فيه 17 commit، منهم `refactor: remove Inertia entirely` و`chore: drop remaining Livewire configuration leftovers` و`fix(vue/backend): ... central store isolation`، ومفيش ولا واحد منهم ضاف ملف تحت `docs/history`.
- **Impact:** قرارات إزالة Inertia وعزل الـ store المركزي مش متوثقة، ومحدش هيعرف ليه اتعملت.
- **Recommendation:** اكتب سجلات history بأثر رجعي من `git show` مع "غير موثّق" لأي سبب أو فحص مش ظاهر في الأدلة.

### 70. [LOW] [SHOULD-HAVE] Feature registry still has coffee-specific and stack-stale entries
- **Location:** `backend/database/seeders/PlansAndFeaturesSeeder.php:25`
- **Effort:** S
- **Evidence:** `blender.access` is described as 'استوديو توليف وخلاط البن', and plan descriptions mention 'المطاحن'. `api.access` mentions NativePHP/Flutter, while the real mobile app is Capacitor.
- **Impact:** A generic retail, wholesale or by-weight SaaS shows coffee terminology to non-coffee customers.
- **Recommendation:** Rename the feature to a generic 'mixing/recipe/bundles' key, or make it an industry-preset add-on. Fix the descriptions and translations.

### 71. [LOW] The POS stock shown falls back to company-wide stock when an item has no row for the branch
- **Location:** `backend/app/Actions/POS/GetPOSBootstrapDataAction.php:64`
- **Effort:** S
- **Evidence:** The query uses COALESCE(store_stocks.quantity, items.current_stock) as calculated_stock. Meanwhile StockService::deductStock (lines 59-67) locks the store_stocks row and throws if the branch quantity is not enough.
- **Impact:** A branch with no stock row shows the global quantity as available. The sale then fails at checkout with an 'insufficient stock' error, which confuses cashiers.
- **Recommendation:** Use COALESCE(store_stocks.quantity, 0) whenever a store is resolved.

### 72. [LOW] Coffee naming and data are still in keys, seeders, the frontend and docs
- **Location:** `backend/database/seeders/PlansAndFeaturesSeeder.php:25`
- **Effort:** M
- **Evidence:** PlansAndFeaturesSeeder:25 and :143 ('المطاحن', 'استوديو خلط البن'). RealisticEnterpriseDataSeeder:126-172 (bun turki items, 'حبهان' category). Translation keys coffee_blend_btn, coffee_blender, roast_type and roast_* remain, although the Arabic values are now generic (lang/ar/inventory.php:114-160). Remaining code names: CoffeeBlenderController, the useCoffeeBlender composable, the Components/CoffeeBlender folder, the /coffee-blender route, docs/pages/coffee-blender.md (90 lines) and the brochure. docs/generalization-review-log.md says the generalization is 'COMPLETED', but that only holds for the visible string values.
- **Impact:** Plan cards seen by super-admins and prospects still advertise a coffee feature. Demo data is coffee-only. Every developer sees the coffee naming.
- **Recommendation:** Rename the plan feature texts now (cheap). Then rename the keys, routes and classes to assembly or bom, with a route alias for old mobile builds. Mark generalization-review-log as partial.

### 73. [LOW] stores.type is only a label, with inconsistent values across layers
- **Location:** `backend/app/Services/TenantProvisionerService.php:94`
- **Effort:** M
- **Evidence:** The migration comment lists retail_shop / wholesale_van / main_warehouse. TenantProvisionerService creates the main store with type 'retail'. StoresGrid.vue:29 checks 'van' and 'warehouse'. StoreStoreRequest:21 only validates 'string'. The only behaviour is icons and labels (ReportPrintController:236, TelegramService:182) plus scopes in Store.php:41-51 that nothing calls.
- **Impact:** A warehouse can sell through POS, and a van has no load/settle workflow. Icons are wrong for new tenants. The type gives none of the wholesale or warehouse behaviour the brochure implies.
- **Recommendation:** Turn the type into a validated enum (Rule::in) and fix the provisioner and grid values. Then decide on behaviour: for example, block POS on main_warehouse and add van load/unload settlement.

### 74. [LOW] The demo-data command writes columns that do not exist (price_retail, price_wholesale, credit_limit)
- **Location:** `backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php:298`
- **Effort:** S
- **Evidence:** Item::create receives 'price_retail' and 'price_wholesale' (lines 298-299). These are appended accessors and not fillable, so they are discarded. Line 487 then reads $it->price_wholesale, which returns min_selling_price. Customer::create receives 'credit_limit' at line 352, which is also discarded.
- **Impact:** The demo data does not model the wholesale prices it claims to, which hides the pricing-model gap during demos and QA.
- **Recommendation:** Fix the command once real wholesale-price and credit-limit columns exist, or remove those keys now.

### 75. [LOW] The Electron cash-drawer kick sends no ESC/POS bytes
- **Location:** `desktop/src/hardware/cashDrawer.js:8`
- **Effort:** M
- **Evidence:** kickDrawer's comment says 'ESC p 0 25 250'. The code actually prints an HTML div containing a transparent '.' through printThermalSilent. No raw bytes are sent.
- **Impact:** Most drawers that open through the printer need the raw pulse. This method may only open drawers that are set to open on any print job, and it wastes a small strip of paper each time.
- **Recommendation:** Send raw ESC/POS through a raw-printing path (for example a node-thermal-printer or raw spool library). Keep the HTML print for receipts.

### 76. [LOW] Dead helpers and schema drift in the activity log layer
- **Location:** `backend/app/Services/ActivityLogService.php:114`
- **Effort:** S
- **Evidence:** logInventory (114), logExpense (150), logAuth (171) and logSystem (186) have no callers anywhere in app/. The badge and label maps in ActivityLog.php reference actions that are never emitted (item_price_changed, expense_created, expense_deleted, customer_created, customer_updated, settings_updated). The central and tenant activity_logs migrations share a filename but differ: central has an unsignedBigInteger store_id, tenant has a foreignId constrained to stores. The central table is only written by SuperAdminLoginAction and login attempts.
- **Impact:** Gives a false impression that expenses, customers and settings are audited. Maintenance confusion.
- **Recommendation:** Wire the helpers in when fixing coverage, or delete them. Rename one of the two same-named migrations to avoid confusion.

### 77. [LOW] Four translation-audit docs overlap, contradict each other, and describe the deleted Inertia stack
- **Location:** `docs/TRANSLATION_AUDIT_REPORT.md:11`
- **Effort:** S
- **Evidence:** TRANSLATION_AUDIT_REPORT.md, VUE_DASHBOARD_TRANSLATION_AUDIT.md, VUE_FULL_TRANSLATION_AUDIT.md and BACKEND_TRANSLATION_AUDIT.md were all committed 2026-08-20 and all claim 100%. They reference paths that no longer exist: resources/js/Pages/Dashboard.vue, app/Http/Middleware/HandleInertiaRequests.php, app/Http/Controllers/DashboardController.php, and the subtitle 'Backend Vue 3 Inertia App'. The counts disagree (58 files vs 48 Vue screens; BACKEND claims 36 lang files, but lang/ has 25 per locale plus ar.json and en.json). Hardcoded Arabic still exists in code, e.g. PosView.vue:764-785, ActivityLog.php label maps, the ForceDeleteTrashRecordAction exception and the ActivityLogController fallbacks. docs/i18n-review-log.md (written after the Inertia removal) is the newer, overlapping record.
- **Impact:** Readers get stale and conflicting status. The '100%' claims hide real i18n debt in a product being sold bilingually.
- **Recommendation:** Consolidate into docs/i18n-review-log.md as the single live i18n record. Move the four 2026-08-20 files to an archive folder or delete them, with a one-line pointer. Re-measure hardcoded strings with a script, not by assertion.

### 78. [LOW] A feature test makes a real outbound Telegram call
- **Location:** `backend/tests/Feature/Api/SettingApiTest.php:152`
- **Effort:** S
- **Evidence:** test_can_send_test_telegram_notification posts to /api/v1/settings/telegram/test with no Http::fake(). SettingController::sendTestTelegram calls TelegramService::sendTestNotification. phpunit.xml has no Telegram overrides (grep: no matches). The 2026-09-21 review (line 299) reports that shift tests sent real alerts.
- **Impact:** Tests depend on the network and can leak test messages to a real chat if a real token is in .env.
- **Recommendation:** Call Http::fake() in TestCase::setUp and set TELEGRAM_* to dummy values in phpunit.xml.

### 79. [INFO] No history logs for the Livewire and Inertia removal refactors
- **Location:** `docs/history`
- **Effort:** S
- **Evidence:** The newest history folder is 2026-08-26, plus one 2026-08-29 entry added in 9fa5797b. The commits 210dd887 'drop remaining Livewire configuration leftovers' (2026-09-15), 9b08be27 'remove Inertia entirely' (deleted the 796-line AppLayout.vue, changed routes/api.php and tenant.php), 1b2f71f0 and 72947829 have no history entry.
- **Impact:** There is no record of why routes changed or what was removed during the stack cutover, and that is exactly what makes the stale docs misleading.
- **Recommendation:** Add a retrospective history log for the stack cutover. Its date and rationale should say 'غير موثّق' where there is no evidence.

### 80. [INFO] AGENTS.md names the production deploy host
- **Location:** `AGENTS.md:58`
- **Effort:** S
- **Evidence:** AGENTS.md:58 names the production domain the deploy script targets. .claude/rules/security-and-operations.md names only the script, not the host.
- **Impact:** A small information disclosure in a tracked file, and a drift from the rules mirror.
- **Recommendation:** Remove the hostname from AGENTS.md and keep only the script reference.

### 81. [INFO] مصفوفة done/partial/missing لمراحل التخطيط مقارنة بالكود
- **Location:** `docs/05-planning/tasks-breakdown.md`
- **Effort:** M
- **Evidence:** منفّذ (route + service/action + Vue + test): المراحل 1-3 (items وcustomers وsuppliers وpurchases وinvoices وstock والخدمات `StockService` و`PurchaseService` و`InvoiceService` و`ProfitService` و`CustomerBalanceService` و`ReturnService` و`PaymentService`، والـ views موجودة، والاختبارات `InvoiceServiceTest` و`PurchaseServiceTest` و`ReturnServiceTest` و`CustomerBalanceTest` و`*ApiTest`). المرحلة 5 multi-store (stores وstore_stocks وtransfers و`StockTransferService` و`StoreAccess` و`MultiStorePhase1/2Test`). المرحلة 10 تسوية المخزون (`adjust-stock` و`StockAdjustmentFeatureTest`) وإلغاء المشتريات (`PurchaseCancelAndRestoreFeatureTest`). المرحلة 11 additional_expenses (migration وmodel وDTOs). المرحلة 14 smart-reorder (route وview وservice). المرحلة 15 activity logs والإشعارات (`SpaLayout.vue:120`) وcache الـ 15 دقيقة (InventoryAnalyticsService:31 وProfitLossService:31). PWA (`manifest.json` و`sw.js`). قوالب الطباعة الأربعة في `resources/views/layouts`. جزئي: استعادة المشتريات (service فقط)، والتصدير (CSV فقط)، والفواتير المعلقة (localStorage)، وP&L الفروع (`ProfitLossService` بيستخدمه `ReportPrintController` بس، ومفيش API)، وABC (طباعة وreports/inventory)، والتزامن (test متسلسل)، والنسخ الاحتياطي (أمر `backup:telegram` في جدولة console.php:37، ومسارات الواجهة مكسورة)، و`StoreScope` (middleware لاختيار الفرع، مش Eloquent scope زي ما الوثيقة بتقول). ناقص: التحويل بين الخزن (API وUI)، وXLSX وPDF، والباركود، وتعدد الوحدات، والضرايب، واختبارات Unit للخصومات، والاختبارات المسمّاة في الوثائق. وكل الحاجات دي مش متابعة في أي ملف تخطيط: signup وbilling وتطبيق حدود الباقات وإنشاء باقة (api.php:207-208 فيه index وupdate بس) وتنفيذ الاشتراك. مهام [ ] اتنفذت فعلًا: مفيش أي [ ] في ملفات التخطيط التلاتة. صفحات الـ router (39) قصاد docs/pages (36): الصفحات اللي ملهاش توثيق هي `/connect` و`/login` و`/invoices/:id/print`. وإعدادات المنصة (`/super-admin/settings` API) ملهاش view مستقل، وموجودة جوّه `useSuperAdminDashboard.js:42`.
- **Impact:** دي المرجع لإعادة بناء roadmap حقيقي.
- **Recommendation:** استخدم المصفوفة دي كأساس لملف `docs/05-planning/roadmap-saas.md`، واكتب سجل history للتغيير.

## Open questions
- The AGENTS.md injected into this session is the 2026-08-08 Livewire version, not the file on disk. Which tool or config loads it: a cached copy, the main checkout, or a .claude/worktrees copy? Until fixed, sessions get harmful guidance.
- Was the WIP commit 76f32ce0 ('epitaxy pre-switch') made by a tool on purpose? Does the owner want the backups/ CSV/PDF cost-audit artefacts kept anywhere (outside git)?
- Is the git remote for this repo private? That decides whether a push of the dumps is critical or 'only' high.
- Should docs/01-overview, 02-requirements and 05-planning be rewritten for the generic multi-tenant SaaS, or frozen with a 'historical' banner while a new product/requirements doc is written?
- Should the legacy tests_e2e/ Python suite and run_all_tests.py be retired in favour of e2e/ (Playwright JS)?
- None of the '7/7 ناجحة' test claims in system-architecture-master.md could be checked, because no test run was performed in this read-only analysis.
- هل دومينات المستأجرين (InitializeTenancyByDomain في tenant.php) شغالة في الإنتاج؟ لو أيوه، مسارات الطباعة غير المحمية مكشوفة للإنترنت دلوقتي. ولو الـ SPA شغال على الدومين المركزي بس، يبقى فيه سؤال تاني: مسارات web.php غير المحمية (print/reports/export-csv) بتقرأ من أي قاعدة على الدومين المركزي؟ وهل فيها بيانات legacy حقيقية؟
- مافيش أي تأكيد إن 140+ أو 306 اختبار شغالين بنجاح دلوقتي. مشغلتش `php artisan test` لأن اختبارات tenancy ممكن تعمل ملفات sqlite في backend/database، وده خارج حدود التشغيل للقراءة فقط.
- هل `min_selling_price` المقصود بيه سعر الجملة ولا حد أدنى إلزامي للبيع؟ القرار ده بيحدد هل فيه bug ولا الوثائق بس هي الغلط.
- هل التحويل بين الخزن واستعادة فواتير الشراء اتشالوا من الواجهة عن قصد وقت الانتقال لـ Vue، ولا اتنسوا؟
- هل backend/docs وbackend/e2e نسخ قديمة نقدر نحذفها، ولا فيها محتوى مش موجود في الجذر؟ فيه 79 ملف مختلف بين الشجرتين.
- Is the `super_admin` role or `super_admin.access` permission actually present in production tenant DBs (were tenants provisioned through PermissionsSeeder)? This decides whether the escalation in finding 1 can be used right now. I verified it from code only and did not run it against any environment.
- Which pricing structure is the commercial truth: seeder (4 tiers), SaaS doc (3), or study/brochure (6)? Are add-ons (extra branch/user/van, WhatsApp packs) meant to be billed through the system or manually?
- What data-retention policy should apply on cancellation (read-only period, export, purge after N days)? No policy is documented and DeleteTenantAction is broken.
- Is ApiTokenAuth step 3 (a central admin token mapped to a tenant user by phone, app/Http/Middleware/ApiTokenAuth.php ~L50-60) intended as the super-admin impersonation mechanism? It ties authorization to phone-number equality across DBs and deserves a separate security review.
- Should coffee-blender become a generic 'recipes/mixing' feature, or an industry-preset add-on gated by `blender.access`?
- Is the price-tier toggle meant to be a manual cashier choice, or should it follow customers.price_tier automatically? This decides whether the server must enforce tier prices.
- Should the coffee blender stay as a paid add-on ('blender.access') or be replaced by a generic bill-of-materials/kit module? The rename scope depends on it.
- Which target markets come first (Egypt ETA, KSA ZATCA, or no tax)? That sets the priority of the VAT and e-invoice work.
- Should main_warehouse stores be blocked from POS, and do wholesale vans need a load/settle cycle?
- Is the tenant API group running a session store (no StartSession on the api group)? The current_store_id session writes in ApiTokenAuth appear to live only for one request. That makes the X-Store-Id header the effective store selector, so the access check described above matters even more.
- Are PosView.legacy.grid.vue and usePOSCart.js intentionally kept? They are the only places where customer price_tier changed the price automatically.
- What does the production central database contain? If it is the legacy single-store Sroor DB with items, customers and invoices tables, the unauthenticated web.php export routes are a live data leak, not just a 500.
- Which CACHE_STORE does production use? With CacheTenancyBootstrapper disabled (config/tenancy.php:36) and the global 'spatie.permission.cache' key, a non-database store (file or redis) would share the permission cache across tenants. With the 'database' store, each tenant DB's cache table isolates it.
- Do tenant databases have any backup beyond the hosting provider? The in-product scheduler only dumps the central DB.
- Is the activity log meant to be the single audit trail, so audit_logs can be retired, or does the product need old/new value diffs exposed to tenants?
- Do any of the 117 committed root scripts contain credentials in the current tree or in git history? I did not open them, per instructions.