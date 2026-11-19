# 05 — DevOps / operations production-readiness audit (read-only)

Date: 2026-11-09 · Branch: `feature/multi-tenant` @ `2547eb90` · Auditor: devops-engineer agent
Scope: CI/CD, deploy, secrets, hosting, queue/scheduler, Redis, tenant provisioning and migrations, backups, monitoring, environments, TLS, scaling, app release, repo hygiene.
Rules followed: no files edited in the repo, no root `*.py` / deploy script run, no SSH, no secret values reproduced (only `file:line`). GitHub was queried **read-only** through `gh`.

---

## Executive summary

**Overall: the VPS plan is well engineered and has been tested offline, but none of it has run on real infrastructure. The only system actually in production (the live shop on `main`, on Hostinger) is exposed today.**

What is good (verified):
- `release.yml` + `scripts/ops/deploy.sh` give a real release flow: an artifact built in CI with a sha256 check; `releases/<id>` plus an atomic symlink; a pre-migration encrypted backup; `migrate` + `tenants:migrate` under a separate migrator account; caches; `horizon:terminate`; a health gate before and after the switch; automatic rollback (exit 3); manual `--rollback`; and a deploy lock. The offline suites are green on this machine: `deploy-test.sh` **127/0**, `render-env-test.sh` **38/0**, `run-tests.sh` **194/0** (the live-MySQL part was skipped).
- Idempotent provisioning (`scripts/ops/steps/00…90`) covers ufw, fail2ban, SSH by key only, PHP 8.4, MySQL 8.4 with least-privilege users (escaped `tenant\_%` grants), Redis with a password, a wildcard certificate over DNS-01, supervisor/Horizon and cron.
- Secrets hygiene for the new stack: a GitHub Environment template, `render-env.sh` and the `check-env.sh` production gate, a gitleaks CI gate with a self-test, and GitHub secret scanning plus push protection enabled.
- Backups: `backup:tenants` (central + each tenant, AES-256, Google Drive with `drive.file` scope, sha256 re-download verification, 7/4/3 retention), `backup:restore-tenant --drop-after-verify`, and spatie/laravel-health checks.
- CI (`ci.yml`): php, mysql (real MySQL 8.4), frontend, desktop and gitleaks jobs. The last 3 runs on `feature/multi-tenant` are green.

What blocks launch:
1. **OPS-01 / OPS-02 (P0):** the repo is **PUBLIC**, and leaked Hostinger SSH, DB and `APP_KEY` credentials plus the deploy-webhook token sit in tracked files. They have not been rotated (rotation was deferred by a CTO decision). The `main` deploy is a GET webhook whose token is in `deploy.yml`, and it runs `git reset --hard` + `migrate --force` on the live shop with no backup.
2. **OPS-03 (P0):** the VPS has never been provisioned. `release.yml` has never run. The GitHub Environment `production` does not exist (`gh api …/environments` → 0). The DNS provider for the wildcard certificate is still undecided.
3. **OPS-04 (P0):** no backup has ever been taken on real infrastructure, Google Drive OAuth is not set up, and no restore drill has run. The live shop's only backup is an **unencrypted SQL dump sent to Telegram** (`main:routes/console.php:36-37`).

Top P1s: no branch protection or rulesets on any branch, and a tag on any commit can release (OPS-05). Sentry is installed but **not wired** into exception handling (OPS-06). No external uptime or heartbeat monitoring (OPS-07). An unsupervised `queue:work` runs from the scheduler every minute, alongside Horizon (OPS-08). `ArchiveTenantJob` has a 1800 s timeout but a 90 s `retry_after`, so it runs twice and gets marked failed (OPS-09). Pulse writes to **tenant** DBs in production (OPS-10). There is no staging for the SaaS codebase (OPS-11). RPO is 24 h on a single VPS (OPS-12). The Android APK is built with `assembleDebug` and unsigned for release (OPS-13).

Estimated work to clear P0 + P1: about 2–3 developer-days of code and config (all S/M), plus about 3–5 CTO days of infrastructure execution (VPS rehearsal, rotation, Drive OAuth, signing keys).

---

## Method and evidence base

| Check | Result |
|---|---|
| `gh repo view` | `kamalsroor1/sroor-cofe-erp`, **visibility PUBLIC**, default branch `main` |
| `gh api …/branches/{feature/multi-tenant,main}/protection` | **404 "Branch not protected"** for both |
| `gh api …/rulesets` | `[]` (no rulesets, no tag protection) |
| `gh api …/environments` | **0 environments** |
| `gh api …` `security_and_analysis` | secret_scanning **enabled**, push_protection **enabled**, dependabot_security_updates **disabled** |
| `gh api …/actions/secrets` | `[]` (no repo-level secrets) |
| `gh run list` CI on `feature/multi-tenant` | last 3 runs success (latest 2026-10-11) |
| `gh run list --workflow release.yml` | 404: workflow not on the default branch, **never run** |
| `bash scripts/ops/tests/deploy-test.sh` | 127 passed / 0 failed |
| `bash scripts/ops/tests/render-env-test.sh` | 38 passed / 0 failed |
| `bash scripts/ops/tests/run-tests.sh` | 194 passed / 0 failed (live MySQL part skipped) |
| Not verifiable from here | anything on Hostinger or the VPS: real cron, worker, `.env` values, disk, DNS, certificates, Drive |

Existing runbooks reviewed: `docs/07-operations/{vps-runbook,deploy-runbook,secrets,backup-restore,app-update-checklists,android-update-checklist,local-testing}.md`, `.claude/skills/production-ops/SKILL.md`, `.claude/skills/ci-pipeline/SKILL.md`, and the earlier `docs/reviews/2026-10-07-saas-analysis/08-devops.md`. Several findings from that review (dumps in git history, central-only backups, the Telegram dump schedule on this branch) are now **fixed** on `feature/multi-tenant`. No `.sql`, `.apk`, `.jks` or `.keystore` file appears in any commit in history (`git log --all --diff-filter=A`), and `backups/` is untracked and ignored.

---

## Findings

