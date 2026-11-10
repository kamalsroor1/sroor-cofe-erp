# Monitoring storage and alerting (OPS-6, OPS-7, OPS-10)

## Pulse and Telescope write to the central database only (OPS-10)

stancl swaps the *default* DB connection to `tenant` while a request runs inside a shop. Pulse used
`PULSE_DB_CONNECTION` = null (= the default), so its tables were written into the shop databases.
`config/pulse.php` now falls back to `DB_CONNECTION` (the central connection, the same value as
`tenancy.database.central_connection`) and Telescope already pins `DB_CONNECTION`.
Pinned by `tests/Feature/Ops/MonitoringStorageIsCentralTest.php`.

Owner actions: never set `PULSE_DB_CONNECTION=tenant`. On servers that already ran Pulse inside shops,
the stray `pulse_*` tables in tenant databases can be dropped after a backup (not done automatically).

## Sentry (OPS-6)

`sentry/sentry-laravel` was installed but never attached to the exception handler, so nothing reached Sentry.
`bootstrap/app.php` now calls `Sentry\Laravel\Integration::handles($exceptions)`. Without a DSN the SDK is
inert (dev, CI and tests send nothing).

Privacy: `send_default_pii=false`, SQL bindings are off, and every event and transaction passes through
`App\Support\SentryScrubber` (`before_send` in `config/sentry.php`): it drops the user, request body, cookies,
query string and every header except a short allow-list, and redacts `Bearer`/`Basic` credentials, Sanctum
tokens and `password=`/`token=`/`api_key=` pairs inside messages. Pinned by `SentryScrubberTest` and
`tests/Feature/Ops/SentryWiringTest.php`.

Owner actions:
1. Create a Sentry project (platform Laravel) and copy its DSN.
2. Production `.env` (never commit it): `SENTRY_LARAVEL_DSN=<dsn>`, `SENTRY_ENVIRONMENT=production`;
   optional `SENTRY_RELEASE` (the deploy version) and `SENTRY_TRACES_SAMPLE_RATE=0.05`.
3. `php artisan config:clear` is not needed after a deploy (the pipeline rebuilds the config cache).
4. Add an alert rule in Sentry (new issue / regression -> your email or Telegram).

## Uptime, heartbeat and Telegram alerts (OPS-7)

Before: only `health:check` ran on the box (every 5 minutes) and mailed on failure. If the box, cron or PHP
died nothing was watching, and the only channel was email.

1. **Uptime probe:** `GET /up` (Laravel's health route: 200, no auth, no tenant, not traced by Sentry).
   Point an UptimeRobot / Better Stack HTTP monitor at `https://<platform-domain>/up`, interval 1-5 minutes.
2. **Dead-man heartbeat:** `ops:heartbeat` runs every minute from the scheduler and GETs `OPS_HEARTBEAT_URL`
   (empty = off). Create a *heartbeat* monitor (expected every 1-2 minutes, grace 3-5 minutes) and paste its URL.
   When cron/PHP/the box stops, the pings stop and the service alerts you. The URL is never logged.
3. **Telegram:** a failing `health:check` (disk, DB, Redis, queue, schedule, Horizon, backup freshness) now
   sends mail **and** a plain-text Telegram message (`PlatformCheckFailedNotification`, throttled to one per
   hour per channel). It uses its own bot, not the per-shop Telegram settings.

Owner actions (production `.env`, never commit values):
- `OPS_HEARTBEAT_URL=<heartbeat url>`
- `HEALTH_TELEGRAM_BOT_TOKEN=<bot token from @BotFather>`, `HEALTH_TELEGRAM_CHAT_ID=<your chat or group id>`
  (send the bot a message first; read the chat id from `getUpdates`).
- Create the UptimeRobot/Better Stack monitors above and route their alerts to Telegram/phone.
- Optional dead-man pings that already existed: `SCHEDULE_HEARTBEAT_URL`, `HORIZON_HEARTBEAT_URL`.

Pinned by `tests/Feature/Ops/UptimeAlertingTest.php`.
