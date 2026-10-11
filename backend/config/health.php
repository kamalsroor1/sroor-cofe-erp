<?php

use Spatie\Health\Notifications\CheckFailedNotification;
use Spatie\Health\Notifications\Notifiable;
use Spatie\Health\ResultStores\CacheHealthResultStore;

/*
|--------------------------------------------------------------------------
| spatie/laravel-health (OPS-3 deploy gate, OPS-5 backups, OPS-7 monitoring)
|--------------------------------------------------------------------------
|
| The checks are registered in App\Providers\HealthServiceProvider. They run through
| `php artisan health:check` only: scheduled every 5 minutes (mail on failure) and
| by scripts/ops/deploy.sh as the release gate. No HTTP endpoint is exposed.
|
| Results are kept in the cache (no extra central table). Every address and URL
| comes from .env.
*/

return [

    'result_stores' => [
        CacheHealthResultStore::class => [
            'store' => env('HEALTH_CACHE_STORE', 'file'),
        ],
    ],

    'notifications' => [
        'enabled' => env('HEALTH_NOTIFICATIONS_ENABLED', true),

        'notifications' => [
            CheckFailedNotification::class => ['mail'],
        ],

        'notifiable' => Notifiable::class,

        // One mail per hour at most while checks keep failing.
        'throttle_notifications_for_minutes' => 60,
        'throttle_notifications_key' => 'health:latestNotificationSentAt:',

        // Warnings (e.g. a missing backup outside production) never send mail.
        'only_on_failure' => true,

        'mail' => [
            'to' => env('HEALTH_TO_ADDRESS', env('BACKUP_NOTIFICATION_EMAIL', env('MAIL_FROM_ADDRESS', ''))),

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
    ],

    'oh_dear_endpoint' => [
        'enabled' => false,
        'always_send_fresh_results' => true,
        'secret' => null,
        'url' => '/oh-dear-health-check-results',
    ],

    /*
     * Optional external dead-man switches (e.g. healthchecks.io): pinged after each
     * successful Horizon / schedule check, so the outside world notices when the box,
     * cron or Horizon dies and no health:check runs at all.
     */
    'horizon' => [
        'heartbeat_url' => env('HORIZON_HEARTBEAT_URL'),
    ],

    'schedule' => [
        'heartbeat_url' => env('SCHEDULE_HEARTBEAT_URL'),
    ],

    'theme' => 'light',

    'silence_health_queue_job' => true,

    'json_results_failure_status' => 200,

    'secret_token' => null,

    /*
     * Checks switched off by a condition (Redis when unused, the monitoring checks in the
     * deploy gate) are "skipped", not failed.
     */
    'treat_skipped_as_failure' => false,

    /*
     * Sroor thresholds (read by App\Providers\HealthServiceProvider).
     */
    'sroor' => [
        'disk_warn_percent' => (int) env('HEALTH_DISK_WARN_PERCENT', 80),
        'disk_fail_percent' => (int) env('HEALTH_DISK_FAIL_PERCENT', 90),
        // true on the VPS (queue-mode horizon); the staging box has no Horizon.
        'horizon' => (bool) env('HEALTH_CHECK_HORIZON', false),
        'schedule_max_age_minutes' => (int) env('HEALTH_SCHEDULE_MAX_AGE_MINUTES', 5),
    ],
];
