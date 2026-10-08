<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Exceptions\Billing\AddonPricingException;
use App\Models\Concerns\UsesCentralConnection;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Central DB: an add-on line a tenant bought (`subscription_addons`, ENTI-1.5).
 *
 * `unit_price` is the stored per-unit price for the line's cycle, frozen at purchase;
 * `lineTotal()` multiplies it by the quantity with bcmath. A line counts towards the
 * tenant's entitlements only while `activeFor()` returns it: status `active` and
 * starts_at <= moment < ends_at (NULL ends_at = open-ended, NULL starts_at = not started).
 *
 * @property int $id
 * @property int $subscription_id
 * @property string $tenant_id
 * @property int $addon_id
 * @property int $quantity
 * @property string $unit_price DECIMAL(12,3) as a numeric string
 * @property BillingCycle $billing_cycle
 * @property SubscriptionAddonStatus $status
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subscription $subscription
 * @property-read Tenant|null $tenant
 * @property-read Addon $addon
 */
class SubscriptionAddon extends Model
{
    use UsesCentralConnection;

    protected $table = 'subscription_addons';

    protected $fillable = [
        'subscription_id',
        'tenant_id',
        'addon_id',
        'quantity',
        'unit_price',
        'billing_cycle',
        'status',
        'starts_at',
        'ends_at',
    ];

    /**
     * Mirrors the DB defaults so a model created without these attributes is
     * consistent before it is refreshed.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 1,
        'billing_cycle' => 'monthly',
        'status' => 'pending_payment',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'subscription_id' => 'integer',
            'addon_id' => 'integer',
            'quantity' => 'integer',
            'unit_price' => 'decimal:3',
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionAddonStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<Addon, $this>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    /**
     * Lines of $tenant that are in force at $at (default: now): status `active`,
     * started (starts_at <= $at) and not ended (ends_at NULL or > $at).
     * pending_payment / cancelled / expired lines and lines outside their window are
     * excluded. The parent subscription's state is NOT checked here: tenant access is
     * decided by `tenants.status` (IDEN-3.x), and the entitlement engine owns that rule.
     *
     * @param  Builder<SubscriptionAddon>  $query
     * @return Builder<SubscriptionAddon>
     */
    public function scopeActiveFor(Builder $query, Tenant|string $tenant, ?DateTimeInterface $at = null): Builder
    {
        $tenantId = $tenant instanceof Tenant ? (string) $tenant->getKey() : $tenant;
        $moment = $at ?? Carbon::now();

        return $query
            ->where($this->qualifyColumn('tenant_id'), $tenantId)
            ->where($this->qualifyColumn('status'), SubscriptionAddonStatus::Active->value)
            ->whereNotNull($this->qualifyColumn('starts_at'))
            ->where($this->qualifyColumn('starts_at'), '<=', $moment)
            ->where(function (Builder $window) use ($moment): void {
                $window->whereNull($this->qualifyColumn('ends_at'))
                    ->orWhere($this->qualifyColumn('ends_at'), '>', $moment);
            });
    }

    /**
     * In-memory twin of scopeActiveFor() for an already loaded line.
     */
    public function isActive(?DateTimeInterface $at = null): bool
    {
        $moment = Carbon::instance($at ?? Carbon::now());

        return $this->status === SubscriptionAddonStatus::Active
            && $this->starts_at !== null
            && $this->starts_at->lte($moment)
            && ($this->ends_at === null || $this->ends_at->gt($moment));
    }

    /**
     * unit_price x quantity, scale 3 string (bcmath, no floats).
     *
     * @throws AddonPricingException when the quantity is below one
     */
    public function lineTotal(): string
    {
        $quantity = (int) $this->quantity;

        if ($quantity < 1) {
            throw AddonPricingException::invalidQuantity($quantity);
        }

        return bcmul((string) $this->unit_price, (string) $quantity, 3);
    }
}
