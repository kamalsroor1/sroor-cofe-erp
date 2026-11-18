<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LimitResource;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PLAN-12, CENTRAL `tenant_limit_overrides`: one shop's own limit for one resource, replacing the
 * plan + add-ons figure. `value` NULL = unlimited for this shop. An expired row is ignored (scopeActive).
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $resource LimitResource value
 * @property int|null $value
 * @property string $reason
 * @property Carbon|null $expires_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CentralUser|null $creator
 */
class TenantLimitOverride extends Model
{
    use UsesCentralConnection;

    public const REASON_MAX = 255;

    public const VALUE_MAX = 1000000;

    protected $table = 'tenant_limit_overrides';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'resource',
        'value',
        'reason',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'expires_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    /**
     * @param  Builder<TenantLimitOverride>  $query
     * @return Builder<TenantLimitOverride>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->lessThanOrEqualTo(now());
    }

    public function resourceEnum(): ?LimitResource
    {
        return LimitResource::tryFrom($this->resource);
    }

    /**
     * @return BelongsTo<CentralUser, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }
}