### A. Secrets and the live shop (current production)

#### OPS-01 — Leaked production credentials in a PUBLIC repo, not rotated
- **Severity:** P0 (launch blocker: `secrets.md` §5 itself says the rotation must happen before the first paid customer or the shop migration, whichever comes first)
- **Evidence:** repo visibility PUBLIC (gh). Tracked files whose lines match hardcoded-credential patterns (names only, values not reproduced):
  - About 49 root scripts carry the Hostinger SSH password, e.g. `deploy_root_baraa.py`, `deploy_sroor.py`, `deploy_remote.py`, `deploy_live_safe.py`, `deploy_live_and_seed.py`, `deploy_hostinger_php83.py`, `deploy_hostinger_php84.py`, `deploy_all_locations.py`, `deploy_subdomain.py`, `deploy_local_to_server.py`, `sync_sroor.py`, `seed_initial_data.py`, `seed_fresh_direct.py`, `check_live_db.py`, `check_live_tinker.py`, `check_logs.py`, `check_server_git.py`, `fix_htaccess*.py`, `fix_server_timezone.py`, `fix_root_index.py`, `publish_windows_version.py`, `rebuild_cache.py`, `run_check.py`, `verify_*production*.py`, `test_*live*.py`, `test_val.php`, and others. The full list is in the `.gitleaksignore` baseline.
  - Embedded `.env` (DB password + `APP_KEY` of the live shop): `deploy_root_baraa.py`, `deploy_to_sroor_subdomain.py`, `deploy_with_mysql.py`, `fix_shipping_php83.py` (3 hits each).
  - Deploy webhook token in a URL: `.github/workflows/deploy.yml:49`. Webhook secret line: `update_webhook.php:6`, `backend/public/update_webhook.php:6`.
  - Test or other material: `.env.e2e` (tracked), `tests_e2e/config.py`, `.agents/skills/laravel-security/SKILL.md` (and its `backend/` copy).
  - `secrets.md` §5 records 91 historical findings in 63 files.
- **Problem:** anyone can SSH to the Hostinger account that hosts the live shop and the SaaS test central DB, read or alter the live DB, and forge sessions or decrypt data with the leaked `APP_KEY`. Removing the files from HEAD does not help, because git history is public.
- **Fix (rotation plan, CTO):**
  1. Change the Hostinger SSH password, then switch to key-only login (hPanel → SSH Access → add a public key, disable the password if Hostinger allows it).
  2. Change every MySQL user password on Hostinger.
  3. Generate a new `APP_KEY` for the live shop in a maintenance window. This invalidates sessions and anything stored with `encrypt()`, so re-encrypt first if needed.
  4. Revoke the webhook token and disable the GET webhook (see OPS-02).
  5. Revoke the Telegram bot token if it was ever committed, and create a new bot.
  6. Check whether `tests_e2e/config.py` and `.env.e2e` hold real accounts; rotate if they do.
  7. Only after rotation, `git rm --cached` the root scripts (the decision is in product-overview §17) and keep the useful ones as `scripts/ops/*` reading from `.env`.
  8. Consider making the repo **private** until rotation is done. This is a settings change only the CTO can make.
  The VPS already starts with fresh secrets (`vps-runbook.md` §1), so the SaaS launch itself does not reuse any leaked value.
- **Effort:** M

#### OPS-02 — Live-shop deploy is a public GET webhook that hard-resets and migrates with no backup
- **Severity:** P0 (current production exposure)
- **Evidence:**
  - `.github/workflows/deploy.yml` (on `main`, push to `main`/`master`) runs `composer update` (not `install`, so the dependency set is not reproducible) and tests, then `curl` GET `…/update_webhook.php?token=…` (line 49).
  - `main:update_webhook.php:36-47` runs `git fetch` + `git reset --hard origin/main` + cache rebuild. `main:public/update_webhook.php:32-36` runs `git reset --hard` + `artisan migrate --force`. `main:backend/public/update_webhook.php:31-32` contains `key:generate --force || true` and `migrate --force --seed || true`. The same lines are in the tracked root `deploy.sh`.
  - On `feature/multi-tenant`, `backend/public/update_webhook.php` was hardened to an HMAC POST (`DEPLOY_WEBHOOK_HMAC_SECRET`). `main` was not.
- **Problem:** anyone who reads the public repo can trigger a redeploy plus migrations of the live shop at any time, with no backup and no review. Depending on which file the shop's docroot serves, an `APP_KEY` regeneration on deploy is possible. That would break every session and encrypted value.
- **Fix:** CTO action on `main`, outside agent authority:
  1. Remove or rename the webhook files on the server, or block them in `.htaccess`.
  2. Disable `deploy.yml` (Actions → Disable workflow), or replace the curl with the HMAC POST variant and a secret held in GitHub Secrets.
  3. Take a manual backup before every `main` deploy.
  4. Never ship any `key:generate --force` path.
- **Effort:** S

#### OPS-04 (live-shop part) — see OPS-04 below.

### B. Release pipeline, CI gates and environments

#### OPS-03 — The VPS and the release pipeline have never run on real infrastructure
- **Severity:** P0
- **Evidence:** `vps-runbook.md` §14 and §15 say "NOT EXECUTED", and every row of the 15-step rehearsal is unchecked. `deploy-runbook.md` §5 says the same. `gh api …/environments` returns 0. `release.yml` has never run (gh 404 on the default branch). `vps-runbook.md` §2.4 still has "TODO(CTO): confirm the DNS provider" (needed for the DNS-01 wildcard). `secrets.md` §6 has "TODO(CTO): link to the red gitleaks run".
- **Problem:** all guarantees (atomic switch, rollback, health gate, grants, Horizon restart, wildcard TLS) are proven only against stubs. An environment named in a workflow is **auto-created without protection rules** by GitHub on first use, so a `v*` tag pushed before the CTO configures `production` would run `deploy` with no reviewer gate. The job would fail only because the secrets are missing.
- **Fix:**
  1. Create the `production` environment with the CTO as required reviewer and a `v*` tag policy before any tag is pushed (`secrets.md` §2).
  2. Choose the DNS provider and create a zone-scoped API token.
  3. Execute `vps-runbook.md` §15 steps 1–15, including the failing-migration and failing-health rehearsals, then the rebuild with fresh secrets.
  4. Record the results in §14.
