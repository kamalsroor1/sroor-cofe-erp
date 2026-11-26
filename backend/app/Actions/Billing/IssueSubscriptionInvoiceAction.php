<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\TemporaryAccessOutcome;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingSequenceService;
use App\Services\Billing\SubscriptionInvoicePricer;
use App\Services\Billing\TemporaryAccessService;
use App\Services\CentralAuditLogger;
use App\Support\Database\TransactionRetry;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;

/**
 * PRD-03 / ENTI-3.2 + ENTI-3.10: issue a SaaS subscription invoice (central DB).
 *
 * Types: plan | renewal | upgrade (a plan line at the chosen cycle), service (one-off lines) and
 * addon (add-on lines priced from the tenant's plan catalog, only from a support request).
 * An upgrade can instead carry ONE "difference" line (the pro-rata suggestion or the amount the
 * admin typed), optionally followed by a `renewal` plan line for one full cycle of the new plan
 * appended after the current paid end (#232 spec B, upgrade + renewal). Plan-type invoices may carry extra service lines. Everything is snapshotted into `lines`.
 *
 * Plan line price (PRICE-2026-11): FounderPricingService decides the step of the tenant's founder
 * schedule at the PERIOD START of the billed cycle, and a cycle is priced entirely by that step
 * (a monthly cycle starting one day before the year-1 end pays the founder price for the whole
 * month; the next one pays the transition price). A tenant on a discounted step (founder or
 * transition) gets it automatically on every plan / renewal / upgrade invoice, whoever issues it;
 * `useFounderPrice` is only needed for a FIRST payment that should claim a free slot
 * (`founderPriceIfAvailable` asks for the same price without refusing when none is free). A
 * difference line is never founder-priced (it is the amount the admin decided).
 *
 * Money: bcmath strings at scale 3. VAT = platform setting billing_tax_rate, computed ONCE on the
 * net (subtotal - discount) and rounded half-up with App\Support\Money\Decimal (the SETG-13 rule).
 * #232: plan lines, renewal add-on lines, subtotal and VAT are priced by SubscriptionInvoicePricer,
 * the same code GET /billing/change-preview runs, so a preview always equals the issued invoice.
 * `listPriceOnly` (the plan-change path, spec C) bills plan lines at the list price only.
 *
 * One central transaction through TransactionRetry (a deadlock is retried, then 409 busy): the
 * tenant row is locked first, so two issuers for the same tenant are serialised and the duplicate
 * check below cannot race. Tenant FIRST is the one billing lock order (tenant -> payment ->
 * invoice(s) -> subscription, see VerifyManualPaymentAction): settleOpenRenewals() locks the open
 * renewal invoices only after the tenant row, and verify / record-payment lock the tenant before
 * their payment / invoice, so the two flows can no longer wait on each other in a cycle. The gap-free number comes from
 * BillingSequenceService inside the same transaction (a failure leaves no number gap).
 * Idempotent per (tenant, type, period_start) while the earlier invoice is not void: BillingException 409.
 *
 * Runs from the central console and, for RequestRenewalInvoiceAction, from a tenant request
 * (issuedBy null); both use only central-pinned models, so tenancy context is irrelevant.
 */
final class IssueSubscriptionInvoiceAction
{
    /** Upper bound of the months one monthly add-on purchase can be charged for (a prepaid term). */
    private const MAX_ADDON_MONTHS = 36;

    public function __construct(
        private readonly BillingSequenceService $sequences,
        private readonly SubscriptionInvoicePricer $pricer,
        private readonly CentralAuditLogger $audit,
        private readonly TemporaryAccessService $temporaryAccess,
    ) {}

