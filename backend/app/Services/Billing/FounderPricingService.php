<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\FounderPriceStep;
use App\Exceptions\Billing\FounderPricingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSequence;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Founder pricing (ENTI-1.7): the first N paying customers (config billing.founder.slots,
 * 50) get a price schedule measured from their first verified payment (PRICE-2026-11, owner
 * decision 2026-11-15):
 *  - FounderPriceStep::Founder for billing.founder.months (12): plans.founder_price_*; its end
 *    is subscriptions.founder_price_until;
 *  - FounderPriceStep::Transition for billing.founder.transition_months (12) more:
 *    plans.founder_transition_price_*; its end is derived (transitionUntil()), never stored;
 *  - FounderPriceStep::List afterwards (and for every non-founder): the plan list price.
 * A step whose price is missing on the plan for the cycle falls through to the list price. A
 * step needs both its calendar window AND its count of billed months (stepFor(), review M2).
 * This class is the single source of truth for the step and its price (stepFor(), stepPrice(),
 * planPrice(), nextStepChange()); invoices price a cycle by the step at the cycle START.
 *
 * Q-E4 [CTO-2026-10-08]:
 * - a slot is taken at the tenant's FIRST verified payment, not at signup or trial;
 * - a slot is never given back: cancelling / expiring does not decrement the counter
 *   (there is deliberately no release method);
 * - the founder price replaces the PLAN price only; add-ons always pay their full price,
 *   so callers price add-on lines with Addon::unitPriceFor(), never with this service.
 *
 * The slot counter is the row ('founder_slot', '') of central `billing_sequences`, read
 * with lockForUpdate() inside the CALLER's central transaction (ActivateSubscriptionAction,
 * ENTI-3.x): the slot is consumed only if the activation commits, and concurrent
 * activations queue on the row, so slot N+1 is never handed out. The count check and the
 * increment both happen while that lock is held; claimedSlots() / remainingSlots() /
 * hasAvailableSlot() are unlocked reads for display only and must never gate a claim.
 * Proven on MySQL with parallel processes by FounderPricingServiceTest (group `mysql`).
 *
 * Lock order inside claim(): subscription row, then counter row. Callers that also lock
 * the subscription must lock it before calling claim() (same order, no deadlock).
 */
final class FounderPricingService
{
    /** billing_sequences key of the founder slot counter (period ''). */
    public const SLOT_SEQUENCE = 'founder_slot';

    private const SLOT_PERIOD = '';

    /** Payments that prove the tenant already paid once (a refund does not undo "paid before"). */
    private const PAID_STATUSES = [BillingPaymentStatus::Verified, BillingPaymentStatus::Refunded];

    public function totalSlots(): int
    {
        $slots = config('billing.founder.slots');

        if (! is_int($slots) || $slots < 0) {
            throw FounderPricingException::invalidConfiguration('billing.founder.slots');
        }

        return $slots;
    }

    public function durationMonths(): int
    {
        $months = config('billing.founder.months');

        if (! is_int($months) || $months < 1) {
            throw FounderPricingException::invalidConfiguration('billing.founder.months');
        }

        return $months;
    }

    /** Months of the transition step after the year-1 founder price (0 = no transition step). */
    public function transitionMonths(): int
    {
        $months = config('billing.founder.transition_months');

        if (! is_int($months) || $months < 0) {
            throw FounderPricingException::invalidConfiguration('billing.founder.transition_months');
        }

        return $months;
    }

    /** Days ahead of a step change that the tenant billing screen flags it. */
    public function noticeDays(): int
    {
        $days = config('billing.founder.notice_days');

        if (! is_int($days) || $days < 0) {
            throw FounderPricingException::invalidConfiguration('billing.founder.notice_days');
        }

        return $days;
    }

    /** Slots handed out so far (a plain read for display; claim() re-reads under lock). */
    public function claimedSlots(): int
    {
        return (int) BillingSequence::query()
            ->where('key', self::SLOT_SEQUENCE)
            ->where('period', self::SLOT_PERIOD)
            ->value('last_value');
    }

    public function remainingSlots(): int
    {
        return max(0, $this->totalSlots() - $this->claimedSlots());
    }

    public function hasAvailableSlot(): bool
    {
        return $this->remainingSlots() > 0;
    }

