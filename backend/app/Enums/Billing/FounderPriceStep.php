<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * The step of the founder price schedule a subscription is on (owner decision 2026-11-15,
 * config billing.founder): year 1 founder price, then the transition price, then list price.
 * A subscription that never held a founder slot is always on `list`.
 */
enum FounderPriceStep: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Founder = 'founder';
    case Transition = 'transition';
    case List = 'list';

    public static function translationGroup(): string
    {
        return 'price_step';
    }

    /** Founder and transition are both founder pricing (a discounted plan line). */
    public function isDiscounted(): bool
    {
        return $this !== self::List;
    }
}
