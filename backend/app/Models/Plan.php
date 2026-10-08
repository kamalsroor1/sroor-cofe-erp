<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Central DB: a sellable plan. Prices are DECIMAL(12,3) strings; every limit is
 * nullable and NULL means unlimited.
 *
 * @property int $id
 * @property string $name
 * @property string|null $name_key
 * @property string $slug
 * @property string|null $description
 * @property string $price_monthly
 * @property string $price_yearly
 * @property string|null $founder_price_monthly
 * @property string|null $founder_price_yearly
 * @property int|null $max_users
 * @property int|null $max_stores retail branches only (warehouses and vans have their own limits)
 * @property int|null $max_warehouses
 * @property int|null $max_vans
 * @property int|null $max_items
 * @property int|null $max_invoices_per_month
 * @property int|null $max_storage_mb
 * @property int $trial_days
 * @property array<string, mixed>|null $features
 * @property bool $is_active
 * @property bool $is_public
 * @property bool $is_popular
 * @property int $sort_order
 */
class Plan extends Model
{
    use HasFactory;
    use UsesCentralConnection;

    /** Limit columns; NULL in any of them = unlimited. */
    public const LIMIT_COLUMNS = [
        'max_users',
        'max_stores',
        'max_warehouses',
        'max_vans',
        'max_items',
        'max_invoices_per_month',
        'max_storage_mb',
    ];

    protected $fillable = [
        'name',
        'name_key',
        'slug',
        'description',
        'price_monthly',
        'price_yearly',
        'founder_price_monthly',
        'founder_price_yearly',
        'max_users',
        'max_stores',
        'max_warehouses',
        'max_vans',
        'max_items',
        'max_invoices_per_month',
        'max_storage_mb',
        'trial_days',
        'features',
        'is_active',
        'is_public',
        'is_popular',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:3',
        'price_yearly' => 'decimal:3',
        'founder_price_monthly' => 'decimal:3',
        'founder_price_yearly' => 'decimal:3',
        'max_users' => 'integer',
        'max_stores' => 'integer',
        'max_warehouses' => 'integer',
        'max_vans' => 'integer',
        'max_items' => 'integer',
        'max_invoices_per_month' => 'integer',
        'max_storage_mb' => 'integer',
        'trial_days' => 'integer',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * @return HasMany<Tenant, $this>
     */
    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Add-ons offered on this plan (ENTI-1.4), with the `plan_addon` pivot.
     *
     * @return BelongsToMany<Addon, $this, PlanAddon, 'pivot'>
     */
    public function addons(): BelongsToMany
    {
        return $this->belongsToMany(Addon::class, 'plan_addon')
            ->using(PlanAddon::class)
            ->withPivot(PlanAddon::PIVOT_COLUMNS)
            ->withTimestamps();
    }

    /**
     * هل الفيتشر محدد ونشط في هذه الباقة؟
     */
    public function hasFeature(string $key): bool
    {
        return isset($this->features[$key]) && $this->features[$key] === true;
    }

    /**
     * نطاق الباقات النشطة المعروضة للجمهور
     *
     * @param  Builder<Plan>  $query
     * @return Builder<Plan>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order', 'asc');
    }
}