    /**
     * The plan has a positive founder price for this cycle. A NULL founder price means
     * "no founder price" for that cycle (the catalog sets yearly = 10 x monthly, 3,490 / 6,990 /
     * 10,990 since PRICE-2026-11), and biennial is never sold in Phase 1 (Q-E6). This is about
     * the year-1 founder price; the transition step is decided by stepFor().
     */
    public function isEligiblePlan(Plan $plan, BillingCycle $cycle): bool
    {
        $price = $this->founderPrice($plan, $cycle);

        return $price !== null && bccomp($price, '0', 3) > 0;
    }

    /**
     * Mark $subscription as founder-priced when the tenant's first verified payment
     * ($payment) activates it and a slot is still free. Returns whether the subscription holds
     * founder pricing afterwards (the price itself: planPrice()); false (slot 51, plan without a founder price, a tenant that
     * paid before, an expired founder window) writes nothing.
     *
     * - Same subscription again (a retried activation): no second slot, returns true.
     * - The tenant already holds a slot on another subscription (renewal / plan change):
     *   no new slot; the subscription inherits the ORIGINAL founder_price_until while the
     *   schedule (year-1 founder price + transition) is not over (it runs from the first
     *   payment, not per renewal).
     *
     * Must run inside an open transaction on the central connection.
     *
     * @throws FounderPricingException on a caller or configuration error, before anything is written
     */
    public function claim(Subscription $subscription, BillingPayment $payment): bool
    {
        $connection = (new BillingSequence)->getConnection();
        if ($connection->transactionLevel() < 1) {
            throw FounderPricingException::transactionRequired();
        }

        $totalSlots = $this->totalSlots();
        $months = $this->durationMonths();

        if ((string) $payment->tenant_id !== (string) $subscription->tenant_id) {
            throw FounderPricingException::paymentTenantMismatch();
        }
        if ($payment->status !== BillingPaymentStatus::Verified) {
            throw FounderPricingException::paymentNotVerified();
        }

        /** @var Subscription $locked */
        $locked = Subscription::query()->whereKey($subscription->getKey())->lockForUpdate()->firstOrFail();

        if ($locked->is_founder) {
            $subscription->setRawAttributes($locked->getAttributes(), true);

            return true;
        }

        // Every claim serialises on the counter row, so the "already a founder / paid before"
        // reads below cannot race another activation of the same tenant.
        $counter = $this->lockCounter();
        $now = Carbon::now();

        $held = $this->heldFounderWindow($locked);
        if ($held !== null) {
            // The schedule (founder, then transition) runs from the FIRST payment: a new
            // subscription inherits it while any discounted step is still ahead.
            if ($held->copy()->addMonthsNoOverflow($this->transitionMonths())->lessThanOrEqualTo($now)) {
                return false;
            }

            return $this->markFounder($subscription, $locked, $held);
        }

        if (! $this->isFirstPayment($payment)) {
            return false;
        }

        $plan = Plan::query()->find($locked->plan_id);
        if (! $plan instanceof Plan || ! $this->isEligiblePlan($plan, $locked->billing_cycle)) {
            return false;
        }

        if ($counter->last_value >= $totalSlots) {
            return false;
        }

        $counter->last_value = $counter->last_value + 1;
        $counter->save();

        $start = $payment->verified_at ?? $now;

        return $this->markFounder($subscription, $locked, Carbon::instance($start)->addMonthsNoOverflow($months));
    }

    /** The year-1 founder price applies to this subscription at $at (default now). */
    public function isFounderPriceActive(Subscription $subscription, ?CarbonInterface $at = null): bool
    {
        return $this->priceStep($subscription, $at) === FounderPriceStep::Founder;
    }

    /**
     * End of the transition step (founder_price_until + transition_months), or null for a
     * subscription that never held a founder slot. Derived, so it follows the config.
     */
    public function transitionUntil(Subscription $subscription): ?Carbon
    {
        if (! $subscription->is_founder || $subscription->founder_price_until === null) {
            return null;
        }

        return $subscription->founder_price_until->copy()->addMonthsNoOverflow($this->transitionMonths());
    }