- **Effort:** M (mostly CTO time)

#### OPS-05 — No branch, tag or ruleset protection; release can ship any tagged commit with a partial gate
- **Severity:** P1
- **Evidence:** gh reports `main` and `feature/multi-tenant` as "Branch not protected", and rulesets are `[]`. The `release.yml` `tests` job runs only PHPUnit (sqlite) plus the ops tests. It does **not** run Pint, Larastan, ESLint, Prettier, JS tests, the MySQL group (row locks, DECIMAL) or gitleaks. Nothing checks that the tagged commit is reachable from the release branch or passed `ci.yml`.
- **Problem:** a tag on an unreviewed or side-branch commit, or one that failed CI, reaches the approval step. The reviewer has nothing in the UI showing that CI was green. Pushes to `main` deploy the live shop without review.
- **Fix:**
  - Branch protection or rulesets: `feature/multi-tenant` (and later the release branch) requires a PR plus the checks `php`, `mysql`, `frontend`, `desktop`, `gitleaks`, up to date, no force push. Do the same on `main` with its deploy test job.
  - A tag ruleset: only admins may create `v*`.
  - In `release.yml` `tests`, add a step that fails unless `git merge-base --is-ancestor $GITHUB_SHA origin/<release-branch>`. Either reuse `ci.yml` through `workflow_call` (so the same five jobs gate the release) or query the check-runs for the SHA through the API.
- **Effort:** S

#### OPS-11 — No staging for the SaaS codebase; the "staging" deploy script ships an archived branch with fake data
- **Severity:** P1
- **Evidence:** `deploy_root_baraa.py:70-72` runs `git checkout feature/api-migration` + `reset --hard`. That branch is archived (tag `archive/api-migration` exists) and still present locally, although the decision says to delete it. Lines 123-129 run `migrate`, `tenants:migrate --tenants=2m --force || true`, `db:seed …` and `tenant:populate-realistic-data 2m` **on every run**. Lines 29-31 run `git add .` + commit + push. Line 46 uses `paramiko.AutoAddPolicy()` (no host-key check). No script or workflow deploys `feature/multi-tenant` anywhere. The decision (product-overview, W0) is to rehearse on the production VPS, wipe it, and add a staging VPS only after the first paid customer.
- **Problem:** after go-live, every release (central and tenant migrations, Horizon config, nginx changes) goes straight to production. There is no place to rehearse the shop migration (§16, "rehearsal on staging"), and no non-production target for `deploy.sh` regressions. Meanwhile `CLAUDE.md` still calls `deploy_root_baraa.py` "the sanctioned deploy path".
- **Fix:**
  1. Recommendation for a CTO decision: a small staging VPS (about EUR 5–8/month) before go-live, provisioned by the same `provision.sh` and deployed by the same `release.yml` through a second environment `staging` (auto-deploy on `feature/multi-tenant` or `v*-rc*` tags, no reviewer).
  2. Retire `deploy_root_baraa.py` as the documented path, and update `CLAUDE.md` and the security rules accordingly.
- **Effort:** M

#### OPS-15 — Deploy time and blast radius grow linearly with tenant count
- **Severity:** P2
- **Evidence:** `scripts/ops/deploy.sh:440-468`:
  - The pre-migration `backup:tenants --no-cleanup` covers **every** tenant and uploads to Drive on **every** deploy.
  - It is followed by a sequential `tenants:migrate --force`, where "any failing tenant stops the release".
  - `deploy-runbook.md` §4.1 states that migrations are never rolled back.
- **Problem:** at 200+ tenants a deploy can take tens of minutes. During that window the old code serves a schema that is partly migrated across tenants. A failure on tenant N leaves 1..N-1 migrated with no switch (exit 1), and there is no per-tenant migration status or resume. The Google Drive upload quota and throughput become the bottleneck.
- **Fix:**
  - Enforce expand/contract (backward-compatible) migrations with a CI check that flags `dropColumn`, `renameColumn` or `change()` in `database/migrations/tenant` unless they are labelled.
  - Back up before migrating only when the release contains new migrations (compare `migrate:status`).
  - Run `tenants:migrate` with bounded parallelism (stancl supports `--tenants` chunks), log per-tenant duration, set a time budget and alert, and document how to resume after a partial run.
- **Effort:** M

#### OPS-19 — Supply chain: no dependency scanning, actions not SHA-pinned
- **Severity:** P2
- **Evidence:** gh reports `dependabot_security_updates: disabled`. There is no `composer audit` or `npm audit` step in `ci.yml` or `release.yml`. Actions are pinned by tag (`actions/checkout@v4`, `shivammathur/setup-php@v2`, `actions/cache@v4`, `actions/upload-artifact@v4`); the release `build` job runs third-party `setup-php`.
- **Fix:**
  - Enable Dependabot alerts and security updates (composer, npm in `backend/` and `desktop/`, github-actions).
  - Add `composer audit --locked` and `npm audit --omit=dev --audit-level=high` to CI.
  - Pin third-party actions to commit SHAs in `release.yml`.
- **Effort:** S

#### OPS-25 — CI PHP version differs from production
- **Severity:** P3
- **Evidence:** `ci.yml:54` uses `PHP_VERSION: '8.3'`. `release.yml:455` and the VPS use 8.4. Larastan and the MySQL group run only on 8.3.
- **Fix:** move `ci.yml` to 8.4 (or use a matrix of 8.3 and 8.4 for the php job).
- **Effort:** S

#### OPS-26 — Versioning has several sources of truth; the release workflow is not on the default branch
- **Severity:** P3
- **Evidence:**
  - `bump_version.py` writes `backend/version.json` and `backend/resources/js/version.json`.
  - Android `backend/android/app/build.gradle:10-11` has `versionCode 3` / `versionName "1.0.2"`, edited by hand.
  - `desktop/package.json` stays at `version` `1.0.0`.
  - SaaS releases use `v*` tags.
  - `release.yml` is absent on `main`, so "Run workflow" is not offered in the UI.
