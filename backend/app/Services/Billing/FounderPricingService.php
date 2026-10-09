<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingPaymentStatus;
use App\Exceptions\Billing\FounderPricingException;
use App\Models\BillingPayment;
use App\Models\BillingSequence;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Founder pricing (ENTI-1.7): the first N paying customers (config billing.founder.slots,
 * 50) keep the plan's founder price for M months (billing.founder.months, 12).
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
     * "no founder price" for that cycle (the approved catalog sets yearly = 10 x monthly,
     * 2990 / 5990 / 9990, CTO W1 Q4), and biennial is never sold in Phase 1 (Q-E6).
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
     *   no new slot; the subscription inherits the ORIGINAL founder_price_until while it is
     *   still in the future (the 12 months run from the first payment, not per renewal).
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
            if ($held->lessThanOrEqualTo($now)) {
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

    /** The founder price applies to this subscription at $at (default now). */
    public function isFounderPriceActive(Subscription $subscription, ?CarbonInterface $at = null): bool
    {
        if (! $subscription->is_founder || $subscription->founder_price_until === null) {
            return false;
        }

        $at ??= Carbon::now();
        if (! $subscription->founder_price_until->greaterThan($at)) {
            return false;
        }

        $plan = $subscription->plan;

        return $plan instanceof Plan && $this->isEligiblePlan($plan, $subscription->billing_cycle);
    }

    /**
     * Plan price for one cycle of $subscription at $at (DECIMAL(12,3) string): the founder
     * price inside the founder window, the regular plan price after it. Plan line only;
     * add-ons are always full price (Q-E4).
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

        if ($this->isFounderPriceActive($subscription, $at)) {
            return bcadd((string) $this->founderPrice($plan, $cycle), '0', 3);
        }

        return bcadd($cycle === BillingCycle::Yearly ? $plan->price_yearly : $plan->price_monthly, '0', 3);
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
