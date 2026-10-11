<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\Enums\PlatformAssetSlot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BRND-2: the effective platform settings for the super-admin console
 * (stored value, else the config/branding.php default).
 *
 * Assets are exposed as public URLs plus `custom` (true = an uploaded file on the central
 * public disk, false = the config default or nothing). Disk paths are never returned.
 *
 * @property PlatformBrandingDTO $resource
 */
final class PlatformSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branding = $this->resource;

        return [
            'name' => $branding->name,
            'short_name' => $branding->shortName,
            'subtitle' => $branding->subtitle,
            'legal_name' => $branding->legalName,
            'primary_color' => $branding->primaryColor,
            'support_email' => $branding->supportEmail,
            'support_phone' => $branding->supportPhone,
            'website_url' => $branding->websiteUrl,
            'powered_by_enabled' => $branding->poweredByEnabled,
            'assets' => [
                PlatformAssetSlot::LogoLight->value => $this->asset($branding->logoLight),
                PlatformAssetSlot::LogoDark->value => $this->asset($branding->logoDark),
                PlatformAssetSlot::Favicon->value => $this->asset($branding->favicon),
                PlatformAssetSlot::AppIcon->value => $this->asset($branding->appIcon),
            ],
        ];
    }

    /**
     * @return array{url: string|null, custom: bool}
     */
    private function asset(?string $reference): array
    {
        return [
            'url' => PlatformAssetSlot::publicUrl($reference),
            'custom' => PlatformAssetSlot::isManagedPath($reference),
        ];
    }
}
