<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\IssuePlanChangeInvoiceDTO;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use App\Services\Billing\ProrationCalculator;
use App\Services\CentralAuditLogger;
use App\Support\Money\Decimal;
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
 *
 * A request whose target is a permanent free plan is never invoiced: it is stored `scheduled`
 * (not pending -> 409 here) and an older pending one is refused (422 free_plan_not_billable).
 *
 * One central transaction: the request row is locked first (409 `billing.request_not_pending`
 * when it is no longer pending, so a double click issues one invoice), then
 * IssueSubscriptionInvoiceAction reuses its numbering / VAT / rounding inside the same transaction.
 */
final class IssuePlanChangeInvoiceAction
{
    public function __construct(
        private readonly IssueSubscriptionInvoiceAction $issue,
        private readonly CentralAuditLogger $audit,
        private readonly ProrationCalculator $proration,
        private readonly FounderPricingService $founder,
    ) {}

    public function execute(IssuePlanChangeInvoiceDTO $dto, CentralUser $operator): PlanChangeRequest
    {
        $connection = DB::connection((string) (new PlanChangeRequest)->getConnectionName());

        $issued = $connection->transaction(function () use ($dto, $operator): ?PlanChangeRequest {
            $request = PlanChangeRequest::query()->whereKey($dto->requestId)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw BillingException::requestNotPending();
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
            $request->reviewed_by = (int) $operator->getKey();
            $request->reviewed_at = now();
            $request->save();

            $this->audit->record(
                CentralAuditEvent::PlanChangeInvoiced,
                [
                    'request_id' => (int) $request->getKey(),
                    'kind' => $request->kind->value,
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_number' => $invoice->number,
                    'total' => $invoice->total,
                    'with_renewal' => $dto->withRenewal,
                    'founder_hold_released' => $dto->releaseFounderHold && $request->founder_review_held_at !== null,
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

    /** #232 spec C: stamp (once) and audit a request held for the owner's founder-price review. */
    private function holdForFounderReview(PlanChangeRequest $request, CentralUser $operator): void
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

    /**
     * #232 spec B: (new - current) x remaining days / actual days of the current period, both prices
     * at the subscription's own cycle; the current period is the cycle that ends at
     * tenants.subscription_ends_at. Days per ProrationCalculator (remaining rounded UP to whole days
     * on the platform clock). '0.000' when there is no running term or no price gap.
     */
    private function suggestedDifference(Tenant $tenant, ?Plan $current, Plan $target): string
    {
        $end = $tenant->subscription_ends_at;
        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->first();

        if ($end === null || ! $end->isFuture() || ! $current instanceof Plan || ! $subscription instanceof Subscription || ! $subscription->billing_cycle->isSellable()) {
            return '0.000';
        }

        $yearly = $subscription->billing_cycle === BillingCycle::Yearly;
        $termEnd = CarbonImmutable::instance($end)->setTimezone((string) config('app.timezone'));
        $termStart = $yearly ? $termEnd->subYearNoOverflow() : $termEnd->subMonthNoOverflow();

        return $this->proration->difference(
            Decimal::normalize($yearly ? $current->price_yearly : $current->price_monthly),
            Decimal::normalize($yearly ? $target->price_yearly : $target->price_monthly),
            $this->proration->wholeDaysUp(CarbonImmutable::now(), $termEnd),
            $this->proration->wholeDaysUp($termStart, $termEnd),
        );
    }

    private function invoiceDto(IssuePlanChangeInvoiceDTO $dto, PlanChangeRequest $request, CentralUser $operator): IssueSubscriptionInvoiceDTO
    {
        $tenantId = (string) $request->tenant_id;
        $issuedBy = (int) $operator->getKey();

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

        if ($tenant->plan_id !== null && (int) $tenant->plan_id === (int) $target->getKey()) {
            throw BillingException::requestSamePlan();
        }

        // #232: no paid term running (a trial, or an expired term) means there is no remainder to
        // price a difference on. With no amount typed, the request is answered with a REGULAR
        // full-cycle invoice for the requested plan (a plan line priced by IssueSubscriptionInvoiceAction,
        // list price: spec C, no automatic founder price on a plan change); paying it switches the
        // plan and starts a new paid term at the verified transfer moment (spec A: not at trial end,
        // remaining trial days are not added - open owner decision أ).
        // A typed amount is still a difference and stays 409 difference_needs_active_term.
        if ($dto->amount === null && ! $tenant->hasPaidTermRunning()) {
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
            );
        }

        $current = Plan::query()->find($tenant->plan_id ?? $request->current_plan_id);

        // BILL-02: no amount typed -> the system proposes the pro-rata difference for the rest of the term.
        $amount = $dto->amount ?? $this->suggestedDifference($tenant, $current, $target);

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
            ],
            fromRequest: true,
            appendRenewal: $dto->withRenewal,
        );
    }
}
