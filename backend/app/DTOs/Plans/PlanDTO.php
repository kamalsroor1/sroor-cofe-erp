<?php

declare(strict_types=1);

namespace App\DTOs\Plans;

/**
 * Input of CreatePlanAction / UpdatePlanAction (validated StorePlanRequest / UpdatePlanRequest).
 *
 * `attributes` holds only the plan columns the request sent (an omitted optional field is left
 * untouched on update); money is a DECIMAL(12,3) string, dates 'Y-m-d H:i:s' (app timezone).
 * `features` is the submitted key => bool map. `addons` is null when the request did not send the
 * add-on list (availability untouched), else one row per add-on to write.
 */
final readonly class PlanDTO
{
    /** Plan columns the console may write (PLAN-ENF: every limit, max_invoices_per_month / max_storage_mb included; PLAN-10: is_free). */
    public const COLUMNS = [
        'name', 'name_key', 'description',
        'price_monthly', 'price_yearly',
        'founder_price_monthly', 'founder_price_yearly',
        'founder_transition_price_monthly', 'founder_transition_price_yearly',
        'compare_at_price_monthly', 'compare_at_price_yearly', 'promo_starts_at', 'promo_ends_at',
        'max_users', 'max_stores', 'max_warehouses', 'max_vans', 'max_items',
        'max_invoices_per_month', 'max_storage_mb',
        'trial_days', 'is_free', 'is_active', 'is_public', 'is_popular', 'sort_order',
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, bool>  $features
     * @param  list<array{addon_id: int, is_available: bool, included_quantity: int, included_billing_cycle: string|null}>|null  $addons
     */
    public function __construct(
        public array $attributes,
        public array $features,
        public ?array $addons,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated plan request data
     */
    public static function fromArray(array $data): self
    {
        $attributes = array_intersect_key($data, array_flip(self::COLUMNS));

        foreach (['name_key', 'description'] as $nullable) {
            if (array_key_exists($nullable, $attributes) && is_string($attributes[$nullable]) && trim($attributes[$nullable]) === '') {
                $attributes[$nullable] = null;
            }
        }
        if (isset($attributes['name']) && is_string($attributes['name'])) {
            $attributes['name'] = trim($attributes['name']);
        }

        $features = [];
        foreach ((array) ($data['features'] ?? []) as $key => $value) {
            $features[(string) $key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        }

        $addons = null;
        if (array_key_exists('addons', $data) && is_array($data['addons'])) {
            $addons = [];
            foreach ($data['addons'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $quantity = max(0, (int) ($row['included_quantity'] ?? 0));
                $cycle = $row['included_billing_cycle'] ?? null;
                $addons[] = [
                    'addon_id' => (int) $row['addon_id'],
                    'is_available' => filter_var($row['is_available'], FILTER_VALIDATE_BOOLEAN),
                    'included_quantity' => $quantity,
                    // A cycle only means something on an included add-on.
                    'included_billing_cycle' => $quantity > 0 && is_string($cycle) && $cycle !== '' ? $cycle : null,
                ];
            }
        }

        return new self($attributes, $features, $addons);
    }

    /**
     * @return array{attributes: array<string, mixed>, features: array<string, bool>, addons: list<array{addon_id: int, is_available: bool, included_quantity: int, included_billing_cycle: string|null}>|null}
     */
    public function toArray(): array
    {
        return [
            'attributes' => $this->attributes,
            'features' => $this->features,
            'addons' => $this->addons,
        ];
    }
}
