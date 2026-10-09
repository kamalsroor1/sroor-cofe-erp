<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Models\Concerns\UsesCentralConnection;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One tenant status change, CENTRAL `tenant_lifecycle_events` (IDEN-3.2).
 *
 * Append-only history written by App\Actions\Tenants\TransitionTenantStatusAction in the
 * same central transaction as the `tenants` update. `tenant_id` has no foreign key on
 * purpose: the history outlives the tenant row. Rows are never updated.
 *
 * @property int $id
 * @property string $tenant_id
 * @property TenantStatus|null $from_status null for the first status of a new tenant
 * @property TenantStatus $to_status
 * @property TenantLifecycleActor $actor
 * @property int|null $central_user_id the operator, for SuperAdmin moves
 * @property TenantSuspensionReason|null $reason
 * @property string|null $note
 * @property array<string, mixed>|null $properties
 * @property Carbon|null $created_at
 * @property-read Tenant|null $tenant
 * @property-read CentralUser|null $centralUser
 */
class TenantLifecycleEvent extends Model
{
    use UsesCentralConnection;

    public const NOTE_MAX = 500;

    /** Append-only: there is no updated_at column. */
    public const UPDATED_AT = null;

    protected $table = 'tenant_lifecycle_events';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'from_status',
        'to_status',
        'actor',
        'central_user_id',
        'reason',
        'note',
        'properties',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => TenantStatus::class,
            'to_status' => TenantStatus::class,
            'actor' => TenantLifecycleActor::class,
            'reason' => TenantSuspensionReason::class,
            'central_user_id' => 'integer',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Tenant lifecycle events are append-only.');
        });
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<CentralUser, $this>
     */
    public function centralUser(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class);
    }
}
