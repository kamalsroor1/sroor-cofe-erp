<?php

declare(strict_types=1);

namespace App\Actions\AppVersions;

use App\DTOs\AppVersions\StoreAppVersionDTO;
use App\Models\AppVersion;
use App\Support\AppReleases\ReleaseFileLocator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Publish a release (central app_versions + binary on the PRIVATE release disk).
 *
 * Order: checksum and size are taken from the validated upload, the file is stored under a
 * server-generated name (W1 hardening note 5: nothing from the client's name/extension),
 * THEN the row is written in a transaction that re-checks "newer than every release of the
 * platform" under a lock (the request check can race a parallel upload; the
 * unique(platform, version_code) index is the last line). Any failure after the file was
 * stored deletes it, so no orphan binary is ever left behind.
 */
final class CreateAppVersionAction
{
    public function execute(StoreAppVersionDTO $dto): AppVersion
    {
        $file = $dto->apkFile;
        $realPath = (string) $file->getRealPath();
        $checksum = hash_file('sha256', $realPath);
        $size = filesize($realPath);

        if (! is_string($checksum) || $size === false || $size <= 0) {
            throw ValidationException::withMessages(['apk_file' => [__('app_releases.store_failed')]]);
        }

        $extension = $dto->platform->extension();
        $path = $file->storeAs(
            'apks/'.$dto->platform->value,
            Str::lower((string) Str::ulid()).'.'.$extension,
            AppVersion::RELEASE_DISK,
        );

        if (! is_string($path) || ! ReleaseFileLocator::isManagedPath($path)) {
            throw new RuntimeException((string) __('app_releases.binary_write_failed'));
        }

        try {
            return DB::connection((new AppVersion)->getConnectionName())->transaction(
                fn (): AppVersion => $this->createRow($dto, $path, (int) $size, $checksum),
            );
        } catch (Throwable $e) {
            AppVersion::releaseDisk()->delete($path);

            if ($e instanceof UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['version_code' => [__('app_releases.version_code_taken', [
                    'platform' => __('app_releases.platforms.'.$dto->platform->value),
                ])]]);
            }

            throw $e;
        }
    }

    private function createRow(StoreAppVersionDTO $dto, string $path, int $size, string $checksum): AppVersion
    {
        // Serialises concurrent uploads of one platform (rows of that platform are locked).
        $max = AppVersion::query()
            ->forPlatform($dto->platform->value)
            ->lockForUpdate()
            ->pluck('version_code')
            ->max();

        if ($max !== null && $dto->versionCode <= (int) $max) {
            throw ValidationException::withMessages(['version_code' => [__('app_releases.version_not_newer', [
                'platform' => __('app_releases.platforms.'.$dto->platform->value),
                'max' => (int) $max,
            ])]]);
        }

        // Download name only (Content-Disposition), built from validated fields.
        $appNameSlug = Str::slug((string) config('app.name', 'erp-pos')) ?: 'erp-pos';
        $prefix = $dto->platform->value === 'windows' ? $appNameSlug.'-Setup-v' : $appNameSlug.'-v';

        return AppVersion::query()->create([
            ...$dto->toArray(),
            'apk_path' => $path,
            'apk_filename' => $prefix.Str::slug($dto->versionName).'.'.$dto->platform->extension(),
            'apk_size_bytes' => $size,
            'apk_checksum' => $checksum,
            'download_count' => 0,
            'published_at' => now(),
        ]);
    }
}
