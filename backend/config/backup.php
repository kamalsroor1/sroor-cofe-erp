<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Spatie\DbDumper\Compressors\GzipCompressor;

/*
|--------------------------------------------------------------------------
| spatie/laravel-backup
|--------------------------------------------------------------------------
|
| Published on purpose: the package default backs up the WHOLE base_path(),
| which puts `.env` (APP_KEY, DB / mail / Telegram / OAuth credentials) inside
| every archive that leaves the server. This config never does that.
|
| What goes into an archive:
|   - database dumps (the `databases` list below);
|   - user files only: storage/ (central + per-tenant uploads, because stancl
|     suffixes storage_path() to storage/tenant<id>/), minus caches, logs and
|     key material.
| The application code is NOT archived: it is in git and in the release dirs.
|
| Secret files are excluded twice: they sit outside the include roots, and the
| exclude list names them explicitly, so re-adding base_path() to `include`
| still cannot leak them. tests/Unit/BackupConfigTest.php enforces this.
|
| Database backups (central + every tenant, one encrypted archive each) are
| made by `php artisan backup:tenants` (OPS-5, the `tenants` block below);
| spatie's own `backup:run --only-files` is scheduled for the user files.
| Runbook: docs/07-operations/backup-restore.md. Every sensitive value comes
| from .env.
*/

$backupName = (string) env('BACKUP_NAME', env('APP_NAME', 'sroor'));
$backupDisks = array_values(array_filter(array_map('trim', explode(',', (string) env('BACKUP_DISKS', 'local')))));

// CTO decision D4: no archive password = no destination at all, so spatie's backup:run
// fails loudly (and notifies) instead of uploading a plain zip. Security audit (W2 lane 3I):
// enforced everywhere except local/testing (staging included), like backup:tenants.
// The application itself still boots; the BackupArchivePassword health check is red.
if (! in_array((string) env('APP_ENV', 'production'), ['local', 'testing'], true)
    && (string) env('BACKUP_ARCHIVE_PASSWORD', '') === '') {
    $backupDisks = [];
}

