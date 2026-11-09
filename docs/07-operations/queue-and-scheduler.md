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
