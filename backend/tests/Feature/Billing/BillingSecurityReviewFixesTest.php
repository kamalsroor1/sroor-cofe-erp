<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Tenants\ApplyTenantLifecycleAction;
use App\Actions\Tenants\TransitionTenantStatusAction;
use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\LimitResource;
use App\Enums\PlatformNotificationType;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Jobs\NotifyOperatorsOfBillingEventJob;
use App\Mail\BillingReceiptUploadedMail;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\PlatformNotification;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Billing\BillingOperatorRecipients;
use App\Services\Billing\TemporaryAccessService;
use App\Support\Tenancy\TenantStatusTransition;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;

/**
 * Security audit + code review of the #232 billing rework (S1-S5, R1-R5):
 *  - S1: the 48h window needs a full-amount receipt and never lifts an operator's suspension;
 *  - S2: receipt e-mails deduplicated per invoice + 15-minute slot, per-tenant upload cap, cancel throttled;
 *  - S3: the lifecycle sweep only pauses for a window the policy really grants;
 *  - S4: the classifier reason is console-only; S5: no legacy payment_instructions;
 *  - R1: a non-payment suspension lifted by a window lets every user sign in normally;
 *  - R3: owned add-ons count during a window; R5: the operator e-mail job really retries.
 */
#[Group('billing')]
final class BillingSecurityReviewFixesTest extends BillingTestCase
{
    private const OPS_EMAIL = 'billing-ops@example.test';

    private CarbonImmutable $t0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->t0 = CarbonImmutable::parse('2026-11-26 10:00:00');
        $this->moveClockTo($this->t0);
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

    /** A real 720x1280 PNG with enough detail to be classified as a transfer receipt image. */
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

    /**
     * @param  array<string, string>|null  $headers
     */
    private function upload(Tenant $tenant, BillingInvoice $invoice, string $amount = '100.000', ?array $headers = null): BillingPayment
    {
        $response = $this->post('/api/v1/billing/invoices/'.$invoice->getKey().'/payments', [
            'receipt' => $this->receiptImage(),
            'method' => 'instapay',
            'amount' => $amount,
            'payer_reference' => '01000000777',
            'paid_at' => now()->toDateString(),
        ], $this->multipart($headers ?? $this->tenantHeaders($tenant)));
        $response->assertCreated();

        return BillingPayment::query()->findOrFail($response->json('data.id'));
    }

    private function cancel(Tenant $tenant, BillingPayment $payment): void
    {
        $this->postJson('/api/v1/billing/payments/'.$payment->getKey().'/cancel', [], $this->tenantHeaders($tenant))->assertOk();
    }

    /** A trial that ended 10 days ago: read-only now. */
    private function expiredTrialTenant(): Tenant
    {
        return $this->tenantOn($this->plan(), [
            'status' => 'trial',
            'trial_ends_at' => $this->t0->subDays(10),
            'status_changed_at' => $this->t0->subDays(24),
        ]);
    }

    /** Suspended for non-payment BY THE LIFECYCLE (actor System, from read_only). */
    private function systemSuspendedTenant(): Tenant
    {
        $tenant = $this->tenantOn($this->plan(), ['status' => 'read_only', 'status_changed_at' => $this->t0->subDays(31)]);
        $this->endTenancy();
        app(TransitionTenantStatusAction::class)->execute(TenantStatusTransition::to(
            (string) $tenant->getKey(),
            TenantStatus::Suspended,
            TenantLifecycleActor::System,
            expectFrom: TenantStatus::ReadOnly,
        ));

        return $tenant->refresh();
    }

    private function operatorSuspends(Tenant $tenant, string $reason = 'non_payment'): void
    {
        $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getTenantKey().'/toggle-status', [
            'status' => TenantStatus::Suspended->value,
            'reason' => $reason,
            'note' => 'Operator decision',
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertOk();
    }

    private function writeStatus(Tenant $tenant): int
    {
        // Empty body: 422 when writes are allowed, 423 read-only, 403 blocked.
        return $this->postJson('/api/v1/customers', [], $this->tenantHeaders($tenant))->status();
    }

    private function lastNotOpenedReason(): ?string
    {
        $row = CentralAuditLog::query()->where('event', CentralAuditEvent::BillingReceiptClassified->value)->orderByDesc('id')->firstOrFail();

        return $row->properties['temporary_access_not_opened_reason'];
    }

    // ---- S1 ----------------------------------------------------------------------------------

    public function test_a_partial_amount_opens_no_window_and_stays_pending_for_manual_review(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);

        $payment = $this->upload($tenant, $invoice, '99.999');

        $this->assertSame('transfer_receipt_image', $payment->receipt_classification?->value);
        $this->assertSame('pending', $payment->status->value);
        $this->assertNull($invoice->refresh()->temporary_access_started_at);
        $this->assertNull($tenant->refresh()->temporary_access_ends_at);
        $this->assertSame(TemporaryAccessService::NOT_OPENED_PARTIAL_AMOUNT, $this->lastNotOpenedReason());
        $this->assertSame(423, $this->writeStatus($tenant));
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.review_state', 'under_review')
            ->assertJsonPath('data.temporary_access.state', 'none');
    }

