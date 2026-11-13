<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\CentralMedia;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;

/**
 * PRD-04 / ENTI-3.3: the shop's own billing API. 200 / 422 / 401 / 403, tenant isolation,
 * uploads while read-only, and the receipt landing on the central private disk.
 */
#[Group('billing')]
final class TenantBillingApiTest extends BillingTestCase
{
    private function paymentUrl(int|BillingInvoice $invoice): string
    {
        return '/api/v1/billing/invoices/'.($invoice instanceof BillingInvoice ? $invoice->getKey() : $invoice).'/payments';
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentPayload(array $overrides = []): array
    {
        return array_merge([
            'receipt' => $this->receipt(),
            'method' => 'instapay',
            'amount' => '100.000',
            'payer_reference' => '01000000777',
            'paid_at' => now()->toDateString(),
        ], $overrides);
    }

    public function test_the_shop_lists_and_opens_only_its_own_invoices(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $own = $this->pendingInvoice($a);
        $foreign = $this->pendingInvoice($b);

        $this->getJson('/api/v1/billing/invoices', $this->tenantHeaders($a))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->getKey())
            ->assertJsonPath('data.0.number', $own->number)
            ->assertJsonPath('data.0.remaining', '100.000')
            ->assertJsonPath('data.0.has_pending_payment', false)
            ->assertJsonMissingPath('data.0.tenant');

        $this->getJson('/api/v1/billing/invoices/'.$own->getKey(), $this->tenantHeaders($a))
            ->assertOk()
            ->assertJsonPath('data.lines.0.description', 'Setup')
            ->assertJsonPath('data.total', '100.000');

        // Another shop's id is a 404, never a 403: its existence is not revealed.
        $this->getJson('/api/v1/billing/invoices/'.$foreign->getKey(), $this->tenantHeaders($a))->assertNotFound();
    }

    public function test_a_shop_cannot_pay_another_shops_invoice(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $foreign = $this->pendingInvoice($b);

        $this->post($this->paymentUrl($foreign), $this->paymentPayload(), $this->multipart($this->tenantHeaders($a)))->assertNotFound();

        $this->assertSame(0, BillingPayment::query()->count());
    }

    public function test_upload_creates_a_pending_payment_with_the_receipt_on_the_central_private_disk(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);

        $response = $this->post($this->paymentUrl($invoice), $this->paymentPayload(['receipt' => $this->receipt('Ahmed Ali transfer.png')]), $this->multipart($this->tenantHeaders($tenant)));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status.value', 'pending')
            ->assertJsonPath('data.amount', '100.000')
            ->assertJsonPath('data.method.value', 'instapay')
            ->assertJsonPath('data.payer_reference', '01000000777')
            ->assertJsonPath('data.has_receipt', true)
            ->assertJsonMissingPath('data.proof_path')
            ->assertJsonMissingPath('data.verified_by');

        $payment = BillingPayment::query()->findOrFail($response->json('data.id'));
        $this->assertSame('manual', $payment->gateway->value);
        $this->assertNull($payment->gateway_reference);

