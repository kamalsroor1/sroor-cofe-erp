<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MOB-03: the platform an installed app reports to GET /api/v1/app/check-update.
 *
 * It differs from AppPlatform (what a release binary is published for) by `android_play`: the
 * Google Play build. Play forbids self-updating with a downloaded APK, so that build is never
 * offered a binary: its updates come from the Play Store. `android` stays the directly
 * distributed build (self-update through the AppUpdater plugin).
 */
enum UpdateClientPlatform: string
{
    case Android = 'android';
    case AndroidPlay = 'android_play';
    case Windows = 'windows';

    /** The release platform whose binaries this client could install. */
    public function releasePlatform(): AppPlatform
    {
        return match ($this) {
            self::Android, self::AndroidPlay => AppPlatform::Android,
            self::Windows => AppPlatform::Windows,
        };
    }

    /** False when the store owns updates: no binary, download URL or checksum is ever offered. */
    public function offersBinary(): bool
    {
        return $this !== self::AndroidPlay;
    }
}
