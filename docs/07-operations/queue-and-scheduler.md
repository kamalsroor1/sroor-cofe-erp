# Queue workers and the scheduler (OPS-8, OPS-9)

## Who consumes the queue

| Host | `QUEUE_CONNECTION` | Worker | `SCHEDULER_QUEUE_WORKER` |
|---|---|---|---|
| Production / VPS | `redis` | Horizon under supervisor (`scripts/ops/templates/supervisor-horizon.conf.tmpl`) | off (default) |
| Staging on Hostinger shared (no resident process) | `database` | the cron-driven `schedule:run` | on (default) |
| Local / CI | `sync` or `database` | none / `composer dev` | off for `sync` |

OPS-8: `routes/console.php` used to run `queue:work --stop-when-empty` every minute on **every** host,
so on the VPS a second consumer raced Horizon on the same Redis queue (ignoring Horizon's per-queue
timeout/tries and its metrics). It is now gated by `config('queue.scheduler_worker')`:

- default: on for any connection except `redis`, `sync`, `null`;
- override with `SCHEDULER_QUEUE_WORKER=true|false` in `.env`;
- the `queue:restart` entry follows the same flag (Horizon is restarted by `deploy.sh` via
  `horizon:terminate`).

The check runs at schedule time, so `config:cache` is respected. Pinned by
`tests/Feature/Ops/SchedulerQueueWorkerTest.php`.

## Owner actions

- VPS: confirm `QUEUE_CONNECTION=redis` in the production `.env` (then nothing else is needed), and that
  `supervisorctl status sroor-horizon` is RUNNING.
- Hostinger staging: leave `QUEUE_CONNECTION=database`; do not set `SCHEDULER_QUEUE_WORKER=false`.

## Long jobs: ArchiveTenantJob on its own connection (OPS-9)

`ArchiveTenantJob` runs a final tenant backup (timeout 1800 s, one attempt). On the default redis
connection `retry_after` is 90 s, so the job was re-released and a second archive started while the
first was still running. Now:

- `config/queue.php` has a `long` connection (redis, queue `long`, `retry_after` never below 1900 s,
  default 2000 s; `LONG_QUEUE_RETRY_AFTER` raises it);
- `tenant_lifecycle.archive.queue_connection` / `.queue` read `TENANT_ARCHIVE_QUEUE_CONNECTION` /
  `TENANT_ARCHIVE_QUEUE`; the production template sets both to `long`;
- Horizon `supervisor-long` (1 process, timeout 1830 s, tries 1) consumes it. `supervisor-long` and
  `supervisor-provisioning` are now listed in `horizon.environments.production`: a supervisor in
  `defaults` only runs in an environment that names it.

Rule for any new long job: its connection's `retry_after` must exceed the job `timeout`, and the Horizon
supervisor timeout must sit between the two. Pinned by `tests/Feature/Ops/ArchiveQueueRetryAfterTest.php`.

Owner actions on the VPS `.env`: `TENANT_ARCHIVE_QUEUE_CONNECTION=long`, `TENANT_ARCHIVE_QUEUE=long`, then
deploy (Horizon restarts and starts `supervisor-long` and `supervisor-provisioning`).
