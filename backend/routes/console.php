<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 1. Run queue worker every minute (processes pending background jobs)
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=55')
    ->everyMinute()
    ->withoutOverlapping();

// 2. Restart queue worker gracefully every 6 hours to free RAM
Schedule::command('queue:restart')
    ->everySixHours();

// 3. Clear old Pulse monitoring entries weekly
Schedule::command('pulse:clear --force')
    ->weekly();

// Telegram jobs (#4-#7) run in central context. They are DISABLED by default
// (services.telegram.scheduled_jobs_enabled / TELEGRAM_SCHEDULED_JOBS_ENABLED)
// until they are tenant-aware and backups are encrypted. The check runs at
// schedule time, so the entries stay registered and config:cache is respected.
$telegramScheduledJobsEnabled = static fn (): bool => (bool) config('services.telegram.scheduled_jobs_enabled', false);

// 4. Send daily EOD business summary report to Telegram at 11:59 PM (disabled by default)
Schedule::command('notify:daily-summary')
    ->dailyAt('23:59')
    ->when($telegramScheduledJobsEnabled);

// 5. Send daily low stock notification to Telegram at 09:00 AM (disabled by default)
Schedule::command('notify:low-stock')
    ->dailyAt('09:00')
    ->when($telegramScheduledJobsEnabled);

// 6. Check and alert for overdue open shifts to Telegram every 2 hours (disabled by default)
Schedule::command('notify:overdue-shifts')
    ->everyTwoHours()
    ->when($telegramScheduledJobsEnabled);

// 7. Send daily gzipped SQL database backup to Telegram at 00:05 AM (disabled by default)
Schedule::command('backup:telegram')
    ->dailyAt('00:05')
    ->when($telegramScheduledJobsEnabled);
