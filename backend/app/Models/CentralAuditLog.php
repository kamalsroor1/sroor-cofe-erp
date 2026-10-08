<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CentralAuditEvent;
use App\Exceptions\CentralAuditLogImmutableException;
use App\Models\Builders\CentralAuditLogBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;

/**
 * One append-only entry of the CENTRAL platform-operator audit log (IDEN-1.5).
 *
 * Lives in `central_audit_logs` on the central connection, even while a tenant is
 * initialized. Rows are written only through App\Services\CentralAuditLogger (which
 * redacts secrets) and can never be updated or deleted through Eloquent.
 *
 * TODO(CTO): the plan asks for this model on top of spatie/laravel-activitylog. The
 * package is not installed yet (PKG-1 owns composer.json), so this is the documented
 * fallback: a standalone model whose columns mirror activitylog's, so it can later
 * extend Spatie\Activitylog\Models\Activity without a data migration.
 *
 * @property int $id
 * @property string $event
 * @property string|null $description
 * @property string|null $causer_type
 * @property int|null $causer_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string|null $tenant_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property-read Model|null $causer
 * @property-read Model|null $subject
 *
 * @method static CentralAuditLogBuilder query()
 */
class CentralAuditLog extends Model
{
    protected $table = 'central_audit_logs';

    /** Append-only: there is no updated_at column. */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event',
        'description',
        'causer_type',
        'causer_id',
        'subject_type',
        'subject_id',
        'tenant_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'causer_id' => 'integer',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Second line of defence next to CentralAuditLogBuilder.
        static::updating(static function (): never {
            throw new CentralAuditLogImmutableException;
        });

        static::deleting(static function (): never {
            throw new CentralAuditLogImmutableException;
        });
    }

    /**
     * The audit log ALWAYS lives in the central database, even while a tenant is initialized.
     */
    public function getConnectionName(): ?string
    {
        return config('tenancy.database.central_connection', config('database.default'));
    }

    /**
     * @param  QueryBuilder  $query
     */
    public function newEloquentBuilder($query): CentralAuditLogBuilder
    {
        return new CentralAuditLogBuilder($query);
    }

    /**
     * The operator (CentralUser) who performed the action, if any.
     *
     * @return MorphTo<Model, $this>
     */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** The typed event, or null for a value no longer defined in the enum. */
    public function eventEnum(): ?CentralAuditEvent
    {
        return CentralAuditEvent::tryFrom($this->event);
    }
}