- **Fix:** derive `version.json` from the tag at build time in `release.yml`, and derive the app versions from their own release tags (`android-v*`, `desktop-v*`) or CI. Drop `bump_version.py` once the root deploy flow is retired.
- **Effort:** S

### C. Backups and disaster recovery

#### OPS-04 — Backups have never run or been restored on real infrastructure; the live shop's backup is an unencrypted dump on Telegram
- **Severity:** P0
- **Evidence:**
  - `backup-restore.md` §2: "Google Drive setup (by the CTO, once) — not done". §6: "first drill not done", and the drill log table is empty.
  - `check-env.sh` blocks a release without the Drive OAuth keys or `BACKUP_ARCHIVE_PASSWORD`, which is good, but this has never been exercised.
  - Live shop: `main:routes/console.php:36-37` schedules `backup:telegram` (a gzipped SQL dump to Telegram, unencrypted, containing PII and password hashes). This contradicts production-ops rule 8 ("Telegram never carries dumps").
  - Dead code that could revive this path is still on this branch: `backend/app/Services/TelegramService.php:397` `sendDatabaseBackupNotification()` + `app/Services/DatabaseBackupService.php` (no callers found).
- **Problem:** there is no proven recovery for any tenant. The live shop's financial data has a single, unencrypted copy on a third-party chat platform.
- **Fix:**
  1. CTO: complete `backup-restore.md` §2. Set the OAuth consent screen to **In production**, store `BACKUP_ARCHIVE_PASSWORD` in a password manager **off** the server, run the first `backup:tenants`, then a restore drill (`--drop-after-verify`), and log it.
  2. Live shop: before the migration, add an encrypted off-site backup (even a manual weekly encrypted `mysqldump`). Stop the Telegram dump on `main` and delete the old dumps from the Telegram chat.
  3. Delete `sendDatabaseBackupNotification` and `DatabaseBackupService` from `feature/multi-tenant`.
- **Effort:** M

#### OPS-12 — RPO of 24 h and a single VPS as the single point of failure
- **Severity:** P1
- **Evidence:** a single Hetzner VPS (app + MySQL + Redis) per `vps-runbook.md` §1. Backups run daily at 01:30 (`routes/console.php:36-38`). Hetzner Backups are "preferred", not required (`vps-runbook.md` §2.2). `mysql-sroor.cnf.tmpl` does not configure binlog shipping or expiry (MySQL 8.4 enables binlog by default with 30-day expiry, unshipped).
- **Problem:** a disk or host failure, or an operator mistake, at 20:00 loses a full trading day of invoices, stock and treasury for **every** tenant. Unshipped binlogs fill the disk without protecting anything.
- **Fix:**
  - Make Hetzner daily Backups mandatory (S). This is a whole-VM image and complements the logical dumps.
  - Set `binlog_expire_logs_seconds` explicitly (e.g. 3 days) and either ship binlogs off-site every 5–15 min for point-in-time recovery (M), or add an hourly encrypted central + tenant dump for paid plans.
  - Write a "full server loss" runbook (provision new VPS → restore central → restore tenants → DNS) with a target RTO.
- **Effort:** M

#### OPS-17 — Single, deletable backup destination on a personal Google account
- **Severity:** P2
- **Evidence:** `BACKUP_DISKS=google` only (`production.env.example`). The `drive.file` scope (`backup-restore.md` §2.2) lets the server **delete** its own files, and retention (`backup:clean`) relies on that. The account is the owner's personal Gmail.
- **Problem:** a compromised VPS, a revoked token or Google account trouble can erase or stop all backups. There is no immutable or secondary copy. Customer data is stored outside Egypt in a personal account, which is a data-protection and contractual question (Egypt PDPL 151/2020) worth a legal check before selling.
- **Fix:** add a second disk with immutability, e.g. Hetzner Storage Box with snapshots, or Backblaze B2 / S3 with Object Lock (the same AES archives, `BACKUP_TENANT_DISKS=google,s3`). Alert when either target falls behind. Document the data-location statement for customer contracts.
- **Effort:** M

#### OPS-18 — The restore drill is manual and monthly
- **Severity:** P2
- **Evidence:** `backup-restore.md` §6 (manual, first business day of the month).
- **Fix:** schedule a weekly automated drill: `backup:restore-tenant <random ready tenant> --drop-after-verify` (and central monthly) on the VPS at low traffic, with failure → alert (OPS-07) and success → a heartbeat ping. Keep the manual quarterly drill from a *monthly* archive.
- **Effort:** S

### D. Queue, scheduler, Redis

#### OPS-08 — The scheduler starts an unsupervised `queue:work` every minute, including on the VPS beside Horizon
- **Severity:** P1
- **Evidence:** `backend/routes/console.php:12-14` runs `Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')->everyMinute()->withoutOverlapping()` with no environment condition. Line 16 runs `queue:restart` every six hours. The VPS uses Horizon (`supervisor-horizon.conf.tmpl`).
- **Problem:** on the VPS, a second worker outside Horizon consumes the `default` Redis queue with its own `tries` and timeout defaults, invisible to Horizon metrics and balancing. It is not `runInBackground()`, so `schedule:run` blocks for up to about 55 s every minute, which delays the `health:*` heartbeats and any task due in that minute. This is the staging (Hostinger) pattern applied everywhere.
- **Fix:** wrap it in `->when(fn () => (bool) config('queue.cron_worker'))` with `QUEUE_CRON_WORKER=true` only in the Hostinger `.env`. Add `->runInBackground()`. Add `QUEUE_CRON_WORKER=false` to `production.env.example`, plus a `check-env.sh` rule that it must not be true when `QUEUE_MODE=horizon`.
- **Effort:** S

