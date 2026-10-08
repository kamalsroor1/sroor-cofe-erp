<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Exceptions\Billing\FounderPricingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\BillingSequence;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TenantTestCase;
use Throwable;

/**
 * ENTI-1.7: founder pricing (first 50 paying customers keep 299/599/999 for 12 months).
 *
 * Q-E4 [CTO-2026-10-08]:
 * - the slot is taken at the tenant's FIRST verified payment (not at signup / trial);
 * - a slot is never given back (cancel / expiry does not free it);
 * - it discounts the plan price only (add-ons always pay full price).
 *
 * The counter is a locked row in central `billing_sequences` (key `founder_slot`): claim()
 * runs inside the caller's central transaction, so a rolled-back activation gives the slot
 * back and concurrent activations can never hand out slot 51.
 */
#[Group('billing')]
#[Group('mysql')]
final class FounderPricingServiceTest extends TenantTestCase
{
    private const LOCK_WAIT_TIMEOUT = 1205;

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.founder.slots' => 50, 'billing.founder.months' => 12]);
    }

    public function test_config_defaults_match_the_cto_decision(): void
    {
        $config = require config_path('billing.php');

        $this->assertSame(50, $config['founder']['slots']);
        $this->assertSame(12, $config['founder']['months']);
        $this->assertSame(50, $this->service()->totalSlots());
        $this->assertSame(12, $this->service()->durationMonths());
    }

    public function test_first_verified_payment_claims_a_slot_for_twelve_months(): void
    {
        $verifiedAt = Carbon::parse('2026-10-15 10:00:00');
        [, $subscription, $payment] = $this->paidTenant(verifiedAt: $verifiedAt);

        $this->assertSame(50, $this->service()->remainingSlots());

        $claimed = $this->inCentralTransaction(fn (): bool => $this->service()->claim($subscription, $payment));

        $this->assertTrue($claimed);
        $subscription->refresh();
        $this->assertTrue($subscription->is_founder);
        $this->assertNotNull($subscription->founder_price_until);
        $this->assertTrue($subscription->founder_price_until->equalTo($verifiedAt->copy()->addMonthsNoOverflow(12)));
        $this->assertSame(1, $this->service()->claimedSlots());
        $this->assertSame(49, $this->service()->remainingSlots());
        $this->assertTrue($this->service()->hasAvailableSlot());
    }

    public function test_slot_fifty_is_granted_and_slot_fifty_one_is_refused(): void
    {
        $this->setCounter(49);

        [, $fiftieth, $fiftiethPayment] = $this->paidTenant();
        [, $fiftyFirst, $fiftyFirstPayment] = $this->paidTenant();

        $this->assertTrue($this->inCentralTransaction(fn (): bool => $this->service()->claim($fiftieth, $fiftiethPayment)));
        $this->assertSame(50, $this->service()->claimedSlots());
        $this->assertSame(0, $this->service()->remainingSlots());
        $this->assertFalse($this->service()->hasAvailableSlot());

        $this->assertFalse($this->inCentralTransaction(fn (): bool => $this->service()->claim($fiftyFirst, $fiftyFirstPayment)));

        $fiftyFirst->refresh();
        $this->assertFalse($fiftyFirst->is_founder);
        $this->assertNull($fiftyFirst->founder_price_until);
        $this->assertSame(50, $this->service()->claimedSlots(), 'A refused claim must not move the counter past the limit.');
        $this->assertSame('449.000', $this->service()->planPrice($fiftyFirst));
    }

    public function test_a_lowered_limit_never_reports_negative_remaining_slots(): void
    {
        $this->setCounter(50);
        config(['billing.founder.slots' => 10]);

        $this->assertSame(0, $this->service()->remainingSlots());
        $this->assertFalse($this->service()->hasAvailableSlot());
    }

    public function test_claim_is_idempotent_for_the_same_subscription(): void
    {
        [, $subscription, $payment] = $this->paidTenant();

        $this->assertTrue($this->inCentralTransaction(fn (): bool => $this->service()->claim($subscription, $payment)));
        $until = $subscription->refresh()->founder_price_until;

        $this->assertTrue($this->inCentralTransaction(fn (): bool => $this->service()->claim($subscription, $payment)));

        $this->assertSame(1, $this->service()->claimedSlots(), 'Retrying an activation must not burn a second slot.');
        $this->assertTrue($subscription->refresh()->founder_price_until?->equalTo($until));
    }

    public function test_a_renewal_keeps_the_founder_window_without_a_new_slot(): void
    {
        $verifiedAt = Carbon::now()->startOfSecond()->subMonths(2);
        [$tenant, $first, $firstPayment] = $this->paidTenant(verifiedAt: $verifiedAt);
        $this->assertTrue($this->inCentralTransaction(fn (): bool => $this->service()->claim($first, $firstPayment)));

        [$renewal, $renewalPayment] = $this->renewal($tenant, $first->plan_id);

        $this->assertTrue($this->inCentralTransaction(fn (): bool => $this->service()->claim($renewal, $renewalPayment)));

        $renewal->refresh();
        $this->assertTrue($renewal->is_founder);
        $this->assertTrue($renewal->founder_price_until?->equalTo($verifiedAt->copy()->addMonthsNoOverflow(12)), 'The 12 months run from the first payment, not from each renewal.');
        $this->assertSame(1, $this->service()->claimedSlots());
    }

    public function test_a_renewal_after_the_window_gets_the_regular_price(): void
    {
        $verifiedAt = Carbon::now()->startOfSecond()->subMonths(13);
        [$tenant, $first, $firstPayment] = $this->paidTenant(verifiedAt: $verifiedAt);
        $this->assertTrue($this->inCentralTransaction(fn (): bool => $this->service()->claim($first, $firstPayment)));

        [$renewal, $renewalPayment] = $this->renewal($tenant, $first->plan_id);

        $this->assertFalse($this->inCentralTransaction(fn (): bool => $this->service()->claim($renewal, $renewalPayment)));
        $this->assertFalse($renewal->refresh()->is_founder);
        $this->assertSame(1, $this->service()->claimedSlots());
        $this->assertSame('449.000', $this->service()->planPrice($renewal));
    }

    public function test_only_the_first_payment_can_claim_a_slot(): void
    {
        // The tenant paid before (e.g. while all slots were taken, or for a plan without a
        // founder price): a later payment is not a "first payment" and gets no slot.
        [$tenant, $first, $firstPayment] = $this->paidTenant(planAttributes: ['founder_price_monthly' => null]);
        $this->assertFalse($this->inCentralTransaction(fn (): bool => $this->service()->claim($first, $firstPayment)));

        $founderPlan = $this->plan();
        [$second, $secondPayment] = $this->renewal($tenant, $founderPlan->id);

        $this->assertFalse($this->inCentralTransaction(fn (): bool => $this->service()->claim($second, $secondPayment)));
        $this->assertSame(0, $this->service()->claimedSlots());
    }

    public function test_a_refunded_earlier_payment_still_counts_as_paid_before(): void
    {
        [$tenant, $first, $firstPayment] = $this->paidTenant(planAttributes: ['founder_price_monthly' => null]);
        $firstPayment->update(['status' => BillingPaymentStatus::Refunded]);

        [$second, $secondPayment] = $this->renewal($tenant, $this->plan()->id);

        $this->assertFalse($this->inCentralTransaction(fn (): bool => $this->service()->claim($second, $secondPayment)));
    }

    public function test_pending_or_rejected_earlier_payments_do_not_block_the_first_real_payment(): void
    {
        [$tenant, $subscription, $payment] = $this->paidTenant();

        $invoice = $this->invoice($subscription);
        $this->payment($invoice, ['status' => BillingPaymentStatus::Rejected]);
        $this->payment($invoice, ['status' => BillingPaymentStatus::Pending]);
        $this->payment($invoice, ['status' => BillingPaymentStatus::Failed]);

        $this->assertTrue($this->inCentralTransaction(fn (): bool => $this->service()->claim($subscription, $payment)));
        $this->assertSame($tenant->id, $subscription->tenant_id);
    }

    public function test_a_plan_without_a_founder_price_for_the_cycle_takes_no_slot(): void
    {
        // Yearly founder price is NULL (TODO(CTO) in ENTI-1.2): a yearly subscriber is not founder-priced.
        [, $subscription, $payment] = $this->paidTenant(cycle: BillingCycle::Yearly);

        $this->assertFalse($this->service()->isEligiblePlan($subscription->plan()->firstOrFail(), BillingCycle::Yearly));
        $this->assertFalse($this->inCentralTransaction(fn (): bool => $this->service()->claim($subscription, $payment)));
        $this->assertSame(0, $this->service()->claimedSlots());
        $this->assertSame('4490.000', $this->service()->planPrice($subscription->refresh()));
    }

    public function test_biennial_is_never_founder_priced(): void
    {
        $plan = $this->plan(['founder_price_yearly' => '2990.000']);

        $this->assertTrue($this->service()->isEligiblePlan($plan, BillingCycle::Monthly));
        $this->assertTrue($this->service()->isEligiblePlan($plan, BillingCycle::Yearly));
        $this->assertFalse($this->service()->isEligiblePlan($plan, BillingCycle::Biennial));
    }

    public function test_a_free_founder_price_is_not_eligible(): void
    {
        $this->assertFalse($this->service()->isEligiblePlan($this->plan(['founder_price_monthly' => '0']), BillingCycle::Monthly));
    }

    public function test_price_switches_to_the_regular_price_after_the_window(): void
    {
        $verifiedAt = Carbon::parse('2026-01-31 09:00:00');
        [, $subscription, $payment] = $this->paidTenant(verifiedAt: $verifiedAt);
        $this->inCentralTransaction(fn (): bool => $this->service()->claim($subscription, $payment));
        $subscription->refresh();

        $until = $verifiedAt->copy()->addMonthsNoOverflow(12);
        $this->assertSame('2027-01-31 09:00:00', $until->format('Y-m-d H:i:s'));

        $this->assertSame('299.000', $this->service()->planPrice($subscription, $verifiedAt));
        $this->assertSame('299.000', $this->service()->planPrice($subscription, $until->copy()->subSecond()));
        $this->assertTrue($this->service()->isFounderPriceActive($subscription, $until->copy()->subSecond()));

        $this->assertSame('449.000', $this->service()->planPrice($subscription, $until));
        $this->assertFalse($this->service()->isFounderPriceActive($subscription, $until));
        $this->assertSame('449.000', $this->service()->planPrice($subscription, $until->copy()->addMonth()));

        // Defaults to now().
        $this->travelTo($until->copy()->subDay());
        $this->assertSame('299.000', $this->service()->planPrice($subscription));
        $this->travelTo($until->copy()->addDay());
        $this->assertSame('449.000', $this->service()->planPrice($subscription));
    }

    public function test_cancelling_a_founder_subscription_does_not_free_the_slot(): void
    {
        [, $subscription, $payment] = $this->paidTenant();
        $this->inCentralTransaction(fn (): bool => $this->service()->claim($subscription, $payment));

        $subscription->update(['status' => SubscriptionStatus::Cancelled, 'cancelled_at' => now()]);
        $subscription->update(['status' => SubscriptionStatus::Expired]);

        $this->assertSame(1, $this->service()->claimedSlots());
        $this->assertSame(49, $this->service()->remainingSlots());
    }

    public function test_a_rolled_back_activation_gives_the_slot_back(): void
    {
        [, $subscription, $payment] = $this->paidTenant();

        $caught = null;
        try {
            $this->inCentralTransaction(function () use ($subscription, $payment): void {
                $this->assertTrue($this->service()->claim($subscription, $payment));
                $this->assertSame(1, $this->service()->claimedSlots());

                throw new RuntimeException('activation failed');
            });
        } catch (RuntimeException $e) {
            $caught = $e->getMessage();
        }
        $this->assertSame('activation failed', $caught, 'The failing activation must roll the transaction back.');

        $this->assertSame(0, $this->service()->claimedSlots());
        $this->assertFalse($subscription->refresh()->is_founder);
    }

    public function test_claim_refuses_to_run_outside_a_transaction(): void
    {
        [, $subscription, $payment] = $this->paidTenant();
        $central = DB::connection($this->centralConnectionName());
        $levels = $central->transactionLevel();

        // RefreshDatabase wraps the test in a transaction: leave it to reproduce a caller that
        // forgot DB::transaction(), then restore it for the teardown rollback.
        for ($i = 0; $i < $levels; $i++) {
            $central->rollBack();
        }

        try {
            $this->assertSame(0, $central->transactionLevel());
            $this->service()->claim($subscription, $payment);
            $this->fail('claim() must refuse to run without an open transaction.');
        } catch (FounderPricingException $e) {
            $this->assertSame('transaction_required', $e->reason());
            $this->assertSame(__('billing.founder_pricing.transaction_required'), $e->getMessage());
        } finally {
            for ($i = 0; $i < $levels; $i++) {
                $central->beginTransaction();
            }
        }
    }

    public function test_claim_rejects_an_unverified_payment(): void
    {
        [, $subscription, $payment] = $this->paidTenant();
        $payment->update(['status' => BillingPaymentStatus::Pending, 'verified_at' => null]);

        $this->assertClaimFails('payment_not_verified', $subscription, $payment);
        $this->assertSame(0, $this->service()->claimedSlots());
    }

    public function test_claim_rejects_a_payment_of_another_tenant(): void
    {
        [, $subscription] = $this->paidTenant();
        [, , $otherPayment] = $this->paidTenant();

        $this->assertClaimFails('payment_tenant_mismatch', $subscription, $otherPayment);
        $this->assertSame(0, $this->service()->claimedSlots());
    }

    public function test_plan_price_rejects_a_cycle_that_is_not_sold(): void
    {
        [, $subscription] = $this->paidTenant();
        $subscription->billing_cycle = BillingCycle::Biennial;

        try {
            $this->service()->planPrice($subscription);
            $this->fail('Biennial is not priced in Phase 1 (Q-E6).');
        } catch (FounderPricingException $e) {
            $this->assertSame('cycle_not_sellable', $e->reason());
            $this->assertSame(__('billing.founder_pricing.cycle_not_sellable', ['cycle' => BillingCycle::Biennial->label()]), $e->getMessage());
        }
    }

    /**
     * @return array<string, array{0: string, 1: mixed}>
     */
    public static function invalidConfiguration(): array
    {
        return [
            'negative slots' => ['billing.founder.slots', -1],
            'string slots' => ['billing.founder.slots', '50'],
            'missing slots' => ['billing.founder.slots', null],
            'zero months' => ['billing.founder.months', 0],
            'string months' => ['billing.founder.months', '12'],
        ];
    }

    #[DataProvider('invalidConfiguration')]
    public function test_invalid_configuration_is_rejected_before_a_slot_is_taken(string $key, mixed $value): void
    {
        [, $subscription, $payment] = $this->paidTenant();
        config([$key => $value]);

        $this->assertClaimFails('invalid_configuration', $subscription, $payment);
        $this->assertSame(0, (int) BillingSequence::query()->where('key', FounderPricingService::SLOT_SEQUENCE)->value('last_value'));
        $this->assertFalse($subscription->refresh()->is_founder);
    }

    public function test_counter_lives_in_the_central_db_even_inside_a_tenant(): void
    {
        [$tenant, $subscription, $payment] = $this->paidTenant();
        $central = $this->centralConnectionName();

        $seen = $this->inTenant($tenant, fn (): array => [
            'default' => DB::getDefaultConnection(),
            'claimed' => DB::connection($central)->transaction(fn (): bool => $this->service()->claim($subscription, $payment)),
            'count' => $this->service()->claimedSlots(),
        ]);

        $this->assertNotSame($central, $seen['default'], 'Tenancy must switch the default connection for this test to mean anything.');
        $this->assertTrue($seen['claimed']);
        $this->assertSame(1, $seen['count']);
        $this->assertSame(1, BillingSequence::query()->where('key', FounderPricingService::SLOT_SEQUENCE)->where('period', '')->value('last_value'));
    }

    public function test_claim_waits_for_the_counter_lock_on_mysql(): void
    {
        $central = $this->centralConnectionName();
        if (! in_array(DB::connection($central)->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('MySQL-only: sqlite has no row locks. Runs in the CI `mysql` job (phpunit.mysql.xml).');
        }

        [, $subscription, $payment] = $this->paidTenant();
        $holder = $this->centralSideConnection('qa_founder_slot_holder');

        $holder->table('billing_sequences')->insertOrIgnore([
            'key' => FounderPricingService::SLOT_SEQUENCE, 'period' => '', 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->beforeApplicationDestroyed(function (): void {
            $this->centralSideConnection('qa_founder_slot_cleanup')->table('billing_sequences')
                ->where('key', FounderPricingService::SLOT_SEQUENCE)->where('period', '')->delete();
            DB::purge('qa_founder_slot_cleanup');
        });

        $connection = DB::connection($central);
        $connection->statement('SET SESSION innodb_lock_wait_timeout = 1');
        $holder->beginTransaction();

        $errorCode = null;
        try {
            $holder->selectOne('select id from billing_sequences where `key` = ? and period = ? for update', [FounderPricingService::SLOT_SEQUENCE, '']);

            try {
                $connection->transaction(fn (): bool => $this->service()->claim($subscription, $payment));
            } catch (Throwable $e) {
                for ($cause = $e; $cause !== null; $cause = $cause->getPrevious()) {
                    if ($cause instanceof QueryException) {
                        $errorCode = (int) ($cause->errorInfo[1] ?? 0);
                        break;
                    }
                }

                if ($errorCode === null) {
                    throw $e;
                }
            }
        } finally {
            $holder->rollBack();
            DB::purge('qa_founder_slot_holder');
        }

        try {
            $this->assertSame(self::LOCK_WAIT_TIMEOUT, $errorCode, 'claim() did not wait for the counter lock: lockForUpdate() is missing.');
            $this->assertTrue($connection->transaction(fn (): bool => $this->service()->claim($subscription, $payment)));
        } finally {
            $connection->statement('SET SESSION innodb_lock_wait_timeout = 50');
        }
    }

    private function assertClaimFails(string $reason, Subscription $subscription, BillingPayment $payment): void
    {
        try {
            $this->inCentralTransaction(fn (): bool => $this->service()->claim($subscription, $payment));
            $this->fail("claim() must fail with [{$reason}].");
        } catch (FounderPricingException $e) {
            $this->assertSame($reason, $e->reason());
            $this->assertNotSame('billing.founder_pricing.'.$reason, $e->getMessage(), 'The message must be translated.');
        }
    }

    private function service(): FounderPricingService
    {
        return app(FounderPricingService::class);
    }

    private function setCounter(int $value): void
    {
        BillingSequence::query()->create(['key' => FounderPricingService::SLOT_SEQUENCE, 'period' => '', 'last_value' => $value]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'plan-'.Str::lower(Str::random(10));

        return Plan::query()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '449.000',
            'price_yearly' => '4490.000',
            'founder_price_monthly' => '299.000',
            'founder_price_yearly' => null,
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * A tenant with one active subscription and its first verified payment.
     *
     * @param  array<string, mixed>  $planAttributes
     * @return array{0: Tenant, 1: Subscription, 2: BillingPayment}
     */
    private function paidTenant(BillingCycle $cycle = BillingCycle::Monthly, ?Carbon $verifiedAt = null, array $planAttributes = []): array
    {
        $tenant = $this->createTenant();
        $plan = $this->plan($planAttributes);

        $subscription = $this->subscription($tenant, $plan->id, $cycle);
        $payment = $this->payment($this->invoice($subscription), [
            'status' => BillingPaymentStatus::Verified,
            'verified_at' => $verifiedAt ?? Carbon::now()->startOfSecond(),
            'paid_at' => $verifiedAt ?? Carbon::now()->startOfSecond(),
        ]);

        return [$tenant, $subscription, $payment];
    }

    /**
     * @return array{0: Subscription, 1: BillingPayment}
     */
    private function renewal(Tenant $tenant, int $planId): array
    {
        $subscription = $this->subscription($tenant, $planId, BillingCycle::Monthly);
        $payment = $this->payment($this->invoice($subscription), [
            'status' => BillingPaymentStatus::Verified,
            'verified_at' => Carbon::now()->startOfSecond(),
        ]);

        return [$subscription, $payment];
    }

    private function subscription(Tenant $tenant, int $planId, BillingCycle $cycle): Subscription
    {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id,
            'plan_id' => $planId,
            'billing_cycle' => $cycle,
            'status' => SubscriptionStatus::Active,
            'amount' => $cycle === BillingCycle::Yearly ? '4490.000' : '449.000',
            'starts_at' => now(),
            'ends_at' => $cycle === BillingCycle::Yearly ? now()->addYear() : now()->addMonth(),
        ]);
    }

    private function invoice(Subscription $subscription): BillingInvoice
    {
        return BillingInvoice::query()->create([
            'number' => 'TST-'.Str::lower(Str::random(12)),
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'type' => BillingInvoiceType::Plan,
            'lines' => [['kind' => 'plan', 'key' => 'basic', 'quantity' => 1, 'unit_price' => '449.000', 'total' => '449.000']],
            'subtotal' => '449.000',
            'total' => '449.000',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function payment(BillingInvoice $invoice, array $attributes = []): BillingPayment
    {
        return BillingPayment::query()->create(array_merge([
            'billing_invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->total,
            'method' => BillingPaymentMethod::Instapay,
        ], $attributes));
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function inCentralTransaction(Closure $callback): mixed
    {
        return DB::connection($this->centralConnectionName())->transaction($callback);
    }

    private function centralSideConnection(string $name): Connection
    {
        config(["database.connections.{$name}" => (array) config('database.connections.'.$this->centralConnectionName())]);

        return DB::connection($name);
    }
}
