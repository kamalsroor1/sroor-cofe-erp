<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Temporary raise of one tenant's rate limits by a platform operator (IDEN-4.6 ext,
 * CTO W1 Q1), CENTRAL `tenant_rate_limit_overrides`.
 *
 * Written only by App\Actions\Tenants\RaiseTenantRateLimitAction; read by the named
 * limiters through App\Support\TenantRateLimitOverrides. An override can only RAISE a
 * limit (the limiter uses max(config, override)) and always expires. Rows are kept as
 * history: a newer override revokes the previous one (`revoked_at`).
 *
 * @property int $id
 * @property string $tenant_id
 * @property int|null $tenant_login_per_ip_per_minute
 * @property int|null $tenant_login_per_login_per_minute
 * @property int|null $tenant_resolve_per_minute
 * @property string $reason
 * @property int|null $central_user_id
 * @property Carbon $expires_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 */
class TenantRateLimitOverride extends Model
{
    use UsesCentralConnection;

    /**
     * Override column => config key of the default limit it raises.
     *
     * @var array<string, string>
     */
    public const LIMITS = [
        'tenant_login_per_ip_per_minute' => 'rate_limits.tenant_login.per_ip_per_minute',
        'tenant_login_per_login_per_minute' => 'rate_limits.tenant_login.per_login_per_minute',
        'tenant_resolve_per_minute' => 'rate_limits.tenant_resolve.per_minute',
    ];

    public const REASON_MAX = 500;

    protected $table = 'tenant_rate_limit_overrides';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'tenant_login_per_ip_per_minute',
        'tenant_login_per_login_per_minute',
        'tenant_resolve_per_minute',
        'reason',
        'central_user_id',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tenant_login_per_ip_per_minute' => 'integer',
            'tenant_login_per_login_per_minute' => 'integer',
            'tenant_resolve_per_minute' => 'integer',
            'central_user_id' => 'integer',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /**
     * Not revoked and not expired yet.
     *
     * @param  Builder<TenantRateLimitOverride>  $query
     * @return Builder<TenantRateLimitOverride>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    /**
     * The raised values, keyed by override column (columns left null are omitted).
     *
     * @return array<string, int>
     */
    public function limits(): array
    {
        $limits = [];

        foreach (array_keys(self::LIMITS) as $column) {
            $value = $this->getAttribute($column);

            if (is_int($value) && $value > 0) {
                $limits[$column] = $value;
            }
        }

        return $limits;
    }
}