#### OPS-09 — `ArchiveTenantJob` (timeout 1800 s) runs on the default Redis connection with `retry_after` 90 s
- **Severity:** P1
- **Evidence:**
  - `app/Jobs/ArchiveTenantJob.php:34` sets `tries = 1`. Line 46 sets the timeout to 1800 s.
  - Lines 48-55 route the job by `tenant_lifecycle.archive.queue_connection` / `queue`, both `null` (`config/tenant_lifecycle.php:70-71`). It therefore uses the `redis` connection, where `retry_after` = `REDIS_QUEUE_RETRY_AFTER` defaults to 90 (`config/queue.php:71`).
  - The Horizon default supervisor timeout is 60 (`config/horizon.php:215`).
  - Line 96 has a `failed()` that calls `ArchiveTenantAction::markFailed(...)`.
- **Problem:** after 90 s the reserved job becomes visible again and another worker picks it up. Attempt 2 exceeds `tries=1`, so `failed()` marks the archive failed **while the first run is still dumping or uploading**. The tenant status and audit trail diverge, and an operator may retry or purge based on a false failure.
- **Fix:** add a `long` Redis connection with `retry_after` greater than 1860 (e.g. 2000) and a Horizon supervisor `supervisor-long` (1 process, timeout 1830, tries 1). Set `TENANT_ARCHIVE_QUEUE_CONNECTION=long` / `TENANT_ARCHIVE_QUEUE=long` in `production.env.example`. Add a config test asserting `retry_after > job timeout` for every job with a custom timeout, mirroring what OPS-2 did for provisioning.
- **Effort:** S

#### OPS-10 — Pulse storage is not pinned to the central connection in production
- **Severity:** P1
- **Evidence:** `config/pulse.php:68` sets `'connection' => env('PULSE_DB_CONNECTION')` (null → `database.default`). stancl switches `database.default` to `tenant` during tenant requests, and Pulse ingests at request termination. Pulse tables exist only centrally (`database/migrations/2026_08_13_220048_create_pulse_tables.php`; none in `migrations/tenant`). `scripts/ops/templates/production.env.example` has `PULSE_ENABLED=true` but **no** `PULSE_DB_CONNECTION`. `docs/07-operations/local-testing.md:102` already says `PULSE_DB_CONNECTION=mysql` is mandatory locally.
- **Problem:** in production, tenant traffic, which is almost all traffic, fails to record (missing table) and produces error reports and log noise. Pulse shows only central requests.
- **Fix:** add `PULSE_DB_CONNECTION=mysql` to `production.env.example` and the staging `.env`, add a `check-env.sh` rule, and add a unit test asserting Pulse writes centrally while tenancy is initialized. Alternatively, default the config to `config('tenancy.database.central_connection')`.
- **Effort:** S

#### OPS-14 — One Redis with `noeviction` holds cache, sessions and queues
- **Severity:** P2
- **Evidence:** `scripts/ops/templates/redis-sroor.conf.tmpl` has `maxmemory ${REDIS_MAXMEMORY}` + `maxmemory-policy noeviction`. Cache (DB 1), sessions (`SESSION_DRIVER=redis`) and the queue share the instance. `app/Models/Setting.php:37` uses `Cache::rememberForever`, and report caches are versioned keys (`TenantCache::version`) that leave old versions behind until their TTL expires.
- **Problem:** cache growth reaching `maxmemory` makes **all** writes fail: logins (sessions), Horizon and the queue break at the same moment. That is a full outage caused by cache.
- **Fix:** run two Redis instances (or two systemd units): `redis-queue` (`noeviction`, AOF) for queue and Horizon, and `redis-cache` (`allkeys-lru`, no persistence) for cache and sessions (or keep sessions on the queue instance). Give every cache a TTL. Add a health or Pulse alert at 80 % of `used_memory`.
- **Effort:** S

#### OPS-22 — Missing housekeeping schedules; cron output discarded
- **Severity:** P2
- **Evidence:** `routes/console.php` has no `telescope:prune` (Telescope is **enabled** on the Hostinger staging, a shared host with a disk quota). It also has no per-tenant `activitylog:clean`, no per-tenant `sanctum:prune-expired`, no `horizon:snapshot` (the Horizon metrics stay empty), and no tenant-lifecycle sweep (trial → read-only → suspended → archive, product-overview §7; IDEN-3.x not yet scheduled). `scripts/ops/templates/cron-scheduler.tmpl` sends output to `>> /dev/null 2>&1`.
- **Fix:**
  - Add `telescope:prune --hours=48` (only when it is enabled) and `horizon:snapshot` every 5 min.
  - Add tenant-iterating prune commands (`Tenant::cursor()` + `$tenant->run()`, skipping tenants that are not ready).
  - Schedule the lifecycle sweep when IDEN-3 lands.
  - Send cron output to `shared/storage/logs/scheduler.log` (rotated).
- **Effort:** S

#### OPS-27 — Scheduler runs in UTC; tenant-facing schedules ignore the tenant timezone
- **Severity:** P3
- **Evidence:** `production.env.example` has `APP_TIMEZONE=UTC`. `notify:daily-summary` runs `dailyAt('23:59')` (01:59/02:59 Cairo time), and `notify:low-stock` runs at 09:00 UTC. The settings decision (2026-10-09) requires a per-tenant end of business day.
- **Fix:** add `->timezone('Africa/Cairo')` for platform jobs now. Turn tenant-facing notifications into an hourly dispatcher that selects tenants whose local business-day end has passed.
- **Effort:** S

### E. Monitoring, alerting, logs

#### OPS-06 — Sentry is installed but does not capture exceptions
- **Severity:** P1
- **Evidence:** `composer.json` requires `sentry/sentry-laravel` 4.28.0. `bootstrap/app.php:102-…` `withExceptions` does **not** call `\Sentry\Laravel\Integration::handles($exceptions)`. The package registers exception reporting only through that call (`vendor/sentry/sentry-laravel/src/Sentry/Laravel/Integration.php:58-60`), and there is no `sentry` log channel in `config/logging.php`. `check-env.sh` treats an empty `SENTRY_LARAVEL_DSN` as a warning only.
- **Problem:** even with a DSN set, production 500s are not reported. There is no error tracking at launch.
- **Fix:** add `Integration::handles($exceptions);` in `withExceptions`. Make the DSN **required** in `check-env.sh` for production. Keep `SENTRY_SEND_DEFAULT_PII=false` and add a `before_send` scrubber for the `Authorization` and `X-Tenant` headers. Tag events with `tenant_id` (non-PII) from tenancy initialization. Verify with `php artisan sentry:test` during the rehearsal.
- **Effort:** S

