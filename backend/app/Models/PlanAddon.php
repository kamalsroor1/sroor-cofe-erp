<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\BillingCycle;
use App\Exceptions\Billing\AddonPricingException;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Central DB: availability of an add-on on a plan (`plan_addon`).
 *
 * @property int $id
 * @property int $plan_id
 * @property int $addon_id
 * @property bool $is_available
 * @property int $included_quantity
 * @property string|null $unit_price_override flat monthly/one-time price on this plan, NULL = catalog
 * @property string|null $yearly_price_override flat yearly price on this plan, NULL = catalog
 * @property-read Plan $plan
 * @property-read Addon $addon
 */
class PlanAddon extends Pivot
{
    use UsesCentralConnection;

    /** Pivot columns exposed through Plan::addons() / Addon::plans(). */
    public const PIVOT_COLUMNS = [
        'id',
        'is_available',
        'included_quantity',
        'unit_price_override',
        'yearly_price_override',
    ];

    public $incrementing = true;

    protected $table = 'plan_addon';

    protected $fillable = [
        'plan_id',
        'addon_id',
        'is_available',
        'included_quantity',
        'unit_price_override',
        'yearly_price_override',
    ];

    protected $attributes = [
        'is_available' => true,
        'included_quantity' => 0,
    ];

    protected function casts(): array
    {
        return [
            'plan_id' => 'integer',
            'addon_id' => 'integer',
            'is_available' => 'boolean',
            'included_quantity' => 'integer',
            'unit_price_override' => 'decimal:3',
            'yearly_price_override' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Addon, $this>
     */
    public function addon(): BelongsTo
    {
        return $this->belongsTo(Addon::class);
    }

    /**
     * Unit price of the add-on on this plan: the plan override for the cycle when set
     * (a flat price, tiers do not apply to it), otherwise Addon::unitPriceFor().
     * Availability (`is_available`) and `included_quantity` are the caller's concern.
     *
     * @throws AddonPricingException
     */
    public function unitPriceFor(BillingCycle $cycle, int $quantity = 1): string
    {
        if (! $cycle->isSellable()) {
            throw AddonPricingException::cycleNotSellable($cycle);
        }

        if ($quantity < 1) {
            throw AddonPricingException::invalidQuantity($quantity);
        }

        $override = $cycle === BillingCycle::Yearly ? $this->yearly_price_override : $this->unit_price_override;

        if ($override !== null) {
            return bcadd($override, '0', 3);
        }

        return $this->addon->unitPriceFor($cycle, $quantity);
    }
}
