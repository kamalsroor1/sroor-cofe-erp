<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. OPS-8: cron-driven queue worker for hosts WITHOUT a resident worker (Hostinger shared
// staging, QUEUE_CONNECTION=database). Where Horizon owns the queue (redis, the VPS) it must NOT
// run: two consumers on the same queue race and ignore Horizon's per-queue timeout/tries.
// Decided at schedule time from config('queue.scheduler_worker'), so config:cache is respected.
$schedulerQueueWorkerEnabled = static fn (): bool => (bool) config('queue.scheduler_worker', false);

Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')
    ->everyMinute()
    ->withoutOverlapping()
    ->when($schedulerQueueWorkerEnabled);

// 2. Restart queue workers gracefully every 6 hours to free RAM (same hosts only: Horizon
// is restarted by the deploy via horizon:terminate and supervisor).
Schedule::command('queue:restart')
    ->everySixHours()
    ->when($schedulerQueueWorkerEnabled);

// 3. Clear old Pulse monitoring entries weekly
Schedule::command('pulse:clear --force')
    ->weekly();

// Telegram jobs. OPS-24: the daily summary (#4) and the overdue-shift alert (#6) now run PER
// TENANT through one central command: it enters each provisioned tenant and queues a job only
// when due (summary: after the shop's business day ended; overdue: once per shift). Before, they
// ran in the central context where invoices / cash_shifts do not exist and nothing was ever sent.
// A failing tenant is isolated; any failure is logged and reported by onFailure.
// Low stock (#5) is still central and gated until it is tenant-aware (OPS-10).
$telegramScheduledJobsEnabled = static fn (): bool => (bool) config('services.telegram.scheduled_jobs_enabled', false);

// 4 + 6. Per-tenant daily summary and overdue-shift alert, checked every 15 minutes.
Schedule::command('notify:dispatch-tenant-alerts')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10)
    ->onFailure(static function (): void {
        \Illuminate\Support\Facades\Log::critical('notify:dispatch-tenant-alerts failed: at least one shop got no Telegram alert.');
    });

// 5. Send daily low stock notification to Telegram at 09:00 AM (disabled by default)
Schedule::command('notify:low-stock')
    ->dailyAt('09:00')
    ->when($telegramScheduledJobsEnabled);

// 7. OPS-5: encrypted backup of the central DB + every tenant DB to the backup disks
// (Google Drive on the VPS), verified, retention 7/4/3. It mails and logs every failure
// itself and refuses to run in production without BACKUP_ARCHIVE_PASSWORD (D4).
// The old unencrypted `backup:telegram` dump was removed for good.
Schedule::command('backup:tenants')
    ->dailyAt('01:30')
    ->withoutOverlapping(720);

// User files (storage/: uploads, receipts, logos) through spatie/laravel-backup, same
// password and disks; D4 leaves it without a destination, so it fails loudly.
Schedule::command('backup:run --only-files')
    ->dailyAt('02:30')
    ->withoutOverlapping(720);
Schedule::command('backup:clean')
    ->dailyAt('03:00')
    ->withoutOverlapping(120);

// OPS-7: health checks (mail on failure, throttled) + the heartbeats they watch.
Schedule::command('health:check')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
Schedule::command('health:schedule-check-heartbeat')
    ->everyMinute();
Schedule::command('health:queue-check-heartbeat')
    ->everyMinute();
// OPS-7: external dead-man switch (no-op while OPS_HEARTBEAT_URL is empty).
Schedule::command('ops:heartbeat')
    ->everyMinute()
    ->runInBackground();

// 8. IDEN-1.15: platform audit retention (2 years, CTO W1 Q2). Deletes ONLY rows strictly
// older than config('activitylog.central_audit.retention_days') from central_audit_logs and
// activity_log, through the dedicated `audit_pruner` DB connection (the app's DB user has
// no UPDATE/DELETE on those tables in production). --days can extend retention, never
// shorten it below the configured value.
Artisan::command('central-audit:prune {--days= : Keep rows younger than this many days (never below the configured retention)}', function (): int {
    $retention = max(1, (int) config('activitylog.central_audit.retention_days', 730));
    $requested = $this->option('days');
    $days = is_numeric($requested) ? max($retention, (int) $requested) : $retention;
    $cutoff = now()->subDays($days);
    $chunk = max(1, (int) config('activitylog.central_audit.prune_chunk', 1000));
    $connection = DB::connection((string) config('activitylog.central_audit.pruner_connection', 'audit_pruner'));

    $total = 0;
    foreach (array_unique(['central_audit_logs', (string) config('activitylog.table_name', 'activity_log')]) as $table) {
        do {
            $deleted = $connection->table($table)
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($chunk)
                ->delete();
            $total += $deleted;
        } while ($deleted >= $chunk);
    }

    $this->info((string) __('central_audit.prune.done', ['count' => $total, 'date' => $cutoff->toDateTimeString()]));

    return 0;
})->purpose('Delete platform audit rows older than the retention period (IDEN-1.15)');

Schedule::command('central-audit:prune')
    ->dailyAt('03:15')
    ->withoutOverlapping();

// BE-05 / IDEN-3.6: subscription lifecycle sweep. Persists trial → read_only (after the grace
// days), active → past_due → read_only and read_only → suspended as the System actor, with
// lifecycle events + central audit. Access is already enforced from the derived status by
// EnsureTenantActive; this keeps the stored status, the audit trail and the console in step.
Schedule::command('tenants:apply-lifecycle')
    ->dailyAt((string) config('tenant_lifecycle.sweep.at', '01:00'))
    ->withoutOverlapping(60);

// SELL-06: issue the first subscription invoice of every trial ending within
// billing.trial_invoice_lead_days (3) days. Idempotent (one invoice per tenant and period).
Schedule::command('billing:issue-trial-invoices')
    ->dailyAt('01:10')
    ->withoutOverlapping(60)
    ->onOneServer();

// SELL-02: trial / subscription expiry reminders (7 / 3 / 1 days before, the end day, and the
// day after = grace started): in-app notification per shop + one Telegram summary for the
// operator. After the 01:00 lifecycle sweep, in the morning so the owner reads it the same day.
// Idempotent (central tenant_lifecycle_reminders, UNIQUE tenant + kind + period end).
Schedule::command('tenants:send-lifecycle-reminders')
    ->dailyAt((string) config('tenant_lifecycle.reminders_at', '09:00'))
    ->withoutOverlapping(60)
    ->onOneServer();

// 9. SEC-03: drop expired tenant API tokens (12h fixed TTL) from every provisioned tenant DB.
// `tenants:run` is the app's provisioned-tenants-only variant (RunForProvisionedTenants).
Schedule::command('tenants:run sanctum:prune-expired --option="hours=24"')
    ->dailyAt('03:30')
    ->withoutOverlapping();

// PERF-01: drop expired rows of the database cache store (dashboard keys carry the business date
// and a version, so expired ones are never read - and lazily deleted - again).
Schedule::command('cache:prune-expired')
    ->dailyAt('03:45')
    ->withoutOverlapping();