#### OPS-07 — No external uptime or dead-man monitoring; alerts go to one channel (mail via the same app)
- **Severity:** P1
- **Evidence:** health and backup notifications are mail-only (`config/health.php:31-45`, `config/backup.php:145-157`). `SCHEDULE_HEARTBEAT_URL` is "optional" (`backup-restore.md:141`). Telegram ops alerts are deferred to OPS-10 (`backup-restore.md` §1). No uptime monitor is mentioned in any runbook.
- **Problem:** if the VPS, PHP-FPM, cron or SMTP dies, the component that would send the alert is the one that is down, so nobody is told. POS customers notice first.
- **Fix:**
  - An external HTTP monitor on `https://<domain>/up` and one tenant host, every 1 min (UptimeRobot, Better Stack or Hetzner monitoring).
  - Make the healthchecks.io-style heartbeat **required**: one ping for `schedule:run` and one for successful `backup:tenants`, so silence triggers an alert.
  - Deliver ops alerts to Telegram (the platform bot, a CTO-only chat, success or failure only) in addition to mail.
  - Add a TLS-expiry check (certbot plus an external monitor).
- **Effort:** S

#### OPS-23 — Logs are local only, with no aggregation or redaction policy
- **Severity:** P3
- **Evidence:** `LOG_CHANNEL=daily`, `LOG_DAILY_DAYS=14`, `LOG_LEVEL=warning` (`production.env.example`). The Horizon log is rotated by supervisor at 10 MB × 5. nginx, php-fpm and MySQL logs use distro logrotate defaults. Nothing is shipped off the box.
- **Fix:** after OPS-06/07, ship errors to Sentry and optionally `daily` logs to Better Stack or Loki. Add a log-redaction processor (Monolog) for `Authorization`, `token`, `password` and `X-Deploy-Signature`. Document the retention (14 days app, 30 days nginx).
- **Effort:** S

### F. Hosting, tenancy, scaling, TLS

#### OPS-16 — MySQL is not tuned for many databases, and there is no capacity plan for DB-per-tenant
- **Severity:** P2
- **Evidence:** `scripts/ops/templates/mysql-sroor.cnf.tmpl` sets only the buffer pool, `max_connections = 300`, charset and isolation. There is no `table_open_cache`, `table_definition_cache`, `open_files_limit` / systemd `LimitNOFILE`, `innodb_open_files`, slow query log or `binlog_expire_logs_seconds`. The tenant schema is 51 migrations with 38 `Schema::create` tables plus package tables (≈ 40–50 tables per tenant), so 100 tenants ≈ 5k tables and 1,000 ≈ 50k. Each tenant also has its own MySQL user (`TENANT_DB_PER_TENANT_USER=true`).
- **Problem:** past roughly 40–80 active tenants the default caches thrash (table-definition and file-handle churn, higher latency). Backup, migrate and purge times grow linearly. There is no threshold that tells when to add a second DB host.
- **Fix:**
  - Set `table_definition_cache` ≈ tables + 20 %, `table_open_cache` 8–16k, `open_files_limit` / `LimitNOFILE` 65535 and `innodb_open_files` to match, plus a slow log at 1 s.
  - Record a capacity model in `vps-runbook.md`: tenants per 8 GB box, a trigger to move to a dedicated DB VPS, and a later per-tenant `tenancy_db_host` for sharding (stancl supports per-tenant connection templates).
  - Load-test with 200 synthetic tenants during the rehearsal.
- **Effort:** S (config) / M (plan + test)

#### OPS-21 — CORS allows every origin
- **Severity:** P2
- **Evidence:** there is no `backend/config/cors.php`, so the framework default applies: `vendor/laravel/framework/config/cors.php:22` `'allowed_origins' => ['*']`. production-ops rule 10 says "CORS restricted to app domains".
- **Fix:** publish `config/cors.php` with `allowed_origins_patterns` for `https://*.<CENTRAL_DOMAIN>`, the admin host, and the Capacitor (`https://localhost`, `capacitor://localhost`) and Electron origins, all driven by env. Add a check in `check-env.sh`.
- **Effort:** S

#### OPS-24 — Custom domains (Phase 3) are not supported by the edge
- **Severity:** P3
- **Evidence:** `nginx-site.conf.tmpl:7-10` and `:22-24` (the default server returns `444`), `server_name ${PLATFORM_DOMAIN} *.${PLATFORM_DOMAIN}`, and a single wildcard certificate (`vps-runbook.md` §7).
- **Fix (when the `custom.domain` add-on is built):** add domain verification (a TXT or CNAME to `<slug>.<domain>`), on-demand TLS (Caddy `on_demand_tls` with an `ask` endpoint, or certbot HTTP-01 per domain with a renewal hook), an nginx include per domain, and Let's Encrypt rate-limit handling.
- **Effort:** L

#### OPS-29 — Real client IP is not handled if a CDN or proxy is put in front
- **Severity:** P3
- **Evidence:** `vps-runbook.md` §12.6. There is no `trustProxies(...)` in `bootstrap/app.php`.
- **Problem:** behind Cloudflare, every IP-keyed throttle (login, public endpoints) would collapse to proxy IPs, and audit IPs would be wrong.
- **Fix:** add env-driven `trustProxies(at: …)` plus nginx `real_ip_header CF-Connecting-IP` with the Cloudflare ranges. Do this only if a proxy is adopted.
- **Effort:** S

#### OPS-30 — No edge rate limiting
- **Severity:** P3
- **Evidence:** there is no `limit_req` in `nginx-site.conf.tmpl`. Throttling is app-level only (`config/rate_limits.php`).
- **Fix:** add a coarse `limit_req_zone` per IP for `/api/v1/auth/*` and the tenant resolver, so floods are rejected before PHP-FPM (`pm.max_children`) saturates.
- **Effort:** S

