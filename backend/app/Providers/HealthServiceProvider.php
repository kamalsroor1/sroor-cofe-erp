<?php

declare(strict_types=1);

namespace App\Providers;

use App\Health\Checks\BackupArchivePasswordCheck;
use App\Health\Checks\BackupFreshnessCheck;
use App\Health\Checks\DiskSpaceCheck;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use Masbug\Flysystem\GoogleDriveAdapter;
use RuntimeException;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\HorizonCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\RedisCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Facades\Health;

/**
 * Operations wiring of OPS-5 / OPS-7 (kept out of AppServiceProvider on purpose):
 *
 * 1. spatie/laravel-health checks, run by `php artisan health:check` (scheduled every
 *    5 minutes with mail on failure, and by scripts/ops/deploy.sh as the release gate).
 *    No HTTP route is registered (PackageAdoptionTest).
 *
 *    Deploy gate (deploy.sh exports SROOR_HEALTH_DEPLOY_GATE=1 to that artisan process):
 *    only what decides whether THIS release can serve requests: central DB, cache, Redis,
 *    disk, archive password (D4), and never as a warning. Monitoring only (not registered
 *    in the gate): Horizon, the queue and schedule heartbeats and backup freshness. Those
 *    depend on the cron / supervisor of the box, not on the release; gating on them would
 *    refuse the very first deploy (nothing has run yet) and roll back a good release while
 *    Horizon restarts.
 *
 * 2. The `google` filesystem driver (masbug/flysystem-google-drive-ext) used by the
 *    backup disk of config/filesystems.php. OAuth with the owner's refresh token: the
 *    access token is fetched lazily on the first API call, so nothing touches the network
 *    while the app boots or when the disk is merely resolved.
 */
final class HealthServiceProvider extends ServiceProvider
{
    public const DEPLOY_GATE_ENV = 'SROOR_HEALTH_DEPLOY_GATE';

    public function boot(): void
    {
        $this->registerGoogleDriveDriver();

        Health::checks(self::checks());
    }

    /**
     * The registered checks. In the deploy gate only the release-level checks are
     * registered and they never answer "warning" (spatie's --fail-command-on-failing-check
     * treats a warning as a failure, which would refuse or roll back a good release because
     * the disk is 81% full).
     *
     * @return list<Check>
     */
    public static function checks(?bool $deployGate = null): array
    {
        $gate = $deployGate ?? self::isDeployGate();
        $diskFail = (int) config('health.sroor.disk_fail_percent', 90);

        $checks = [
            DatabaseCheck::new()->connectionName((string) config('tenancy.database.central_connection')),
            CacheCheck::new(),
            RedisCheck::new()->if(static fn (): bool => self::usesRedis()),
            DiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage($gate ? $diskFail : (int) config('health.sroor.disk_warn_percent', 80))
                ->failWhenUsedSpaceIsAbovePercentage($diskFail),
            BackupArchivePasswordCheck::new()->failuresOnly($gate),
        ];

        if ($gate) {
            return $checks;
        }

        return [
            ...$checks,
            HorizonCheck::new()->if(static fn (): bool => (bool) config('health.sroor.horizon', false)),
            QueueCheck::new()->if(static fn (): bool => config('queue.default') !== 'sync'),
            ScheduleCheck::new()->heartbeatMaxAgeInMinutes((int) config('health.sroor.schedule_max_age_minutes', 5)),
            BackupFreshnessCheck::new(),
        ];
    }

    /**
     * True while scripts/ops/deploy.sh runs `health:check` as the release gate. Read from the
     * process environment at run time (config is cached by then and must not change).
     */
    public static function isDeployGate(): bool
    {
        $value = getenv(self::DEPLOY_GATE_ENV);

        return is_string($value) && filter_var($value, FILTER_VALIDATE_BOOL);
    }

    private static function usesRedis(): bool
    {
        return in_array('redis', [
            config('cache.default'),
            config('queue.default'),
            config('session.driver'),
        ], true);
    }

    private function registerGoogleDriveDriver(): void
    {
        Storage::extend('google', function (Application $app, array $config): FilesystemAdapter {
            $clientId = (string) ($config['clientId'] ?? '');
            $clientSecret = (string) ($config['clientSecret'] ?? '');
            $refreshToken = (string) ($config['refreshToken'] ?? '');

            if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
                throw new RuntimeException((string) __('console.backups.google_not_configured'));
            }

            $client = new GoogleClient;
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->setAccessType('offline');
            // Expired placeholder: the client exchanges the refresh token on the first call.
            $client->setAccessToken([
                'access_token' => '',
                'refresh_token' => $refreshToken,
                'created' => 0,
                'expires_in' => 0,
            ]);

            $options = [];
            $folderId = (string) ($config['folderId'] ?? '');
            if ($folderId !== '') {
                $options['sharedFolderId'] = $folderId;
            }

            $adapter = new GoogleDriveAdapter(new Drive($client), null, $options);

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });
    }
}
