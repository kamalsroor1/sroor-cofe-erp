<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\CentralMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * medialibrary for CENTRAL models (PKG-2 rule 5).
 *
 * Any model whose files are uploaded from a tenant request into the platform (billing
 * payments / receipts, platform assets) must use this trait instead of
 * InteractsWithMedia, together with UsesCentralConnection, and register its collections
 * on a central disk (`central_private` or `central_public`). The media relation and the
 * FileAdder then build CentralMedia rows, which live in the central `media` table.
 */
trait InteractsWithCentralMedia
{
    use InteractsWithMedia;

    public function getMediaModel(): string
    {
        return CentralMedia::class;
    }
}