### G. Mobile and desktop release

#### OPS-13 — The Android APK is debug-built and unsigned for release; the Windows build is unsigned; neither is built in CI
- **Severity:** P1
- **Evidence:**
  - `build-apk.bat:31` runs `call gradlew.bat assembleDebug`.
  - `backend/android/app/build.gradle` has no `signingConfigs` block. Lines 10-11 set `versionCode 3` / `versionName "1.0.2"` by hand, and line 21 sets `minifyEnabled false`.
  - `desktop/package.json` `build.win` has no signing configuration and no `publish`.
  - No workflow builds APK or EXE. The decisions (W0) require APK signing and Authenticode, with the certificate bought by the CTO.
  - Positive: the super-admin release upload stores SHA-256 and serves files from private storage (`deploy-runbook.md` 7b and 12b).
- **Problem:** customers who install a debug-signed APK **cannot upgrade in place** to a properly signed APK (Android signature mismatch forces an uninstall, which loses local data). The debug keystore is generic to the build machine. Unsigned EXEs trigger SmartScreen and cannot be verified by the updater.
- **Fix:**
  - Generate the release keystore once (CTO). Store it and its passwords **only** as GitHub Environment secrets (`ANDROID_KEYSTORE_B64`, …) plus an offline backup. Add a `signingConfigs.release` that reads env vars.
  - Add an `apps` workflow on `android-v*` / `desktop-v*` tags: `npm run build` → `cap sync` → `assembleRelease` (or `bundleRelease` for Play) → `apksigner verify` → artifact.
  - Build electron-builder on `windows-latest` with an Authenticode certificate from secrets (or Azure Trusted Signing).
  - Set `versionCode` from the tag or run number.
  - Ship **no** customer APK before the release key exists.
- **Effort:** M

### H. Repo hygiene and docs

#### OPS-20 — Root scratch scripts, tracked build output, and dead risky code
- **Severity:** P2
- **Evidence:**
  - 99 tracked root files, 74 of them `*.py` (live-server `check_*`, `deploy_*`, `fix_*`, `sync_*`, `restore_sroor_db.py`, `test_*live*`), plus `backup_sroor_db.py`.
  - 95 tracked files under `backend/public/build/` although `.gitignore:22` has `public/build/`. The working tree currently shows 96 of them as deleted (` D`), so the next careless `git add .` (as in `deploy_root_baraa.py:29`) would commit the churn. 49 untracked root items are waiting to be swept the same way.
  - `.env.e2e` is tracked (gitleaks baseline). `feature/api-migration` still exists locally, against decision §15.
  - The dead unencrypted-dump code is covered in OPS-04.
- **Fix (after OPS-01 rotation and after the VPS pipeline is proven):**
  - `git rm --cached` the root `*.py`, `*.bat` and `*.ps1` that touch servers. Move the useful ones to `scripts/ops/` reading `.env`, and the rest to the scratchpad.
  - `git rm -r --cached backend/public/build` (the release builds it in CI; the `main` live shop still needs its own path, so do this only on `feature/multi-tenant`).
  - Rename `.env.e2e` to `.env.e2e.example` with dummy values.
  - Delete the local `feature/api-migration` (the tag already exists).
  - Stage explicit paths only.
- **Effort:** M

#### OPS-28 — Ops docs and skills drift from the code
- **Severity:** P3
- **Evidence:**
  - `.claude/skills/production-ops/SKILL.md` rule 1 says to use `CacheTenancyBootstrapper`, but `config/tenancy.php:59` keeps it commented and the code uses `App\Support\TenantCache` (manual tenant-scoped keys).
  - Rule 6 says the queue worker runs under supervisor `queue:work`, but the runbook uses Horizon.
  - `CLAUDE.md` and `security-and-operations.md` call `deploy_root_baraa.py` "the sanctioned deploy path".
  - The `ci-pipeline` skill lists only `php`, `frontend` and `desktop` as required checks (it misses `mysql` and `gitleaks`).
- **Fix:** update the skill, `CLAUDE.md` and the rules so they describe `release.yml`, Horizon, TenantCache and the 5 required checks. This needs the CTO's go-ahead because it changes `CLAUDE.md` and the rules.
- **Effort:** S

---

## Summary table

| ID | Severity | Title | Effort |
|---|---|---|---|
| OPS-01 | P0 | Leaked production credentials in a PUBLIC repo, not rotated | M |
| OPS-02 | P0 | Live-shop deploy = public GET webhook, `reset --hard` + migrate, no backup | S |
| OPS-03 | P0 | VPS and release pipeline never run on real infra; `production` environment missing; DNS provider TBD | M |
| OPS-04 | P0 | Backups never run or restored; live-shop backup is an unencrypted dump on Telegram | M |
| OPS-05 | P1 | No branch, tag or ruleset protection; release gate is a subset of CI; tagged commit not verified | S |
| OPS-06 | P1 | Sentry installed but not wired into exception handling; DSN optional | S |
| OPS-07 | P1 | No external uptime or heartbeat monitoring; mail-only alerts | S |
| OPS-08 | P1 | Scheduler runs an unsupervised `queue:work` every minute beside Horizon | S |
| OPS-09 | P1 | `ArchiveTenantJob` 1800 s timeout vs 90 s `retry_after`: double run, false failure | S |
| OPS-10 | P1 | Pulse not pinned to central DB, writes to tenant DBs in production | S |
| OPS-11 | P1 | No staging for the SaaS code; the "staging" script ships an archived branch plus fake data | M |
| OPS-12 | P1 | RPO 24 h, single VPS SPOF, no PITR, binlogs unmanaged | M |
| OPS-13 | P1 | APK debug-built and unsigned for release; EXE unsigned; no CI builds | M |
| OPS-14 | P2 | One Redis with `noeviction` for cache, sessions and queue | S |
| OPS-15 | P2 | Deploy time and blast radius scale with tenant count (backup all + sequential migrate) | M |
| OPS-16 | P2 | MySQL not tuned for many DBs; no DB-per-tenant capacity plan | S/M |
| OPS-17 | P2 | Single deletable backup target on a personal Google account; no immutable copy | M |
| OPS-18 | P2 | Restore drill manual and monthly; not automated | S |
| OPS-19 | P2 | Dependabot off, no composer/npm audit, actions not SHA-pinned | S |
| OPS-20 | P2 | Root scratch scripts, tracked `public/build`, `.env.e2e`, dead dump code | M |
| OPS-21 | P2 | CORS allows `*` | S |
| OPS-22 | P2 | Missing prune, snapshot and lifecycle schedules; cron output to /dev/null | S |
| OPS-23 | P3 | Logs local only, no aggregation or redaction policy | S |
| OPS-24 | P3 | Custom domains need on-demand TLS and an nginx change | L |
| OPS-25 | P3 | CI on PHP 8.3, production on 8.4 | S |
| OPS-26 | P3 | Several version sources; `release.yml` not on the default branch | S |
| OPS-27 | P3 | Scheduler in UTC; tenant timezone ignored | S |
| OPS-28 | P3 | Skills, `CLAUDE.md` and rules drift from the code (deploy path, cache, Horizon, checks) | S |
| OPS-29 | P3 | Real-IP / TrustProxies missing if a CDN is used | S |
| OPS-30 | P3 | No nginx `limit_req` on auth and public endpoints | S |

