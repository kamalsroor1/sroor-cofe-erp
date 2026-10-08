<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use LogicException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;

/**
 * Media rows that belong to the PLATFORM, stored in the CENTRAL `media` table (PKG-2).
 *
 * Use it (through Concerns\InteractsWithCentralMedia) for every file uploaded from a
 * tenant request into the platform — payment receipts (ENTI-3.3, disk `central_private`)
 * — and for platform assets (BRND-2, disk `central_public`). The default media model
 * (config media-library.media_model) follows the default connection, so inside a tenant
 * request it would silently write the row to the tenant DB and the file under
 * storage/tenant<id>/.
 *
 * Invariants:
 *  - the connection is pinned to central (UsesCentralConnection; ENTI-1.9 guard list);
 *  - the disk and conversions disk must be central disks, i.e. NOT one of the disks
 *    FilesystemTenancyBootstrapper re-roots per tenant (tenancy.filesystem.disks).
 */
class CentralMedia extends Media
{
    use UsesCentralConnection;

    protected $table = 'media';

    protected static function booted(): void
    {
        // The package registers its observer on the configured media model only; Eloquent
        // events are keyed by the concrete class, so the subclass needs its own registration
        // (sort order on create, renames, file removal on delete).
        if (config('media-library.media_model') !== static::class) {
            /** @var class-string $observer */
            $observer = config('media-library.media_observer', MediaObserver::class);
            static::observe(app($observer));
        }

        static::saving(static function (CentralMedia $media): void {
            $tenantDisks = array_map('strval', (array) config('tenancy.filesystem.disks', []));

            foreach (array_filter([$media->disk, $media->conversions_disk]) as $disk) {
                if (in_array($disk, $tenantDisks, true)) {
                    // Developer error, never shown to end users.
                    throw new LogicException("CentralMedia cannot use the tenant-suffixed disk [{$disk}]; use central_private or central_public.");
                }
            }
        });
    }
}