    public function execute(IssueSubscriptionInvoiceDTO $dto, ?CentralUser $actor = null): BillingInvoice
    {
        $connection = (string) (new BillingInvoice)->getConnectionName();

        return TransactionRetry::run(function () use ($dto, $actor): BillingInvoice {
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();
            $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->first();

            $plan = $this->planFor($dto);
            $cycle = $plan !== null || $dto->type === BillingInvoiceType::Addon ? $dto->cycle : null;

            if ($dto->refuseWhenOpenPlanInvoice) {
                $this->assertNoOpenPlanInvoice($tenant);
            }

            // #232 spec B: "upgrade + renewal" bills the appended full cycle at the requested cycle.
            $renewalCycle = $cycle;
            $appendRenewal = $dto->differenceLine !== null && $dto->appendRenewal;

            // BILL-02: a difference keeps the paid term, so it stays on the subscription's own cycle
            // (monthly -> yearly would otherwise leave a yearly cycle on a monthly end date).
            if ($dto->differenceLine !== null && $subscription instanceof Subscription && $subscription->billing_cycle->isSellable()) {
                $cycle = $subscription->billing_cycle;
            }

            $termEnd = $this->futureEnd($tenant, $subscription);

            // A difference bills only the gap on a paid term, so it needs a term still running.
            if ($dto->differenceLine !== null && $termEnd === null) {
                throw BillingException::differenceNeedsActiveTerm();
            }

            // Upgrade + renewal: the invoice period is the appended cycle (current end -> + cycle).
            $periodStart = match (true) {
                $appendRenewal => $termEnd->startOfSecond(),
                $dto->differenceLine !== null => CarbonImmutable::now()->startOfSecond(),
                default => $this->periodStart($dto, $tenant, $subscription),
            };
            if (! $dto->fromRequest && $dto->type !== BillingInvoiceType::Addon) {
                $this->assertNotDuplicate($tenant, $dto->type, $periodStart, $this->futureEnd($tenant, $subscription) === null);
            }

            // BILL-01 review: a renewal paid after this difference would move the term it prices.
            if ($dto->differenceLine !== null) {
                $this->settleOpenRenewals($tenant, $actor);
            }

            $lines = [];
            if ($plan instanceof Plan && $cycle instanceof BillingCycle) {
                $lines[] = $dto->differenceLine !== null
                    ? $this->differenceLine($dto->differenceLine, $plan, $cycle, $termEnd)
                    : $this->pricer->planLine($plan, $cycle, $tenant, $subscription, $periodStart, $dto->useFounderPrice, $dto->founderPriceIfAvailable, $dto->listPriceOnly);
            }
            if ($appendRenewal && $plan instanceof Plan && $renewalCycle instanceof BillingCycle) {
                $lines[] = array_merge($this->pricer->planLine($plan, $renewalCycle, $tenant, $subscription, $periodStart, $dto->useFounderPrice, $dto->founderPriceIfAvailable, $dto->listPriceOnly), [
                    'renewal' => true,
                    'description' => (string) __('billing.invoice.upgrade_renewal_line', ['plan' => $plan->name, 'cycle' => $renewalCycle->label()]),
                ]);
                $cycle = $renewalCycle;
            }
            if ($dto->type === BillingInvoiceType::Renewal && $cycle instanceof BillingCycle && $subscription instanceof Subscription) {
                foreach ($this->pricer->renewalAddonLines($subscription, $tenant, $periodStart, $this->pricer->periodEnd($periodStart, $cycle), $cycle) as $renewalLine) {
                    $lines[] = $renewalLine;
                }
            }
            if ($dto->addonLines !== [] && $cycle instanceof BillingCycle) {
                if (! $subscription instanceof Subscription) {
                    throw BillingException::addonNeedsSubscription();
                }
                if (! $subscription->isInForce()) {
                    throw BillingException::addonNeedsActiveSubscription();
                }
                $months = $cycle === BillingCycle::Monthly ? $this->addonMonths($termEnd) : 1;
                foreach ($dto->addonLines as $addonLine) {
                    $lines[] = $this->addonLine($addonLine, $cycle, $tenant, $months);
                }
            }
            foreach ($dto->serviceLines as $service) {
                $lines[] = [
                    'type' => 'service',
                    'description' => $service['description'],
                    'quantity' => 1,
                    'unit_price' => $service['amount'],
                    'amount' => $service['amount'],
                ];
            }

            $subtotal = $this->pricer->subtotal($lines);

            // A discount can reduce the invoice but never to zero or below: such an invoice is unpayable.
            if (bccomp($dto->discount, '0', 3) < 0 || bccomp($dto->discount, $subtotal, 3) >= 0) {
                throw BillingException::discountTooHigh();
            }

            $net = bcsub($subtotal, $dto->discount, 3);
            ['rate' => $taxRate, 'tax' => $tax, 'total' => $total] = $this->pricer->tax($net);

            $now = CarbonImmutable::now();

            $invoice = BillingInvoice::query()->create([
                'number' => $this->sequences->nextInvoiceNumber($now),
                'tenant_id' => (string) $tenant->getKey(),
                'subscription_id' => $subscription?->getKey(),
                'type' => $dto->type,
                'status' => BillingInvoiceStatus::Pending,
                'billing_cycle' => $cycle,
                'period_start' => $periodStart,
                'period_end' => $dto->differenceLine !== null && ! $appendRenewal ? $termEnd : $this->pricer->periodEnd($periodStart, $cycle),
                'lines' => $lines,
                'subtotal' => $subtotal,
                'discount' => $dto->discount,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $total,
                'currency' => Subscription::DEFAULT_CURRENCY,
                'issued_at' => $now,
                'due_at' => $this->dueAt($now, $dto->differenceLine !== null ? $termEnd : null),
                'issued_by' => $dto->issuedBy,
                'notes' => $dto->notes,
            ]);

            $this->audit->record(
                CentralAuditEvent::BillingInvoiceIssued,
                [
                    'invoice_id' => (int) $invoice->getKey(),
                    'number' => $invoice->number,
                    'type' => $dto->type->value,
                    'billing_cycle' => $cycle?->value,
                    'total' => $total,
                    'tax_rate' => $taxRate,
                    'requested_by_tenant' => $dto->issuedBy === null,
                ],
                $actor,
                $invoice,
                (string) $tenant->getKey(),
            );

            return $invoice;
        }, connectionName: $connection);
    }

