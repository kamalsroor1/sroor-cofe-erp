<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use App\Enums\Billing\BillingCycle;
use LogicException;

/**
 * Founder pricing could not be applied safely (ENTI-1.7). These are caller or
 * configuration errors (no transaction, a payment that is not this tenant's verified
 * payment, invalid config, a cycle that is not sold), so nothing is written. The
 * message is translated (lang/{ar,en}/billing.php -> founder_pricing.*); reason() is a
 * stable code for callers and API error payloads.
 */
final class FounderPricingException extends LogicException
{
    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public static function transactionRequired(): self
    {
        return self::make('transaction_required');
    }

    public static function paymentNotVerified(): self
    {
        return self::make('payment_not_verified');
    }

    public static function paymentTenantMismatch(): self
    {
        return self::make('payment_tenant_mismatch');
    }

    /** Q-E6: biennial exists as an enum value only and is not priced in Phase 1. */
    public static function cycleNotSellable(BillingCycle $cycle): self
    {
        return self::make('cycle_not_sellable', ['cycle' => $cycle->label()]);
    }

    public static function invalidConfiguration(string $key): self
    {
        return self::make('invalid_configuration', ['key' => $key]);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function make(string $reason, array $replace = []): self
    {
        return new self($reason, (string) __('billing.founder_pricing.'.$reason, $replace));
    }
}
