<?php

namespace App\DTOs\AppVersions;

use Illuminate\Http\UploadedFile;

readonly class StoreAppVersionDTO
{
    public function __construct(
        public string $platform,
        public string $versionName,
        public int $versionCode,
        public int $minVersionCode,
        public bool $isForceUpdate,
        public string $releaseNotesAr,
        public ?string $releaseNotesEn,
        public ?UploadedFile $apkFile,
        public bool $isActive,
    ) {}

    public static function fromRequest(array $validated, ?UploadedFile $apkFile): self
    {
        return new self(
            platform: $validated['platform'] ?? 'android',
            versionName: (string) $validated['version_name'],
            versionCode: (int) $validated['version_code'],
            minVersionCode: (int) ($validated['min_version_code'] ?? $validated['version_code']),
            isForceUpdate: (bool) ($validated['is_force_update'] ?? false),
            releaseNotesAr: (string) $validated['release_notes_ar'],
            releaseNotesEn: isset($validated['release_notes_en']) ? (string) $validated['release_notes_en'] : null,
            apkFile: $apkFile,
            isActive: (bool) ($validated['is_active'] ?? true),
        );
    }
}
