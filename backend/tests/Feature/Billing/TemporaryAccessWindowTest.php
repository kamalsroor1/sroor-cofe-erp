<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Tenants\ApplyTenantLifecycleAction;
use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\CentralAuditEvent;
use App\Enums\PlatformNotificationType;
use App\Mail\BillingReceiptUploadedMail;
use App\Mail\BillingTemporaryAccessReminderMail;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PlatformNotification;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\Catalog\FeatureCatalog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232 spec D / E: the 48-hour temporary activation opened by a classified transfer receipt, its
 * one-per-invoice / one-unsettled-per-tenant rules, access on the CURRENT plan during it, the way
 * back on expiry / rejection, and the operator notifications (panel + e-mail, 6-hour reminder).
 */
#[Group('billing')]
final class TemporaryAccessWindowTest extends BillingTestCase
{
    private const OPS_EMAIL = 'billing-ops@example.test';

    private CarbonImmutable $t0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->t0 = CarbonImmutable::parse('2026-11-26 10:00:00');
        Carbon::setTestNow($this->t0);
        config(['billing.admin_notifications.emails' => [self::OPS_EMAIL]]);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function moveClockTo(CarbonImmutable $moment): void
    {
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    /** A real 720x1280 PNG with enough detail to look like a phone screenshot (> 8 KB). */
    private function receiptImage(): UploadedFile
    {
        $image = imagecreatetruecolor(720, 1280);
        mt_srand(232);
        for ($i = 0; $i < 4000; $i++) {
            $color = imagecolorallocate($image, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255));
            imageline($image, mt_rand(0, 719), mt_rand(0, 1279), mt_rand(0, 719), mt_rand(0, 1279), (int) $color);
        }
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return UploadedFile::fake()->createWithContent('transfer.png', $bytes);
    }

