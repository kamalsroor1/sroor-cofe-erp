<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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

    /** Disk the super-admin uploads release binaries to (always from central context). */
    public const RELEASE_DISK = 'public';

    /** Central alias of RELEASE_DISK's root that tenancy never re-roots (config/filesystems.php). */
    public const CENTRAL_RELEASE_DISK = 'app_releases';

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
        'download_count',
        'is_active',
        'published_at',
    ];

    protected $casts = [
        'version_code' => 'integer',
        'min_version_code' => 'integer',
        'is_force_update' => 'boolean',
        'apk_size_bytes' => 'integer',
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

    /**
     * Disk to read release binaries from. Releases are central (one row and one file
     * for every tenant), but on a tenant host stancl's FilesystemTenancyBootstrapper
     * re-roots 'public' to storage/tenant<id>/app/public, so the binary is read through
     * the central alias there.
     */
    public static function releaseDisk(): Filesystem
    {
        $tenancyInitialized = function_exists('tenancy') && tenancy()->initialized;

        return Storage::disk($tenancyInitialized ? self::CENTRAL_RELEASE_DISK : self::RELEASE_DISK);
    }

    /**
     * Full download URL
     */
    public function getDownloadUrlAttribute(): string
    {
        return url('/api/v1/app/download-latest-apk?platform='.$this->platform);
    }
}