return [

    'backup' => [
        'name' => $backupName,

        'source' => [
            'files' => [
                'include' => [
                    storage_path(),
                ],

                // Globs are expanded by spatie at backup time (FileSelection::sanitize()).
                'exclude' => [
                    // Secrets: environment files and key material.
                    base_path('.env'),
                    base_path('.env.*'),
                    base_path('auth.json'),
                    storage_path('*.key'),
                    storage_path('*.pem'),
                    storage_path('oauth-*'),
                    storage_path('app/private/*.key'),
                    storage_path('app/private/*.json'),
                    // Regenerable / noisy runtime state (central and per tenant).
                    storage_path('framework'),
                    storage_path('logs'),
                    storage_path('debugbar'),
                    storage_path('pail'),
                    storage_path('inertia-devtools'),
                    storage_path('tenant*/framework'),
                    storage_path('tenant*/logs'),
                    // Never re-archive local backups or temp dumps.
                    // (the `local` disk root is storage/app/private).
                    storage_path('app/backup-temp'),
                    storage_path('app/private/'.$backupName),
                ],

                'follow_links' => false,

                'ignore_unreadable_directories' => true,

                // Paths inside the zip are relative to storage/, never absolute server paths.
                'relative_path' => storage_path(),
            ],

            /*
             * Central database. Per-tenant databases are dumped by the OPS-5
             * tenant-aware backup command, not by listing connections here.
             */
            'databases' => [
                env('BACKUP_DB_CONNECTION', env('DB_CONNECTION', 'mysql')),
            ],
        ],

        'database_dump_compressor' => GzipCompressor::class,

        'database_dump_file_timestamp_format' => 'Y-m-d-H-i-s',

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => env('BACKUP_FILENAME_PREFIX', ''),

            // Comma separated disk names, e.g. "local" on staging, "google" on the VPS (OPS-5).
            'disks' => $backupDisks,

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * Archive encryption. MUST be set in the server .env before any backup
         * leaves the machine. Empty in production = backups refuse to run (D4).
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'aes256',

        'verify_backup' => true,

        'tries' => 2,

        'retry_delay' => 60,
    ],

    'notifications' => [
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Sroor ERP'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    'log_channel' => null,

    'monitor_backups' => [
        [
            'name' => $backupName,
            'disks' => $backupDisks,
            'health_checks' => [
                MaximumAgeInDays::class => 1,
                MaximumStorageInMegabytes::class => 50000,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | OPS-5: per-tenant + central database backups (`php artisan backup:tenants`)
    |--------------------------------------------------------------------------
    |
    | One encrypted zip (AES-256, BACKUP_ARCHIVE_PASSWORD) per database per run:
    | the central DB and every tenant DB that is not archived. Each archive holds
    | the dump plus a manifest (row count per table) used by backup:restore-tenant
    | to prove a restore is complete. Every upload is recorded in the central
    | `tenant_backups` ledger with its sha256, then re-downloaded and verified.
    |
    | Layout on the disk: <path_prefix>/<subject>/<subject>-<Y-m-d-H-i-s>.zip where
    | <subject> is `central` or the tenant id. Retention (7 daily / 4 weekly /
    | 3 monthly) is applied per subject after a successful run.
    |
    | D4 (CTO): an empty BACKUP_ARCHIVE_PASSWORD outside local/testing makes
    | backup:tenants refuse to run (exit 1); the app itself still boots. Failure
    | mails carry the subject and exception class only, details go to the log.
    */
    'tenants' => [
        // Comma separated disk names: `google` on the VPS, `local` for dev/staging.
        'disks' => array_values(array_filter(array_map('trim', explode(',', (string) env('BACKUP_TENANT_DISKS', env('BACKUP_DISKS', 'local')))))),

        'path_prefix' => trim((string) env('BACKUP_PATH_PREFIX', 'sroor-backups'), '/'),

        // Local scratch directory for dumps and archives (deleted after each subject).
        'temporary_directory' => storage_path('app/backup-temp/tenants'),

        // Back up the central DB too (`--skip-central` turns it off for one run).
        'include_central' => true,

        // Connection dumped as "the central DB". null = tenancy.database.central_connection.
        'central_connection' => null,

        // Tenants in these statuses are skipped (their final backup is OPS-9's job).
        'skip_statuses' => ['archived'],

        // Re-download every archive after the upload and compare its sha256.
        'verify_after_upload' => (bool) env('BACKUP_VERIFY_AFTER_UPLOAD', true),

        'retention' => [
            'keep_all_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 3,
        ],

        // Failure alerts: always to the log; mail when an address is configured.
        'notify_mail' => env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS')),

        // Health: a subject without a verified backup younger than this is red (OPS-7).
        'max_age_hours' => (int) env('BACKUP_MAX_AGE_HOURS', 26),

        /*
         * backup:restore-tenant creates a NEW database named
         * <restore_database_prefix><subject>_<Y-m-d-H-i-s>. On MySQL the prefix
         * must start with TENANT_DB_PREFIX so the provisioner account may create it
         * (vps-runbook.md §5). Restoring over an existing database is refused.
         */
        'restore_database_prefix' => (string) env('BACKUP_RESTORE_DB_PREFIX', env('TENANT_DB_PREFIX', 'tenant_').'zz_restore_'),

        // MySQL account for the restore drill (CREATE + INSERT on the new database).
        // Empty = the central connection's credentials.
        'restore_username' => env('DB_RESTORE_USERNAME'),
        'restore_password' => env('DB_RESTORE_PASSWORD'),

        'mysql_binary_path' => (string) env('BACKUP_MYSQL_BINARY_PATH', ''),
    ],

    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        /*
         * Retention 7 daily / 4 weekly / 3 monthly (CTO decision 2026-10-08).
         * Periods are consecutive: the first 7 days keep everything (one backup a
         * day = 7 dailies), then one per week for 4 weeks, then one per month
         * for 3 months. No yearly copies.
         */
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 0,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 3,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

];