        // The media row is on the CENTRAL connection and the file under the central_private root.
        $media = CentralMedia::query()->where('model_id', $payment->getKey())->firstOrFail();
        $this->assertSame('central_private', $media->disk);
        $this->assertSame(BillingPayment::class, $media->model_type);
        $this->assertStringStartsWith($this->centralRoot.'/central/private/', $media->getPath());
        $this->assertFileExists($media->getPath());
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}\.png$/', $media->file_name);
        $this->assertStringNotContainsString('ahmed', strtolower($media->getPath()));

        $tenantMediaRows = $this->inTenant($tenant, fn (): int => DB::table('media')->count());
        $this->assertSame(0, $tenantMediaRows, 'Nothing may land in the tenant media table.');
    }

    public function test_upload_is_allowed_while_the_shop_is_read_only_or_past_due(): void
    {
        foreach (['read_only', 'past_due'] as $status) {
            $tenant = $this->createTenant(['status' => $status, 'status_changed_at' => now()->subDay()]);
            $invoice = $this->pendingInvoice($tenant);
            $headers = $this->multipart($this->tenantHeaders($tenant));

            $this->post($this->paymentUrl($invoice), $this->paymentPayload(), $headers)->assertCreated();
            $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $this->tenantHeaders($tenant))
                ->assertStatus($tenant->plan_id === null ? 422 : 201);

            // Reads work, other writes stay locked for a read-only shop.
            $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))->assertOk();
        }
    }

    public function test_a_read_only_shop_can_request_a_renewal_invoice_and_other_writes_stay_locked(): void
    {
        $plan = $this->plan();
        $tenant = $this->tenantOn($plan, ['status' => 'read_only', 'status_changed_at' => now()->subDay()]);

        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'yearly'], $this->tenantHeaders($tenant))
            ->assertCreated()
            ->assertJsonPath('data.type.value', 'renewal')
            ->assertJsonPath('data.billing_cycle.value', 'yearly');

        $this->postJson('/api/v1/customers', [], $this->tenantHeaders($tenant))->assertStatus(423);
    }

    // SELL-07: only a NON-PAYMENT suspension reaches billing (PaymentRecoveryTest); any other reason stays blocked.
    public function test_a_suspended_shop_is_blocked_from_billing(): void
    {
        $tenant = $this->createTenant(['status' => 'suspended', 'suspension_reason' => 'violation', 'status_changed_at' => now()->subDay()]);
        $headers = $this->tenantHeaders($tenant);

        $this->getJson('/api/v1/billing/subscription', $headers)->assertForbidden();
        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)->assertForbidden();
    }

    public function test_validation_errors_are_422(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $headers = $this->multipart($this->tenantHeaders($tenant));
        $url = $this->paymentUrl($invoice);

        $this->post($url, [], $headers)->assertStatus(422)->assertJsonValidationErrors(['receipt', 'method', 'amount', 'payer_reference', 'paid_at']);

        $svg = UploadedFile::fake()->createWithContent('receipt.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post($url, $this->paymentPayload(['receipt' => $svg]), $headers)->assertStatus(422)->assertJsonValidationErrors(['receipt']);

        $svgAsPng = UploadedFile::fake()->createWithContent('receipt.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post($url, $this->paymentPayload(['receipt' => $svgAsPng]), $headers)->assertStatus(422)->assertJsonValidationErrors(['receipt']);

        $php = UploadedFile::fake()->createWithContent('receipt.php', '<?php echo 1;');
        $this->post($url, $this->paymentPayload(['receipt' => $php]), $headers)->assertStatus(422)->assertJsonValidationErrors(['receipt']);

        $big = UploadedFile::fake()->create('receipt.pdf', 5121, 'application/pdf');
        $this->post($url, $this->paymentPayload(['receipt' => $big]), $headers)->assertStatus(422)->assertJsonValidationErrors(['receipt']);

        $this->post($url, $this->paymentPayload(['method' => 'cash']), $headers)->assertStatus(422)->assertJsonValidationErrors(['method']);
        $this->post($url, $this->paymentPayload(['amount' => '0']), $headers)->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->post($url, $this->paymentPayload(['paid_at' => now()->addDays(3)->toDateString()]), $headers)->assertStatus(422)->assertJsonValidationErrors(['paid_at']);

        $this->post($url, $this->paymentPayload(['amount' => '100.001']), $headers)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.amount_out_of_range')->assertJsonPath('details.remaining', '100.000');

        $pdf = UploadedFile::fake()->createWithContent('receipt.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n");
        $this->post($url, $this->paymentPayload(['receipt' => $pdf]), $headers)->assertCreated();

        $this->assertSame(1, BillingPayment::query()->count());
    }

    public function test_only_one_pending_payment_per_invoice(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $headers = $this->multipart($this->tenantHeaders($tenant));

        $this->post($this->paymentUrl($invoice), $this->paymentPayload(['amount' => '40.000']), $headers)->assertCreated();
        $this->post($this->paymentUrl($invoice), $this->paymentPayload(['amount' => '40.000']), $headers)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.payment_already_pending');

        $this->assertSame(1, BillingPayment::query()->count());
    }

    public function test_a_paid_or_void_invoice_takes_no_payment(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->multipart($this->tenantHeaders($tenant));

        foreach (['paid', 'void'] as $status) {
            $invoice = $this->pendingInvoice($tenant, ['status' => $status]);
            $this->post($this->paymentUrl($invoice), $this->paymentPayload(), $headers)
                ->assertStatus(409)->assertJsonPath('error_code', 'billing.invoice_not_payable');
        }
    }

    public function test_renewal_invoice_is_idempotent_per_period_and_points_to_the_existing_one(): void
    {
        $plan = $this->plan(['price_monthly' => '300.000']);
        $tenant = $this->tenantOn($plan);
        $headers = $this->tenantHeaders($tenant);

        $first = $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.total', '300.000')
            ->assertJsonPath('data.status.value', 'pending');

        $this->assertNull(BillingInvoice::query()->findOrFail($first->json('data.id'))->issued_by);

        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.invoice_duplicate')
            ->assertJsonPath('details.invoice_id', $first->json('data.id'));

        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'weekly'], $headers)->assertStatus(422)->assertJsonValidationErrors(['cycle']);
        $this->assertSame(1, BillingInvoice::query()->count());
    }

    public function test_an_unpaid_renewal_blocks_a_new_one_on_a_later_day_when_there_is_no_future_end(): void
    {
        $plan = $this->plan(['price_monthly' => '300.000']);
        $tenant = $this->tenantOn($plan);
        $headers = $this->tenantHeaders($tenant);

        Carbon::setTestNow('2026-11-10 10:00:00');
        $first = $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)->assertCreated();

        Carbon::setTestNow('2026-11-11 09:00:00');
        // SEC-03: tenant tokens die 12h after creation, so mint a fresh one for the later day.
        $headers = $this->tenantHeaders($tenant);
        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'yearly'], $headers)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.invoice_duplicate')
            ->assertJsonPath('details.invoice_id', $first->json('data.id'))
            ->assertJsonPath('details.invoice_number', $first->json('data.number'));

        // The console issuing a renewal hits the same guard.
        $admin = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices', [
            'type' => 'renewal', 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
        ], $admin)->assertStatus(409)->assertJsonPath('details.invoice_id', $first->json('data.id'));

        // Once the earlier one is void, a new one is allowed.
        BillingInvoice::query()->whereKey($first->json('data.id'))->update(['status' => 'void', 'voided_at' => now()]);
        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)->assertCreated();
        Carbon::setTestNow();
    }

    public function test_notes_are_never_shown_to_the_shop_but_are_shown_to_the_console(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant, ['notes' => 'internal: slow payer']);

        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonMissingPath('data.notes');
        $this->getJson('/api/v1/billing/invoices', $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonMissingPath('data.0.notes');

        $this->getJson('/api/v1/super-admin/billing-invoices/'.$invoice->getKey(), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()->assertJsonPath('data.notes', 'internal: slow payer');
    }

    public function test_invoice_lists_validate_per_page(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);

        $this->getJson('/api/v1/billing/invoices?per_page=1000', $headers)->assertStatus(422)->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/v1/billing/invoices?per_page=abc', $headers)->assertStatus(422);
        $this->getJson('/api/v1/billing/invoices?per_page=5', $headers)->assertOk();

        $central = $this->centralHeaders($this->centralSuperAdmin());
        $url = '/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices';
        $this->getJson($url.'?per_page=1000', $central)->assertStatus(422)->assertJsonValidationErrors(['per_page']);
        $this->getJson($url.'?per_page=5', $central)->assertOk();
    }

    public function test_renewal_invoice_requests_are_throttled_per_tenant(): void
    {
        config(['rate_limits.billing_renewal_invoice.per_hour' => 2]);
        $tenant = $this->tenantOn($this->plan());
        $headers = $this->tenantHeaders($tenant);

        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)->assertCreated();
        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)->assertStatus(409);
        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $headers)->assertStatus(429);
    }

    public function test_a_failure_after_the_receipt_is_stored_leaves_no_row_and_no_file(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);

        // The audit write is the last step after the file is stored: make it fail.
        CentralAuditLog::creating(static function (): never {
            throw new \RuntimeException('boom');
        });

        $this->withoutExceptionHandling();

        try {
            $this->post($this->paymentUrl($invoice), $this->paymentPayload(), $this->multipart($this->tenantHeaders($tenant)));
            $this->fail('The forced failure should bubble up.');
        } catch (\RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertSame(0, BillingPayment::query()->count());
        $this->assertSame(0, CentralMedia::query()->count());
        $files = is_dir($this->centralRoot) ? File::allFiles($this->centralRoot) : [];
        $this->assertSame([], array_map(static fn ($f) => $f->getPathname(), $files), 'No orphan receipt file may remain.');
    }

    public function test_a_shop_without_a_plan_cannot_request_a_renewal(): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.no_plan');
    }

    public function test_guests_are_401_and_users_without_the_permission_are_403(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $cashier = $this->createTenantUser($tenant, null, ['invoices.view']);

        $routes = [
            ['GET', '/api/v1/billing/subscription'],
            ['GET', '/api/v1/billing/invoices'],
            ['GET', '/api/v1/billing/invoices/'.$invoice->getKey()],
            ['POST', '/api/v1/billing/invoices/'.$invoice->getKey().'/payments'],
            ['POST', '/api/v1/billing/renewal-invoice'],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->json($method, $uri, [], $this->tenantGuestHeaders($tenant))->assertUnauthorized();
            $this->json($method, $uri, [], $this->tenantHeaders($tenant, $cashier))->assertForbidden();
        }

        $granted = $this->createTenantUser($tenant, null, ['billing.manage']);
        $this->getJson('/api/v1/billing/invoices', $this->tenantHeaders($tenant, $granted))->assertOk();
    }

    public function test_the_tenant_cannot_reach_other_tenants_data_through_the_central_api(): void
    {
        $tenant = $this->createTenant();

        $this->assertInstanceOf(Tenant::class, $tenant);
        $this->getJson('/api/v1/super-admin/billing/payments', $this->tenantHeaders($tenant))->assertStatus(404);
    }
}
