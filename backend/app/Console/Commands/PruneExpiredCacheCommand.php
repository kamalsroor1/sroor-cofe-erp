<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Cache\DatabaseStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * PERF-01: delete expired rows of the database cache store.
 *
 * Laravel only deletes an expired database-cache row when that same key is read again. Keys
 * that are never read again stay forever: the dashboard cache (DashboardCache) puts the
 * business date and a version in every key, so yesterday's keys (and every key of a bumped
 * version) are never read again. The store is bound to the central connection at boot, so all
 * rows live in the central `cache` table. A no-op for any other cache driver.
 */
final class PruneExpiredCacheCommand extends Command
{
    protected $signature = 'cache:prune-expired
        {--chunk=1000 : Rows deleted per statement}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.cache_prune.description'));
    }

    public function handle(): int
    {
        $store = Cache::store()->getStore();
        if (! $store instanceof DatabaseStore) {
            $this->info((string) __('console.cache_prune.not_database', ['driver' => $store::class]));

            return self::SUCCESS;
        }

        $chunk = max(1, (int) $this->option('chunk'));
        $table = (string) config('cache.stores.'.config('cache.default').'.table', 'cache');
        $now = time();
        $total = 0;

        do {
            $keys = $store->getConnection()->table($table)
                ->where('expiration', '<=', $now)
                ->orderBy('expiration')
                ->limit($chunk)
                ->pluck('key')
                ->all();

            $deleted = $keys === []
                ? 0
                : $store->getConnection()->table($table)->whereIn('key', $keys)->where('expiration', '<=', $now)->delete();
            $total += $deleted;
        } while ($deleted >= $chunk);

        $this->info((string) __('console.cache_prune.done', ['count' => $total]));

        return self::SUCCESS;
    }
}
