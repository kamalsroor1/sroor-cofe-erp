<?php

namespace App\Actions\AppVersions;

use App\DTOs\AppVersions\CheckUpdateDTO;
use App\Models\AppVersion;

class CheckAppUpdateAction
{
    public function execute(CheckUpdateDTO $dto): array
    {
        $latest = AppVersion::forPlatform($dto->platform)
            ->active()
            ->orderByDesc('version_code')
            ->first();

        if (!$latest) {
            return [
                'has_update' => false,
                'is_force_update' => false,
                'current_version_code' => $dto->versionCode,
                'message' => 'التطبيق محدث إلى آخر إصدار متاح.',
            ];
        }

        $hasUpdate = $latest->version_code > $dto->versionCode;
        $isForceUpdate = $hasUpdate && ($latest->is_force_update || $dto->versionCode < $latest->min_version_code);

        return [
            'has_update' => $hasUpdate,
            'is_force_update' => $isForceUpdate,
            'current_version_code' => $dto->versionCode,
            'current_version_name' => $dto->versionName,
            'latest_version' => $hasUpdate ? [
                'id' => $latest->id,
                'version_name' => $latest->version_name,
                'version_code' => $latest->version_code,
                'release_notes_ar' => $latest->release_notes_ar,
                'release_notes_en' => $latest->release_notes_en,
                'file_size' => $latest->formatted_size,
                'file_size_bytes' => $latest->apk_size_bytes,
                'checksum' => $latest->apk_checksum,
                'download_url' => $latest->download_url,
                'published_at' => $latest->published_at ? $latest->published_at->toDateTimeString() : $latest->created_at->toDateTimeString(),
            ] : null,
        ];
    }
}
