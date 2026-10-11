<?php

declare(strict_types=1);

namespace App\DTOs\AppVersions;

use App\Enums\AppPlatform;

/**
 * Validated input of the public update check / download (CheckUpdateRequest).
 * versionCode is null when the client omitted it (download-apk: "latest").
 */
final readonly class CheckUpdateDTO
{
    public function __construct(
        public AppPlatform $platform,
        public ?int $versionCode,
        public ?string $versionName,
    ) {}

    /**
     * @param  array{platform?: string, version_code?: int|string, version_name?: string, current_version?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        $name = $data['current_version'] ?? $data['version_name'] ?? null;

        return new self(
            platform: AppPlatform::from((string) ($data['platform'] ?? AppPlatform::Android->value)),
            versionCode: isset($data['version_code']) ? (int) $data['version_code'] : null,
            versionName: is_string($name) && trim($name) !== '' ? trim($name) : null,
        );
    }

    /**
     * @return array{platform: string, version_code: int|null, version_name: string|null}
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform->value,
            'version_code' => $this->versionCode,
            'version_name' => $this->versionName,
        ];
    }
}
