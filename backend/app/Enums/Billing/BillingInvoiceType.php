<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Why a SaaS invoice was issued (ENTI-3.2 / ENTI-3.10).
 */
enum BillingInvoiceType: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Plan = 'plan';
    case Renewal = 'renewal';
    case Upgrade = 'upgrade';
    case Addon = 'addon';
    case Service = 'service';

    public static function translationGroup(): string
    {
        return 'billing_invoice_type';
    }
}
