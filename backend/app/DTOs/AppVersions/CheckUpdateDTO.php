<?php

declare(strict_types=1);

namespace App\DTOs\AppVersions;

use App\Enums\AppPlatform;
use App\Enums\UpdateClientPlatform;

/**
 * Validated input of the public update check / download (CheckUpdateRequest).
 * versionCode is null when the client omitted it (download-apk: "latest").
 *
 * MOB-03: `platform` is the RELEASE platform (android_play reads Android releases); `client`
 * keeps what the app reported, so the Play build is never offered a binary.
 */
final readonly class CheckUpdateDTO
{
    public function __construct(
        public AppPlatform $platform,
        public ?int $versionCode,
        public ?string $versionName,
        public UpdateClientPlatform $client = UpdateClientPlatform::Android,
    ) {}

    /** False for the Play build: the store owns its updates (no APK, URL or checksum offered). */
    public function offersBinary(): bool
    {
        return $this->client->offersBinary();
    }

    /**
     * @param  array{platform?: string, version_code?: int|string, version_name?: string, current_version?: string}  $data
     */
    public static function fromArray(array $data): self
    {
        $name = $data['current_version'] ?? $data['version_name'] ?? null;
        $client = UpdateClientPlatform::from((string) ($data['platform'] ?? UpdateClientPlatform::Android->value));

        return new self(
            platform: $client->releasePlatform(),
            versionCode: isset($data['version_code']) ? (int) $data['version_code'] : null,
            versionName: is_string($name) && trim($name) !== '' ? trim($name) : null,
            client: $client,
        );
    }

    /**
     * @return array{platform: string, client_platform: string, version_code: int|null, version_name: string|null}
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform->value,
            'client_platform' => $this->client->value,
            'version_code' => $this->versionCode,
            'version_name' => $this->versionName,
        ];
    }
}
