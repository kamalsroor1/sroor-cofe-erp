<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Kind of catalog add-on in central `addons`: billed every cycle, or a service
 * (onboarding, premium support…) issued as its own invoice line.
 */
enum AddonType: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Recurring = 'recurring';
    case Service = 'service';

    public static function translationGroup(): string
    {
        return 'addon_type';
    }
}
