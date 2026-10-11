<?php

declare(strict_types=1);

namespace App\DTOs\AppVersions;

use App\Enums\AppPlatform;
use App\Support\AppReleases\ReleaseBinaryReport;
use Illuminate\Http\UploadedFile;

/**
 * A validated release upload (StoreAppVersionRequest), including the inspector's report on
 * the binary. Nothing from the client's file name or extension is carried.
 */
final readonly class StoreAppVersionDTO
{
    public function __construct(
        public AppPlatform $platform,
        public string $versionName,
        public int $versionCode,
        public int $minVersionCode,
        public bool $isForceUpdate,
        public string $releaseNotesAr,
        public ?string $releaseNotesEn,
        public UploadedFile $apkFile,
        public ReleaseBinaryReport $binaryReport,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $validated  StoreAppVersionRequest::validated()
     */
    public static function fromArray(array $validated, UploadedFile $apkFile, ReleaseBinaryReport $binaryReport): self
    {
        $notesEn = isset($validated['release_notes_en']) ? trim((string) $validated['release_notes_en']) : '';

        return new self(
            platform: AppPlatform::from((string) $validated['platform']),
            versionName: (string) $validated['version_name'],
            versionCode: (int) $validated['version_code'],
            // Omitted = 1 (the column default): no installed build is forced by the minimum.
            // Forcing is explicit (is_force_update, or a min_version_code sent on purpose).
            minVersionCode: (int) ($validated['min_version_code'] ?? 1),
            isForceUpdate: (bool) ($validated['is_force_update'] ?? false),
            releaseNotesAr: (string) $validated['release_notes_ar'],
            releaseNotesEn: $notesEn === '' ? null : $notesEn,
            apkFile: $apkFile,
            binaryReport: $binaryReport,
            isActive: (bool) ($validated['is_active'] ?? true),
        );
    }

    /**
     * Release columns from the validated fields (the binary columns are added by the Action).
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'platform' => $this->platform->value,
            'version_name' => $this->versionName,
            'version_code' => $this->versionCode,
            'min_version_code' => $this->minVersionCode,
            'is_force_update' => $this->isForceUpdate,
            'release_notes_ar' => $this->releaseNotesAr,
            'release_notes_en' => $this->releaseNotesEn,
            'binary_mime' => $this->binaryReport->mime,
            'apk_signing_block' => $this->binaryReport->apkSigningBlock,
            'authenticode_present' => $this->binaryReport->authenticodePresent,
            'is_active' => $this->isActive,
        ];
    }
}
