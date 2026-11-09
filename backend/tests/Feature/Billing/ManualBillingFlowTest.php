<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\LimitResource;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Entitlements\TenantEntitlementService;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Group;

/**
 * PRD-03 end to end: super-admin issues -> shop uploads the receipt -> super-admin verifies
 * (or rejects) -> subscription, tenant status and entitlements follow, exactly once.
 */
#[Group('billing')]
final class ManualBillingFlowTest extends BillingTestCase
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

    private function issuePlanInvoice(Tenant $tenant, int $planId, string $cycle = 'monthly', string $type = 'plan'): int
    {
        return (int) $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices', [
            'type' => $type, 'plan_id' => $planId, 'billing_cycle' => $cycle,
        ], $this->admin)->assertCreated()->json('data.id');
    }

    private function submit(Tenant $tenant, int $invoiceId, string $amount): int
    {
        return (int) $this->post('/api/v1/billing/invoices/'.$invoiceId.'/payments', [
            'receipt' => $this->receipt(),
            'method' => 'vodafone_cash',
            'amount' => $amount,
            'payer_reference' => '01000000888',
            'paid_at' => now()->toDateString(),
        ], $this->multipart($this->tenantHeaders($tenant)))->assertCreated()->json('data.id');
    }

    private function assertAbout(CarbonInterface $expected, ?CarbonInterface $actual, int $seconds = 120): void
    {
        $this->assertNotNull($actual);
        $this->assertLessThanOrEqual($seconds, abs($expected->diffInSeconds($actual, true)), 'Expected ~'.$expected->toIso8601String().' got '.$actual->toIso8601String());
    }

    public function test_upload_then_verify_activates_the_new_plan_exactly_once(): void
    {
        $small = $this->plan(['max_users' => 2, 'price_monthly' => '100.000']);
        $big = $this->plan(['max_users' => 50, 'price_monthly' => '449.000']);
        $tenant = $this->tenantOn($small, ['status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);

        $this->assertSame(2, app(TenantEntitlementService::class)->entitlements($tenant)->limit(LimitResource::Users));

        $invoiceId = $this->issuePlanInvoice($tenant, $big->id);
        $paymentId = $this->submit($tenant, $invoiceId, '449.000');

        $url = '/api/v1/super-admin/billing/payments/'.$paymentId.'/verify';
        $this->postJson($url, [], $this->admin)
            ->assertOk()
            ->assertJsonPath('data.status.value', 'verified')
            ->assertJsonPath('data.reviewed_by.id', $this->operator->getKey());

        $tenant = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame('active', $tenant->status);
        $this->assertSame($big->id, $tenant->plan_id);
        $firstEnd = $tenant->subscription_ends_at;
        $this->assertAbout(now()->addMonth(), $firstEnd);

        $invoice = BillingInvoice::query()->findOrFail($invoiceId);
        $this->assertSame('paid', $invoice->status->value);
        $this->assertNotNull($invoice->paid_at);

        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($big->id, $subscription->plan_id);
        $this->assertSame('449.000', $subscription->amount);
        $this->assertAbout($firstEnd, $subscription->ends_at, 2);
        $this->assertSame($subscription->getKey(), $invoice->subscription_id);

        // Verifying again (double click, retry) changes nothing: one extension only.
        $this->postJson($url, [], $this->admin)->assertOk()->assertJsonPath('data.status.value', 'verified');
        $tenant->refresh();
        $this->assertSame($firstEnd->toIso8601String(), $tenant->subscription_ends_at->toIso8601String());
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::SubscriptionActivated->value)->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingPaymentVerified->value)->count());

        // The entitlement cache was bumped: the new plan's limits apply on the next read.
        $this->assertSame(50, app(TenantEntitlementService::class)->entitlements($tenant)->limit(LimitResource::Users));
        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.plan.id', $big->id)
            ->assertJsonPath('data.tenant_status.value', 'active')
            ->assertJsonPath('data.open_invoices_count', 0);
    }

    public function test_a_renewal_extends_from_the_current_end_not_from_today(): void
    {
        $plan = $this->plan();
        $end = now()->addDays(10)->startOfSecond();
        $tenant = $this->tenantOn($plan, ['status' => 'active', 'subscription_ends_at' => $end]);

        $invoiceId = $this->issuePlanInvoice($tenant, $plan->id, 'yearly', 'renewal');
        $this->assertAbout($end, BillingInvoice::query()->findOrFail($invoiceId)->period_start, 2);

        $paymentId = $this->submit($tenant, $invoiceId, '4490.000');
        $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->admin)->assertOk();

        $this->assertAbout($end->copy()->addYear(), Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at, 2);
    }

    public function test_an_expired_shop_comes_back_to_active_through_billing(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'suspended', 'suspension_reason' => 'non_payment', 'status_changed_at' => now()->subDays(5), 'subscription_ends_at' => now()->subDays(40)]);
        $invoice = $this->pendingInvoice($tenant, ['type' => 'renewal', 'billing_cycle' => 'monthly', 'lines' => [
            ['type' => 'plan', 'plan_id' => $plan->id, 'plan_name' => $plan->name, 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => '100.000', 'amount' => '100.000', 'founder' => false],
        ]]);
        $payment = $this->pendingPayment($invoice, '100.000');

        $this->postJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify', [], $this->admin)->assertOk();

        $tenant->refresh();
        $this->assertSame('active', $tenant->status);
        $this->assertNull($tenant->suspension_reason);
        $this->assertAbout(now()->addMonth(), $tenant->subscription_ends_at);
    }

    public function test_a_violation_suspension_is_not_lifted_by_a_payment(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'suspended', 'suspension_reason' => 'violation', 'status_changed_at' => now()->subDay()]);
        $invoice = $this->pendingInvoice($tenant, ['type' => 'renewal', 'billing_cycle' => 'monthly', 'lines' => [
            ['type' => 'plan', 'plan_id' => $plan->id, 'plan_name' => $plan->name, 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => '100.000', 'amount' => '100.000', 'founder' => false],
        ]]);
        $payment = $this->pendingPayment($invoice, '100.000');

        $this->postJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify', [], $this->admin)->assertOk();

        $this->assertSame('suspended', Tenant::query()->findOrFail($tenant->getKey())->status);
        $this->assertSame('paid', $invoice->refresh()->status->value);
    }

    public function test_an_archived_shop_refuses_activation_and_nothing_changes(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'archived', 'archived_at' => now()->subDay(), 'status_changed_at' => now()->subDay()]);
        $invoice = $this->pendingInvoice($tenant, ['type' => 'renewal', 'billing_cycle' => 'monthly', 'lines' => [
            ['type' => 'plan', 'plan_id' => $plan->id, 'plan_name' => $plan->name, 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => '100.000', 'amount' => '100.000', 'founder' => false],
        ]]);
        $payment = $this->pendingPayment($invoice, '100.000');

        $this->postJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify', [], $this->admin)
            ->assertStatus(409)->assertJsonPath('error_code', 'billing.tenant_not_activatable');

        $this->assertSame('pending', $payment->refresh()->status->value);
        $this->assertSame('pending', $invoice->refresh()->status->value);
    }

    public function test_a_service_invoice_is_marked_paid_without_touching_the_subscription(): void
    {
        $plan = $this->plan();
        $end = now()->addDays(20)->startOfSecond();
        $tenant = $this->tenantOn($plan, ['status' => 'active', 'subscription_ends_at' => $end]);

        $invoiceId = (int) $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices', [
            'type' => 'service', 'service_lines' => [['description' => 'Data import', 'amount' => '300.000']],
        ], $this->admin)->assertCreated()->json('data.id');

        $paymentId = $this->submit($tenant, $invoiceId, '300.000');
        $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->admin)->assertOk();

        $this->assertSame('paid', BillingInvoice::query()->findOrFail($invoiceId)->status->value);
        $this->assertSame($end->toIso8601String(), Tenant::query()->findOrFail($tenant->getKey())->subscription_ends_at->toIso8601String());
        $this->assertSame(0, Subscription::query()->where('tenant_id', $tenant->getKey())->count());
    }

    public function test_a_partial_payment_leaves_the_invoice_pending_until_the_rest_is_verified(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan);
        $invoice = $this->pendingInvoice($tenant);

        $first = $this->submit($tenant, $invoice->getKey(), '40.000');
        $this->postJson('/api/v1/super-admin/billing/payments/'.$first.'/verify', [], $this->admin)->assertOk();
        $this->assertSame('pending', $invoice->refresh()->status->value);

        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonPath('data.paid_amount', '40.000')->assertJsonPath('data.remaining', '60.000');

        $this->post('/api/v1/billing/invoices/'.$invoice->getKey().'/payments', [
            'receipt' => $this->receipt(), 'method' => 'instapay', 'amount' => '60.001', 'payer_reference' => 'ref-1', 'paid_at' => now()->toDateString(),
        ], $this->multipart($this->tenantHeaders($tenant)))->assertStatus(422)->assertJsonPath('details.remaining', '60.000');

        $second = $this->submit($tenant, $invoice->getKey(), '60.000');
        $this->postJson('/api/v1/super-admin/billing/payments/'.$second.'/verify', [], $this->admin)->assertOk();
        $this->assertSame('paid', $invoice->refresh()->status->value);
    }

    public function test_reject_needs_a_reason_keeps_the_invoice_open_and_lets_the_shop_resubmit(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan);
        $invoice = $this->pendingInvoice($tenant);
        $paymentId = $this->submit($tenant, $invoice->getKey(), '100.000');
        $url = '/api/v1/super-admin/billing/payments/'.$paymentId.'/reject';

        $this->postJson($url, [], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->postJson($url, ['reason' => 'ab'], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->postJson($url, ['reason' => str_repeat('x', 501)], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->assertSame('pending', BillingPayment::query()->findOrFail($paymentId)->status->value);

        $this->postJson($url, ['reason' => 'Amount does not match the receipt'], $this->admin)
            ->assertOk()
            ->assertJsonPath('data.status.value', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Amount does not match the receipt');

        $this->assertSame('pending', $invoice->refresh()->status->value);
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingPaymentRejected->value)->count());

        // The shop sees why, and can send a corrected receipt.
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonPath('data.payments.0.rejection_reason', 'Amount does not match the receipt');
        $this->submit($tenant, $invoice->getKey(), '100.000');

        // A rejected payment can be neither verified nor rejected again.
        $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->admin)->assertStatus(409)->assertJsonPath('error_code', 'billing.payment_not_pending');
        $this->postJson($url, ['reason' => 'again again'], $this->admin)->assertStatus(409);
    }

    public function test_an_invoice_with_a_payment_cannot_be_voided(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $this->submit($tenant, $invoice->getKey(), '100.000');

        $this->postJson('/api/v1/super-admin/billing-invoices/'.$invoice->getKey().'/void', [], $this->admin)
            ->assertStatus(409)->assertJsonPath('error_code', 'billing.invoice_not_voidable');

        $this->assertSame('pending', $invoice->refresh()->status->value);
    }

    public function test_a_voided_invoice_takes_no_payment_and_cannot_be_verified(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $payment = $this->pendingPayment($invoice, '100.000');
        $invoice->update(['status' => 'void', 'voided_at' => now()]);

        $this->postJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify', [], $this->admin)
            ->assertStatus(409)->assertJsonPath('error_code', 'billing.invoice_not_payable');
    }

    private function mintReceiptLink(int $paymentId): string
    {
        return (string) $this->getJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/receipt', $this->centralHeaders($this->operator))
            ->assertOk()->assertJsonStructure(['data' => ['url', 'expires_at']])->json('data.url');
    }

    public function test_the_receipt_opens_only_through_a_short_lived_single_use_signed_link(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $paymentId = $this->submit($tenant, $invoice->getKey(), '100.000');

        $link = $this->mintReceiptLink($paymentId);

        $this->assertStringContainsString('signature=', $link);
        $this->assertStringNotContainsString('central/private', $link);

        // Minting alone is not a view.
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingReceiptViewed->value)->count());

        // Tampering with the signed query, or another payment id, is refused (and does not burn the link).
        $this->get($link.'x')->assertForbidden();
        $this->get(str_replace('payments/'.$paymentId.'/', 'payments/'.($paymentId + 1).'/', $link))->assertForbidden();
        $this->get('/api/v1/super-admin/billing/payments/'.$paymentId.'/receipt/file')->assertForbidden();

        $file = $this->get($link);
        $file->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith('image/png', (string) $file->headers->get('Content-Type'));
        $this->assertStringStartsWith('inline', (string) $file->headers->get('Content-Disposition'));
        $this->assertNotSame('', $file->streamedContent());

        // The view is audited at fetch time, with the operator the link was minted for.
        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::BillingReceiptViewed->value)->get();
        $this->assertCount(1, $audit);

        // Single use: the same URL a second time is gone, and is not audited again.
        $this->get($link)->assertStatus(410);
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingReceiptViewed->value)->count());

        // A fresh mint works again (the UI re-mints for "open in new tab").
        $this->get($this->mintReceiptLink($paymentId))->assertOk();
        $this->assertSame(2, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingReceiptViewed->value)->count());

        // The link dies with the operator's access.
        $fresh = $this->mintReceiptLink($paymentId);
        $this->operator->forceFill(['is_active' => false])->save();
        $this->get($fresh)->assertForbidden();
        $this->operator->forceFill(['is_active' => true])->save();

        $this->travel(10)->minutes();
        $this->get($fresh)->assertForbidden();
    }

    public function test_a_pdf_receipt_is_served_as_an_attachment(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $pdf = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");

        $paymentId = (int) $this->post('/api/v1/billing/invoices/'.$invoice->getKey().'/payments', [
            'receipt' => $pdf, 'method' => 'instapay', 'amount' => '100.000', 'payer_reference' => 'ref-pdf-1', 'paid_at' => now()->toDateString(),
        ], $this->multipart($this->tenantHeaders($tenant)))->assertCreated()->json('data.id');

        $response = $this->get($this->mintReceiptLink($paymentId))->assertOk();
        $this->assertStringStartsWith('attachment', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_a_payment_without_a_stored_receipt_has_no_link(): void
    {
        $tenant = $this->createTenant();
        $payment = $this->pendingPayment($this->pendingInvoice($tenant), '100.000');

        $this->getJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/receipt', $this->centralHeaders($this->operator))->assertNotFound();
    }

    private function pendingPayment(BillingInvoice $invoice, string $amount): BillingPayment
    {
        return BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->getKey(),
            'tenant_id' => $invoice->tenant_id,
            'amount' => $amount,
            'method' => 'instapay',
            'gateway' => 'manual',
            'status' => 'pending',
            'paid_at' => now(),
            'raw_payload' => ['payer_reference' => '01000000999'],
        ]);
    }
}
