<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantLogoVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * BRND-5: the shop's brand profile (TENANT database, one row per tenant).
 *
 * Owns the logo_light / logo_dark media through the DEFAULT media model, so the rows go
 * to the tenant `media` table and the files to the tenant-suffixed disk
 * (config media-library.disk_name, re-rooted per tenant by FilesystemTenancyBootstrapper);
 * file names are generated server-side (ServerGeneratedFileNamer). Never use
 * InteractsWithCentralMedia here: a shop logo must never land in the central DB or disk.
 *
 * Each collection holds one file. The replacement is done by the upload Action, which
 * deletes the previous media only AFTER the new one is stored (the package's singleFile()
 * would delete the old file first), with no file I/O inside a DB transaction.
 * Readers always take the newest media of the collection.
 *
 * No SoftDeletes: a configuration singleton, not business data (nothing to recover in
 * the recycle bin; removing a logo is an explicit action).
 *
 * @property int $id
 * @property string $singleton_key
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantBrandProfile extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const SINGLETON_KEY = 'default';

    /** Raster types the sanitizer can produce for a logo (BrandAssetSpec::logo()). */
    public const LOGO_MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    protected $table = 'tenant_brand_profiles';

    protected $fillable = ['singleton_key'];

    public function registerMediaCollections(): void
    {
        foreach (TenantLogoVariant::cases() as $variant) {
            $this->addMediaCollection($variant->collection())
                ->acceptsMimeTypes(self::LOGO_MIMES);
        }
    }
}
