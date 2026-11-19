<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountDeletionRequestStatus;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Central DB: a public "delete my account and data" request (MOB-05).
 *
 * @property int $id
 * @property string $contact_name
 * @property string $phone
 * @property string|null $email
 * @property string|null $shop_code
 * @property string|null $reason
 * @property string|null $locale
 * @property AccountDeletionRequestStatus $status
 * @property int|null $handled_by
 * @property Carbon|null $handled_at
 * @property string|null $handled_note
 * @property string|null $ip
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CentralUser|null $handler
 */
class AccountDeletionRequest extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'contact_name',
        'phone',
        'email',
        'shop_code',
        'reason',
        'locale',
        'status',
        'handled_by',
        'handled_at',
        'handled_note',
        'ip',
        'user_agent',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccountDeletionRequestStatus::class,
            'handled_by' => 'integer',
            'handled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CentralUser, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'handled_by');
    }

    public function isPending(): bool
    {
        return $this->status === AccountDeletionRequestStatus::Pending;
    }
}
