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
