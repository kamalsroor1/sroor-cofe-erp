<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\BillingInvoice;
use App\Models\CentralUser;
use App\Models\PlanChangeRequest;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232: a shop with no paid term running (a trial, or an expired term) asks to move to a paid plan.
 * There is no remainder to price a "difference" on, so "issue invoice" with no amount answers the
 * request with a regular full-cycle plan invoice; a typed amount is still refused with a
 * machine-readable 409 that is NOT "already processed" (the request stays pending).
 */
#[Group('billing')]
final class TrialPlanChangeInvoiceTest extends PlanChangeTestCase
{
    private const QUEUE = '/api/v1/super-admin/billing/plan-change-requests';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = $this->centralSuperAdmin();
        $this->admin = $this->steppedUpCentralHeaders($this->operator);
    }

    private function trialTenant(int $daysLeft = 5): Tenant
    {
        return $this->tenantOn($this->plan(['name' => 'Trial']), [
            'status' => 'trial',
            'trial_ends_at' => now()->addDays($daysLeft),
            'subscription_ends_at' => null,
        ]);
    }

    public function test_a_typed_difference_for_a_trial_is_a_coded_409_and_the_request_stays_pending(): void
    {
        $big = $this->plan(['name' => 'Big']);
        $tenant = $this->trialTenant();
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['amount' => '120'], $this->admin)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'billing.difference_needs_active_term')
            ->assertJsonPath('message', __('billing.errors.difference_needs_active_term'));

        $this->assertTrue($row->fresh()->isPending());
        $this->assertSame(0, BillingInvoice::query()->count());
    }

    public function test_the_queue_tells_the_console_whether_a_paid_term_is_running(): void
    {
        $big = $this->plan();
        $trial = $this->trialTenant();
        $paying = $this->subscribedTenant($this->plan());
        $trialRow = $this->requestRow($trial, ['requested_plan_id' => $big->getKey()]);
        $this->requestRow($paying, ['requested_plan_id' => $big->getKey()]);

        $this->getJson(self::QUEUE.'?tenant_id='.$trial->getKey(), $this->admin)
            ->assertOk()
            ->assertJsonPath('data.0.id', $trialRow->getKey())
            ->assertJsonPath('data.0.tenant.has_paid_term', false);
        $this->getJson(self::QUEUE.'?tenant_id='.$paying->getKey(), $this->admin)
            ->assertOk()
            ->assertJsonPath('data.0.tenant.has_paid_term', true);
    }

    public function test_issuing_without_an_amount_for_a_trial_creates_a_full_monthly_plan_invoice(): void
    {
        $this->setTaxRate('14.000');
        $big = $this->plan(['name' => 'Big', 'price_monthly' => '999.000', 'price_yearly' => '9990.000']);
        $tenant = $this->trialTenant();
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey(), 'billing_cycle' => 'monthly']);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['notes' => 'trial upgrade'], $this->admin)
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'invoiced')
            ->assertJsonPath('data.invoice.total', '1138.860');

        $invoice = BillingInvoice::query()->findOrFail($row->fresh()->billing_invoice_id);
        $this->assertSame('upgrade', $invoice->type->value);
        $this->assertSame($tenant->getKey(), $invoice->tenant_id);
        $this->assertSame('monthly', $invoice->billing_cycle?->value);
        $this->assertSame('999.000', $invoice->subtotal);
        $this->assertSame('0.000', $invoice->discount);
        $this->assertSame('139.860', $invoice->tax);
        $this->assertSame('1138.860', $invoice->total);
        $this->assertSame('trial upgrade', $invoice->notes);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame('plan', $invoice->lines[0]['type']);
        $this->assertSame($big->getKey(), $invoice->lines[0]['plan_id']);
        $this->assertSame('999.000', $invoice->lines[0]['unit_price']);
        $this->assertArrayNotHasKey('difference', $invoice->lines[0], 'a trial has no term to price a difference on');
        $this->assertNotNull($invoice->period_end);
    }

    public function test_a_yearly_trial_request_is_billed_at_the_yearly_plan_price(): void
    {
        $big = $this->plan(['price_monthly' => '999.000', 'price_yearly' => '9990.500']);
        $tenant = $this->trialTenant();
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey(), 'billing_cycle' => 'yearly']);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)->assertCreated();

        $invoice = BillingInvoice::query()->findOrFail($row->fresh()->billing_invoice_id);
        $this->assertSame('yearly', $invoice->billing_cycle?->value);
        $this->assertSame('9990.500', $invoice->subtotal);
        $this->assertSame('9990.500', $invoice->total);
    }

    public function test_an_expired_term_without_an_amount_also_gets_a_full_plan_invoice(): void
    {
        $small = $this->plan();
        $big = $this->plan(['price_monthly' => '650.250']);
        $tenant = $this->tenantOn($small, ['status' => 'read_only', 'status_changed_at' => now()->subDay(), 'subscription_ends_at' => now()->subDays(3)]);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)->assertCreated();

        $invoice = BillingInvoice::query()->findOrFail($row->fresh()->billing_invoice_id);
        $this->assertSame('650.250', $invoice->total);
        $this->assertArrayNotHasKey('difference', $invoice->lines[0]);
    }

    public function test_an_open_plan_invoice_blocks_the_full_invoice_and_keeps_the_request_pending(): void
    {
        $big = $this->plan();
        $tenant = $this->trialTenant(2);
        // The auto-issued trial-end invoice (SELL-06) is still unpaid.
        $open = $this->pendingInvoice($tenant, ['type' => 'plan', 'billing_cycle' => 'monthly']);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.invoice_duplicate')
            ->assertJsonPath('details.open_invoice', true)
            ->assertJsonPath('details.invoice_id', $open->getKey());

        $this->assertTrue($row->fresh()->isPending());
        $this->assertSame(1, BillingInvoice::query()->count());
    }

    public function test_paying_the_trial_upgrade_invoice_switches_the_plan_and_keeps_the_rest_of_the_trial(): void
    {
        $big = $this->plan(['max_users' => 50]);
        $tenant = $this->trialTenant();

        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonPath('data.subscription.has_paid_term', false);

        $requestId = (int) $this->postJson('/api/v1/billing/plan-change-requests', ['kind' => 'plan_change', 'requested_plan_code' => $big->slug, 'billing_cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertCreated()->json('data.id');
        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$requestId.'/invoice', [], $this->admin)
            ->assertCreated()->json('data.invoice.id');
        $invoice = BillingInvoice::query()->findOrFail($invoiceId);

        $paymentId = (int) $this->post('/api/v1/billing/invoices/'.$invoiceId.'/payments', [
            'receipt' => $this->receipt(),
            'method' => 'vodafone_cash',
            'amount' => $invoice->total,
            'payer_reference' => '01000000888',
            'paid_at' => now()->toDateString(),
        ], $this->multipart($this->tenantHeaders($tenant)))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->admin)->assertOk();

        $tenant = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame($big->getKey(), $tenant->plan_id);
        $this->assertSame('active', $tenant->status);
        $this->assertNotNull($tenant->subscription_ends_at);
        // #232 review: like the trial-end invoice, paying early keeps the 5 trial days left: the paid
        // month starts at trial end, not today.
        $this->assertNotNull($invoice->period_start);
        $this->assertLessThan(120, abs(now()->addDays(5)->diffInSeconds($invoice->period_start, true)));
        $this->assertLessThan(120, abs(now()->addDays(5)->addMonthNoOverflow()->diffInSeconds($tenant->subscription_ends_at, true)));
        $this->assertSame('paid', $invoice->fresh()->status->value);
        $this->assertSame('completed', PlanChangeRequest::query()->findOrFail($requestId)->status->value);

        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonPath('data.subscription.has_paid_term', true);
    }
}
