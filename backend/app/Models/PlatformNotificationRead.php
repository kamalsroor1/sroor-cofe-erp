<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Central DB: an operator marked a panel notification as read (unique notification + operator).
 *
 * @property int $id
 * @property int $platform_notification_id
 * @property int $central_user_id
 * @property Carbon $read_at
 * @property-read PlatformNotification $notification
 */
class PlatformNotificationRead extends Model
{
    use UsesCentralConnection;

    public $timestamps = false;

    protected $table = 'platform_notification_reads';

    protected $fillable = [
        'platform_notification_id',
        'central_user_id',
        'read_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform_notification_id' => 'integer',
            'central_user_id' => 'integer',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PlatformNotification, $this>
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(PlatformNotification::class, 'platform_notification_id');
    }
}
