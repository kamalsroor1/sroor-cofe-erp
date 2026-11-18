<?php

declare(strict_types=1);

namespace App\Services\Tenancy;

use App\Models\Tenant;
use App\Support\TenantCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * FREE-15: stamps `tenants.last_activity_at` (central) so the dormant free shop policy knows
 * which shops are alive.
 *
 * Cheap by construction: the central write happens at most once per
 * tenant_lifecycle.dormant.activity_throttle_minutes (15) per shop. The guard lives under a
 * CENTRAL key (identical inside and outside tenancy). A request inside the window costs exactly
 * one cache read (Cache::has, no write even on the database store); only on a miss does it
 * Cache::add (atomic, so concurrent first requests race for a single winner). A login (`force`)
 * always writes and refreshes the guard.
 *
 * The write is a plain query-builder UPDATE of that one column: it never loads or saves the Tenant
 * model (stancl's `data` column and a concurrent status transition are untouched). It must never
 * break a request: any failure is logged (class only) and swallowed.
 */
final class TenantActivityTracker
{
    private const KEY_PREFIX = 'tenant-activity:';

    public function touch(Tenant|string $tenant, bool $force = false): void
    {
        $id = $tenant instanceof Tenant ? (string) $tenant->getKey() : $tenant;
        if ($id === '') {
            return;
        }

        try {
            $ttl = now()->addMinutes(max(1, (int) config('tenant_lifecycle.dormant.activity_throttle_minutes', 15)));
            $key = TenantCache::centralKey(self::KEY_PREFIX.$id);

            if ($force) {
                Cache::put($key, 1, $ttl);
            } elseif (Cache::has($key) || ! Cache::add($key, 1, $ttl)) {
                return;
            }

            DB::connection((string) (new Tenant)->getConnectionName())
                ->table((new Tenant)->getTable())
                ->where('id', $id)
                ->update(['last_activity_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('Tenant activity stamp failed.', ['exception' => $e::class, 'tenant_id' => $id]);
        }
    }
}
