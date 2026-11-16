<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\FounderPriceStep;
use App\Enums\Billing\SubscriptionStatus;
use App\Models\BillingInvoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Review M2: the founder schedule follows the BILLED term chain. Paid on Jan 31, monthly renewals
 * drift to the 28th (addMonthNoOverflow), yet exactly 12 cycles are founder-priced, 12 transition
 * and the rest list; yearly: 1 / 1 / rest. next_step_at is the start of the first cycle billed at
 * the new price.
 */
final class FounderScheduleTermChainTest extends BillingTestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function startDates(): array
    {
        return [
            'Jan 31' => ['2026-01-31 10:00:00'],
            'Jan 29' => ['2026-01-29 10:00:00'],
            'Feb 28 (common year)' => ['2027-02-28 10:00:00'],
            'Feb 29 (leap year)' => ['2028-02-29 10:00:00'],
        ];
    }

    #[DataProvider('startDates')]
    public function test_a_monthly_chain_gets_exactly_twelve_founder_and_twelve_transition_cycles(string $start): void
    {
        [$subscription] = $this->founder(Carbon::parse($start), BillingCycle::Monthly);

        $steps = $this->billChain($subscription, Carbon::parse($start), BillingCycle::Monthly, 30);

        $this->assertSame(array_merge(
            array_fill(0, 12, 'founder'),
            array_fill(0, 12, 'transition'),
            array_fill(0, 6, 'list'),
        ), $steps);
    }

    #[DataProvider('startDates')]
    public function test_a_yearly_chain_gets_one_founder_and_one_transition_year(string $start): void
    {
        [$subscription] = $this->founder(Carbon::parse($start), BillingCycle::Yearly);

        $steps = $this->billChain($subscription, Carbon::parse($start), BillingCycle::Yearly, 4);

        $this->assertSame(['founder', 'transition', 'list', 'list'], $steps);
    }

    public function test_next_step_at_is_the_start_of_the_first_cycle_billed_at_the_new_price(): void
    {
        $start = Carbon::parse('2026-01-31 10:00:00');
        [$subscription, $tenant] = $this->founder($start, BillingCycle::Monthly);

        // 11 cycles billed and paid (Jan 31 .. Nov 28 on the drifting chain); the paid end is the 12th start.
        $starts = $this->chainStarts($start, BillingCycle::Monthly, 13);
        foreach (array_slice($starts, 0, 11) as $periodStart) {
            $this->paidInvoice($tenant, $periodStart, BillingCycle::Monthly);
        }
        $tenant->forceFill(['subscription_ends_at' => $starts[11]])->save();
        $this->travelTo($starts[10]->copy()->addDay());

        $service = app(FounderPricingService::class);
        $next = $service->nextStepChange($subscription->refresh());

        $this->assertSame(FounderPriceStep::Founder, $service->currentStep($subscription));
        $this->assertNotNull($next);
        $this->assertSame(FounderPriceStep::Transition, $next['step']);
        // The 13th cycle (Jan 28, 2027 on this chain) is the first transition-priced one, not Jan 31.
        $this->assertSame($starts[12]->toDateTimeString(), $next['at']->toDateTimeString());
        $this->assertSame('2027-01-28 10:00:00', $next['at']->toDateTimeString());
    }

    /**
     * Bills $count cycles on the term chain, each priced at its start, and returns their steps.
     *
     * @return list<string>
     */
    private function billChain(Subscription $subscription, Carbon $start, BillingCycle $cycle, int $count): array
    {
        $service = app(FounderPricingService::class);
        $plan = $subscription->plan()->firstOrFail();
        $tenant = Tenant::query()->findOrFail($subscription->tenant_id);
        $steps = [];

        foreach ($this->chainStarts($start, $cycle, $count) as $periodStart) {
            $steps[] = $service->stepFor($subscription, $plan, $cycle, $periodStart)->value;
            $this->paidInvoice($tenant, $periodStart, $cycle);
        }

        return $steps;
    }

    /**
     * @return list<Carbon> cycle starts chained like ActivateSubscriptionAction
     */
    private function chainStarts(Carbon $start, BillingCycle $cycle, int $count): array
    {
        $starts = [];
        $at = $start->copy();
        for ($i = 0; $i < $count; $i++) {
            $starts[] = $at->copy();
            $at = $cycle === BillingCycle::Yearly ? $at->copy()->addYearNoOverflow() : $at->copy()->addMonthNoOverflow();
        }

        return $starts;
    }

    /**
     * A founder whose first verified payment is at $start (founder_price_until = start + 12 months).
     *
     * @return array{0: Subscription, 1: Tenant}
     */
    private function founder(Carbon $start, BillingCycle $cycle): array
    {
        $plan = $this->plan([
            'founder_price_monthly' => '349.000', 'founder_price_yearly' => '3490.000',
            'founder_transition_price_monthly' => '429.000', 'founder_transition_price_yearly' => '4290.000',
        ]);
        $tenant = $this->tenantOn($plan);
        $this->endTenancy();

        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'status' => SubscriptionStatus::Active,
            'amount' => '349.000',
            'is_founder' => true,
            'founder_price_until' => $start->copy()->addMonthsNoOverflow(12),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMonth(),
        ]);

        return [$subscription, $tenant];
    }

    private function paidInvoice(Tenant $tenant, Carbon $periodStart, BillingCycle $cycle): void
    {
        BillingInvoice::query()->create([
            'number' => 'SUB-CHAIN-'.Str::upper(Str::random(10)),
            'tenant_id' => $tenant->getKey(),
            'type' => 'renewal',
            'status' => 'paid',
            'billing_cycle' => $cycle,
            'period_start' => $periodStart,
            'lines' => [['type' => 'plan', 'plan_id' => (int) $tenant->plan_id, 'billing_cycle' => $cycle->value, 'quantity' => 1, 'unit_price' => '1.000', 'amount' => '1.000']],
            'subtotal' => '1.000', 'discount' => '0.000', 'tax_rate' => '0.000', 'tax' => '0.000', 'total' => '1.000',
            'issued_at' => now(), 'due_at' => now()->addDays(7),
        ]);
    }
}
