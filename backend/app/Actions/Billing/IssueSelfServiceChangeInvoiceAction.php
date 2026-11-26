<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\IssuePlanChangeInvoiceDTO;
use App\DTOs\Billing\SelfServiceChangeInvoiceDTO;
use App\DTOs\Billing\SelfServiceChangeInvoiceResult;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use App\Services\Billing\SubscriptionInvoicePricer;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * #232 UI §1: the shop issues its own upgrade / subscribe invoice ("issue the invoice and pay"),
 * priced exactly like GET /billing/change-preview and the console issue: a PlanChangeRequest is filed
 * (kept for audit and the console; ActivateSubscriptionAction completes it when the invoice is paid)
 * and answered at once by IssuePlanChangeInvoiceAction with no operator. So: list price only, upgrade
 * only or upgrade + renewal on a running paid term, one full-cycle plan line with no paid term
 * (a trial may subscribe to the paid plan it is on, item 8), the term starting at the verified transfer.
 *
 * Refusals, before anything is written (same codes as the preview / request / issue flows):
 *  - 422 request_plan_unavailable (not active / public), free_plan_not_billable, request_same_plan
 *    (same plan with a running paid term: use renew), upgrade_not_higher (running term, plan not
 *    dearer: downgrade is an open owner decision), request_already_pending (an open plan-change request);
 *  - 409 billing.invoice_duplicate when an unpaid plan / renewal / upgrade invoice exists (pay it first).
 *
 * #232 spec C: a tenant with an agreed founder price gets NO invoice. The request is filed pending and
 * stamped `founder_review_held_at` (audited), and the result says held_for_price_review; the owner
 * issues it from the console with release_founder_hold.
 *
 * One central transaction, tenant row locked first (the billing lock order); IssuePlanChangeInvoiceAction
 * and IssueSubscriptionInvoiceAction nest inside it, so a refusal there rolls the request back too.
 */
final class IssueSelfServiceChangeInvoiceAction
{
    public function __construct(
        private readonly IssuePlanChangeInvoiceAction $issue,
        private readonly CentralAuditLogger $audit,
        private readonly FounderPricingService $founder,
        private readonly SubscriptionInvoicePricer $pricer,
    ) {}

    public function execute(SelfServiceChangeInvoiceDTO $dto): SelfServiceChangeInvoiceResult
    {
        $connection = DB::connection((string) (new PlanChangeRequest)->getConnectionName());

        return $connection->transaction(function () use ($dto): SelfServiceChangeInvoiceResult {
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();
            $target = $this->targetPlan($dto, $tenant);

            $this->assertNoOpenRequest($tenant);
            $this->assertNoOpenInvoice($tenant);

            $held = $this->founder->hasAgreedFounderPrice((string) $tenant->getKey());
            $request = $this->file($dto, $tenant, $target, $held);

            if ($held) {
                return new SelfServiceChangeInvoiceResult($request->load(['currentPlan', 'requestedPlan']), null, true);
            }

            $issued = $this->issue->execute(new IssuePlanChangeInvoiceDTO(
                requestId: (int) $request->getKey(),
                amount: null,
                description: null,
                quantity: null,
                unitPrice: null,
                notes: null,
                withRenewal: $dto->renew,
            ), null);

            $invoice = BillingInvoice::query()->with('payments')->findOrFail((int) $issued->billing_invoice_id);

            return new SelfServiceChangeInvoiceResult($issued->load(['currentPlan', 'requestedPlan']), $invoice, false);
        });
    }

    private function targetPlan(SelfServiceChangeInvoiceDTO $dto, Tenant $tenant): Plan
    {
        $target = Plan::query()->whereKey($dto->planId)->where('is_active', true)->where('is_public', true)->first();

        if (! $target instanceof Plan) {
            throw BillingException::requestPlanUnavailable();
        }
        if ($target->is_free) {
            throw BillingException::freePlanNotBillable();
        }

        $samePlan = $tenant->plan_id !== null && (int) $tenant->plan_id === (int) $target->getKey();

        if (! $tenant->hasPaidTermRunning()) {
            return $target;
        }
        if ($samePlan) {
            throw BillingException::requestSamePlan();
        }

        // Running term: only a dearer plan (the same check as the preview; downgrade is open).
        $current = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);
        $difference = $this->pricer->upgradeDifference($tenant, $current, $target);
        if (bccomp($difference['amount'], '0', 3) <= 0) {
            throw BillingException::upgradeNotHigher();
        }

        return $target;
    }

    private function assertNoOpenRequest(Tenant $tenant): void
    {
        $open = PlanChangeRequest::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('kind', PlanChangeRequestKind::PlanChange->value)
            ->open()
            ->exists();

        if ($open) {
            throw BillingException::requestAlreadyPending();
        }
    }

    /** SELL-06: an unpaid plan / renewal / upgrade invoice must be paid (or voided) first. */
    private function assertNoOpenInvoice(Tenant $tenant): void
    {
        $existing = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('type', [BillingInvoiceType::Plan->value, BillingInvoiceType::Renewal->value, BillingInvoiceType::Upgrade->value])
            ->whereIn('status', [BillingInvoiceStatus::Draft->value, BillingInvoiceStatus::Pending->value])
            ->orderBy('id')
            ->first();

        if ($existing instanceof BillingInvoice) {
            throw BillingException::openInvoiceExists($existing);
        }
    }

    private function file(SelfServiceChangeInvoiceDTO $dto, Tenant $tenant, Plan $target, bool $held): PlanChangeRequest
    {
        $request = PlanChangeRequest::query()->create([
            'tenant_id' => (string) $tenant->getKey(),
            'kind' => PlanChangeRequestKind::PlanChange,
            'current_plan_id' => $tenant->plan_id,
            'requested_plan_id' => (int) $target->getKey(),
            'quantity' => 1,
            'billing_cycle' => $dto->cycle,
            'renew' => $dto->renew,
            'status' => PlanChangeRequestStatus::Pending,
            'requested_by_name' => $dto->requestedByName,
            'requested_by_user_id' => $dto->requestedByUserId,
            'founder_review_held_at' => $held ? now() : null,
        ]);

        $this->audit->record(
            CentralAuditEvent::PlanChangeRequested,
            [
                'request_id' => (int) $request->getKey(),
                'kind' => PlanChangeRequestKind::PlanChange->value,
                'current_plan_id' => $tenant->plan_id,
                'requested_plan_id' => (int) $target->getKey(),
                'billing_cycle' => $dto->cycle->value,
                'renew' => $dto->renew,
                'action' => $dto->action,
                'self_service' => true,
            ],
            null,
            $request,
            (string) $tenant->getKey(),
        );

        if ($held) {
            $this->audit->record(
                CentralAuditEvent::PlanChangeHeldForFounderReview,
                ['request_id' => (int) $request->getKey(), 'kind' => PlanChangeRequestKind::PlanChange->value, 'requested_plan_id' => (int) $target->getKey(), 'self_service' => true],
                null,
                $request,
                (string) $tenant->getKey(),
            );
        }

        return $request;
    }
}