    /** Normal due date, but a difference invoice must be payable only while the term it prices is still running. */
    private function dueAt(CarbonImmutable $now, ?CarbonImmutable $termEnd): CarbonImmutable
    {
        $due = $now->addDays(max(1, (int) config('billing.invoice_due_days', 7)));

        return $termEnd !== null && $termEnd->lessThan($due) ? $termEnd : $due;
    }

    /**
     * BILL-01 review: an upgrade difference prices the rest of the CURRENT term, so no renewal may be
     * paid while it is open. Every open renewal invoice of the tenant (locked, id order) is voided
     * when nothing was paid on it; one with a payment pending review or already (partly) verified
     * refuses the difference with 409 `billing.renewal_in_flight` (support settles the renewal
     * first, then issues the difference for the new term). Voiding is safe: SubmitManualPayment
     * locks the same invoice row and re-checks it is pending, and after the upgrade the shop
     * requests a renewal at the new plan. ActivateSubscriptionAction is the second line of defence.
     */
    private function settleOpenRenewals(Tenant $tenant, ?CentralUser $actor): void
    {
        $open = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', BillingInvoiceType::Renewal->value)
            ->whereIn('status', [BillingInvoiceStatus::Draft->value, BillingInvoiceStatus::Pending->value])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($open as $renewal) {
            $paid = BillingPayment::query()
                ->where('billing_invoice_id', $renewal->getKey())
                ->whereIn('status', [BillingPaymentStatus::Verified->value, BillingPaymentStatus::Pending->value])
                ->exists();

            if ($paid) {
                throw BillingException::renewalInFlight($renewal);
            }
        }

        foreach ($open as $renewal) {
            $renewal->status = BillingInvoiceStatus::Void;
            $renewal->voided_at = now();
            $renewal->save();

            // #232 spec D: a voided invoice never keeps an unsettled 48h window.
            $this->temporaryAccess->settle($tenant, $renewal, TemporaryAccessOutcome::Voided, $actor, CarbonImmutable::now());

            $this->audit->record(
                CentralAuditEvent::BillingInvoiceVoided,
                ['invoice_id' => (int) $renewal->getKey(), 'number' => $renewal->number, 'type' => $renewal->type->value, 'reason' => 'superseded_by_upgrade'],
                $actor,
                $renewal,
                (string) $renewal->tenant_id,
            );
        }
    }

