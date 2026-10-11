<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\Branding\TenantBrandingDTO;
use App\Enums\TenantLogoVariant;
use App\Models\TenantBrandProfile;
use App\Services\Branding\TenantBranding;
use App\Support\Media\SanitizedImage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * BRND-5: store a shop logo (light or dark) for the CURRENT tenant.
 *
 * The image arrives already sanitized (ImageSanitizer: magic-byte type, dimensions, GD
 * re-encode). It is written through the tenant's TenantBrandProfile, so the media row goes
 * to the tenant DB and the file to the tenant-suffixed disk under a server-generated name.
 * Nothing is ever written to public/.
 *
 * No file I/O inside a DB transaction (W2 batch 4 review, N1): the new media (row + file)
 * is added on its own, outside any transaction; if adding it fails, whatever it left
 * (row or file) is removed again. Only then is the previous media of the slot (row + file)
 * deleted, unless $removePrevious is false (UpdateSettingsAction removes it itself once its
 * settings are saved). A failure therefore never leaves the shop without its old logo, and
 * readers always serve the NEWEST media of the slot, so two concurrent uploads at worst
 * leave an older, never-served media row behind.
 */
final class UploadTenantBrandAssetAction
{
    public function __construct(
        private readonly TenantBranding $tenantBranding,
    ) {}

    public function execute(TenantLogoVariant $variant, SanitizedImage $image, bool $removePrevious = true): TenantBrandingDTO
    {
        $profile = TenantBrandProfile::query()->createOrFirst(['singleton_key' => TenantBrandProfile::SINGLETON_KEY]);

        /** @var list<Media> $previous */
        $previous = $profile->media()->where('collection_name', $variant->collection())->get()->all();
        $previousIds = array_map(static fn (Media $media): int => (int) $media->getKey(), $previous);

        try {
            $profile->addMediaFromString($image->binary)
                ->usingName($variant->collection())
                ->usingFileName($variant->collection().'.'.$image->extension)
                ->withCustomProperties([
                    'sha256' => $image->sha256,
                    'width' => $image->width,
                    'height' => $image->height,
                ])
                ->toMediaCollection($variant->collection());
        } catch (Throwable $e) {
            $this->removeMedia($profile->media()
                ->where('collection_name', $variant->collection())
                ->whereNotIn('id', $previousIds)
                ->get()
                ->all());

            throw $e;
        }

        $profile->touch();

        if ($removePrevious) {
            $this->removeMedia($previous);
        }

        return $this->tenantBranding->get();
    }

    /**
     * Delete media rows + files; a failure is reported, never thrown (newest wins).
     *
     * @param  list<Media>  $media
     */
    private function removeMedia(array $media): void
    {
        foreach ($media as $item) {
            try {
                $item->delete();
            } catch (Throwable $e) {
                report($e);
            }
        }
    }
}
