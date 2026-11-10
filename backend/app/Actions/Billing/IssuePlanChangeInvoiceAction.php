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
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Console: answer a pending request by issuing its invoice (owner decision: manual, no proration).
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

        if ($dto->amount === null || bccomp($dto->amount, '0', 3) <= 0) {
            throw BillingException::differenceRequired();
        }

        $current = Plan::query()->find($tenant->plan_id ?? $request->current_plan_id);

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
                'amount' => $dto->amount,
            ],
            fromRequest: true,
        );
    }
}