    /** Step of $subscription at $at (default now) on its own plan and cycle. */
    public function priceStep(Subscription $subscription, ?CarbonInterface $at = null): FounderPriceStep
    {
        $plan = $subscription->plan;

        if (! $plan instanceof Plan) {
            return FounderPriceStep::List;
        }

        return $this->stepFor($subscription, $plan, $subscription->billing_cycle, $at);
    }

    /**
     * Step of $subscription's founder schedule for a billing cycle STARTING at $at (default now),
     * billed on $plan at $cycle (a plan change keeps the schedule, it only changes which prices
     * apply). A step the plan has no positive price for, at that cycle, falls through to List.
     *
     * Review M2: a step needs BOTH conditions, so a monthly term chain that drifts (paid Jan 31,
     * renewals on the 28th because addMonthNoOverflow clamps) still gets exactly 12 founder and 12
     * transition cycles:
     *  - calendar: $at is before the end of the step (founder_price_until / transitionUntil), so a
     *    shop that stops paying cannot "save" founder months for later;
     *  - billed months: fewer than `months` (resp. `months + transition_months`) schedule months
     *    were billed before $at (billedScheduleMonths(), monthly = 1, yearly = 12).
     * $billedMonths lets a caller that simulates future cycles pass the count it already knows.
     */
    public function stepFor(Subscription $subscription, Plan $plan, BillingCycle $cycle, ?CarbonInterface $at = null, ?int $billedMonths = null): FounderPriceStep
    {
        if (! $subscription->is_founder || $subscription->founder_price_until === null) {
            return FounderPriceStep::List;
        }

        $at ??= Carbon::now();
        $billedMonths ??= $this->billedScheduleMonths($subscription, $at);
        $founderMonths = $this->durationMonths();

        if ($subscription->founder_price_until->greaterThan($at) && $billedMonths < $founderMonths) {
            return $this->isEligiblePlan($plan, $cycle) ? FounderPriceStep::Founder : FounderPriceStep::List;
        }

        $transitionUntil = $this->transitionUntil($subscription);
        if ($transitionUntil !== null
            && $transitionUntil->greaterThan($at)
            && $billedMonths < $founderMonths + $this->transitionMonths()
            && $this->hasTransitionPrice($plan, $cycle)) {
            return FounderPriceStep::Transition;
        }

        return FounderPriceStep::List;
    }

    /**
     * Schedule months already billed to the tenant before $before: every plan / renewal / upgrade
     * invoice that is not void or refunded, whose period starts before $before (monthly = 1 month,
     * yearly = 12). A support "difference" invoice keeps the term, so it bills no month. The
     * schedule starts at the first verified payment, so every such invoice belongs to it.
     */
    public function billedScheduleMonths(Subscription $subscription, CarbonInterface $before): int
    {
        $invoices = BillingInvoice::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->whereIn('type', [BillingInvoiceType::Plan->value, BillingInvoiceType::Renewal->value, BillingInvoiceType::Upgrade->value])
            ->whereIn('status', [BillingInvoiceStatus::Draft->value, BillingInvoiceStatus::Pending->value, BillingInvoiceStatus::Paid->value])
            ->whereNotNull('period_start')
            ->where('period_start', '<', $before)
            ->get(['id', 'billing_cycle', 'lines']);

        $months = 0;
        foreach ($invoices as $invoice) {
            if ($this->isDifferenceInvoice($invoice)) {
                continue;
            }
            $months += $invoice->billing_cycle === BillingCycle::Yearly ? 12 : 1;
        }

        return $months;
    }

    /**
     * Price of one $cycle of $plan at $step (DECIMAL(12,3) string). A step without a
     * positive price on the plan gives the list price.
     *
     * @throws FounderPricingException for a cycle that is not sold (biennial, Q-E6)
     */
    public function stepPrice(Plan $plan, BillingCycle $cycle, FounderPriceStep $step): string
    {
        if (! $cycle->isSellable()) {
            throw FounderPricingException::cycleNotSellable($cycle);
        }

        $price = match ($step) {
            FounderPriceStep::Founder => $this->founderPrice($plan, $cycle),
            FounderPriceStep::Transition => $this->transitionPrice($plan, $cycle),
            FounderPriceStep::List => null,
        };

        if ($price === null || bccomp($price, '0', 3) <= 0) {
            $price = $cycle === BillingCycle::Yearly ? $plan->price_yearly : $plan->price_monthly;
        }

        return bcadd($price, '0', 3);
    }

