<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * SELL-02 (central): a subscription reminder already sent to a tenant (and to the operator).
 * Written only by App\Actions\Tenants\SendLifecycleRemindersAction; see the migration for the
 * once-only rule (UNIQUE tenant_id + kind + target_date).
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $kind e.g. `trial.d3`, `subscription.today`, `trial.grace`
 * @property Carbon $target_date the calendar date (app timezone) the trial / paid period ends
 * @property int $recipients tenant users the in-app notification was written for
 * @property Carbon|null $tenant_notified_at
 * @property Carbon|null $operator_notified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class TenantLifecycleReminder extends Model
{
    use UsesCentralConnection;

    protected $fillable = [
        'tenant_id',
        'kind',
        'target_date',
        'recipients',
        'tenant_notified_at',
        'operator_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'recipients' => 'integer',
            'tenant_notified_at' => 'datetime',
            'operator_notified_at' => 'datetime',
        ];
    }
}
