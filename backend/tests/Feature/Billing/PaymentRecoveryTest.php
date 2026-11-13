<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\BillingPayment;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Group;

/**
 * SELL-07: a shop suspended for NON-PAYMENT can sign in and reach /billing only; a violation
 * suspension stays fully blocked. A shop can withdraw its own pending (wrong amount) payment and
 * resubmit, and the subscription screen shows one consistent status plus a partial-payment hint.
 */
#[Group('billing')]
final class PaymentRecoveryTest extends BillingTestCase
{
    private function suspended(string $reason): Tenant
    {
        return $this->tenantOn($this->plan(), [
            'status' => 'suspended',
            'suspension_reason' => $reason,
            'status_changed_at' => now()->subDay(),
        ]);
    }

    /** @return array<string, mixed> */
    private function login(Tenant $tenant): array
    {
        $admin = $this->tenantAdmin($tenant);

        return ['login' => $admin->email, 'password' => 'password'];
    }

    public function test_non_payment_suspended_shop_can_log_in_and_reaches_billing_only(): void
    {
        $tenant = $this->suspended('non_payment');

        $response = $this->postJson('/api/v1/auth/login', $this->login($tenant), ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertOk()
            ->assertJsonPath('data.subscription_blocked', true)
            ->assertJsonPath('data.user.permissions', ['billing.manage']);

        $headers = array_merge($this->tenantHeaders($tenant), ['Authorization' => 'Bearer '.$response->json('data.token')]);

        $this->getJson('/api/v1/billing/subscription', $headers)->assertOk();
        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)->assertCreated();
        $this->getJson('/api/v1/invoices', $headers)->assertForbidden()->assertJsonPath('error_code', 'subscription.access_blocked');
        $this->getJson('/api/v1/customers', $headers)->assertForbidden();
    }

    public function test_a_non_billing_user_of_a_non_payment_suspended_shop_gets_the_wrong_password_answer(): void
    {
        $tenant = $this->suspended('non_payment');
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $headers = ['X-Tenant' => (string) $tenant->getTenantKey()];

        $right = $this->postJson('/api/v1/auth/login', ['login' => $cashier->email, 'password' => 'password'], $headers)
            ->assertStatus(422);
        $wrong = $this->postJson('/api/v1/auth/login', ['login' => $cashier->email, 'password' => 'not-the-password'], $headers)
            ->assertStatus(422);

        // No password oracle: a correct password is indistinguishable from a wrong one.
        $this->assertSame($wrong->json(), $right->json());
        $this->assertSame((string) __('auth.failed'), $right->json('errors.login.0'));
        $this->assertArrayNotHasKey('token', (array) $right->json('data'));

        // A disabled billing manager is refused the same way.
        $disabled = $this->createTenantUser($tenant, 'admin', attributes: ['is_active' => false]);
        $this->postJson('/api/v1/auth/login', ['login' => $disabled->email, 'password' => 'password'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.login.0', (string) __('auth.failed'));
    }

    public function test_violation_suspended_shop_stays_fully_blocked(): void
    {
        $tenant = $this->suspended('violation');

        $this->postJson('/api/v1/auth/login', $this->login($tenant), ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertForbidden()
            ->assertJsonPath('error_code', 'subscription.access_blocked');
    }

    public function test_shop_cancels_its_pending_payment_and_submits_a_new_one(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);
        $headers = $this->tenantHeaders($tenant);

        $wrong = (int) $this->post('/api/v1/billing/invoices/'.$invoice->getKey().'/payments', [
            'receipt' => $this->receipt(), 'method' => 'instapay', 'amount' => '10.000', 'payer_reference' => 'ref-1', 'paid_at' => now()->toDateString(),
        ], $this->multipart($headers))->assertCreated()->json('data.id');

        $this->postJson('/api/v1/billing/payments/'.$wrong.'/cancel', [], $headers)->assertOk();
        $this->assertSame('rejected', BillingPayment::query()->findOrFail($wrong)->status->value);

        $this->post('/api/v1/billing/invoices/'.$invoice->getKey().'/payments', [
            'receipt' => $this->receipt(), 'method' => 'instapay', 'amount' => '100.000', 'payer_reference' => 'ref-2', 'paid_at' => now()->toDateString(),
        ], $this->multipart($headers))->assertCreated();

        // Already rejected: cannot be cancelled again.
        $this->postJson('/api/v1/billing/payments/'.$wrong.'/cancel', [], $headers)->assertStatus(409);
    }

    public function test_cancel_is_scoped_to_the_callers_tenant(): void
    {
        $mine = $this->tenantOn($this->plan());
        $other = $this->tenantOn($this->plan());
        $payment = BillingPayment::query()->create([
            'billing_invoice_id' => $this->pendingInvoice($other)->getKey(),
            'tenant_id' => $other->getKey(),
            'amount' => '10.000', 'method' => 'instapay', 'gateway' => 'manual', 'status' => 'pending', 'paid_at' => now(),
            'raw_payload' => ['payer_reference' => 'x'],
        ]);

        $this->postJson('/api/v1/billing/payments/'.$payment->getKey().'/cancel', [], $this->tenantHeaders($mine))->assertNotFound();
        $this->assertSame('pending', $payment->refresh()->status->value);
    }

    public function test_cancel_requires_authentication(): void
    {
        $tenant = $this->tenantOn($this->plan());

        $this->postJson('/api/v1/billing/payments/1/cancel', [], ['X-Tenant' => (string) $tenant->getTenantKey()])->assertUnauthorized();
    }
}
