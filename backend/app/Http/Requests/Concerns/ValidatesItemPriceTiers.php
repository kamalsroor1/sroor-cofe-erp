<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Item;
use App\Support\Money\Decimal;
use Illuminate\Contracts\Validation\Validator;

/**
 * BE-08: a wholesale price (when set) may never sit below the minimum selling price (when set).
 * On update, a key that is not sent is compared using the stored value.
 */
trait ValidatesItemPriceTiers
{
    protected function validateWholesaleAgainstFloor(Validator $validator, ?Item $existing = null): void
    {
        $validator->after(function (Validator $validator) use ($existing): void {
            if ($validator->errors()->hasAny(['wholesale_price', 'min_selling_price'])) {
                return;
            }

            $wholesale = $this->filled('wholesale_price') ? Decimal::normalize((string) $this->input('wholesale_price')) : (string) ($existing->wholesale_price ?? '0');
            $min = $this->filled('min_selling_price') ? Decimal::normalize((string) $this->input('min_selling_price')) : (string) ($existing->min_selling_price ?? '0');

            if (bccomp($wholesale, '0', 3) > 0 && bccomp($min, '0', 3) > 0 && bccomp($wholesale, $min, 3) < 0) {
                $validator->errors()->add('wholesale_price', __('inventory.wholesale_below_min_price'));
            }
        });
    }
}
