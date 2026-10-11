<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * APP-3 / APP-7: platforms a release binary can be published for (central app_versions).
 *
 * iOS is deliberately absent (CTO decision Q7, W2 batch 5): there is no iOS build, so an
 * `ios` upload or update check is a 422, never a silently stored .ipa.
 */
enum AppPlatform: string
{
    case Android = 'android';
    case Windows = 'windows';

    /** Extension of the stored binary. Follows the platform, never the client's file name. */
    public function extension(): string
    {
        return match ($this) {
            self::Android => 'apk',
            self::Windows => 'exe',
        };
    }

    /** Content-Type of the download response. */
    public function contentType(): string
    {
        return match ($this) {
            self::Android => 'application/vnd.android.package-archive',
            self::Windows => 'application/vnd.microsoft.portable-executable',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $platform): string => $platform->value, self::cases());
    }
}