    /**
     * BILL-02: the whole months a MONTHLY add-on bought now must be charged for so it can run to the
     * paid term end (rounded UP: a started month is a month, no daily proration), 1 when there is no
     * running term. ActivateSubscriptionAction grants exactly these months, capped at the term end.
     */
    private function addonMonths(?CarbonImmutable $termEnd): int
    {
        if ($termEnd === null) {
            return 1;
        }

        $now = CarbonImmutable::now();
        $months = 1;

        while ($months < self::MAX_ADDON_MONTHS && $now->addMonthsNoOverflow($months)->lessThan($termEnd)) {
            $months++;
        }

        return $months;
    }

    private function planFor(IssueSubscriptionInvoiceDTO $dto): ?Plan
    {
        if ($dto->type === BillingInvoiceType::Service) {
            return null;
        }

        if ($dto->type === BillingInvoiceType::Addon) {
            if ($dto->addonLines === [] || ! $dto->cycle instanceof BillingCycle || ! $dto->cycle->isSellable()) {
                throw BillingException::noPlan();
            }

            return null;
        }

        if ($dto->planId === null || ! $dto->cycle instanceof BillingCycle || ! $dto->cycle->isSellable()) {
            throw BillingException::noPlan();
        }

        $plan = Plan::query()->find($dto->planId);

        if (! $plan instanceof Plan) {
            throw BillingException::noPlan();
        }

        // PLAN-10: a permanent free plan has no invoice (trial-end, renewal, console or upgrade).
        if ($plan->is_free) {
            throw BillingException::freePlanNotBillable();
        }

        return $plan;
    }

    /**
     * The upgrade difference line (the pro-rata suggestion or the amount the admin typed), never a
     * catalog / founder price. `difference` tells ActivateSubscriptionAction to switch the plan
     * without extending the term (unless a `renewal` line follows); `term_end` is the paid-term end
     * it priced (#232), so a renewal paid in between is detected even on an upgrade + renewal invoice.
     *
     * A system-computed difference carries its `proration` inputs (owner decision 2026-11-26): the
     * line is flagged `prorated` with old/new price, period days, the remaining days and the moment
     * it was priced, so VerifyManualPaymentAction can re-price it from the verified transfer moment
     * with the same ProrationCalculator (UpgradeDifferenceRepricer). A typed amount has no
     * `proration` and is never re-priced.
     *
     * @param  array{description: string, amount: string, proration?: array{old_price: string, new_price: string, remaining_days: int, period_days: int, priced_at: string}}  $difference
     * @return array<string, mixed>
     */
    private function differenceLine(array $difference, Plan $plan, BillingCycle $cycle, ?CarbonImmutable $termEnd): array
    {
        $amount = Decimal::normalize($difference['amount']);

        return [
            'type' => 'plan',
            'difference' => true,
            'plan_id' => (int) $plan->getKey(),
            'plan_name' => $plan->name,
            'billing_cycle' => $cycle->value,
            'description' => $difference['description'],
            'term_end' => $termEnd?->toIso8601String(),
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
            'founder' => false,
        ] + (isset($difference['proration']) ? ['prorated' => true] + $difference['proration'] : []);
    }

