<?php

declare(strict_types=1);

namespace App\Enums\Billing;

use App\Enums\Billing\Concerns\BillingEnum;
use App\Enums\Billing\Concerns\HasTranslatedLabel;

/**
 * #232 UI §2: the one status card of a shop's invoice screen, derived from the invoice, its
 * payments and its temporary-access window (never stored). The shop never sees "paid" before a
 * super-admin verified the payment.
 *
 *  - awaiting_payment: nothing under review (no payment yet, or the shop withdrew its receipt);
 *  - under_review_temporary: a receipt is under review and the 48h window it opened is running;
 *  - under_review_grace_used: a NEWER receipt is under review while the invoice's first window
 *    still runs (no new 48h);
 *  - under_review_manual: under review, the proof was not classified as a transfer receipt image
 *    (no temporary access);
 *  - under_review: under review without a running window (it expired, was used before, or another
 *    invoice of the shop holds an unsettled one);
 *  - rejected: the last receipt was rejected by a super-admin (reason on the payment);
 *  - paid / void: the invoice is settled.
 */
enum InvoiceReviewState: string implements BillingEnum
{
    use HasTranslatedLabel;

    case AwaitingPayment = 'awaiting_payment';
    case UnderReviewTemporary = 'under_review_temporary';
    case UnderReviewGraceUsed = 'under_review_grace_used';
    case UnderReviewManual = 'under_review_manual';
    case UnderReview = 'under_review';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Void = 'void';

    public static function translationGroup(): string
    {
        return 'invoice_review_state';
    }
}
