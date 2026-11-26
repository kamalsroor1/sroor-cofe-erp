<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * #232 spec D: what App\Contracts\Billing\TransferReceiptClassifier concluded about an uploaded
 * payment proof (`billing_payments.receipt_classification`). Only `transfer_receipt_image` opens
 * the 48-hour temporary access; `not_receipt` means manual review only (never an automatic reject).
 */
enum ReceiptClassification: string implements BillingEnum
{
    use HasTranslatedLabel;

    case TransferReceiptImage = 'transfer_receipt_image';
    case NotReceipt = 'not_receipt';

    public static function translationGroup(): string
    {
        return 'receipt_classification';
    }
}
