<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\FounderPriceStep;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Branding\PlatformBranding;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;

/**
 * #232: the ONE place a subscription invoice line is priced. IssueSubscriptionInvoiceAction (which
 * writes the invoice), IssuePlanChangeInvoiceAction (the upgrade difference) and
 * PreviewBillingChangeAction (GET /billing/change-preview) all call these methods, so the preview a
 * shop sees is, by construction, the invoice that would be issued - there is no second formula.
 *
 * Read-only: nothing here writes. Callers that write own the transaction and the row locks.
 *
 *  - planLine(): one full cycle of a plan, priced by FounderPricingService at the PERIOD START
 *    (PRICE-2026-11 founder schedule step); a first-payment founder claim only when asked for;
 *  - renewalAddonLines(): the active add-ons a renewal charges for (BILL-02);
 *  - upgradeDifference(): (new - current) x remaining days / actual period days, both prices at the
 *    subscription's own cycle (#232 spec B, ProrationCalculator);
 *  - subtotal() / tax(): bcmath scale 3, VAT = platform billing_tax_rate on the net, half-up once.
 */
final class SubscriptionInvoicePricer
{
    public function __construct(
        private readonly FounderPricingService $founder,
        private readonly PlatformBranding $platform,
        private readonly ProrationCalculator $proration,
    ) {}

    /**
     * Plan line price (PRICE-2026-11): FounderPricingService decides the step of the tenant's founder
     * schedule at the PERIOD START of the billed cycle. A tenant on a discounted step gets it
     * automatically; $useFounderPrice (refuse when unavailable) / $founderPriceIfAvailable ask for the
     * year-1 founder price with a FIRST payment that may claim a free slot.
     *
     * $listPriceOnly (#232 spec C, the plan-change path): always the LIST price, whatever step the
     * tenant's founder schedule is on, and the line is flagged `founder_excluded` so paying it never
     * claims a founder slot (ActivateSubscriptionAction). The founder flags are ignored then.
     *
     * @return array<string, mixed>
     */
    public function planLine(
        Plan $plan,
        BillingCycle $cycle,
        Tenant $tenant,
        ?Subscription $subscription,
        CarbonImmutable $periodStart,
        bool $useFounderPrice = false,
        bool $founderPriceIfAvailable = false,
        bool $listPriceOnly = false,
    ): array {
        if ($listPriceOnly) {
            $price = Decimal::normalize($this->founder->stepPrice($plan, $cycle, FounderPriceStep::List));

            return [
                'type' => 'plan',
                'plan_id' => (int) $plan->getKey(),
                'plan_name' => $plan->name,
                'billing_cycle' => $cycle->value,
                'quantity' => 1,
                'unit_price' => $price,
                'amount' => $price,
                'founder' => false,
                'price_step' => FounderPriceStep::List->value,
                'founder_excluded' => true,
            ];
        }

        $step = $subscription instanceof Subscription
            ? $this->founder->stepFor($subscription, $plan, $cycle, $periodStart)
            : FounderPriceStep::List;

        if (! $step->isDiscounted() && $useFounderPrice) {
            if (! $this->canClaimFounderPrice($plan, $cycle, $tenant, $subscription)) {
                throw BillingException::founderPriceUnavailable();
            }
            $step = FounderPriceStep::Founder;
        } elseif (! $step->isDiscounted() && $founderPriceIfAvailable && $this->canClaimFounderPrice($plan, $cycle, $tenant, $subscription)) {
            $step = FounderPriceStep::Founder;
        }

        $price = Decimal::normalize($this->founder->stepPrice($plan, $cycle, $step));

        return [
            'type' => 'plan',
            'plan_id' => (int) $plan->getKey(),
            'plan_name' => $plan->name,
            'billing_cycle' => $cycle->value,
            'quantity' => 1,
            'unit_price' => $price,
            'amount' => $price,
            'founder' => $step->isDiscounted(),
            'price_step' => $step->value,
        ];
    }

