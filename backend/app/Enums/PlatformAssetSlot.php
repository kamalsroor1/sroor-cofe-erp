<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Media\BrandAssetSpec;
use Illuminate\Support\Facades\Storage;

/**
 * BRND-2: the platform brand images an operator can upload
 * (POST/DELETE /api/v1/super-admin/platform-settings/assets/{slot}).
 *
 * Each slot is stored as a path on the CENTRAL public disk (`central_public`, never a
 * tenant-suffixed disk) under `branding/<uuid>.<ext>`, in the matching
 * PlatformSettingKey row of `platform_settings`. The file name is always generated
 * server-side; the client name is never used.
 */
enum PlatformAssetSlot: string
{
    case LogoLight = 'logo_light';
    case LogoDark = 'logo_dark';
    case Favicon = 'favicon';
    case AppIcon = 'app_icon';

    /** Central disk holding uploaded platform assets (config/filesystems.php). */
    public const DISK = 'central_public';

    /** Directory on DISK for platform assets. */
    public const DIRECTORY = 'branding';

    /** A path this feature wrote: branding/<uuid>.<png|jpg|webp>. Anything else is never deleted. */
    private const MANAGED_PATH = '/^branding\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(png|jpg|webp)$/';

    public function settingKey(): PlatformSettingKey
    {
        return match ($this) {
            self::LogoLight => PlatformSettingKey::LogoLight,
            self::LogoDark => PlatformSettingKey::LogoDark,
            self::Favicon => PlatformSettingKey::Favicon,
            self::AppIcon => PlatformSettingKey::AppIcon,
        };
    }

    public function spec(): BrandAssetSpec
    {
        return match ($this) {
            self::LogoLight, self::LogoDark => BrandAssetSpec::logo(),
            self::Favicon => BrandAssetSpec::favicon(),
            self::AppIcon => BrandAssetSpec::appIcon(),
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $slot): string => $slot->value, self::cases());
    }

    /** True only for a file uploaded through BRND-2 (safe to delete from DISK). */
    public static function isManagedPath(?string $reference): bool
    {
        return $reference !== null && preg_match(self::MANAGED_PATH, $reference) === 1;
    }

    /**
     * Public URL of an asset reference: an uploaded file on DISK, or the config/branding.php
     * default (an absolute URL or a root-relative public path), else null.
     */
    public static function publicUrl(?string $reference): ?string
    {
        if ($reference === null || trim($reference) === '') {
            return null;
        }

        if (self::isManagedPath($reference)) {
            return Storage::disk(self::DISK)->url($reference);
        }

        if (str_starts_with($reference, '/') || preg_match('#^https?://#i', $reference) === 1) {
            return $reference;
        }

        return null;
    }
}
