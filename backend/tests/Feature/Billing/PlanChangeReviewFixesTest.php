<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\RecordManualPaymentAction;
use App\DTOs\Billing\RecordManualPaymentDTO;
use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Code-review fixes on #232 phase 1 (commit f2955ef7):
 *  1. a console full-cycle upgrade invoice billed at the founder price claims the founder slot again
 *     (only difference invoices, and list-price plan-change invoices, skip the claim);
 *  2. the plan-change path bills every plan line at the LIST price (released founder hold with a
 *     renewal, and a lapsed founder's full-cycle invoice);
 *  3. `release_founder_hold` on a request that was never held is a coded 409;
 *  4. the period days come from the actual current term (Jan 31 -> Feb 28 = 28 days);
 *  5. a term already over when applied is flagged `term_already_ended`;
 *  6. a record-payment transfer-moment error is reported on `paid_at`;
 *  7. issuing locks the tenant before the request (existing flows stay green).
 */
#[Group('billing')]
final class PlanChangeReviewFixesTest extends PlanChangeTestCase
{
    private const QUEUE = '/api/v1/super-admin/billing/plan-change-requests';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-26 12:00:00', (string) config('app.timezone')));
        $this->operator = $this->centralSuperAdmin();
        $this->admin = $this->steppedUpCentralHeaders($this->operator);
    }

    private function payment(BillingInvoice $invoice): BillingPayment
    {
        $this->endTenancy();

        return BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->getKey(),
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'method' => BillingPaymentMethod::Instapay,
            'gateway' => BillingGateway::Manual,
            'status' => BillingPaymentStatus::Pending,
            'paid_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function verify(BillingPayment $payment, array $body): TestResponse
    {
        return $this->postJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify', $body, $this->admin);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function issue(PlanChangeRequest $row, array $body = []): BillingInvoice
    {
        $id = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', $body, $this->admin)
            ->assertCreated()->json('data.invoice.id');

        return BillingInvoice::query()->findOrFail($id);
    }

    private function makeFounder(Tenant $tenant): void
    {
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['is_founder' => true, 'founder_price_until' => now()->addMonths(6)]);
    }

    private function holdThenRelease(PlanChangeRequest $row, array $body = []): BillingInvoice
    {
        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.upgrade_held_for_founder_review');

        return $this->issue($row, ['release_founder_hold' => true] + $body);
    }

    // ------------------------------------------------------------------ 1. founder claim

    public function test_a_console_full_cycle_upgrade_at_the_founder_price_claims_the_founder_slot(): void
    {
        $plan = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $tenant = $this->tenantOn($this->plan(), ['status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);
        $slots = app(FounderPricingService::class)->claimedSlots();

        $id = (int) $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices', [
            'type' => 'upgrade', 'plan_id' => $plan->getKey(), 'billing_cycle' => 'monthly', 'use_founder_price' => true,
        ], $this->admin)->assertCreated()->json('data.id');
        $invoice = BillingInvoice::query()->findOrFail($id);
        $this->assertSame('699.000', $invoice->lines[0]['amount']);
        $this->assertTrue($invoice->lines[0]['founder']);
        $this->assertArrayNotHasKey('founder_excluded', $invoice->lines[0]);

        $this->verify($this->payment($invoice), $this->verifyBody())->assertOk();

        $this->assertTrue((bool) Subscription::query()->where('tenant_id', $tenant->getKey())->sole()->is_founder);
        $this->assertSame($slots + 1, app(FounderPricingService::class)->claimedSlots());
    }

    public function test_a_difference_invoice_never_claims_a_founder_slot(): void
    {
        $small = $this->plan(['price_monthly' => '499.000', 'founder_price_monthly' => '299.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $slots = app(FounderPricingService::class)->claimedSlots();

        $invoice = $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]));
        $this->verify($this->payment($invoice), $this->verifyBody())->assertOk();

        $this->assertFalse((bool) Subscription::query()->where('tenant_id', $tenant->getKey())->sole()->is_founder);
        $this->assertSame($slots, app(FounderPricingService::class)->claimedSlots());
    }

    // ------------------------------------------------------------------ 2. list price on the plan-change path

    public function test_a_released_founder_upgrade_with_renewal_bills_the_renewal_at_the_list_price(): void
    {
        $this->setTaxRate('14.000');
        $small = $this->plan(['price_monthly' => '499.000', 'founder_price_monthly' => '299.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000', 'founder_transition_price_monthly' => '849.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $this->makeFounder($tenant);

        $invoice = $this->holdThenRelease($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]), ['with_renewal' => true]);

        // Difference: (999 - 499) x 10 / 30 = 166.667 (list prices); renewal: list 999, never 699 / 849.
        $this->assertCount(2, $invoice->lines);
        $this->assertSame('166.667', $invoice->lines[0]['amount']);
        $this->assertSame('999.000', $invoice->lines[1]['amount']);
        $this->assertFalse($invoice->lines[1]['founder']);
        $this->assertSame('list', $invoice->lines[1]['price_step']);
        $this->assertTrue($invoice->lines[1]['founder_excluded']);
        // 1165.667 + 14% (163.193) = 1328.860
        $this->assertSame('1328.860', $invoice->total);
    }

    public function test_a_lapsed_founder_full_cycle_plan_change_is_billed_at_the_list_price(): void
    {
        $small = $this->plan(['price_monthly' => '499.000', 'founder_price_monthly' => '299.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $this->makeFounder($tenant);
        // The paid term lapsed: no running term, the founder schedule itself is still on.
        Tenant::query()->whereKey($tenant->getKey())->update(['subscription_ends_at' => now()->subDays(3), 'status' => 'past_due']);
        $slots = app(FounderPricingService::class)->claimedSlots();

        $invoice = $this->holdThenRelease($this->requestRow(Tenant::query()->findOrFail($tenant->getKey()), ['requested_plan_id' => $big->getKey()]));

        $this->assertCount(1, $invoice->lines);
        $this->assertSame('999.000', $invoice->lines[0]['amount']);
        $this->assertFalse($invoice->lines[0]['founder']);
        $this->assertTrue($invoice->lines[0]['founder_excluded']);

        $this->verify($this->payment($invoice), $this->verifyBody())->assertOk();
        $this->assertSame($slots, app(FounderPricingService::class)->claimedSlots());
    }

    // ------------------------------------------------------------------ 3. release guard

    public function test_releasing_a_founder_hold_on_a_request_that_was_never_held_is_a_coded_409(): void
    {
        $tenant = $this->subscribedTenant($this->plan(['price_monthly' => '499.000']), 10);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $this->plan(['price_monthly' => '999.000'])->getKey()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['release_founder_hold' => true], $this->admin)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.founder_hold_not_set')
            ->assertJsonPath('message', __('billing.errors.founder_hold_not_set'))
            ->assertJsonPath('details.request_id', $row->getKey());

        $this->assertSame(0, BillingInvoice::query()->count());
        $this->assertTrue($row->fresh()?->isPending());
    }

    // ------------------------------------------------------------------ 4. actual period days

    public function test_a_clamped_month_jan_31_to_feb_28_is_priced_on_its_28_actual_days(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $start = CarbonImmutable::parse('2027-01-31 12:00:00', (string) config('app.timezone'));
        $end = $start->addMonthNoOverflow(); // 2027-02-28 12:00
        $tenant = $this->tenantOn($small, ['status' => 'active', 'subscription_ends_at' => $end]);
        Subscription::query()->create(['tenant_id' => $tenant->getKey(), 'plan_id' => $small->getKey(), 'billing_cycle' => 'monthly', 'status' => 'active', 'amount' => '499.000', 'starts_at' => $start, 'ends_at' => $end]);
        $this->pendingInvoice($tenant, [
            'type' => 'renewal', 'status' => 'paid', 'billing_cycle' => 'monthly', 'period_start' => $start, 'period_end' => $end,
            'lines' => [['type' => 'plan', 'plan_id' => $small->getKey(), 'plan_name' => $small->name, 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => '499.000', 'amount' => '499.000', 'founder' => false]],
            'subtotal' => '499.000', 'total' => '499.000',
        ]);

        $this->travelTo(CarbonImmutable::parse('2027-02-18 12:00:00', (string) config('app.timezone')));
        $this->admin = $this->steppedUpCentralHeaders($this->operator);

        $invoice = $this->issue($this->requestRow(Tenant::query()->findOrFail($tenant->getKey()), ['requested_plan_id' => $big->getKey()]));

        // 500 x 10 / 28 = 178.5714.. -> 178.571 (end minus one month would say 31 days: 161.290).
        $this->assertSame('178.571', $invoice->subtotal);
        $this->assertSame(28, $invoice->lines[0]['period_days']);
        $this->assertSame(10, $invoice->lines[0]['remaining_days']);
    }

    public function test_without_a_paid_full_cycle_invoice_the_period_falls_back_to_end_minus_one_cycle(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10); // Nov 6 -> Dec 6 = 30 days, no invoice

        $invoice = $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]));

        $this->assertSame(30, $invoice->lines[0]['period_days']);
        $this->assertSame('166.667', $invoice->subtotal);
    }

    // ------------------------------------------------------------------ 5. term already ended

    public function test_a_transfer_verified_after_its_term_ended_is_flagged(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'read_only', 'status_changed_at' => now()->subDays(50), 'subscription_ends_at' => now()->subDays(60)]);
        $invoice = $this->pendingInvoice($tenant, [
            'type' => 'renewal', 'billing_cycle' => 'monthly', 'issued_at' => now()->subDays(40),
            'lines' => [['type' => 'plan', 'plan_id' => $plan->getKey(), 'plan_name' => $plan->name, 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => '100.000', 'amount' => '100.000', 'founder' => false]],
        ]);
        $payment = $this->payment($invoice);

        // Transfer 35 days ago (Cairo summer time, +03:00) -> a one-month term that ended 4 days ago.
        $this->verify($payment, ['transferred_at' => '2026-10-22T12:00:00+03:00'])
            ->assertOk()
            ->assertJsonPath('data.term_already_ended', true);

        $this->assertSame('2026-11-22T12:00:00+02:00', Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at?->toIso8601String());
        $this->assertTrue($payment->refresh()->raw_payload['term_already_ended']);
        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::SubscriptionActivated->value)->latest('id')->firstOrFail();
        $this->assertTrue($audit->properties['term_already_ended']);
    }

    public function test_a_normal_verification_is_not_flagged(): void
    {
        $plan = $this->plan();
        $tenant = $this->subscribedTenant($plan, 10);
        $invoice = $this->pendingInvoice($tenant, [
            'type' => 'renewal', 'billing_cycle' => 'monthly',
            'lines' => [['type' => 'plan', 'plan_id' => $plan->getKey(), 'plan_name' => $plan->name, 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => '100.000', 'amount' => '100.000', 'founder' => false]],
        ]);

        $this->verify($this->payment($invoice), $this->verifyBody())->assertOk()->assertJsonPath('data.term_already_ended', false);
    }

    // ------------------------------------------------------------------ 6. record payment error key

    public function test_a_record_payment_transfer_error_is_reported_on_paid_at(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);

        try {
            app(RecordManualPaymentAction::class)->execute(new RecordManualPaymentDTO(
                invoiceId: (int) $invoice->getKey(),
                method: BillingPaymentMethod::Cash,
                amount: '100.000',
                reference: null,
                note: null,
                paidAt: CarbonImmutable::now()->addHour(),
            ), $this->operator);
            $this->fail('A future paid_at must be refused.');
        } catch (ValidationException $e) {
            $this->assertSame(['paid_at'], array_keys($e->errors()));
            $this->assertSame(__('billing.errors.transferred_at_future'), $e->errors()['paid_at'][0]);
        }

        $this->assertSame(0, BillingPayment::query()->count());
        $this->assertSame('pending', $invoice->refresh()->status->value);
    }

    // ------------------------------------------------------------------ 7. lock order

    public function test_issuing_still_answers_a_missing_request_with_404_and_a_settled_one_with_409(): void
    {
        $tenant = $this->subscribedTenant($this->plan(['price_monthly' => '499.000']), 10);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $this->plan(['price_monthly' => '999.000'])->getKey()]);

        $this->postJson(self::QUEUE.'/999999/invoice', [], $this->admin)->assertNotFound();
        $this->issue($row);
        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)
            ->assertStatus(409)->assertJsonPath('error_code', 'billing.request_not_pending');
        $this->assertSame(1, BillingInvoice::query()->count());
        $this->assertInstanceOf(Plan::class, Plan::query()->find($row->requested_plan_id));
    }
}
