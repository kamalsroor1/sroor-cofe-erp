<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\BillingGateway;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232 owner decision 2026-11-26: a system-computed upgrade difference is FINAL at Verify, re-priced
 * from the days remaining at the verified transfer moment; when the approval comes more than 48h
 * after the transfer, from the approval moment. Anything paid above the final total is stored as
 * `overpaid_amount` (policy open). GET .../verify-preview shows the same numbers before approving.
 *
 * Fixture: 499 -> 999 monthly, VAT 14%, term Nov 6 -> Dec 6 2026 12:00 (30 days), issued Nov 26 12:00
 * with 10 days left: (999 - 499) x 10 / 30 = 166.667 + 23.333 VAT = 190.000, paid in full.
 */
#[Group('billing')]
final class UpgradeRepricingAtVerifyTest extends PlanChangeTestCase
{
    private const QUEUE = '/api/v1/super-admin/billing/plan-change-requests';

    private CentralUser $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo($this->at('2026-11-26 12:00:00'));
        $this->operator = $this->centralSuperAdmin();
        $this->setTaxRate('14.000');
    }

    private function at(string $moment): CarbonImmutable
    {
        return CarbonImmutable::parse($moment, (string) config('app.timezone'));
    }

    /** Issue the fixture's upgrade invoice (190.000) and a pending payment of its full total. */
    private function issuedUpgrade(bool $withRenewal = false, ?string $amount = null): BillingPayment
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $body = array_filter(['with_renewal' => $withRenewal ?: null, 'amount' => $amount], static fn ($value): bool => $value !== null);
        $id = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', $body, $this->steppedUpCentralHeaders($this->operator))
            ->assertCreated()->json('data.invoice.id');
        $invoice = BillingInvoice::query()->findOrFail($id);

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

    private function verify(BillingPayment $payment, string $transferredAt): TestResponse
    {
        return $this->postJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify', ['transferred_at' => $transferredAt], $this->steppedUpCentralHeaders($this->operator));
    }

    private function preview(BillingPayment $payment, string $transferredAt, ?CentralUser $as = null): TestResponse
    {
        return $this->getJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify-preview?transferred_at='.urlencode($transferredAt), $this->centralHeaders($as ?? $this->operator));
    }

    private function invoiceOf(BillingPayment $payment): BillingInvoice
    {
        return BillingInvoice::query()->findOrFail($payment->billing_invoice_id);
    }

    public function test_the_issued_line_keeps_its_proration_inputs(): void
    {
        $line = $this->invoiceOf($this->issuedUpgrade())->lines[0];

        $this->assertTrue($line['prorated']);
        $this->assertSame('499.000', $line['old_price']);
        $this->assertSame('999.000', $line['new_price']);
        $this->assertSame(10, $line['remaining_days']);
        $this->assertSame(30, $line['period_days']);
        $this->assertSame('2026-11-26T12:00:00+02:00', $line['priced_at']);
    }

    public function test_a_transfer_on_the_issue_day_keeps_the_amount(): void
    {
        $payment = $this->issuedUpgrade();
        $this->travelTo($this->at('2026-11-26 15:00:00'));

        $this->verify($payment, '2026-11-26T14:00:00+02:00')->assertOk()->assertJsonPath('data.invoice_overpaid_amount', '0.000');

        $invoice = $this->invoiceOf($payment);
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame('166.667', $invoice->lines[0]['amount']);
        $this->assertSame('190.000', $invoice->total);
        $this->assertSame('0.000', $invoice->overpaid_amount);
        $this->assertSame('transfer', $invoice->lines[0]['pricing_basis']);
    }

    public function test_a_transfer_earlier_on_the_issue_day_never_adds_a_day(): void
    {
        $payment = $this->issuedUpgrade();

        // 09:00 is before the 12:00 issue moment: priced from 12:00 (ceiling), still 190.000 and paid.
        $this->verify($payment, '2026-11-26T09:00:00+02:00')->assertOk();

        $invoice = $this->invoiceOf($payment);
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame('190.000', $invoice->total);
    }

    public function test_a_transfer_days_after_issue_reprices_to_the_remaining_days_and_stores_the_excess(): void
    {
        $payment = $this->issuedUpgrade();
        $this->travelTo($this->at('2026-11-29 12:00:00'));

        $this->verify($payment, '2026-11-29T10:00:00+02:00')
            ->assertOk()
            ->assertJsonPath('data.invoice_total', '152.000')
            ->assertJsonPath('data.invoice_overpaid_amount', '38.000');

        // ceil(Dec 6 12:00 - Nov 29 10:00) = 8 days: 500 x 8 / 30 = 133.333; VAT 18.66662 -> 18.667.
        $invoice = $this->invoiceOf($payment);
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame('133.333', $invoice->lines[0]['amount']);
        $this->assertSame(8, $invoice->lines[0]['remaining_days']);
        $this->assertSame('133.333', $invoice->subtotal);
        $this->assertSame('18.667', $invoice->tax);
        $this->assertSame('152.000', $invoice->total);
        $this->assertSame('38.000', $invoice->overpaid_amount);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::BillingInvoiceRepriced->value)->sole();
        $this->assertSame('190.000', $audit->properties['old']['total']);
        $this->assertSame('152.000', $audit->properties['new']['total']);
        $this->assertSame('38.000', $audit->properties['overpaid_amount']);
        $this->assertSame('transfer', $audit->properties['basis']);

        // The upgrade itself is applied, end kept.
        $this->assertSame('2026-12-06T12:00:00+02:00', Tenant::query()->findOrFail($invoice->tenant_id)->subscription_ends_at?->toIso8601String());
    }

    public function test_an_approval_within_48_hours_prices_from_the_transfer(): void
    {
        $payment = $this->issuedUpgrade();
        $this->travelTo($this->at('2026-11-29 11:59:00')); // 47h59m after the transfer

        $this->preview($payment, '2026-11-27T12:00:00+02:00')->assertOk()
            ->assertJsonPath('data.basis', 'transfer')
            ->assertJsonPath('data.effective_moment', '2026-11-27T12:00:00+02:00')
            ->assertJsonPath('data.remaining_days', 9);

        $this->verify($payment, '2026-11-27T12:00:00+02:00')->assertOk();

        // 500 x 9 / 30 = 150.000; VAT 21.000; total 171.000; paid 190.000 -> 19.000 over.
        $invoice = $this->invoiceOf($payment);
        $this->assertSame('150.000', $invoice->subtotal);
        $this->assertSame('21.000', $invoice->tax);
        $this->assertSame('171.000', $invoice->total);
        $this->assertSame('19.000', $invoice->overpaid_amount);
    }

    public function test_an_approval_48_hours_and_one_minute_after_the_transfer_prices_from_the_approval(): void
    {
        $payment = $this->issuedUpgrade();
        $this->travelTo($this->at('2026-11-29 12:01:00'));

        $this->preview($payment, '2026-11-27T12:00:00+02:00')->assertOk()
            ->assertJsonPath('data.basis', 'approval')
            ->assertJsonPath('data.effective_moment', '2026-11-29T12:01:00+02:00')
            ->assertJsonPath('data.remaining_days', 7);

        $this->verify($payment, '2026-11-27T12:00:00+02:00')->assertOk();

        // ceil(6d 23h 59m) = 7: 500 x 7 / 30 = 116.6666.. -> 116.667; VAT 16.33338 -> 16.333; 133.000.
        $invoice = $this->invoiceOf($payment);
        $this->assertSame('116.667', $invoice->subtotal);
        $this->assertSame('16.333', $invoice->tax);
        $this->assertSame('133.000', $invoice->total);
        $this->assertSame('57.000', $invoice->overpaid_amount);
        $this->assertSame('approval', $invoice->lines[0]['pricing_basis']);
        // The paid term itself still starts per spec A (a running term: appended/kept, unchanged here).
        $this->assertSame('2026-12-06T12:00:00+02:00', Tenant::query()->findOrFail($invoice->tenant_id)->subscription_ends_at?->toIso8601String());
    }

    public function test_the_preview_equals_what_verify_then_applies(): void
    {
        $payment = $this->issuedUpgrade(withRenewal: true);
        $this->travelTo($this->at('2026-12-01 09:00:00'));

        $preview = $this->preview($payment, '2026-11-30T18:00:00+02:00')->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.repriced', true)
            ->assertJsonPath('data.previous_total', '1328.860')
            ->assertJsonPath('data.paid_amount', '1328.860')
            ->json('data');

        // Nothing is written by the preview.
        $this->assertSame('1328.860', $this->invoiceOf($payment)->total);
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingInvoiceRepriced->value)->count());

        $this->verify($payment, '2026-11-30T18:00:00+02:00')->assertOk();
        $invoice = $this->invoiceOf($payment);

        // ceil(Dec 6 12:00 - Nov 30 18:00) = 6: 500 x 6 / 30 = 100.000 + renewal 999.000 = 1099.000;
        // VAT 153.860; total 1252.860; paid 1328.860 -> 76.000 over.
        $this->assertSame('1099.000', $preview['subtotal']);
        $this->assertSame('153.860', $preview['vat']);
        $this->assertSame('1252.860', $preview['total']);
        $this->assertSame('76.000', $preview['overpaid_amount']);
        $this->assertSame('0.000', $preview['remaining_due']);
        $this->assertSame($preview['subtotal'], $invoice->subtotal);
        $this->assertSame($preview['vat'], $invoice->tax);
        $this->assertSame($preview['total'], $invoice->total);
        $this->assertSame($preview['overpaid_amount'], $invoice->overpaid_amount);
        $this->assertSame($preview['lines'], $invoice->lines);
        // The renewal line is unchanged.
        $this->assertSame('999.000', $invoice->lines[1]['amount']);
        $this->assertTrue($invoice->lines[1]['renewal']);
    }

    public function test_a_double_verify_is_idempotent(): void
    {
        $payment = $this->issuedUpgrade();
        $this->travelTo($this->at('2026-11-29 12:00:00'));

        $this->verify($payment, '2026-11-29T10:00:00+02:00')->assertOk();
        $this->verify($payment, '2026-11-29T11:00:00+02:00')->assertOk()->assertJsonPath('data.invoice_overpaid_amount', '38.000');

        $invoice = $this->invoiceOf($payment);
        $this->assertSame('152.000', $invoice->total);
        $this->assertSame('38.000', $invoice->overpaid_amount);
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingInvoiceRepriced->value)->count());
    }

    public function test_a_typed_amount_is_never_repriced(): void
    {
        $payment = $this->issuedUpgrade(amount: '120.000');
        $this->travelTo($this->at('2026-11-30 12:00:00'));

        $this->preview($payment, '2026-11-30T10:00:00+02:00')->assertOk()->assertJsonPath('data.repriced', false);
        $this->verify($payment, '2026-11-30T10:00:00+02:00')->assertOk();

        $invoice = $this->invoiceOf($payment);
        $this->assertSame('120.000', $invoice->subtotal);
        $this->assertSame('paid', $invoice->status->value);
        $this->assertSame('0.000', $invoice->overpaid_amount);
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingInvoiceRepriced->value)->count());
    }

    public function test_preview_is_422_on_a_bad_transfer_moment_and_409_on_a_settled_payment(): void
    {
        $payment = $this->issuedUpgrade();

        $this->getJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify-preview', $this->centralHeaders($this->operator))
            ->assertStatus(422)->assertJsonPath('errors.transferred_at.0', __('billing.errors.transferred_at_required'));
        $this->preview($payment, '2026-11-25T10:00:00+02:00')->assertStatus(422)
            ->assertJsonPath('errors.transferred_at.0', __('billing.errors.transferred_at_before_invoice'));
        $this->preview($payment, '2026-11-26T18:00:00+02:00')->assertStatus(422)->assertJsonValidationErrors(['transferred_at']);
        $this->getJson('/api/v1/super-admin/billing/payments/999999/verify-preview?transferred_at=2026-11-26T10:00:00%2B02:00', $this->centralHeaders($this->operator))
            ->assertNotFound();

        $this->verify($payment, '2026-11-26T12:00:00+02:00')->assertOk();
        $this->preview($payment, '2026-11-26T12:00:00+02:00')->assertStatus(409)->assertJsonPath('error_code', 'billing.payment_not_pending');
    }

    public function test_preview_is_401_for_guests_403_without_the_manage_permission_and_404_on_a_tenant_host(): void
    {
        $payment = $this->issuedUpgrade();
        $url = '/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify-preview?transferred_at=2026-11-26T10:00:00%2B02:00';

        $this->getJson($url, ['Accept' => 'application/json'])->assertUnauthorized();
        $this->getJson($url, $this->centralHeaders($this->centralSupport()))->assertForbidden();
        // A tenant host never reaches a central route (EnsureCentralContext): 404, like every console route.
        $this->getJson($url, $this->tenantHeaders($this->createTenant()))->assertNotFound();
    }
}
