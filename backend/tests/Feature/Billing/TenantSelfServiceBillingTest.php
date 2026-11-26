<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\CentralAuditEvent;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;

/**
 * #232 shop-side gaps (UI spec §1 / §2 / §3):
 *  4. POST /api/v1/billing/change-invoice: the shop issues its own upgrade / subscribe invoice, priced
 *     exactly like GET /billing/change-preview; open-invoice / pending-request refusals; founder hold;
 *     401 / 403 / 422; tenant isolation;
 *  5. `renew` on POST /billing/plan-change-requests, used by the console when it issues;
 *  6. `payer_reference` is optional on the receipt upload;
 *  7. /system/context carries `subscription_temporary_access` (one invoice query);
 *  8. a trial on a PAID plan may subscribe to that same plan (a free plan stays refused);
 *  9. the temporary_access block names the invoice's `plan` and `period_end`.
 */
#[Group('billing')]
final class TenantSelfServiceBillingTest extends PlanChangeTestCase
{
    private const URL = '/api/v1/billing/change-invoice';

    private const PREVIEW = '/api/v1/billing/change-preview';

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-26 12:00:00', (string) config('app.timezone')));
        $this->setTaxRate('14.000');
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function changeInvoice(Tenant $tenant, array $body): TestResponse
    {
        return $this->postJson(self::URL, $body, $this->tenantHeaders($tenant));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function preview(Tenant $tenant, array $query): array
    {
        return $this->getJson(self::PREVIEW.'?'.http_build_query($query), $this->tenantHeaders($tenant))->assertOk()->json('data');
    }

    /**
     * @param  array<string, mixed>  $preview
     */
    private function assertPreviewIsInvoice(array $preview, BillingInvoice $invoice): void
    {
        $priced = array_values(array_filter($preview['lines'], static fn (array $line): bool => $line['kind'] !== 'tax'));
        $this->assertSame(array_column($invoice->lines, 'amount'), array_column($priced, 'amount'));
        $this->assertSame($invoice->subtotal, $preview['subtotal']);
        $this->assertSame($invoice->tax, $preview['tax']);
        $this->assertSame($invoice->total, $preview['total']);
    }

    private function trialOn(Plan $plan): Tenant
    {
        return $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->addDays(5), 'subscription_ends_at' => null]);
    }

    private function invoiceFrom(TestResponse $response): BillingInvoice
    {
        $this->endTenancy();

        return BillingInvoice::query()->findOrFail((int) $response->json('data.invoice.id'));
    }

    // ------------------------------------------------------------------ 4. change-invoice

    public function test_an_upgrade_only_invoice_equals_the_preview_and_files_a_request(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $body = ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly'];

        $preview = $this->preview($tenant, $body);
        $response = $this->changeInvoice($tenant, $body)
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('billing.messages.invoice_issued'))
            ->assertJsonPath('data.held_for_price_review', false)
            ->assertJsonPath('data.invoice.type.value', 'upgrade')
            ->assertJsonPath('data.request.status.value', 'invoiced')
            ->assertJsonPath('data.request.renew', false)
            ->assertJsonStructure(['data' => ['invoice' => ['id', 'number', 'lines', 'total', 'remaining', 'payments', 'temporary_access', 'review_state'], 'request' => ['id']]]);

        $invoice = $this->invoiceFrom($response);
        $this->assertPreviewIsInvoice($preview, $invoice);
        // (999 - 499) x 10 / 30 = 166.667; + 14% (23.333) = 190.000
        $this->assertSame('166.667', $invoice->lines[0]['amount']);
        $this->assertSame('190.000', $invoice->total);
        $this->assertNull($invoice->issued_by);

        $request = PlanChangeRequest::query()->sole();
        $this->assertSame((string) $tenant->getKey(), $request->tenant_id);
        $this->assertSame((int) $invoice->getKey(), $request->billing_invoice_id);
        $this->assertNull($request->reviewed_by);
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::PlanChangeRequested->value)->count());
        $this->assertTrue(CentralAuditLog::query()->where('event', CentralAuditEvent::PlanChangeInvoiced->value)->sole()->properties['self_service']);
    }

    public function test_an_upgrade_with_renewal_equals_the_preview(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $body = ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly', 'renew' => true];

        $preview = $this->preview($tenant, $body);
        $invoice = $this->invoiceFrom($this->changeInvoice($tenant, $body)->assertCreated()->assertJsonPath('data.request.renew', true));

        $this->assertPreviewIsInvoice($preview, $invoice);
        $this->assertCount(2, $invoice->lines);
        $this->assertSame('999.000', $invoice->lines[1]['amount']);
        // 166.667 + 999.000 = 1165.667; + 14% (163.193) = 1328.860
        $this->assertSame('1328.860', $invoice->total);
    }

    public function test_a_trial_subscribe_equals_the_preview_and_starts_the_term_at_the_transfer(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'price_yearly' => '9990.000']);
        $tenant = $this->trialOn($small);
        $body = ['action' => 'subscribe', 'plan_id' => $big->getKey(), 'cycle' => 'yearly'];

        $preview = $this->preview($tenant, $body);
        $this->assertSame('subscribe', $preview['kind']);
        $invoice = $this->invoiceFrom($this->changeInvoice($tenant, $body)->assertCreated());

        $this->assertPreviewIsInvoice($preview, $invoice);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame('9990.000', $invoice->lines[0]['amount']);
        // 9990.000 + 14% (1398.600) = 11388.600
        $this->assertSame('11388.600', $invoice->total);
    }

    public function test_an_open_invoice_is_409_and_files_nothing(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $open = $this->pendingInvoice($tenant, ['type' => 'renewal']);

        $this->changeInvoice($tenant, ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly'])
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.invoice_duplicate')
            ->assertJsonPath('details.invoice_id', $open->getKey());

        $this->assertSame(0, PlanChangeRequest::query()->count());
        $this->assertSame(1, BillingInvoice::query()->count());
    }

    public function test_a_second_call_after_an_issued_invoice_is_409_and_a_pending_request_is_422(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $body = ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly'];

        $this->changeInvoice($tenant, $body)->assertCreated();
        $this->changeInvoice($tenant, $body)->assertStatus(409)->assertJsonPath('error_code', 'billing.invoice_duplicate');

        $other = $this->subscribedTenant($small, 10);
        $this->requestRow($other, ['requested_plan_id' => $big->getKey()]);
        $this->changeInvoice($other, $body)->assertStatus(422)->assertJsonPath('error_code', 'billing.request_already_pending');

        $this->assertSame(1, BillingInvoice::query()->count());
    }

    public function test_plan_refusals_use_the_preview_codes(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $cheaper = $this->plan(['price_monthly' => '299.000']);
        $hidden = $this->plan(['price_monthly' => '999.000', 'is_public' => false]);
        $free = $this->plan(['price_monthly' => '0.000', 'price_yearly' => '0.000', 'is_free' => true]);
        $tenant = $this->subscribedTenant($small, 10);

        $codes = [
            [999999, 'billing.request_plan_unavailable'],
            [$hidden->getKey(), 'billing.request_plan_unavailable'],
            [$small->getKey(), 'billing.request_same_plan'],
            [$cheaper->getKey(), 'billing.upgrade_not_higher'],
            [$free->getKey(), 'billing.free_plan_not_billable'],
        ];
        foreach ($codes as [$planId, $code]) {
            $this->changeInvoice($tenant, ['action' => 'upgrade', 'plan_id' => $planId, 'cycle' => 'monthly'])
                ->assertStatus(422)->assertJsonPath('error_code', $code);
        }

        $this->assertSame(0, PlanChangeRequest::query()->count());
        $this->assertSame(0, BillingInvoice::query()->count());
    }

    public function test_an_agreed_founder_price_is_held_for_review_with_no_invoice(): void
    {
        $small = $this->plan(['price_monthly' => '499.000', 'founder_price_monthly' => '299.000']);
        $big = $this->plan(['price_monthly' => '999.000', 'founder_price_monthly' => '699.000']);
        $tenant = $this->subscribedTenant($small, 10);
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['is_founder' => true, 'founder_price_until' => now()->addMonths(6)]);
        $body = ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly', 'renew' => true];

        $this->assertTrue($this->preview($tenant, $body)['held_for_price_review']);

        $this->changeInvoice($tenant, $body)
            ->assertCreated()
            ->assertJsonPath('message', __('billing.messages.plan_change_requested'))
            ->assertJsonPath('data.held_for_price_review', true)
            ->assertJsonPath('data.invoice', null)
            ->assertJsonPath('data.request.status.value', 'pending')
            ->assertJsonPath('data.request.renew', true);

        $request = PlanChangeRequest::query()->sole();
        $this->assertNotNull($request->founder_review_held_at);
        $this->assertSame(0, BillingInvoice::query()->count());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::PlanChangeHeldForFounderReview->value)->count());

        // The owner releases it from the console; the shop's `renew` choice is used.
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $this->postJson('/api/v1/super-admin/billing/plan-change-requests/'.$request->getKey().'/invoice', ['release_founder_hold' => true], $headers)
            ->assertCreated();
        $this->assertCount(2, BillingInvoice::query()->sole()->lines);
    }

    public function test_it_is_401_for_guests_403_without_the_permission_and_422_on_bad_input(): void
    {
        $tenant = $this->subscribedTenant($this->plan(), 10);
        $user = $this->createTenantUser($tenant, null, ['items.view']);
        $body = ['action' => 'upgrade', 'plan_id' => 1, 'cycle' => 'monthly'];

        $this->postJson(self::URL, $body, $this->tenantGuestHeaders($tenant))->assertUnauthorized();
        $this->postJson(self::URL, $body, $this->tenantHeaders($tenant, $user))->assertForbidden();

        $this->changeInvoice($tenant, [])->assertStatus(422)->assertJsonValidationErrors(['action', 'plan_id', 'cycle']);
        $this->changeInvoice($tenant, ['action' => 'renew', 'plan_id' => 'x', 'cycle' => 'biennial', 'renew' => 'maybe'])
            ->assertStatus(422)->assertJsonValidationErrors(['action', 'plan_id', 'cycle', 'renew']);

        $this->assertSame(0, PlanChangeRequest::query()->count());
    }

    public function test_each_shop_only_issues_and_sees_its_own_invoice(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $a = $this->subscribedTenant($small, 10);
        $b = $this->subscribedTenant($small, 20);

        $invoice = $this->invoiceFrom($this->changeInvoice($a, ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly'])->assertCreated());

        $this->assertSame((string) $a->getKey(), $invoice->tenant_id);
        $this->getJson('/api/v1/billing/invoices', $this->tenantHeaders($b))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $this->tenantHeaders($b))->assertNotFound();
        // B is untouched and can still issue its own (priced on its own 20 days: 333.333).
        $own = $this->invoiceFrom($this->changeInvoice($b, ['action' => 'upgrade', 'plan_id' => $big->getKey(), 'cycle' => 'monthly'])->assertCreated());
        $this->assertSame('333.333', $own->lines[0]['amount']);
    }

    public function test_a_read_only_shop_can_still_subscribe(): void
    {
        $plan = $this->plan(['price_monthly' => '499.000']);
        $tenant = $this->tenantOn($plan, ['status' => 'trial', 'trial_ends_at' => now()->subDays(30), 'subscription_ends_at' => null]);

        $this->changeInvoice($tenant, ['action' => 'subscribe', 'plan_id' => $plan->getKey(), 'cycle' => 'monthly'])->assertCreated();
    }

    // ------------------------------------------------------------------ 8. trial subscribes to its own plan

    public function test_a_trial_on_a_paid_plan_subscribes_to_that_plan(): void
    {
        $plan = $this->plan(['price_monthly' => '499.000']);
        $tenant = $this->trialOn($plan);
        $body = ['action' => 'subscribe', 'plan_id' => $plan->getKey(), 'cycle' => 'monthly'];

        $preview = $this->preview($tenant, $body);
        $this->assertSame('subscribe', $preview['kind']);
        // The same plan is accepted under `upgrade` too while no paid term runs.
        $this->assertSame($preview['total'], $this->preview($tenant, ['action' => 'upgrade'] + $body)['total']);

        $invoice = $this->invoiceFrom($this->changeInvoice($tenant, $body)->assertCreated());
        $this->assertPreviewIsInvoice($preview, $invoice);
        // 499.000 + 14% (69.860) = 568.860
        $this->assertSame('568.860', $invoice->total);

        $payment = BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->getKey(), 'tenant_id' => $tenant->getKey(), 'amount' => $invoice->total,
            'method' => 'instapay', 'gateway' => 'manual', 'status' => 'pending', 'paid_at' => now(),
        ]);
        $transfer = now()->subHours(2)->startOfSecond();
        $this->postJson('/api/v1/super-admin/billing/payments/'.$payment->getKey().'/verify', $this->verifyBody($transfer), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertOk();

        $fresh = Tenant::query()->findOrFail($tenant->getKey());
        $this->assertSame((int) $plan->getKey(), (int) $fresh->plan_id);
        $this->assertSame(CarbonImmutable::instance($transfer)->addMonthNoOverflow()->getTimestamp(), $fresh->subscription_ends_at?->getTimestamp());
        $this->assertSame('completed', PlanChangeRequest::query()->sole()->status->value);
    }

    public function test_the_same_plan_stays_refused_on_a_free_plan_or_a_running_term(): void
    {
        $free = $this->plan(['price_monthly' => '0.000', 'price_yearly' => '0.000', 'is_free' => true]);
        $freeTrial = $this->trialOn($free);
        $this->changeInvoice($freeTrial, ['action' => 'subscribe', 'plan_id' => $free->getKey(), 'cycle' => 'monthly'])
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.free_plan_not_billable');

        $paid = $this->plan(['price_monthly' => '499.000']);
        $running = $this->subscribedTenant($paid, 10);
        $this->changeInvoice($running, ['action' => 'subscribe', 'plan_id' => $paid->getKey(), 'cycle' => 'monthly'])
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.request_same_plan');
        $this->getJson(self::PREVIEW.'?'.http_build_query(['action' => 'subscribe', 'plan_id' => $paid->getKey(), 'cycle' => 'monthly']), $this->tenantHeaders($running))
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.request_same_plan');
    }

    public function test_the_support_request_endpoint_accepts_the_same_plan_during_a_trial(): void
    {
        $plan = $this->plan(['price_monthly' => '499.000']);
        $tenant = $this->trialOn($plan);

        $this->postJson('/api/v1/billing/plan-change-requests', ['kind' => 'plan_change', 'requested_plan_code' => $plan->slug, 'billing_cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertCreated();
    }

    // ------------------------------------------------------------------ 5. renew on the support request

    public function test_the_stored_renew_choice_is_used_when_the_console_issues(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);

        $id = (int) $this->postJson('/api/v1/billing/plan-change-requests', [
            'kind' => 'plan_change', 'requested_plan_code' => $big->slug, 'billing_cycle' => 'monthly', 'renew' => true,
        ], $this->tenantHeaders($tenant))->assertCreated()->assertJsonPath('data.renew', true)->json('data.id');

        $this->endTenancy();
        $this->assertTrue(PlanChangeRequest::query()->findOrFail($id)->renew);

        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $this->postJson('/api/v1/super-admin/billing/plan-change-requests/'.$id.'/invoice', [], $headers)->assertCreated();
        $this->assertCount(2, BillingInvoice::query()->sole()->lines);
    }

    public function test_an_explicit_with_renewal_false_overrides_the_stored_choice(): void
    {
        $small = $this->plan(['price_monthly' => '499.000']);
        $big = $this->plan(['price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small, 10);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey(), 'renew' => true]);

        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $this->postJson('/api/v1/super-admin/billing/plan-change-requests/'.$row->getKey().'/invoice', ['with_renewal' => false], $headers)->assertCreated();
        $this->assertCount(1, BillingInvoice::query()->sole()->lines);
    }

    public function test_renew_on_the_support_request_must_be_boolean(): void
    {
        $tenant = $this->subscribedTenant($this->plan(), 10);

        $this->postJson('/api/v1/billing/plan-change-requests', ['kind' => 'plan_change', 'requested_plan_code' => 'x', 'billing_cycle' => 'monthly', 'renew' => 'maybe'], $this->tenantHeaders($tenant))
            ->assertStatus(422)->assertJsonValidationErrors(['renew']);
    }

    // ------------------------------------------------------------------ 6. optional payer_reference

    public function test_a_receipt_can_be_sent_without_a_payer_reference(): void
    {
        $tenant = $this->createTenant();
        $invoice = $this->pendingInvoice($tenant);
        $url = '/api/v1/billing/invoices/'.$invoice->getKey().'/payments';
        $headers = $this->multipart($this->tenantHeaders($tenant));
        $payload = ['receipt' => $this->receipt(), 'method' => 'instapay', 'amount' => '100.000', 'paid_at' => now()->toDateString()];

        $this->post($url, $payload + ['payer_reference' => 'ab'], $headers)->assertStatus(422)->assertJsonValidationErrors(['payer_reference']);

        $this->post($url, $payload + ['payer_reference' => ''], $headers)
            ->assertCreated()
            ->assertJsonPath('data.payer_reference', null);

        $this->endTenancy();
        $this->assertSame([], BillingPayment::query()->sole()->raw_payload);
    }

    // ------------------------------------------------------------------ 7 + 9. temporary-access banner

    private function windowInvoice(Tenant $tenant, Plan $plan): BillingInvoice
    {
        return $this->pendingInvoice($tenant, [
            'type' => 'plan',
            'billing_cycle' => 'monthly',
            'period_start' => now()->startOfDay(),
            'period_end' => now()->startOfDay()->addMonth(),
            'lines' => [['type' => 'plan', 'plan_id' => $plan->getKey(), 'plan_name' => 'Growth', 'billing_cycle' => 'monthly', 'quantity' => 1, 'unit_price' => '100.000', 'amount' => '100.000']],
            'temporary_access_started_at' => now()->subHours(43),
            'temporary_access_ends_at' => now()->addHours(5),
        ]);
    }

    public function test_system_context_carries_the_temporary_access_banner_with_one_invoice_query(): void
    {
        $plan = $this->plan(['name' => 'Growth']);
        $tenant = $this->trialOn($plan);
        $invoice = $this->windowInvoice($tenant, $plan);
        $headers = $this->tenantHeaders($tenant);

        $central = DB::connection((string) (new BillingInvoice)->getConnectionName());
        $central->flushQueryLog();
        $central->enableQueryLog();
        $response = $this->getJson('/api/v1/system/context', $headers)->assertOk();
        $invoiceQueries = count(array_filter($central->getQueryLog(), static fn (array $q): bool => str_contains((string) $q['query'], 'billing_invoices')));
        $central->disableQueryLog();

        $response
            ->assertJsonPath('data.subscription_temporary_access.state', 'active')
            ->assertJsonPath('data.subscription_temporary_access.invoice_id', $invoice->getKey())
            ->assertJsonPath('data.subscription_temporary_access.invoice_type', 'plan')
            ->assertJsonPath('data.subscription_temporary_access.seconds_left', 5 * 3600)
            ->assertJsonPath('data.subscription_temporary_access.last_hours', true)
            ->assertJsonPath('data.subscription_temporary_access.ends_at', now()->addHours(5)->toIso8601String())
            ->assertJsonPath('data.subscription_temporary_access.plan', ['id' => $plan->getKey(), 'name' => 'Growth'])
            ->assertJsonPath('data.subscription_temporary_access.period_end', now()->startOfDay()->addMonth()->toIso8601String())
            ->assertJsonPath('data.subscription_temporary_access.rejection_reason', null);
        $this->assertSame(1, $invoiceQueries);
    }

    public function test_system_context_banner_is_null_without_a_window_or_without_billing_access(): void
    {
        $plan = $this->plan();
        $quiet = $this->trialOn($plan);
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($quiet))->assertOk()
            ->assertJsonPath('data.subscription_temporary_access', null);

        $tenant = $this->trialOn($plan);
        $this->windowInvoice($tenant, $plan);
        $cashier = $this->createTenantUser($tenant, null, ['items.view']);
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant, $cashier))->assertOk()
            ->assertJsonPath('data.subscription_temporary_access', null);
    }

    public function test_the_temporary_access_block_names_the_plan_and_period_end(): void
    {
        $plan = $this->plan(['name' => 'Growth']);
        $tenant = $this->trialOn($plan);
        $invoice = $this->windowInvoice($tenant, $plan);
        $headers = $this->tenantHeaders($tenant);
        $periodEnd = now()->startOfDay()->addMonth()->toIso8601String();

        $this->getJson('/api/v1/billing/subscription', $headers)->assertOk()
            ->assertJsonPath('data.temporary_access.plan', ['id' => $plan->getKey(), 'name' => 'Growth'])
            ->assertJsonPath('data.temporary_access.period_end', $periodEnd);
        $this->getJson('/api/v1/billing/invoices/'.$invoice->getKey(), $headers)->assertOk()
            ->assertJsonPath('data.temporary_access.plan.name', 'Growth')
            ->assertJsonPath('data.temporary_access.period_end', $periodEnd);

        // A service invoice bills no plan.
        $service = $this->pendingInvoice($tenant);
        $this->getJson('/api/v1/billing/invoices/'.$service->getKey(), $headers)->assertOk()
            ->assertJsonPath('data.temporary_access.plan', null)
            ->assertJsonPath('data.temporary_access.period_end', null);
    }
}
