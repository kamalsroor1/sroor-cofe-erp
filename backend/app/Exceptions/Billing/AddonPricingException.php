<?php

declare(strict_types=1);

namespace App\Exceptions\Billing;

use App\Enums\Billing\BillingCycle;
use DomainException;

/**
 * A price could not be resolved for a catalog add-on. Thrown instead of guessing a
 * price (no derived yearly price, no formula for missing tiers). The message is
 * translated (lang/{ar,en}/billing.php → addon_pricing.*); reason() is a stable code
 * for callers and API error payloads.
 */
final class AddonPricingException extends DomainException
{
    private function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /** Q-E6: biennial exists as an enum value only and is not priced in Phase 1. */
    public static function cycleNotSellable(BillingCycle $cycle): self
    {
        return self::make('cycle_not_sellable', ['cycle' => $cycle->label()]);
    }

    public static function yearlyPriceMissing(string $addonKey): self
    {
        return self::make('yearly_price_missing', ['addon' => $addonKey]);
    }

    public static function invalidQuantity(int $quantity): self
    {
        return self::make('invalid_quantity', ['quantity' => (string) $quantity]);
    }

    public static function invalidPriceTiers(string $addonKey): self
    {
        return self::make('invalid_price_tiers', ['addon' => $addonKey]);
    }

    public static function invalidPrice(string $addonKey): self
    {
        return self::make('invalid_price', ['addon' => $addonKey]);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function make(string $reason, array $replace): self
    {
        return new self($reason, (string) __('billing.addon_pricing.'.$reason, $replace));
    }
}
