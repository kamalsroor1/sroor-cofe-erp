<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\CentralAuditEvent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use PHPUnit\Framework\Attributes\Group;

/**
 * PRD-03 / ENTI-3.10: the super-admin side of manual billing, over HTTP.
 * Issue 201 / 409 / 422 / 401 / 403, step-up, void, listings, platform billing settings.
 */
#[Group('billing')]
final class CentralBillingApiTest extends BillingTestCase
{
    private function issueUrl(string $tenantId): string
    {
        return '/api/v1/super-admin/tenants/'.$tenantId.'/billing-invoices';
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(int $planId, array $extra = []): array
    {
        return array_merge(['type' => 'plan', 'plan_id' => $planId, 'billing_cycle' => 'monthly'], $extra);
    }

    public function test_issue_returns_201_with_the_frozen_snapshot_and_audits(): void
    {
        $plan = $this->plan(['name' => 'Pro', 'price_monthly' => '449.000']);
        $tenant = $this->tenantOn($plan);
        $operator = $this->centralSuperAdmin();

        $response = $this->postJson($this->issueUrl($tenant->getKey()), $this->planPayload($plan->id, [
            'service_lines' => [['description' => 'Training day', 'amount' => '100.000']],
            'discount' => '49.000',
            'notes' => 'Welcome',
        ]), $this->steppedUpCentralHeaders($operator));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.type.value', 'plan')
            ->assertJsonPath('data.billing_cycle.value', 'monthly')
            ->assertJsonPath('data.subtotal', '549.000')
            ->assertJsonPath('data.discount', '49.000')
            ->assertJsonPath('data.tax', '0.000')
            ->assertJsonPath('data.total', '500.000')
            ->assertJsonPath('data.remaining', '500.000')
            ->assertJsonPath('data.lines.0.type', 'plan')
            ->assertJsonPath('data.lines.1.description', 'Training day')
            ->assertJsonPath('data.notes', 'Welcome')
            ->assertJsonStructure(['data' => ['id', 'number', 'tax_rate', 'currency', 'issued_at', 'due_at', 'period_start', 'period_end', 'payments']]);

        $invoice = BillingInvoice::query()->findOrFail($response->json('data.id'));
        $this->assertSame($operator->getKey(), $invoice->issued_by);
        $this->assertSame((string) $tenant->getKey(), $invoice->tenant_id);
        $this->assertTrue($invoice->due_at->isFuture());

        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingInvoiceIssued->value)->count());
    }

    public function test_a_second_invoice_for_the_same_period_is_409_and_names_the_existing_one(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan);
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $first = $this->postJson($this->issueUrl($tenant->getKey()), $this->planPayload($plan->id), $headers)->assertCreated();

        $this->postJson($this->issueUrl($tenant->getKey()), $this->planPayload($plan->id), $headers)
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'billing.invoice_duplicate')
            ->assertJsonPath('details.invoice_id', $first->json('data.id'))
            ->assertJsonPath('details.invoice_number', $first->json('data.number'));

        $this->assertSame(1, BillingInvoice::query()->count());
    }

    public function test_a_voided_invoice_frees_the_period_for_a_new_one(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan);
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $first = $this->postJson($this->issueUrl($tenant->getKey()), $this->planPayload($plan->id), $headers)->assertCreated();
        $this->postJson('/api/v1/super-admin/billing-invoices/'.$first->json('data.id').'/void', [], $headers)
            ->assertOk()->assertJsonPath('data.status.value', 'void');

        $this->postJson($this->issueUrl($tenant->getKey()), $this->planPayload($plan->id), $headers)->assertCreated();
        $this->assertNotNull(BillingInvoice::query()->findOrFail($first->json('data.id'))->voided_at);
    }

    public function test_validation_errors_are_422(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan);
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $url = $this->issueUrl($tenant->getKey());

        $this->postJson($url, [], $headers)->assertStatus(422)->assertJsonValidationErrors(['type']);
        $this->postJson($url, ['type' => 'addon'], $headers)->assertStatus(422)->assertJsonValidationErrors(['type']);
        $this->postJson($url, ['type' => 'plan'], $headers)->assertStatus(422)->assertJsonValidationErrors(['plan_id', 'billing_cycle']);
        $this->postJson($url, $this->planPayload(999999), $headers)->assertStatus(422)->assertJsonValidationErrors(['plan_id']);
        $this->postJson($url, $this->planPayload($plan->id, ['billing_cycle' => 'biennial']), $headers)->assertStatus(422)->assertJsonValidationErrors(['billing_cycle']);
        $this->postJson($url, ['type' => 'service'], $headers)->assertStatus(422)->assertJsonValidationErrors(['service_lines']);
        $this->postJson($url, ['type' => 'service', 'service_lines' => [['description' => 'x y', 'amount' => '-5']]], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['service_lines.0.amount']);
        $this->postJson($url, $this->planPayload($plan->id, ['discount' => '449.000']), $headers)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.discount_too_high');
        $this->postJson($url, $this->planPayload($plan->id, ['use_founder_price' => true]), $headers)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.founder_unavailable');

        $this->assertSame(0, BillingInvoice::query()->count());
    }

    public function test_unknown_tenant_is_404(): void
    {
        $plan = $this->plan();

        $this->postJson($this->issueUrl('does-not-exist'), $this->planPayload($plan->id), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertNotFound();
    }

    public function test_guests_get_401_on_every_billing_route(): void
    {
        $tenant = $this->createTenant();
        $routes = [
            ['GET', $this->issueUrl($tenant->getKey())],
            ['POST', $this->issueUrl($tenant->getKey())],
            ['GET', '/api/v1/super-admin/billing/payments'],
            ['GET', '/api/v1/super-admin/billing/payments/1/receipt'],
            ['POST', '/api/v1/super-admin/billing/payments/1/verify'],
            ['POST', '/api/v1/super-admin/billing/payments/1/reject'],
            ['GET', '/api/v1/super-admin/billing-invoices/1'],
            ['POST', '/api/v1/super-admin/billing-invoices/1/void'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri, [], ['Accept' => 'application/json'])->assertUnauthorized();
        }
    }

    public function test_a_tenant_token_is_401_on_the_control_plane(): void
    {
        $tenant = $this->createTenant();

        $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];

        $this->getJson('/api/v1/super-admin/billing/payments', $headers)->assertUnauthorized();
    }

    public function test_support_can_read_but_never_write_billing(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan);
        $invoice = $this->pendingInvoice($tenant);
        $support = $this->centralSupport();
        $headers = $this->steppedUpCentralHeaders($support);

        $this->getJson('/api/v1/super-admin/billing/payments', $headers)->assertOk();
        $this->getJson('/api/v1/super-admin/billing-invoices/'.$invoice->getKey(), $headers)->assertOk();
        $this->getJson($this->issueUrl($tenant->getKey()), $headers)->assertOk();

        $this->postJson($this->issueUrl($tenant->getKey()), $this->planPayload($plan->id), $headers)->assertForbidden();
        $this->postJson('/api/v1/super-admin/billing-invoices/'.$invoice->getKey().'/void', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/super-admin/billing/payments/1/verify', [], $headers)->assertForbidden();
        $this->postJson('/api/v1/super-admin/billing/payments/1/reject', ['reason' => 'nope'], $headers)->assertForbidden();
    }

    public function test_an_operator_without_any_role_is_403_even_to_read(): void
    {
        $headers = $this->centralHeaders($this->centralOperator(null));

        $this->getJson('/api/v1/super-admin/billing/payments', $headers)->assertForbidden();
    }

    public function test_writes_need_a_recent_second_factor(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan);

        $this->postJson($this->issueUrl($tenant->getKey()), $this->planPayload($plan->id), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');
    }

    public function test_tenant_invoice_list_includes_the_subscription_summary(): void
    {
        $plan = $this->plan(['name' => 'Growth']);
        $tenant = $this->tenantOn($plan);
        $this->pendingInvoice($tenant);
        $other = $this->tenantOn($plan);
        $this->pendingInvoice($other);

        $this->getJson($this->issueUrl($tenant->getKey()), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('subscription.tenant.id', $tenant->getKey())
            ->assertJsonStructure(['data' => [['id', 'number', 'status', 'total', 'remaining']], 'subscription' => ['tenant', 'plan', 'status', 'billing_cycle', 'ends_at', 'subscription_ends_at', 'is_founder'], 'meta']);
    }

    public function test_payment_queue_filters_by_status_and_validates_it(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        foreach (['pending', 'rejected'] as $status) {
            BillingPayment::query()->create([
                'billing_invoice_id' => $invoice->getKey(), 'tenant_id' => $tenant->getKey(), 'amount' => '10.000',
                'method' => 'instapay', 'gateway' => 'manual', 'status' => $status, 'paid_at' => now(),
                'raw_payload' => ['payer_reference' => '01000000999'],
            ]);
        }

        $this->getJson('/api/v1/super-admin/billing/payments?status=pending', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status.value', 'pending')
            ->assertJsonPath('data.0.invoice_number', $invoice->number)
            ->assertJsonPath('data.0.tenant.id', $tenant->getKey())
            ->assertJsonPath('data.0.payer_reference', '01000000999')
            ->assertJsonMissingPath('data.0.proof_path');

        $this->getJson('/api/v1/super-admin/billing/payments', $headers)->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/super-admin/billing/payments?status=bogus', $headers)->assertStatus(422);
    }

    public function test_platform_billing_settings_are_saved_audited_and_validated(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $url = '/api/v1/super-admin/platform-settings';

        $this->getJson($url, $headers)
            ->assertOk()
            ->assertJsonPath('data.billing_instapay_address', '')
            ->assertJsonPath('data.billing_vodafone_cash_number', '')
            ->assertJsonPath('data.billing_account_name', '')
            ->assertJsonPath('data.billing_payment_note', '')
            ->assertJsonPath('data.billing_tax_rate', '0.000');

        $this->putJson($url, [
            'billing_instapay_address' => 'dummy@instapay',
            'billing_vodafone_cash_number' => '01000000123',
            'billing_account_name' => 'Dummy Account',
            'billing_payment_note' => 'Send the exact amount.',
            'billing_tax_rate' => '14',
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.billing_instapay_address', 'dummy@instapay')
            ->assertJsonPath('data.billing_vodafone_cash_number', '01000000123')
            ->assertJsonPath('data.billing_tax_rate', '14.000');

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformSettingsUpdated->value)->latest('id')->firstOrFail();
        $this->assertContains('billing_tax_rate', $audit->properties['changed'] ?? []);

        foreach ([['billing_tax_rate' => '100.001'], ['billing_tax_rate' => '-1'], ['billing_tax_rate' => 'abc'], ['billing_tax_rate' => '1.2345'],
            ['billing_vodafone_cash_number' => 'not a phone'], ['billing_payment_note' => str_repeat('x', 501)],
            ['billing_instapay_address' => str_repeat('x', 101)], ['billing_account_name' => '<b>x</b>']] as $bad) {
            $this->putJson($url, $bad, $headers)->assertStatus(422);
        }

        // null resets a billing field back to "not set".
        $this->putJson($url, ['billing_payment_note' => null, 'billing_tax_rate' => null], $headers)
            ->assertOk()
            ->assertJsonPath('data.billing_payment_note', '')
            ->assertJsonPath('data.billing_tax_rate', '0.000');
    }
}
