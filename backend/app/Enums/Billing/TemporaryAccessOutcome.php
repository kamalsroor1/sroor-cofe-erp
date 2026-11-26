<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * #232 spec D: how an invoice's 48-hour temporary-access window was settled
 * (`billing_invoices.temporary_access_outcome`). Expiry is not an outcome: an expired window stays
 * unsettled until the review decides (verified / rejected) or the invoice is voided.
 */
enum TemporaryAccessOutcome: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Verified = 'verified';
    case Rejected = 'rejected';
    case Voided = 'voided';

    public static function translationGroup(): string
    {
        return 'temporary_access_outcome';
    }
}
