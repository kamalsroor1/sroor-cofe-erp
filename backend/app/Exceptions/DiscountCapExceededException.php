<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * POS-07: the total discount of a sale is above the store's cap and nobody approved it. A normal 422
 * validation error plus a stable `error_code` (see bootstrap/app.php) and an `override` error key, the
 * same signal the SPA uses to ask for a manager PIN.
 */
final class DiscountCapExceededException extends ValidationException
{
    public const ERROR_CODE = 'pos.discount_cap_exceeded';

    public static function forCap(string $maxPercent): self
    {
        return self::withMessages([
            'discount' => __('pos.discount_cap_exceeded', ['max' => rtrim(rtrim($maxPercent, '0'), '.')]),
            'override' => __('pos.discount_override_required'),
        ]);
    }
}
