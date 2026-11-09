<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * OPS-7: dead-man switch. The scheduler calls this every minute; it GETs OPS_HEARTBEAT_URL
 * (Better Stack / UptimeRobot / healthchecks.io heartbeat monitor). When the box, cron or PHP dies the
 * pings stop and the external service alerts, which an on-box mail alert can never do.
 * Empty URL = disabled. The URL is a secret: never logged.
 */
final class PingHeartbeatCommand extends Command
{
    protected $signature = 'ops:heartbeat';

    protected $description = 'Ping the external uptime monitor heartbeat URL (OPS_HEARTBEAT_URL)';

    public function handle(): int
    {
        $url = (string) config('health.heartbeat.url', '');
        if ($url === '') {
            return self::SUCCESS;
        }

        try {
            $response = Http::timeout(10)->get($url);
        } catch (Throwable $e) {
            Log::warning('Heartbeat ping failed.', ['exception' => $e::class]);
            $this->error((string) __('console.health.heartbeat_failed'));

            return self::FAILURE;
        }

        if (! $response->successful()) {
            Log::warning('Heartbeat ping was rejected.', ['status' => $response->status()]);
            $this->error((string) __('console.health.heartbeat_failed'));

            return self::FAILURE;
        }

        $this->info((string) __('console.health.heartbeat_sent'));

        return self::SUCCESS;
    }
}
