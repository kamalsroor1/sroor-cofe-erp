<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

/**
 * BE-08 / POS-18: the optional manager approval of a below-minimum-price sale, shared by
 * /invoices and /pos/checkout. The PIN is checked server-side by SalePriceGuard (never here), and only
 * when a line is actually below its floor and the acting user lacks `pos.price.below_min`.
 */
trait ValidatesPriceOverride
{
    /**
     * @return array<string, list<string>>
     */
    protected function priceOverrideRules(): array
    {
        return [
            'override' => ['nullable', 'array'],
            'override.user_id' => ['required_with:override', 'integer', 'min:1'],
            // A string: a JSON number would lose a leading zero.
            'override.pin' => ['required_with:override', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }

    /**
     * Rules for a sale line's numbers (unit_price is optional: the server then uses the tier list price): plain decimals only (no 1e3), at most 3 decimals, DECIMAL(12,3) range.
     *
     * @return array<string, list<string>>
     */
    protected function saleLineNumberRules(string $discountKey): array
    {
        return [
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'decimal:0,3', 'max:999999999.999'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            'items.*.'.$discountKey => ['nullable', 'numeric', 'min:0', 'decimal:0,3', 'max:999999999.999'],
            // POS-07: the line discount as a percent of the line total (wins over the amount; the server turns it into money).
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,3'],
        ];
    }
}
