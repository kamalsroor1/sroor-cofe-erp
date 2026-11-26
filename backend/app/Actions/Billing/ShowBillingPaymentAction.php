<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\BillingPayment;

/**
 * Super-admin: one manual payment (central DB) in the same shape as a review-queue item: tenant,
 * invoice (48h window, overpaid amount, plan change from/to) and verifier. Unknown id -> 404.
 */
final class ShowBillingPaymentAction
{
    /**
     * Relations every console payment response loads (queue, show, verify, reject), so
     * BillingPaymentResource never lazy-loads per row. `invoice.planChangeRequest.*` feeds
     * `invoice_plan_change`.
     */
    public const RELATIONS = [
        'tenant',
        'invoice.planChangeRequest.currentPlan',
        'invoice.planChangeRequest.requestedPlan',
        'verifier',
    ];

    public function execute(int $paymentId): BillingPayment
    {
        return BillingPayment::query()
            ->with(self::RELATIONS)
            ->findOrFail($paymentId);
    }
}
