<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Billing record status of a central `subscriptions` row.
 *
 * Access is decided by `tenants.status` (IDEN-3.x); this is the billing log only.
 */
enum SubscriptionStatus: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case PendingPayment = 'pending_payment';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public static function translationGroup(): string
    {
        return 'subscription_status';
    }
}
