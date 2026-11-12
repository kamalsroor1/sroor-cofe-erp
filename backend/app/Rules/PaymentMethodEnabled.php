<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\Settings\PosSaleSettings;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SET-01: a NEW sale/receipt may only use a payment method the shop has enabled in its POS
 * settings (server-side, not just hidden in the UI). Old invoices keep whatever they stored.
 * Legacy aliases sent by old clients (smart_wallet, wallet, bank) map to their enum value first.
 */
final class PaymentMethodEnabled implements ValidationRule
{
    private const ALIASES = ['smart_wallet' => 'e_wallet', 'wallet' => 'e_wallet', 'bank' => 'bank_transfer'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $method = self::ALIASES[$value] ?? $value;

        if (! app(PosSaleSettings::class)->isMethodAccepted($method)) {
            $fail(__('settings.pos_payment_method_disabled'));
        }
    }
}
