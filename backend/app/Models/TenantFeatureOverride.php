<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeatureOverrideMode;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * PLAN-12, CENTRAL `tenant_feature_overrides`: grant or revoke one feature for one shop on top of
 * plan + add-ons. `feature_key` is canonical. An expired row is ignored (scopeActive).
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $feature_key
 * @property FeatureOverrideMode $mode
 * @property string $reason
 * @property Carbon|null $expires_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CentralUser|null $creator
 */
class TenantFeatureOverride extends Model
{
    use UsesCentralConnection;

    public const REASON_MAX = 255;

    protected $table = 'tenant_feature_overrides';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'feature_key',
        'mode',
        'reason',
        'expires_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'mode' => FeatureOverrideMode::class,
            'expires_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    /**
     * @param  Builder<TenantFeatureOverride>  $query
     * @return Builder<TenantFeatureOverride>
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

    /**
     * @return BelongsTo<CentralUser, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(CentralUser::class, 'created_by');
    }
}
