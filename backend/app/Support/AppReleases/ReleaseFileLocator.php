<?php

declare(strict_types=1);

namespace App\Support\AppReleases;

use App\Models\AppVersion;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

/**
 * Where a release binary physically is.
 *
 * Since W2 batch 5 every binary is written to the PRIVATE central disk
 * AppVersion::RELEASE_DISK (storage/app/private/releases, never web-served, never re-rooted by
 * tenancy). Releases uploaded before that sit on the old public root under the same relative
 * path (`apks/<platform>/<file>`); until `php artisan app-releases:relocate` has moved them
 * (a deploy step), they are still READ from the legacy alias AppVersion::LEGACY_RELEASE_DISK.
 * Nothing is ever written to the legacy disk.
 */
final class ReleaseFileLocator
{
    /** A relative path this feature manages: apks/[<platform>/]<name>.<ext>, no traversal. */
    private const MANAGED_PATH = '#^apks/([a-z]+/)?[A-Za-z0-9._-]+$#';

    public static function isManagedPath(?string $path): bool
    {
        return is_string($path)
            && preg_match(self::MANAGED_PATH, $path) === 1
            && ! str_contains($path, '..');
    }

    public static function privateDisk(): Filesystem
    {
        return Storage::disk(AppVersion::RELEASE_DISK);
    }

    public static function legacyDisk(): Filesystem
    {
        return Storage::disk(AppVersion::LEGACY_RELEASE_DISK);
    }

    /** The disk holding $version's binary (private first, legacy fallback), or null when missing. */
    public static function diskFor(AppVersion $version): ?Filesystem
    {
        $path = $version->apk_path;
        if (! self::isManagedPath($path)) {
            return null;
        }

        foreach ([self::privateDisk(), self::legacyDisk()] as $disk) {
            if ($disk->exists((string) $path)) {
                return $disk;
            }
        }

        return null;
    }

    /** Absolute local path of $version's binary, or null when it is not on any release disk. */
    public static function absolutePath(AppVersion $version): ?string
    {
        $disk = self::diskFor($version);

        return $disk === null ? null : $disk->path((string) $version->apk_path);
    }

    /** Delete $version's binary from every release disk (private + legacy). */
    public static function delete(AppVersion $version): void
    {
        $path = $version->apk_path;
        if (! self::isManagedPath($path)) {
            return;
        }

        foreach ([self::privateDisk(), self::legacyDisk()] as $disk) {
            if ($disk->exists((string) $path)) {
                $disk->delete((string) $path);
            }
        }
    }
}