    private function receiptPdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n".str_repeat('%', 9000));
    }

    private function upload(Tenant $tenant, BillingInvoice $invoice, UploadedFile $file, string $amount = '100.000'): BillingPayment
    {
        $response = $this->post('/api/v1/billing/invoices/'.$invoice->getKey().'/payments', [
            'receipt' => $file,
            'method' => 'instapay',
            'amount' => $amount,
            'payer_reference' => '01000000777',
            'paid_at' => now()->toDateString(),
        ], $this->multipart($this->tenantHeaders($tenant)));
        $response->assertCreated();

        return BillingPayment::query()->findOrFail($response->json('data.id'));
    }

    /** A trial that ended 10 days ago: read-only now (no free fallback: console signup). */
    private function expiredTrialTenant(?Plan $plan = null): Tenant
    {
        return $this->tenantOn($plan ?? $this->plan(), [
            'status' => 'trial',
            'trial_ends_at' => $this->t0->subDays(10),
            'status_changed_at' => $this->t0->subDays(24),
        ]);
    }

    private function writeStatus(Tenant $tenant): int
    {
        // Empty body: 422 when writes are allowed, 423 when the shop is read-only.
        return $this->postJson('/api/v1/customers', [], $this->tenantHeaders($tenant))->status();
    }

    private function verify(BillingPayment $payment, ?CarbonImmutable $transferredAt = null): void
    {
        $this->postJson(
            '/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify',
            $this->verifyBody($transferredAt ?? CarbonImmutable::now()),
            $this->steppedUpCentralHeaders($this->centralSuperAdmin()),
        )->assertOk();
    }

    private function reject(BillingPayment $payment, string $reason = 'المبلغ ناقص'): void
    {
        $this->postJson(
            '/api/v1/super-admin/billing/payments/'.$payment->getKey().'/reject',
            ['reason' => $reason],
            $this->steppedUpCentralHeaders($this->centralSuperAdmin()),
        )->assertOk();
    }

    private function remind(): void
    {
        $this->artisan('billing:send-temporary-access-reminders')->assertSuccessful();
    }

    public function test_a_receipt_image_opens_a_48_hour_window_and_the_shop_sees_under_review_never_paid(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);

        $payment = $this->upload($tenant, $invoice, $this->receiptImage());

        $this->assertSame('transfer_receipt_image', $payment->receipt_classification?->value);
        $this->assertSame('receipt_image', $payment->receipt_classification_reason);
        $this->assertSame('pending', $payment->status->value);

        $invoice->refresh();
        $this->assertTrue($invoice->temporary_access_started_at->equalTo($this->t0));
        $this->assertTrue($invoice->temporary_access_ends_at->equalTo($this->t0->addHours(48)));
        $this->assertSame((int) $payment->getKey(), $invoice->temporary_access_payment_id);
        $this->assertSame('pending', $invoice->status->value);
        $this->assertTrue($tenant->refresh()->temporary_access_ends_at->equalTo($this->t0->addHours(48)));

        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.review_state', 'under_review_temporary')
            ->assertJsonPath('data.temporary_access.state', 'active')
            ->assertJsonPath('data.temporary_access.seconds_left', 48 * 3600)
            ->assertJsonPath('data.temporary_access.last_hours', false)
            ->assertJsonPath('data.payments.0.receipt_classification.value', 'transfer_receipt_image');

        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.tenant_status.value', 'read_only')
            ->assertJsonPath('data.tenant_status.access', 'full')
            ->assertJsonPath('data.tenant_status.temporary_access', true)
            ->assertJsonPath('data.temporary_access.state', 'active')
            ->assertJsonPath('data.temporary_access.invoice_id', (int) $invoice->getKey())
            ->assertJsonPath('data.temporary_access.ends_at', $this->t0->addHours(48)->toIso8601String());

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::BillingReceiptClassified->value)->sole();
        $this->assertSame('transfer_receipt_image', $audit->properties['classification']);
        $this->assertTrue($audit->properties['temporary_access_opened']);
        $this->assertSame(720, $audit->properties['facts']['width']);
    }

    public function test_a_pdf_opens_no_window_and_stays_pending_for_manual_review(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);

        $payment = $this->upload($tenant, $invoice, $this->receiptPdf());

        $this->assertSame('not_receipt', $payment->receipt_classification?->value);
        $this->assertSame('pdf', $payment->receipt_classification_reason);
        $this->assertSame('pending', $payment->status->value);
        $this->assertNull($invoice->refresh()->temporary_access_started_at);
        $this->assertNull($tenant->refresh()->temporary_access_ends_at);
        $this->assertSame(423, $this->writeStatus($tenant));

        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertJsonPath('data.review_state', 'under_review_manual')
            ->assertJsonPath('data.temporary_access.state', 'none');

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::BillingReceiptClassified->value)->sole();
        $this->assertFalse($audit->properties['temporary_access_opened']);
        $this->assertSame('not_receipt', $audit->properties['temporary_access_not_opened_reason']);
    }

    public function test_a_tiny_image_is_not_a_receipt(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);

        $payment = $this->upload($tenant, $invoice, $this->receipt());

        $this->assertSame('not_receipt', $payment->receipt_classification?->value);
        $this->assertNull($invoice->refresh()->temporary_access_started_at);
    }

    public function test_withdrawing_and_re_uploading_never_resets_the_window(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $first = $this->upload($tenant, $invoice, $this->receiptImage(), '40.000');

        $this->moveClockTo($this->t0->addHours(10));
        $this->postJson('/api/v1/billing/payments/'.$first->getKey().'/cancel', [], $this->tenantHeaders($tenant))->assertOk();
        $this->assertSame(422, $this->writeStatus($tenant), 'A withdraw by the shop settles nothing: the window keeps running.');

        $second = $this->upload($tenant, $invoice, $this->receiptImage());

        $invoice->refresh();
        $this->assertTrue($invoice->temporary_access_ends_at->equalTo($this->t0->addHours(48)));
        $this->assertSame((int) $first->getKey(), $invoice->temporary_access_payment_id);
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertJsonPath('data.review_state', 'under_review_grace_used')
            ->assertJsonPath('data.temporary_access.ends_at', $this->t0->addHours(48)->toIso8601String());

        // After the window: still no new one for this invoice.
        $this->moveClockTo($this->t0->addHours(49));
        $this->postJson('/api/v1/billing/payments/'.$second->getKey().'/cancel', [], $this->tenantHeaders($tenant))->assertOk();
        $this->upload($tenant, $invoice, $this->receiptImage());
        $this->assertTrue($invoice->refresh()->temporary_access_ends_at->equalTo($this->t0->addHours(48)));
        $this->assertSame(423, $this->writeStatus($tenant));
    }

    public function test_a_rejected_window_is_never_reopened_by_a_new_receipt_for_the_same_invoice(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $payment = $this->upload($tenant, $invoice, $this->receiptImage());

        $this->reject($payment);
        $this->upload($tenant, $invoice, $this->receiptImage());

        $invoice->refresh();
        $this->assertSame('rejected', $invoice->temporary_access_outcome?->value);
        $this->assertNull($tenant->refresh()->temporary_access_ends_at);
        $this->assertSame(423, $this->writeStatus($tenant));
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertJsonPath('data.review_state', 'under_review')
            ->assertJsonPath('data.temporary_access.state', 'rejected');
    }

    public function test_no_window_for_another_invoice_while_the_first_one_is_unsettled_even_after_it_expired(): void
    {
        $tenant = $this->expiredTrialTenant();
        $first = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $second = $this->pendingInvoice($tenant, ['type' => 'renewal']);
        $this->upload($tenant, $first, $this->receiptImage());

        $this->upload($tenant, $second, $this->receiptImage());
        $this->assertNull($second->refresh()->temporary_access_started_at);

        $this->moveClockTo($this->t0->addHours(60));
        $third = $this->pendingInvoice($tenant, ['type' => 'upgrade']);
        $this->upload($tenant, $third, $this->receiptImage());
        $this->assertNull($third->refresh()->temporary_access_started_at, 'An expired but unsettled window still blocks a new one.');

        $reasons = CentralAuditLog::query()->where('event', CentralAuditEvent::BillingReceiptClassified->value)
            ->orderBy('id')->get()->map(fn (CentralAuditLog $row) => $row->properties['temporary_access_not_opened_reason'])->all();
        $this->assertSame([null, 'tenant_window_unsettled', 'tenant_window_unsettled'], $reasons);
    }

    public function test_after_a_confirmed_payment_a_later_cycle_gets_a_window_again(): void
    {
        $plan = $this->plan(['price_monthly' => '100.000']);
        $tenant = $this->expiredTrialTenant($plan);
        $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices', ['type' => 'plan', 'plan_id' => $plan->id, 'billing_cycle' => 'monthly'], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertCreated();
        $first = BillingInvoice::query()->where('tenant_id', $tenant->getKey())->sole();

        $payment = $this->upload($tenant, $first, $this->receiptImage(), (string) $first->total);
        $this->moveClockTo($this->t0->addHours(5));
        $this->verify($payment, $this->t0->addMinutes(5));

        $first->refresh();
        $this->assertSame('paid', $first->status->value);
        $this->assertSame('verified', $first->temporary_access_outcome?->value);
        $this->assertNull($tenant->refresh()->temporary_access_ends_at);
        $this->assertSame('active', $tenant->status);

        $renewal = $this->pendingInvoice($tenant, ['type' => 'renewal']);
        $this->upload($tenant, $renewal, $this->receiptImage());
        $this->assertTrue($renewal->refresh()->temporary_access_ends_at->equalTo($this->t0->addHours(5 + 48)));
    }

    public function test_during_the_window_an_expired_trial_works_on_its_current_plan_only(): void
    {
        $feature = FeatureCatalog::keys()[0];
        $current = $this->plan(['features' => array_fill_keys(FeatureCatalog::keys(), true) + [$feature => false]]);
        $current->update(['features' => array_merge($current->features, [$feature => false])]);
        $tenant = $this->expiredTrialTenant($current);
        $invoice = $this->pendingInvoice($tenant, ['type' => 'upgrade']);
        $features = app(TenantFeatureManagerInterface::class);

        $this->assertSame(423, $this->writeStatus($tenant));

        $this->upload($tenant, $invoice, $this->receiptImage());

        $this->assertSame(422, $this->writeStatus($tenant), 'Full access during the window.');
        $tenant->refresh();
        $this->assertSame((int) $current->getKey(), (int) $tenant->plan_id, 'The plan never changes before Verify.');
        $this->assertFalse($features->isFeatureEnabled($tenant, $feature), 'Upgrade features wait for the confirmed payment.');

        // The daily sweep leaves the shop alone while the window runs.
        app(ApplyTenantLifecycleAction::class)->execute();
        $this->assertSame('trial', $tenant->refresh()->status);
        $this->assertSame((int) $current->getKey(), (int) $tenant->plan_id);
    }

    public function test_expiry_returns_to_the_previous_state_and_keeps_data_and_a_running_paid_term(): void
    {
        $plan = $this->plan();
        $tenant = $this->expiredTrialTenant($plan);
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $this->upload($tenant, $invoice, $this->receiptImage());

        $this->moveClockTo($this->t0->addHours(47)->addMinutes(59));
        $this->assertSame(422, $this->writeStatus($tenant));
        $customerId = $this->inTenant($tenant, fn (): int => (int) Customer::query()->create(['name' => 'Window customer'])->getKey());

        $this->moveClockTo($this->t0->addHours(48));
        $this->assertSame(423, $this->writeStatus($tenant));
        $this->assertTrue($this->inTenant($tenant, fn (): bool => Customer::query()->whereKey($customerId)->exists()));
        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertJsonPath('data.tenant_status.access', 'read_only')
            ->assertJsonPath('data.tenant_status.temporary_access', false)
            ->assertJsonPath('data.temporary_access.state', 'expired');
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertJsonPath('data.review_state', 'under_review')
            ->assertJsonPath('data.status.value', 'pending');

        // A shop with a running paid term (renewing early): term and plan untouched by the window.
        $paidEnd = $this->t0->addDays(20)->startOfSecond();
        $active = $this->tenantOn($plan, ['status' => 'active', 'subscription_ends_at' => $paidEnd]);
        $renewal = $this->pendingInvoice($active, ['type' => 'renewal']);
        $this->upload($active, $renewal, $this->receiptImage());
        $this->moveClockTo($this->t0->addHours(100));
        $active->refresh();
        $this->assertTrue($active->subscription_ends_at->equalTo($paidEnd));
        $this->assertSame((int) $plan->getKey(), (int) $active->plan_id);
        $this->assertSame(422, $this->writeStatus($active));
    }

    public function test_rejection_ends_the_window_at_once_and_keeps_data(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $payment = $this->upload($tenant, $invoice, $this->receiptImage());
        $customerId = $this->inTenant($tenant, fn (): int => (int) Customer::query()->create(['name' => 'Kept'])->getKey());

        $this->moveClockTo($this->t0->addHours(3));
        $this->reject($payment, 'التحويل مش ظاهر');

        $this->assertSame(423, $this->writeStatus($tenant));
        $this->assertTrue($this->inTenant($tenant, fn (): bool => Customer::query()->whereKey($customerId)->exists()));
        $this->assertSame('trial', $tenant->refresh()->status);
        $this->assertNull($tenant->temporary_access_ends_at);
        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertJsonPath('data.temporary_access.state', 'rejected')
            ->assertJsonPath('data.temporary_access.rejection_reason', 'التحويل مش ظاهر');
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertJsonPath('data.review_state', 'rejected');
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::BillingTemporaryAccessSettled->value)->count());
    }

    public function test_voiding_the_invoice_settles_its_window(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $payment = $this->upload($tenant, $invoice, $this->receiptImage());
        $this->postJson('/api/v1/billing/payments/'.$payment->getKey().'/cancel', [], $this->tenantHeaders($tenant))->assertOk();

        $this->postJson('/api/v1/super-admin/billing-invoices/'.$invoice->getKey().'/void', [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertOk();

        $this->assertSame('voided', $invoice->refresh()->temporary_access_outcome?->value);
        $this->assertNull($tenant->refresh()->temporary_access_ends_at);
        $this->assertSame(423, $this->writeStatus($tenant));
    }

    public function test_upload_notifies_the_panel_and_queues_the_operator_email(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $payment = $this->upload($tenant, $invoice, $this->receiptImage());

        $item = PlatformNotification::query()->sole();
        $this->assertSame(PlatformNotificationType::BillingReceiptUploaded, $item->type);
        $this->assertSame((int) $payment->getKey(), $item->billing_payment_id);
        $this->assertTrue($item->data['temporary_access']);

        Mail::assertQueued(BillingReceiptUploadedMail::class, function (BillingReceiptUploadedMail $mail) use ($invoice): bool {
            return $mail->hasTo(self::OPS_EMAIL)
                && $mail->invoiceNumber === $invoice->number
                && $mail->temporaryAccessEndsAt === '2026-11-28 10:00'
                && str_contains($mail->reviewUrl, '/super-admin/billing?payment=');
        });
        Mail::assertQueuedCount(1);

        // The manual-review variant.
        $other = $this->pendingInvoice($tenant, ['type' => 'renewal']);
        $this->upload($tenant, $other, $this->receiptPdf());
        Mail::assertQueued(BillingReceiptUploadedMail::class, fn (BillingReceiptUploadedMail $mail): bool => $mail->invoiceNumber === $other->number && $mail->temporaryAccessEndsAt === null);
    }

    public function test_without_configured_addresses_the_active_billing_managers_get_the_email(): void
    {
        config(['billing.admin_notifications.emails' => []]);
        $manager = $this->centralSuperAdmin();
        $support = $this->centralSupport();
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);

        $this->upload($tenant, $invoice, $this->receiptImage());

        Mail::assertQueued(BillingReceiptUploadedMail::class, fn (BillingReceiptUploadedMail $mail): bool => $mail->hasTo($manager->email));
        Mail::assertNotQueued(BillingReceiptUploadedMail::class, fn (BillingReceiptUploadedMail $mail): bool => $mail->hasTo($support->email));
    }

    public function test_the_reminder_goes_out_once_at_six_hours_before_the_end(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $this->upload($tenant, $invoice, $this->receiptImage());

        $this->moveClockTo($this->t0->addHours(42)->subMinute());
        $this->remind();
        Mail::assertNotQueued(BillingTemporaryAccessReminderMail::class);

        $this->moveClockTo($this->t0->addHours(42));
        $this->remind();
        $this->remind();
        $this->moveClockTo($this->t0->addHours(43));
        $this->remind();

        Mail::assertQueued(BillingTemporaryAccessReminderMail::class, 1);
        Mail::assertQueued(BillingTemporaryAccessReminderMail::class, fn (BillingTemporaryAccessReminderMail $mail): bool => $mail->hasTo(self::OPS_EMAIL) && $mail->endsAt === '2026-11-28 10:00');
        $this->assertSame(1, PlatformNotification::query()->where('type', PlatformNotificationType::BillingTemporaryAccessReminder->value)->count());
        $this->assertTrue($invoice->refresh()->temporary_access_reminder_sent_at->equalTo($this->t0->addHours(42)));

        // The window shows its last hours to the shop.
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertJsonPath('data.temporary_access.last_hours', true);
    }

    public function test_no_reminder_after_the_window_was_settled_or_ended(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $payment = $this->upload($tenant, $invoice, $this->receiptImage());
        $this->reject($payment);

        $this->moveClockTo($this->t0->addHours(42));
        $this->remind();

        $other = $this->tenantOn($this->plan(), ['status' => 'read_only', 'status_changed_at' => $this->t0->subDay()]);
        $late = $this->pendingInvoice($other, ['type' => 'plan']);
        $this->upload($other, $late, $this->receiptImage());
        $this->moveClockTo($this->t0->addHours(42 + 49));
        $this->remind();

        Mail::assertNotQueued(BillingTemporaryAccessReminderMail::class);
    }

    public function test_tenant_isolation_of_the_window_and_its_api(): void
    {
        $a = $this->expiredTrialTenant();
        $b = $this->expiredTrialTenant();
        $invoiceA = $this->pendingInvoice($a, ['type' => 'plan']);
        $this->upload($a, $invoiceA, $this->receiptImage());

        $this->assertSame(422, $this->writeStatus($a));
        $this->assertSame(423, $this->writeStatus($b), "Another shop's window never opens this shop.");
        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($b))->assertOk()->assertJsonPath('data.temporary_access', null);
        $this->getJson('/api/v1/billing/invoices/'.$invoiceA->getKey(), $this->tenantHeaders($b))->assertNotFound();

        // B still gets its own window (the rule is per shop).
        $invoiceB = $this->pendingInvoice($b, ['type' => 'plan']);
        $this->upload($b, $invoiceB, $this->receiptImage());
        $this->assertNotNull($invoiceB->refresh()->temporary_access_started_at);
    }
}
