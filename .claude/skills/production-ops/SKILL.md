---
name: production-ops
description: Sroor ERP production/staging operations standards — VPS provisioning (Redis, queue worker, cron, per-tenant DB auto-provisioning), production .env standards, encrypted per-tenant backups to Google Drive, and the staging (Hostinger shared) constraints. Use when designing or reviewing infra, deploy, backup, queue/scheduler, or environment configuration work.
---

# Production operations (decided by the CTO on 2026-10-08)

The full decisions live in `docs/01-overview/product-overview.md`. This is the operational summary.

## Environments
| Env | Where | Notes |
|---|---|---|
| **Staging / test** | Current Hostinger **shared** server | Also hosts one **live shop running the `main` branch**, so treat it with production care. No Redis, no supervisor. Leave its `.env` (incl. `APP_DEBUG`) as the CTO set it |
| **Production (SaaS)** | New **VPS**, self-managed | Fresh secrets only. Nothing from the old scripts is reused |

## Production standards checklist
1. `CACHE_STORE=redis` with stancl `CacheTenancyBootstrapper`, so cache is per tenant. On staging use the file cache with tenant-prefixed keys.
2. `APP_DEBUG=false`, `APP_ENV=production`.
3. Telescope disabled in production (`TELESCOPE_ENABLED=false`), enabled on staging.
4. Pulse enabled, reachable only by the central super-admin.
5. `config:cache` + `route:cache` on every deploy. No `env()` outside `config/*.php`.
6. Queue on Redis, worker under `supervisor`. On staging use `QUEUE_CONNECTION=database` plus a cron `schedule:run` that runs `queue:work --stop-when-empty --max-time=50` every minute.
7. Scheduler cron every minute. Any tenant-related job runs inside tenancy (`tenancy()->runForMultiple` / queue tenancy bootstrapper). Never touch tenant data from central context.
8. Backups: daily for **every tenant DB and the central DB**, encrypted before upload, sent to **Google Drive**. Retention 7 daily / 4 weekly / 3 monthly. Restore tested monthly. Telegram only reports success or failure, never carries dumps.
9. Secrets only in the server `.env` and GitHub Actions Secrets. SSH by key only, password login disabled. Never write secrets into scripts, docs or logs.
10. HTTPS enforced, `SESSION_SECURE_COOKIE=true`, correct `SANCTUM_STATEFUL_DOMAINS`, CORS restricted to app domains, rate limiting on login and public endpoints, daily log rotation with no tokens or passwords in logs.

## Google Drive backups (personal Gmail account with a paid storage plan)
- A service account cannot be used: it has no Drive quota. Use **OAuth with the owner's account**:
  1. Create a Google Cloud project and enable the Drive API.
  2. Create an OAuth client and get a one-time consent → refresh token, stored in `.env` only.
  3. Set the OAuth consent screen to **In production**. In "Testing" mode refresh tokens expire after 7 days.
- Use a dedicated folder. Package: `spatie/laravel-backup` plus a maintained Google Drive Flysystem adapter (justify the choice).
- Archives are password-encrypted (key in `.env`). The job alerts on Telegram on failure. If the owner changes their Google password or revokes access, backups stop; the alert must catch this.

## VPS provisioning (outline)
PHP 8.3+ FPM (bcmath, intl, pdo_mysql, redis, gd, zip) · Nginx · MySQL 8 with a dedicated app user that has `CREATE DATABASE` rights limited to the `tenant%` prefix · Redis · supervisor (queue) · cron (scheduler) · certbot · ufw (22 with key only, 80, 443) · fail2ban · unattended security upgrades.

## Never
- Run anything against the live server or the live shop without the CTO's explicit request in that message.
- Run `migrate:fresh`, `db:wipe`, seeders or `tenant:populate-realistic-data` on staging or production.
- Reuse any credential that appears in git history. All of them are considered leaked, because the repos are public.
