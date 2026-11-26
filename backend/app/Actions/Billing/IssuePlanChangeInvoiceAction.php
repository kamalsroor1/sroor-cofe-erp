<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\IssuePlanChangeInvoiceDTO;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use App\Services\Billing\SubscriptionInvoicePricer;
use App\Services\CentralAuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Console: answer a pending request by issuing its invoice.
 *
 *  - plan_change on a running paid term (#232 spec B):
 *      - upgrade only (default): ONE `upgrade` difference line, the pro-rata suggestion when no
 *        amount is typed (ProrationCalculator: (new - current) x remaining days / actual period
 *        days at the current term's cycle). Paying it switches the plan, the term end is kept;
 *      - upgrade + renewal (`with_renewal`): the same difference line PLUS a plan line (flagged
 *        `renewal`) for one full cycle of the new plan at the request's cycle, appended after the
 *        current paid end; paying it switches the plan and extends the end by that cycle.
 *  - plan_change with no paid term running (trial / expired) and no amount typed: a regular
 *    full-cycle plan line for the requested plan (a typed amount stays 409
 *    difference_needs_active_term; `with_renewal` changes nothing there, it is one full cycle anyway).
 *    Exception, spec C: a RELEASED founder hold with a typed amount bills that full cycle at the
 *    typed amount, the price the owner agreed and reviewed (line flagged `agreed_price`, VAT on top,
 *    never re-priced at Verify, never claims a founder slot).
 *    The term starts at the verified transfer (ActivateSubscriptionAction, spec A), not at trial end.
 *  - addon: an `addon` invoice with the add-on line(s), priced from the tenant's plan catalog for
 *    the request's cycle unless the admin overrides the unit price. It is a separate invoice.
 *
 * #232 spec C: no automatic founder pricing on this path. A tenant with an AGREED founder price
 * (FounderPricingService::hasAgreedFounderPrice) has its plan change HELD for the owner's review:
 * nothing is invoiced, the request stays pending and is stamped `founder_review_held_at` (audited
 * once) and the call answers 409 `billing.upgrade_held_for_founder_review`. The owner releases it by
 * issuing again with `release_founder_hold` (optionally with a typed amount). The stamp is committed
 * before the 409 is thrown, so the console can show the held state.
 * `release_founder_hold` on a request that was never held is refused with 409
 * `billing.founder_hold_not_set`. Released or not, every plan line of a plan change is billed at the
 * LIST price (`listPriceOnly`): a founder step never applies to an upgrade automatically.
 *
 * A request whose target is a permanent free plan is never invoiced: it is stored `scheduled`
 * (not pending -> 409 here) and an older pending one is refused (422 free_plan_not_billable).
 *
 * `$operator` null = the shop's own self-service change invoice (IssueSelfServiceChangeInvoiceAction):
 * same pricing, no reviewer stamped. `with_renewal` not sent = the request's stored `renew` choice.
 *
 * One central transaction: the tenant row is locked first, then the request row (409 `billing.request_not_pending`
 * when it is no longer pending, so a double click issues one invoice), then
 * IssueSubscriptionInvoiceAction reuses its numbering / VAT / rounding inside the same transaction.
 */
final class IssuePlanChangeInvoiceAction
{
    public function __construct(
        private readonly IssueSubscriptionInvoiceAction $issue,
        private readonly CentralAuditLogger $audit,
        private readonly SubscriptionInvoicePricer $pricer,
        private readonly FounderPricingService $founder,
    ) {}

    public function execute(IssuePlanChangeInvoiceDTO $dto, ?CentralUser $operator): PlanChangeRequest
    {
        $connection = DB::connection((string) (new PlanChangeRequest)->getConnectionName());

        $issued = $connection->transaction(function () use ($dto, $operator): ?PlanChangeRequest {
            // #232 review: the billing lock order is tenant -> payment -> invoice(s) -> subscription ->
            // plan_change_requests (ActivateSubscriptionAction completes the request last). Lock the
            // tenant FIRST (its id read unlocked; a request never changes tenant), then the request.
            $tenantId = PlanChangeRequest::query()->whereKey($dto->requestId)->valueOrFail('tenant_id');
            Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

            $request = PlanChangeRequest::query()->whereKey($dto->requestId)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw BillingException::requestNotPending();
            }

            // #232 review: releasing a founder hold is only meaningful for a request that was held.
            if ($dto->releaseFounderHold && $request->founder_review_held_at === null) {
                throw BillingException::founderHoldNotSet($dto->requestId);
            }

            if ($request->kind === PlanChangeRequestKind::PlanChange
                && ! $dto->releaseFounderHold
                && $this->founder->hasAgreedFounderPrice((string) $request->tenant_id)) {
                $this->holdForFounderReview($request, $operator);

                return null;
            }

            $invoice = $this->issue->execute($this->invoiceDto($dto, $request, $operator), $operator);

            $request->status = PlanChangeRequestStatus::Invoiced;
            $request->billing_invoice_id = (int) $invoice->getKey();
            // Self-service (the shop's own change-invoice): issued with no operator, nobody reviewed it.
            $request->reviewed_by = $operator === null ? null : (int) $operator->getKey();
            $request->reviewed_at = $operator === null ? null : now();
            $request->save();

            $this->audit->record(
                CentralAuditEvent::PlanChangeInvoiced,
                [
                    'request_id' => (int) $request->getKey(),
                    'kind' => $request->kind->value,
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_number' => $invoice->number,
                    'total' => $invoice->total,
                    'with_renewal' => $this->withRenewal($dto, $request),
                    'self_service' => $operator === null,
                    // A release is only accepted for a held request (founder_hold_not_set otherwise).
                    'founder_hold_released' => $dto->releaseFounderHold,
                    'agreed_price' => $this->agreedPrice($invoice),
                ],
                $operator,
                $request,
                (string) $request->tenant_id,
            );

            return $request;
        });

        if (! $issued instanceof PlanChangeRequest) {
            throw BillingException::upgradeHeldForFounderReview($dto->requestId);
        }

        return $issued;
    }

    /** #232: the operator's explicit `with_renewal`, else the shop's choice stored on the request. */
    private function withRenewal(IssuePlanChangeInvoiceDTO $dto, PlanChangeRequest $request): bool
    {
        return $dto->withRenewal ?? (bool) $request->renew;
    }

    /** #232 spec C: the owner-agreed full-cycle price billed on the invoice, null when none. */
    private function agreedPrice(BillingInvoice $invoice): ?string
    {
        foreach ($invoice->lines as $line) {
            if (($line['agreed_price'] ?? false) === true) {
                return (string) $line['amount'];
            }
        }

        return null;
    }

    /** #232 spec C: stamp (once) and audit a request held for the owner's founder-price review. */
    private function holdForFounderReview(PlanChangeRequest $request, ?CentralUser $operator): void
    {
        if ($request->founder_review_held_at !== null) {
            return;
        }

        $request->founder_review_held_at = now();
        $request->save();

        $this->audit->record(
            CentralAuditEvent::PlanChangeHeldForFounderReview,
            ['request_id' => (int) $request->getKey(), 'kind' => $request->kind->value, 'requested_plan_id' => $request->requested_plan_id],
            $operator,
            $request,
            (string) $request->tenant_id,
        );
    }

    private function invoiceDto(IssuePlanChangeInvoiceDTO $dto, PlanChangeRequest $request, ?CentralUser $operator): IssueSubscriptionInvoiceDTO
    {
        $tenantId = (string) $request->tenant_id;
        $issuedBy = $operator === null ? null : (int) $operator->getKey();

        if ($request->kind === PlanChangeRequestKind::Addon) {
            if ($request->addon_id === null) {
                throw BillingException::requestAddonUnavailable();
            }

            return new IssueSubscriptionInvoiceDTO(
                tenantId: $tenantId,
                type: BillingInvoiceType::Addon,
                planId: null,
                cycle: $request->billing_cycle,
                useFounderPrice: false,
                serviceLines: [],
                discount: '0.000',
                notes: $dto->notes,
                periodStart: null,
                issuedBy: $issuedBy,
                addonLines: [[
                    'addon_id' => (int) $request->addon_id,
                    'quantity' => max(1, $dto->quantity ?? (int) $request->quantity),
                    'unit_price' => $dto->unitPrice,
                ]],
                fromRequest: true,
            );
        }

        $target = $request->requestedPlan;
        // #232 review: lock the tenant before choosing full-invoice vs difference, so a renewal paid
        // between this read and IssueSubscriptionInvoiceAction's own lock cannot flip the decision.
        // Re-entrant in the same transaction; keeps the tenant-first lock order.
        $tenant = Tenant::query()->whereKey($tenantId)->lockForUpdate()->firstOrFail();

        if (! $target instanceof Plan) {
            throw BillingException::requestPlanUnavailable();
        }

        // PLAN-10 review: a move to a permanent free plan is never invoiced (it is a `scheduled`
        // downgrade applied by the lifecycle sweep); this guards rows filed before that existed.
        if ($target->is_free) {
            throw BillingException::freePlanNotBillable();
        }

        // #232 item 8: with no paid term running (a trial on a paid plan, or an expired term) the
        // same plan is a SUBSCRIBE: one full cycle at the list price, starting at the verified transfer.
        if ($tenant->plan_id !== null && (int) $tenant->plan_id === (int) $target->getKey() && $tenant->hasPaidTermRunning()) {
            throw BillingException::requestSamePlan();
        }

        // #232: no paid term running (a trial, or an expired term) means there is no remainder to
        // price a difference on. With no amount typed, the request is answered with a REGULAR
        // full-cycle invoice for the requested plan (a plan line priced by IssueSubscriptionInvoiceAction,
        // list price: spec C, no automatic founder price on a plan change); paying it switches the
        // plan and starts a new paid term at the verified transfer moment (spec A: not at trial end,
        // remaining trial days are not added - open owner decision أ).
        // A typed amount is still a difference and stays 409 difference_needs_active_term, unless it
        // releases a founder hold: spec C, the owner reviewed the agreed founder price before issue, so
        // the typed amount IS the full-cycle price (agreedPlanPrice; release needs a held request).
        $agreedPrice = $dto->releaseFounderHold ? $dto->amount : null;
        if (($dto->amount === null || $agreedPrice !== null) && ! $tenant->hasPaidTermRunning()) {
            return new IssueSubscriptionInvoiceDTO(
                tenantId: $tenantId,
                type: BillingInvoiceType::Upgrade,
                planId: (int) $target->getKey(),
                cycle: $request->billing_cycle,
                useFounderPrice: false,
                serviceLines: [],
                discount: '0.000',
                notes: $dto->notes,
                periodStart: null,
                issuedBy: $issuedBy,
                fromRequest: true,
                // SELL-06: an open trial-end / plan invoice paid as well would extend the term twice.
                refuseWhenOpenPlanInvoice: true,
                // #232 spec C: list price only, even for a founder whose hold was released...
                listPriceOnly: true,
                // ...unless the release typed the agreed price for this full cycle.
                agreedPlanPrice: $agreedPrice,
            );
        }

        $current = Plan::query()->find($tenant->plan_id ?? $request->current_plan_id);

        // BILL-02: no amount typed -> the system proposes the pro-rata difference for the rest of the term
        // (SubscriptionInvoicePricer, the same code as the shop's change preview), priced now as an
        // estimate. Owner decision 2026-11-26: the amount becomes final at Verify, re-priced from the
        // verified transfer moment (UpgradeDifferenceRepricer), so the line keeps its proration inputs.
        $suggested = $dto->amount === null ? $this->pricer->upgradeDifference($tenant, $current, $target) : null;
        $amount = $dto->amount ?? $suggested['amount'] ?? '0.000';

        if (bccomp($amount, '0', 3) <= 0) {
            throw BillingException::differenceRequired();
        }

        return new IssueSubscriptionInvoiceDTO(
            tenantId: $tenantId,
            type: BillingInvoiceType::Upgrade,
            planId: (int) $target->getKey(),
            cycle: $request->billing_cycle,
            useFounderPrice: false,
            serviceLines: [],
            discount: '0.000',
            notes: $dto->notes,
            periodStart: null,
            issuedBy: $issuedBy,
            differenceLine: [
                'description' => $dto->description ?? (string) __('billing.invoice.upgrade_difference', [
                    'from' => $current instanceof Plan ? $current->name : (string) __('billing.invoice.no_plan_name'),
                    'to' => $target->name,
                ]),
                'amount' => $amount,
            ] + ($suggested === null ? [] : ['proration' => [
                'old_price' => $suggested['old_price'],
                'new_price' => $suggested['new_price'],
                'remaining_days' => $suggested['remaining_days'],
                'period_days' => $suggested['period_days'],
                'priced_at' => CarbonImmutable::now()->toIso8601String(),
            ]]),
            fromRequest: true,
            appendRenewal: $this->withRenewal($dto, $request),
            // #232 spec C: the appended renewal cycle is billed at the list price (no founder step).
            listPriceOnly: true,
        );
    }
}
