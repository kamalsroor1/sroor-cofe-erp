<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Validation\ValidationException;

/**
 * SHOP-03: a credit / partial sale would take the customer's balance above their credit limit and
 * nobody approved it. A normal 422 validation error plus a stable `error_code` (see bootstrap/app.php)
 * and an `override` error key, the same signal the SPA uses to ask for a manager PIN.
 */
final class CreditLimitExceededException extends ValidationException
{
    public const ERROR_CODE = 'customer.credit_limit_exceeded';

    public static function forCustomer(string $limit, string $balance, string $newCredit, string $currency): self
    {
        return self::withMessages([
            'customer_id' => __('customers.credit_limit_exceeded', [
                'limit' => $limit,
                'balance' => $balance,
                'amount' => $newCredit,
                'currency' => $currency,
            ]),
            'override' => __('customers.credit_limit_override_required'),
        ]);
    }
}
