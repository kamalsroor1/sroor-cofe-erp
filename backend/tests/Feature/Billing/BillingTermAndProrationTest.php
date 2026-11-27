<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232 phase 1 (owner-approved spec, sections A-C):
 *  A. a new paid term starts at the ACTUAL transfer moment entered at Verify (trial / expired);
 *     a renewal on a running term is appended after the current paid end;
 *  B. mid-term upgrade = (new - current) x remaining days (ceil) / actual period days, monthly and
 *     annual, end kept; upgrade + renewal = difference line + one full cycle appended;
 *  C. no automatic founder price on a plan change; an agreed founder price holds the request.
 * All amounts are exact decimal strings; the clock is frozen at midday on the platform clock.
 */
#[Group('billing')]
final class BillingTermAndProrationTest extends PlanChangeTestCase
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

    private function at(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment, (string) config('app.timezone'));
    }

    private function payment(BillingInvoice $invoice, BillingPaymentMethod $method = BillingPaymentMethod::Instapay): BillingPayment
    {
        $this->endTenancy();

        return BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->getKey(),
            'tenant_id' => $invoice->tenant_id,
            'amount' => $invoice->total,
            'currency' => $invoice->currency,
            'method' => $method,
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

    /** A tenant on $plan with a running YEARLY paid term ending at $end. */
    private function yearlyTenant(Plan $plan, CarbonImmutable $end): Tenant
    {
        $tenant = $this->tenantOn($plan, ['status' => 'active', 'subscription_ends_at' => $end]);
        Subscription::query()->create([
            'tenant_id' => $tenant->getKey(),
            'plan_id' => $plan->getKey(),
            'billing_cycle' => 'yearly',
            'status' => 'active',
            'amount' => $plan->price_yearly,
            'starts_at' => $end->subYearNoOverflow(),
            'ends_at' => $end,
        ]);

        return Tenant::query()->findOrFail($tenant->getKey());
    }

    private function planInvoice(Tenant $tenant, Plan $plan, string $type = 'plan'): BillingInvoice
    {
        return $this->pendingInvoice($tenant, [
            'type' => $type,
            'billing_cycle' => 'monthly',
            'lines' => [['type' => 'plan', 'plan_id' => $plan->getKey(), 'plan_name' => $plan->name, 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => '100.000', 'amount' => '100.000', 'founder' => false]],
        ]);
    }

    // ------------------------------------------------------------------ A. term start

    public function test_a_trial_tenant_paying_a_plan_invoice_starts_its_term_at_the_transfer_without_trial_days(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(9), 'subscription_ends_at' => null]);
        $payment = $this->payment($this->planInvoice($tenant, $plan));

        $this->verify($payment, ['transferred_at' => '2026-11-26T08:30:00+02:00'])
            ->assertOk()
            ->assertJsonPath('data.transferred_at', '2026-11-26T08:30:00+02:00');

        $this->assertSame('2026-12-26T08:30:00+02:00', Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at?->toIso8601String());
        $this->assertSame('2026-11-26T08:30:00+02:00', $payment->refresh()->transferred_at?->toIso8601String());
        $this->assertSame('active', Tenant::query()->findOrFail($tenant->getKey())->status);
    }

    public function test_an_expired_tenant_starts_its_new_term_at_the_transfer_in_utc_input_too(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'read_only', 'status_changed_at' => now()->subDay(), 'subscription_ends_at' => now()->subDays(5)]);
        $payment = $this->payment($this->planInvoice($tenant, $plan, 'renewal'));

        // 07:00Z = 09:00 on the platform clock (Africa/Cairo, +02:00 in November).
        $this->verify($payment, ['transferred_at' => '2026-11-26T07:00:00Z'])->assertOk();

        $this->assertSame('2026-12-26T09:00:00+02:00', Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at?->toIso8601String());
    }

    public function test_a_renewal_on_a_running_term_is_appended_after_the_current_end(): void
    {
        $plan = $this->plan();
        $tenant = $this->subscribedTenant($plan, 10); // ends 2026-12-06 12:00
        $payment = $this->payment($this->planInvoice($tenant, $plan, 'renewal'));

        $this->verify($payment, ['transferred_at' => '2026-11-26T10:00:00+02:00'])->assertOk();

        $this->assertSame('2027-01-06T12:00:00+02:00', Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at?->toIso8601String());
    }

    public function test_verifying_twice_never_extends_the_term_twice(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(3), 'subscription_ends_at' => null]);
        $payment = $this->payment($this->planInvoice($tenant, $plan));

        $this->verify($payment, ['transferred_at' => '2026-11-26T08:00:00+02:00'])->assertOk();
        $this->verify($payment, ['transferred_at' => '2026-11-26T11:00:00+02:00'])->assertOk()->assertJsonPath('data.status.value', 'verified');
        $this->verify($payment, [])->assertOk();

        $this->assertSame('2026-12-26T08:00:00+02:00', Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at?->toIso8601String());
        $this->assertSame('2026-11-26T08:00:00+02:00', $payment->refresh()->transferred_at?->toIso8601String());
    }

    public function test_transferred_at_is_required_not_future_and_not_before_the_invoice_issue_day(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(3), 'subscription_ends_at' => null]);
        $invoice = $this->planInvoice($tenant, $plan); // issued 2026-11-26 12:00
        $payment = $this->payment($invoice);

        $this->verify($payment, [])->assertStatus(422)->assertJsonValidationErrors(['transferred_at'])
            ->assertJsonPath('errors.transferred_at.0', __('billing.errors.transferred_at_required'));
        $this->verify($payment, ['transferred_at' => '2026-11-26T12:30:00+02:00'])->assertStatus(422)->assertJsonValidationErrors(['transferred_at']);
        $this->verify($payment, ['transferred_at' => 'not-a-date'])->assertStatus(422)->assertJsonValidationErrors(['transferred_at']);
        $this->verify($payment, ['transferred_at' => '2026-11-25T23:59:59+02:00'])->assertStatus(422)
            ->assertJsonPath('errors.transferred_at.0', __('billing.errors.transferred_at_before_invoice'));

        // The start of the issue day is accepted.
        $this->verify($payment, ['transferred_at' => '2026-11-26T00:00:00+02:00'])->assertOk();
        $this->assertSame('2026-12-26T00:00:00+02:00', Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at?->toIso8601String());
    }

    public function test_a_refused_transfer_date_changes_nothing(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(3), 'subscription_ends_at' => null]);
        $invoice = $this->planInvoice($tenant, $plan);
        $payment = $this->payment($invoice);

        $this->verify($payment, ['transferred_at' => '2026-11-20T10:00:00+02:00'])->assertStatus(422);

        $this->assertSame('pending', $payment->refresh()->status->value);
        $this->assertNull($payment->transferred_at);
        $this->assertSame('pending', $invoice->refresh()->status->value);
        $this->assertNull(Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at);
    }

    public function test_recording_a_payment_dated_before_the_invoice_day_is_refused(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(3), 'subscription_ends_at' => null]);
        $invoice = $this->planInvoice($tenant, $plan);

        $this->postJson('/api/v1/super-admin/billing-invoices/'.$invoice->getKey().'/record-payment', [
            'amount' => '100.000', 'method' => 'instapay', 'paid_at' => '2026-11-25',
        ], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['paid_at']);

        $this->assertSame(0, BillingPayment::query()->count());
    }

    public function test_verify_and_preview_compare_issue_day_in_cairo_timezone_even_when_app_timezone_is_utc(): void
    {
        $prevAppTz = (string) config('app.timezone');
        $prevPhpTz = date_default_timezone_get();

        try {
            config(['app.timezone' => 'UTC', 'billing.timezone' => 'Africa/Cairo']);
            date_default_timezone_set('UTC');
            Carbon::setTestNow(CarbonImmutable::parse('2026-11-27 12:00:00', 'Africa/Cairo'));
            $admin = $this->steppedUpCentralHeaders($this->operator);

            $plan = $this->plan();
            $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(3), 'subscription_ends_at' => null]);
            $invoice = $this->planInvoice($tenant, $plan);

            // Day before in Cairo (2026-11-26 23:59:59+02:00) is refused on both preview and verify.
            $paymentBefore = $this->payment($invoice);
            $this->getJson('/api/v1/super-admin/billing/payments/'.$paymentBefore->getKey().'/verify-preview?transferred_at='.urlencode('2026-11-26T23:59:59+02:00'), $admin)
                ->assertStatus(422)
                ->assertJsonPath('errors.transferred_at.0', __('billing.errors.transferred_at_before_invoice'));
            $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentBefore->getKey().'/verify', ['transferred_at' => '2026-11-26T23:59:59+02:00'], $admin)
                ->assertStatus(422)
                ->assertJsonPath('errors.transferred_at.0', __('billing.errors.transferred_at_before_invoice'));

            // 00:00, 01:59:59 and 02:00 Cairo on the issue day are all valid on preview and verify.
            foreach (['2026-11-27T00:00:00+02:00', '2026-11-27T01:59:59+02:00', '2026-11-27T02:00:00+02:00'] as $moment) {
                $this->getJson('/api/v1/super-admin/billing/payments/'.$paymentBefore->getKey().'/verify-preview?transferred_at='.urlencode($moment), $admin)
                    ->assertOk();
            }

            $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentBefore->getKey().'/verify', ['transferred_at' => '2026-11-27T01:59:59+02:00'], $admin)
                ->assertOk();
        } finally {
            Carbon::setTestNow();
            config(['app.timezone' => $prevAppTz]);
            date_default_timezone_set($prevPhpTz);
        }
    }

    // ------------------------------------------------------------------ B. proration

    public function test_a_monthly_mid_term_upgrade_bills_the_remaining_days_difference_plus_vat(): void
    {
        $this->setTaxRate('14.000');
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10); // 10 of the 30 days (Nov 6 -> Dec 6) left
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $invoice = $this->issue($row);

        // (999 - 499) x 10 / 30 = 166.6666.. -> 166.667; VAT 14% = 23.33338 -> 23.333.
        $this->assertCount(1, $invoice->lines);
        $this->assertTrue($invoice->lines[0]['difference']);
        $this->assertSame('166.667', $invoice->lines[0]['amount']);
        $this->assertSame('166.667', $invoice->subtotal);
        $this->assertSame('23.333', $invoice->tax);
        $this->assertSame('190.000', $invoice->total);
        $this->assertSame('2026-12-06T12:00:00+02:00', $invoice->period_end?->toIso8601String());
    }

    public function test_a_started_day_counts_as_a_whole_remaining_day(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $end = $this->at('2026-12-06 18:00:00'); // 10.25 days left -> 11; period Nov 6 18:00 -> Dec 6 = 30 days
        $tenant = $this->tenantOn($small, ['status' => 'active', 'subscription_ends_at' => $end]);
        Subscription::query()->create(['tenant_id' => $tenant->getKey(), 'plan_id' => $small->getKey(), 'billing_cycle' => 'monthly', 'status' => 'active', 'amount' => '499.000', 'starts_at' => $end->subMonth(), 'ends_at' => $end]);
        $row = $this->requestRow(Tenant::query()->findOrFail($tenant->getKey()), ['requested_plan_id' => $big->getKey()]);

        // 500 x 11 / 30 = 183.3333.. -> 183.333
        $this->assertSame('183.333', $this->issue($row)->subtotal);
    }

    public function test_an_annual_mid_term_upgrade_uses_the_actual_days_of_the_year(): void
    {
        $this->setTaxRate('14.000');
        $small = $this->plan(['price_yearly' => '4990.000']);
        $big = $this->plan(['price_yearly' => '9990.000']);
        $tenant = $this->yearlyTenant($small, $this->at('2027-03-06 12:00:00')); // 100 of 365 days left
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey(), 'billing_cycle' => 'yearly']);

        $invoice = $this->issue($row);

        // (9990 - 4990) x 100 / 365 = 1369.8630.. -> 1369.863; VAT 14% = 191.78082 -> 191.781.
        $this->assertSame('1369.863', $invoice->subtotal);
        $this->assertSame('191.781', $invoice->tax);
        $this->assertSame('1561.644', $invoice->total);
        $this->assertSame('yearly', $invoice->billing_cycle?->value);
        $this->assertSame('2027-03-06T12:00:00+02:00', $invoice->period_end?->toIso8601String());
    }

    public function test_upgrade_only_keeps_the_period_end_and_switches_the_plan_on_verify(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $invoice = $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]));

        $this->verify($this->payment($invoice), ['transferred_at' => '2026-11-26T11:00:00+02:00'])->assertOk();

        $tenant = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame($big->getKey(), $tenant->plan_id);
        $this->assertSame('2026-12-06T12:00:00+02:00', $tenant->subscription_ends_at?->toIso8601String());
        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->sole();
        $this->assertSame('monthly', $subscription->billing_cycle->value);
        $this->assertSame('999.000', $subscription->amount);
        $this->assertSame('2026-12-06T12:00:00+02:00', $subscription->ends_at->toIso8601String());
    }

    public function test_upgrade_plus_renewal_bills_two_lines_and_extends_by_one_new_plan_cycle(): void
    {
        $this->setTaxRate('14.000');
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'name' => 'Big']);
        $tenant = $this->subscribedTenant($small, 10);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $invoice = $this->issue($row, ['with_renewal' => true]);

        $this->assertSame('upgrade', $invoice->type->value);
        $this->assertCount(2, $invoice->lines);
        $this->assertTrue($invoice->lines[0]['difference']);
        $this->assertSame('166.667', $invoice->lines[0]['amount']);
        $this->assertTrue($invoice->lines[1]['renewal']);
        $this->assertArrayNotHasKey('difference', $invoice->lines[1]);
        $this->assertSame('999.000', $invoice->lines[1]['amount']);
        $this->assertFalse($invoice->lines[1]['founder']);
        // 1165.667 + 14% (163.19338 -> 163.193) = 1328.860
        $this->assertSame('1165.667', $invoice->subtotal);
        $this->assertSame('163.193', $invoice->tax);
        $this->assertSame('1328.860', $invoice->total);
        $this->assertSame('2026-12-06T12:00:00+02:00', $invoice->period_start?->toIso8601String());
        $this->assertSame('2027-01-06T12:00:00+02:00', $invoice->period_end?->toIso8601String());

        $this->verify($this->payment($invoice), ['transferred_at' => '2026-11-26T11:00:00+02:00'])->assertOk();

        $tenant = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame($big->getKey(), $tenant->plan_id);
        $this->assertSame('2027-01-06T12:00:00+02:00', $tenant->subscription_ends_at?->toIso8601String());
        $this->assertSame('999.000', Subscription::query()->where('tenant_id', $tenant->getKey())->sole()->amount);
        $this->assertSame('completed', $row->fresh()?->status->value);
    }

    public function test_upgrade_plus_renewal_is_refused_when_a_renewal_moved_the_term_first(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $combined = $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]), ['with_renewal' => true]);

        // A renewal paid in between moves the term the difference priced.
        $renewal = $this->planInvoice($tenant, $small, 'renewal');
        $this->verify($this->payment($renewal), ['transferred_at' => '2026-11-26T09:00:00+02:00'])->assertOk();

        $this->verify($this->payment($combined), ['transferred_at' => '2026-11-26T09:30:00+02:00'])
            ->assertStatus(409)->assertJsonPath('error_code', 'billing.difference_term_changed');
        $this->assertSame($small->getKey(), Tenant::query()->findOrFail($tenant->getKey())->plan_id);
    }

    // ------------------------------------------------------------------ C. founder price

    public function test_a_plan_change_never_gets_the_founder_price_automatically(): void
    {
        $this->assertGreaterThan(0, app(FounderPricingService::class)->remainingSlots());
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $tenant = $this->tenantOn($this->plan(), ['status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);
        $slotsBefore = app(FounderPricingService::class)->claimedSlots();

        $invoice = $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]));

        $this->assertSame('999.000', $invoice->lines[0]['amount']);
        $this->assertFalse($invoice->lines[0]['founder']);

        $this->verify($this->payment($invoice), ['transferred_at' => '2026-11-26T10:00:00+02:00'])->assertOk();
        $this->assertFalse((bool) Subscription::query()->where('tenant_id', $tenant->getKey())->sole()->is_founder);
        $this->assertSame($slotsBefore, app(FounderPricingService::class)->claimedSlots());
    }

    public function test_a_tenant_with_an_active_founder_schedule_is_invoiced_automatically_without_hold(): void
    {
        $small = $this->plan(['price_monthly' => '499.000', 'founder_price_monthly' => '299.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '599.000']);
        $tenant = $this->subscribedTenant($small, 10);
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['is_founder' => true, 'founder_price_until' => now()->addMonths(6)]);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        // Founder upgrades bill automatically using the founder schedule (no 409 manual-review hold):
        // (599 - 299) * 10 / 30 = 100.000.
        $invoice = $this->issue($row);
        $this->assertSame('100.000', $invoice->subtotal);
        $this->assertTrue((bool) $invoice->lines[0]['founder']);
        $fresh = PlanChangeRequest::query()->findOrFail($row->getKey());
        $this->assertSame('invoiced', $fresh->status->value);
        $this->assertNull($fresh->founder_review_held_at);

        // Passing optional release_founder_hold / explicit agreed amount still works cleanly.
        $row2 = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);
        $invoice2 = $this->issue($row2, ['release_founder_hold' => true, 'amount' => '120.000']);
        $this->assertSame('120.000', $invoice2->subtotal);
        $this->assertSame('invoiced', PlanChangeRequest::query()->findOrFail($row2->getKey())->status->value);
    }

    public function test_an_ended_founder_schedule_is_not_held(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        // founder (12 months) + transition (config) both over.
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['is_founder' => true, 'founder_price_until' => now()->subYears(3)]);

        $this->issue($this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]));
        $this->assertSame(1, BillingInvoice::query()->count());
    }

    public function test_issue_options_must_be_booleans(): void
    {
        $tenant = $this->subscribedTenant($this->plan(), 10);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $this->plan()->getKey()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['with_renewal' => 'maybe', 'release_founder_hold' => 'x'], $this->admin)
            ->assertStatus(422)->assertJsonValidationErrors(['with_renewal', 'release_founder_hold']);
    }
}
