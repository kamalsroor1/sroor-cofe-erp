<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\AppVersions\CheckUpdateDTO;
use App\Models\AppVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GET /api/v1/app/check-update response (top level, not wrapped in `data`: the installed
 * Android / desktop apps already parse this flat shape).
 *
 * Release fields (download_url, file_size*, checksum, release_notes*, published_at) are null
 * / empty when no advertisable release exists: a release without a checksum is never
 * advertised. Texts are translated (X-Locale), never literals.
 *
 * @property array{dto: CheckUpdateDTO, latest: AppVersion|null, rollback: AppVersion|null, has_update: bool, force_update: bool} $resource
 */
final class AppUpdateCheckResource extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dto = $this->resource['dto'];
        $latest = $this->resource['latest'];
        $hasUpdate = $this->resource['has_update'];
        $force = $this->resource['force_update'];
        $notes = $latest instanceof AppVersion ? $this->releaseNotes($latest) : [];

        return [
            'success' => true,
            'has_update' => $hasUpdate,
            'force_update' => $force,
            'current_app_version' => $dto->versionName,
            'current_version_code' => $dto->versionCode,
            'latest_version' => $latest instanceof AppVersion ? $latest->version_name : $dto->versionName,
            'latest_version_code' => $latest instanceof AppVersion ? $latest->version_code : $dto->versionCode,
            'min_supported_version_code' => $latest?->min_version_code,
            'download_url' => $latest?->download_url,
            'manual_download_url' => rtrim((string) config('app.url'), '/').'/download',
            'file_size' => $latest instanceof AppVersion ? self::formatSize($latest->apk_size_bytes) : null,
            'file_size_bytes' => $latest?->apk_size_bytes,
            'checksum' => $latest?->apk_checksum,
            'checksum_algorithm' => 'sha256',
            'release_notes' => $notes,
            'release_notes_ar' => $latest instanceof AppVersion ? $latest->release_notes_ar : '',
            'published_at' => $latest === null ? null : ($latest->published_at ?? $latest->created_at)?->toIso8601String(),
            'title' => __($hasUpdate
                ? ($force ? 'app_releases.check.title_force' : 'app_releases.check.title_optional')
                : 'app_releases.check.title_up_to_date'),
            'message' => $hasUpdate
                ? __($force ? 'app_releases.check.message_force' : 'app_releases.check.message_available', ['version' => $latest?->version_name])
                : __('app_releases.check.message_up_to_date'),
            'rollback' => $this->rollback(),
        ];
    }

    /** "18.4 MB" style, unit translated; integer/bcmath math only (no floats). */
    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1_048_576) {
            return (string) __('app_releases.size.megabytes', ['size' => bcdiv((string) $bytes, '1048576', 1)]);
        }

        if ($bytes >= 1_024) {
            return (string) __('app_releases.size.kilobytes', ['size' => bcdiv((string) $bytes, '1024', 1)]);
        }

        return (string) __('app_releases.size.bytes', ['size' => (string) $bytes]);
    }

    /**
     * Notes in the request locale (en falls back to ar when no English notes), one per line.
     *
     * @return list<string>
     */
    private function releaseNotes(AppVersion $release): array
    {
        $text = app()->getLocale() === 'en' && filled($release->release_notes_en)
            ? (string) $release->release_notes_en
            : (string) $release->release_notes_ar;

        $lines = array_map('trim', preg_split('/\R/u', $text) ?: []);

        return array_values(array_filter($lines, static fn (string $line): bool => $line !== ''));
    }

    /**
     * @return array{version: string, version_code: int, download_url: string, checksum: string|null, file_size_bytes: int}|null
     */
    private function rollback(): ?array
    {
        $rollback = $this->resource['rollback'];

        if (! $rollback instanceof AppVersion) {
            return null;
        }

        return [
            'version' => $rollback->version_name,
            'version_code' => $rollback->version_code,
            'download_url' => $rollback->download_url,
            'checksum' => $rollback->apk_checksum,
            'file_size_bytes' => $rollback->apk_size_bytes,
        ];
    }
}
