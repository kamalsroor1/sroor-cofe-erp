<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Billing cycle of a subscription / add-on line.
 *
 * Q-E6 [CTO-2026-10-08]: `biennial` exists as a value only — it is not priced
 * and not sold in Phase 1.
 */
enum BillingCycle: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Monthly = 'monthly';
    case Yearly = 'yearly';
    case Biennial = 'biennial';

    public static function translationGroup(): string
    {
        return 'billing_cycle';
    }

    public function isSellable(): bool
    {
        return $this !== self::Biennial;
    }

    /**
     * Cycles a tenant can buy right now.
     *
     * @return list<self>
     */
    public static function sellable(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $cycle): bool => $cycle->isSellable()));
    }
}
