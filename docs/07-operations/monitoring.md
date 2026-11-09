# Monitoring storage and alerting (OPS-6, OPS-7, OPS-10)

## Pulse and Telescope write to the central database only (OPS-10)

stancl swaps the *default* DB connection to `tenant` while a request runs inside a shop. Pulse used
`PULSE_DB_CONNECTION` = null (= the default), so its tables were written into the shop databases.
`config/pulse.php` now falls back to `DB_CONNECTION` (the central connection, the same value as
`tenancy.database.central_connection`) and Telescope already pins `DB_CONNECTION`.
Pinned by `tests/Feature/Ops/MonitoringStorageIsCentralTest.php`.

Owner actions: never set `PULSE_DB_CONNECTION=tenant`. On servers that already ran Pulse inside shops,
the stray `pulse_*` tables in tenant databases can be dropped after a backup (not done automatically).
