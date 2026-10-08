<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Status of a purchased add-on line in central `subscription_addons`.
 */
enum SubscriptionAddonStatus: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Active = 'active';
    case PendingPayment = 'pending_payment';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public static function translationGroup(): string
    {
        return 'subscription_addon_status';
    }
}
