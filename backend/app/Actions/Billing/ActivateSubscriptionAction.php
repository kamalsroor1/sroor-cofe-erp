<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Tenants\TransitionTenantStatusAction;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\Billing\TemporaryAccessOutcome;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use App\Services\Billing\TemporaryAccessService;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
use App\Support\Money\Decimal;
use App\Support\Tenancy\TenantStatusTransition;
use App\Support\Tenancy\TenantSuspensionReason;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * PRD-03 / ENTI-3.4: apply a PAID plan / renewal / upgrade invoice to the tenant's subscription.
 *
 * Called only by VerifyManualPaymentAction, inside its central transaction (it refuses to run
 * outside one). The caller already holds the tenant row (billing lock order tenant -> payment ->
 * invoice(s) -> subscription, see VerifyManualPaymentAction); re-locking it here is a no-op.
 *
 *  - subscription: plan + cycle from the invoice, status active, price = the billed plan line,
 *    period = one month / one year from the TERM START (#232 spec A, see termBase()):
 *      - a paid term still running at the transfer moment: appended after the current paid end
 *        (a renewal never loses paid days and is never charged twice);
 *      - no paid term (trial, free plan, expired): the ACTUAL transfer moment the operator entered
 *        at Verify (`billing_payments.transferred_at`), else the confirmation moment (a gateway
 *        payment, or a legacy row). The invoice's own period_start (e.g. the SELL-06 trial end) no
 *        longer moves the start, and remaining trial days are NOT added: whether they should be is
 *        the owner's open decision (أ). The invoice period is rewritten to the term actually applied;
 *  - tenant: plan_id and subscription_ends_at follow, and the lifecycle status moves to `active`
 *    ONLY through TransitionTenantStatusAction (actor Billing) when it is not already active.
 *    A `violation` suspension is never lifted by a payment: the period is extended and the
 *    operator decides (the audit row says so). An archived or purged tenant refuses (409);
 *  - founder price window: FounderPricingService::claim (self-checking, first payment only);
 *  - entitlement cache: bumped by the model observers and once more here after commit;
 *  - audit: SubscriptionActivated.
 *
 * Support-driven shapes:
 *  - an UPGRADE ONLY (type upgrade, ONE plan line flagged `difference`, #232 spec B): the plan
 *    switches at once and the paid term END is KEPT; it needs a term still running (409 otherwise);
 *  - an UPGRADE + RENEWAL (type upgrade, the `difference` line plus a plan line flagged `renewal`):
 *    the plan switches and ONE full cycle of the new plan (the invoice cycle) is appended after the
 *    current paid end; same running-term and term-changed guards as the upgrade only;
 *  - an ADD-ON invoice (type addon): creates or tops up the `subscription_addons` line (active;
 *    a monthly line runs the months its invoice charged, capped at the subscription end, a yearly
 *    line one year from now) so the add-on counts in TenantEntitlementService. The plan, the term
 *    and the tenant status are untouched.
 *
 * BILL-01 review: a difference invoice is refused (409 difference_term_changed) unless the term it
 * priced (pricedTermEnd()) is still the tenant's subscription_ends_at: a renewal paid in between moved it.
 *
 * #232 spec C: a difference invoice and a plan-change invoice billed at the list price only
 * (`founder_excluded` lines) never claim a founder slot; every other full-cycle invoice (plan,
 * renewal, a console upgrade invoice) runs FounderPricingService::claim.
 * #232 review: a term that is already over when it is applied (an old transfer verified late) is
 * flagged `term_already_ended` on the payment's raw_payload and in the audit row.
 *
 * PLAN-10 review: a tenant on a permanent free plan holds an active 0.000 row with no paid term;
 * paying a plan invoice CLOSES that row (expired, ends_at = now) and creates a new paid row (the
 * free period stays in the history), and cancels a still-scheduled downgrade to the free plan.
 *
 * Idempotency lives in the caller (a payment is verified once); the invoice can only settle once.
 */
final class ActivateSubscriptionAction
{
    public function __construct(
        private readonly TransitionTenantStatusAction $transition,
        private readonly FounderPricingService $founder,
        private readonly TenantEntitlementService $entitlements,
        private readonly CentralAuditLogger $audit,
        private readonly TemporaryAccessService $temporaryAccess,
    ) {}

    public function execute(BillingInvoice $invoice, BillingPayment $payment, ?CentralUser $operator = null): Subscription
    {
        $connection = DB::connection((string) (new Subscription)->getConnectionName());

        if ($connection->transactionLevel() < 1) {
            throw new LogicException('ActivateSubscriptionAction must run inside a central transaction.');
        }

        $tenant = Tenant::query()->whereKey($invoice->tenant_id)->lockForUpdate()->firstOrFail();

        if ($tenant->isPurged() || $tenant->status === TenantStatus::Archived->value) {
            throw BillingException::tenantNotActivatable();
        }

        if ($invoice->type === BillingInvoiceType::Addon) {
            return $this->activateAddons($invoice, $payment, $tenant, $operator);
        }

        $plan = $this->planOf($invoice);
        $cycle = $invoice->billing_cycle;

        if (! $plan instanceof Plan || ! $cycle instanceof BillingCycle || ! $cycle->isSellable()) {
            throw BillingException::noPlan();
        }

        $now = CarbonImmutable::now();
        $currentEnd = $tenant->subscription_ends_at;
        $hasFutureEnd = $currentEnd !== null && $currentEnd->isAfter($now);
        $base = $this->termBase($currentEnd, $payment, $now);
        $difference = $this->isDifference($invoice);
        $appendsCycle = $difference && $this->appendsRenewal($invoice);

        // BILL-01: a difference prices the REST of a running term; once it ended the shop would get
        // a full cycle for a fraction of the price.
        if ($difference && ! $hasFutureEnd) {
            throw BillingException::differenceNeedsActiveTerm();
        }

        // BILL-01 review: a difference prices the term that was running when it was issued. If the
        // term moved since (a renewal was paid first), paying the old difference would upgrade the
        // NEW term for the price of the old remainder: refuse, support re-issues it for the current term.
        // ($currentEnd is non-null here: a difference without a future end was refused above.)
        $pricedEnd = $difference ? $this->pricedTermEnd($invoice) : null;
        if ($difference && ($pricedEnd === null || $pricedEnd->getTimestamp() !== $currentEnd->getTimestamp())) {
            throw BillingException::differenceTermChanged();
        }

        // BILL-01: an old renewal invoice (issued before an upgrade) must never move the shop back.
        if ($invoice->type === BillingInvoiceType::Renewal
            && $tenant->plan_id !== null
            && (int) $tenant->plan_id !== (int) $plan->getKey()) {
            throw BillingException::renewalPlanMismatch();
        }

        // Upgrade only keeps the term end; upgrade + renewal appends one cycle after it (a running
        // term was enforced above, so $base is the current end for both).
        $keepsTerm = $difference && ! $appendsCycle;
        $newEnd = $keepsTerm
            ? $base
            : ($cycle === BillingCycle::Yearly ? $base->addYearNoOverflow() : $base->addMonthNoOverflow());

        // #232 review: an old transfer verified late can buy a term that is already over (spec A
        // starts it at the transfer). The math is kept, but never silently: the payment and the
        // audit row carry `term_already_ended` and the verify response shows it to the operator.
        $termAlreadyEnded = ! $newEnd->isAfter($now);
        if ($termAlreadyEnded) {
            $payment->raw_payload = array_merge(is_array($payment->raw_payload) ? $payment->raw_payload : [], ['term_already_ended' => true]);
            $payment->save();
        }

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        // PLAN-10 review: the 0.000 row of a permanent free plan is history, never overwritten: close
        // it at now and start a new paid row, so the free period stays visible.
        if ($subscription instanceof Subscription && $subscription->isFreePlanRow()) {
            $subscription->status = SubscriptionStatus::Expired;
            $subscription->ends_at = Carbon::instance($now);
            $subscription->save();
            $subscription = null;
        }

        $wasPaying = $subscription instanceof Subscription && $subscription->status === SubscriptionStatus::Active;
        $subscription ??= new Subscription(['tenant_id' => (string) $tenant->getKey()]);
        $effectiveCycle = $keepsTerm && $subscription->exists && $subscription->billing_cycle->isSellable()
            ? $subscription->billing_cycle
            : $cycle;
        $keptTermAmount = Decimal::normalize($this->founder->stepPrice(
            $plan,
            $effectiveCycle,
            $this->founder->stepFor($subscription, $plan, $effectiveCycle, $now),
        ));

        $subscription->fill([
            'plan_id' => $plan->getKey(),
            // A kept term keeps its cycle: the end date is still the old cycle's end. An appended
            // cycle (upgrade + renewal) moves to the invoice cycle like any plan invoice.
            'billing_cycle' => $effectiveCycle,
            'status' => SubscriptionStatus::Active,
            // An upgrade only bills the gap: the subscription price tracks the plan's effective
            // schedule price (founder/transition/list). With a renewal, the full-cycle line on the
            // new plan is the price.
            'amount' => $keepsTerm ? $keptTermAmount : $this->planAmount($invoice),
            'currency' => $invoice->currency,
            'starts_at' => $wasPaying ? $subscription->starts_at : $base,
            'ends_at' => $newEnd,
            'cancelled_at' => null,
            'payment_method' => 'manual',
        ]);
        $subscription->save();

        // Keeps the invoice pointing at the subscription it activated (older rows may have none).
        if ($invoice->subscription_id === null) {
            $invoice->subscription_id = $subscription->getKey();
        }
        // #232 spec A: a full cycle's invoice states the term it actually bought (e.g. a trial-end
        // invoice paid early starts at the transfer, not at the trial end). Money is never touched.
        if (! $difference) {
            $invoice->period_start = Carbon::instance($base);
            $invoice->period_end = Carbon::instance($newEnd);
        }
        if ($invoice->isDirty()) {
            $invoice->save();
        }

        $tenant->plan_id = (int) $plan->getKey();
        $tenant->subscription_ends_at = Carbon::instance($newEnd);
        $tenant->save();

        if ($invoice->type === BillingInvoiceType::Renewal) {
            // BILL-02: add-ons renew only when the renewal invoice charged for them.
            $this->extendPaidAddons($invoice, $subscription, $now, $newEnd);
        } elseif (! $keepsTerm) {
            // Plan / upgrade-after-expiry invoices still carry running add-ons (owner decision pending).
            $this->carryAddonsForward($subscription, $currentEnd, $now, $newEnd);
        }

        if ($invoice->type !== BillingInvoiceType::Renewal) {
            $this->voidStaleRenewals($invoice, $tenant, $plan, $operator);
        }

        $this->completePlanChangeRequest($invoice);
        $this->cancelScheduledDowngrade($tenant, $invoice, $operator);

        $statusKept = $this->keepsStatus($tenant);

        if (! $statusKept && $tenant->status !== TenantStatus::Active->value) {
            $this->transition->execute(TenantStatusTransition::to(
                (string) $tenant->getKey(),
                TenantStatus::Active,
                TenantLifecycleActor::Billing,
                context: [
                    'billing_invoice_id' => (int) $invoice->getKey(),
                    'billing_payment_id' => (int) $payment->getKey(),
                ],
                causer: $operator,
            ));
        }

        // #232 spec C + review: a difference invoice (a lone difference, or difference + renewal) and a
        // plan-change invoice billed at the list price only (`founder_excluded` lines) never claim a
        // founder slot. Every other full-cycle invoice does, including a console upgrade invoice
        // billed at the founder price (`use_founder_price`).
        if (! $difference && ! $this->isFounderExcluded($invoice)) {
            $this->founder->claim($subscription, $payment);
        }

        $tenantId = (string) $tenant->getKey();
        $connection->afterCommit(fn () => $this->entitlements->forget($tenantId));

        $this->audit->record(
            CentralAuditEvent::SubscriptionActivated,
            [
                'invoice_id' => (int) $invoice->getKey(),
                'invoice_number' => $invoice->number,
                'payment_id' => (int) $payment->getKey(),
                'plan_id' => (int) $plan->getKey(),
                'billing_cycle' => $cycle->value,
                'period_end' => $newEnd->toIso8601String(),
                'kind' => $appendsCycle ? 'upgrade_with_renewal' : ($difference ? 'upgrade_difference' : 'plan'),
                'term_start' => $base->toIso8601String(),
                'transferred_at' => $payment->transferred_at?->toIso8601String(),
                'term_kept' => $keepsTerm,
                'term_already_ended' => $termAlreadyEnded,
                'status_kept_for_violation' => $statusKept,
            ],
            $operator,
            $subscription,
            $tenantId,
        );

        return $subscription;
    }

    /**
     * @param  Tenant  $tenant  the tenant row, already locked by execute()
     */
    private function activateAddons(BillingInvoice $invoice, BillingPayment $payment, Tenant $tenant, ?CentralUser $operator): Subscription
    {
        $cycle = $invoice->billing_cycle;

        if (! $cycle instanceof BillingCycle || ! $cycle->isSellable()) {
            throw BillingException::noPlan();
        }

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $subscription instanceof Subscription) {
            throw BillingException::addonNeedsSubscription();
        }

        $now = CarbonImmutable::now();
        $end = $tenant->subscription_ends_at;
        // BILL-02: a YEARLY add-on lasts its own year. A MONTHLY one lasts the months its invoice line
        // charged (`months`, see IssueSubscriptionInvoiceAction::addonMonths), never past the
        // subscription end: it rides the term only as far as it was paid for.
        $months = $this->paidMonths($invoice);
        if ($cycle === BillingCycle::Yearly) {
            $endsAt = $now->addYearNoOverflow();
        } else {
            $endsAt = $now->addMonthsNoOverflow($months);
            if ($end !== null && $end->isAfter($now) && $endsAt->isAfter($end)) {
                $endsAt = CarbonImmutable::instance($end);
            }
        }

        $applied = [];

        foreach ($invoice->lines as $row) {
            if (($row['type'] ?? null) !== 'addon') {
                continue;
            }

            $addon = Addon::query()->find((int) ($row['addon_id'] ?? 0));
            if (! $addon instanceof Addon) {
                throw BillingException::requestAddonUnavailable();
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 1));

            $line = SubscriptionAddon::query()
                ->where('subscription_id', $subscription->getKey())
                ->where('addon_id', $addon->getKey())
                ->where('status', SubscriptionAddonStatus::Active->value)
                ->lockForUpdate()
                ->first();

            // Top-up keeps the line's existing unit_price (earlier units are not re-priced); the price
            // of THIS purchase is on its invoice line. Only a new line takes the invoice unit_price.
            if ($line instanceof SubscriptionAddon) {
                $line->quantity += $quantity;
            } else {
                $line = new SubscriptionAddon([
                    'subscription_id' => $subscription->getKey(),
                    'addon_id' => $addon->getKey(),
                    'quantity' => $quantity,
                    // The per-cycle unit price, not the line's unit_price for its whole period (BILL-02).
                    'unit_price' => Decimal::normalize((string) ($row['cycle_unit_price'] ?? $row['unit_price'] ?? '0')),
                    'billing_cycle' => $cycle,
                ]);
            }

            $line->fill([
                'status' => SubscriptionAddonStatus::Active,
                'starts_at' => $line->starts_at ?? $now,
                'ends_at' => $line->ends_at !== null && $line->ends_at->isAfter($endsAt) ? $line->ends_at : $endsAt,
            ]);
            $line->save();

            $applied[] = ['addon_id' => (int) $addon->getKey(), 'key' => $addon->key, 'quantity' => (int) $line->quantity];
        }

        if ($applied === []) {
            throw BillingException::noPlan();
        }

        if ($invoice->subscription_id === null) {
            $invoice->subscription_id = $subscription->getKey();
            $invoice->save();
        }

        $tenantId = (string) $tenant->getKey();
        DB::connection((string) (new Subscription)->getConnectionName())->afterCommit(fn () => $this->entitlements->forget($tenantId));

        $this->audit->record(
            CentralAuditEvent::SubscriptionActivated,
            [
                'invoice_id' => (int) $invoice->getKey(),
                'invoice_number' => $invoice->number,
                'payment_id' => (int) $payment->getKey(),
                'kind' => 'addon',
                'billing_cycle' => $cycle->value,
                'addons' => $applied,
                'ends_at' => $endsAt->toIso8601String(),
            ],
            $operator,
            $subscription,
            $tenantId,
        );

        return $subscription;
    }

    /**
     * A paid add-on must not silently expire on renewal day: every ACTIVE line still running, or
     * ending exactly at the old subscription end, moves to the new end.
     * NOTE (pending owner decision): renewal invoices do not charge for add-ons yet; add-ons are
     * billed as separate invoices, so this only keeps what was already paid for alive.
     */
    private function carryAddonsForward(Subscription $subscription, ?\DateTimeInterface $oldEnd, CarbonImmutable $now, CarbonImmutable $newEnd): void
    {
        $lines = SubscriptionAddon::query()
            ->where('subscription_id', $subscription->getKey())
            ->where('status', SubscriptionAddonStatus::Active->value)
            ->where(function ($q) use ($oldEnd, $now): void {
                $q->where('ends_at', '>=', $now);
                if ($oldEnd !== null) {
                    $q->orWhere('ends_at', $oldEnd);
                }
            })
            ->lockForUpdate()
            ->get();

        foreach ($lines as $line) {
            if ($line->ends_at === null || $line->ends_at->lt($newEnd)) {
                $line->ends_at = Carbon::instance($newEnd);
                $line->save();
            }
        }
    }

    /**
     * BILL-02: extend every add-on line the paid renewal invoice charged. A YEARLY line gets one more
     * year from the later of its current end and now. A MONTHLY line was charged for every month of
     * the renewed period, so it runs to the new subscription end ($newEnd). Lines not on the invoice
     * are left to lapse.
     */
    private function extendPaidAddons(BillingInvoice $invoice, Subscription $subscription, CarbonImmutable $now, CarbonImmutable $newEnd): void
    {
        foreach ($invoice->lines as $row) {
            if (($row['type'] ?? null) !== 'addon' || ! isset($row['addon_id'])) {
                continue;
            }

            $line = SubscriptionAddon::query()
                ->where('subscription_id', $subscription->getKey())
                ->where('addon_id', (int) $row['addon_id'])
                ->where('status', SubscriptionAddonStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if (! $line instanceof SubscriptionAddon) {
                continue;
            }

            $from = $line->ends_at !== null && $line->ends_at->isAfter($now) ? CarbonImmutable::instance($line->ends_at) : $now;
            $addonCycle = BillingCycle::tryFrom((string) ($row['billing_cycle'] ?? '')) ?? BillingCycle::Monthly;

            if ($addonCycle === BillingCycle::Yearly) {
                $line->ends_at = Carbon::instance($from->addYearNoOverflow());
            } elseif ($line->ends_at === null || $line->ends_at->lt($newEnd)) {
                $line->ends_at = Carbon::instance($newEnd);
            }
            $line->save();
        }
    }

    /**
     * BILL-01: once the plan changed, a still-pending renewal invoice of ANOTHER plan is stale (paying
     * it would be refused with 409): void it so a correct renewal can be issued. Invoices with a
     * payment in flight are left alone (the payment review decides).
     */
    private function voidStaleRenewals(BillingInvoice $paid, Tenant $tenant, Plan $plan, ?CentralUser $operator): void
    {
        $stale = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', BillingInvoiceType::Renewal->value)
            ->where('status', BillingInvoiceStatus::Pending->value)
            ->whereKeyNot($paid->getKey())
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($stale as $old) {
            if ($this->planOf($old)?->getKey() === $plan->getKey()) {
                continue;
            }

            $inFlight = BillingPayment::query()
                ->where('billing_invoice_id', $old->getKey())
                ->whereIn('status', [BillingPaymentStatus::Verified->value, BillingPaymentStatus::Pending->value])
                ->exists();

            if ($inFlight) {
                continue;
            }

            $old->status = BillingInvoiceStatus::Void;
            $old->voided_at = now();
            $old->save();

            // #232 spec D: a voided invoice never keeps an unsettled 48h window.
            $this->temporaryAccess->settle($tenant, $old, TemporaryAccessOutcome::Voided, $operator, CarbonImmutable::now());

            $this->audit->record(
                CentralAuditEvent::BillingInvoiceVoided,
                ['invoice_id' => (int) $old->getKey(), 'number' => $old->number, 'type' => $old->type->value, 'reason' => 'plan_changed'],
                $operator,
                $old,
                (string) $old->tenant_id,
            );
        }
    }

    /** BILL-01: a paid plan-change invoice completes the request that produced it. */
    private function completePlanChangeRequest(BillingInvoice $invoice): void
    {
        PlanChangeRequest::query()
            ->where('billing_invoice_id', $invoice->getKey())
            ->where('status', PlanChangeRequestStatus::Invoiced->value)
            ->update(['status' => PlanChangeRequestStatus::Completed->value]);
    }

    /**
     * PLAN-10 review: paying for a paid plan term means the shop stays on a paid plan, so a downgrade
     * to the free plan it scheduled earlier is cancelled (else the sweep would move a shop that just
     * paid to the free plan at the end of the new term). Audited like a shop cancellation.
     */
    private function cancelScheduledDowngrade(Tenant $tenant, BillingInvoice $invoice, ?CentralUser $operator): void
    {
        $scheduled = PlanChangeRequest::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', PlanChangeRequestStatus::Scheduled->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($scheduled as $request) {
            $request->status = PlanChangeRequestStatus::Cancelled;
            $request->save();

            $this->audit->record(
                CentralAuditEvent::PlanChangeCancelled,
                ['request_id' => (int) $request->getKey(), 'kind' => $request->kind->value, 'was' => PlanChangeRequestStatus::Scheduled->value, 'reason' => 'paid_plan_invoice', 'invoice_id' => (int) $invoice->getKey()],
                $operator,
                $request,
                (string) $tenant->getKey(),
            );
        }
    }

    /**
     * BILL-02: the months a MONTHLY add-on invoice charged (the largest `months` of its add-on lines,
     * at least 1; lines issued as a single month carry 1).
     */
    private function paidMonths(BillingInvoice $invoice): int
    {
        $months = 1;

        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'addon') {
                $months = max($months, (int) ($line['months'] ?? 1));
            }
        }

        return $months;
    }

    /**
     * #232 spec A: where the new term starts. The transfer moment is the operator-entered actual
     * transfer (manual) or the confirmation moment (gateway / no date). A paid term still running at
     * that moment is extended from its end (appended, no paid day lost); otherwise the term starts
     * at the transfer. Remaining trial days are not added (open owner decision أ).
     */
    private function termBase(?\DateTimeInterface $currentEnd, BillingPayment $payment, CarbonImmutable $now): CarbonImmutable
    {
        $transfer = $payment->transferred_at !== null ? CarbonImmutable::instance($payment->transferred_at) : $now;

        if ($currentEnd !== null && $currentEnd > $transfer) {
            return CarbonImmutable::instance($currentEnd);
        }

        return $transfer;
    }

    /**
     * The paid-term end a difference invoice priced: its difference line's `term_end` (#232), else
     * (invoices issued before that) the invoice period_end, which was that end.
     */
    private function pricedTermEnd(BillingInvoice $invoice): ?CarbonImmutable
    {
        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan' && ($line['difference'] ?? false) === true && is_string($line['term_end'] ?? null)) {
                return CarbonImmutable::parse($line['term_end']);
            }
        }

        return $invoice->period_end === null ? null : CarbonImmutable::instance($invoice->period_end);
    }

    /** #232 spec B: the upgrade invoice also carries a full cycle of the new plan after the current end. */
    private function appendsRenewal(BillingInvoice $invoice): bool
    {
        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan' && ($line['renewal'] ?? false) === true && ($line['difference'] ?? false) !== true) {
                return true;
            }
        }

        return false;
    }

    /** #232 spec C: a plan line billed by the plan-change path at the list price only. */
    private function isFounderExcluded(BillingInvoice $invoice): bool
    {
        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan' && ($line['founder_excluded'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /** True for the single "difference" plan line a support-decided upgrade carries. */
    private function isDifference(BillingInvoice $invoice): bool
    {
        if ($invoice->type !== BillingInvoiceType::Upgrade) {
            return false;
        }

        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan' && ($line['difference'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /** A payment does not lift a suspension imposed for a violation: the operator decides. */
    private function keepsStatus(Tenant $tenant): bool
    {
        return $tenant->status === TenantStatus::Suspended->value
            && $tenant->suspension_reason === TenantSuspensionReason::Violation;
    }

    private function planOf(BillingInvoice $invoice): ?Plan
    {
        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan' && isset($line['plan_id'])) {
                return Plan::query()->find((int) $line['plan_id']);
            }
        }

        return null;
    }

    /** The full-cycle plan line's amount (never the upgrade difference line). */
    private function planAmount(BillingInvoice $invoice): string
    {
        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan' && ($line['difference'] ?? false) !== true) {
                return bcadd((string) ($line['amount'] ?? '0'), '0', 3);
            }
        }

        return '0.000';
    }
}
