# Testing & QA — Score 3/10

> Branch `feature/multi-tenant` · 2026-10-07 · read-only multi-agent analysis · secrets redacted

## Executive summary

Score: 3 out of 10. The SaaS branch is not ready for sale from a testing point of view. The suite is large and mostly consistent: 306 PHPUnit tests, 305 passing, 1389 assertions, and 123 of 150 in-scope API routes covered. But it lets the most dangerous problems through, and in several places it locks them in as correct behaviour.

1. **Account takeover without a password.**
   - `/api/v1/auth/quick-login` is public and has no rate limit. It hands out a full-access token for any user found by phone, email or numeric id (`backend/app/Actions/Auth/ApiQuickLoginAction.php:26`). The tenant is chosen by an `X-Tenant` header the caller sets.
   - `/auth/workspace-users` is also public and lists every user's id and phone.
   - `AuthApiTest.php:287` (`test_quick_login_succeeds_without_password`) asserts this as the expected behaviour.

2. **A tenant user can become platform super-admin, in three independent ways.**
   - Set your own phone (via `/profile`) or a new user's phone (via `/users`) to one of two hard-coded numbers that `Gate::before` always allows (`AppServiceProvider.php:37`).
   - Create a user with role `super_admin`. That role is seeded into every tenant DB.
   - Sync the tenant `admin` role to `Permission::all()`, which includes `super_admin.access` (`UpdateRolePermissionsAction.php:22`). This one rests on the order Laravel boots its providers and was not confirmed by running a test.
   - The `/super-admin/*` routes accept tenant context, so any of these lets one tenant suspend or delete other tenants, change their DB config, and publish a malicious APK. No test covers any of these paths. `ProfileApiTest` even uses a privileged phone as its normal fixture.

3. **Tenant isolation is essentially untested.**
   - No test sends `X-Tenant` or calls `tenancy()->initialize`.
   - `tests/TestCase.php:13-15` migrates the tenant tables into the same single sqlite DB, so tests never run the one-DB-per-tenant setup production uses.
   - Suspended or expired tenants are not blocked on `/api/v1` (`ResolveApiTenancy.php:30`), so billing is not enforced.

4. **Money and stock defects that existing tests either work around or never assert.**
   - Customer and supplier opening balances are overwritten by the first invoice, payment or return (`CustomerBalanceService.php:16`). `CustomersApiTest` seeds a fake invoice that hides this, and `ReturnServiceTest` never checks the balance.
   - Closing a shift counts every cash sale twice and pulls in other branches' payments (`ShiftService.php:81`).
   - A branch with no `store_stocks` row can sell stock held in other branches (`StockService.php:64`). `InvoiceServiceTest` depends on this.
   - Deleting, restoring or force-deleting a return does not reverse its stock or balance effect, and force-delete physically removes a financial document (`DeleteReturnAction.php:15`).

5. **Structural limits of the test setup.** sqlite `:memory:` cannot check `lockForUpdate`, DECIMAL(12,3) rounding or deadlocks. The provisioning test writes to an on-disk tenant sqlite file.

**What is solid:**
- bcmath at scale 3 and `decimal:3` casts throughout, with exact string assertions.
- Transactions and locks on stock and cancel paths.
- Good purchase cancel/restore and stock transfer tests.
- 401/403 tests on most endpoints.
- A Playwright setup pointed at local hosts.

The scaffolding is a good base to build on. What is missing is negative, cross-tenant and money-invariant tests, and the critical auth bugs have to be fixed before any tenant is onboarded.

