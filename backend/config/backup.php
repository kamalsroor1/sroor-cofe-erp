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
| Tenant DB dumps, the Google Drive disk and Telegram alerts are OPS-5
| (docs/05-planning/phase-1-plan.md). Every sensitive value comes from .env.
*/

$backupName = (string) env('BACKUP_NAME', env('APP_NAME', 'sroor'));
$backupDisks = array_values(array_filter(array_map('trim', explode(',', (string) env('BACKUP_DISKS', 'local')))));

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
         * leaves the machine; an empty value means an unencrypted zip.
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
