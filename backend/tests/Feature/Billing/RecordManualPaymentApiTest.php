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
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
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

    public function test_a_retry_with_the_same_idempotency_key_records_the_partial_payment_once(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);
        $body = $this->body('40.000', ['idempotency_key' => '6f1c2b8e-4d1a-4c6e-9a51-0b7d3f2e9c11']);

        $this->postJson($this->url($invoice->id), $body, $this->admin)->assertOk()->assertJsonPath('data.remaining', '60.000');
        $this->postJson($this->url($invoice->id), $body, $this->admin)->assertOk()->assertJsonPath('data.remaining', '60.000');

        $this->assertSame(1, BillingPayment::query()->where('billing_invoice_id', $invoice->id)->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingPaymentRecorded->value)->count());
    }

    public function test_an_identical_partial_payment_within_ten_minutes_is_409_even_without_a_key(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);

        $this->postJson($this->url($invoice->id), $this->body('30.000'), $this->admin)->assertOk();
        $this->postJson($this->url($invoice->id), $this->body('30.000'), $this->admin)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.payment_duplicate');
        $this->assertSame(1, BillingPayment::query()->where('billing_invoice_id', $invoice->id)->count());

        // A different reference is a different payment; the same one after the window is allowed again.
        $this->postJson($this->url($invoice->id), $this->body('30.000', ['reference' => 'WA-2']), $this->admin)->assertOk();
        $this->travel(11)->minutes();
        $this->postJson($this->url($invoice->id), $this->body('30.000'), $this->admin)
            ->assertOk()
            ->assertJsonPath('data.remaining', '10.000');
        $this->assertSame(3, BillingPayment::query()->where('billing_invoice_id', $invoice->id)->count());
    }

    public function test_a_malformed_idempotency_key_is_422(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);

        $this->postJson($this->url($invoice->id), $this->body('10.000', ['idempotency_key' => 'not-a-uuid']), $this->admin)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['idempotency_key']);
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

        $this->postJson($this->url($invoice->id), $this->body('10'), ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)])
            ->assertUnauthorized();
        $this->assertSame(0, BillingPayment::query()->where('billing_invoice_id', $invoice->id)->count());
    }

    public function test_reference_is_optional_and_must_be_2_to_100_chars_without_angle_brackets(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);

        // 1 char, 101 chars, and angle brackets are 422 with translated message (ar & en).
        $this->postJson($this->url($invoice->id), $this->body('10.000', ['reference' => 'a']), $this->admin)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference'])
            ->assertJsonPath('errors.reference.0', __('validation.min.string', ['attribute' => __('validation.attributes.reference'), 'min' => 2]));

        $this->postJson($this->url($invoice->id), $this->body('10.000', ['reference' => str_repeat('a', 101)]), $this->admin + ['X-Locale' => 'en'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference'])
            ->assertJsonPath('errors.reference.0', __('validation.max.string', ['attribute' => __('validation.attributes.reference', [], 'en'), 'max' => 100], 'en'));

        $this->postJson($this->url($invoice->id), $this->body('10.000', ['reference' => 'bad<ref']), $this->admin)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reference']);

        // Nullable/empty, 2 chars, and 100 chars succeed.
        $this->postJson($this->url($invoice->id), $this->body('10.000', ['reference' => '']), $this->admin)->assertOk();
        $this->postJson($this->url($invoice->id), $this->body('10.000', ['reference' => 'ab']), $this->admin)->assertOk();
        $this->postJson($this->url($invoice->id), $this->body('10.000', ['reference' => str_repeat('r', 100)]), $this->admin)->assertOk();
    }

    public function test_note_and_notes_enforce_max_500_characters(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant);

        $this->postJson($this->url($invoice->id), $this->body('10.000', ['note' => str_repeat('n', 501)]), $this->admin)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);

        $payload501Notes = $this->body('10.000', ['notes' => str_repeat('n', 501)]);
        unset($payload501Notes['note']);
        $this->postJson($this->url($invoice->id), $payload501Notes, $this->admin)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);

        // 500 chars via notes succeeds and persists in raw_payload['note'].
        $payload500Notes = $this->body('10.000', ['notes' => str_repeat('n', 500)]);
        unset($payload500Notes['note']);
        $this->postJson($this->url($invoice->id), $payload500Notes, $this->admin)->assertOk();
        $this->assertSame(str_repeat('n', 500), BillingPayment::query()->latest('id')->firstOrFail()->raw_payload['note']);
    }

    public function test_paid_at_compares_calendar_days_in_cairo_timezone_even_when_app_timezone_is_utc(): void
    {
        $prevAppTz = (string) config('app.timezone');
        $prevPhpTz = date_default_timezone_get();

        try {
            config(['app.timezone' => 'UTC', 'billing.timezone' => 'Africa/Cairo']);
            date_default_timezone_set('UTC');

            // Midday Cairo on 2026-11-27: invoice issued the same day.
            Carbon::setTestNow(CarbonImmutable::parse('2026-11-27 12:00:00', 'Africa/Cairo'));
            $tenant = $this->tenantOn($this->plan());
            $invoice = $this->pendingInvoice($tenant, ['issued_at' => CarbonImmutable::parse('2026-11-27 10:00:00', 'Africa/Cairo')]);

            // Day before in Cairo (2026-11-26 23:59:59+02:00) is rejected.
            $this->postJson($this->url($invoice->id), $this->body('10.000', ['paid_at' => '2026-11-26T23:59:59+02:00']), $this->admin)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['paid_at'])
                ->assertJsonPath('errors.paid_at.0', __('billing.errors.transferred_at_before_invoice'));

            // 00:00:00, 01:59:59, and 02:00:00 Cairo on the issue day must all be accepted.
            foreach (['2026-11-27T00:00:00+02:00', '2026-11-27T01:59:59+02:00', '2026-11-27T02:00:00+02:00'] as $index => $paidAt) {
                $this->postJson($this->url($invoice->id), $this->body('10.000', [
                    'reference' => 'CAIRO-'.$index,
                    'paid_at' => $paidAt,
                ]), $this->admin)->assertOk();
            }

            // The stored instant is the one sent (no 2h shift when app.timezone is UTC).
            $stored = BillingPayment::query()->where('billing_invoice_id', $invoice->id)->latest('id')->firstOrFail();
            $this->assertSame(
                CarbonImmutable::parse('2026-11-27T02:00:00+02:00')->utc()->toIso8601String(),
                CarbonImmutable::instance($stored->fresh()->paid_at)->utc()->toIso8601String(),
            );

            // Also verify 00:30 Cairo (22:30 UTC previous day): date-only "2026-11-27" and "00:00+02:00" succeed.
            Carbon::setTestNow(CarbonImmutable::parse('2026-11-27 00:30:00', 'Africa/Cairo'));
            $earlyInvoice = $this->pendingInvoice($tenant, ['issued_at' => CarbonImmutable::parse('2026-11-27 00:15:00', 'Africa/Cairo')]);
            $this->postJson($this->url($earlyInvoice->id), $this->body('10.000', [
                'reference' => 'EARLY-0',
                'paid_at' => '2026-11-27T00:00:00+02:00',
            ]), $this->admin)->assertOk();
            $this->postJson($this->url($earlyInvoice->id), $this->body('10.000', [
                'reference' => 'EARLY-DAY',
                'paid_at' => '2026-11-27',
            ]), $this->admin)->assertOk();
        } finally {
            Carbon::setTestNow();
            config(['app.timezone' => $prevAppTz]);
            date_default_timezone_set($prevPhpTz);
        }
    }
}
