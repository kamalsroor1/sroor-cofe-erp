<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Payment gateway key stored on `billing_payments.gateway`.
 *
 * Which gateways are enabled is configuration (`config('billing.gateways')`, ENTI-3.1),
 * not this enum. Phase 1 activates payments manually only.
 */
enum BillingGateway: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Manual = 'manual';
    case Paymob = 'paymob';
    case Fawry = 'fawry';

    public static function translationGroup(): string
    {
        return 'billing_gateway';
    }
}
