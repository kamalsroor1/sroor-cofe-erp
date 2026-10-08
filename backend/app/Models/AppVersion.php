<?php

namespace App\Models;

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
     * Full download URL
     */
    public function getDownloadUrlAttribute(): string
    {
        return url('/api/v1/app/download-latest-apk?platform='.$this->platform);
    }
}