## Top risks
- CRITICAL: Anyone can get a full-access token with no password. POST /api/v1/auth/quick-login (routes/api.php:28; backend/app/Actions/Auth/ApiQuickLoginAction.php:26-61) finds the user by phone OR email OR id, checks no password, has no throttle, and also stores the plaintext token in users.api_token. /auth/workspace-users (AuthController.php:93-110) is public and lists every user's id, name and phone. The caller picks the tenant through X-Tenant. tests/Feature/Api/AuthApiTest.php:287 asserts this as the happy path. (Two sub-agent findings merged here.)
- CRITICAL: A tenant user can become platform super-admin. Gate::before (backend/app/Providers/AppServiceProvider.php:37-45) allows the super_admin role or two hard-coded phone numbers (a hardcoded phone-number allowlist; values not reproduced). The same list is also hardcoded in OverrideTenantFeatureRequest, ToggleTenantStatusRequest, UserResource, TelescopeServiceProvider and routes/web.php. Any user can change their own phone through PUT /profile (UpdateProfileRequest.php:14,23; UpdateProfileAction.php:26); phone uniqueness is only checked inside the tenant DB. A tenant admin can also create a user with role super_admin (StoreUserRequest:23, CreateUserAction:29), because PermissionsSeeder.php:70 seeds that role into every tenant DB (TenantProvisionerService:87). The /super-admin/* routes (routes/api.php:196) run in tenant context and act on the central Tenant and AppVersion models: suspend, delete or reconfigure other tenants, run their migrations, push an APK. Plan and platform-setting writes do NOT reach central data through this path. No test covers it.
- CRITICAL (inferred): Syncing the admin role grants super_admin.access. backend/app/Actions/Roles/UpdateRolePermissionsAction.php:20-22 syncs the tenant 'admin' role to Permission::all(), which includes super_admin.access. UpdateRolePermissionsRequest only checks exists:permissions,name, so any roles.manage holder can also grant it to any other role. Spatie's Gate::before probably runs before the AppServiceProvider deny callback. This is inferred from the order Laravel boots its providers and was not proven by running a test. RoleApiTest only touches the cashier role.
- CRITICAL: Opening balances are silently lost. CreateCustomerAction.php:24 and CreateSupplierAction.php:24 store the opening balance only in current_balance. CustomerBalanceService.php:16-44, SupplierBalanceService.php:17-48 and ReturnService.php:164-170 then recompute that field from scratch on every invoice, payment, purchase or return, so the first transaction erases the opening debt for good. The ledgers also start at 0.000. CustomersApiTest.php:205-251 seeds a fake invoice that hides the bug. ReturnServiceTest passes a non-fillable initial_balance and never asserts the balance; the real results would be -120 and -1250, not 380 and 750.
- HIGH: Tenant and store isolation is untested, and suspended tenants are not blocked. ResolveApiTenancy.php:30-37 starts any tenant named in X-Tenant, ?tenant= or the request body without checking status or subscription; Tenant::isSuspended is checked only in ResolveTenantWorkspaceAction.php:48. No test sends X-Tenant. tests/TestCase.php:13-15 puts tenant tables in one shared sqlite DB, so the one-DB-per-tenant setup is never exercised. Only 2 tests assert a 404, and no test checks another store's data.
- HIGH: Shift close counts cash sales twice. ShiftService::calculateShiftTotals (backend/app/Services/ShiftService.php:81-111) adds both the paid_amount of cash invoices and the Payment rows that confirmInvoice creates for the same money (InvoiceService.php:241-251). Payments have no store_id, so other branches' cash leaks into every shift. Every return is subtracted as a cash refund. The result is a false shortage on every shift and a Telegram alert. Closing is not lock-safe (the status check in CloseShiftAction:23 has no lockForUpdate). The z-report tests use pre-seeded totals and never run the calculation.
- HIGH: Branch stock check is skipped when the branch has no stock row. StockService::deductStock (backend/app/Services/StockService.php:64-73) skips the per-branch check and deduction when no store_stocks row exists; PurchaseService.php:273 has the same gap. CreateStoreAction creates no stock rows, so any branch added after items exist can sell the whole tenant's stock. The POS screen shows the master total for such branches (GetPOSBootstrapDataAction:64). InvoiceServiceTest's multi-store numbering test relies on this behaviour.
- HIGH: Deleting a return leaves its effects in place. backend/app/Actions/Returns/DeleteReturnAction.php:15-17 only soft-deletes: no transaction, no stock reversal, no balance recalculation, no audit entry. Restoring or force-deleting from the trash (RestoreTrashRecordAction:28, ForceDeleteTrashRecordAction:28) does no reversal either, and force-delete physically removes a financial document, which breaks the no-hard-delete rule. ReturnsApiTest.php:251-277 only asserts assertSoftDeleted.
- MEDIUM (structural): The test setup cannot check locking or decimal behaviour. With sqlite :memory:, lockForUpdate does nothing and DECIMAL(12,3) truncation and deadlocks are never exercised. There is no MySQL CI job. The tenant provisioning test writes to the on-disk database/tenant_wadi-elbon.sqlite, so it is not hermetic. The baseline has 1 failing test.

## Quick wins
- Replace AuthApiTest::test_quick_login_succeeds_without_password with tests that must fail today: quick-login by id is rejected, quick-login without a credential issues no token (personal_access_tokens count unchanged), workspace-users returns 401 without auth, and both endpoints are throttled. Then remove or disable the routes at routes/api.php:28-29 until a device-bound PIN design exists.
- Add escalation tests: (a) PUT /profile with a reserved phone, then GET /super-admin/dashboard must return 403; (b) POST /users with role=super_admin in tenant context must return 422; (c) PUT /roles/{admin}/permissions must not give the admin role super_admin.access, and no role may be granted super_admin.*; (d) any /super-admin/* call with X-Tenant must return 403 or 404.
- Remove the hard-coded phone bypass from AppServiceProvider.php:37 and the five other files that copy it. Rely on the central super_admin role only. Swap the privileged phone fixture in ProfileApiTest and the e2e fixtures for a neutral number.
- In UpdateRolePermissionsAction.php:20-22, filter super_admin.* out of every sync, both in the request and for the admin role, instead of syncing Permission::all(). Exclude super_admin in StoreUserRequest and UpdateUserRequest.
- Add a guard that rejects /super-admin/* when tenancy()->initialized is true, plus one test for it.
- Write failing money-invariant tests to hand to backend-architect: opening balance 2500 plus a 100 credit invoice must give current_balance '2600.000' (customer and supplier); one cash sale then a close with actual = opening + sale must give cash_difference '0.000'; selling 1 from a branch with no store_stocks row must throw with all quantities unchanged; deleting a sales return of 1.000 must restore the exact previous stock and balance.
- Remove the fake-invoice workaround from CustomersApiTest.php:205-251. Fix ReturnServiceTest to use fillable fields and assert the final balances. Seed per-store stock in the multi-store numbering test in InvoiceServiceTest.
- Add a suspended-tenant check to ResolveApiTenancy (403/402 when isSuspended or the subscription has expired), plus a test with a suspended tenant against /api/v1/items.
- Make the provisioning test hermetic: use a temp directory instead of database/tenant_wadi-elbon.sqlite, and fix the one failing test so CI starts green.

## Strategic items
- Build a real multi-tenant test harness: two tenants with separate sqlite files or MySQL schemas, initialized through X-Tenant, subdomain and slug. Then add a TenancyIsolationTest that checks: a token from A with X-Tenant B is rejected, A's data is invisible from B, an unknown tenant gives 404, a suspended tenant is blocked, and a central-context request never reaches ERP data.
- Add store-isolation tests for each store-scoped resource (invoices, items, customers, transfers, returns, shifts, payments): show, update and delete another store's record must return 404 or 403. Add store_id to payments so shift and treasury reports can be scoped.
- Add a MySQL 8 CI job, using the same engine as production, to run the money and stock suites. That is the only way to verify lockForUpdate, DECIMAL(12,3) rounding, deadlock retry and concurrent oversell. Add parallel-request concurrency tests for deductStock and invoice numbering.
- Add balance-ledger invariant tests: after any sequence of invoices, payments, returns, cancels, deletes and restores, current_balance must equal opening + sum of ledger entries, and item stock must equal the sum of stock_movements. Use them as regression guards across all services that recompute balances.
- Redesign auth for SaaS: a device-paired PIN or terminal secret for fast POS login, central-only super-admin identities kept apart from tenant users, impersonation through the existing tenant_user_impersonation_tokens table, and no plaintext api_token column. Back each piece with negative tests.
- Add a security-focused test layer to CI: route-inventory tests that fail when a route outside ApiTokenAuth is not on an allowlist, when a route points to a missing controller method (15 known), or when a write route has no 403 test. Add a mutation-testing pass (for example Infection) on Services to find assertions that never fail.
- Bring E2E into the release gate: run the Playwright flow specs against a freshly provisioned tenant in CI, including a two-tenant login and switch scenario, RTL and dark-mode snapshots, and the printable invoice route.

## Strengths
- The suite is large and mostly green: 306 PHPUnit tests and 1389 assertions, with 305 passing and 1 failing for a known reason. 123 of 150 in-scope API routes are covered.
- Test files follow a consistent pattern (RefreshDatabase, tenant migrations, PermissionsSeeder, admin and unprivileged users, Sanctum bearer tokens) with typed void methods and strict_types, which makes new tests cheap to write.
- Most index endpoints have 401 and 403 tests, and many write tests assert DB state with exact decimal strings ('0.250', '1.000', '49.625'). The decimal:3 casts make those true string comparisons.
- Money and stock arithmetic is disciplined. Services use bcmath at scale 3. deductStock, addStock, cancelInvoice, cancelPurchase, restorePurchase and cancelTransfer run inside DB::transaction with lockForUpdate and reject double cancels.
- Good scenario coverage in places: PurchaseCancelAndRestoreFeatureTest (exact stock, balance and movement ledger), MultiStorePhase2Test (exact per-store transfer and cancel quantities), adjustStock surplus/deficit/no-op, invoice numbering with soft-delete collisions, and fractional-weight sales with Arabic fixtures.
- Super-admin and resolver tests (SuperAdminApiTest, SuperAdminSolidTest, CentralTenantResolverApiTest) already initialize tenancy and fake events. That is a starting point for real multi-tenant tests, and the resolver already returns 403 for suspended tenants.
- Some protections hold by construction: Sanctum tokens are hashed per tenant DB, the Tenant model and AppVersion use the central connection, settings cache keys include the tenant id, invoices and purchases have no delete route, and restrictOnDelete foreign keys protect referenced master data.
- The Playwright E2E setup is safe and broad. It defaults to 127.0.0.1 with no production hosts in specs, credentials can be overridden by environment variable, the crawler has a destructive-action denylist, 37 of 39 flow specs assert no console errors, and render specs run at 5 viewport widths with touch-target checks.

## Findings

### 1. [CRITICAL] Customer and supplier opening balances are wiped by the first transaction, and the tests hide it
- **Location:** `backend/app/Services/CustomerBalanceService.php:16`
- **Effort:** M
- **Evidence:** The opening balance is only ever written into current_balance (CreateCustomerAction.php:24 and CreateSupplierAction.php:24 use 'current_balance' => $dto->opening_balance). No opening_balance or initial_balance column exists in the tenant migrations. CustomerBalanceService::updateBalance (lines 16-41) and SupplierBalanceService::updateBalance (lines 17-46) recompute the balance from scratch as invoices - payments - returns and overwrite current_balance. ReturnService.php:164-170 does the same inline for suppliers. Tests: CustomersApiTest.php:205-251 seeds a fake Invoice::create of 1000 so the recomputation happens to match the seeded 1000 balance. ReturnServiceTest.php:49-54/100-105 starts customers and suppliers at 500/2000 balances, calls the test 'reduces customer debt', and never asserts the balance. With this code it would be -120, not 380.
- **Impact:** A shop migrating customers with opening debts (for example a 5,000 EGP receivable) loses the whole debt the moment that customer gets any invoice, payment or return. Supplier payables are lost the same way. This is silent loss of receivables and payables data.
- **Recommendation:** Write a failing test: create a customer through the API with opening_balance=2500, issue a 100 credit invoice, and assert current_balance === '2600.000'. Do the same for suppliers. Report it to backend-architect: persist the opening balance as its own column or ledger entry and include it in both recalculations. Remove the fake-invoice workaround from CustomersApiTest.
- **Verification:** I checked this against the code and it holds. CreateCustomerAction.php:24 and CreateSupplierAction.php:24 write `$dto->opening_balance` into `current_balance`. Nothing else stores it: the customers and suppliers tenant migrations (2026_08_08_160002/160003) only have `current_balance` DECIMAL(12,3), and Customer::$fillable has no opening or initial balance field. No observer handles Customer or Supplier (only TenantObserver exists), and no opening-balance invoice or payment is created. The opening balance is a real user input: StoreCustomerRequest and StoreSupplierRequest accept it, and resources/js/Composables/useCustomers.js and useSuppliers.js send it from the UI.

The recompute that overwrites it is real. CustomerBalanceService::updateBalance (lines 16-44) sets `current_balance = confirmed invoices - payments - sales returns`. SupplierBalanceService::updateBalance (lines 17-48) does the same with purchases, payments and purchase returns. ReturnService.php:164-170 repeats it inline for suppliers. These recomputes run on every normal money flow. On the customer side: InvoiceService lines 255, 316, 575/578 and 649, PaymentService:64 and ReturnService:94. On the supplier side: PaymentService:120, PurchaseService lines 224, 311, 404 and 445, and ReturnService:164-170. So the first invoice, payment, purchase or return silently replaces the opening debt. The value is not kept anywhere else, so it cannot be recovered from the data.

The ledgers (getCustomerLedger and getSupplierLedger) also start the running balance at '0.000' and have no opening line, so statements leave it out too.

The test claims are accurate:
- **CustomersApiTest.php:205-251:** it seeds `current_balance` 1000 together with a fake confirmed Invoice of 1000, so the recompute (1000 - 400 = 600) happens to match.
- **ReturnServiceTest.php:**
  - It passes `initial_balance`, which is not fillable, so Eloquent drops it silently.
  - Neither test asserts the balance (grep shows no balance assertion).
  - With this code the customer would end at -120 instead of 380, and the supplier at -1250 instead of 750.

I'm keeping critical because receivables and payables are lost silently and permanently on the normal onboarding path for a shop bringing in existing customer and supplier debts.

### 2. [CRITICAL] Unauthenticated, passwordless quick-login by user id/phone/email gives a Sanctum token for any tenant or the central DB, and a test treats this as correct
- **Location:** `D:/projects/sroor/backend/app/Actions/Auth/ApiQuickLoginAction.php:26`
- **Effort:** M
- **Evidence:** routes/api.php:28 registers POST /api/v1/auth/quick-login outside ApiTokenAuth with no throttle. ApiQuickLoginAction::execute looks up the user with User::where(phone=$login)->orWhere(email=$login)->orWhere(id=$login)->first(), never checks a password, and calls createToken(['*']). It also stores the plaintext token in users.api_token. GET /api/v1/auth/workspace-users (AuthController.php:93-110, also unauthenticated) lists every active user's id, name and phone/email. ResolveApiTenancy accepts X-Tenant, ?tenant= or a body 'tenant' field, so the caller can pick any tenant. tests/Feature/Api/AuthApiTest.php:287 test_quick_login_succeeds_without_password asserts a 200 and a token. Nothing tests login by id, cross-tenant use, or the central context.
- **Impact:** An anonymous attacker can send POST /api/v1/auth/quick-login {login:'1'} with X-Tenant:<any tenant id>. The resolver endpoint will hand out tenant ids. The attacker gets that tenant's first user, usually the admin, and with it full read/write over money, stock and users. With no X-Tenant on a central host, the same call returns the central user with id=1. If that user has super_admin or one of the hard-coded phones, the attacker controls the whole platform: tenants, plans and APK releases.
- **Recommendation:** Report to backend-architect as P0. Remove quick-login or bind it to a device-paired PIN plus a terminal secret, and throttle it. Make workspace-users require auth. Then the QA work: rewrite AuthApiTest::test_quick_login_succeeds_without_password into negative tests. These should cover: quick-login by id is rejected, quick-login without a PIN or device secret is rejected, quick-login with X-Tenant=B for a user of A fails, and quick-login in central context never returns a super_admin.
- **Verification:** I confirmed this from the code and found no guard anywhere that stops it.

- **Route:** routes/api.php:28 registers POST /api/v1/auth/quick-login inside the v1 group, which has only the ResolveApiTenancy middleware. It sits outside the ApiTokenAuth group, which starts at line 32. bootstrap/app.php adds no global throttle or auth to the api group. The only extra web middleware there is StoreScope.
- **Controller:** AuthController::quickLogin (lines 115-133) validates only that login is a string. Unlike login(), which calls ensureIsNotRateLimited, it has no rate limiting.
- **Action:** ApiQuickLoginAction.php:26-30 finds the user with where(phone)->orWhere(email)->orWhere(id)->first(). It checks no password and only requires is_active. It then calls createToken($tokenName, ['*']) at line 55 and stores the plaintext token in users.api_token at lines 58-61.
- **User list:** workspaceUsers (AuthController.php:93-110) is also public. It returns id, name and phone or email for every active user.
- **Tenant choice:** ResolveApiTenancy.php:30 takes X-Tenant, ?tenant= or a body 'tenant' field and starts that tenant with no ownership check. With no tenant given on a central host (127.0.0.1, localhost, baraa-solutions.com), the query runs against the central DB, which has its own users table (central migration 0001_01_01_000000_create_users_table.php).
- **Test:** tests/Feature/Api/AuthApiTest.php:287 test_quick_login_succeeds_without_password treats a passwordless 200 plus token as correct. Its only negative test checks an inactive user. Nothing tests login by id, rate limiting or tenant scoping.
- **Frontend:** resources/js/stores/auth.js:73 calls this endpoint, so the feature is meant to be live.

The impact chain holds. Anyone can list users with workspace-users, then post {login: <id>} with any tenant id and get a full-scope bearer token for that user, including admins. Whether the central-context takeover reaches super-admin depends on the central user data, but the tenant-level takeover alone justifies critical.

### 3. [CRITICAL] A tenant admin can escalate to platform super-admin (super_admin role or hard-coded phone), and super-admin routes run inside tenant context
- **Location:** `D:/projects/sroor/backend/app/Providers/AppServiceProvider.php:37`
- **Effort:** M
- **Evidence:** Gate::before returns true for hasRole('super_admin') or phone in ['[REDACTED_PHONE]','[REDACTED_PHONE]']. The super-admin route group (routes/api.php:196) sits inside the same ResolveApiTenancy group and is guarded only by can:super_admin.access. Nothing requires central context. TenantProvisionerService:98 runs PermissionsSeeder in every tenant DB, and that seeder creates the 'super_admin' role (PermissionsSeeder.php:70). StoreUserRequest:23 validates role with exists:roles,name, and CreateUserAction:29 calls syncRoles([$dto->role]). The UI hides super_admin (UserController.php:62), but the API does not. Phone uniqueness is only checked within the tenant DB (StoreUserRequest:20). The Tenant model uses stancl's CentralConnection, and AppVersion pins the central connection (AppVersion.php:18). So once tenancy is initialized, super-admin controllers still read and write central data.
- **Impact:** A tenant admin with users.manage can POST /api/v1/users with role=super_admin, or with phone [REDACTED_PHONE]. They then log in as that user and call /api/v1/super-admin/* with X-Tenant set to their own tenant. From there they can list, suspend or extend every tenant, change plan prices and limits, update platform settings, and publish an APK that every customer's app installs (app-versions store). This is a cross-tenant compromise of the whole platform.
- **Recommendation:** App fix, routed to backend-architect: remove the hard-coded phones. Reject super-admin routes when tenancy()->initialized. Exclude super_admin in StoreUserRequest/UpdateUserRequest when in tenant context, and do not seed the super_admin role into tenant DBs. QA tests to add: (1) in tenant context, POST /users with role=super_admin returns 422; (2) a tenant user with the magic phone gets 403 on /super-admin/dashboard; (3) any super-admin route called with an X-Tenant header returns 403/404.
- **Verification:** I confirmed the escalation path from the code. Two of the impact claims are overstated.

1) AppServiceProvider.php:37-40. `Gate::before` returns true when the user has `hasRole('super_admin')` or when their phone is one of two hard-coded numbers. Nothing in that check looks at whether a tenant context is active.

2) routes/api.php:19 and :196. The super-admin group sits inside the `ResolveApiTenancy` + `ApiTokenAuth` group and is guarded only by `can:super_admin.access`. `ResolveApiTenancy` skips tenancy only for `api/v1/central/*`, so `X-Tenant` initialises tenancy on `/super-admin/*` as normal.

3) ApiTokenAuth authenticates against the tenant DB. In tenant context it binds the `tenant` guard, so the user it resolves is a tenant DB user.

4) TenantProvisionerService.php:87 runs `PermissionsSeeder` inside `$tenant->run`. PermissionsSeeder.php:70 creates the `super_admin` role in every tenant DB.

5) StoreUserRequest.php:13 authorizes anyone who is admin, or has `users.manage` or `roles.manage`. Its rules are `role => exists:roles,name` (tenant DB) and `phone => unique:users,phone` (tenant DB only). CreateUserAction.php:29 then calls `syncRoles([$dto->role])`. The only filter on `super_admin` is in the role list for the UI (UserController.php:62 and GetRolesMatrixAction.php:21). The POST path has no such filter.

So a tenant user with `users.manage` can create a tenant user with role `super_admin`, or with one of the hard-coded phones. They then log in and pass the gate.

Central-data impact I confirmed:
- `App\Models\Tenant` extends stancl's BaseTenant, which uses `Concerns\CentralConnection` (vendor Tenant.php:24). So listing, toggling status, overriding features, deleting tenants, updating DB config and running migrations all act on the central DB.
- AppVersion.php:18 pins the central connection, so publishing an APK through app-versions reaches every tenant.

Overstated parts:
- `Plan` (app/Models/Plan.php) and `Setting` have no central connection. Under an `X-Tenant` request, `updatePlan` would query the tenant DB, which has no plans migration, and fail. Platform settings would be written to the tenant's own settings table.
- So "change plan prices and limits" and "update platform settings" do not reach the platform through this path.

The core cross-tenant takeover (suspend or delete any tenant, change its DB config, push a malicious APK) still stands, so the severity stays critical.

### 4. [CRITICAL] Passwordless quick-login lets anyone get a Sanctum token for any user (including admin) by phone, email or numeric id. The test suite treats this as intended behaviour
- **Location:** `D:\projects\sroor\backend\app\Actions\Auth\ApiQuickLoginAction.php:26`
- **Effort:** M
- **Evidence:** routes/api.php:28 registers POST /api/v1/auth/quick-login outside the ApiTokenAuth group. It has no throttle and no settings or feature flag. ApiQuickLoginAction::execute looks the user up with where('phone',$login)->orWhere('email',$login)->orWhere('id',$login) and calls createToken($tokenName,['*']) without checking a password. routes/api.php:29 /auth/workspace-users is also unauthenticated (AuthController.php:93-110) and returns every active user's id, name and phone/email. tests/Feature/Api/AuthApiTest.php:287 test_quick_login_succeeds_without_password asserts this as the happy path.
- **Impact:** An unauthenticated attacker calls workspace-users, then quick-login with login=<admin id>, and gets a full-scope admin token. ResolveApiTenancy accepts any X-Tenant header or ?tenant= value, so this works against every tenant, and in the central context against platform users. That means full account takeover of every shop, with access to money, stock and user management.
- **Recommendation:** Report to backend-architect/security as a P0. Remove the route, or limit it to a device-bound PIN plus a per-tenant setting plus throttle. QA should replace the existing happy-path test with failing tests: quick-login without a credential must return 401/422 and issue no token (assertDatabaseCount personal_access_tokens unchanged), workspace-users must require auth, and both must be throttled.
- **Verification:** The finding holds; I checked each step in the code. routes/api.php:28-29 registers POST /auth/quick-login and GET /auth/workspace-users inside the v1 group, whose only middleware is ResolveApiTenancy. Both sit outside the ApiTokenAuth group that starts at line 33. bootstrap/app.php adds no API throttle and no auth middleware; it only appends StoreScope to the web group and defines aliases. AuthController::quickLogin validates only that 'login' is a string and then calls ApiQuickLoginAction::execute. At ApiQuickLoginAction.php:26-30 the action looks the user up with where phone OR email OR id = $login. It checks only is_active (line 47) and then issues createToken($tokenName, ['*']) at line 55. There is no password check, no role restriction (admins are not excluded), no settings or feature flag, and no device or IP allowlist. AuthController::workspaceUsers (lines 93-110) is unauthenticated and returns the id, name and phone/email of every active user, which gives an attacker the login values. ResolveApiTenancy.php:30-37 starts tenancy for any tenant named in the X-Tenant header, the ?tenant= query or the request body; it also resolves tenants by subdomain or domain. Nothing ties the caller to that tenant, so the attack works against every tenant whose id, slug or domain is known. The frontend (LoginView.vue:135, stores/auth.js:76) shows this as an intended 'no password' feature, and tests/Feature/Api/AuthApiTest.php:287 test_quick_login_succeeds_without_password asserts the passwordless token as the happy path. No negative test covers admins or an unauthenticated attacker. I found no guard in middleware, policies or DB constraints. The claim about the central context, that platform users are exposed when no tenant resolves, depends on what the central users table holds and was not fully verified. That does not lower the severity: any shop admin can be taken over without credentials, which is critical.

### 5. [CRITICAL] Any user can make themselves a global super-user by changing their own phone to one of two hardcoded numbers that Gate::before always allows
- **Location:** `D:\projects\sroor\backend\app\Providers\AppServiceProvider.php:37`
- **Effort:** S
- **Evidence:** Gate::before returns true when hasRole('super_admin') OR in_array($user->phone, [two hardcoded phone numbers]). The same list is hardcoded in OverrideTenantFeatureRequest.php:13, ToggleTenantStatusRequest.php:13, UserResource.php:19, TelescopeServiceProvider and routes/web.php. UpdateProfileRequest.php:14 authorizes any logged-in user. Its rules (line 23) accept any 'phone' that is unique in that DB's users table, and UpdateProfileAction.php:26 saves it. Nothing in the tests covers this.
- **Impact:** A cashier in a new tenant DB (where those numbers don't exist yet) sends PUT /api/v1/profile with phone set to one of the numbers. From then on every Gate check passes, including can:super_admin.access on /api/v1/super-admin/* (tenant listing, destroy, update-db-config, run-migrations). This is a cross-tenant platform compromise. The numbers are personal data committed to source (type: hardcoded phone-number allowlist; values not reproduced here).
- **Recommendation:** Remove the phone-based bypass everywhere and rely on the super_admin role in the central DB only. Add tests: (1) a cashier PUTs /profile with a privileged phone, then GET /super-admin/dashboard must return 403; (2) /profile must not let a user change their phone to a reserved value, or phone changes need re-verification.
- **Verification:** I confirmed this in the code and found no guard that stops it. backend/app/Providers/AppServiceProvider.php:37-45 registers a Gate::before that returns true when the user has the super_admin role OR when $user->phone is in a hardcoded list of two phone numbers (the type of secret is a hardcoded phone-number allowlist; I have not reproduced the values). The check runs before the super_admin.* deny branch, so it also grants super_admin.access. Line 49 uses the same check for viewPulse. The privilege therefore comes from a field the user can edit, not from a role.

How a normal user reaches it:
(1) routes/api.php:183 exposes PUT /api/v1/profile inside the ApiTokenAuth group. The only extra check is UpdateProfileRequest::authorize(), which returns $this->user() !== null.
(2) The rules on line 23 accept any phone ('required|string|max:20') that is unique in the current connection's users table. When tenancy is on, that is the tenant DB, so the unique rule only blocks the change if one of the numbers already exists in that tenant.
(3) app/Actions/Profile/UpdateProfileAction.php:26 writes $user->phone directly. Nothing re-checks it and no observer stops it.
(4) The super-admin routes (routes/api.php:196-220) sit in the same v1 group, behind ResolveApiTenancy and ApiTokenAuth. ResolveApiTenancy only skips tenancy for api/v1/central/*, so a tenant-scoped token passes ApiTokenAuth with a tenant User. The only gate is middleware 'can:super_admin.access', which Gate::before satisfies.
(5) SuperAdminApiController does not check tenancy()->initialized and has no other guard. It works on central-connection Tenant models, so tenant listing, destroyTenant, updateDatabaseConfig and runTenantMigrations all run from a tenant request.
(6) TenantProvisionerService:99-107 seeds the first admin with the owner's own phone, or slug_admin if none is given. New tenants therefore do not contain the two numbers, so the unique rule does not block the change.

The same escalation also works without the profile endpoint: a tenant admin can create a user with one of these phones through /users, and a tenant owner can register with one. The privilege also applies everywhere inside the tenant, since every Gate check passes.

On testing: tests/Feature/Api/ProfileApiTest.php uses one of the hardcoded numbers as its fixture phone. It even checks a phone update to that number, but only as normal behaviour. No test checks that changing your phone does not change your privileges. SuperAdminApiTest's unauthorized-user test does not cover this path either.

The numbers also sit in the source files listed in the finding, in the database/*.sqlite files, and in the e2e fixtures. This is a cross-tenant platform compromise with an easy exploit, so critical is justified.

### 6. [CRITICAL] A tenant admin can probably give their own role super_admin.access through the roles API (admin role is always synced to Permission::all())
- **Location:** `D:\projects\sroor\backend\app\Actions\Roles\UpdateRolePermissionsAction.php:22`
- **Effort:** S
- **Evidence:** if ($role->name === 'admin') { $role->syncPermissions(Permission::all()); } else { $role->syncPermissions($permissions); }. Permission::all() includes 'super_admin.access' (PermissionsSeeder.php:62), even though the seeder deliberately leaves it out of admin (line 80). UpdateRolePermissionsRequest only validates exists:permissions,name, so any roles.manage holder can also add super_admin.access to cashier or any other role. Spatie registers its Gate::before in packageBooted, before AppServiceProvider's deny-for-'super_admin.*' callback. A user who holds the permission directly therefore gets true from the first callback. No test covers this: RoleApiTest only updates the cashier role with normal permissions.
- **Impact:** PUT /api/v1/roles/{adminRoleId}/permissions with any body gives the tenant admin super_admin.access, which opens /api/v1/super-admin/* (manage or delete all tenants, change DB configs). This is a cross-tenant escalation. The callback ordering is inferred from the provider boot order and should be confirmed with a test.
- **Recommendation:** Write a failing test: a tenant admin PUTs /roles/{admin}/permissions, then GET /super-admin/dashboard is asserted 403, and the admin role must not have super_admin.access. Also test that a roles.manage user cannot add super_admin.access to any role. Fix: never sync Permission::all(), and filter super_admin.* out of the roles API.
- **Verification:** I could not refute this. Every link in the chain is in the code.

1. **The action grants every permission to `admin`.** In `UpdateRolePermissionsAction.php:20-21`, when the role is `admin` the action runs `syncPermissions(Permission::all())` and ignores the request body.

2. **The tenant database holds `super_admin.access`.** `TenantProvisionerService.php:85-87` runs `PermissionsSeeder` inside `$tenant->run()`. That seeder creates `super_admin.access` at line 62 and deliberately leaves it out of the `admin` role at line 80. So `Permission::all()` includes it, and the rule `exists:permissions,name` accepts it for any other role too.

3. **The only gate on the endpoint is `admin` or `roles.manage`.** `UpdateRolePermissionsRequest::authorize()` allows `hasRole('admin') || can('roles.manage')`. The `/api/v1` route in `api.php:175` adds no `can:` middleware.

4. **Super-admin routes are reachable with a tenant token.** They sit in the same group, behind `ResolveApiTenancy` and `ApiTokenAuth` (`api.php:196`). With an `X-Tenant` header, `ResolveApiTenancy` starts tenancy. The tenant user is then authenticated, and `can:super_admin.access` is checked against the tenant database's Spatie tables.

5. **The deny callback runs too late.** Spatie 8.3.0 registers its `Gate::before` in `packageBooted` through `callAfterResolving(Gate)`. `composer.json` has an empty `dont-discover`, so this package provider registers and boots before `AppServiceProvider`, which is listed in `bootstrap/providers.php`. Spatie's callback therefore runs first. It returns `true` from `checkPermissionTo()` once the role holds the permission, before `AppServiceProvider:41` can return `false` for `super_admin.*`. I inferred this order from how Laravel 11+ registers providers. I did not run a test, because this was a read-only run.

6. **The super-admin endpoints act on central data.** `Tenant` extends the stancl `BaseTenant`, which uses the central connection. `destroyTenant`, `updateDatabaseConfig`, `toggleStatus`, `runTenantMigrations` and the plans and settings endpoints all work on central data from inside a tenant request.

7. **The endpoints' own checks do not stop it.** Several super-admin FormRequests, such as `UpdateTenantDatabaseConfigRequest` and `ToggleTenantStatusRequest`, already authorize any user with `hasRole('admin')`. So the route middleware is the only real barrier.

**No test covers it.** `RoleApiTest` only updates the cashier role, at lines 122 and 143.

**Result:** a tenant admin can send `PUT /api/v1/roles/{adminRoleId}/permissions` and then call `/api/v1/super-admin/*`. That is a cross-tenant platform takeover, so critical stands.

### 7. [HIGH] Tenant/store isolation and the tenancy middleware are almost untested, and suspended tenants are not blocked at the API layer
- **Location:** `D:/projects/sroor/backend/app/Http/Middleware/ResolveApiTenancy.php:30`
- **Effort:** L
- **Evidence:** No test in tests/Feature sends an X-Tenant header or calls tenancy()->initialize. In the coverage matrix only 7 of 123 tested routes have anything isolation-related, and all of those are super-admin or resolver routes. No test method name mentions isolation, other_store or cross-tenant, apart from AppUpdateApiTest::test_platform_isolation. Only 2 tests in the whole suite assert a 404. ResolveApiTenancy initializes tenancy for any tenant found by X-Tenant, ?tenant, request body 'tenant' or host, and never checks status or subscription. Tenant::isSuspended() (Tenant.php:176) is only consulted in ResolveTenantWorkspaceAction.php:48, the resolver endpoint. With no tenant identifier on a central host, the request continues on the central DB. tests/TestCase.php:13-15 migrates tenant tables into the same single sqlite DB, so every API test runs tenant models on the central connection, which is not the production topology.
- **Impact:** Nothing would catch a regression that leaks data between tenants or stores, for example a missing store scope on index/show, or a token from tenant A used with X-Tenant: B. A suspended or expired tenant (toggle-status, subscription_ends_at) can apparently keep using the API by sending X-Tenant directly, which bypasses billing enforcement for a paid SaaS. This is a code-reading inference and needs a failing test to confirm.
- **Recommendation:** Add a TenancyIsolationTest with two real tenants (sqlite file per tenant): a token from A plus X-Tenant B gives 401; A's data is invisible from B; a suspended tenant gives 403/402 on /api/v1/items; an unknown X-Tenant gives 404; host/subdomain/slug resolution works. For each store-scoped resource (invoices, items, customers, transfers, returns), add show/update/delete cases for another store's id that expect 404 or 403.
- **Verification:** The code confirms the finding. backend/app/Http/Middleware/ResolveApiTenancy.php:30-37 takes the tenant from X-Tenant, ?tenant or the request body 'tenant' and calls tenancy()->initialize() for any tenant it finds. It never reads status, isSuspended() or isActive(). The host branch (lines 55-69) does the same, and on a central host with no identifier the request goes on with no tenant. This middleware is the only one on the /api/v1 group (routes/api.php:19). The only check for suspended or expired tenants in app/ and routes/ is ResolveTenantWorkspaceAction.php:48. Login has none, and TenancyServiceProvider has no listener on TenancyInitialized apart from BootstrapTenancy. So a suspended or expired tenant (Tenant::isSuspended at Tenant.php:176-179) can keep calling tenant APIs by sending X-Tenant directly. The test gaps are also real. No file in tests/ uses X-Tenant or tenancy()->initialize. tests/TestCase.php:13-15 migrates the tenant tables into the single default sqlite DB, so tests never use the per-tenant DB setup that production uses. No test name mentions isolation, cross-tenant or other_store, apart from AppUpdateApiTest::test_platform_isolation and the tests for the resolver and super-admin endpoints. Only 2 assertions check for 404 (AppUpdateApiTest:171 and CentralTenantResolverApiTest:93). Two points are overstated. About 25 tests do assert 403, but they check role permissions, not store or tenant isolation. And the scenario "a token from tenant A used with X-Tenant: B" probably does not leak data in production with one DB per tenant, because the token would be looked up in B's database. I did not confirm that path, so the cross-tenant leak is a missing test, not a proven bug. The suspended-tenant gap in billing enforcement for a paid SaaS is clearly visible in the code and has no test, so high severity holds.

### 8. [HIGH] Shift close counts every cash sale twice, so each closed shift shows a false shortage
- **Location:** `backend/app/Services/ShiftService.php:81`
- **Effort:** M
- **Evidence:** calculateShiftTotals sums cash-invoice paid_amount (lines 81-85, plus partial invoices at 95-99). It then adds ALL customer cash Payments since opened_at (lines 102-111). But confirmInvoice already creates a Payment row with customer_id and method cash for every paid invoice (InvoiceService.php:241-251), so the same money is counted twice. The Payment and supplier-payment queries (102-105, 120-123) also have no store_id filter, and every sales return is subtracted as a cash refund (126-129). closeShift (159-177) neither locks the shift nor checks status==='open'. Tests: ShiftApiTest.php:155-188 closes a shift with no sales and never asserts expected_cash_balance or cash_difference. The z-report tests (ShiftApiTest:190, ShiftsAndDailyJournalApiTest:155) use pre-seeded totals, so the calculation is never exercised.
- **Impact:** After one 1,000 EGP cash sale, expected cash comes out 1,000 too high. Every shift then shows a shortage equal to its cash sales and fires a Telegram discrepancy alert (line 197). Cashiers get blamed for shortages that don't exist, and the Z-report numbers are wrong. Other branches' cash payments also leak into each shift.
- **Recommendation:** Add a test: open a shift, post one cash invoice through /api/v1/invoices, close the shift with actual = opening + sale, and assert cash_difference === '0.000'. Add a second store and assert its payments are excluded. Add a test that closing an already-closed shift is rejected. The test will fail. Route the defect to backend-architect.
- **Verification:** The core finding is confirmed in the code. ShiftService::calculateShiftTotals (backend/app/Services/ShiftService.php:81-85, 95-99) sums paid_amount from confirmed cash and partial invoices. At lines 102-111 it also adds every Payment that has customer_id and payment_method='cash' since opened_at. InvoiceService::confirmInvoice (InvoiceService.php:225-251) creates exactly that kind of Payment row for every paid invoice, with customer_id, invoice_id and method defaulting to 'cash'. All three sale paths reach confirmInvoice: ProcessPOSInvoiceAction:17, CreateSalesInvoiceAction:22 and CreateBlenderInvoiceAction:46. So each cash sale is counted twice. expected_cash_balance comes out too high by the cash collected, and closeShift (lines 164 and 197) records a false shortage and sends the Telegram discrepancy alert.

The cross-branch leak is also real. The Payment model has no global or store scope, and the payments table (tenant migration 2026_08_08_160007) has no store_id column. Customer and supplier cash payments from every branch therefore go into every shift. The Payment query at line 102 has no invoice_id exclusion either.

Every sales return is subtracted as a cash refund (lines 126-129), whatever the refund method. The z-report tests (tests/Feature/Api/ShiftApiTest.php:204 and ShiftsAndDailyJournalApiTest.php:169) assert pre-seeded expected_cash_balance and cash_difference values, so they never run the calculation.

One sub-claim is overstated. CloseShiftAction.php:23 loads the shift with where('status','open'), so a status check does exist upstream. It is not race-safe, though, because there is no lockForUpdate. That does not reduce the main money bug, so severity stays high.

### 9. [HIGH] deductStock skips the branch stock check when the branch has no store_stocks row
- **Location:** `backend/app/Services/StockService.php:64`
- **Effort:** S
- **Evidence:** `if ($storeStock && bccomp(...) < 0) throw` and then `if ($storeStock) {...}`. When the store has no StoreStock row, only the master item total is checked and decremented. The suite even depends on this: InvoiceServiceTest::test_multi_store_independent_invoice_numbering (lines 255-323) sells from 'SHOP-MAADI' and 'VAN-01', which have no stock rows, and the sales succeed. cancelPurchase pre-check (PurchaseService.php:274) has the same `$storeStock &&` hole. StockTransferService.php:71 handles this correctly with `!$fromStock ||`.
- **Impact:** A branch or van with zero stock can sell goods held in another branch. Per-branch stock stops reconciling with the master stock total, and oversell protection only works at tenant level.
- **Recommendation:** Write a failing test: an item with 10 in the main store and no row for a van, then sell 1 from the van and expect an exception with every quantity unchanged. Fix the multi-store numbering test so it seeds stock in each store. Report StockService.php:64-73 to backend-architect.
- **Verification:** I confirmed this in the code. In backend/app/Services/StockService.php:64, the branch check only runs when a row exists (`if ($storeStock && bccomp(...) < 0) throw`). The decrement at lines 70-73 is also inside `if ($storeStock)`. So when a store has no store_stocks row, the sale is checked and deducted only against Item.current_stock (line 47), and the StockMovement is still recorded with that store_id.

I found no guard that closes this gap:
- InvoiceService::confirmInvoice (lines 58-108) calls deductStock directly without any branch pre-check. Both CreateSalesInvoiceAction and ProcessPOSInvoiceAction go through it.
- hasAvailableStock does treat a missing row as unavailable, but nothing in app/ calls it.
- PurchaseService.php:273 has the same `$storeStock &&` gap in the cancelPurchase pre-check.
- StockTransferService.php:71 handles it correctly with `!$fromStock ||`.

The InvoiceServiceTest multi-store test (SHOP-MAADI / VAN-01) creates the stores and the item without any StoreStock rows. Its sales from both stores succeed, which confirms this behaviour.

One thing reduces the risk. CreateItemAction (lines 42-49) creates zero-quantity StoreStock rows for every store that exists when an item is created, and those zero rows are checked correctly.

The gap is still easy to reach in practice. CreateStoreAction does not create StoreStock rows, so any branch or van added after items already exist has no rows and can sell the whole tenant-level stock. Rows can also be missing for imported or seeded items. In addition, GetPOSBootstrapDataAction:64 shows `COALESCE(store_stocks.quantity, items.current_stock)`, so the POS screen tells the cashier that branch's stock equals the master total.

Adding branches is a core flow for a multi-branch SaaS, so I am keeping the severity at high.

### 10. [HIGH] Deleting, restoring or force-deleting a return does not reverse its stock or balance
- **Location:** `backend/app/Actions/Returns/DeleteReturnAction.php:15`
- **Effort:** M
- **Evidence:** DeleteReturnAction only runs `$returnDoc->delete()`. RestoreTrashRecordAction.php:28 and ForceDeleteTrashRecordAction.php:28 restore or forceDelete ReturnDocument with no stock or balance logic, and forceDelete is a hard delete of an approved financial document. ReturnsApiTest::test_can_delete_return_document (251-277) asserts only assertSoftDeleted.
- **Impact:** Deleting a sales return leaves the returned quantity in stock (phantom inventory). Once anything recalculates the customer balance, the credit silently disappears because the soft-deleted return is excluded. Restoring from trash flips it back without re-adding stock. Repeated delete and restore cycles drift stock and balances with no audit trail.
- **Recommendation:** Write failing tests: after a 1.000 sales return, delete it and assert stock returns to its exact prior value and current_balance is recalculated. Restore it and assert the values come back. Also assert that force delete on returns is rejected. Report to backend-architect.
- **Verification:** I confirmed this from the code. backend/app/Actions/Returns/DeleteReturnAction.php:15-17 runs only `ReturnDocument::findOrFail($id)->delete()`. It has no DB::transaction, no stock reversal, no balance recalculation and no audit log. ReturnController::destroy calls it directly; that endpoint is at api.php:153 and tenant.php:203 and is gated only by the returns.manage permission.

ReturnDocument (app/Models/ReturnDocument.php) uses SoftDeletes and has no boot or event hooks. The only registered observer is TenantObserver (AppServiceProvider:53), so nothing elsewhere reverses the stock effect.

When a return is created, ReturnService::createSalesReturn calls StockService::addStock (sales_return_in) and updates the customer balance. createPurchaseReturn calls deductStock (purchase_return_out) and recalculates the supplier balance. Neither effect is undone on delete.

Balance drift is also confirmed. CustomerBalanceService::updateBalance (line 30) and the supplier calculation (ReturnService ~line 167) sum ReturnDocument with the default SoftDeletes scope. Any later recalculation, such as a new invoice or payment, drops the deleted return's credit, while its stock stays in place.

RestoreTrashRecordAction:28 calls only `restore()`, and ForceDeleteTrashRecordAction:28 calls only `forceDelete()`. That forceDelete is a physical delete of a financial document, which breaks the no-hard-delete rule; the stock_movements rows that reference it through the morph source are left orphaned.

tests/Feature/Api/ReturnsApiTest.php:251-277 asserts only status 200 and assertSoftDeleted. It never checks the item quantity or the customer balance.

One part of the claimed impact is slightly overstated. Restoring does not re-add stock, but the stock was never removed, so a delete-then-restore cycle does not drift stock further. The real damage is that stock and balance are inconsistent while the document is deleted, and that the trash path can permanently erase it. Severity stays high.

### 11. [HIGH] Sales returns are not tied to an invoice, so any quantity at any price can be credited
- **Location:** `backend/app/Http/Requests/StoreReturnRequest.php:1`
- **Effort:** M
- **Evidence:** The rules have no invoice_id, so validated() drops it and returns are never linked to an invoice. unit_price comes from the client (items.*.unit_price, min:0). ReturnService::createSalesReturn (63-89) adds stock and credits the customer with no check that the item was sold to this customer, that the quantity is at most the quantity sold minus earlier returns, or that the invoice isn't cancelled. ShiftService.php:126-129 treats every sales return as a cash refund taken from the drawer.
- **Impact:** A cashier can post a return for goods never sold. That lowers the expected drawer cash (letting them pocket the difference), inflates stock, and credits the customer. Returning against a cancelled invoice reverses the stock twice.
- **Recommendation:** Add 422 tests for these cases: a return quantity above the quantity sold, an item not on the invoice, a cancelled invoice, and a unit_price different from the invoice line. They will fail today. Report to backend-architect.
- **Verification:** I confirmed this in the code. backend/app/Http/Requests/StoreReturnRequest.php:16-29 has no rule for invoice_id or purchase_id. ReturnController::store (ReturnController.php:118-126) builds ReturnDocumentDTO::fromArray($request->validated()), so invoice_id is always null, even though the DTO and the service both accept it. items.*.unit_price comes from the client (numeric, min:0). ReturnService::createSalesReturn (ReturnService.php:40-104) only locks the customer and the item. It never checks that the item was on any invoice for that customer, never caps the quantity at sold minus already returned, and never looks at invoice status. Even when an invoice is loaded (line 43), it is used only to get store_id. It then calls addStock and CustomerBalanceService::updateBalance, which subtracts the sum of sales returns from the customer balance. ShiftService.php:125-128 adds the total_amount of every sales_return in the shift and store to cash outflows, which lowers the expected drawer cash. The same return also reduces the customer's balance, so its value is counted both as a credit and as a cash refund. Nothing upstream guards this. The only gate is authorize(): admin, returns.create or returns.manage, plus can:returns.manage on the tenant route. PermissionsSeeder.php:93 grants returns.manage to the cashier role, so the cashier scenario is realistic. There is no observer or DB constraint that ties return quantities to invoice lines. tests/Feature/Api/ReturnsApiTest.php and ReturnServiceTest.php never mention invoice_id, so this path has no test coverage. One nuance: no invoice is linked through the API, so a specific 'return against a cancelled invoice' never happens there. The real double-reversal risk is crediting stock and money for goods that were already reversed when the invoice was cancelled. I kept severity at high: a cashier can directly cause cash and stock fraud and the code has no compensating control.

### 12. [HIGH] Invoice cancel leaves payments and treasury-paid expenses in place and allows double reversal after a return
- **Location:** `backend/app/Services/InvoiceService.php:278`
- **Effort:** M
- **Evidence:** cancelInvoice only re-adds stock and sets the status. The Payment rows created at 229-251 stay, the Expense rows created for treasury-paid additional expenses (168-178) stay, and there is no check for existing sales returns against the invoice. CustomerBalanceService then excludes the cancelled invoice but still subtracts its payments, so a cash customer ends with a negative (credit) balance. TreasuryService::getBalances (34-37) still counts the payment as inflow. Tests: InvoiceServiceTest::test_invoice_cancellation_reverses_stock uses a credit invoice and asserts only stock. InvoiceApiTest.php:283-317 cancels a CASH invoice and asserts only stock, with a (float) comparison.
- **Impact:** Cancelling a 5,500 cash sale leaves treasury overstated by 5,500 and gives the customer a 5,500 credit, with no refund document. A return followed by a cancel puts the goods back into stock twice.
- **Recommendation:** Extend the cancel tests to assert exact prior values: item and store stock, customer.current_balance === '0.000', and the treasury cash balance after cancel, payment rows (soft-deleted or reversed), expenses, and a guard rejecting cancel when returns exist. Ask the product owner whether cancelling a cash sale should create a refund voucher.
- **Verification:** I confirmed this from the code. The only path is CancelSalesInvoiceAction.php:24, which calls InvoiceService::cancelInvoice (InvoiceService.php:278-335). The method checks the admin role, locks the invoice and rejects a second cancel. It then re-adds stock per line with addStock 'cancellation_in', sets status=cancelled and remaining_amount=0, and recalculates the balance. Nothing else happens.
1. It does not void or reverse the Payment rows created at 229-251.
2. It does not touch the treasury-paid Expense rows created at 168-178.
3. It does not look for ReturnDocument rows with invoice_id = this invoice.
I found no observer, listener or model hook on 'cancelled' that would make up for this; a grep of Observers, Models and Listeners returned nothing.
CustomerBalanceService::updateBalance (lines 21-38) sums net_total for confirmed invoices only, but sums ALL Payments for the customer with no invoice-status filter. A cancelled cash invoice therefore leaves a negative (credit) balance equal to what was paid. The ledger (73-88) also still lists the payment.
TreasuryService::getBalances (34-37) counts every customer Payment as inflow, and 46-49 counts every Expense as outflow, with no link to invoice status. So treasury stays overstated by the collected amount, and the expense stays booked as an outflow.
ReturnService::createSalesReturn (38-91) puts stock back with 'sales_return_in' and does not check the invoice status. cancelInvoice re-adds the full original quantities without subtracting earlier returns, so a return followed by a cancel restocks those goods twice. The return amount also stays as a credit on the customer.
On tests: InvoiceServiceTest::test_invoice_cancellation_reverses_stock (133-170) asserts only status and the stock movement. No test covers payments, treasury, expenses or return-then-cancel.
Severity stays high. This is a real money and stock integrity bug and it breaks the project rule "Cancelling a posted document must reverse its stock, balance, and treasury effects". It is limited to admins, but any routine cancel of a cash sale causes it.

### 13. [HIGH] POS and SPA checkout silently drop discounts, split payments and additional expenses
- **Location:** `backend/app/Http/Requests/StoreSalesInvoiceRequest.php:1`
- **Effort:** M
- **Evidence:** PosView.vue:707-722 posts discount_type, discount_value, `expenses` and `payments` to /api/v1/invoices. StoreSalesInvoiceRequest has rules for neither `expenses` nor `payments`, and CreateInvoiceDTO (10-21, 40-54) has no payments field. InvoiceController.php:148 builds the DTO from validated(), so both are discarded. The /api/v1/pos/checkout path (PosController.php:54) validates discount_amount and items.*.discount (StorePOSInvoiceRequest.php:70,84), but POSInvoiceDTO reads discount_value (line 39) and POSInvoiceItemDTO drops discount entirely, so all discounts are lost. payment_type 'bank_transfer' passes validation but falls into the credit branch in InvoiceService.php:191-197 (paid=0). POSSolidArchitectureTest.php:97-132 builds the DTO directly, bypassing the request, so the test passes. PosApiTest:129 sends no discount.
- **Impact:** A split payment such as 300 Visa plus 200 cash is booked as a single payment in one method, which distorts treasury per-method balances and shift cash. Shipping or expenses entered at POS are never charged. Discounts on /pos/checkout are not applied, so the server-side total no longer matches what the cashier showed. A bank_transfer sale is recorded as unpaid debt.
- **Recommendation:** Add HTTP-level tests that post the exact SPA payload (discount_value, expenses, payments with two methods) and assert net_total, payment rows per method and expense rows. Add a /pos/checkout test with discount_amount and items.*.discount, and a bank_transfer payment_type test. All will fail. Report to backend-architect.
- **Verification:** The main claim holds for the path the live SPA actually uses. A few sub-claims are overstated.

Confirmed:
- `backend/resources/js/views/POS/PosView.vue:707-722` posts to `/invoices` with `expenses: additionalExpenses` and `payments: multiPayments`.
- `StoreSalesInvoiceRequest.php:40-56` has no rules for `payments` or `expenses`. It only has a rule for `additional_expenses`, a different key from the one the SPA sends.
- `InvoiceController.php:148` builds the DTO from `$request->validated()`, so both keys are stripped.
- `CreateInvoiceDTO.php:9-54` has no `payments` field either.
- `InvoiceService` does support both. It handles `$data['payments']` at line 225 and writes one `Payment` per method. It handles `$data['additional_expenses']` at line 130 and adds them to `net_total` / `shipping_cost`.
- Because the data never reaches the service, a split payment becomes one `Payment` in a single `payment_method` (the else-branch at around line 240). For `cash`, the service sets `paid = netTotal` (line 191), and that total leaves out the expenses. So shipping charged to the customer account never reaches the invoice or the customer balance, even though the cashier's screen total (`cartNetTotal`, PosView:470) includes it.
- No test sends `payments` or `expenses` through `/api/v1/invoices`, so the suite does not catch this.

Overstated:
1. Discounts are NOT lost on the main SPA path. `StoreSalesInvoiceRequest` validates `discount_type` and `discount_value`, and the DTO and service apply them.
2. The `/api/v1/pos/checkout` discount loss is real in code, but its exposure is limited. `StorePOSInvoiceRequest` validates `discount_amount` (line 70) and `items.*.discount` (line 84). `POSInvoiceDTO::fromArray` reads `discount_value` (line 39), which `validated()` strips. `POSInvoiceItemDTO` drops the per-line discount. However, `/pos/checkout` is only called from `PosView.legacy.grid.vue:1399` and from tests, not from the current `PosView`. That path does keep `payments` and `expenses` (`POSInvoiceDTO` lines 43-44).
3. The `bank_transfer` `payment_type` falling into the credit branch is latent. `PosView` sends `bank_transfer` as a `payment_method` (it is one of the options in `POSMultiPaymentModal`), not as a `payment_type`.

Severity stays high: the active POS screen silently mis-books money. Split payments distort per-method treasury and shift totals, and customer-charged expenses are dropped from `net_total` and from the customer's balance.

### 14. [HIGH] Central users with the 'admin' role are auto-provisioned as admin in every tenant (login fallbacks and token fallback)
- **Location:** `D:/projects/sroor/backend/app/Http/Middleware/ApiTokenAuth.php:50`
- **Effort:** M
- **Evidence:** ApiTokenAuth step 3: if no tenant user matches, the token is looked up in the central DB. If that central user hasRole('admin') (not super_admin), the request is authenticated as the tenant user with the same phone. ApiLoginAction.php:36-58 and LoginAction.php:40-62 go further: they firstOrCreate a tenant user from the central user's credentials and call syncRoles([admin]) in whatever tenant X-Tenant names. No test covers any of these paths, because no test initializes tenancy.
- **Impact:** Any central-DB user holding the ordinary 'admin' role can log into every customer's tenant as admin, with no audit trail on the tenant side. The central DB is 452 MB and appears to hold legacy single-tenant ERP data with admin users. Under the phone-match fallback, a central admin can also become an unrelated tenant user who happens to share the phone number.
- **Recommendation:** Restrict the fallback to an explicit super_admin plus impersonation tokens (a tenant_user_impersonation_tokens table already exists), with an audit log. Tests to add: a central admin token with X-Tenant=B gets 401; a central plain-admin login into tenant B is rejected; any allowed impersonation writes an activity log in B.
- **Verification:** I confirmed the finding in the code. One part is overstated.

**What the code does**
- **Token fallback.** `backend/app/Http/Middleware/ApiTokenAuth.php:50-63`: when a tenant is active and no tenant user owns the token, the token is looked up in the central DB. If that central user `hasRole('admin')`, the request runs as the tenant user found by `where('phone', $centralUser->phone)`. That match is by phone only, so the tenant user can be an unrelated person. If the central phone is null, Laravel turns the query into `whereNull`, which matches any active tenant user with no phone.
- **API login.** `backend/app/Actions/Auth/ApiLoginAction.php:36-59` runs only when no tenant user has that phone or email. It checks the password against the central user, runs `firstOrCreate` on a tenant user, and calls `syncRoles([admin])`.
- **Web login (worse).** `backend/app/Actions/Auth/LoginAction.php:40-65`, wired to `POST /login` in `routes/tenant.php:29`. If the tenant attempt fails, the central fallback calls `firstOrCreate(['phone' => ...])`. That finds an existing, unrelated tenant user with the same phone, replaces all of their roles with `admin`, and logs in as them.
- **Who picks the tenant.** The tenant comes from `ResolveApiTenancy` on the whole `/v1` group, which can use the client-supplied `X-Tenant` header (per `.claude/rules/multi-tenancy.md`). So this works against any tenant.
- **Tests.** No test under `backend/tests` initializes tenancy or calls `central(`, so none of these paths is covered.

**What is overstated**
In the central DB, `admin` is not an ordinary role. It is effectively the platform-operator role:
- `SuperAdminLoginAction.php:43` lets anyone with `hasRole('admin')` into the super-admin panel.
- `DatabaseSeeder.php:17-41` gives the seeded platform admins both `super_admin` and `admin`.

So this looks like an intended "super admin can enter any tenant" backdoor, not a privilege escalation for ordinary users. The claim that the 452 MB central DB holds legacy shop admins who would gain this access cannot be checked from code.

**Why it stays high**
- It provisions tenant accounts silently, with no consent from the tenant.
- The phone-collision cases take over and escalate unrelated tenant accounts.
- `DatabaseSeeder.php:20-41` seeds those central admin accounts with hard-coded weak default passwords (credential type: plaintext default passwords). If that seeder ran in production, anyone who knows them gets into every tenant.
- None of this is tested.

### 15. [HIGH] No test initializes tenancy or sends X-Tenant, so every cross-tenant attack path is unproven
- **Location:** `D:/projects/sroor/backend/tests/TestCase.php:13`
- **Effort:** L
- **Evidence:** Searching tests/ for 'X-Tenant', 'tenancy()' or '->initialize(' returns no matches. CentralTenantResolverApiTest, SuperAdminApiTest and SuperAdminSolidTest only create Tenant rows, with TenantCreated/CreatingDatabase/MigratingDatabase events faked. TestCase::setUp runs `migrate --path=database/migrations/tenant` into the same :memory: DB as the central migrations. Ten tenant migrations share filenames with central ones, so the migrator skips them. Two of those differ: the users softDeletes, and activity_logs, whose tenant version has an FK to stores with nullOnDelete. Central tables (tenants, plans, subscriptions, domains) and tenant tables (items, invoices) end up in one schema.
- **Impact:** None of these attack paths is covered: a token from A plus X-Tenant:B; ?tenant= or body tenant= overriding; the host resolution in ResolveApiTenancy.php:49-70 re-initializing to a different tenant after the header already chose one; a tenant-not-found 404; ERP endpoints hit with no tenant at all, which falls back to the central DB with no guard. Code that queries a central table from tenant context, or the reverse, passes in tests and fails in production. Example: Plan/Subscription have no CentralConnection, and Tenant::checkLimit queries users.tenant_id, a column that does not exist in tenant DBs.
- **Recommendation:** Add a TenancyTestCase that creates two sqlite-file (or :memory: named-connection) tenant DBs through the real CreateDatabase/MigrateDatabase pipeline, seeds a user and token in each, and asserts: A's token on B returns 401; B's items are invisible to A; ?tenant=B combined with an A token returns 401; with no tenant resolved, ERP routes return 4xx rather than hitting the central DB. Run central and tenant migrations on separate connections.
- **Verification:** I confirmed this from the code.

**No test ever enters a tenant.**
- A grep of `backend/tests` for `X-Tenant`, `tenancy()`, `->initialize(`, `tenancy.` and `Tenancy::` returns nothing.
- Only four test files mention Tenant: `CentralTenantResolverApiTest`, `SuperAdminApiTest`, `SuperAdminSolidTest` and `POSSolidArchitectureTest`.
- `CentralTenantResolverApiTest` fakes the tenancy events and only calls `/api/v1/central/tenants/resolve` (and its alias). `ResolveApiTenancy` skips that route at its step 0.
- So `ResolveApiTenancy`'s header / `?tenant=` / body / host branches never run, and neither does its own 404 `tenant_not_found` response.

**Both schemas share one test database.**
- `TestCase.php:13-14` runs `migrate --path=database/migrations/tenant` into the same sqlite `:memory:` DB as the central migrations (`phpunit.xml` sets `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
- Running `comm` on the two migration folders shows exactly 10 shared filenames, so the migrator skips the tenant copies, as the finding says. I did not diff the users and activity_logs contents myself.

**The issues the tests would hide are real.**
- `ResolveApiTenancy.php:30-45` takes the tenant from the header, then `?tenant=`, then the body `tenant` field.
- After that, the host block at lines 48-70 still runs and can call `tenancy()->initialize()` a second time with a different tenant.
- When no tenant is found, the request goes through with no guard.
- `Plan` and `Subscription` extend plain `Model` with no connection pin.
- `Tenant::checkLimit` (`Tenant.php:103-111`) queries `User`/`Store`/`Item` `where('tenant_id', ...)`. No `tenant_id` column for these exists in the central migrations I grepped (`tenant_id` only appears in subscriptions, domains and impersonation tokens).

**Severity.** I kept it high but treat it as a test-coverage gap, not an exploit in itself. The middleware bugs listed under Impact should be filed as their own findings. The gap matters because the tenant boundary, the main security line of a database-per-tenant SaaS, has zero coverage, and the merged test schema hides errors that would fail in production.

### 16. [HIGH] Suspended or expired tenants keep full API access; status and subscription are only checked by the public resolver
- **Location:** `D:/projects/sroor/backend/app/Http/Middleware/ResolveApiTenancy.php:33`
- **Effort:** M
- **Evidence:** ResolveApiTenancy calls tenancy()->initialize($tenant) without looking at status, isSuspended(), isOnTrial() or isActive(). The only status check anywhere is ResolveTenantWorkspaceAction.php:48, used by GET /central/tenants/resolve. Searching app/ and routes/ for isSuspended/isOnTrial/isActive finds no other callers on Tenant. CentralTenantResolverApiTest::test_returns_403_when_tenant_is_suspended only covers the resolver.
- **Impact:** Once a super admin suspends a non-paying tenant or its trial runs out, the tenant's devices (which already know the tenant id or domain) keep selling, invoicing and syncing. The SaaS billing model is therefore not enforced.
- **Recommendation:** Add a CheckTenantSubscription middleware after tenancy is resolved, returning 402/403. Tests to add: suspended tenant + valid token gives 403 on /items; trial_ends_at in the past gives 403; active with subscription_ends_at in the future gives 200; extend_days via toggle-status restores access.
- **Verification:** The finding holds. I read the code and found no guard that blocks a suspended or expired tenant.

1. **The tenancy middleware never checks status.** `backend/app/Http/Middleware/ResolveApiTenancy.php` lines 33-37 and 57-68 find the tenant from the `X-Tenant` header, the `tenant` param, the domain or the subdomain slug, then call `tenancy()->initialize($tenant)` straight away. It reads no `status` field and does not call `isSuspended()`, `isActive()` or `isOnTrial()`.

2. **The token middleware checks only the user.** `backend/app/Http/Middleware/ApiTokenAuth.php` checks `$user->is_active`. It never looks at the tenant.

3. **Login does not check the tenant either.** `backend/app/Actions/Auth/ApiLoginAction.php` checks `user->is_active` (line 72) and then issues a Sanctum token. A suspended tenant's users can still log in and get new tokens.

4. **The web routes have no check.** `backend/routes/tenant.php:20` uses only `web`, `InitializeTenancyByDomain` and `PreventAccessFromCentralDomains`.

5. **No middleware or listener elsewhere adds a check.**
   - `bootstrap/app.php` registers only the role, permission, `store.scope` and `store.access` aliases.
   - `routes/api.php:19` wraps v1 in `ResolveApiTenancy` only.
   - In `TenancyServiceProvider`, the `TenancyInitialized` event only runs `BootstrapTenancy`.
   - `TenantFeatureManager` has only feature methods and no status or subscription logic.

6. **The status helpers have only one real caller.** A grep finds `isSuspended`, `isActive` and `isOnTrial` defined on `Tenant` (lines 156-179). The only tenant-status check is in `ResolveTenantWorkspaceAction.php:48`, the central resolver. `PaymentMethod::isActive` and `Subscription::isActive` are unrelated. The only test for suspension is `CentralTenantResolverApiTest::test_returns_403_when_tenant_is_suspended`, which covers that resolver alone.

**Impact:** a suspended tenant, or one whose subscription or trial has ended, keeps full API and web access. Its devices already have the tenant id or domain and a token, so they can keep selling, invoicing and syncing. The billing model is not enforced, so high severity is justified.

### 17. [HIGH] Plan limits and feature flags are not enforced anywhere on the server, and Tenant::checkLimit is broken
- **Location:** `D:/projects/sroor/backend/app/Models/Tenant.php:99`
- **Effort:** L
- **Evidence:** Searching app/ and routes/ for checkLimit/getAllLimits/max_users/max_stores/max_items/max_invoices finds only the Plan model, PlanResource and UpdatePlanRequest. checkLimit() and getAllLimits() have no callers. checkLimit queries User/Store/Item ->where('tenant_id', ...), but tenant DB tables have no tenant_id column, so it would throw or always count 0. TenantFeatureManager::isFeatureEnabled and Tenant::hasFeature are never called outside super-admin override toggling. The front-end FeatureGate.vue returns true when no tenant is loaded and is not used by any view. No test exists for any of this.
- **Impact:** Every plan is effectively unlimited: users, stores, items and invoices per month. Plan tiers and prices cannot be enforced, so there is no reason for a customer to upgrade.
- **Recommendation:** App fix: add a limit check inside Create{User,Store,Item}Action and invoice creation (counting within the tenant DB), plus a feature middleware such as 'feature:pos_offline'. Tests to add: with a plan of max_users=2, creating the third user returns 422/403 and no row is created; the invoices-per-month limit works at the month boundary; a disabled feature route returns 403 and a super-admin override then enables it.
- **Verification:** I checked the code and the finding holds. In backend/app and backend/routes, max_users, max_stores, max_items and max_invoices_per_month appear only in Plan.php, PlanResource.php, UpdatePlanRequest.php and Tenant::getAllLimits(). Tests and seeders also set these fields, but nothing reads them to enforce anything. Nothing in app/ or routes/ calls checkLimit(), getFeatureLimit(), getAllLimits(), getAllFeatures(), Tenant::hasFeature() or TenantFeatureManager::isFeatureEnabled() / resolveAllFeatures(). No route has a feature or limit middleware. The only plan-related route is the super-admin override-feature endpoint (routes/api.php:204). The one subscription check that is enforced is the suspension/expiry check in ResolveTenantWorkspaceAction.php:48. It covers suspended or expired accounts only, not quotas or features.

Tenant::checkLimit (Tenant.php:103-111) is broken in two ways:
1. It runs User/Store/Item::where('tenant_id', ...), but no tenant migration in database/migrations/tenant has a tenant_id column. With DB-per-tenant, that query would throw a column-not-found error.
2. It reads the limit through getFeatureLimit('limits.users'), which looks in plan->features, not plan->max_users. That key is not part of the plan columns, so the limit would come back as 0.

On the frontend, FeatureGate.vue returns true when there is no tenant (line 25-26) and also by default (line 46). Grepping resources/js for "FeatureGate" finds no other file using it. useModules.js reads a static config/modules.json, not the tenant's plan.

No test covers checkLimit, hasFeature or isFeatureEnabled. The plan tests only check that super-admins can create and update plans.

So every plan is effectively unlimited on users, stores, items and invoices, and plan feature flags are not applied. For a product meant to be sold as a SaaS with plan tiers, that is a real business-integrity gap, so high severity is justified. It is a missing feature rather than an exploitable security hole.

### 18. [HIGH] DeleteTenantAction and UpdateTenantDatabaseConfigAction are syntactically invalid PHP, and their endpoints have no tests
- **Location:** `D:/projects/sroor/backend/app/Actions/Tenants/DeleteTenantAction.php:12`
- **Effort:** S
- **Evidence:** `php -l` reports: 'Parse error: syntax error, unexpected token ")", expecting variable in app/Actions/Tenants/DeleteTenantAction.php on line 12' and 'unexpected token "," ... UpdateTenantDatabaseConfigAction.php on line 11'. The source has every $variable stripped, e.g. `public function execute(Tenant ): void { DB::transaction(function () use () { ->domains()->delete(); ->delete(); }); }`, which looks like PowerShell $-interpolation damage (commit 606da74b). The controller method-injects both classes (SuperAdminApiController.php:247, 268). Searching tests/ for destroyTenant, tenants.destroy and update-db-config finds nothing. These are the only two app files that fail php -l.
- **Impact:** DELETE /api/v1/super-admin/tenants/{id} and POST /tenants/{id}/update-db-config fail with a 500 (a ParseError during container resolution), so super admins cannot offboard tenants or fix DB credentials. The suite stayed green because no test touches these files.
- **Recommendation:** Route to backend-architect to restore the code. Add a `php -l` (or phpstan) CI step over app/. Add feature tests: destroy removes the tenant and its domains, and the DeleteDatabase job runs (faked); update-db-config persists only the whitelisted keys; a tenant user calling either gets 403.
- **Verification:** I confirmed this from the code. backend/app/Actions/Tenants/DeleteTenantAction.php:12 reads `public function execute(Tenant ): void` and the closure has `use ()` with `->domains()->delete(); ->delete();`. Every $variable has been stripped. UpdateTenantDatabaseConfigAction.php:11 is damaged the same way: `execute(Tenant , array )`, ` = [];`, `->update();`, `return ;`. Running `php -l` on each file gives the exact parse errors quoted in the finding (line 12 and line 11). git log shows both files were last changed in commit 606da74b ("ensure DeleteTenantAction has clean UTF-8 without BOM"), which fits a PowerShell rewrite stripping the $ signs. Both classes are wired into live code. SuperAdminApiController.php:247 (destroyTenant) and :268 (updateDatabaseConfig) method-inject them. routes/api.php:201-202 registers DELETE /tenants/{id} and POST /tenants/{id}/update-db-config. Resolving either class autoloads the broken file and throws a ParseError, so both endpoints return 500. No middleware, policy or fallback route avoids this. Searching tests/ for destroyTenant, update-db-config, DeleteTenantAction and UpdateTenantDatabaseConfig found nothing, so the suite never loads these files and stays green. High severity is justified because two super-admin tenant-management features are broken in production code with no test coverage. One mitigating point: the failure happens before any change, so no data is lost or corrupted.

### 19. [HIGH] There is no store isolation in the API: StoreAccess middleware is never used, X-Store-Id is trusted as sent, and show/cancel by id never check the store
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:160`
- **Effort:** L
- **Evidence:** ApiTokenAuth writes any numeric X-Store-Id into session('current_store_id') without checking that the user belongs to that store. The store.access alias (bootstrap/app.php) is not used on any route (grep of routes/*.php finds no store.access/StoreAccess). There are no global store scopes (no addGlobalScope anywhere; App\Scopes\TenantScope is defined but never applied). By-id lookups have no store filter: GetInvoiceDetailsAction.php:23, CancelSalesInvoiceAction.php:22, CancelPurchaseAction.php:22, PurchaseController.php:114, ReturnController.php:107, DeleteReturnAction.php:16, StockTransferController.php:121, CancelStockTransferAction.php:22, ExpenseController.php:166/179/201, GetShiftZReportAction.php:16, CloseShiftAction.php:23 (closes any open shift by id). InvoiceController::index (line 40) filters by store only when store_id/X-Store-Id is given, so a plain request lists every branch. Purchases, returns and shifts accept store_id=all. InvoicePolicy::view ignores $invoice->store_id. StoreController::stocks (line 195) and TreasuryController (line 34) read any store_id without an access check.
- **Impact:** A cashier or accountant assigned to branch A can read, cancel and close branch B's invoices, purchases, returns, expenses, shifts, Z-reports, treasury and stock, and can reverse branch B's stock and balances. This is unacceptable for a multi-branch SaaS where branch staff should not see each other's cash.
- **Recommendation:** Add store-isolation tests for each resource, with a non-admin user assigned only to store A and fixtures in store B. Cover: GET list with no header (expect only A), GET list with X-Store-Id=B and store_id=all (expect 403), GET /{id} of a B record (expect 403/404), and POST cancel/close/destroy on a B record (expect 403/404, with stock, balances and status unchanged). Resources: invoices, purchases, returns, transfers, shifts plus z-report, expenses, daily-journal, treasury/summary, dashboard, reports/*, stores/stocks, items/{id}/movements. All of these will fail today; report them as defects for backend-architect (central store-access resolver plus policies that check store_id).
- **Verification:** I confirmed this in the code. The only correction is the line number: ApiTokenAuth.php has 90 lines, and the X-Store-Id handling is at lines 82-86, not line 160. There it writes any numeric header value into session('current_store_id') without checking that the user belongs to that store. StoreAccess (app/Http/Middleware/StoreAccess.php) is the only middleware that checks user->stores(), and it is only registered as an alias in bootstrap/app.php:28. A grep finds no use of it in routes/ or app/. StoreScope is added only to the web group, and it only checks that the store exists and is active, not that the user may use it. A grep of app/Models finds no booted, addGlobalScope or store scope trait, and app/Scopes/TenantScope is never applied. The by-id lookups have no store filter, as claimed: GetInvoiceDetailsAction::execute (Invoice::with(...)->findOrFail), CancelSalesInvoiceAction (Invoice::findOrFail), CancelPurchaseAction, DeleteReturnAction, CancelStockTransferAction, GetShiftZReportAction, and CloseShiftAction (CashShift::where('status','open')->findOrFail). Their callers check permissions only. InvoiceController::show/index check hasRole('admin') || can('invoices.view') || can('pos.access'). CancelInvoiceRequest::authorize checks only the invoices.cancel permission. CloseShiftRequest::authorize lets any user with pos.sell close any open shift. InvoicePolicy::view/cancel ignore $invoice->store_id. InvoiceController::index (lines 40-58) filters by store only when store_id or X-Store-Id is sent, and 'all' or no value means no filter. StoreController::stocks (line 195) and TreasuryController::summary (line 34) take any store_id with no assignment check. The app clearly intends per-branch staff: StoreController::switchStore does check user->stores() and default_store_id. That check is the only one, and since ApiTokenAuth trusts the header, it is easy to get around. Two limits apply. Tenancy is DB-per-tenant, so this is cross-branch exposure inside one tenant, not cross-tenant. And the attacker must be an authenticated user who holds the matching permission (for example invoices.cancel or pos.sell). Still, a user limited to one branch can read other branches' data and cancel or reverse their invoices, purchases, transfers and shifts, which changes stock and balances. High severity is justified.

### 20. [HIGH] Write endpoints accept any store_id, so a branch user can sell, buy or transfer against another branch's stock
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\StorePOSInvoiceRequest.php:66`
- **Effort:** M
- **Evidence:** store_id is validated only with exists:stores,id (StorePOSInvoiceRequest.php:66, StoreSalesInvoiceRequest.php:42, StoreStockTransferRequest.php:19-20). InvoiceController::store (line 143), PurchaseController (line 127) and ReturnController (line 120) take X-Store-Id/store_id directly. ItemController::adjustStock (line 274) defaults to store 1. No test sends a foreign store_id.
- **Impact:** A cashier at branch A can POST /pos/checkout or /invoices with store_id=B, which deducts B's stock and records sales against B. A storekeeper can transfer stock out of a branch they don't belong to. Stock and cash reports per branch become unreliable.
- **Recommendation:** Add failing tests: a non-admin posts to pos/checkout, invoices, purchases, returns, transfers and items/{id}/adjust-stock with store_id or X-Store-Id of an unassigned store. Expect 403/422, with store_stocks, stock_movements and documents count unchanged.
- **Verification:** The core claim holds: no write path checks that a store_id belongs to the user.
- **Form requests:** StorePOSInvoiceRequest.php:66 checks store_id only with 'exists:stores,id'. Its prepareForValidation (lines 22-26) takes store_id from the input or the X-Store-Id header first. StoreStockTransferRequest.php:19-20 checks from_store_id and to_store_id only with exists, and its authorize() allows anyone with stores.view. AdjustStockRequest.php:19 does the same for store_id.
- **No guard elsewhere:** The StoreAccess middleware does check store membership against store_user and default_store_id. It is only given an alias in bootstrap/app.php:27-28 and is never attached to a route in routes/api.php or routes/tenant.php. ApiTokenAuth.php:83-85 writes any numeric X-Store-Id header into session current_store_id without checking it, and User::getCurrentStore() then trusts that session value as long as the store is active. StorePolicy::view and StorePolicy::switchStore have the right check, but only StoreController uses them; PosController::checkout, ProcessPOSInvoiceAction, InvoiceService::confirmInvoice (which uses $data['store_id'] directly) and the transfer controller do not.
- **Impact as claimed:** A user with pos.access can POST /api/v1/pos/checkout with another branch's store_id and deduct that branch's stock. A user with stores.view can transfer stock out of any branch.
- **Overstated details:** ItemController line 274 is lowStock, a read-only endpoint that falls back to store 1; it is not adjustStock. adjustStock at line 218 requires store_id but does not check membership either, so the gap is real there for a different reason than stated. I did not search exhaustively for a test that sends a foreign store_id, but no obvious one turned up.
- **Severity:** The leak stays inside one tenant, so it is an insider risk at branch level, not data crossing tenants. Branch isolation is an explicit project rule and stock and money integrity per branch is affected, so high is justified.

### 21. [HIGH] 14 permission names used in code are not in PermissionsSeeder. Seeded roles silently lose features, and the tests hide this by creating those permissions ad hoc
- **Location:** `D:\projects\sroor\backend\database\seeders\PermissionsSeeder.php:18`
- **Effort:** M
- **Evidence:** Used in app/routes/router but not seeded: items.manage, purchases.manage, stores.view, users.manage, settings.manage, reports.advanced, expenses.view, returns.view, returns.create, suppliers.view, pos.sell, system.manage, inventory.adjust, daily_journal.manage. Seeded but never checked: invoices.discount, items.view_cost, daily_journal.close_shift. Spatie checkPermissionTo catches PermissionDoesNotExist and returns false, so these checks fail silently. Concrete effects: the seeded storekeeper role (items.create/items.edit/transfers.create) gets 403 on POST/PUT /items (StoreItemRequest.php:13 and UpdateItemRequest.php:13 need items.manage), on POST /items/{id}/adjust-stock (AdjustStockRequest.php:13), on POST /transfers (StoreStockTransferRequest.php:13 needs stores.manage/stores.view) and on transfer cancel. StoreAppVersionRequest needs system.manage, so only super_admin passes through Gate::before. Tests create 'pos.sell' and 'users.manage' themselves. No test assigns the storekeeper or accountant roles.
- **Impact:** Customers who use the built-in roles find that storekeepers can't receive or create items, adjust stock or transfer stock, while the UI (resources/js/router/index.js:111/121/131/244 uses items.manage/stores.view) hides or shows pages inconsistently. Granting 'invoices.discount' or 'daily_journal.close_shift' has no effect.
- **Recommendation:** Add a test that collects every permission string used in app/ and routes/ (reflection or regex) and asserts each one exists after PermissionsSeeder. Add role-matrix tests with a DataProvider over the seeded roles (cashier, storekeeper, accountant) and expected status codes per endpoint. Stop creating permissions inside tests; use the seeder only.
- **Verification:** I confirmed this from the code. PermissionsSeeder.php:18-63 is the only place that creates permissions. I grepped app/ and database/ for Permission::create, firstOrCreate and findOrCreate, and the only hit is PermissionsSeeder.php:66. TenantProvisionerService.php:87 runs this seeder for every new tenant.

Checks in the code use names the seeder never creates:
- items.manage: StoreItemRequest:13, UpdateItemRequest:13, AdjustStockRequest:13, and ItemController:182/201 for destroy and toggle-active.
- stores.view / stores.manage: StoreStockTransferRequest:13 and CancelStockTransferRequest:13. The cancel check needs stores.manage, which is seeded, but the storekeeper role doesn't have it.
- system.manage: StoreAppVersionRequest:11.
- Also users.manage, settings.manage, reports.advanced, expenses.view, returns.view, returns.create, suppliers.view, pos.sell, inventory.adjust, daily_journal.manage and purchases.manage, in the controllers, policies and requests listed in the finding.

Spatie's HasPermissions::checkPermissionTo (vendor/.../HasPermissions.php:253-259) catches PermissionDoesNotExist and returns false, so these checks fail without any error.

Gate::before (AppServiceProvider:37-44) returns true for super_admin and admin, so those two roles are unaffected. Every other built-in role is affected. The storekeeper role is seeded with items.create, items.edit and transfers.create, so it gets 403 on creating, updating or deleting items, on adjust-stock, and on creating or cancelling transfers.

A tenant admin can't fix this from the UI. GetRolesMatrixAction lists stores.view, users.manage and reports.advanced, which don't exist. Saving them goes through UpdateRolePermissionsAction, which calls Role::syncPermissions, and that throws PermissionDoesNotExist (a 500 error) instead of granting the permission.

The seeded-but-never-checked names are also confirmed. invoices.discount, items.view_cost and daily_journal.close_shift appear only in the seeder, the permission-tree display and translation keys.

The tests do create permissions themselves: ShiftsAndDailyJournalApiTest:34 creates pos.sell and UsersAndRolesApiTest:33 creates users.manage. No test refers to the storekeeper or accountant roles.

One overstatement: the admin and super_admin roles are not affected. I'm keeping the severity at high anyway. The default non-admin roles can't do their main inventory work in a SaaS sold per role, and tenants can't repair it through the UI.

### 22. [HIGH] DELETE /returns/{id} soft-deletes an approved return without reversing stock or balances. Trash can then hard-delete it, and the test treats this as success
- **Location:** `D:\projects\sroor\backend\app\Actions\Returns\DeleteReturnAction.php:16`
- **Effort:** M
- **Evidence:** DeleteReturnAction does ReturnDocument::findOrFail($id)->delete(), with no transaction, no status check and no stock/customer/supplier reversal. ReturnDocument has no deleting hooks, and the only observer is TenantObserver. ForceDeleteTrashRecordAction.php:26-31 allows 'returns' (and 'stores', 'expenses') to be hard-deleted. tests/Feature/Api/ReturnsApiTest.php:251 test_can_delete_return_document only asserts assertSoftDeleted and never checks stock or balances. Invoices and purchases have no API delete route (cancel only) and are not in the trash force-delete map, which is good, but no test asserts that trash/invoices/{id}/force or trash/purchases/{id}/force is rejected.
- **Impact:** A deleted sales return leaves the returned quantity in stock and the customer credit in place, even though the document is gone from lists. Stock and customer balance no longer match their movements, and after a force-delete the audit trail is lost permanently. This breaks the 'no hard delete of approved docs' rule.
- **Recommendation:** Write a failing test: create a sales return, then DELETE it, and assert store_stocks quantity and customer current_balance go back exactly (decimal strings), or that the API returns 422 and requires a cancel flow. Add tests that trash restore/force with types invoices and purchases return 422 and leave rows intact, and that a trashed store with stock cannot be force-deleted (store_stocks is cascadeOnDelete in tenant migration 2026_08_11_180000:33).
- **Verification:** I confirmed this from the code and found no guard that prevents it.
- backend/app/Actions/Returns/DeleteReturnAction.php:16-17 only runs ReturnDocument::findOrFail($id)->delete(). There is no DB::transaction, no status check and no stock or balance reversal. The returns table has no status column (see $fillable), so every return is already posted when it is created.
- ReturnService::createSalesReturn and createPurchaseReturn post the effects immediately inside a transaction. Sales returns call stockService->addStock ('sales_return_in') and CustomerBalanceService::updateBalance. Purchase returns call deductStock ('purchase_return_out') and recompute supplier->current_balance. Deleting the return undoes none of this.
- backend/app/Models/ReturnDocument.php has no booted() method and no deleting or deleted hooks. No observer is registered for it under app/Observers or the providers.
- ReturnController::destroy (around line 138) only checks the role or returns.manage, the same permission used to create returns. In routes/tenant.php:203 the route uses can:returns.manage, and routes/api.php:153 has the same endpoint.
- backend/app/Actions/Trash/ForceDeleteTrashRecordAction.php:28 includes 'returns' => ReturnDocument::onlyTrashed()->findOrFail($id)->forceDelete(). That lets the document be removed permanently.
- RestoreTrashRecordAction.php:28 also allows restoring a return without re-applying anything. This part did not matter because nothing was reversed in the first place.
- tests/Feature/Api/ReturnsApiTest.php test_can_delete_return_document only checks assertStatus(200) and assertSoftDeleted. It never checks stock or balances.

Detail on the balance impact: CustomerBalanceService::updateBalance (lines 30-32) sums non-trashed returns. So the stored customer balance stays wrong after a delete. The next time anything triggers a recompute, the balance jumps silently with no matching document. The returned stock is never taken back out. Either way, stock and balances drift, and the StockMovement rows end up pointing at a document that was deleted or force-deleted. This breaks the 'reverse effects' and 'no hard delete of posted docs' rules.

I kept the severity at high. The gap is mainly in the business logic, not only in the tests, and anyone with returns.manage can reach it.

### 23. [HIGH] E2E default login is the seeded super-admin with a weak default password, and the deploy scripts run that same seeder on production
- **Location:** `D:/projects/sroor/e2e/auth/login.setup.js:16`
- **Effort:** S
- **Evidence:** login.setup.js:16-17 and e2e/flows/login-flow.spec.js:17-18 fall back to a hardcoded phone number and a trivial password literal when E2E_USER_PHONE/E2E_USER_PASSWORD are unset. The same literals are hardcoded with no env fallback in backend/tests/e2e/fixtures/auth.fixture.js:16-17, backend/tests/e2e/specs/01_auth_session.spec.js:16-17, unified_journey.spec.js:38-40 and unified_fresh_journey.spec.js:43-45. These match backend/database/seeders/DatabaseSeeder.php:20-30, which uses User::firstOrCreate with the same phone and bcrypt of the same password, then syncRoles([super_admin, admin]). The seeder has no environment guard. deploy_all_locations.py:46 and deploy_live_and_seed.py:63 run `artisan db:seed --force` on the remote host after `git reset --hard origin/main`.
- **Impact:** The credential pair committed in 6+ test files is very likely a working super-admin login on any production or tenant install that was first seeded by these deploy scripts (firstOrCreate does not overwrite an existing password, but it does create one on a fresh install). Anyone with repo access, or anyone who guesses a phone plus a trivial password, gets cross-tenant super-admin control. A second seeded super admin (DatabaseSeeder.php:33-38) also has a weak numeric password.
- **Recommendation:** Backend owner: guard the demo-user part of DatabaseSeeder with app()->environment(['local','testing']), take admin credentials from env on production, and rotate the password of that phone on every live install now. E2E: remove the literal fallbacks, require E2E_USER_PHONE/E2E_USER_PASSWORD (fail fast if missing), and seed a dedicated e2e-only user in a dedicated e2e DB.
- **Verification:** I confirmed the finding in the code, but one part of the impact is overstated.

What holds up:
- `e2e/auth/login.setup.js:16-17` falls back to a hardcoded phone and password when the E2E env vars are unset.
- I compared the strings programmatically. The same pair is hardcoded in `e2e/flows/login-flow.spec.js`, `backend/tests/e2e/fixtures/auth.fixture.js`, `backend/tests/e2e/specs/01_auth_session.spec.js`, `unified_journey.spec.js` and `unified_fresh_journey.spec.js`.
- That pair is identical to admin1 in `backend/database/seeders/DatabaseSeeder.php:21-30`. The seeder uses `User::firstOrCreate` with a bcrypt of the same all-digit 8-character password, then `syncRoles([super_admin, admin])`.
- admin2 at lines 33-42 also has an all-digit password. The seeder has no environment or production guard.
- `deploy_all_locations.py` cd's into three Hostinger `public_html` paths (lines 12-14 and 29), runs `git reset --hard origin/main` (line 34), then `php artisan db:seed --force` (line 46). `deploy_live_and_seed.py:55` and `:63` do the same.

What is overstated:
- The deploy scripts pull `origin/main`, not `feature/multi-tenant`. On main the seeder is at `database/seeders/DatabaseSeeder.php`, not under `backend/`.
- Main's seeder (lines 19-40) creates the same two phone/password pairs, which I verified are identical. But it only calls `syncRoles([$adminRole])`, with no `super_admin`.
- So the scripts as they stand today create a store-admin login with a trivial, publicly committed password on each fresh production install. They do not create a cross-tenant super-admin.
- The super-admin part (`super_admin.access` on `/api/v1/super-admin/*` in `routes/api.php:196+`, which includes tenant destroy and update-db-config) only applies once this branch is merged to main and deployed with the same scripts. Nothing in the code prevents that.

Other limits:
- `firstOrCreate` does not reset an existing password, so production is only exposed if the users were first created by the seeder and the password was never changed. Code alone cannot prove that.

Why it stays high: a full-admin login with a trivial, committed password is very likely working on production. The account becomes a cross-tenant super-admin once this SaaS branch is deployed.

### 24. [MEDIUM] CSV export routes have no auth, no permission check and no tenancy, and no test covers them
- **Location:** `D:/projects/sroor/backend/routes/web.php:278`
- **Effort:** M
- **Evidence:** routes/web.php:278-281 register /items/{id}/export-movements-csv, /customers/{id}/export-csv, /suppliers/{id}/export-csv and /items/export-csv at top level, outside any auth group. route:list shows their only middleware is 'web'. ExportController.php has no authorization: it calls Customer::findOrFail($id) and then ExportService::exportCustomerStatement, which streams invoices, payments and balances. routes/tenant.php has no export routes, so these never run with InitializeTenancyByDomain and always read the default (central) connection. No test file mentions export-csv or ExportController.
- **Impact:** Anyone who can reach the host, with no login, can fetch customer and supplier ledgers and the full inventory valuation as CSV, for any id they enumerate, from whatever DB the default connection points at. In tests this is the combined central+tenant sqlite DB. In production it is the central DB, which may still hold legacy single-tenant data (open question). In tenant mode the feature is also functionally broken, because it never reads the tenant DB.
- **Recommendation:** Defect for backend-architect: move the export routes under the tenant auth group (or under /api/v1 with ApiTokenAuth + ResolveApiTenancy) and add can:customers.statement / items.view middleware. QA: add ExportApiTest covering 401 (guest), 403 (no permission), 200 with exact decimal-string CSV rows, 404 for another store's customer, and a tenant-isolation case.
- **Verification:** The core claims hold up in the code. The impact is overstated, because the central schema has no customer or supplier tables.

What I confirmed:
(1) backend/routes/web.php:278-281 registers the 4 ExportController routes at top level. No Route::middleware/group wraps them; the only group closings are at lines 268, 322 and 334, all unrelated. So they get only the 'web' group.
(2) bootstrap/app.php appends just StoreScope to the web group. StoreScope (app/Http/Middleware/StoreScope.php) does nothing for guests: `if (Auth::check()) {...} return $next($request);`. There are no other global auth guards.
(3) app/Http/Controllers/ExportController.php has no authorize/can/policy call. It does Customer::findOrFail($id), Supplier::findOrFail($id) and Item::withTrashed()->findOrFail($id), then streams via ExportService.
(4) Customer, Supplier, Item and Invoice have no global scopes (no addGlobalScope/ScopedBy), so neither store nor tenant filtering applies.
(5) routes/tenant.php (the 'web' + InitializeTenancyByDomain + PreventAccessFromCentralDomains group) has no ExportController routes. web.php routes are host-agnostic, so these never initialize tenancy and always use the default/central connection.
(6) No test covers them. Only tests/Feature/Api/ActivityLogApiTest.php matches 'export-csv', and it tests /api/v1/activity-logs/export-csv, not ExportController.

Why medium, not high:
- The central migrations in backend/database/migrations have no customers, suppliers, items or invoices tables; they exist only in database/migrations/tenant. On a clean SaaS install, an unauthenticated hit on central most likely throws a table-not-found 500 rather than leaking data.
- Real data exposure needs the production central DB to still hold legacy single-tenant tables. That is plausible given the restore scripts, but the code does not show it.
- resources/js has no references to export-csv or export-movements, so the SPA does not use these routes. They are effectively dead, unprotected endpoints. In tenant mode the feature is functionally broken, as claimed.

Overall: a real missing-auth and missing-test defect with conditional data exposure.

### 25. [MEDIUM] The 'rollback' assertions in money/stock tests never run because they come after expectException
- **Location:** `D:/projects/sroor/backend/tests/Feature/ConcurrencyTest.php:100`
- **Effort:** M
- **Evidence:** ConcurrencyTest.php:100 calls $this->expectException(Exception::class), then calls confirmInvoice (which throws), and only after that runs lines 119-121 '$this->item->refresh(); $this->assertEquals('0.250', ...)'. That code is unreachable. InvoiceServiceTest.php:124-130 has the same pattern: the 'Verify rollback: stock remains 1.000 and 0 invoices created' assertions after the throwing call never run. ConcurrencyTest is also the only concurrency test, and it calls the service twice one after the other, so nothing exercises lockForUpdate under parallel access. expectException(Exception::class) is so broad that any QueryException would also count as a pass.
- **Impact:** The two tests that claim to prove 'oversell fails and stock/invoices roll back' never check either thing. If the transaction were removed or a partial write slipped through (stock decremented, invoice header saved), both tests would stay green. Atomicity of stock and money, the system's core integrity guarantee, has no real test.
- **Recommendation:** Rewrite these tests with try { ...; $this->fail('expected exception'); } catch (InsufficientStockException $e) {} and then assert stock '0.250', Invoice::count(), stock_movements count and customer balance. Use the specific domain exception class. Add a real concurrency test that runs two processes or connections against a file-based sqlite or MySQL DB, or at least verify that lockForUpdate is called on the stock row.
- **Verification:** The finding holds. In D:/projects/sroor/backend/tests/Feature/ConcurrencyTest.php, line 100 calls expectException(Exception::class) and line 102 calls confirmInvoice, which throws. The assertions on lines 120-121 (refresh, then assertEquals('0.250')) can never run, because PHPUnit ends the test method when the expected exception is thrown. The same pattern is in D:/projects/sroor/backend/tests/Feature/InvoiceServiceTest.php at lines 124-130: the "Verify rollback" checks on current_stock and Invoice::count() are dead code. Both tests catch the root Exception class, so any QueryException or other error would also pass. The test calls the service twice in a row with no parallel access, so lockForUpdate is never exercised under contention. I searched for other coverage and found none: no try/catch-then-assert rollback test, no assertDatabaseCount on invoices after a failed sale, and no API-level oversell test that checks the DB stays unchanged. StockTransfersApiTest's "rollback" test is about cancelling a transfer, not about a failed transaction. PurchaseCancelAndRestoreFeatureTest.php:123 and StockAdjustmentFeatureTest.php:90 also use expectException(\Exception::class) and may have the same dead-code pattern, but I did not read them. Even so, .claude/rules/testing.md requires a rollback test and points to ConcurrencyTest as the reference, and that reference is ineffective, so the gap is real. I lowered the severity from high to medium because this is a missing test, not a defect shown in production code. The partial-write regression the finding describes is hypothetical, and I did not find a failure in InvoiceService itself.

### 26. [MEDIUM] 27 routes, including destructive super-admin operations, have no test
- **Location:** `D:/projects/sroor/backend/routes/api.php`
- **Effort:** L
- **Evidence:** Coverage matrix built from `php artisan route:list --json` (311 routes, 150 in scope) by grepping test method bodies, with string concatenation normalized: 123 tested, 27 untested. Untested routes: SuperAdminAppVersionController (index, store, destroy, toggle-active, the whole controller); SuperAdminApiController showTenant, destroyTenant, runTenantMigrations, updateDatabaseConfig, updateTenantUnits; DELETE stores/{id}; DELETE suppliers/{id}; reports/top-items, reports/treasury, reports/items/{id}/card; auth/workspace-users; dashboard/summary; app/download-latest-apk; ping; all 5 web POSController routes (pos, invoices/create, pos/invoices, pos/customers, pos/customer-last-price); all 4 ExportController routes. ReportPrintController.php has no route at all, so it is dead code.
- **Impact:** Tenant deletion, per-tenant DB credential changes, remote migrations and APK uploads (file upload into public storage) can all ship broken or under-authorized, and nothing would catch it. These are the riskiest operations in a SaaS control plane. Treasury/top-items reports are money numbers with no correctness test.
- **Recommendation:** Priority order: (1) SuperAdmin destroyTenant, update-db-config and run-migrations: 401/403 for a tenant admin, 422, 404, happy path with Event::fake on tenancy jobs. (2) SuperAdminAppVersionController with Storage::fake, mime/size 422, 403. (3) reports/treasury and top-items with exact decimal assertions. (4) Delete ReportPrintController or wire it up.
- **Verification:** Mostly confirmed from the code; I lowered severity from high to medium because this is a gap in test coverage, not a defect anyone has shown in the code.

What the code shows:
- The super-admin routes are at backend/routes/api.php:196-220. They include destroyTenant (line 201), updateDatabaseConfig (202), updateTenantUnits (205) and runTenantMigrations (206), plus SuperAdminAppVersionController index/store/toggle-active/destroy (217-220).
- backend/tests/Feature/Api/SuperAdminApiTest.php only calls dashboard, tenants index/store, toggle-status, override-feature, plans, settings and units.
- backend/tests/Feature/SuperAdminSolidTest.php covers the index, toggle, override and plan actions. It does not cover delete, the DB-config change, migrations or units.
- Searching tests/ for SuperAdminAppVersion, app-versions, DeleteTenantAction, UpdateTenantDatabaseConfig, run-migrations, top-items, reports/treasury, /card, workspace-users, /ping, download-latest-apk, ExportController and the web POSController routes (routes/tenant.php:82-86) found nothing.
- AppUpdateApiTest calls /app/download-apk, not /app/download-latest-apk.
- StoresApiTest and SuppliersApiTest have no delete calls.
- ReportsApiTest covers summary/comprehensive/items/stores/customers/expenses/inventory, but not treasury, top-items or the item card.
- ReportPrintController (app/Http/Controllers/ReportPrintController.php) is not referenced from any route, so it is dead code.

Why the severity is lower than claimed:
1. These are missing tests, not proven bugs or authorization holes. The whole group sits behind the `can:super_admin.access` middleware (api.php:196). That gate is tested for unauthenticated and unauthorized users, though only through /super-admin/dashboard.
2. dashboard/summary is partly covered. DashboardApiControllerTest tests GetDashboardApiOverviewAction and DashboardOverviewResource directly, with numeric asserts on sales, COGS and profit. It just doesn't go through HTTP.
3. ping is a trivial closure, and the web POS routes repeat logic that is tested through /api/v1/pos/*.

The gap is still real and worth fixing. Tenant deletion, per-tenant DB credential changes, remote tenants:migrate (controller line 222) and APK upload/delete have no tests at the HTTP or action level, and neither do the treasury and top-items money reports.

### 27. [MEDIUM] Rollback assertions placed after expectException never run
- **Location:** `backend/tests/Feature/ConcurrencyTest.php:100`
- **Effort:** S
- **Evidence:** ConcurrencyTest.php:100 calls expectException, then confirmInvoice throws, and the `assertEquals('0.250', ...)` at lines 120-122 is unreachable. InvoiceServiceTest.php:124-130 has the same problem: 'Verify rollback: stock remains 1.000 and 0 invoices created' never executes. PurchaseCancelAndRestoreFeatureTest.php:123-126 and StockAdjustmentFeatureTest.php:90-91 assert only that the exception is thrown. They never check that state is unchanged.
- **Impact:** These are the suite's only 'rollback' tests, and they prove nothing about rollback. A regression that commits a partial invoice, stock movement or payment before throwing would still pass.
- **Recommendation:** Replace the pattern with try { ...; $this->fail(); } catch (Exception) {} followed by exact assertions: item.current_stock, the store_stocks quantity, Invoice::withTrashed()->count(), StockMovement count, Payment count and customer.current_balance. Use a 2-line invoice where line 2 fails so the test proves line 1's deduction rolls back.
- **Verification:** The finding is real. I checked each cited test in the code.

- **backend/tests/Feature/ConcurrencyTest.php:100** calls `$this->expectException(Exception::class);` and then `confirmInvoice(...)`, which throws. The lines after it, `$this->item->refresh(); $this->assertEquals('0.250', ...)` at lines 119-121, never run. The `assertEquals('0.250')` at line 96 does run, but it only checks the first sale, which succeeds.
- **backend/tests/Feature/InvoiceServiceTest.php:124-130** has the same pattern. `expectException` comes before `confirmInvoice`, and the assertions under the comment "Verify rollback: stock remains 1.000 and 0 invoices created" (`current_stock == '1.000'` and `Invoice::count() == 0`) never run.
- **backend/tests/Feature/PurchaseCancelAndRestoreFeatureTest.php:123-126** and **backend/tests/Feature/StockAdjustmentFeatureTest.php:90-97** only check the exception type and message. They assert nothing about state after the throw.
- I found no other test that checks state after a failure. No test uses try/catch or `assertThrows`. The 422 API tests I sampled (InvoiceApiTest:229-240, PosApiTest:173-182) only cover form-request validation (empty `items`), which never reaches the service transaction. Some files use `assertDatabaseMissing`/`assertDatabaseCount` (AuthApiTest, TrashApiTest, SettingsProfileTrashApiTest), but those are unrelated to stock or money rollback.

So it is accurate that no test proves a failed sale, purchase cancel or adjustment leaves stock, invoices, movements or payments unchanged.

I lowered the severity from high to medium for two reasons:
1. This is a gap in test coverage, not a production defect. The services may well roll back correctly.
2. PHPUnit runs each test inside its own wrapping transaction, so even a working test would only show that the service's nested transaction or savepoint rolled back, not that it did so under real conditions.

The fix is cheap: wrap the throwing call in try/catch or `assertThrows`, then assert stock, `Invoice::count()`, stock movements and treasury rows. Still worth doing, because these are the only tests that claim to cover the core invariant ("no partial stock or money mutation").

### 28. [MEDIUM] No real concurrency test exists, and the sqlite :memory: suite cannot verify lockForUpdate
- **Location:** `backend/phpunit.xml:27`
- **Effort:** L
- **Evidence:** phpunit.xml sets DB_CONNECTION=sqlite with DB_DATABASE=:memory:. SQLite's grammar compiles lockForUpdate() to nothing, and :memory: runs on a single connection. A grep of tests/ finds no second DB connection, no Process/pcntl/proc_open and no parallel workers. ConcurrencyTest has one test with two sequential confirmInvoice calls, which is really an insufficient-stock validation test. Race-prone mutation paths with no oversell or double-spend test: (1) TreasuryService::transfer checks the balance at line 142 OUTSIDE the transaction and without a lock, then inserts at 151, which is a TOCTOU double-spend. (2) ShiftService::openShift checks for an active shift and then inserts (44-60) with no transaction or lock, so a store can end up with two open shifts. Shift numbers are count()+1 (49). (3) closeShift has no lock (159). (4) Document numbering in InvoiceService::generateUniqueNumber (672), PurchaseService (473), ReturnService (183) and StockTransferService (270) uses MAX+1 with no lock, so concurrent checkouts hit unique-constraint 500s. Expense, ADJ and TRF numbers use the last 4 hex digits of uniqid() (InvoiceService.php:167, StockService.php:297, TreasuryService.php:152), which collide often. (5) PaymentService::recordCustomerPayment (33-48) does not prevent concurrent overpayment beyond remaining_amount. (6) Lock-order inversion: confirmInvoice locks Customer then Item (29, 61), while cancelInvoice locks Invoice, then Item, then Customer (via updateBalance at 316). That is a deadlock risk on InnoDB with DB::transaction attempts=1.
- **Impact:** The core 'no double-sell' guarantee in AGENTS.md is untested. On production MySQL, two cashiers selling the last 0.250 kg, or two treasury transfers draining the same account, have never been exercised.
- **Recommendation:** Add a MySQL-backed test group (for example a phpunit-mysql.xml run in CI with a disposable DB). Use two PDO connections, or Process::pool running artisan commands, to race deductStock/confirmInvoice, TreasuryService::transfer, openShift and generateUniqueNumber. Assert exactly one winner and stock >= 0. Mark the group so the default sqlite run skips it explicitly instead of passing silently.
- **Verification:** I checked this against the code and it holds. Five checks:

1. **Test database:** backend/phpunit.xml:27-28 sets DB_CONNECTION=sqlite and DB_DATABASE=:memory:, so the suite runs on one connection.
2. **No real concurrency anywhere in tests:** a grep of tests/ finds no lockForUpdate assertions, no pcntl, proc_open or Process calls, and no second DB::connection.
3. **ConcurrencyTest is not a race test:** tests/Feature/ConcurrencyTest.php has a single test, test_overselling_beyond_stock_fails_and_maintains_data_integrity. It makes two sequential confirmInvoice calls and checks the insufficient-stock error, nothing parallel.
4. **The race-prone code is real:**
   - TreasuryService::transfer runs getBalances and the sufficiency check (about lines 141-148) before DB::transaction and without a lock. The TRF number is built from the last 4 characters of uniqid().
   - ShiftService::openShift does getActiveShift, then count()+1, then CashShift::create, with no transaction and no lock.
   - InvoiceService::generateUniqueNumber (672) reads the last number with orderBy desc ->first() and no lock.
   - Lock order differs between paths: confirmInvoice locks Customer (29) then Item (61), while cancelInvoice locks Invoice (285) then Item (295).
5. **Why I lowered the severity to medium:** the main stock path does take row locks. confirmInvoice, cancelInvoice and updateInvoice call lockForUpdate on Item inside DB::transaction, so the "no double-sell" guarantee is built in code. It just isn't tested. As a Testing & QA finding, this is a missing-coverage gap, not a defect.

The actual race bugs in the code (the treasury transfer double-spend, duplicate open shifts, MAX+1 and uniqid numbering) are separate correctness findings and could each be high on their own.

### 29. [MEDIUM] Specs are render/screenshot smoke checks; no spec asserts a business outcome (sale totals, stock after sale, balances)
- **Location:** `D:/projects/sroor/e2e/flows/pos-full-page-audit.spec.js:59`
- **Effort:** L
- **Evidence:** pos-full-page-audit only asserts that the 'حفظ واعتماد الفاتورة' button is visible and at least 44px tall. Nothing in any flow ever clicks checkout. The pinned-item test (lines 115-123) is wrapped in `if (await quickItemBtn.isVisible())`, so it passes vacuously when no item is pinned. Across 39 flow files: 0 toHaveText/toHaveValue, only 8 fill() calls (mostly search boxes and the login), 93 `if (await ...isVisible)` conditional guards, and no assertion on any total, stock quantity, balance or 3-decimal amount (a grep of expect lines for total/إجمالي/رصيد/stock/.ddd only hits static headings). create-purchase, create-return and create-stock-transfer only check that headings and the submit button are visible. Profile dark/light toggles click with no assertion (profile-full-page-audit.spec.js:60-73).
- **Impact:** For a POS/ERP SaaS, the critical journeys (sell 0.250 kg, then the invoice total, stock decrement, treasury/shift cash, cancel and reverse, purchase increasing stock, transfer between branches, return) have zero E2E coverage. A broken checkout, a wrong total in the cart or a regressed stock deduction would pass the whole suite green.
- **Recommendation:** Add real journey specs: POS sale with a fractional quantity, asserting the cart total string, the success state, the invoice in /invoices, the stock in /stores/stocks and the item movement. Also cancel invoice → stock restored, purchase create → stock up, transfer create → both stores updated, and return. Verify totals through the API (request fixture) as well as the UI. Replace `if visible` guards with hard expectations backed by seeded data.
- **Verification:** The facts in the finding check out against the Playwright code.

What I confirmed:
- In e2e/flows/pos-full-page-audit.spec.js:59-65 the checkout button 'حفظ واعتماد الفاتورة' is only checked for being visible and at least 44px tall. Nothing clicks it.
- The pinned-item test (lines 115-123) sits inside `if (await quickItemBtn.isVisible(...))`, so it passes without testing anything when no item is pinned.
- The only submit click in all 39 flow files is the login button (login-flow.spec.js:35).
- There are 0 toHaveText/toHaveValue assertions. The toContainText hits are all page headings.
- There are 8 fill() calls and 97 `if (await` guards.
- The profile theme toggles (profile-full-page-audit.spec.js:60-73) click with no assertion.
- The crawler spec and interaction-helper deliberately avoid destructive or critical buttons.
So the E2E suite really is a render and smoke check, with no checkout, purchase, return or transfer journey.

Why I lowered the severity:
The claim that a wrong total or a broken stock deduction "would pass the whole suite green" goes too far. backend/tests/Feature has PHPUnit tests for these outcomes at the service and API level: FractionalWeightSaleTest, InvoiceServiceTest (6 tests), ConcurrencyTest, CustomerBalanceTest, PurchaseServiceTest, PurchaseCancelAndRestoreFeatureTest, ReturnServiceTest, StockAdjustmentFeatureTest and MultiStorePhase1/2. They cover stock, quantity, balance, cancel and purchase. A backend regression in stock deduction or totals would most likely be caught there.

What remains a real gap:
- There is no end-to-end test of the Vue SPA checkout path: cart total math in the browser, the payload sent to the API, and the result shown on screen.
- The E2E specs can pass without testing anything because of the conditional guards.
That is worth fixing, but it is medium rather than high, because the business rules do have automated tests at the PHPUnit layer.

### 30. [MEDIUM] All specs run as one super-admin+admin user, so permission-hidden actions and tenant isolation are never exercised in E2E
- **Location:** `D:/projects/sroor/e2e/auth/login.setup.js:16`
- **Effort:** L
- **Evidence:** There is a single auth-setup project and a single storageState. The seeded user has roles super_admin+admin (DatabaseSeeder.php:30). In the SPA, auth store hasPermission() returns true for any admin/super_admin role (resources/js/stores/auth.js:159), so every permission-gated button and route is always visible. Only users-full-page-audit and roles-full-page-audit mention roles, and only to check that the roles link is visible. No spec sets X-Tenant or a tenant host, visits /connect (WorkspaceConnectView, the tenant-selection entry point of the SaaS), or logs in as a limited cashier. Super-admin specs and tenant specs share the same session.
- **Impact:** A cashier seeing the cancel/delete/cost-price buttons, a router permission guard regressing, or tenant A's UI showing tenant B data would never be caught. These are the most important properties of a sellable multi-tenant SaaS.
- **Recommendation:** Add auth-setup projects for: a super admin (central only), a tenant admin on tenant A, a cashier on tenant A with pos.access only, and an admin on tenant B. Write specs that assert hidden actions and redirects for the cashier, and that tenant B cannot see tenant A's items/invoices through the UI or by direct URL/id.
- **Verification:** The finding is real, but it overstates how much is left untested.

**What the code confirms:**
- playwright.config.js has one `auth-setup` project. The desktop, tablet and mobile projects all use the same storageState file, `e2e/.auth/user.json`.
- `e2e/auth/login.setup.js:16` logs in by default with phone [REDACTED_PHONE].
- In `DatabaseSeeder.php:30`, that user gets `syncRoles([$superAdminRole, $adminRole])`.
- In `resources/js/stores/auth.js:159`, `hasPermission` returns true for any user with the admin or super_admin role. So every permission-gated element is always visible in the E2E runs.
- A grep of `e2e/` finds no X-Tenant header, no `/connect` visit and no second user.
- "Cashier" appears only in `roles-full-page-audit.spec.js:62`, where the admin clicks the cashier role card in the role editor. No test ever logs in as a cashier.
- The super-admin specs and the tenant specs share the same session.

So the E2E suite cannot catch:
- permission-hidden UI regressions
- router guard regressions
- tenant-selection flow regressions
- cross-tenant leaks in the UI

**Why I lowered the severity:** the backend PHPUnit suite tests these rules at the API layer:
- 25 `assertForbidden`/403 checks across the Feature/Api tests, including PermissionApiTest, PermissionsAndContextApiTest and UsersAndRolesApiTest.
- Tenant resolver tests in CentralTenantResolverApiTest, plus SuperAdmin tenant tests.

A cashier who can see a cancel button should still be blocked by the API, assuming those 403 tests cover the endpoint. That makes most of the claimed impact a UI/UX visibility gap, not a security hole that slips through untested. True cross-tenant data isolation is also mostly a backend concern, and PHPUnit is the better place to test it.

The gap is a real test-coverage weakness for a multi-tenant SaaS, but medium fits better than high.

### 31. [MEDIUM] AppUpdateApiTest.php:165 failure comes from an app bug plus a local file: a request for the iOS update gets the Android APK
- **Location:** `D:/projects/sroor/backend/app/Actions/AppVersions/DownloadLatestApkAction.php:28`
- **Effort:** S
- **Evidence:** I re-ran the full suite: 306 tests, 305 passed, 1 failed, 1389 assertions, 275.6s (slower than the lead's 108s, probably because other jobs were running at the same time). The isolated run also fails with 'Expected 404 but received 200'. Cause: when no AppVersion row exists, the code checks fallback paths at lines 28-38. Any platform other than 'windows' gets the ANDROID list (public_path('sroor-cofe-erp-2m.apk'), public_path('app.apk'), ...). Both files are on this machine: backend/public/app.apk and backend/public/sroor-cofe-erp-2m.apk, about 87 MB each. They are gitignored (.gitignore:75 '*.apk'), so the test only fails on machines that have them. The test uses Storage::fake('public'), but that cannot hide public_path() files that are read with file_exists() (line 44).
- **Impact:** In production, an iOS client calling /api/v1/app/download-apk?platform=ios gets an 87 MB Android APK with Content-Type application/octet-stream instead of a 404. The suite is also environment-dependent: it passes on a clean CI checkout and fails on dev machines, which teaches people to ignore red builds. The fallback paths are hardcoded with legacy 'sroor-cofe' names, which does not fit a generic SaaS.
- **Recommendation:** Defect for backend-architect: apply the fallback list only to 'android' (and 'windows'), and throw a 404 for 'ios'. On the test side, keep the assertion and do not weaken it. Add tests for platform=android with no row and no fallback (expect 404), and for the case where a fallback exists. Make the fallback paths configurable so tests can point them at a temp directory.

### 32. [MEDIUM] A test makes a real HTTPS call to api.telegram.org, and nothing in the suite blocks outbound HTTP
- **Location:** `D:/projects/sroor/backend/tests/Feature/Api/SettingApiTest.php:152`
- **Effort:** S
- **Evidence:** test_can_send_test_telegram_notification POSTs to /api/v1/settings/telegram/test with bot_token 'test_bot_token_123'. SettingController::sendTestTelegram (app/Http/Controllers/Api/SettingController.php:100-122) saves the token with Setting::set and calls TelegramService::sendMessage, which runs Http::timeout(10)->post('https://api.telegram.org/bot{token}/sendMessage') (TelegramService.php:74). The service is enabled by default (config/services.php:41 env default true), and phpunit.xml does not override TELEGRAM_*. Nothing in tests/ calls Http::fake, Http::preventStrayRequests, Queue::fake or Notification::fake. A junit timing shows this test takes 2.66s, against about 1.25-1.48s for its sibling tests, which fits a real network round-trip. The test only checks assertJsonStructure(['success','message']), so it passes whether Telegram accepts or rejects the request. The endpoint has no FormRequest validation for bot_token/chat_id.
- **Impact:** The suite is non-hermetic: it is slow, depends on the network, and could fail or hang in CI without internet. The test proves nothing about behaviour. Backend .env contains a TELEGRAM_BOT_TOKEN entry (value not reproduced), so any future test that hits a Telegram path without passing a token would send real messages to production chats.
- **Recommendation:** Add Http::preventStrayRequests() in TestCase::setUp, and set TELEGRAM_NOTIFICATIONS_ENABLED=false and an empty TELEGRAM_BOT_TOKEN in phpunit.xml. Rewrite the test with Http::fake(['api.telegram.org/*' => Http::response(['ok'=>true])]) and Http::assertSent(...) on chat_id and text, plus a failure case (fake 400 means success=false). Add 422 cases for bot_token and chat_id once a FormRequest exists.

### 33. [MEDIUM] ApiTokenAuth's central-admin fallback (maps a central token to a tenant user by phone) and its plaintext api_token fallback have no tests
- **Location:** `D:/projects/sroor/backend/app/Http/Middleware/ApiTokenAuth.php:51`
- **Effort:** M
- **Evidence:** Lines 44-48 accept a plaintext users.api_token column match. Lines 51-63: inside a tenant context, a token that resolves to a central user with role 'admin' is mapped to the tenant user with the same phone: User::where('phone', $centralUser->phone). The tenant comes from the caller-controlled X-Tenant header. No test covers either fallback, and grep finds no test that sends X-Tenant.
- **Impact:** This is an authentication path that crosses the central/tenant boundary, and nothing tests it. If any non-platform account has the central 'admin' role (for example tenant owners registered centrally), that account could act as a same-phone user in any tenant it names. A regression here is a cross-tenant compromise.
- **Recommendation:** Add tests: central admin token plus X-Tenant for a tenant with no matching phone gives 401; a central non-admin token gives 401; an inactive tenant user gives 401; a central admin token works only for the intended super-admin flow. Ask backend-architect to confirm who holds the central 'admin' role, and to consider replacing the phone match with explicit impersonation tokens (a tenant_user_impersonation_tokens table already exists).

### 34. [MEDIUM] 15 tenant routes point at controller methods that do not exist
- **Location:** `D:/projects/sroor/backend/routes/tenant.php:90`
- **Effort:** S
- **Evidence:** I checked every route action in route:list against the controller source. These have no matching method: InvoiceController@edit, @update, @destroy, @restore (tenant.php:90-94); PurchaseController@create (194); ReturnController@create (201); DailyJournalController@openShift, @closeShift, @storeExpense; SettingController@downloadBackup, @clearCache, @sendBackupTelegram, @sendDailySummaryTelegram, @sendLowStockTelegram, @sendOverdueShiftTelegram.
- **Impact:** Each of these URLs returns a 500 (BadMethodCallException) when called. Some are user-facing actions (invoice edit, restore, delete; settings backup download), so a cashier or admin hits a server error. route:list does not catch this, and no test exercises the routes.
- **Recommendation:** Defect for backend-architect: remove the dead routes or implement the methods. QA: add a RoutesResolveTest that loops over Route::getRoutes() and asserts that each controller@method exists. This is cheap and catches the whole class of bug.

### 35. [MEDIUM] Tested routes rarely check validation errors, permission denials or not-found cases
- **Location:** `D:/projects/sroor/backend/tests/Feature/Api`
- **Effort:** L
- **Evidence:** Of the 123 tested routes, the tests that touch each route check: happy path with DB-state assertion (assertDatabaseHas/Missing/SoftDeleted/refresh) on 52, 422 on 29, 401 on 28, 403 on 25, 404 on 2, and isolation on 7 (super-admin only). Write endpoints with no 403 test include POST purchases, returns, transfers, transfers/{id}/cancel, purchases/{id}/cancel, suppliers/{id}/pay, customers/{id}/collect-payment, items/{id}/adjust-stock, stores CRUD and users CRUD. Money endpoints with no 422 test include collect-payment, suppliers/{id}/pay, adjust-stock and transfers/{id}/cancel. StockTransferController@cancel has no DB-state assertion.
- **Impact:** A missing permission check on a money or stock mutation (supplier pay, collect payment, adjust stock, cancel purchase or transfer) would go unnoticed. Negative amounts and over-precision decimals on payment endpoints are unverified.
- **Recommendation:** For each money/stock write endpoint, add a #[DataProvider] for 422 cases (missing, negative, 4-decimal, non-numeric, foreign key from another store), a 403 for a user without the permission, a 404 for a missing or soft-deleted id, and exact decimal-string DB assertions.

### 36. [MEDIUM] Purchase cancel doesn't revert weighted_avg_cost or cost_price, and restore recomputes WAC a second time
- **Location:** `backend/app/Services/PurchaseService.php:246`
- **Effort:** M
- **Evidence:** createPurchase writes cost_price and weighted_avg_cost (lines 118-127). cancelPurchase (246-329) only deducts stock and deletes payments, leaving both cost fields as the cancelled purchase set them. restorePurchase (351-360) blends the line cost into WAC again. PurchaseCancelAndRestoreFeatureTest asserts stock and supplier balance but never weighted_avg_cost or cost_price, and every fixture starts from stock 0, where the error is invisible.
- **Impact:** Example: 10 kg at 100 in stock, then a purchase of 10 at 300 gives WAC 200. Cancel leaves 10 kg at WAC 200 instead of 100. COGS and profit on later sales are wrong, and cancel/restore cycles keep shifting the cost.
- **Recommendation:** Add tests that start with existing stock and assert exact weighted_avg_cost and cost_price strings after create, cancel and restore. Report the missing WAC reversal.

### 37. [MEDIUM] A WAC of '0.000' never falls back to cost_price because the string is truthy
- **Location:** `backend/app/Services/PurchaseService.php:120`
- **Effort:** S
- **Evidence:** `currentWac: (string)($item->weighted_avg_cost ?: $item->cost_price)`. With the decimal:3 cast, weighted_avg_cost is the string '0.000', which is truthy in PHP, so the fallback never fires. The same pattern appears at PurchaseService.php:353, ProfitLossService.php:74 and :93, InventoryAnalyticsService.php:68 and ReorderAssistantService.php:59. weighted_avg_cost defaults to 0 (items migration line 19). depositStock (StockService.php:201-203) sets only cost_price. InvoiceService.php:82 does it correctly with bccomp.
- **Impact:** An item with opening stock but a zero WAC (for example created by deposit or import) gets its WAC halved on the next purchase: 10 at cost 100 plus 10 at 100 gives a WAC of 50. P&L then uses a cost of 0 and overstates profit.
- **Recommendation:** Add a test: an item with stock 10, cost_price 100 and WAC 0.000, then a purchase of 10 at 100, asserting WAC === '100.000'. Add a P&L test with a WAC-0 item. Report all six call sites.

### 38. [MEDIUM] Float casts on the POS and blender paths, plus 4-decimal blend quantities that drift against DECIMAL(12,3)
- **Location:** `backend/app/DTOs/POSInvoiceItemDTO.php:74`
- **Effort:** M
- **Evidence:** POSInvoiceItemDTO casts quantity and unitPrice to float (lines 74-83), and POSInvoiceDTO casts discountValue and paidAmount to float (17-18, 39-40), then both are converted back with (string). A large value becomes '1.0E+15', which bcmath rejects with a ValueError (HTTP 500). CreateBlenderInvoiceAction.php:25 computes kg = bcdiv(grams,'1000',4), so 125.4 g becomes '0.1254'. StockService.php:78 truncates with bcsub(...,3) and deducts 0.126, while MySQL DECIMAL(12,3) rounds invoice_items.quantity to 0.125, and cancel adds back the stored 0.125. The request allows grams down to 0.1 (CreateBlenderInvoiceRequest.php:51). sqlite stores the 4-dp value and the decimal:3 cast rounds it on read, so the suite cannot see the drift. CoffeeBlenderApiTest.php:248-249 asserts with (float).
- **Impact:** Fractional-gram blends leak about 0.001 kg of stock per sale and cancel cycle, and line totals are priced on a different quantity from the one deducted. Some numeric inputs crash checkout.
- **Recommendation:** Add blender tests with grams=125.4 and 0.1 that assert exact string stock and invoice_items quantities, and run them against MySQL. Add a POS test with quantity '0.333' and unit_price '1000000000.125'. Recommend string DTO fields and rounding to 3 dp before stock calls.

### 39. [MEDIUM] Treasury per-store balance mixes all stores' payments with one store's expenses
- **Location:** `backend/app/Services/TreasuryService.php:34`
- **Effort:** S
- **Evidence:** In getBalances, inflows (34-37), supplierOutflows (40-43) and expensePayments (52-55) ignore $storeId, while expenses (46-49) and transfers (60-75) filter by it. Payments of cancelled invoices are still counted. TreasuryApiTest.php:131-147 seeds Invoice::create with paid_amount but creates no Payment row, so the inflow path is never exercised. Its assertions are float literals inside assertJson, which compares loosely.
- **Impact:** Branch treasury balances are wrong in multi-branch tenants, and TreasuryService::transfer's sufficiency check uses this inflated figure.
- **Recommendation:** Add a two-store treasury test that creates sales through InvoiceService and asserts exact per-store balances, including after a cash invoice is cancelled.

### 40. [MEDIUM] API money tests use loose float comparisons and seed documents directly, bypassing the services
- **Location:** `backend/tests/Feature/Api/InvoiceApiTest.php:305`
- **Effort:** M
- **Evidence:** `assertEquals(40.000, (float)Item::find(...)->current_stock)` (InvoiceApiTest 305, 316). Likewise PurchasesApiTest 261 and 274, StockTransfersApiTest 265-289, CoffeeBlenderApiTest 248-249, PosApiTest:171 and POSSolidArchitectureTest:94. With a float operand, PHPUnit uses NumericComparator (verified in vendor/sebastian/comparator), so 40.0004 and 40 can compare equal. assertJson subsets such as 'net_total' => 70.000 compare with loose ==. Treasury, Customers, Shift and DailyJournal tests create Invoice, Expense and CashShift rows with Model::create, so the service side effects (Payment rows, stock movements) never exist. Note that the service-level tests (InvoiceServiceTest, PurchaseServiceTest, FractionalWeightSaleTest) compare string to string against decimal:3-cast attributes, which IS exact.
- **Impact:** Precision regressions and missing side effects (double counting, missing payments) slip through the API layer.
- **Recommendation:** Assert with assertSame('40.000', (string)$item->fresh()->current_stock) and assertDatabaseHas with string decimals. Build fixtures through the real endpoints or services instead of Model::create.

### 41. [MEDIUM] No mid-operation rollback test for most money and stock services
- **Location:** `backend/tests/Feature`
- **Effort:** M
- **Evidence:** Apart from the single-line, dead-assertion invoice test, no test forces a failure on the second line or a later step. Missing for: PurchaseService::createPurchase (line 2 has an invalid item, so check stock, WAC, supplier balance and payments), ReturnService (purchase return where line 2 has insufficient stock), StockTransferService::createTransfer (line 2 insufficient, line 1 must roll back) and cancelTransfer (destination partly sold), cancelPurchase (multi-line, second item sold), PaymentService (invoice not found after payment), ShiftService, ExpenseService, CreateBlenderInvoiceAction (second component short), and the TreasuryService::transfer audit-log failure.
- **Impact:** Partial commits in multi-line documents would go undetected.
- **Recommendation:** Add one data-provider-driven rollback test per service. Snapshot item.current_stock, all store_stocks, weighted_avg_cost, customer and supplier balances, treasury balances and document, movement and payment counts, then trigger the failure and assert the snapshot is identical.

### 42. [MEDIUM] The provisioning test writes to a real on-disk sqlite file, so it depends on the environment; provisioning has no transaction and no rollback test
- **Location:** `D:/projects/sroor/backend/tests/Feature/Api/SuperAdminApiTest.php:112`
- **Effort:** M
- **Evidence:** test_can_get_tenants_and_provision_new_tenant fakes TenantCreated, so no tenant DB gets created. TenantProvisionerService::provision then calls $tenant->run(...), which connects to database/tenant_wadi-elbon.sqlite. That file exists locally, is gitignored, and its mtime is 2026-10-07 22:47, matching the lead's suite run, so the test mutates it every time. On a fresh clone or in CI the file is missing, SQLiteConnector throws, the controller's catch(Throwable) returns 422, and the 201 assertion fails. That is the same class of problem as the AppUpdateApiTest APK failure. TenantProvisionerService does Tenant::create, domains, Subscription::create and the tenant seed with no DB::transaction and no compensation. On a second run, User::where(email)->orWhere(phone) hits the user from the previous run and takes the update branch instead of the create branch.
- **Impact:** The green run is fake and order-dependent, and the test is not hermetic. In production, a seed failure such as a bad migration leaves an orphan tenant, domain and subscription row, and retries fail on unique slug/email.
- **Recommendation:** Rewrite the test so the TenantCreated pipeline runs against a temp-dir sqlite (override tenancy.database.prefix/path to a storage/framework/testing dir and delete it in tearDown). Assert the tenant DB contains the main store, an admin user with the admin role, and the settings. Add a rollback test: force PermissionsSeeder or Setting::set to throw (bind a failing mock) and assert no tenants/domains/subscriptions rows remain.

### 43. [MEDIUM] Provisioning accepts an arbitrary tenancy_db_name/username/password with no uniqueness check
- **Location:** `D:/projects/sroor/backend/app/Http/Requests/StoreTenantRequest.php:30`
- **Effort:** S
- **Evidence:** tenancy_db_name is 'nullable|string|max:100', with no unique check across tenants and no format check. TenantProvisionerService:44-54 passes it straight through. Inside $tenant->run it then does User::where('email',...)->orWhere('phone',...)->first() and, if a user is found, overwrites that user's password and syncs the admin role.
- **Impact:** A typo, or reusing an existing tenant's DB name, points a new tenant at another customer's database. Provisioning then resets an existing admin's password and both tenants share data.
- **Recommendation:** Validate the DB name format and uniqueness against tenants.data, or remove the field from the public API. Test: provisioning with tenancy_db_name equal to an existing tenant's DB returns 422 and the existing tenant's users are untouched.

### 44. [MEDIUM] About 33 test files authenticate their admin with the hard-coded magic phone [REDACTED_PHONE], which bypasses all permission checks
- **Location:** `D:/projects/sroor/backend/tests/Feature/Api/CustomersApiTest.php:46`
- **Effort:** M
- **Evidence:** `grep -rln [REDACTED_PHONE] tests/Feature` returns 33 files. CustomersApiTest creates the 'admin' fixture with phone '[REDACTED_PHONE]'. AppServiceProvider Gate::before returns true for that phone before role or permission logic runs, and also grants super_admin.* abilities.
- **Impact:** These tests never exercise the real tenant-admin path (Gate::before returns hasRole('admin') ? true : null, and super_admin.* is denied). A regression in admin permissions, or the deliberate removal of the magic phones, would show up only in production, or would break 33 suites at once.
- **Recommendation:** Use a factory phone for admin fixtures (e.g. fake()->unique()->numerify('010########')). Add one dedicated test asserting a tenant admin is denied /super-admin/*.

### 45. [MEDIUM] SuperAdminAppVersionController (APK publish/toggle/delete) has no authorization, validation or file-handling tests
- **Location:** `D:/projects/sroor/backend/app/Http/Controllers/Api/V1/SuperAdmin/SuperAdminAppVersionController.php:36`
- **Effort:** M
- **Evidence:** Routes are at api.php:217-220. Searching tests for 'super-admin/app-versions' finds nothing, and AppUpdateApiTest covers only the public check/download endpoints. destroy() deletes from the public disk with no soft delete. store() accepts an uploaded APK file.
- **Impact:** Combined with the tenant-to-super-admin escalation above, an untested upload path means a malicious APK could reach every customer device. Validation gaps (mime type, size, a version_code lower than current) go unnoticed.
- **Recommendation:** Add tests using Storage::fake('public'): store with a valid APK gives 201 and the file exists; non-APK, oversized or duplicate version_code gives 422; a tenant user (with or without X-Tenant) gets 403; toggle flips is_active; destroy removes the file and the row.

### 46. [MEDIUM] ERP endpoints run against the central DB when no tenant is resolved
- **Location:** `D:/projects/sroor/backend/app/Http/Middleware/ResolveApiTenancy.php:49`
- **Effort:** S
- **Evidence:** If there is no header, query or body tenant and the host is in central_domains (127.0.0.1, localhost, baraa-solutions.com, ...), the middleware simply calls $next. All /api/v1 ERP routes under ApiTokenAuth then run on the central connection. The production central DB (database.sqlite here is 452 MB) appears to hold legacy ERP data. ResolveApiTenancy also reads $request->input('tenant'), so any JSON body field named 'tenant' switches the tenant. Step 3 (host lookup) runs even after a header already initialized tenancy, so it can re-initialize to a different tenant.
- **Impact:** Central-context requests can read or modify legacy central ERP data. Tenant selection is ambiguous when header and host disagree. None of this is tested.
- **Recommendation:** Make tenant-scoped routes require tenancy()->initialized and return 400/404 otherwise. Accept the identifier only from the header or host, never from the body, and return early once a tenant is initialized. Tests: no-tenant GET /items returns 4xx; header=A plus host=B is either rejected or deterministic; a body field 'tenant' is ignored.

### 47. [MEDIUM] Unauthenticated thermal-print web route exposes invoices by sequential id
- **Location:** `D:/projects/sroor/backend/routes/web.php:54`
- **Effort:** S
- **Evidence:** Route::get('/invoices/{id}/print/thermal', fn => Invoice::with(['customer','items.item',...])->findOrFail($id)) is registered with no auth middleware. Only StoreScope is appended to the web group.
- **Impact:** Anyone can iterate invoice ids and read customer names, items and amounts from whichever DB the web request resolves to. No tests exist.
- **Recommendation:** Put the route behind auth plus invoice view permission and tenant resolution. Add a test that a guest gets 302/401 and a user from another store or tenant gets 403/404.

### 48. [MEDIUM] Report caches are not tenant-keyed while CacheTenancyBootstrapper is disabled (depends on configuration)
- **Location:** `D:/projects/sroor/backend/app/Services/ProfitLossService.php:29`
- **Effort:** S
- **Evidence:** cacheKey = "erp_pnl_" . ($storeId ?? 'all') . "_{$fromDate}_{$toDate}". InventoryAnalyticsService uses "erp_abc_..." the same way. config/tenancy.php:36 comments out CacheTenancyBootstrapper. This is safe only while CACHE_STORE=database with a null DB_CACHE_CONNECTION, because then the cache table follows the tenant connection. With file or redis (or the array store used in tests), tenant B gets tenant A's P&L for the same date range. The Setting model, by contrast, keys its cache by tenant id.
- **Impact:** Under file or redis caching, one tenant could see another tenant's profit and loss and ABC analysis.
- **Recommendation:** Enable CacheTenancyBootstrapper or prefix the keys with tenant id. Add a two-tenant test that warms tenant A's P&L cache and asserts tenant B's report differs.

### 49. [MEDIUM] 403 coverage is thin: about 25 of about 105 API routes have an unprivileged-user assertion, and none of the money-moving write endpoints beyond invoice cancel do
- **Location:** `D:\projects\sroor\backend\tests\Feature\Api`
- **Effort:** M
- **Evidence:** Endpoints with a 403 assertion: activity-logs (+export), categories POST/DELETE, coffee-blender/calculate, customers POST, daily-journal, expenses POST, invoices/{id}/cancel, items POST, payments/customer-receipt, pos/bootstrap, purchases GET, reports/summary, returns GET, roles GET, settings GET, shifts GET, transfers GET, super-admin/dashboard, suppliers GET, trash GET, treasury/summary, users GET. Missing 403 tests include POST /invoices, /pos/checkout, /pos/quick-customer, /purchases, /purchases/{id}/cancel, /returns, DELETE /returns/{id}, /transfers and /transfers/{id}/cancel, /items/{id}/adjust-stock, PUT/DELETE /items, /payments/supplier-voucher, /customers/{id}/collect-payment, /suppliers/{id}/pay, /shifts/open and /shifts/close, PUT/DELETE /expenses, /stores POST/PUT/DELETE/assign-users/toggle-active, /users POST/PUT/DELETE, PUT /roles/{id}/permissions, POST /settings, /trash restore/force, all 20 /super-admin/* sub-routes (only dashboard is tested) and /super-admin/app-versions. Several controllers have no permission check at all: StoreController::show (line 107) and ::stocks (line 193), and DashboardApiController::index (line 22). Duplicate files InvoicesAndPosApiTest, ShiftsAndDailyJournalApiTest, UsersAndRolesApiTest and SettingsProfileTrashApiTest have zero 401/403 tests.
- **Impact:** A regression in any Form Request authorize() (for example the ?? true fallback in StoreSalesInvoiceRequest.php:13) or a removed inline check would not be caught. Unpermissioned users already reach branch stock valuation and dashboard figures.
- **Recommendation:** Add one DataProvider-driven test per controller listing [method, uri, payload] and asserting 403 plus no DB change for a user with no roles, and a second one for 'admin' role vs super-admin routes. Add explicit tests (expected to fail) for stores/{id}, stores/stocks and dashboard.

### 50. [MEDIUM] Super-admin Form Requests also authorize the tenant 'admin' role, and no test checks that a tenant admin is denied
- **Location:** `D:\projects\sroor\backend\app\Http\Requests\UpdateTenantDatabaseConfigRequest.php:13`
- **Effort:** S
- **Evidence:** UpdateTenantDatabaseConfigRequest, UpdateTenantUnitsRequest, UpdatePlatformSettingsRequest, UpdateSystemUnitsRequest, ToggleTenantStatusRequest and OverrideTenantFeatureRequest all return true for hasRole('admin'). StoreTenantRequest and UpdatePlanRequest return true unconditionally. Protection depends entirely on the route-group middleware can:super_admin.access (routes/api.php:196) and the AppServiceProvider deny callback. SuperAdminApiTest only tests a user with no roles (line 97), never a role 'admin' user.
- **Impact:** If the route middleware is removed or a route is added outside the group, any shop admin can rewrite tenant DB credentials or plans. Combined with the roles-API finding, the deny callback may already be bypassable.
- **Recommendation:** Add tests where a tenant user with role 'admin' calls every /super-admin/* route and gets 403. Raise to backend-architect that the Form Requests should require the central super_admin role.

### 51. [MEDIUM] 401 coverage only tests one GET per module; auth relies on one middleware group and accepts tokens in the query string and a plaintext api_token column
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:98`
- **Effort:** S
- **Evidence:** All 30 401 assertions are on GET index or bootstrap routes (e.g. CustomersApiTest.php:66, InvoiceApiTest.php:140). No 401 test covers any POST/PUT/DELETE, /stores/switch, /auth/logout, /profile PUT, /trash force or /super-admin sub-routes. ApiTokenAuth accepts $request->query('api_token') and falls back to User::where('api_token',$token) (line 122). ApiQuickLoginAction saves the plain Sanctum token into users.api_token. In tenant context, a central admin token is mapped to a tenant user by phone (lines 128-139).
- **Impact:** Today every protected route is inside the ApiTokenAuth group, so the risk is a future route added outside the group without being noticed. Tokens in URLs end up in access logs, and plaintext tokens in users.api_token can be replayed if the DB leaks. The central-to-tenant phone mapping lets a central admin act as any same-phone user in any tenant.
- **Recommendation:** Add one route-introspection test that iterates Route::getRoutes() under api/v1 (minus an explicit guest allowlist), asserts ApiTokenAuth is in the middleware stack, and calls each route without a token expecting 401. Add tests that ?api_token= is rejected (once that is fixed) and that a central admin token cannot reach a tenant where no matching admin exists.

### 52. [MEDIUM] Store-switch and multi-store tests only cover admins and model-level code; no negative store-access scenario exists anywhere
- **Location:** `D:\projects\sroor\backend\tests\Feature\MultiStorePhase2Test.php:248`
- **Effort:** S
- **Evidence:** MultiStorePhase1Test (4 tests) and MultiStorePhase2Test (5 tests) exercise the StockTransfer service, store pricing and getCurrentStore(), plus test_store_switch_route with $this->admin only. StoresApiTest has 11 tests and no 403 assertion, so the unauthorized-switch branch in StoreController::switchStore (line 236) is never run. Most API test files create only one Store. A grep for otherStore/isolation/cross-store scenarios in tests/ finds nothing.
- **Impact:** The one store guard that does exist (switchStore) could regress unnoticed, and nothing in the suite would detect the isolation defects described above.
- **Recommendation:** Add StoresApiTest cases: a non-admin switching to an unassigned store gets 403 and session/current store is unchanged; a non-admin GET /stores lists only assigned stores; a non-admin GET /stores/{unassignedId} gets 403 (expected to fail today, since StorePolicy::view is not used by the controller).

### 53. [MEDIUM] A Playwright storageState with a live Sanctum bearer token, session cookies and user PII is committed to git
- **Location:** `D:/projects/sroor/backend/e2e/.auth/user.json:1`
- **Effort:** S
- **Evidence:** `git ls-files backend/e2e` lists backend/e2e/.auth/user.json (added in commit db2e4eca). Inspecting key names and lengths only: cookies XSRF-TOKEN and laravel-session (342 chars each) for 127.0.0.1, and localStorage keys auth_token (51 chars, Sanctum plain-text token format), auth_user (936 chars, includes name/phone/email/roles/is_super_admin) and auth_store, for both 127.0.0.1:8000 and localhost:8000. Root .gitignore ignores e2e/.auth/ but not backend/e2e/.auth/.
- **Impact:** A super-admin bearer token sits in the repo history. It is only valid against the DB that issued it, which looks like the local dev DB. But the repo has DB restore/sync scripts (restore_sroor_db.py etc.), so if a production dump was ever restored locally, or a local DB pushed, the token works remotely until it is revoked. It also leaks the owner's personal contact data.
- **Recommendation:** Revoke all personal_access_tokens for that user in every environment, git rm --cached the file, add `**/e2e/.auth/` to .gitignore, and consider purging it from history.

### 54. [MEDIUM] Three parallel E2E trees and two Playwright configs; the legacy trees and root test:* scripts are dead or broken
- **Location:** `D:/projects/sroor/backend/playwright.config.js:37`
- **Effort:** S
- **Evidence:** Trees: (1) root e2e/ with 39 flows, the live one. (2) backend/e2e/, committed, a stale copy with 10 flows, a different items-full-page-audit.spec.js, a committed .auth/user.json and 161 committed screenshots under backend/e2e/screenshots. (3) backend/tests/e2e/ with specs 01-05, unified_journey and unified_fresh_journey, a fixture, utils/db-reset.js and a committed Playwright HTML/trace report (reports/index.html, reports/trace/*, reports/data/*.md failure dump). backend/playwright.config.js is a byte copy of the root config, but with rootDir=backend its testDir resolves to backend/e2e and its webServer runs `php backend/artisan serve` with cwd=backend, i.e. backend/backend/artisan, which does not exist. No config has testDir pointing at backend/tests/e2e, and its filenames would not match the testMatch regex /crawlers\/|flows\// anyway. Root package.json test:fresh/test:journey/test:auth/test:stores/test:shifts/test:pos/test:invoices point to tests/e2e/specs/... relative to the repo root (missing) and use --project=Mobile-Pixel-7, which no config defines. test:report points at tests/e2e/reports. backend/package.json e2e:* correctly use --config=../playwright.config.js, but e2e:report runs show-report with no html reporter configured (reporter is only ['list']), so there is nothing to show.
- **Impact:** Developers can run the wrong config or tree, or hit broken scripts. Stale specs drift silently. Repo bloat (trace viewer bundle, screenshots). Committed credentials live in the stale tree (see the other findings).
- **Recommendation:** Delete backend/tests/e2e/ (dead, unreferenced; port any unique journeys first, e.g. 03_shifts_cash and 04_pos_fast_checkout, into e2e/flows), backend/e2e/ and backend/playwright.config.js. Remove the root test:* Playwright scripts. Add an html reporter to the output folder that e2e:report targets, and gitignore it. All of this is safe to drop: nothing references these paths apart from the broken root scripts.

### 55. [MEDIUM] Legacy unified_fresh_journey runs `migrate:fresh --seed` against whatever DB backend/.env points to, and silently swallows failure
- **Location:** `D:/projects/sroor/backend/tests/e2e/utils/db-reset.js:14`
- **Effort:** S
- **Evidence:** resetFreshDatabase() calls execSync('php artisan migrate:fresh --seed') with cwd path.resolve(__dirname,'../../../backend'). From backend/tests/e2e/utils that resolves to D:/projects/sroor/backend/backend (nonexistent), and the catch only console.errors. unified_fresh_journey.spec.js:14 calls it in beforeAll and then carries on against the stale DB. There is no APP_ENV/DB guard and no --env=testing.
- **Impact:** Today the path bug makes it a no-op, so the 'fresh' journey actually runs on dirty data and passes or fails for the wrong reason. If someone 'fixes' the path, it wipes the developer's main DB (backend/database/database.sqlite) or any DB .env points to, including a remote one.
- **Recommendation:** Delete it along with the legacy tree. If a reset is needed in the live e2e/, use a dedicated --env=e2e with DB_DATABASE=database/e2e_testing.sqlite, refuse to run unless APP_ENV is local/e2e and DB_CONNECTION is sqlite, and throw on failure.

### 56. [MEDIUM] E2E runs against the developer's main DB with no isolation, seeding or cleanup; baseURL is env-overridable while webServer is pinned to localhost
- **Location:** `D:/projects/sroor/playwright.config.js:27`
- **Effort:** M
- **Evidence:** baseURL = process.env.APP_URL || 'http://127.0.0.1:8000', but webServer.url is hardcoded to 127.0.0.1:8000 with reuseExistingServer:true and command `php backend/artisan serve`, using backend/.env (APP_ENV=local, DB_CONNECTION=sqlite, so the main database.sqlite, not the e2e_testing.sqlite that exists next to it). There is no globalSetup to migrate or seed. Specs write data without cleanup, e.g. super-admin-units-full-page-audit.spec.js:64-66 adds a system-wide unit 'وحدة_اختبار' on every run. Specs hardcode ids (/customers/1/statement, /suppliers/1/statement, /invoices/1, /items/1/movements). I found no production/Hostinger host in any spec, util, config or .env (backend/.env APP_URL is localhost).
- **Impact:** If APP_URL is exported in the shell, e.g. from a deploy session, Playwright still boots or reuses the local server but drives the browser at that remote host, logging in as super admin and creating units there. Locally, runs pollute the dev DB and depend on row id 1 existing, so results are non-deterministic.
- **Recommendation:** Use a dedicated E2E_BASE_URL and assert in the config that it is localhost/127.0.0.1 (throw otherwise). Add a globalSetup that runs `php artisan migrate:fresh --seed --env=e2e` against e2e_testing.sqlite with a hard guard. Create fixtures through the API and look up ids instead of hardcoding 1.

### 57. [MEDIUM] All selectors are translated Arabic text or Tailwind classes; zero role/label/data-testid selectors
- **Location:** `D:/projects/sroor/e2e/utils/interaction-helper.js:58`
- **Effort:** L
- **Evidence:** In e2e/flows: getByRole 0, getByLabel 0, getByTestId/data-testid 0, has-text/getByText/toContainText 391 uses in 38 files. Examples: POS `button:has-text("نقدي عام")`, `h1` toContainText('نقطة البيع'), and the modal located by `.fixed.inset-0` (pos spec:90). The crawler decides which buttons are 'modal openers' by Arabic substrings (interaction-helper.js:58-67), and the dangerous-action denylist is a list of Arabic/English keywords (lines 4-8). The same 4-button Arabic dismiss-modal locator is copy-pasted into nearly every spec instead of a shared util.
- **Impact:** Any copy or translation change, an English-locale run, or a Tailwind refactor breaks the suite. The ar/en parity rule cannot be tested because the specs only work in Arabic. A keyword-based safety denylist misses new destructive labels.
- **Recommendation:** Add data-testid to key controls (checkout, customer picker, cart rows/total, modals) through the frontend owner. Switch to getByRole/getByLabel/getByTestId. Move dismissModal and console-error capture into a shared fixture in e2e/utils.

### 58. [LOW] Overlapping and duplicate API test files
- **Location:** `D:/projects/sroor/backend/tests/Feature/Api/InvoicesAndPosApiTest.php`
- **Effort:** S
- **Evidence:** InvoicesAndPosApiTest repeats 4 InvoiceApiTest tests with identical names (create/deduct, show, cancel/restore, list) and 3 PosApiTest scenarios. ShiftsAndDailyJournalApiTest repeats 5 ShiftApiTest tests with identical names. UsersAndRolesApiTest repeats 5 UsersApiTest tests and 2 RoleApiTest tests. PermissionsAndContextApiTest overlaps both PermissionApiTest and SystemContextApiTest. SettingsProfileTrashApiTest overlaps SettingApiTest, ProfileApiTest and TrashApiTest. DashboardApiControllerTest (1 test) overlaps DashboardApiTest. The 'combined' files also lack declare(strict_types=1).
- **Impact:** Running the same scenarios twice costs roughly 30 tests × ~1s of runtime. Fixes have to be made in two places, and the copies drift, which gives a false sense of breadth.
- **Recommendation:** Keep one file per controller (InvoiceApiTest, PosApiTest, ShiftApiTest, DailyJournalApiTest, UsersApiTest, RoleApiTest, PermissionApiTest, SystemContextApiTest, SettingApiTest, ProfileApiTest, TrashApiTest, DashboardApiTest). Move any unique assertions from the combined files into them, then delete the combined files.

### 59. [LOW] Trivial and misleading tests
- **Location:** `D:/projects/sroor/backend/tests/Feature/ExampleTest.php:9`
- **Effort:** S
- **Evidence:** tests/Unit/ExampleTest.php contains only assertTrue(true), and it is the only Unit test. Feature/ExampleTest::test_guests_are_redirected_to_login asserts assertStatus(200) on '/', which contradicts its name. CustomerBalanceTest has 1 test and 2 assertions. Some tests only check response structure, e.g. SettingApiTest telegram test and the 9 ReportsApiTest routes with no value assertions.
- **Impact:** Inflated test counts. The misleading name hides the fact that guest access to '/' is not checked.
- **Recommendation:** Delete Unit/ExampleTest. Rename or fix the Feature ExampleTest (assert a redirect for guests on the tenant domain). Add unit tests for the pure bcmath helpers and services, e.g. totals, discount and profit calculations, with exact string results.

### 60. [LOW] Test convention violations
- **Location:** `D:/projects/sroor/backend/tests/TestCase.php:1`
- **Effort:** S
- **Evidence:** 23 files do not declare(strict_types=1): TestCase.php, ConcurrencyTest, CustomerBalanceTest, ExampleTest (both), ExpenseServiceTest, FractionalWeightSaleTest, InvoiceServiceTest, MultiStorePhase1Test, MultiStorePhase2Test, POSSolidArchitectureTest, PurchaseCancelAndRestoreFeatureTest, PurchaseServiceTest, ReturnServiceTest, SoftDeletesTest, StockAdjustmentFeatureTest, SuperAdminSolidTest, and the Api files InvoicesAndPosApiTest, PermissionsAndContextApiTest, SettingsProfileTrashApiTest, ShiftsAndDailyJournalApiTest, SpaInfrastructureTest, UsersAndRolesApiTest. 9 test methods have no ': void' return type: MultiStorePhase1Test.php:72,82,112,123 and MultiStorePhase2Test:111,142,169,214,248. There are no @dataProvider docblocks, but also no #[DataProvider] anywhere, so no validation matrix is parameterized. The only fakes used are Storage (AppUpdateApiTest) and Event (3 files). Http, Queue, Notification and Mail are never faked.
- **Impact:** Strict types are not enforced, which matters most in the money tests: bcmath string vs float coercion can be masked. Validation coverage is ad-hoc rather than systematic.
- **Recommendation:** Add strict_types and : void throughout. Put Http::preventStrayRequests() in the base TestCase. Introduce #[DataProvider] tables for 422 cases on money endpoints.

### 61. [LOW] Every test re-runs the full migration set, so the suite is slow
- **Location:** `D:/projects/sroor/backend/tests/TestCase.php:13`
- **Effort:** M
- **Evidence:** TestCase::setUp runs artisan migrate --path=database/migrations/tenant after RefreshDatabase on :memory:. Some tests also run migrate again in their own setUp (AppUpdateApiTest.php:19 runs --path=database/migrations). A junit timing shows about 1.25s per trivial test, and the suite took 275s in this run.
- **Impact:** Slow feedback means developers skip the full suite before reporting, which is how env-dependent failures like the APK one slip through.
- **Recommendation:** Use a file-based sqlite schema dump (schema:dump) or migrate once with DatabaseMigrations plus transactions. Remove the redundant per-class migrate calls.

### 62. [LOW] Generated Playwright trace report is committed, a second legacy e2e tree exists, and specs use waitForTimeout widely
- **Location:** `D:/projects/sroor/backend/tests/e2e/reports/index.html`
- **Effort:** M
- **Evidence:** git ls-files shows 29 tracked files under backend/tests/e2e, including reports/trace/* (1.9 MB) and reports/data/*.md with a recorded failure ('page.waitForTimeout: Target page ... has been closed'). There are two Playwright configs, root playwright.config.js and backend/playwright.config.js. 42 files under e2e/ use waitForTimeout. Both base URLs default to 127.0.0.1, so I found no production URL.
- **Impact:** The repo carries stale artifacts and two competing e2e entry points. Fixed sleeps make the specs flaky.
- **Recommendation:** Delete backend/tests/e2e/reports and gitignore it. Retire backend/tests/e2e/specs after porting any unique journeys into e2e/flows. Replace waitForTimeout with expect(...).toBeVisible() or waitForResponse.

### 63. [LOW] Stock-movement ledger rows are hard-deleted by invoice and purchase delete/update paths, and a test exercises one
- **Location:** `backend/app/Services/InvoiceService.php:637`
- **Effort:** S
- **Evidence:** StockMovement has no SoftDeletes. deleteInvoice (637-639), updateInvoice (367-370) and PurchaseService::deletePurchase (437-439) call ->delete() on movements. updateInvoice also leaves the old treasury-paid Expense rows and creates new ones on every edit (438-443 vs 479-489). None of these are routed today (grep finds no caller), but InvoiceServiceTest uses deleteInvoice as a test helper. tenant.php:94 routes invoices/{id}/restore to an InvoiceController::restore method that doesn't exist.
- **Impact:** Wiring any of these paths up later would destroy the inventory audit trail and duplicate expenses. The restore route returns a 500.
- **Recommendation:** Flag it to backend-architect. In tests, use cancelInvoice instead of deleteInvoice for the numbering tests, and add a test asserting POST /invoices/{id}/restore does not return 500.

### 64. [LOW] Purchase return ignores the purchase's store and duplicates the supplier-balance logic
- **Location:** `backend/app/Services/ReturnService.php:118`
- **Effort:** S
- **Evidence:** `$purchase?->store_id` refers to an undefined variable. `??` suppresses the warning, so the stock silently comes from the user's current store. StoreReturnRequest doesn't even validate purchase_id. Lines 164-170 recompute the supplier balance inline instead of calling SupplierBalanceService, so the logic is duplicated.
- **Impact:** Purchase returns can deduct from the wrong branch.
- **Recommendation:** Add a test: a purchase in store B with the user in store A, then a return without store_id, asserting store B's stock is deducted.

### 65. [LOW] Rounding and allocation truncation is untested (percentage discounts, landed-cost allocation)
- **Location:** `backend/app/Services/PurchaseService.php:88`
- **Effort:** S
- **Evidence:** Allocation computes bcdiv(qty,total,6) and then bcmul(...,3), which truncates, and unit allocation bcdiv(lineAlloc, qty, 3) truncates again. A 100.000 expense split equally over 3 lines allocates 99.999. Invoice percentage discounts truncate too (InvoiceService.php:120). No test covers additional_expenses, allocation methods, or a percentage discount with a remainder. Fractional coverage is limited to 0.250, 0.125, 0.500, 2.500 and 47.500, plus blender 0.150/0.100 asserted as floats.
- **Impact:** Small but systematic losses in inventory valuation and pricing.
- **Recommendation:** Add exact-string tests for by_quantity, by_value and equal allocation with non-divisible amounts, and for a 33.333% discount. Decide on a remainder-to-last-line policy.

### 66. [LOW] The tenant resolver uses a LIKE built from user input, so wildcards enumerate tenants
- **Location:** `D:/projects/sroor/backend/app/Actions/Tenants/ResolveTenantWorkspaceAction.php:35`
- **Effort:** S
- **Evidence:** orWhereHas('domains', fn => ->orWhere('domain','like', "{$code}.%")). sanitizeCode keeps '%' and '_', so ?code=% matches the first tenant with a domain and returns its tenant_id, name and server_url.
- **Impact:** An unauthenticated caller can discover tenant ids, which feed the quick-login and X-Tenant attacks above.
- **Recommendation:** Escape LIKE wildcards or match the domain exactly, and rate-limit the endpoint. Test: ?code=% and ?code=_ return 404.

### 67. [LOW] Telescope access is granted to any 'admin', to any @baraa-solutions.com email, or via ?token=
- **Location:** `D:/projects/sroor/backend/routes/web.php:17`
- **Effort:** S
- **Evidence:** /telescope-access accepts ?token= (a Sanctum or api_token value), allows hasRole('admin'), the magic phones, or str_ends_with(email,'@baraa-solutions.com'), then calls auth('web')->login($user, true). TelescopeServiceProvider::gate also logs in from ?token=.
- **Impact:** An ordinary admin can see every request Telescope recorded, including headers and tokens, which leads to cross-tenant token theft. Tokens passed in query strings also end up in logs.
- **Recommendation:** Restrict access to super_admin and drop query-token login. Test that an admin gets a 403 on /telescope-access.

### 68. [LOW] Trash force-delete returns raw exception messages as 422, and FK-restricted deletes are untested
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\TrashController.php:92`
- **Effort:** S
- **Evidence:** catch (Throwable $e) returns ['message' => $e->getMessage()] with status 422. Force-deleting an item referenced by invoice_items or stock_movements (restrictOnDelete in tenant migrations 160005:35, 160006:13) raises a QueryException whose SQL text is returned to the client. TrashApiTest only force-deletes an unreferenced item (line 138) and checks an invalid type (line 161).
- **Impact:** Internal SQL and schema details leak to the client, and genuine 500s are disguised as validation errors. Nothing proves that items with history are protected.
- **Recommendation:** Add tests: force-delete a trashed item that has invoice history returns a clean 422 with a translated message and the row still exists; a trashed customer with invoices likewise.

