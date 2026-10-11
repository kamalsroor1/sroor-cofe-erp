<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Tenant lifecycle (central), IDEN-3.1
|--------------------------------------------------------------------------
|
| Durations of the subscription lifecycle, read by App\Support\Tenancy\TenantLifecyclePolicy.
| These are product decisions (CTO, 2026-10-08, pricing part 4 + Q-L1/Q-L2/Q-L4), not
| per-environment settings, so they are plain values and not env() lookups:
|
|   trial 14 days → (+7 days once, by a super-admin, never-paid tenants only)
|   → read-only 30 days → suspended → data kept 90 days → archived
|   active → past_due 7-day grace (still working, with reminders) → read-only 30 days → …
|
| Read-only tenants get HTTP 423 on writes, suspended/cancelled/archived get HTTP 403.
| All values are whole days.
|
*/

return [

    // Length of a new tenant's free trial (Pro features, small limits).
    'trial_days' => 14,

    // One-time extension a super-admin may grant to a never-paid trial (also when read-only).
    'trial_extension_days' => 7,

    // past_due: the shop keeps working (with alerts) for this many days after the paid period ends.
    'past_due_grace_days' => 7,

    // read_only: view/export only for this many days, then suspended.
    'read_only_days' => 30,

    // suspended / cancelled: data kept this many days, then archived (never purged automatically).
    // null disables automatic archiving.
    'retention_days' => 90,

    // Days before a deadline on which reminders are sent (IDEN-3.7).
    'reminder_days' => [7, 3, 1],

    'sweep' => [
        // Most moves a never-swept tenant can be caught up by in one run
        // (active → past_due → read_only → suspended → archived). IDEN-3.6.
        'max_catch_up_steps' => 4,

        // Tenants loaded per chunk by the daily sweep.
        'chunk' => 100,

        // Daily run time, in the platform timezone (IDEN-3.6).
        'at' => '01:00',
    ],

    /*
    | OPS-9 archive (CTO Q1, Q4, Q6): DELETE /super-admin/tenants/{id} queues ArchiveTenantJob,
    | which takes a FINAL backup (backup:tenants --kind=final), proves it uploaded + verified,
    | then moves the tenant to `archived`. The single status writer (TransitionTenantStatusAction)
    | refuses `archived` without a verified final / pre_purge backup of the same tenant that is
    | at most `final_backup_max_age_minutes` old. Nothing is ever dropped by an archive.
    */
    'archive' => [
        'final_backup_max_age_minutes' => 120,

        // ArchiveTenantJob: one attempt, killed (→ archive_state failed) after this.
        'job_timeout_seconds' => 1800,

        // null = the default queue connection / queue.
        'queue_connection' => null,
        'queue' => null,

        // A queued / running archive with no progress for this long may be requested again
        // (lost dispatch, dead worker). Must stay above job_timeout_seconds.
        'stale_after_minutes' => 60,
    ],

    /*
    | OPS-9 purge (CTO Q2, Q3, Q4, Q5): `php artisan tenants:purge {tenant}` ONLY, never
    | scheduled, never from the API, no --force. Eligible: archived for archived_min_days, or
    | suspended / cancelled for blocked_min_days; a `violation` suspension never. Before the
    | drop: a verified backup at most backup_max_age_hours old, sha256 re-checked on the disk,
    | decrypted, and (require_restore_drill) restored into a scratch database and counted.
    */
    'purge' => [
        'archived_min_days' => 30,
        'blocked_min_days' => 90,
        'backup_max_age_hours' => 24,
        'require_restore_drill' => true,

        // Cache lock shared with the archive flow (tenant-lifecycle:{id}).
        'lock_seconds' => 3600,
    ],

];
