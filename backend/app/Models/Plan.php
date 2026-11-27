<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\UsesCentralConnection;
use App\Support\Billing\PlanPromotion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;

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
 * @property string|null $founder_transition_price_monthly founder price of months 13-24 (NULL = none)
 * @property string|null $founder_transition_price_yearly
 * @property string|null $compare_at_price_monthly promotion "was" price (see PlanPromotion)
 * @property string|null $compare_at_price_yearly
 * @property Carbon|null $promo_starts_at NULL = the promotion is already running
 * @property Carbon|null $promo_ends_at a promotion without an end is never shown
 * @property int|null $max_users
 * @property int|null $max_stores retail branches only (warehouses and vans have their own limits)
 * @property int|null $max_warehouses
 * @property int|null $max_vans
 * @property int|null $max_items
 * @property int|null $max_invoices_per_month
 * @property int|null $max_storage_mb
 * @property int|null $max_customers PLAN-11 (the walk-in customer is not counted)
 * @property int|null $max_suppliers PLAN-11
 * @property int $trial_days
 * @property bool $is_free PLAN-10: permanent free plan (never billed, no trial, tenant always active)
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

    /**
     * Slugs owned by the catalog seeder / data migrations (Database\Seeders\Catalog\PlanCatalog).
     * A plan created from the console never takes one of them, so the catalog never touches it.
     */
    public const CATALOG_SLUGS = ['free', 'basic', 'pro', 'enterprise'];

    /** Limit columns; NULL in any of them = unlimited. */
    public const LIMIT_COLUMNS = [
        'max_users',
        'max_stores',
        'max_warehouses',
        'max_vans',
        'max_items',
        'max_invoices_per_month',
        'max_storage_mb',
        'max_customers',
        'max_suppliers',
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
        'founder_transition_price_monthly',
        'founder_transition_price_yearly',
        'compare_at_price_monthly',
        'compare_at_price_yearly',
        'promo_starts_at',
        'promo_ends_at',
        'max_users',
        'max_stores',
        'max_warehouses',
        'max_vans',
        'max_items',
        'max_invoices_per_month',
        'max_storage_mb',
        'max_customers',
        'max_suppliers',
        'trial_days',
        'is_free',
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
        'founder_transition_price_monthly' => 'decimal:3',
        'founder_transition_price_yearly' => 'decimal:3',
        'compare_at_price_monthly' => 'decimal:3',
        'compare_at_price_yearly' => 'decimal:3',
        'promo_starts_at' => 'datetime',
        'promo_ends_at' => 'datetime',
        'max_users' => 'integer',
        'max_stores' => 'integer',
        'max_warehouses' => 'integer',
        'max_vans' => 'integer',
        'max_items' => 'integer',
        'max_invoices_per_month' => 'integer',
        'max_storage_mb' => 'integer',
        'max_customers' => 'integer',
        'max_suppliers' => 'integer',
        'trial_days' => 'integer',
        'is_free' => 'boolean',
        'features' => 'array',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'is_popular' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        // SEC-06: tenants.plan_id is nullOnDelete, so deleting a plan in use would silently turn
        // its tenants into plan-less (unenforced) ones. Move them to another plan first.
        static::deleting(function (self $plan): void {
            if ($plan->tenants()->exists()) {
                throw ValidationException::withMessages(['plan' => (string) __('subscription.errors.plan_has_tenants')]);
            }
        });
    }

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
     * The promotion ("was" price) shown at $at (default now), or null when none is running.
     */
    public function activePromotion(?CarbonInterface $at = null): ?PlanPromotion
    {
        $promotion = PlanPromotion::fromPlan($this);

        return $promotion !== null && $promotion->isActiveAt($at ?? Carbon::now()) ? $promotion : null;
    }

    /**
     * هل الفيتشر محدد ونشط في هذه الباقة؟
     */
    public function hasFeature(string $key): bool
    {
        return isset($this->features[$key]) && $this->features[$key] === true;
    }

    /**
     * Legacy free-plan names that should always display as the Free plan label (`المجانية` / `Free`).
     *
     * @var list<string>
     */
    private const LEGACY_FREE_PLAN_NAMES = ['التجربة', 'Trial', 'trial'];

    /**
     * Localized display name: uses `name_key` when present (matching PublicPlanResource),
     * maps legacy free-plan trial names ("التجربة" / "Trial") to `plans.plans.free.name`,
     * maps catalog plans with missing `name_key` and default catalog names to `plans.plans.<slug>.name`,
     * and falls back to the stored `name` column.
     */
    public function localizedName(?string $locale = null): string
    {
        if (is_string($this->name_key) && $this->name_key !== '' && Lang::has($this->name_key, $locale)) {
            return (string) __($this->name_key, [], $locale);
        }

        $stored = (string) $this->name;

        if (($this->slug === 'free' || $this->is_free)
            && ($stored === '' || in_array($stored, self::LEGACY_FREE_PLAN_NAMES, true))
            && Lang::has('plans.plans.free.name', $locale)
        ) {
            return (string) __('plans.plans.free.name', [], $locale);
        }

        if (in_array((string) $this->slug, self::CATALOG_SLUGS, true)) {
            $catalogKey = 'plans.plans.'.$this->slug.'.name';
            if (Lang::has($catalogKey, $locale) && (
                $stored === ''
                || $stored === (string) __($catalogKey, [], 'ar')
                || $stored === (string) __($catalogKey, [], 'en')
            )) {
                return (string) __($catalogKey, [], $locale);
            }
        }

        return $stored;
    }

    /**
     * Localized description derived from `name_key` sibling (`*.name` -> `*.description`),
     * falling back to the stored `description` column.
     */
    public function localizedDescription(?string $locale = null): ?string
    {
        if (is_string($this->name_key) && str_ends_with($this->name_key, '.name')) {
            $descKey = substr($this->name_key, 0, -strlen('name')).'description';
            if (Lang::has($descKey, $locale)) {
                return (string) __($descKey, [], $locale);
            }
        }

        return $this->description;
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
