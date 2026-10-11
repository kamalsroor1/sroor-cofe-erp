<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\DTOs\Branding\TenantBrandingDTO;
use App\Enums\TenantLogoVariant;
use App\Models\TenantBrandProfile;
use App\Services\Branding\TenantBranding;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * BRND-5: remove a shop logo (light or dark) of the CURRENT tenant. Idempotent: removing
 * a logo that does not exist succeeds. The media rows are collected under the profile
 * lock and removed (row + file on the tenant disk) after the commit.
 */
final class DeleteTenantBrandAssetAction
{
    public function __construct(
        private readonly TenantBranding $tenantBranding,
    ) {}

    public function execute(TenantLogoVariant $variant): TenantBrandingDTO
    {
        /** @var list<Media> $media */
        $media = DB::transaction(function () use ($variant): array {
            $profile = TenantBrandProfile::query()
                ->where('singleton_key', TenantBrandProfile::SINGLETON_KEY)
                ->lockForUpdate()
                ->first();

            if ($profile === null) {
                return [];
            }

            $items = $profile->media()->where('collection_name', $variant->collection())->get()->all();
            $profile->touch();

            return $items;
        });

        foreach ($media as $item) {
            try {
                $item->delete();
            } catch (Throwable $e) {
                // The change is committed; a leftover row/file is never served (newest wins).
                report($e);
            }
        }

        return $this->tenantBranding->get();
    }
}
