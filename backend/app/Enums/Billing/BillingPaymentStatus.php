<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Status of a payment attempt in central `billing_payments`.
 *
 * `rejected` = a manual receipt refused by a super-admin (ENTI-3.4);
 * `failed` = a gateway-reported failure (Paymob/Fawry, later).
 */
enum BillingPaymentStatus: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public static function translationGroup(): string
    {
        return 'billing_payment_status';
    }
}
