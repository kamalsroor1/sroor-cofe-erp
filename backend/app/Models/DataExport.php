<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DataExportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PRD-17 (tenant DB): one "Export my data" request and its ZIP on the tenant's private disk.
 *
 * @property int $id
 * @property int|null $user_id
 * @property DataExportStatus $status
 * @property string|null $file_path
 * @property int|null $file_size
 * @property int|null $tables_count
 * @property int|null $rows_count
 * @property string|null $error
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class DataExport extends Model
{
    protected $fillable = [
        'user_id',
        'status',
        'file_path',
        'file_size',
        'tables_count',
        'rows_count',
        'error',
        'started_at',
        'completed_at',
        'expires_at',
    ];

    /** The storage path is internal: never serialised. */
    protected $hidden = ['file_path'];

    protected function casts(): array
    {
        return [
            'status' => DataExportStatus::class,
            'file_size' => 'integer',
            'tables_count' => 'integer',
            'rows_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === DataExportStatus::Ready
            && $this->file_path !== null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }

    public function isStale(): bool
    {
        $minutes = max(5, (int) config('data_export.stale_minutes', 60));

        return $this->updated_at !== null && $this->updated_at->lt(now()->subMinutes($minutes));
    }
}
