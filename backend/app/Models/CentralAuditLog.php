<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CentralAuditEvent;
use App\Exceptions\CentralAuditLogImmutableException;
use App\Models\Builders\CentralAuditLogBuilder;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * One append-only entry of the CENTRAL platform-operator audit log (IDEN-1.5).
 *
 * Lives in `central_audit_logs` on the central connection, even while a tenant is
 * initialized. Rows are written only through App\Services\CentralAuditLogger (which
 * redacts secrets) and can never be updated or deleted through Eloquent.
 *
 * IDEN-1.15 (CTO W1 Q2): the model sits on spatie/laravel-activitylog. It extends
 * Spatie\Activitylog\Models\Activity, so it IS an activitylog Activity (causer/subject
 * morphs, `properties`, causedBy()/forSubject()/forEvent() scopes, getExtraProperty()),
 * but it keeps its own table and column names, so no data was migrated:
 *  - table `central_audit_logs` (not config('activitylog.table_name')) on the central
 *    connection via UsesCentralConnection (the package constructor's connection default
 *    is never used: getConnectionName() wins);
 *  - `event` holds a CentralAuditEvent value; there is no `log_name`, `batch_uuid` or
 *    `updated_at` column, so the package's inLog()/hasBatch()/forBatch() scopes do not
 *    apply here, and rows are never written through the activity() helper: the ONLY write
 *    path stays App\Services\CentralAuditLogger (redaction, transaction rules, retries);
 *  - `properties` keeps the `array` cast (the package default is a Collection), so every
 *    existing reader keeps working; changes() is therefore always empty.
 * The package's own `activity_log` table (App\Models\CentralActivity) is a separate log.
 *
 * Retention: 2 years, pruned by the scheduled `central-audit:prune` command on the
 * dedicated `audit_pruner` DB connection (routes/console.php). In production the app's
 * DB user has no UPDATE/DELETE grant on this table.
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
class CentralAuditLog extends Activity
{
    use UsesCentralConnection;

    protected $table = 'central_audit_logs';

    /** Append-only: there is no updated_at column. */
    public const UPDATED_AT = null;

    /** The package model is fully unguarded; this one accepts $fillable only. */
    public $guarded = ['*'];

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
     * Overrides the package's `properties => collection` cast.
     *
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
