<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\Tenant;

/**
 * PRD-04: a shop asks for its own renewal invoice (tenant context, `billing.manage`).
 *
 * Always the tenant's CURRENT plan at the chosen cycle (monthly | yearly), no discount, no founder
 * price, issued_by null. Plan changes are not self-service: the shop contacts support.
 * Idempotent per period: when a non-void renewal invoice for this period already exists the
 * underlying IssueSubscriptionInvoiceAction answers BillingException 409 (billing.invoice_duplicate)
 * whose details name the existing invoice. SELL-06: the same 409 code (details.open_invoice = true)
 * when ANY unpaid plan / renewal / upgrade invoice exists, e.g. the auto-issued trial-end invoice.
 */
final class RequestRenewalInvoiceAction
{
    public function __construct(private readonly IssueSubscriptionInvoiceAction $issue) {}

    public function execute(Tenant $tenant, BillingCycle $cycle): BillingInvoice
    {
        if ($tenant->plan_id === null || ! $cycle->isSellable()) {
            throw BillingException::noPlan();
        }

        return $this->issue->execute(new IssueSubscriptionInvoiceDTO(
            tenantId: (string) $tenant->getKey(),
            type: BillingInvoiceType::Renewal,
            planId: (int) $tenant->plan_id,
            cycle: $cycle,
            useFounderPrice: false,
            serviceLines: [],
            discount: '0.000',
            notes: null,
            periodStart: null,
            issuedBy: null,
            // SELL-06: never a second unpaid plan-type invoice next to e.g. the trial-end one.
            refuseWhenOpenPlanInvoice: true,
        ));
    }
}
