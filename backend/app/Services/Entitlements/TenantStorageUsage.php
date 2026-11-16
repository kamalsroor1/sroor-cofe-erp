<?php

declare(strict_types=1);

namespace App\Services\Entitlements;

use App\Services\Import\ImportSessionStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\UnableToRetrieveMetadata;

/**
 * PLAN-ENF: usage of the `storage_mb` plan limit for the CURRENT tenant (call while tenancy is
 * initialized). Shared by the upload paths (through PlanEnforcer::withinStorageLimit) and the
 * subscription screen (GetTenantSubscriptionAction).
 *
 * What counts: the files the shop keeps on its tenant disks:
 *  - the tenant `media` table (shop logos, BRND-5): SUM(size);
 *  - the import wizard's uploaded files while their session can still use them (PRD-06): they
 *    sit on the tenant `local` disk under ImportSessionStore::DIRECTORY and stop counting when
 *    the commit deletes them or the session TTL (1 hour) expires, whichever comes first (an
 *    abandoned file stays on disk until the 24-hour sweep, but no longer counts).
 * SaaS billing receipts are platform data on the central disk and never count.
 *
 * Computed live, never cached: the media table holds a handful of rows per shop and the import
 * directory a few short-lived files, so the computation is one indexed SUM plus a tiny directory
 * listing, and it runs only on an upload or the subscription screen. A cached figure or a stored
 * counter would drift whenever a file disappears outside these paths (spatie deletes, the import
 * sweep) and need a reconciler; the live figure is always exact and a delete frees space at once.
 */
final class TenantStorageUsage
{
    public const BYTES_PER_MB = 1048576;

    public function usedBytes(): int
    {
        return $this->mediaBytes() + $this->importBytes();
    }

    /** Whole megabytes, rounded up (1 byte shows as 1 MB). */
    public function usedMb(): int
    {
        return intdiv($this->usedBytes() + self::BYTES_PER_MB - 1, self::BYTES_PER_MB);
    }

    private function mediaBytes(): int
    {
        if (! Schema::hasTable('media')) {
            return 0;
        }

        return (int) DB::table('media')->sum('size');
    }

    private function importBytes(): int
    {
        $disk = Storage::disk('local');
        $bytes = 0;
        // A file older than the session TTL can no longer be committed (its token expired); it
        // waits only for the stale-file sweep and must not cost the shop space meanwhile.
        $liveSince = now()->subSeconds(ImportSessionStore::TTL_SECONDS)->getTimestamp();

        foreach ($disk->files(ImportSessionStore::DIRECTORY) as $file) {
            try {
                if ($disk->lastModified($file) < $liveSince) {
                    continue;
                }

                $bytes += (int) $disk->size($file);
            } catch (UnableToRetrieveMetadata) {
                // Deleted between the listing and the stat (commit / sweep): it no longer counts.
            }
        }

        return $bytes;
    }
}