    /**
     * BILL-02: add-ons are billed on renewal. An ACTIVE add-on line is charged when its own paid
     * end falls inside the period being renewed (a yearly add-on on a monthly subscription is only
     * due once a year), priced from the tenant's plan catalog and falling back to the stored unit
     * price. A YEARLY add-on is charged one year. A MONTHLY add-on is charged for every month of the
     * renewed period (x12 on a yearly renewal): `unit_price` is then the per-unit price of the whole
     * period, `cycle_unit_price` the monthly one and `months` the count, so quantity x unit_price =
     * amount still holds. ActivateSubscriptionAction extends exactly the lines found here.
     *
     * @return list<array<string, mixed>>
     */
    public function renewalAddonLines(Subscription $subscription, Tenant $tenant, CarbonImmutable $periodStart, ?CarbonImmutable $periodEnd, BillingCycle $renewalCycle): array
    {
        if ($periodEnd === null) {
            return [];
        }

        $rows = SubscriptionAddon::query()
            ->where('subscription_id', $subscription->getKey())
            ->where('status', SubscriptionAddonStatus::Active->value)
            ->where(function ($q) use ($periodEnd): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '<=', $periodEnd);
            })
            ->orderBy('id')
            ->get();

        $lines = [];

        foreach ($rows as $row) {
            $addon = Addon::query()->find($row->addon_id);
            if (! $addon instanceof Addon || ! $addon->is_active || $row->ends_at === null) {
                continue;
            }

            $addonCycle = $row->billing_cycle->isSellable() ? $row->billing_cycle : BillingCycle::Monthly;
            $quantity = max(1, (int) $row->quantity);
            $planAddon = $tenant->plan_id === null ? null : PlanAddon::query()
                ->where('plan_id', $tenant->plan_id)
                ->where('addon_id', $addon->getKey())
                ->where('is_available', true)
                ->first();

            $unit = $planAddon instanceof PlanAddon
                ? Decimal::normalize($planAddon->unitPriceFor($addonCycle, $quantity))
                : Decimal::normalize((string) $row->unit_price);
            $months = $addonCycle === BillingCycle::Monthly ? self::monthsIn($renewalCycle) : 1;
            $periodUnit = bcmul($unit, (string) $months, 3);

            $lines[] = [
                'type' => 'addon',
                'renewal' => true,
                'addon_id' => (int) $addon->getKey(),
                'addon_key' => $addon->key,
                'description' => (string) __('billing.invoice.addon_line', ['addon' => (string) __($addon->name_key), 'cycle' => $addonCycle->label()]),
                'billing_cycle' => $addonCycle->value,
                'months' => $months,
                'quantity' => $quantity,
                'cycle_unit_price' => $unit,
                'unit_price' => $periodUnit,
                'amount' => bcmul($periodUnit, (string) $quantity, 3),
            ];
        }

        return $lines;
    }

    /**
     * #232 spec B: the upgrade difference for the rest of the running paid term,
     * (new - current) x remaining days / actual days of the current period, both prices at the
     * subscription's own cycle. Days per ProrationCalculator (remaining rounded UP to whole days on
     * the platform clock). amount '0.000' when there is no running term or no price gap.
     *
     * The current period is the term that ends at tenants.subscription_ends_at; its START is the
     * period_start of the paid full-cycle invoice that bought it (currentTermStart()), because a
     * clamped month (Jan 31 -> Feb 28, addMonthNoOverflow) cannot be rebuilt by subtracting a month
     * from the end (Feb 28 - 1 month = Jan 28 would give 31 days instead of 28). Only when no such
     * invoice exists (a legacy / hand-made term) is it end minus one cycle.
     *
     * The difference is priced when the invoice is ISSUED (remaining days counted from the issue
     * moment), not re-priced at Verify: whether it should be re-priced at the transfer moment is an
     * owner decision that is still open, so this stays the current behaviour.
     *
     * @return array{amount: string, remaining_days: int, period_days: int, old_price: string, new_price: string, cycle: BillingCycle|null, term_end: CarbonImmutable|null}
     */
    public function upgradeDifference(Tenant $tenant, ?Plan $current, Plan $target): array
    {
        $end = $tenant->subscription_ends_at;
        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->first();

        if ($end === null || ! $end->isFuture() || ! $current instanceof Plan || ! $subscription instanceof Subscription || ! $subscription->billing_cycle->isSellable()) {
            return ['amount' => '0.000', 'remaining_days' => 0, 'period_days' => 0, 'old_price' => '0.000', 'new_price' => '0.000', 'cycle' => null, 'term_end' => null];
        }

        $yearly = $subscription->billing_cycle === BillingCycle::Yearly;
        $termEnd = CarbonImmutable::instance($end)->setTimezone((string) config('app.timezone'));
        $termStart = $this->currentTermStart($tenant, $termEnd)
            ?? ($yearly ? $termEnd->subYearNoOverflow() : $termEnd->subMonthNoOverflow());
        $oldPrice = Decimal::normalize($yearly ? $current->price_yearly : $current->price_monthly);
        $newPrice = Decimal::normalize($yearly ? $target->price_yearly : $target->price_monthly);
        $remaining = $this->proration->wholeDaysUp(CarbonImmutable::now(), $termEnd);
        $periodDays = $this->proration->wholeDaysUp($termStart, $termEnd);

        return [
            'amount' => $this->proration->difference($oldPrice, $newPrice, $remaining, $periodDays),
            'remaining_days' => min($remaining, $periodDays),
            'period_days' => $periodDays,
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'cycle' => $subscription->billing_cycle,
            'term_end' => $termEnd,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    public function subtotal(array $lines): string
    {
        $subtotal = '0.000';
        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, (string) $line['amount'], 3);
        }

        return $subtotal;
    }

    /**
     * VAT = platform setting billing_tax_rate, computed ONCE on the net (subtotal - discount) and
     * rounded half-up (the SETG-13 rule).
     *
     * @return array{rate: string, tax: string, total: string}
     */
    public function tax(string $net): array
    {
        return $this->taxAt($net, $this->platform->get()->billingTaxRate);
    }

    /**
     * The same VAT rule at a given rate (an issued invoice keeps the rate it was issued with).
     *
     * @return array{rate: string, tax: string, total: string}
     */
    public function taxAt(string $net, string $rate): array
    {
        $tax = Decimal::percent($net, $rate);

        return ['rate' => $rate, 'tax' => $tax, 'total' => bcadd($net, $tax, 3)];
    }

    public function periodEnd(CarbonImmutable $start, ?BillingCycle $cycle): ?CarbonImmutable
    {
        return match ($cycle) {
            BillingCycle::Monthly => $start->addMonthNoOverflow(),
            BillingCycle::Yearly => $start->addYearNoOverflow(),
            default => null,
        };
    }

    /**
     * Start of the paid term ending at $termEnd: the period_start of the latest PAID plan / renewal /
     * upgrade invoice whose period_end is exactly that end and which bought a full cycle
     * (ActivateSubscriptionAction rewrites a full cycle's period to the term it applied; an upgrade +
     * renewal invoice carries the appended cycle). A lone upgrade difference keeps the term and is
     * skipped (its period starts at its issue moment). Null when none matches or the start is not
     * before the end.
     */
    private function currentTermStart(Tenant $tenant, CarbonImmutable $termEnd): ?CarbonImmutable
    {
        $invoices = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('type', [BillingInvoiceType::Plan->value, BillingInvoiceType::Renewal->value, BillingInvoiceType::Upgrade->value])
            ->where('status', BillingInvoiceStatus::Paid->value)
            ->whereNotNull('period_start')
            ->whereNotNull('period_end')
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'period_start', 'period_end', 'lines']);

        foreach ($invoices as $invoice) {
            if ($invoice->period_end === null || $invoice->period_start === null
                || $invoice->period_end->getTimestamp() !== $termEnd->getTimestamp()
                || self::isLoneDifference($invoice->lines)) {
                continue;
            }

            $start = CarbonImmutable::instance($invoice->period_start)->setTimezone((string) config('app.timezone'));

            return $start->lessThan($termEnd) ? $start : null;
        }

        return null;
    }

    /**
     * A lone upgrade difference (a `difference` plan line and no `renewal` plan line).
     *
     * @param  array<int, array<string, mixed>>|null  $lines
     */
    private static function isLoneDifference(?array $lines): bool
    {
        $difference = false;

        foreach ($lines ?? [] as $line) {
            if (($line['type'] ?? null) !== 'plan') {
                continue;
            }
            if (($line['renewal'] ?? false) === true) {
                return false;
            }
            if (($line['difference'] ?? false) === true) {
                $difference = true;
            }
        }

        return $difference;
    }

    /** Months in one renewal period of the subscription's cycle. */
    private static function monthsIn(BillingCycle $cycle): int
    {
        return $cycle === BillingCycle::Yearly ? 12 : 1;
    }

    /**
     * A tenant not (or no longer) on a discounted step may take the year-1 founder price only
     * with its first-ever payment, while a slot is free (FounderPricingService::claim takes
     * the slot when that payment is verified).
     */
    private function canClaimFounderPrice(Plan $plan, BillingCycle $cycle, Tenant $tenant, ?Subscription $subscription): bool
    {
        if (! $this->founder->isEligiblePlan($plan, $cycle)) {
            return false;
        }

        // A founder whose schedule has ended never starts it again.
        if ($subscription instanceof Subscription && $subscription->is_founder) {
            return false;
        }

        $paidBefore = BillingPayment::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('status', [BillingPaymentStatus::Verified->value, BillingPaymentStatus::Refunded->value])
            ->exists();

        return ! $paidBefore && $this->founder->hasAvailableSlot();
    }
}
