<?php

namespace App\Actions\AppVersions;

use App\DTOs\AppVersions\StoreAppVersionDTO;
use App\Models\AppVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CreateAppVersionAction
{
    public function execute(StoreAppVersionDTO $dto): AppVersion
    {
        return DB::transaction(function () use ($dto) {
            $apkPath = null;
            $apkFilename = null;
            $apkSizeBytes = 0;
            $apkChecksum = null;

            if ($dto->apkFile) {
                $apkFilename = 'sroor-coffee-erp-v' . Str::slug($dto->versionName) . '.apk';
                $apkPath = $dto->apkFile->storeAs('apks/' . $dto->platform, $apkFilename, 'public');
                $apkSizeBytes = $dto->apkFile->getSize();
                $apkChecksum = hash_file('sha256', $dto->apkFile->getRealPath());
            }

            return AppVersion::create([
                'platform' => $dto->platform,
                'version_name' => $dto->versionName,
                'version_code' => $dto->versionCode,
                'min_version_code' => $dto->minVersionCode,
                'is_force_update' => $dto->isForceUpdate,
                'release_notes_ar' => $dto->releaseNotesAr,
                'release_notes_en' => $dto->releaseNotesEn,
                'apk_path' => $apkPath,
                'apk_filename' => $apkFilename,
                'apk_size_bytes' => $apkSizeBytes,
                'apk_checksum' => $apkChecksum,
                'download_count' => 0,
                'is_active' => $dto->isActive,
                'published_at' => now(),
            ]);
        });
    }
}
