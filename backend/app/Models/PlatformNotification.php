<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformNotificationType;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Central DB: one super-admin panel notification item (the bell, #232 spec E / UI §4.2), shared by
 * the whole operator team; read marks are per operator (PlatformNotificationRead).
 *
 * `data` holds the facts the item renders with (tenant name, amount, method, access kind, window
 * end); the text itself is built in the reader's locale by PlatformNotificationResource. Ids are
 * plain columns (no FKs): the history outlives tenants, invoices and payments.
 *
 * @property int $id
 * @property PlatformNotificationType $type
 * @property string|null $tenant_id
 * @property int|null $billing_invoice_id
 * @property int|null $billing_payment_id
 * @property array<string, mixed>|null $data
 * @property string|null $dedupe_key unique: a retried writer never duplicates an item
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, PlatformNotificationRead> $reads
 */
class PlatformNotification extends Model
{
    use UsesCentralConnection;

    protected $table = 'platform_notifications';

    protected $fillable = [
        'type',
        'tenant_id',
        'billing_invoice_id',
        'billing_payment_id',
        'data',
        'dedupe_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PlatformNotificationType::class,
            'billing_invoice_id' => 'integer',
            'billing_payment_id' => 'integer',
            'data' => 'array',
        ];
    }

    /**
     * @return HasMany<PlatformNotificationRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(PlatformNotificationRead::class);
    }
}
