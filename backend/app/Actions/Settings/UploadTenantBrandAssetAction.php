<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\Branding\TenantBrandingDTO;
use App\Enums\TenantLogoVariant;
use App\Models\TenantBrandProfile;
use App\Services\Branding\TenantBranding;
use App\Services\Entitlements\PlanEnforcer;
use App\Services\Entitlements\TenantStorageUsage;
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
 *
 * PLAN-ENF: the write is refused (403 subscription.limit_reached, limit storage_mb) before
 * anything is stored when the tenant's storage after it would pass the plan limit.
 */
final class UploadTenantBrandAssetAction
{
    public function __construct(
        private readonly TenantBranding $tenantBranding,
        private readonly PlanEnforcer $planEnforcer,
        private readonly TenantStorageUsage $storageUsage,
    ) {}

    public function execute(TenantLogoVariant $variant, SanitizedImage $image, bool $removePrevious = true): TenantBrandingDTO
    {
        $profile = TenantBrandProfile::query()->createOrFirst(['singleton_key' => TenantBrandProfile::SINGLETON_KEY]);

        // PLAN-ENF: the slot's current media are read under the storage lock (in the delta
        // closure, run exactly once before the write), so two concurrent uploads to the same slot
        // never both pass with a stale delta. The upload replaces them: only the net growth counts.
        /** @var list<Media> $previous */
        $previous = [];
        $previousIds = [];
        $prepared = false;

        try {
            $this->planEnforcer->withinStorageLimit(
                function () use ($profile, $variant, $image, &$previous, &$previousIds, &$prepared): int {
                    $previous = $profile->media()->where('collection_name', $variant->collection())->get()->all();
                    $previousIds = array_map(static fn (Media $media): int => (int) $media->getKey(), $previous);
                    $prepared = true;

                    return strlen($image->binary) - array_sum(array_map(static fn (Media $media): int => (int) $media->size, $previous));
                },
                fn (): int => $this->storageUsage->usedBytes(),
                fn () => $profile->addMediaFromString($image->binary)
                    ->usingName($variant->collection())
                    ->usingFileName($variant->collection().'.'.$image->extension)
                    ->withCustomProperties([
                        'sha256' => $image->sha256,
                        'width' => $image->width,
                        'height' => $image->height,
                    ])
                    ->toMediaCollection($variant->collection()),
            );
        } catch (Throwable $e) {
            // Before the slot was read (lock timeout) nothing new can exist and $previousIds is
            // empty: cleaning up then would delete the current logo.
            if ($prepared) {
                $this->removeMedia($profile->media()
                    ->where('collection_name', $variant->collection())
                    ->whereNotIn('id', $previousIds)
                    ->get()
                    ->all());
            }

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