    /**
     * Plan price for one cycle of $subscription at $at (DECIMAL(12,3) string): the price of
     * the step in force at $at. Plan line only; add-ons are always full price (Q-E4).
     *
     * @throws FounderPricingException for a cycle that is not sold (biennial, Q-E6)
     */
    public function planPrice(Subscription $subscription, ?CarbonInterface $at = null): string
    {
        $cycle = $subscription->billing_cycle;
        if (! $cycle->isSellable()) {
            throw FounderPricingException::cycleNotSellable($cycle);
        }

        /** @var Plan $plan */
        $plan = $subscription->plan()->firstOrFail();

        return $this->stepPrice($plan, $cycle, $this->stepFor($subscription, $plan, $cycle, $at));
    }

    /**
     * Step of the cycle running at $at (default now): the step of the latest billed cycle that
     * started at or before $at, else the step of a cycle starting at $at.
     */
    public function currentStep(Subscription $subscription, ?CarbonInterface $at = null): FounderPriceStep
    {
        $plan = $subscription->plan;
        if (! $plan instanceof Plan) {
            return FounderPriceStep::List;
        }

        $at ??= Carbon::now();
        $start = BillingInvoice::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->whereIn('type', [BillingInvoiceType::Plan->value, BillingInvoiceType::Renewal->value, BillingInvoiceType::Upgrade->value])
            ->where('status', BillingInvoiceStatus::Paid->value)
            ->whereNotNull('period_start')
            ->where('period_start', '<=', $at)
            ->max('period_start');

        return $this->stepFor($subscription, $plan, $subscription->billing_cycle, $start === null ? $at : Carbon::parse((string) $start));
    }

    /**
     * The first FUTURE billing cycle priced at another step than the current one, and when it
     * starts (= the date the invoice price actually changes): simulated on the same term chain as
     * ActivateSubscriptionAction (from the paid end, addMonthNoOverflow / addYearNoOverflow), on
     * the subscription's own plan and cycle. Null when nothing changes any more.
     *
     * @return array{step: FounderPriceStep, at: Carbon}|null
     */
    public function nextStepChange(Subscription $subscription, ?CarbonInterface $at = null): ?array
    {
        $plan = $subscription->plan;
        $cycle = $subscription->billing_cycle;
        if (! $subscription->is_founder || $subscription->founder_price_until === null || ! $plan instanceof Plan || ! $cycle->isSellable()) {
            return null;
        }

        $at ??= Carbon::now();
        $current = $this->currentStep($subscription, $at);
        $paidEnd = $subscription->tenant?->subscription_ends_at;
        $start = $paidEnd !== null && $paidEnd->greaterThan($at) ? Carbon::instance($paidEnd) : Carbon::instance($at);
        $billed = $this->billedScheduleMonths($subscription, $start);
        $cycleMonths = $cycle === BillingCycle::Yearly ? 12 : 1;
        $horizon = $this->durationMonths() + $this->transitionMonths() + 12;

        for ($i = 0; $i <= $horizon; $i++) {
            $step = $this->stepFor($subscription, $plan, $cycle, $start, $billed);
            if ($step !== $current) {
                return ['step' => $step, 'at' => $start->copy()];
            }
            if ($step === FounderPriceStep::List) {
                return null;
            }
            $billed += $cycleMonths;
            $start = $cycle === BillingCycle::Yearly ? $start->copy()->addYearNoOverflow() : $start->copy()->addMonthNoOverflow();
        }

        return null;
    }

