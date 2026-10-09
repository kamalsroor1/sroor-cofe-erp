<?php

declare(strict_types=1);

use App\Models\CentralActivity;

/*
|--------------------------------------------------------------------------
| spatie/laravel-activitylog — PLATFORM-level audit only
|--------------------------------------------------------------------------
|
| The package is used for platform (central) audit, so it is pinned to the CENTRAL
| database: the activity model is App\Models\CentralActivity (UsesCentralConnection),
| and the `activity_log` table exists only in database/migrations (central).
|
| Tenant business audit is a different system and is NOT affected by this file:
| App\Services\ActivityLogService writes App\Models\ActivityLog rows to the tenant
| `activity_logs` table.
|
*/

return [

    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    // `activitylog:clean` deletes entries older than this many days. Platform audit is kept
    // 2 years (CTO W1 Q2); in production the scheduled `central-audit:prune` does the
    // deleting on the `audit_pruner` connection, because the app's DB user has no DELETE.
    'delete_records_older_than_days' => 730,

    'default_log_name' => 'default',

    // null = the current Laravel auth driver.
    'default_auth_driver' => null,

    'subject_returns_soft_deleted_models' => false,

    // Pinned to the central connection whatever the default connection is (tenancy
    // switches the default connection to the tenant DB while a tenant is initialized).
    'activity_model' => CentralActivity::class,

    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    // Same value as tenancy.database.central_connection (config/tenancy.php). The model
    // above is authoritative for queries; this value is kept central for anything that
    // still reads it (package stubs, Activity::__construct).
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),

    /*
    | IDEN-1.15: retention of the platform audit tables (`central_audit_logs`, written by
    | App\Services\CentralAuditLogger, and this package's `activity_log`). Rows strictly
    | older than `retention_days` are deleted by `central-audit:prune` (scheduled daily in
    | routes/console.php) through `pruner_connection`, a separate DB user that may only
    | DELETE (plus the SELECT MySQL needs for the WHERE) on those two tables. The app user
    | has no UPDATE/DELETE on them. `--days` can only extend retention, never shorten it.
    */
    'central_audit' => [
        'retention_days' => 730,
        'pruner_connection' => 'audit_pruner',
        'prune_chunk' => 1000,
    ],
];