Count: 4 × P0, 9 × P1, 9 × P2, 8 × P3.

---

## Manual steps for the CTO (in order)

1. **Today (live shop):** disable `deploy.yml` (Actions → Deploy… → Disable workflow), or remove or block the `update_webhook.php` files on Hostinger. Take a manual DB backup of the live shop and store it encrypted off-server. (OPS-02, OPS-04)
2. **Rotate the Hostinger credentials** (SSH password → key-only, MySQL users, live-shop `APP_KEY` in a maintenance window, webhook secret, Telegram bot). Consider making the repo private until this is done. (OPS-01)
3. **GitHub settings:**
   - Create the environment `production`: required reviewer = you, deployment policy = tags `v*`.
   - Enable Dependabot alerts and security updates.
   - Create rulesets: `feature/multi-tenant` requires a PR and the checks `php`, `mysql`, `frontend`, `desktop`, `gitleaks`, with no force push; `main` requires a PR; a tag rule lets only admins create `v*`. (OPS-03, OPS-05, OPS-19)
4. **Accounts:** DNS provider plus a zone-scoped API token, the SMTP provider (Brevo or SES) with SPF/DKIM, a Sentry project, an uptime monitor plus 2 healthchecks.io checks, and the Google Cloud OAuth client set to **In production** with `drive.file`. (OPS-03, OPS-06, OPS-07, OPS-04)
5. **Environment secrets** in `production` (names per `secrets.md` §2 + release.yml): `APP_KEY`, `DB_PASSWORD`, `DB_AUDIT_PRUNER_PASSWORD`, `DB_PROVISIONER_PASSWORD`, `DB_MIGRATOR_PASSWORD`, `DB_BACKUP_PASSWORD`, `REDIS_PASSWORD`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `BACKUP_ARCHIVE_PASSWORD` (also store it in a password manager), `GOOGLE_DRIVE_CLIENT_ID`, `GOOGLE_DRIVE_CLIENT_SECRET`, `GOOGLE_DRIVE_REFRESH_TOKEN`, `SENTRY_LARAVEL_DSN`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_CHAT_ID`, `DEPLOY_SSH_PRIVATE_KEY`, `DEPLOY_SSH_KNOWN_HOSTS`. Vars: `APP_URL`, `SESSION_DOMAIN`, `SANCTUM_STATEFUL_DOMAINS`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS`, `CENTRAL_DOMAIN`, `CENTRAL_ADMIN_DOMAINS`, `CENTRAL_PASSWORD_RESET_URL`, `BACKUP_NOTIFICATION_EMAIL`, `DEPLOY_HOST`, `DEPLOY_PORT`, `DEPLOY_USER`, `DEPLOY_APP_ROOT`. Later add `ANDROID_KEYSTORE_*` and the Windows signing certificate (OPS-13).
6. **Let the agents land the S-effort code fixes first:** OPS-06, 08, 09, 10, 14, 21, 22, plus the `release.yml` ancestry and CI-reuse gate (OPS-05) and MySQL tuning (OPS-16). Then do the **VPS rehearsal** `vps-runbook.md` §15 (steps 1–15), including a restore drill and a synthetic-tenant load test, then rebuild with fresh secrets. (OPS-03, OPS-04, OPS-16)
7. Decide on: a staging VPS before go-live (OPS-11), a second immutable backup target and PITR (OPS-12, OPS-17), and the Android release keystore before any customer install (OPS-13).
8. After the pipeline is proven: repo cleanup (OPS-20) and doc and skill sync (OPS-28).

## Risks and rollback notes
- The rotation (OPS-01) changes `APP_KEY` on the live shop. Re-encrypt any `encrypt()`-ed columns first, and expect every user to log in again. Rollback = restore the old `.env` from the off-server backup taken in step 1.
- Disabling `deploy.yml` stops auto-deploys of `main`. Hotfixes then need a manual, backed-up deploy. Rollback = re-enable the workflow, but only after the webhook uses HMAC.
- Gating the cron worker (OPS-08) on Hostinger needs `QUEUE_CRON_WORKER=true` in the Hostinger `.env` **before** that code is deployed there, otherwise jobs stop. Rollback = flip the env flag.
- New queue connections (OPS-09) and the split Redis (OPS-14) need matching `production.env.example`, `horizon.php` and provisioning templates in the same release, or Horizon will not consume the new queue. The `deploy.sh` health gate does not cover Horizon queues, so verify in Horizon after deploy. Rollback = `deploy.sh --rollback`; these are config-only, with no schema change.
- Branch protection (OPS-05) can block the CTO's own direct pushes. Admin bypass can stay enabled during the transition.