    /**
     * An upgrade ONLY (a lone difference line): it bills no schedule month. An upgrade + renewal
     * (#232, a `renewal` plan line next to the difference) bills one cycle like a renewal.
     *
     * @param  BillingInvoice  $invoice  with `lines`
     */
    private function isDifferenceInvoice(BillingInvoice $invoice): bool
    {
        $difference = false;

        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) !== 'plan') {
                continue;
            }
            if (($line['difference'] ?? false) === true) {
                $difference = true;
            } elseif (($line['renewal'] ?? false) === true) {
                return false;
            }
        }

        return $difference;
    }

    /**
     * #232 spec C: the tenant holds an agreed founder price - a founder subscription whose schedule
     * (founder step, then transition step) has not ended at $at. A plan change for such a tenant is
     * held for the owner's manual review instead of being invoiced automatically.
     */
    public function hasAgreedFounderPrice(string $tenantId, ?CarbonInterface $at = null): bool
    {
        $at ??= Carbon::now();

        $founders = Subscription::query()
            ->where('tenant_id', $tenantId)
            ->where('is_founder', true)
            ->whereNotNull('founder_price_until')
            ->get();

        foreach ($founders as $subscription) {
            $until = $this->transitionUntil($subscription);
            if ($until !== null && $until->greaterThan($at)) {
                return true;
            }
        }

        return false;
    }

    private function founderPrice(Plan $plan, BillingCycle $cycle): ?string
    {
        $price = match ($cycle) {
            BillingCycle::Monthly => $plan->founder_price_monthly,
            BillingCycle::Yearly => $plan->founder_price_yearly,
            BillingCycle::Biennial => null,
        };

        return $price;
    }

    private function transitionPrice(Plan $plan, BillingCycle $cycle): ?string
    {
        return match ($cycle) {
            BillingCycle::Monthly => $plan->founder_transition_price_monthly,
            BillingCycle::Yearly => $plan->founder_transition_price_yearly,
            BillingCycle::Biennial => null,
        };
    }

    private function hasTransitionPrice(Plan $plan, BillingCycle $cycle): bool
    {
        $price = $this->transitionPrice($plan, $cycle);

        return $price !== null && bccomp($price, '0', 3) > 0;
    }

    private function lockCounter(): BillingSequence
    {
        // The row is created by migration 2026_10_10_200530 so every claim normally takes
        // only the exclusive row lock below. The insert is a fallback for a database where
        // the row went missing; it runs only when the row is absent, because INSERT IGNORE
        // on an existing locked row takes a shared lock and could deadlock with other
        // claimers on the upgrade (same pattern as ENTI-1.6).
        $exists = BillingSequence::query()
            ->where('key', self::SLOT_SEQUENCE)
            ->where('period', self::SLOT_PERIOD)
            ->exists();

        if (! $exists) {
            $now = Carbon::now();
            (new BillingSequence)->getConnection()->table('billing_sequences')->insertOrIgnore([
                'key' => self::SLOT_SEQUENCE,
                'period' => self::SLOT_PERIOD,
                'last_value' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        /** @var BillingSequence $counter */
        $counter = BillingSequence::query()
            ->where('key', self::SLOT_SEQUENCE)
            ->where('period', self::SLOT_PERIOD)
            ->lockForUpdate()
            ->firstOrFail();

        return $counter;
    }

    /** Latest founder window already held by the tenant on another subscription, if any. */
    private function heldFounderWindow(Subscription $subscription): ?Carbon
    {
        /** @var Subscription|null $held */
        $held = Subscription::query()
            ->where('tenant_id', $subscription->tenant_id)
            ->whereKeyNot($subscription->getKey())
            ->where('is_founder', true)
            ->whereNotNull('founder_price_until')
            ->orderByDesc('founder_price_until')
            ->first();

        return $held?->founder_price_until;
    }

    /**
     * "First payment" = no other verified/refunded payment of this tenant in central
     * `billing_payments`. CTO W1 Q4 [2026-10-09]: tenants that paid BEFORE the billing
     * tables existed DO count among the 50 founders, with 12 months from their first
     * verified payment; the OPS-12 backfill records those historical payments and claims
     * their slots before new signups are activated.
     */
    private function isFirstPayment(BillingPayment $payment): bool
    {
        return ! BillingPayment::query()
            ->where('tenant_id', $payment->tenant_id)
            ->whereKeyNot($payment->getKey())
            ->whereIn('status', array_map(static fn (BillingPaymentStatus $status): string => $status->value, self::PAID_STATUSES))
            ->exists();
    }

    private function markFounder(Subscription $subscription, Subscription $locked, Carbon $until): bool
    {
        $locked->is_founder = true;
        $locked->founder_price_until = $until;
        $locked->save();

        $subscription->setRawAttributes($locked->getAttributes(), true);

        return true;
    }
}