### 69. [LOW] waitForTimeout is used 179 times across 42 files in the live e2e tree (plus 90 in the legacy trees)
- **Location:** `D:/projects/sroor/e2e/flows/app-update-flow.spec.js`
- **Effort:** M
- **Evidence:** Per-file counts in root e2e/: app-update-flow 7; trash, super-admin-tenant-show, settings, reports, pos, invoice-show 6 each; stores, roles, profile, items, invoices, daily-journal, create-stock-transfer, create-return, activity-logs 5 each; users, suppliers, supplier-statement, super-admin-units/tenants/plans/app-versions, store-stocks, stock-transfers, smart-reorder, returns, purchases, item-movements, expenses, customers, customer-statement, create-purchase, coffee-blender, categories 4 each; super-admin-dashboard and dashboard-responsive-audit 3 each; utils/interaction-helper, login-flow, dashboard-modular-verification and auth/login.setup 2 each; crawlers/crawler 1. Legacy: backend/tests/e2e/specs/unified_fresh_journey 55, unified_journey 20, 02/04/05 one each. The backend/e2e copy repeats the root counts. Most uses come after page.goto(...networkidle), e.g. `await page.waitForTimeout(1000)` in the super-admin specs.
- **Impact:** Slow and flaky runs, and it hides real async bugs such as a skeleton that never resolves.
- **Recommendation:** Replace with web-first assertions (expect(locator).toBeVisible()) or waitForResponse on the specific API call. Ban waitForTimeout with an eslint-plugin-playwright rule.

