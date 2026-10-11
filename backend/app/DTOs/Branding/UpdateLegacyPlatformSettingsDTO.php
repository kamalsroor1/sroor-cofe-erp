<?php

declare(strict_types=1);

namespace App\DTOs\Branding;

use App\Enums\PlatformSettingKey;

/**
 * The legacy super-admin "platform settings" form (POST /api/v1/super-admin/settings).
 * A null optional field means "leave unchanged" (the screen's behaviour before BRND-1);
 * the full branding editor replaces this in BRND-2.
 */
final class UpdateLegacyPlatformSettingsDTO
{
    public function __construct(
        public readonly string $platformName,
        public readonly ?string $platformSubtitle,
        public readonly ?string $supportEmail,
        public readonly ?string $supportPhone,
    ) {}

    /**
     * @param  array{platform_name: string, platform_subtitle?: ?string, support_email?: ?string, support_phone?: ?string}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            platformName: (string) $data['platform_name'],
            platformSubtitle: self::nullable($data['platform_subtitle'] ?? null),
            supportEmail: self::nullable($data['support_email'] ?? null),
            supportPhone: self::nullable($data['support_phone'] ?? null),
        );
    }

    /**
     * PlatformSettingKey value => new value, only for the fields that were sent.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            PlatformSettingKey::Name->value => $this->platformName,
            PlatformSettingKey::Subtitle->value => $this->platformSubtitle,
            PlatformSettingKey::SupportEmail->value => $this->supportEmail,
            PlatformSettingKey::SupportPhone->value => $this->supportPhone,
        ], static fn (?string $value): bool => $value !== null);
    }

    private static function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = (string) $value;

        return $value === '' ? null : $value;
    }
}
