<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\CentralAuditEvent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Subscription;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Group;

/**
 * OPS-20: POST /api/v1/super-admin/billing-invoices/{invoice}/record-payment.
 */
#[Group('billing')]
final class RecordManualPaymentApiTest extends BillingTestCase
{
    private CentralUser $operator;

    /** @var array<string, string> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = $this->centralSuperAdmin();
        $this->admin = $this->steppedUpCentralHeaders($this->operator);
    }

    private function url(int $invoiceId): string
    {
        return '/api/v1/super-admin/billing-invoices/'.$invoiceId.'/record-payment';
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function body(string $amount, array $extra = []): array
    {
        return array_merge([
            'amount' => $amount,
            'method' => 'cash',
            'reference' => 'WA-1',
            'note' => 'Paid in person',
            'paid_at' => now()->toDateString(),
        ], $extra);
    }

    private function planInvoice(Tenant $tenant, int $planId): int
    {
        return (int) $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices', [
            'type' => 'plan', 'plan_id' => $planId, 'billing_cycle' => 'monthly',
        ], $this->admin)->assertCreated()->json('data.id');
    }

    public function test_it_records_a_verified_payment_pays_the_invoice_and_activates_once(): void
    {
        $plan = $this->plan(['price_monthly' => '449.000']);
        $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(3), 'subscription_ends_at' => null]);
        $invoiceId = $this->planInvoice($tenant, $plan->id);

        $this->postJson($this->url($invoiceId), $this->body('449.000'), $this->admin)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.value', 'paid')
            ->assertJsonPath('data.remaining', '0.000');

        $payment = BillingPayment::query()->where('billing_invoice_id', $invoiceId)->sole();
        $this->assertSame('verified', $payment->status->value);
        $this->assertSame((int) $this->operator->getKey(), $payment->verified_by);
        $this->assertSame('operator', $payment->raw_payload['source']);
        $this->assertSame('active', Tenant::query()->findOrFail($tenant->getKey())->status);
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingPaymentRecorded->value)->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingPaymentVerified->value)->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::SubscriptionActivated->value)->count());

        // Repeating the request cannot activate or pay twice.
        $this->postJson($this->url($invoiceId), $this->body('449.000'), $this->admin)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.invoice_not_payable');
        $this->assertSame(1, BillingPayment::query()->where('billing_invoice_id', $invoiceId)->count());
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::SubscriptionActivated->value)->count());
    }

    public function test_a_partial_payment_keeps_the_invoice_open_and_the_rest_closes_it(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);

        $this->postJson($this->url($invoice->id), $this->body('40.000'), $this->admin)
            ->assertOk()
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.paid_amount', '40.000')
            ->assertJsonPath('data.remaining', '60.000');

        $this->postJson($this->url($invoice->id), $this->body('60.001'), $this->admin)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.amount_out_of_range')
            ->assertJsonPath('details.remaining', '60.000');

        $this->postJson($this->url($invoice->id), $this->body('60.000', ['method' => 'bank_transfer']), $this->admin)
            ->assertOk()
            ->assertJsonPath('data.status.value', 'paid')
            ->assertJsonPath('data.remaining', '0.000');

        $this->assertSame(2, BillingPayment::query()->where('billing_invoice_id', $invoice->id)->where('status', 'verified')->count());
        $this->assertNotNull(BillingInvoice::query()->findOrFail($invoice->id)->paid_at);
    }

    public function test_an_amount_above_the_remaining_is_422_and_nothing_is_written(): void
    {
        $invoice = $this->pendingInvoice($this->tenantOn($this->plan()));

        $this->postJson($this->url($invoice->id), $this->body('100.001'), $this->admin)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.amount_out_of_range');

        $this->assertSame(0, BillingPayment::query()->count());
        $this->assertSame('pending', BillingInvoice::query()->findOrFail($invoice->id)->status->value);
    }

    public function test_it_refuses_while_a_shop_receipt_is_waiting_for_review(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);
        BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->id,
            'tenant_id' => $tenant->getKey(),
            'amount' => '100.000',
            'method' => 'instapay',
            'status' => 'pending',
        ]);

        $this->postJson($this->url($invoice->id), $this->body('100.000'), $this->admin)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.payment_already_pending');
    }

    public function test_validation_errors_are_422(): void
    {
        $invoice = $this->pendingInvoice($this->tenantOn($this->plan()));

        $this->postJson($this->url($invoice->id), [], $this->admin)
            ->assertStatus(422)->assertJsonValidationErrors(['amount', 'method', 'paid_at']);
        $this->postJson($this->url($invoice->id), $this->body('0'), $this->admin)
            ->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->postJson($this->url($invoice->id), $this->body('10.0001'), $this->admin)
            ->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->postJson($this->url($invoice->id), $this->body('10', ['method' => 'paymob_card']), $this->admin)
            ->assertStatus(422)->assertJsonValidationErrors(['method']);
        $this->postJson($this->url($invoice->id), $this->body('10', ['paid_at' => now()->addDays(3)->toDateString()]), $this->admin)
            ->assertStatus(422)->assertJsonValidationErrors(['paid_at']);

        $this->assertSame(0, BillingPayment::query()->count());
    }

    public function test_a_voided_invoice_cannot_be_paid_and_an_unknown_one_is_404(): void
    {
        $invoice = $this->pendingInvoice($this->tenantOn($this->plan()), ['status' => 'void', 'voided_at' => now()]);

        $this->postJson($this->url($invoice->id), $this->body('10'), $this->admin)
            ->assertStatus(409)->assertJsonPath('error_code', 'billing.invoice_not_payable');
        $this->postJson($this->url(987654), $this->body('10'), $this->admin)->assertNotFound();
    }

    public function test_without_a_recent_second_factor_it_is_403_step_up_required(): void
    {
        $invoice = $this->pendingInvoice($this->tenantOn($this->plan()));

        $this->postJson($this->url($invoice->id), $this->body('10'), $this->centralHeaders($this->operator))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertSame(0, BillingPayment::query()->count());
    }

    public function test_guests_get_401(): void
    {
        $invoice = $this->pendingInvoice($this->tenantOn($this->plan()));

        $this->postJson($this->url($invoice->id), $this->body('10'))->assertUnauthorized();
    }

    public function test_support_cannot_record_a_payment_even_with_a_recent_second_factor(): void
    {
        $invoice = $this->pendingInvoice($this->tenantOn($this->plan()));
        $support = $this->steppedUpCentralHeaders($this->centralSupport());

        $this->postJson($this->url($invoice->id), $this->body('10'), $support)->assertForbidden();

        $this->assertSame(0, BillingPayment::query()->count());
    }

    public function test_a_tenant_token_cannot_reach_the_console_route(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);

        $this->postJson($this->url($invoice->id), $this->body('10'), $this->tenantHeaders($tenant))
            ->assertStatus(401);
    }
}