    /**
     * One add-on line, priced from the tenant's plan (plan override, then catalog tier) unless the
     * admin overrides the unit price. The add-on must be active and offered on the tenant's plan.
     *
     * BILL-02: a MONTHLY add-on is charged for $months months (see addonMonths()); an admin unit
     * price override is a per-month price too. `unit_price` is the per-unit price of the whole
     * charged period, `cycle_unit_price` the per-cycle one stored on the subscription line.
     *
     * @param  array{addon_id: int, quantity: int, unit_price: string|null}  $line
     * @return array<string, mixed>
     */
    private function addonLine(array $line, BillingCycle $cycle, Tenant $tenant, int $months = 1): array
    {
        $addon = Addon::query()->find($line['addon_id']);
        $planAddon = $addon === null || $tenant->plan_id === null ? null : PlanAddon::query()
            ->where('plan_id', $tenant->plan_id)
            ->where('addon_id', $addon->getKey())
            ->where('is_available', true)
            ->first();

        if (! $addon instanceof Addon || ! $addon->is_active || ! $planAddon instanceof PlanAddon) {
            throw BillingException::requestAddonUnavailable();
        }

        $quantity = $line['quantity'];
        $unit = $line['unit_price'] !== null
            ? Decimal::normalize($line['unit_price'])
            : Decimal::normalize($planAddon->unitPriceFor($cycle, $quantity));
        $months = $cycle === BillingCycle::Monthly ? max(1, $months) : 1;
        $periodUnit = bcmul($unit, (string) $months, 3);
        $amount = bcmul($periodUnit, (string) $quantity, 3);

        return [
            'type' => 'addon',
            'addon_id' => (int) $addon->getKey(),
            'addon_key' => $addon->key,
            'description' => (string) __('billing.invoice.addon_line', ['addon' => (string) __($addon->name_key), 'cycle' => $cycle->label()]),
            'billing_cycle' => $cycle->value,
            'months' => $months,
            'quantity' => $quantity,
            'cycle_unit_price' => $unit,
            'unit_price' => $periodUnit,
            'amount' => $amount,
        ];
    }

    private function periodStart(IssueSubscriptionInvoiceDTO $dto, Tenant $tenant, ?Subscription $subscription): CarbonImmutable
    {
        if ($dto->periodStart !== null) {
            return $dto->periodStart->startOfSecond();
        }

        $now = CarbonImmutable::now();

        if ($dto->type === BillingInvoiceType::Service || $dto->type === BillingInvoiceType::Addon) {
            return $now->startOfSecond();
        }

        if ($dto->type === BillingInvoiceType::Renewal) {
            $end = $this->futureEnd($tenant, $subscription);

            if ($end !== null) {
                return $end->startOfSecond();
            }
        }

        // One plan/upgrade/expired-renewal invoice per day: a double click is a 409, not a second invoice.
        return $now->startOfDay();
    }

    /**
     * The PAID term end when it is still in the future, else null (expired / never paid). Only the
     * tenant's subscription_ends_at counts, the same source ActivateSubscriptionAction extends from:
     * a provisioned-but-unpaid subscription row can carry a future ends_at that was never paid for.
     */
    private function futureEnd(Tenant $tenant, ?Subscription $subscription): ?CarbonImmutable
    {
        $end = $tenant->subscription_ends_at;

        return $end !== null && $end->isFuture() ? CarbonImmutable::instance($end) : null;
    }

    /**
     * SELL-06: an unpaid plan / renewal / upgrade invoice already exists (checked after the tenant
     * row lock, so two issuers cannot both pass). Paying two of them would extend the term twice.
     */
    private function assertNoOpenPlanInvoice(Tenant $tenant): void
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

    private function assertNotDuplicate(Tenant $tenant, BillingInvoiceType $type, CarbonImmutable $periodStart, bool $noFutureEnd): void
    {
        $query = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', $type->value)
            ->where('status', '!=', BillingInvoiceStatus::Void->value);

        if ($type === BillingInvoiceType::Renewal && $noFutureEnd) {
            // Expired / never-paid shop: the period start is "today", which would let a new invoice
            // through every day. ANY unpaid renewal invoice counts, whatever its day.
            $query->where(fn ($q) => $q
                ->where('status', BillingInvoiceStatus::Pending->value)
                ->orWhere('period_start', $periodStart));
        } else {
            $query->where('period_start', $periodStart);
        }

        $existing = $query->orderBy('id')->first();

        if ($existing instanceof BillingInvoice) {
            throw BillingException::duplicateInvoice($existing);
        }
    }
}
