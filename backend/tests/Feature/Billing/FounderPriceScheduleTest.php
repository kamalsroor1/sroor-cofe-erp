<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\IssueSubscriptionInvoiceAction;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\FounderPriceStep;
use App\Enums\Billing\SubscriptionStatus;
use App\Exceptions\Billing\BillingException;
use App\Http\Resources\Billing\PriceScheduleResource;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSequence;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use Carbon\CarbonImmutable;
use Closure;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * PRICE-2026-11: the 3-step founder schedule (founder year 1 -> transition year 2 -> list) on the
 * seeded catalog (basic 499/4990 list, 349/3490 founder, 429/4290 transition; pro 999/699/849).
 *
 * Boundaries are exact to the second: founder_price_until = first verified payment + 12 months,
 * the transition ends 12 months later, and an invoice prices its whole cycle by the step at the
 * cycle START. Every amount is a DECIMAL(12,3) string.
 */
#[Group('billing')]
final class FounderPriceScheduleTest extends BillingTestCase
{
    /** First verified payment of every founder in this file. */
    private const FIRST_PAYMENT = '2026-01-15 10:00:00';

    private const YEAR_1_END = '2027-01-15 10:00:00';

    private const TRANSITION_END = '2028-01-15 10:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.founder.slots' => 50, 'billing.founder.months' => 12, 'billing.founder.transition_months' => 12, 'billing.founder.notice_days' => 30]);
        $this->seed(PlansAndFeaturesSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string}> moment, expected step
     */
    public static function boundaryMoments(): array
    {
        return [
            'month 1 start' => [self::FIRST_PAYMENT, 'founder'],
            'month 12 last second' => ['2027-01-15 09:59:59', 'founder'],
            'month 13 first second' => [self::YEAR_1_END, 'transition'],
            'month 24 last second' => ['2028-01-15 09:59:59', 'transition'],
            'month 25 first second' => [self::TRANSITION_END, 'list'],
            'long after' => ['2030-06-01 00:00:00', 'list'],
        ];
    }

    private const PRICES = [
        'monthly' => ['founder' => '349.000', 'transition' => '429.000', 'list' => '499.000'],
        'yearly' => ['founder' => '3490.000', 'transition' => '4290.000', 'list' => '4990.000'],
    ];

    #[DataProvider('boundaryMoments')]
    public function test_service_resolves_the_step_and_price_at_every_boundary(string $moment, string $step): void
    {
        $plan = $this->catalogPlan('basic');

        foreach (['monthly' => BillingCycle::Monthly, 'yearly' => BillingCycle::Yearly] as $label => $cycle) {
            $subscription = $this->founderSubscription($this->createTenant(), $plan, $cycle);
            $at = Carbon::parse($moment);

            $this->assertSame(FounderPriceStep::from($step), $this->service()->stepFor($subscription, $plan, $cycle, $at), "{$label} at {$moment}");
            $this->assertSame(self::PRICES[$label][$step], $this->service()->planPrice($subscription, $at), "{$label} at {$moment}");
            $this->assertSame(self::PRICES[$label][$step], $this->service()->stepPrice($plan, $cycle, FounderPriceStep::from($step)));
        }
    }

    public function test_the_catalog_prices_are_the_approved_ones_and_yearly_is_ten_months(): void
    {
        $expected = [
            'basic' => ['499.000', '349.000', '429.000'],
            'pro' => ['999.000', '699.000', '849.000'],
            'enterprise' => ['1499.000', '1099.000', '1299.000'],
        ];

        foreach ($expected as $slug => [$list, $founder, $transition]) {
            $plan = $this->catalogPlan($slug);

            $this->assertSame($list, $this->service()->stepPrice($plan, BillingCycle::Monthly, FounderPriceStep::List));
            $this->assertSame($founder, $this->service()->stepPrice($plan, BillingCycle::Monthly, FounderPriceStep::Founder));
            $this->assertSame($transition, $this->service()->stepPrice($plan, BillingCycle::Monthly, FounderPriceStep::Transition));

            foreach ([FounderPriceStep::List, FounderPriceStep::Founder, FounderPriceStep::Transition] as $step) {
                $this->assertSame(
                    bcmul($this->service()->stepPrice($plan, BillingCycle::Monthly, $step), '10', 3),
                    $this->service()->stepPrice($plan, BillingCycle::Yearly, $step),
                    "{$slug} yearly = 10 x monthly at {$step->value}",
                );
            }
        }
    }

    public function test_transition_end_is_founder_end_plus_twelve_months_and_follows_the_config(): void
    {
        $subscription = $this->founderSubscription($this->createTenant(), $this->catalogPlan('basic'));

        $this->assertTrue($this->service()->transitionUntil($subscription)?->equalTo(Carbon::parse(self::TRANSITION_END)));

        config(['billing.founder.transition_months' => 6]);
        $this->assertTrue($this->service()->transitionUntil($subscription)?->equalTo(Carbon::parse('2027-07-15 10:00:00')));

        $this->assertNull($this->service()->transitionUntil($this->plainSubscription($this->createTenant(), $this->catalogPlan('basic'))));
    }

    public function test_next_step_change_walks_founder_then_transition_then_nothing(): void
    {
        $subscription = $this->founderSubscription($this->createTenant(), $this->catalogPlan('basic'));

        $first = $this->service()->nextStepChange($subscription, Carbon::parse(self::FIRST_PAYMENT));
        $this->assertSame(FounderPriceStep::Transition, $first['step'] ?? null);
        $this->assertTrue($first['at']->equalTo(Carbon::parse(self::YEAR_1_END)));

        $second = $this->service()->nextStepChange($subscription, Carbon::parse(self::YEAR_1_END));
        $this->assertSame(FounderPriceStep::List, $second['step'] ?? null);
        $this->assertTrue($second['at']->equalTo(Carbon::parse(self::TRANSITION_END)));

        $this->assertNull($this->service()->nextStepChange($subscription, Carbon::parse(self::TRANSITION_END)));
        $this->assertNull($this->service()->nextStepChange($this->plainSubscription($this->createTenant(), $this->catalogPlan('basic'))));
    }

    /**
     * @return array<string, array{0: string, 1: string}> cycle start, expected price_step of the plan line
     */
    public static function renewalStarts(): array
    {
        return [
            '1s before year-1 end' => ['2027-01-15 09:59:59', 'founder'],
            'at year-1 end' => [self::YEAR_1_END, 'transition'],
            '1s after year-1 end' => ['2027-01-15 10:00:01', 'transition'],
            '1s before transition end' => ['2028-01-15 09:59:59', 'transition'],
            'at transition end' => [self::TRANSITION_END, 'list'],
            '1s after transition end' => ['2028-01-15 10:00:01', 'list'],
        ];
    }

    #[DataProvider('renewalStarts')]
    public function test_a_renewal_invoice_prices_the_whole_cycle_by_the_step_at_its_start(string $start, string $step): void
    {
        $plan = $this->catalogPlan('basic');

        foreach (['monthly' => BillingCycle::Monthly, 'yearly' => BillingCycle::Yearly] as $label => $cycle) {
            $tenant = $this->createTenant();
            $this->founderSubscription($tenant, $plan, $cycle);

            $invoice = $this->issue($tenant, $plan, $cycle, BillingInvoiceType::Renewal, $start);
            $line = $invoice->lines[0];

            $this->assertSame($step, $line['price_step'], "{$label} cycle starting {$start}");
            $this->assertSame(self::PRICES[$label][$step], $line['unit_price']);
            $this->assertSame(self::PRICES[$label][$step], $invoice->subtotal);
            $this->assertSame($step !== 'list', $line['founder']);
        }
    }

    public function test_a_plan_with_a_founder_price_but_no_transition_price_goes_founder_then_list(): void
    {
        $plan = $this->catalogPlan('basic');
        $plan->forceFill(['founder_transition_price_monthly' => null, 'founder_transition_price_yearly' => null])->save();
        $subscription = $this->founderSubscription($this->createTenant(), $plan);

        $this->assertSame(FounderPriceStep::Founder, $this->service()->stepFor($subscription, $plan, BillingCycle::Monthly, Carbon::parse('2027-01-15 09:59:59')));
        $this->assertSame('349.000', $this->service()->planPrice($subscription, Carbon::parse('2027-01-15 09:59:59')));

        foreach ([self::YEAR_1_END, '2027-06-01 00:00:00', '2028-01-15 09:59:59'] as $moment) {
            $this->assertSame(FounderPriceStep::List, $this->service()->stepFor($subscription, $plan, BillingCycle::Monthly, Carbon::parse($moment)), $moment);
            $this->assertSame('499.000', $this->service()->planPrice($subscription, Carbon::parse($moment)), $moment);
        }

        // The only change the tenant will ever see: founder -> list.
        $next = $this->service()->nextStepChange($subscription, Carbon::parse(self::FIRST_PAYMENT));
        $this->assertSame(FounderPriceStep::List, $next['step'] ?? null);
        $this->assertTrue($next['at']->equalTo(Carbon::parse(self::YEAR_1_END)));
        $this->assertNull($this->service()->nextStepChange($subscription, Carbon::parse(self::YEAR_1_END)));

        // The invoice follows the same fall-through.
        $invoice = $this->issue($subscription->tenant_id, $plan, BillingCycle::Monthly, BillingInvoiceType::Renewal, '2027-02-15 10:00:00');
        $this->assertSame('list', $invoice->lines[0]['price_step']);
        $this->assertSame('499.000', $invoice->subtotal);
    }

    public function test_a_transition_price_only_for_one_cycle_falls_through_to_list_for_the_other(): void
    {
        $plan = $this->catalogPlan('basic');
        $plan->forceFill(['founder_transition_price_yearly' => null])->save();
        $subscription = $this->founderSubscription($this->createTenant(), $plan);
        $at = Carbon::parse('2027-06-01 00:00:00');

        $this->assertSame('429.000', $this->service()->stepPrice($plan, BillingCycle::Monthly, $this->service()->stepFor($subscription, $plan, BillingCycle::Monthly, $at)));
        $this->assertSame(FounderPriceStep::List, $this->service()->stepFor($subscription, $plan, BillingCycle::Yearly, $at));
    }

    public function test_changing_plan_or_cycle_in_the_transition_year_keeps_the_schedule(): void
    {
        $basic = $this->catalogPlan('basic');
        $pro = $this->catalogPlan('pro');
        $tenant = $this->createTenant();
        $subscription = $this->founderSubscription($tenant, $basic);
        $at = Carbon::parse('2027-06-01 12:00:00');

        // basic -> pro: still the transition step, at pro's own transition price.
        $this->assertSame(FounderPriceStep::Transition, $this->service()->stepFor($subscription, $pro, BillingCycle::Monthly, $at));
        $this->assertSame('849.000', $this->service()->stepPrice($pro, BillingCycle::Monthly, FounderPriceStep::Transition));

        // monthly -> yearly: transition yearly price.
        $this->assertSame(FounderPriceStep::Transition, $this->service()->stepFor($subscription, $basic, BillingCycle::Yearly, $at));

        // The upgrade invoice is priced by the same schedule: no reset to founder, no drop to list.
        $invoice = $this->issue($tenant, $pro, BillingCycle::Monthly, BillingInvoiceType::Plan, '2027-06-01 12:00:00');
        $this->assertSame('transition', $invoice->lines[0]['price_step']);
        $this->assertSame('849.000', $invoice->subtotal);

        $yearly = $this->issue($tenant, $pro, BillingCycle::Yearly, BillingInvoiceType::Plan, '2027-06-02 12:00:00');
        $this->assertSame('8490.000', $yearly->subtotal);

        // Moving to a plan with no transition price (custom plan) falls to list, never to founder.
        $custom = $this->customPlan(['founder_price_monthly' => '300.000', 'founder_transition_price_monthly' => null]);
        $this->assertSame(FounderPriceStep::List, $this->service()->stepFor($subscription, $custom, BillingCycle::Monthly, $at));

        $this->assertTrue($subscription->refresh()->is_founder);
        $this->assertTrue($subscription->founder_price_until->equalTo(Carbon::parse(self::YEAR_1_END)), 'The window is never reset by a plan change.');
    }

    public function test_slot_fifty_one_pays_list_and_a_holder_inherits_its_window_when_slots_are_full(): void
    {
        $basic = $this->catalogPlan('basic');
        $this->setCounter(49);

        // Slot 50.
        $fiftieth = $this->createTenant();
        $fiftiethSubscription = $this->plainSubscription($fiftieth, $basic);
        $fiftiethPayment = $this->verifiedPayment($fiftiethSubscription, Carbon::parse(self::FIRST_PAYMENT));
        $this->assertTrue($this->inCentral(fn (): bool => $this->service()->claim($fiftiethSubscription, $fiftiethPayment)));
        $this->assertSame(50, $this->service()->claimedSlots());

        // Slot 51: nothing written, every step is list.
        $late = $this->createTenant();
        $lateSubscription = $this->plainSubscription($late, $basic);
        $latePayment = $this->verifiedPayment($lateSubscription, Carbon::parse(self::FIRST_PAYMENT));
        $this->assertFalse($this->inCentral(fn (): bool => $this->service()->claim($lateSubscription, $latePayment)));
        $this->assertFalse($lateSubscription->refresh()->is_founder);
        $this->assertSame(50, $this->service()->claimedSlots());
        foreach ([self::FIRST_PAYMENT, '2027-06-01 00:00:00'] as $moment) {
            $this->assertSame('499.000', $this->service()->planPrice($lateSubscription, Carbon::parse($moment)), $moment);
        }
        $this->assertNull($this->service()->nextStepChange($lateSubscription, Carbon::parse(self::FIRST_PAYMENT)));

        // Year 2, all 50 slots gone: the holder's NEW subscription inherits the original window.
        $this->travelTo(Carbon::parse('2027-06-01 12:00:00'));
        $newSubscription = $this->plainSubscription($fiftieth, $basic);
        $renewalPayment = $this->verifiedPayment($newSubscription, Carbon::now());
        $this->assertTrue($this->inCentral(fn (): bool => $this->service()->claim($newSubscription, $renewalPayment)));

        $newSubscription->refresh();
        $this->assertTrue($newSubscription->is_founder);
        $this->assertTrue($newSubscription->founder_price_until->equalTo(Carbon::parse(self::YEAR_1_END)), 'Inherited from the first payment, not restarted.');
        $this->assertSame(FounderPriceStep::Transition, $this->service()->priceStep($newSubscription));
        $this->assertSame('429.000', $this->service()->planPrice($newSubscription));
        $this->assertSame(50, $this->service()->claimedSlots(), 'Inheriting never takes a slot.');

        // The late tenant, paying for the first time in year 2, still cannot claim anything.
        $lateAgain = $this->plainSubscription($late, $basic);
        $latePayment2 = $this->verifiedPayment($lateAgain, Carbon::now());
        $this->assertFalse($this->inCentral(fn (): bool => $this->service()->claim($lateAgain, $latePayment2)));
        $this->assertSame('499.000', $this->service()->planPrice($lateAgain->refresh()));
    }

    public function test_the_trial_end_invoice_is_founder_priced_only_while_a_slot_is_free(): void
    {
        $basic = $this->catalogPlan('basic');
        $free = $this->createTenant();
        $this->setCounter(49);

        $first = $this->issue($free, $basic, BillingCycle::Monthly, BillingInvoiceType::Plan, null, founderIfAvailable: true);
        $this->assertSame('founder', $first->lines[0]['price_step']);
        $this->assertSame('349.000', $first->subtotal);
        $this->assertSame(49, $this->service()->claimedSlots(), 'Issuing an invoice never claims the slot.');

        $this->setCounter(50);
        $second = $this->issue($this->createTenant(), $basic, BillingCycle::Monthly, BillingInvoiceType::Plan, null, founderIfAvailable: true);
        $this->assertSame('list', $second->lines[0]['price_step']);
        $this->assertSame('499.000', $second->subtotal);
    }

    /**
     * @return array<string, array{0: int, 1: bool}> days before the step change, notice_due
     */
    public static function noticeWindows(): array
    {
        return [
            '31 days before' => [31, false],
            '30 days before' => [30, true],
            '29 days before' => [29, true],
            '1 day before' => [1, true],
        ];
    }

    #[DataProvider('noticeWindows')]
    public function test_notice_due_flips_at_the_configured_number_of_days(int $daysBefore, bool $due): void
    {
        $subscription = $this->founderSubscription($this->createTenant(), $this->catalogPlan('basic'));

        foreach ([[self::YEAR_1_END, 'transition', '429.000'], [self::TRANSITION_END, 'list', '499.000']] as [$boundary, $nextStep, $nextPrice]) {
            $this->travelTo(Carbon::parse($boundary)->subDays($daysBefore));
            // Paid up to the boundary: the next cycle (and the new price) starts exactly there.
            $this->paidUntil($subscription->tenant_id, $boundary);

            $schedule = PriceScheduleResource::for($subscription->refresh());

            $this->assertNotNull($schedule);
            $this->assertSame($nextStep, $schedule['next_step']['value'], "{$daysBefore} days before {$boundary}");
            $this->assertSame($nextPrice, $schedule['next_price']);
            $this->assertTrue(Carbon::parse($schedule['next_step_at'])->equalTo(Carbon::parse($boundary)));
            $this->assertSame($due, $schedule['notice_due'], "{$daysBefore} days before {$boundary}");
        }
    }

    public function test_notice_due_is_false_by_one_second_past_the_thirty_day_window(): void
    {
        $subscription = $this->founderSubscription($this->createTenant(), $this->catalogPlan('basic'));

        $this->travelTo(Carbon::parse(self::YEAR_1_END)->subDays(30)->subSecond());
        $this->assertFalse(PriceScheduleResource::for($subscription)['notice_due'] ?? null);

        $this->travelTo(Carbon::parse(self::YEAR_1_END)->subDays(30));
        $this->assertTrue(PriceScheduleResource::for($subscription)['notice_due'] ?? null);

        config(['billing.founder.notice_days' => 7]);
        $this->travelTo(Carbon::parse(self::YEAR_1_END)->subDays(8));
        $this->assertFalse(PriceScheduleResource::for($subscription)['notice_due'] ?? null);
    }

    public function test_price_schedule_of_a_non_founder_is_list_without_a_next_step(): void
    {
        $subscription = $this->plainSubscription($this->createTenant(), $this->catalogPlan('basic'));

        $schedule = PriceScheduleResource::for($subscription);

        $this->assertNotNull($schedule);
        $this->assertSame('list', $schedule['step']['value']);
        $this->assertSame('499.000', $schedule['current_price']);
        $this->assertSame('499.000', $schedule['list_price']);
        $this->assertNull($schedule['next_step']);
        $this->assertNull($schedule['next_step_at']);
        $this->assertNull($schedule['next_price']);
        $this->assertNull($schedule['founder_price_until']);
        $this->assertNull($schedule['transition_until']);
        $this->assertFalse($schedule['notice_due']);
    }

    public function test_price_schedule_after_the_whole_schedule_is_list_and_quiet(): void
    {
        $subscription = $this->founderSubscription($this->createTenant(), $this->catalogPlan('basic'));
        $this->travelTo(Carbon::parse('2028-03-01 00:00:00'));

        $schedule = PriceScheduleResource::for($subscription);

        $this->assertSame('list', $schedule['step']['value'] ?? null);
        $this->assertSame('499.000', $schedule['current_price']);
        $this->assertNull($schedule['next_step']);
        $this->assertFalse($schedule['notice_due']);
    }

    public function test_price_schedule_is_null_without_a_subscription_and_for_an_unsold_cycle(): void
    {
        $this->assertNull(PriceScheduleResource::for(null));

        $subscription = $this->founderSubscription($this->createTenant(), $this->catalogPlan('basic'), BillingCycle::Biennial);
        $this->assertNull(PriceScheduleResource::for($subscription));
    }

    public function test_the_shop_subscription_endpoint_carries_the_price_schedule(): void
    {
        $plan = $this->catalogPlan('basic');
        $tenant = $this->createTenant(['plan_id' => $plan->id]);
        $this->founderSubscription($tenant, $plan);
        $this->travelTo(Carbon::parse('2027-01-01 00:00:00'));

        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription.price_schedule.step.value', 'founder')
            ->assertJsonPath('data.subscription.price_schedule.current_price', '349.000')
            ->assertJsonPath('data.subscription.price_schedule.next_step.value', 'transition')
            ->assertJsonPath('data.subscription.price_schedule.next_price', '429.000')
            ->assertJsonPath('data.subscription.price_schedule.notice_due', true);
    }

    public function test_premium_support_cannot_be_invoiced_as_an_add_on_on_enterprise_but_can_on_pro(): void
    {
        $support = Addon::query()->where('key', 'addon.premium_support')->firstOrFail();

        foreach (['enterprise' => false, 'pro' => true] as $slug => $purchasable) {
            $plan = $this->catalogPlan($slug);
            $tenant = $this->createTenant(['plan_id' => $plan->id, 'subscription_ends_at' => now()->addDays(20)]);
            $this->plainSubscription($tenant, $plan)->forceFill(['starts_at' => now()->subDays(10), 'ends_at' => now()->addDays(20)])->save();

            $dto = new IssueSubscriptionInvoiceDTO(
                tenantId: (string) $tenant->id,
                type: BillingInvoiceType::Addon,
                planId: null,
                cycle: BillingCycle::Monthly,
                useFounderPrice: false,
                serviceLines: [],
                discount: '0.000',
                notes: null,
                periodStart: null,
                issuedBy: null,
                addonLines: [['addon_id' => (int) $support->id, 'quantity' => 1, 'unit_price' => null]],
            );

            if ($purchasable) {
                $invoice = app(IssueSubscriptionInvoiceAction::class)->execute($dto);
                $this->assertSame('addon.premium_support', $invoice->lines[0]['addon_key'], $slug);

                continue;
            }

            try {
                app(IssueSubscriptionInvoiceAction::class)->execute($dto);
                $this->fail('Premium support is included in Enterprise and must not be billable as an add-on.');
            } catch (BillingException $e) {
                $this->assertSame(0, BillingInvoice::query()->where('tenant_id', $tenant->id)->count(), 'Nothing is invoiced.');
            }
        }
    }

    // ---- helpers -----------------------------------------------------------------------------

    private function service(): FounderPricingService
    {
        return app(FounderPricingService::class);
    }

    private function catalogPlan(string $slug): Plan
    {
        return Plan::query()->where('slug', $slug)->sole();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function customPlan(array $attributes = []): Plan
    {
        return $this->plan(array_merge(['price_monthly' => '600.000', 'price_yearly' => '6000.000'], $attributes));
    }

    private function plainSubscription(Tenant $tenant, Plan $plan, BillingCycle $cycle = BillingCycle::Monthly): Subscription
    {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'billing_cycle' => $cycle,
            'status' => SubscriptionStatus::Active,
            'amount' => '499.000',
            'starts_at' => Carbon::parse(self::FIRST_PAYMENT),
            'ends_at' => Carbon::parse(self::FIRST_PAYMENT)->addMonth(),
        ]);
    }

    private function founderSubscription(Tenant|string $tenant, Plan $plan, BillingCycle $cycle = BillingCycle::Monthly): Subscription
    {
        $tenant = $tenant instanceof Tenant ? $tenant : Tenant::query()->findOrFail($tenant);
        $subscription = $this->plainSubscription($tenant, $plan, $cycle);
        $subscription->forceFill(['is_founder' => true, 'founder_price_until' => Carbon::parse(self::YEAR_1_END)])->save();
        // Review M2: next_step_at is the start of the first cycle billed at the new price, i.e. on the
        // shop's own term chain. The shop is paid up to the year-1 end, so its next cycle starts there.
        $this->paidUntil($tenant, self::YEAR_1_END);

        return $subscription->refresh();
    }

    /** The shop's paid term ends at $end (its next renewal starts there). */
    private function paidUntil(Tenant|string $tenant, string $end): void
    {
        Tenant::query()->whereKey($tenant instanceof Tenant ? $tenant->getKey() : $tenant)
            ->update(['subscription_ends_at' => Carbon::parse($end)]);
    }

    private function verifiedPayment(Subscription $subscription, Carbon $at): BillingPayment
    {
        $invoice = BillingInvoice::query()->create([
            'number' => 'SCH-'.strtoupper(bin2hex(random_bytes(6))),
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'type' => BillingInvoiceType::Plan,
            'lines' => [['kind' => 'plan', 'key' => 'basic', 'quantity' => 1, 'unit_price' => '499.000', 'total' => '499.000']],
            'subtotal' => '499.000',
            'total' => '499.000',
        ]);

        return BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->id,
            'tenant_id' => $subscription->tenant_id,
            'amount' => '499.000',
            'method' => BillingPaymentMethod::Instapay,
            'status' => BillingPaymentStatus::Verified,
            'verified_at' => $at,
            'paid_at' => $at,
        ]);
    }

    private function issue(Tenant|string $tenant, Plan $plan, BillingCycle $cycle, BillingInvoiceType $type, ?string $periodStart, bool $founderIfAvailable = false): BillingInvoice
    {
        $tenantId = $tenant instanceof Tenant ? $tenant->id : $tenant;

        return app(IssueSubscriptionInvoiceAction::class)->execute(new IssueSubscriptionInvoiceDTO(
            tenantId: (string) $tenantId,
            type: $type,
            planId: $plan->id,
            cycle: $cycle,
            useFounderPrice: false,
            serviceLines: [],
            discount: '0.000',
            notes: null,
            periodStart: $periodStart === null ? null : CarbonImmutable::parse($periodStart),
            issuedBy: null,
            founderPriceIfAvailable: $founderIfAvailable,
        ));
    }

    private function setCounter(int $value): void
    {
        BillingSequence::query()->updateOrCreate(
            ['key' => FounderPricingService::SLOT_SEQUENCE, 'period' => ''],
            ['last_value' => $value],
        );
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function inCentral(Closure $callback): mixed
    {
        return DB::connection($this->centralConnectionName())->transaction($callback);
    }
}