### 70. [LOW] Required UX axes are not asserted: RTL dir, skeleton to content, empty state, dark/light; viewport testing is redundant
- **Location:** `D:/projects/sroor/e2e/flows/profile-full-page-audit.spec.js:69`
- **Effort:** M
- **Evidence:** Greps across e2e/flows: dir/rtl 0, skeleton/animate-pulse 0, empty-state text in 1 file (POS 'الفاتورة فارغة', OR-ed with 'has cart rows' so it can't fail), dark in 1 file (a click with no assertion on the html class). Console errors are captured in 37 files, but most filter out 'Failed to load resource' and 'net::ERR_' (pos spec:67), so failed API calls (4xx/5xx) never fail a test. Viewports: each spec loops 5 sizes with setViewportSize (36 files), and the config runs every spec under the desktop, tablet and mobile projects, so 15 combos per page. The per-viewport isMobile flag is ignored in 37 of 39 files (only 2 reference vp.isMobile), and setViewportSize can't toggle isMobile anyway.
- **Impact:** RTL regressions, broken dark mode, stuck skeletons and silent API errors all pass. Runtime is inflated about 3x with no extra signal.
- **Recommendation:** Add a shared fixture that asserts html[dir=rtl], fails on any response >= 400 from /api, and checks theme classes after toggling. Assert that the skeleton disappears and data rows or the empty state appear, and seed an empty tenant for the empty-state checks. Let the projects own the viewports and drop the per-spec loops, or the reverse.

### 71. [LOW] Routes with no E2E spec: /connect (workspace/tenant connect) and /invoices/:id/print; detail routes only hit hardcoded id 1
- **Location:** `D:/projects/sroor/backend/resources/js/router/index.js:7`
- **Effort:** M
- **Evidence:** Router paths are /connect(/workspace), /login, /, /stores, /stores/stocks, /customers, /customers/:id/statement, /suppliers, /suppliers/:id/statement, /expenses, /items, /categories, /items/:id/movements, /daily-journal, /purchases, /purchases/create, /purchases/smart-reorder(/smart-reorder), /invoices, /invoices/:id, /invoices/:id/print, /pos, /returns, /returns/create, /stock-transfers, /stock-transfers/create, /coffee-blender, /reports, /users, /roles, /activity-logs, /settings, /profile, /trash, and /super-admin/{dashboard,tenants,tenants/:id,plans,app-versions,units}. Flow specs goto() every one except /connect and /invoices/:id/print. e2e/pages.config.js also omits both and lists a '/brochure' route that does not exist in the router (the catch-all redirects it to '/'). The super-admin dashboard spec visits '/super-admin', which only works through the catch-all redirect to '/' and the super-admin root redirect.
- **Impact:** The tenant-resolution entry flow and the 80mm/A4 print view (customer-facing output with totals/VAT) are never checked. Dead page config entries hide gaps.
- **Recommendation:** Add specs for /connect (valid slug, invalid slug, offline) and for /invoices/:id/print with a seeded invoice, asserting line totals and the grand total. Remove /brochure from pages.config.js and use /super-admin/dashboard directly.

### 72. [INFO] All store/permission tests run against one central sqlite DB, so DB-per-tenant isolation of permissions, roles and stores is unproven
- **Location:** `D:\projects\sroor\backend\app\Scopes\TenantScope.php`
- **Effort:** L
- **Evidence:** Only CentralTenantResolverApiTest, SuperAdminApiTest and SuperAdminSolidTest touch tenancy. TenantScope is never applied (no addGlobalScope usages). Spatie roles and permissions are seeded in the same DB as users in every test.
- **Impact:** Findings that depend on tenant context (the phone bypass, central-admin fallback in ApiTokenAuth, X-Tenant on quick-login) cannot be reproduced by the current suite.
- **Recommendation:** Add a small tenancy test base that creates two tenants (sqlite file DBs), seeds permissions per tenant, and asserts a token from tenant A sent with X-Tenant: B gets 401 and sees none of B's records.

## Open questions
- Does the production central DB still contain the legacy single-tenant tables and data (customers, invoices, items)? If it does, the unauthenticated ExportController routes are an active data leak, not just a broken feature.
- Who holds the 'admin' role in the central DB? Only platform staff, or also tenant owners? That decides how severe the ApiTokenAuth phone-mapping fallback (ApiTokenAuth.php:51-63) is.
- Is anything other than ResolveTenantWorkspaceAction (Tenant.php isSuspended) meant to block suspended or expired tenants on /api/v1? I found no such check in ResolveApiTenancy or ApiTokenAuth.
- Should 'ios' be a valid platform for download-apk at all, given CheckUpdateRequest allows it? If not, the right fix may be a 422 rather than a 404.
- Are the 15 routes that point at missing methods (invoice edit/update/destroy/restore, settings backup/telegram actions, daily-journal shift actions) still linked from the Vue SPA, or are they leftovers from the Livewire/Inertia removal?
- Matrix method: I matched route URIs and HTTP verbs against test method bodies. Tests that call services directly, such as the MultiStorePhase2 and PurchaseCancelAndRestore Feature tests, are not credited to routes, so the per-dimension counts (422/403/404/DB) are a lower bound per route. I did not write any files. The matrix exists only in the session scratchpad (matrix.txt) and is summarized in the findings above.
- When a cash invoice is cancelled, should the system create a refund voucher (treasury outflow) or keep the payment as customer credit? Today it does neither explicitly: the payment stays, and the customer ends up with a credit balance.
- Is /api/v1/pos/checkout (PosController) still used by the Android or Electron clients? The SPA POS posts to /api/v1/invoices. That decides whether the dropped-discount defect on /pos/checkout is live.
- Is there a plan for a MySQL-backed CI job? Without one, lockForUpdate, DECIMAL(12,3) rounding and deadlock behaviour cannot be verified, because sqlite :memory: makes locks no-ops and keeps 4-dp values.
- Should a sales return be required to reference an invoice, with quantity capped at sold minus already returned? It affects how the return tests should be written.
- Should sales from a branch with no store_stocks row be rejected (strict per-branch inventory) or allowed (global pool)? The current tests depend on the permissive behaviour.
- Which user is id=1 in the production central DB, and does it have super_admin or a magic phone? This decides whether quick-login gives anonymous platform takeover or 'only' tenant takeover.
- What are the production CACHE_STORE and DB_CACHE_CONNECTION values? These decide whether the non-tenant-keyed P&L/ABC caches leak across tenants.
- Is quick-login meant to be restricted to a paired POS device? If so, where is that pairing supposed to be enforced? Nothing in the API does it today.
- Is the central-'admin' auto-provisioning into tenants (LoginAction/ApiLoginAction/ApiTokenAuth fallback) intended for platform staff only? If so, it should check super_admin and use the existing tenant_user_impersonation_tokens table.
- Should ERP routes ever run in central (no-tenant) context on the SaaS branch, or does the legacy central ERP data need migrating into a tenant DB?
- I did not run the test suite: the provisioning test writes to the on-disk database/tenant_wadi-elbon.sqlite, and this was a read-only run. I have not confirmed that it fails on a clean checkout; that conclusion comes from SQLiteConnector's missing-file exception being caught and turned into a 422.
- Is passwordless quick-login (/auth/quick-login plus /auth/workspace-users) meant to ship to production? AuthApiTest asserts it as a feature.
- In production, are spatie roles/permissions and users seeded per tenant DB, and which connection does the super-admin controller use when it is reached with X-Tenant set? This decides whether the role-sync and phone-bypass escalations reach central tenant management (confirm with a tenancy test).
- Is the intended rule that branch staff only see their assigned stores (so isolation is a must), or that all staff in a tenant can see all branches? The StoreAccess middleware and StorePolicy suggest the first.
- Should deleting a return reverse stock and balances (a cancel-style flow), or should returns be cancel-only like invoices and purchases?
- I did not run the suite for this sub-area (read-only analysis). The defect tests I propose have not been written or run, so every finding above is backed by code reading, not by a failing test.
- Was DatabaseSeeder ever run on a fresh production or tenant install through deploy_all_locations.py / deploy_live_and_seed.py? If so, the seeded super-admin with the default password exists live and must be rotated now. I could not verify this statically and connected to no server.
- Is the Sanctum token in the committed backend/e2e/.auth/user.json from a DB that was ever restored from or pushed to production (restore_sroor_db.py)? Revoke it regardless.
- How do tenant-scoped pages resolve a tenant during E2E (no X-Tenant header, 127.0.0.1 host)? Do they hit the central DB or a default tenant? This decides whether E2E exercises the DB-per-tenant path at all.
- Have the 39 flow specs actually been run green on the current branch after the Inertia/Livewire removal? Static review can't confirm this. The latest screenshots run is from 2026-08-22.