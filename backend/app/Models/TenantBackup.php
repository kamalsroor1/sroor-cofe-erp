<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Central DB: one uploaded backup archive (`tenant_backups`, OPS-5).
 *
 * Written only by `php artisan backup:tenants` (App\Console\Commands\BackupTenantsCommand),
 * read by `backup:restore-tenant`, the BackupFreshnessCheck health check (OPS-7) and the
 * tenant purge guard (OPS-9). `tenant_id` NULL means the central database.
 *
 * @property int $id
 * @property string|null $tenant_id null = central DB
 * @property string $disk filesystem disk name (config/filesystems.php)
 * @property string $path path of the archive on that disk
 * @property string $sha256 hex sha256 of the encrypted archive
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $verified_at the uploaded copy was read back and its sha256 matched
 */
class TenantBackup extends Model
{
    use UsesCentralConnection;

    /** Subject name used for the central database in paths, options and output. */
    public const CENTRAL = 'central';

    public const UPDATED_AT = null;

    protected $table = 'tenant_backups';

    protected $fillable = [
        'tenant_id',
        'disk',
        'path',
        'sha256',
        'size_bytes',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Rows of one subject: a tenant id, or TenantBackup::CENTRAL / null for the central DB.
     *
     * @param  Builder<TenantBackup>  $query
     * @return Builder<TenantBackup>
     */
    public function scopeForSubject(Builder $query, ?string $subject): Builder
    {
        if ($subject === null || $subject === self::CENTRAL) {
            return $query->whereNull('tenant_id');
        }

        return $query->where('tenant_id', $subject);
    }

    /**
     * @param  Builder<TenantBackup>  $query
     * @return Builder<TenantBackup>
     */
    public function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    public function subject(): string
    {
        return $this->tenant_id ?? self::CENTRAL;
    }
}