    public function test_a_payment_covering_what_is_left_after_a_verified_part_opens_the_window(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->getKey(), 'tenant_id' => $tenant->getKey(), 'amount' => '60.000',
            'method' => 'instapay', 'gateway' => 'manual', 'status' => 'verified', 'paid_at' => now(), 'verified_at' => now(),
        ]);

        $this->upload($tenant, $invoice, '40.000');

        $this->assertNotNull($invoice->refresh()->temporary_access_started_at);
        $this->assertSame(422, $this->writeStatus($tenant));
    }

    public function test_an_operator_suspension_closes_an_open_window_at_once(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $this->upload($tenant, $invoice);
        $this->assertSame(422, $this->writeStatus($tenant), 'Full access during the window.');

        $this->operatorSuspends($tenant);

        $this->assertSame(403, $this->writeStatus($tenant), 'No access during the window once an operator suspended the shop.');
        $invoice->refresh();
        $this->assertSame('voided', $invoice->temporary_access_outcome?->value);
        $this->assertNull($tenant->refresh()->temporary_access_ends_at);
        $settled = CentralAuditLog::query()->where('event', CentralAuditEvent::BillingTemporaryAccessSettled->value)->sole();
        $this->assertSame('voided', $settled->properties['outcome']);
    }

    public function test_a_receipt_never_lifts_a_suspension_an_operator_set_by_hand(): void
    {
        $tenant = $this->tenantOn($this->plan(), ['status' => 'active', 'subscription_ends_at' => $this->t0->addDays(5)]);
        $invoice = $this->pendingInvoice($tenant, ['type' => 'renewal']);
        $this->operatorSuspends($tenant);

        // The billing manager still reaches /billing (payment recovery) and uploads the full amount.
        $admin = $this->tenantAdmin($tenant);
        $token = $this->postJson('/api/v1/auth/login', ['login' => $admin->email, 'password' => 'password'], ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertOk()->assertJsonPath('data.subscription_blocked', true)->json('data.token');
        $headers = array_merge($this->tenantHeaders($tenant), ['Authorization' => 'Bearer '.$token]);
        $payment = $this->upload($tenant, $invoice, '100.000', $headers);

        $this->assertSame('pending', $payment->status->value);
        $this->assertNull($invoice->refresh()->temporary_access_started_at);
        $this->assertNull($tenant->refresh()->temporary_access_ends_at);
        $this->assertSame(TemporaryAccessService::NOT_OPENED_OPERATOR_SUSPENSION, $this->lastNotOpenedReason());
        $this->getJson('/api/v1/customers', $headers)->assertForbidden();
    }

    // ---- R1 ----------------------------------------------------------------------------------

    public function test_a_lifecycle_suspension_lifted_by_a_full_receipt_lets_a_cashier_sign_in_normally(): void
    {
        $tenant = $this->systemSuspendedTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'renewal']);
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $login = fn (string $email) => $this->postJson('/api/v1/auth/login', ['login' => $email, 'password' => 'password'], ['X-Tenant' => (string) $tenant->getTenantKey()]);

        // Before: payment recovery, the cashier gets the wrong-password answer.
        $login($cashier->email)->assertStatus(422);
        $adminLogin = $login($this->tenantAdmin($tenant)->email)->assertOk()->assertJsonPath('data.subscription_blocked', true);
        $headers = array_merge($this->tenantHeaders($tenant), ['Authorization' => 'Bearer '.$adminLogin->json('data.token')]);

        $this->upload($tenant, $invoice, '100.000', $headers);
        $this->assertNotNull($invoice->refresh()->temporary_access_started_at);

        $payload = $login($cashier->email)->assertOk()->json('data');
        $this->assertArrayNotHasKey('subscription_blocked', $payload);
        $this->assertNotSame(['billing.manage'], $payload['user']['permissions']);

        $again = $login($this->tenantAdmin($tenant)->email)->assertOk()->json('data');
        $this->assertArrayNotHasKey('subscription_blocked', $again);

        // /auth/me (what the SPA re-reads to clear its flag) says the shop is not blocked.
        $this->getJson('/api/v1/auth/me', array_merge($this->tenantHeaders($tenant), ['Authorization' => 'Bearer '.$payload['token']]))
            ->assertOk()
            ->assertJsonPath('data.subscription.status', 'suspended')
            ->assertJsonPath('data.subscription.access', 'full');
        $this->getJson('/api/v1/customers', array_merge($this->tenantHeaders($tenant), ['Authorization' => 'Bearer '.$payload['token']]))->assertOk();
    }

    // ---- S2 ----------------------------------------------------------------------------------

    public function test_upload_withdraw_loops_send_one_operator_email_per_invoice_and_slot(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $other = $this->pendingInvoice($tenant, ['type' => 'renewal']);

        $this->cancel($tenant, $this->upload($tenant, $invoice));
        $this->moveClockTo($this->t0->addMinutes(5));
        $this->cancel($tenant, $this->upload($tenant, $invoice));
        $this->moveClockTo($this->t0->addMinutes(14)->addSeconds(59));
        $this->upload($tenant, $invoice);

        $this->assertSame(3, PlatformNotification::query()->where('billing_invoice_id', $invoice->getKey())->count(), 'Every upload keeps its panel item.');
        Mail::assertQueued(BillingReceiptUploadedMail::class, 1);

        // Another invoice of the same shop in the same slot is its own e-mail.
        $this->upload($tenant, $other, '100.000');
        Mail::assertQueued(BillingReceiptUploadedMail::class, 2);

        // The next 15-minute slot mails again.
        $latest = BillingPayment::query()->where('billing_invoice_id', $invoice->getKey())->where('status', 'pending')->sole();
        $this->moveClockTo($this->t0->addMinutes(15));
        $this->cancel($tenant, $latest);
        $this->upload($tenant, $invoice);
        Mail::assertQueued(BillingReceiptUploadedMail::class, 3);
    }

    public function test_receipt_uploads_have_a_per_tenant_hourly_cap_whatever_the_user_or_address(): void
    {
        config(['rate_limits.billing_receipt_upload.per_hour' => 100, 'rate_limits.billing_receipt_upload.per_tenant_per_hour' => 2]);
        $tenant = $this->tenantOn($this->plan());
        $other = $this->tenantOn($this->plan());
        $manager = $this->createTenantUser($tenant, 'admin');
        $url = '/api/v1/billing/invoices/999999/payments';

        $this->postJson($url, [], $this->tenantHeaders($tenant))->assertStatus(422);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])->postJson($url, [], $this->tenantHeaders($tenant))->assertStatus(422);
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])->postJson($url, [], $this->tenantHeaders($tenant, $manager))->assertStatus(429);

        $this->postJson($url, [], $this->tenantHeaders($other))->assertStatus(422);
    }

    public function test_withdrawing_a_payment_is_rate_limited(): void
    {
        config(['rate_limits.billing_payment_cancel.per_hour' => 100, 'rate_limits.billing_payment_cancel.per_tenant_per_hour' => 2]);
        $tenant = $this->tenantOn($this->plan());

        $this->postJson('/api/v1/billing/payments/999998/cancel', [], $this->tenantHeaders($tenant))->assertNotFound();
        $this->postJson('/api/v1/billing/payments/999999/cancel', [], $this->tenantHeaders($tenant))->assertNotFound();
        $this->postJson('/api/v1/billing/payments/999999/cancel', [], $this->tenantHeaders($tenant))->assertStatus(429);
    }

    // ---- S3 ----------------------------------------------------------------------------------

    public function test_the_sweep_ignores_a_window_the_policy_does_not_grant(): void
    {
        $retention = (int) config('tenant_lifecycle.retention_days');
        $cancelled = $this->tenantOn($this->plan(), [
            'status' => 'cancelled',
            'suspension_reason' => 'customer_request',
            'status_changed_at' => $this->t0->subDays($retention + 1),
            'temporary_access_ends_at' => $this->t0->addHours(10),
        ]);
        $granted = $this->tenantOn($this->plan(), [
            'status' => 'read_only',
            'status_changed_at' => $this->t0->subDays(40),
            'temporary_access_ends_at' => $this->t0->addHours(10),
        ]);
        $this->endTenancy();

        $result = app(ApplyTenantLifecycleAction::class)->execute(dryRun: true);

        $this->assertContains((string) $cancelled->getKey(), $result->archiveDue, 'A cancelled shop is never lifted: the sweep still sees it.');
        $this->assertNotContains((string) $granted->getKey(), array_column($result->applied, 'tenant_id'), 'A granted window still pauses the sweep.');
    }

    // ---- S4 / S5 -----------------------------------------------------------------------------

    public function test_the_classifier_reason_is_shown_to_the_console_only(): void
    {
        $tenant = $this->expiredTrialTenant();
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $payment = $this->upload($tenant, $invoice);

        $shop = $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))->assertOk()
            ->assertJsonPath('data.payments.0.receipt_classification.value', 'transfer_receipt_image')
            ->json('data.payments.0.receipt_classification');
        $this->assertSame(['value', 'label'], array_keys($shop));

        $this->getJson('/api/v1/super-admin/billing/payments/'.$payment->getKey(), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.receipt_classification.value', 'transfer_receipt_image')
            ->assertJsonPath('data.receipt_classification.reason', 'receipt_image');
    }

    public function test_the_subscription_payload_has_no_legacy_payment_instructions(): void
    {
        $tenant = $this->tenantOn($this->plan());

        $data = $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->assertOk()->json('data');

        $this->assertSame([
            'plan', 'subscription', 'tenant_status', 'temporary_access', 'usage', 'addons', 'pending_plan_change',
            'pending_addon_request', 'available_plans', 'available_addons', 'open_invoices_count', 'partial_payment',
            'payment_methods', 'support',
        ], array_keys($data));
    }

    // ---- R3 ----------------------------------------------------------------------------------

    public function test_owned_add_ons_count_during_a_window_on_a_read_only_shop(): void
    {
        $plan = $this->plan(['max_users' => 2]);
        $tenant = $this->tenantOn($plan, ['status' => 'read_only', 'status_changed_at' => $this->t0->subDays(2)]);
        $addon = Addon::query()->create([
            'key' => 'extra_user_window', 'name_key' => 'billing.limit_resources.users', 'type' => 'recurring',
            'bundled_limits' => ['users' => 3], 'unit_price' => '50.000', 'is_active' => true, 'is_public' => true, 'sort_order' => 1,
        ]);
        $subscription = Subscription::query()->create([
            'tenant_id' => $tenant->getKey(), 'plan_id' => $plan->id, 'billing_cycle' => 'monthly', 'status' => 'past_due',
            'amount' => '100.000', 'starts_at' => $this->t0->subMonth(), 'ends_at' => $this->t0->subDays(5),
        ]);
        SubscriptionAddon::query()->create([
            'subscription_id' => $subscription->getKey(), 'addon_id' => $addon->getKey(), 'quantity' => 1, 'unit_price' => '50.000',
            'billing_cycle' => 'monthly', 'status' => SubscriptionAddonStatus::Active, 'starts_at' => $this->t0->subMonth(),
        ]);
        $features = app(TenantFeatureManagerInterface::class);
        $this->endTenancy();

        $this->assertSame(2, $features->limit($tenant, LimitResource::Users), 'Read-only: the add-on does not count.');

        // The window opens through the real flow (cache invalidated after commit).
        $invoice = $this->pendingInvoice($tenant, ['type' => 'renewal']);
        $this->upload($tenant, $invoice);
        $this->endTenancy();

        $this->assertSame(5, $features->limit($tenant->refresh(), LimitResource::Users), 'During the window the owned add-on counts (2 + 3).');
        $this->assertSame((int) $plan->getKey(), (int) $tenant->plan_id);
    }

    // ---- R5 ----------------------------------------------------------------------------------

    public function test_the_operator_email_job_rethrows_so_the_queue_retries(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $invoice = $this->pendingInvoice($tenant, ['type' => 'plan']);
        $notification = PlatformNotification::query()->create([
            'type' => PlatformNotificationType::BillingReceiptUploaded,
            'tenant_id' => (string) $tenant->getKey(),
            'billing_invoice_id' => (int) $invoice->getKey(),
            'data' => ['tenant_name' => 'Shop', 'invoice_number' => $invoice->number, 'invoice_type' => 'plan', 'amount' => '100.000', 'currency' => 'EGP', 'method' => 'instapay'],
            'dedupe_key' => 'receipt:test-r5',
        ]);
        Mail::shouldReceive('to')->andThrow(new RuntimeException('mail transport down'));

        $job = new NotifyOperatorsOfBillingEventJob((int) $notification->getKey());
        $this->assertSame(3, $job->tries);

        $this->expectException(RuntimeException::class);
        $job->handle(app(BillingOperatorRecipients::class));
    }
}
