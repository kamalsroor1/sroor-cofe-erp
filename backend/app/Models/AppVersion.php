<?php

namespace App\Models;

use App\Support\AppReleases\ReleaseFileLocator;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $platform
 * @property string $version_name
 * @property int $version_code
 * @property int $min_version_code
 * @property bool $is_force_update
 * @property string $release_notes_ar
 * @property string|null $release_notes_en
 * @property string|null $apk_path
 * @property string|null $apk_filename
 * @property int $apk_size_bytes
 * @property string|null $apk_checksum
 * @property string|null $binary_mime
 * @property bool $apk_signing_block
 * @property bool $authenticode_present
 * @property int $download_count
 * @property bool $is_active
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AppVersion extends Model
{
    use HasFactory;

    protected $table = 'app_versions';

    /**
     * Private central disk holding release binaries (config/filesystems.php). Not web-served,
     * not re-rooted by tenancy: the same file is read from every tenant host.
     */
    public const RELEASE_DISK = 'app_releases_private';

    /** Legacy read-only alias of the old public root, until app-releases:relocate ran. */
    public const LEGACY_RELEASE_DISK = 'app_releases';

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    protected $fillable = [
        'platform',
        'version_name',
        'version_code',
        'min_version_code',
        'is_force_update',
        'release_notes_ar',
        'release_notes_en',
        'apk_path',
        'apk_filename',
        'apk_size_bytes',
        'apk_checksum',
        'binary_mime',
        'apk_signing_block',
        'authenticode_present',
        'download_count',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'version_code' => 'integer',
        'min_version_code' => 'integer',
        'is_force_update' => 'boolean',
        'apk_size_bytes' => 'integer',
        'apk_signing_block' => 'boolean',
        'authenticode_present' => 'boolean',
        'download_count' => 'integer',
        'is_active' => 'boolean',
        'published_at' => 'datetime',
    ];

    /**
     * Scope for active releases
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for specific platform
     */
    public function scopeForPlatform(Builder $query, string $platform = 'android'): Builder
    {
        return $query->where('platform', $platform);
    }

    /**
     * Releases that may be offered to an installed app: active, with a stored binary, a
     * size and a SHA-256 checksum. Whether the file is still on disk is checked by
     * ReleaseFileLocator. A release without a checksum is never advertised (APP-3, W1 Q7).
     */
    public function scopeAdvertisable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('apk_path')
            ->whereNotNull('apk_checksum')
            ->where('apk_size_bytes', '>', 0);
    }

    /**
     * Formatted file size accessor (e.g. 18.5 MB)
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->apk_size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1).' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes.' B';
    }

    /** True when apk_checksum is a lowercase 64-hex SHA-256. */
    public function hasValidChecksum(): bool
    {
        return is_string($this->apk_checksum) && preg_match('/^[a-f0-9]{64}$/', $this->apk_checksum) === 1;
    }

    /**
     * Disk holding release binaries for writes and deletes (always the private disk).
     * Reads go through ReleaseFileLocator (private first, legacy public fallback).
     */
    public static function releaseDisk(): Filesystem
    {
        return ReleaseFileLocator::privateDisk();
    }

    /**
     * Version-specific download URL on the requesting host (a tenant host serves the
     * central release too).
     */
    public function getDownloadUrlAttribute(): string
    {
        return url('/api/v1/app/download-apk').'?'.http_build_query([
            'platform' => $this->platform,
            'version_code' => $this->version_code,
        ]);
    }
}
