<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * BRND-5: the two shop (tenant) logo slots. Each one is a medialibrary collection of
 * App\Models\TenantBrandProfile on the tenant-suffixed disk, served by
 * GET /api/v1/branding/logo/{variant}.
 */
enum TenantLogoVariant: string
{
    case Light = 'light';
    case Dark = 'dark';

    /** medialibrary collection name on TenantBrandProfile. */
    public function collection(): string
    {
        return 'logo_'.$this->value;
    }

    /** Field of the legacy multipart settings form (POST /api/v1/settings). */
    public function legacySettingsField(): string
    {
        return 'logo_'.$this->value.'_file';
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $variant): string => $variant->value, self::cases());
    }
}
