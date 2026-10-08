<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * Status of a SaaS invoice in central `billing_invoices` (not the tenant POS `invoices`).
 */
enum BillingInvoiceStatus: string implements BillingEnum
{
    use HasTranslatedLabel;

    case Draft = 'draft';
    case Pending = 'pending';
    case Paid = 'paid';
    case Void = 'void';
    case Refunded = 'refunded';

    public static function translationGroup(): string
    {
        return 'billing_invoice_status';
    }
}
