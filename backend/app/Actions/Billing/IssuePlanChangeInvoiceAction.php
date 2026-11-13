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
use App\Services\Billing\ProrationCalculator;
use App\Services\CentralAuditLogger;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Console (BILL-02: `amount` may be omitted -> pro-rata suggestion): answer a pending request by issuing its invoice (owner decision: manual, no proration).
 *
 *  - plan_change: ONE `upgrade` line with the DIFFERENCE the admin typed, plan = the requested
 *    plan, cycle = the request's. Paying it switches the plan and keeps the paid term.
 *  - addon: an `addon` invoice with the add-on line(s), priced from the tenant's plan catalog for
 *    the request's cycle unless the admin overrides the unit price. It is a separate invoice.
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
    ) {}

    public function execute(IssuePlanChangeInvoiceDTO $dto, CentralUser $operator): PlanChangeRequest
    {
        $connection = DB::connection((string) (new PlanChangeRequest)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator): PlanChangeRequest {
            $request = PlanChangeRequest::query()->whereKey($dto->requestId)->lockForUpdate()->firstOrFail();

            if (! $request->isPending()) {
                throw BillingException::requestNotPending();
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
                ],
                $operator,
                $request,
                (string) $request->tenant_id,
            );

            return $request;
        });
    }

    /**
     * (new - old) x remaining / total at the subscription's own cycle, the term being the cycle that
     * ends at tenants.subscription_ends_at. '0.000' when there is no running term or no price gap.
     */
    private function suggestedDifference(Tenant $tenant, ?Plan $current, Plan $target): string
    {
        $end = $tenant->subscription_ends_at;
        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->first();

        if ($end === null || ! $end->isFuture() || ! $current instanceof Plan || ! $subscription instanceof Subscription || ! $subscription->billing_cycle->isSellable()) {
            return '0.000';
        }

        $yearly = $subscription->billing_cycle === BillingCycle::Yearly;
        $termEnd = CarbonImmutable::instance($end);
        $termStart = $yearly ? $termEnd->subYearNoOverflow() : $termEnd->subMonthNoOverflow();
        $now = CarbonImmutable::now();

        return $this->proration->difference(
            Decimal::normalize($yearly ? $current->price_yearly : $current->price_monthly),
            Decimal::normalize($yearly ? $target->price_yearly : $target->price_monthly),
            max(0, $termEnd->getTimestamp() - $now->getTimestamp()),
            max(1, $termEnd->getTimestamp() - $termStart->getTimestamp()),
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
        $tenant = Tenant::query()->whereKey($tenantId)->firstOrFail();

        if (! $target instanceof Plan) {
            throw BillingException::requestPlanUnavailable();
        }

        if ($tenant->plan_id !== null && (int) $tenant->plan_id === (int) $target->getKey()) {
            throw BillingException::requestSamePlan();
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
        );
    }
}
