<?php

namespace App\Actions\AppVersions;

use App\DTOs\AppVersions\StoreAppVersionDTO;
use App\Models\AppVersion;
use Illuminate\Support\Facades\DB;
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
                // W1 hardening note 5: nothing from the client's file (name or extension)
                // is used. The extension follows the validated platform; the file on disk
                // gets a unique server-generated name, so re-uploading a version never
                // overwrites (or, on delete, removes) another release's binary.
                $ext = match ($dto->platform) {
                    'windows' => 'exe',
                    'ios' => 'ipa',
                    default => 'apk',
                };
                $apkPath = $dto->apkFile->storeAs('apks/'.$dto->platform, Str::lower((string) Str::ulid()).'.'.$ext, 'public');

                // Download name only (Content-Disposition), built from validated fields.
                $appNameSlug = Str::slug(config('app.name', 'erp-pos')) ?: 'erp-pos';
                $prefix = $dto->platform === 'windows' ? $appNameSlug.'-Setup-v' : $appNameSlug.'-v';
                $apkFilename = $prefix.Str::slug($dto->versionName).'.'.$ext;
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
