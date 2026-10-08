<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Billing\AddonType;
use App\Enums\Billing\BillingCycle;
use App\Exceptions\Billing\AddonPricingException;
use App\Models\Concerns\UsesCentralConnection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Central DB: a catalog add-on (extra store/user/van…, a feature such as mixes.manage,
 * or a service such as onboarding).
 *
 * Prices are explicit stored DECIMAL(12,3) strings. Volume tiers are stored prices, not
 * percentages; the yearly price is stored, never derived from the monthly one.
 *
 * @property int $id
 * @property string $key
 * @property string $name_key
 * @property AddonType $type
 * @property string|null $feature_key
 * @property array<string, int>|null $bundled_limits
 * @property string $unit_price monthly unit price (recurring) or one-time price (service)
 * @property string|null $yearly_price NULL = not sold yearly
 * @property array<array-key, mixed>|null $price_tiers raw JSON: [{"min_qty": 3, "unit_price": "212.000", "yearly_price": "2120.000"}, …]
 * @property bool $is_active
 * @property bool $is_public
 * @property int $sort_order
 * @property PlanAddon|null $pivot
 */
class Addon extends Model
{
    use UsesCentralConnection;

    private const MONEY_PATTERN = '/^\d{1,9}(\.\d{1,3})?$/';

    protected $fillable = [
        'key',
        'name_key',
        'type',
        'feature_key',
        'bundled_limits',
        'unit_price',
        'yearly_price',
        'price_tiers',
        'is_active',
        'is_public',
        'sort_order',
    ];

    protected $attributes = [
        'type' => 'recurring',
        'is_active' => true,
        'is_public' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'bundled_limits' => 'array',
            'unit_price' => 'decimal:3',
            'yearly_price' => 'decimal:3',
            'price_tiers' => 'array',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Plan, $this, PlanAddon, 'pivot'>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_addon')
            ->using(PlanAddon::class)
            ->withPivot(PlanAddon::PIVOT_COLUMNS)
            ->withTimestamps();
    }

    /**
     * @return HasMany<PlanAddon, $this>
     */
    public function planAddons(): HasMany
    {
        return $this->hasMany(PlanAddon::class);
    }

    /**
     * Add-ons that exist and can be billed.
     *
     * @param  Builder<Addon>  $query
     * @return Builder<Addon>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Add-ons listed in the tenant-facing catalog (hidden ones are super-admin only).
     *
     * @param  Builder<Addon>  $query
     * @return Builder<Addon>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function isService(): bool
    {
        return $this->type === AddonType::Service;
    }

    public function isRecurring(): bool
    {
        return $this->type === AddonType::Recurring;
    }

    /**
     * Unit price (scale 3 string) of this add-on for a cycle and a quantity.
     *
     * - recurring: the tier with the highest `min_qty` <= quantity prices the WHOLE quantity
     *   (Q-E3); below the first tier the base price applies. Monthly reads `unit_price`,
     *   yearly reads `yearly_price` — both stored values, never computed;
     * - service: the stored one-time price, whatever the cycle or quantity;
     * - biennial (Q-E6), quantity < 1, a missing yearly price or malformed tiers throw a
     *   translated AddonPricingException instead of guessing.
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

        if ($this->isService()) {
            // TODO(CTO): a monthly service (premium_support 299/month) is billed as a one-time
            // service line on each invoice; there is no yearly service price in Phase 1.
            return $this->money($this->unit_price);
        }

        $tier = $this->tierFor($quantity);

        if ($cycle === BillingCycle::Yearly) {
            $yearly = $tier === null ? $this->yearly_price : $tier['yearly_price'];

            if ($yearly === null) {
                // TODO(CTO): yearly tier prices (e.g. store 2120/1870) are not in the approved
                // price list; until they are stored explicitly, yearly at a tier is not priced.
                throw AddonPricingException::yearlyPriceMissing($this->key);
            }

            return $this->money($yearly);
        }

        return $tier === null ? $this->money($this->unit_price) : $tier['unit_price'];
    }

    /**
     * The tier with the highest `min_qty` that the quantity reaches, or null (base price).
     *
     * @return array{min_qty: int, unit_price: string, yearly_price: string|null}|null
     *
     * @throws AddonPricingException
     */
    private function tierFor(int $quantity): ?array
    {
        $match = null;

        foreach ($this->validatedTiers() as $tier) {
            if ($tier['min_qty'] <= $quantity && ($match === null || $tier['min_qty'] > $match['min_qty'])) {
                $match = $tier;
            }
        }

        return $match;
    }

    /**
     * `price_tiers` is raw JSON edited by the super-admin: validate it strictly and
     * normalise every price to a scale-3 string. Malformed data throws, never guesses.
     *
     * @return list<array{min_qty: int, unit_price: string, yearly_price: string|null}>
     *
     * @throws AddonPricingException
     */
    private function validatedTiers(): array
    {
        $raw = $this->price_tiers;

        if ($raw === null || $raw === []) {
            return [];
        }

        if (! array_is_list($raw)) {
            throw AddonPricingException::invalidPriceTiers($this->key);
        }

        $tiers = [];
        foreach ($raw as $tier) {
            if (! is_array($tier)) {
                throw AddonPricingException::invalidPriceTiers($this->key);
            }

            $minQty = $tier['min_qty'] ?? null;
            $unitPrice = $tier['unit_price'] ?? null;
            $yearlyPrice = $tier['yearly_price'] ?? null;

            if (! is_int($minQty)
                || $minQty < 2
                || isset($tiers[$minQty])
                || ! $this->isMoney($unitPrice)
                || ($yearlyPrice !== null && ! $this->isMoney($yearlyPrice))
            ) {
                throw AddonPricingException::invalidPriceTiers($this->key);
            }

            $tiers[$minQty] = [
                'min_qty' => $minQty,
                'unit_price' => bcadd((string) $unitPrice, '0', 3),
                'yearly_price' => $yearlyPrice === null ? null : bcadd((string) $yearlyPrice, '0', 3),
            ];
        }

        return array_values($tiers);
    }

    /**
     * Stored prices are non-negative decimal strings (or integers) with at most 3 decimals.
     * Floats are rejected: a JSON float is exactly what this table must never contain.
     */
    private function isMoney(mixed $value): bool
    {
        if (is_int($value)) {
            return $value >= 0;
        }

        return is_string($value) && preg_match(self::MONEY_PATTERN, $value) === 1;
    }

    /**
     * @throws AddonPricingException
     */
    private function money(string $value): string
    {
        if (! $this->isMoney($value)) {
            throw AddonPricingException::invalidPrice($this->key);
        }

        return bcadd($value, '0', 3);
    }
}
