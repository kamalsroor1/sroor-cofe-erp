<?php

namespace App\DTOs\AppVersions;

readonly class CheckUpdateDTO
{
    public function __construct(
        public string $platform,
        public int $versionCode,
        public string $versionName,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            platform: $validated['platform'] ?? 'android',
            versionCode: (int) ($validated['version_code'] ?? 1),
            versionName: (string) ($validated['version_name'] ?? '1.0.0'),
        );
    }
}
