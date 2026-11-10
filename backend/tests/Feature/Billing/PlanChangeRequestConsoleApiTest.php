<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\IssueSubscriptionInvoiceAction;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\LimitResource;
use App\Models\BillingInvoice;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Entitlements\TenantEntitlementService;
use PHPUnit\Framework\Attributes\Group;

/**
 * The console side of "plan change / add-on through support" and the two full flows:
 * request -> admin invoices -> shop pays -> admin verifies -> plan switched with the term kept /
 * add-on active and counted by the entitlement engine.
 */
#[Group('billing')]
final class PlanChangeRequestConsoleApiTest extends PlanChangeTestCase
{
    private const QUEUE = '/api/v1/super-admin/billing/plan-change-requests';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = $this->centralSuperAdmin();
        $this->admin = $this->steppedUpCentralHeaders($this->operator);
    }

    private function pay(Tenant $tenant, int $invoiceId, string $amount): int
    {
        $paymentId = (int) $this->post('/api/v1/billing/invoices/'.$invoiceId.'/payments', [
            'receipt' => $this->receipt(),
            'method' => 'vodafone_cash',
            'amount' => $amount,
            'payer_reference' => '01000000888',
            'paid_at' => now()->toDateString(),
        ], $this->multipart($this->tenantHeaders($tenant)))->assertCreated()->json('data.id');

        return $paymentId;
    }

    private function verify(int $paymentId): void
    {
        $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->admin)->assertOk();
    }

    public function test_the_queue_lists_requests_with_filters(): void
    {
        $small = $this->plan(['name' => 'Small']);
        $big = $this->plan(['name' => 'Big']);
        $a = $this->tenantOn($small, ['name' => 'Cafe A']);
        $b = $this->tenantOn($small, ['name' => 'Cafe B']);
        $this->requestRow($a, ['requested_plan_id' => $big->getKey(), 'billing_cycle' => 'yearly', 'notes' => 'please']);
        $this->requestRow($b, ['requested_plan_id' => $big->getKey(), 'status' => 'rejected', 'rejection_reason' => 'no']);

        $this->getJson(self::QUEUE, $this->admin)
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('success', true);

        $this->getJson(self::QUEUE.'?status=pending', $this->admin)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.tenant.name', 'Cafe A')
            ->assertJsonPath('data.0.current_plan.name', 'Small')
            ->assertJsonPath('data.0.requested_plan.name', 'Big')
            ->assertJsonPath('data.0.billing_cycle.value', 'yearly')
            ->assertJsonPath('data.0.notes', 'please')
            ->assertJsonPath('data.0.requested_by_name', 'Owner');

        $this->getJson(self::QUEUE.'?tenant_id='.$b->getKey(), $this->admin)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status.value', 'rejected');
        $this->getJson(self::QUEUE.'?status=bogus', $this->admin)->assertStatus(422)->assertJsonValidationErrors(['status']);
        $this->getJson(self::QUEUE.'?per_page=500', $this->admin)->assertStatus(422)->assertJsonValidationErrors(['per_page']);
    }

    public function test_issuing_a_plan_change_invoice_bills_only_the_difference(): void
    {
        $this->setTaxRate('14.000');
        $small = $this->plan(['name' => 'Small']);
        $big = $this->plan(['name' => 'Big', 'price_monthly' => '999.000']);
        $tenant = $this->subscribedTenant($small);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey(), 'billing_cycle' => 'monthly']);
        $this->admin = $this->steppedUpCentralHeaders($this->operator);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['amount' => '250.5', 'notes' => 'internal'], $this->admin)
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'invoiced')
            ->assertJsonPath('data.invoice.total', '285.570')
            ->assertJsonPath('data.reviewed_by.id', $this->operator->getKey());

        $invoice = BillingInvoice::query()->findOrFail($row->fresh()->billing_invoice_id);
        $this->assertSame('upgrade', $invoice->type->value);
        $this->assertSame($tenant->getKey(), $invoice->tenant_id);
        $this->assertSame('monthly', $invoice->billing_cycle?->value);
        $this->assertSame('250.500', $invoice->subtotal);
        $this->assertSame('35.070', $invoice->tax);
        $this->assertSame('285.570', $invoice->total);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame($big->getKey(), $invoice->lines[0]['plan_id']);
        $this->assertTrue($invoice->lines[0]['difference']);
        $this->assertSame(__('billing.invoice.upgrade_difference', ['from' => 'Small', 'to' => 'Big']), $invoice->lines[0]['description']);
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::PlanChangeInvoiced->value)->count());

        // The shop sees the linked invoice number on its request and can pay it.
        $this->getJson('/api/v1/billing/plan-change-requests', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.0.status.value', 'invoiced')
            ->assertJsonPath('data.0.invoice.number', $invoice->number);
    }

    public function test_a_custom_description_is_used_and_a_second_invoice_is_a_409(): void
    {
        $small = $this->plan();
        $big = $this->plan();
        $tenant = $this->subscribedTenant($small);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);
        $url = self::QUEUE.'/'.$row->getKey().'/invoice';

        $this->postJson($url, ['amount' => '100', 'description' => 'Custom diff'], $this->admin)->assertCreated();
        $this->assertSame('Custom diff', BillingInvoice::query()->firstOrFail()->lines[0]['description']);

        $this->postJson($url, ['amount' => '100'], $this->admin)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.request_not_pending');
        $this->assertSame(1, BillingInvoice::query()->count());
    }

    public function test_invoice_validation_is_422(): void
    {
        $small = $this->plan();
        $tenant = $this->subscribedTenant($small);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $this->plan()->getKey()]);
        $url = self::QUEUE.'/'.$row->getKey().'/invoice';

        $this->postJson($url, [], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->postJson($url, ['amount' => '0'], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->postJson($url, ['amount' => '-5'], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->postJson($url, ['amount' => '10.1234'], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['amount']);
        $this->postJson($url, ['amount' => '10', 'unit_price' => '0'], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['unit_price']);
        $this->postJson($url, ['amount' => '10', 'description' => '<b>x</b>'], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['description']);
        $this->postJson(self::QUEUE.'/999999/invoice', ['amount' => '10'], $this->admin)->assertNotFound();
        $this->assertTrue($row->fresh()->isPending());
        $this->assertSame(0, BillingInvoice::query()->count());
    }

    public function test_an_addon_invoice_uses_the_plan_catalog_price_or_an_override(): void
    {
        $plan = $this->plan();
        $tenant = $this->subscribedTenant($plan);
        $addon = $this->addonOn($plan, unit: '79.500', yearly: '795.000');
        $row = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey(), 'quantity' => 3, 'billing_cycle' => 'monthly']);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'invoiced');

        $invoice = BillingInvoice::query()->firstOrFail();
        $this->assertSame('addon', $invoice->type->value);
        $this->assertSame('monthly', $invoice->billing_cycle?->value);
        $this->assertCount(1, $invoice->lines);
        $this->assertSame('addon', $invoice->lines[0]['type']);
        $this->assertSame(3, $invoice->lines[0]['quantity']);
        $this->assertSame('79.500', $invoice->lines[0]['unit_price']);
        $this->assertSame('238.500', $invoice->lines[0]['amount']);
        $this->assertSame('238.500', $invoice->subtotal);

        // Yearly cycle + quantity and unit price overrides.
        $second = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey(), 'quantity' => 1, 'billing_cycle' => 'yearly']);
        $this->postJson(self::QUEUE.'/'.$second->getKey().'/invoice', ['quantity' => 2, 'unit_price' => '700'], $this->admin)->assertCreated();
        $override = BillingInvoice::query()->orderByDesc('id')->firstOrFail();
        $this->assertSame('700.000', $override->lines[0]['unit_price']);
        $this->assertSame('1400.000', $override->lines[0]['amount']);

        // Catalog yearly price by default.
        $third = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey(), 'quantity' => 1, 'billing_cycle' => 'yearly']);
        $this->postJson(self::QUEUE.'/'.$third->getKey().'/invoice', [], $this->admin)->assertCreated();
        $this->assertSame('795.000', BillingInvoice::query()->orderByDesc('id')->firstOrFail()->lines[0]['unit_price']);
    }

    public function test_an_addon_no_longer_offered_is_refused_and_nothing_is_issued(): void
    {
        $plan = $this->plan();
        $other = $this->plan();
        $tenant = $this->subscribedTenant($plan);
        $addon = $this->addonOn($other);
        $row = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.request_addon_unavailable');

        $this->assertTrue($row->fresh()->isPending());
        $this->assertSame(0, BillingInvoice::query()->count());
    }

    public function test_rejecting_needs_a_reason_and_works_once(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $row = $this->requestRow($tenant, ['requested_plan_id' => $this->plan()->getKey()]);
        $url = self::QUEUE.'/'.$row->getKey().'/reject';

        $this->postJson($url, ['reason' => 'ab'], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['reason']);
        $this->postJson($url, [], $this->admin)->assertStatus(422)->assertJsonValidationErrors(['reason']);

        $this->postJson($url, ['reason' => 'Not available on your contract'], $this->admin)
            ->assertOk()
            ->assertJsonPath('data.status.value', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Not available on your contract');

        $this->postJson($url, ['reason' => 'Again please'], $this->admin)->assertStatus(409)->assertJsonPath('error_code', 'billing.request_not_pending');
        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['amount' => '10'], $this->admin)->assertStatus(409);

        $this->getJson('/api/v1/billing/plan-change-requests', $this->tenantHeaders($tenant))
            ->assertJsonPath('data.0.rejection_reason', 'Not available on your contract');
    }

    public function test_a_cancelled_request_cannot_be_invoiced(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $row = $this->requestRow($tenant, ['requested_plan_id' => $this->plan()->getKey(), 'status' => 'cancelled']);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['amount' => '10'], $this->admin)->assertStatus(409);
    }

    public function test_authentication_and_authorization(): void
    {
        $tenant = $this->tenantOn($this->plan());
        $row = $this->requestRow($tenant, ['requested_plan_id' => $this->plan()->getKey()]);
        $id = $row->getKey();

        $routes = [
            ['GET', self::QUEUE, []],
            ['POST', self::QUEUE.'/'.$id.'/invoice', ['amount' => '10']],
            ['POST', self::QUEUE.'/'.$id.'/reject', ['reason' => 'nope nope']],
        ];

        foreach ($routes as [$method, $uri, $body]) {
            $this->json($method, $uri, $body, ['Accept' => 'application/json'])->assertUnauthorized();
            $this->json($method, $uri, $body, $this->centralHeaders($this->centralOperator(null)))->assertForbidden();
            // A tenant token does not open the control plane.
            $this->json($method, $uri, $body, ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)])->assertUnauthorized();
        }

        // Support reads but never writes.
        $support = $this->steppedUpCentralHeaders($this->centralSupport());
        $this->getJson(self::QUEUE, $support)->assertOk();
        $this->postJson(self::QUEUE.'/'.$id.'/invoice', ['amount' => '10'], $support)->assertForbidden();
        $this->postJson(self::QUEUE.'/'.$id.'/reject', ['reason' => 'nope nope'], $support)->assertForbidden();

        // Writes need a recent second factor.
        $noStepUp = $this->centralHeaders($this->centralSuperAdmin());
        $this->postJson(self::QUEUE.'/'.$id.'/invoice', ['amount' => '10'], $noStepUp)
            ->assertForbidden()->assertJsonPath('error_code', 'central_auth.step_up_required');
        $this->postJson(self::QUEUE.'/'.$id.'/reject', ['reason' => 'nope nope'], $noStepUp)
            ->assertForbidden()->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertTrue($row->fresh()->isPending());
    }

    public function test_full_upgrade_flow_switches_the_plan_and_keeps_the_paid_term(): void
    {
        $small = $this->plan(['max_users' => 2]);
        $big = $this->plan(['max_users' => 50]);
        $tenant = $this->subscribedTenant($small, 12);
        $end = $tenant->subscription_ends_at;
        $service = app(TenantEntitlementService::class);

        $this->assertSame(2, $service->entitlements($tenant)->limit(LimitResource::Users));

        // 1. the shop asks, 2. the admin invoices the difference, 3. the shop pays, 4. the admin verifies.
        $requestId = (int) $this->postJson('/api/v1/billing/plan-change-requests', ['kind' => 'plan_change', 'requested_plan_code' => $big->slug, 'billing_cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertCreated()->json('data.id');
        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$requestId.'/invoice', ['amount' => '200'], $this->admin)
            ->assertCreated()->json('data.invoice.id');
        $invoice = BillingInvoice::query()->findOrFail($invoiceId);
        $paymentId = $this->pay($tenant, $invoiceId, $invoice->total);
        $this->verify($paymentId);

        $tenant->refresh();
        $this->assertSame($big->getKey(), $tenant->plan_id);
        $this->assertSame($end->toIso8601String(), $tenant->subscription_ends_at->toIso8601String(), 'the paid term must not be extended');
        $this->assertSame('active', $tenant->status);

        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->firstOrFail();
        $this->assertSame($big->getKey(), $subscription->plan_id);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($end->toIso8601String(), $subscription->ends_at->toIso8601String());
        $this->assertSame($big->price_monthly, $subscription->amount, 'the subscription price is the plan price, not the difference');
        $this->assertSame(1, Subscription::query()->where('tenant_id', $tenant->getKey())->count());
        $this->assertSame('paid', $invoice->fresh()->status->value);

        // Limits follow at once (cache bumped); the request stays invoiced.
        $this->assertSame(50, $service->entitlements($tenant)->limit(LimitResource::Users));
        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonPath('data.plan.id', $big->getKey())->assertJsonPath('data.pending_plan_change', null);
        $this->assertSame('invoiced', PlanChangeRequest::query()->findOrFail($requestId)->status->value);

        // Verifying again changes nothing.
        $this->verify($paymentId);
        $this->assertSame($end->toIso8601String(), $tenant->fresh()->subscription_ends_at->toIso8601String());
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::SubscriptionActivated->value)->count());
    }

    public function test_a_difference_invoice_needs_a_future_paid_term(): void
    {
        $small = $this->plan();
        $big = $this->plan();
        $tenant = $this->tenantOn($small, ['status' => 'read_only', 'status_changed_at' => now()->subDay(), 'subscription_ends_at' => now()->subDays(3)]);
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['amount' => '120'], $this->admin)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.difference_needs_active_term');

        $this->assertTrue($row->fresh()->isPending());
        $this->assertSame(0, BillingInvoice::query()->count());
    }

    public function test_an_unpaid_subscription_row_with_a_future_end_does_not_count_as_a_paid_term(): void
    {
        // A provisioned-but-unpaid subscription row can carry a future ends_at; only the tenant's
        // paid subscription_ends_at may allow a difference invoice (activation extends from it).
        $small = $this->plan();
        $big = $this->plan();
        $tenant = $this->tenantOn($small, ['status' => 'read_only', 'status_changed_at' => now()->subDay(), 'subscription_ends_at' => null]);
        Subscription::query()->updateOrCreate(
            ['tenant_id' => $tenant->getKey()],
            ['plan_id' => $small->getKey(), 'status' => 'pending_payment', 'billing_cycle' => 'monthly', 'amount' => '449.000', 'starts_at' => now(), 'ends_at' => now()->addMonth()],
        );
        $row = $this->requestRow($tenant, ['requested_plan_id' => $big->getKey()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', ['amount' => '120'], $this->admin)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.difference_needs_active_term');

        $this->assertTrue($row->fresh()->isPending());
        $this->assertSame(0, BillingInvoice::query()->count());
    }

    public function test_an_addon_invoice_is_refused_when_the_subscription_is_not_in_force(): void
    {
        $plan = $this->plan();
        $tenant = $this->subscribedTenant($plan);
        $addon = $this->addonOn($plan);
        $row = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey()]);
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['ends_at' => now()->subDay()]);

        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.addon_needs_active_subscription');
        $this->assertTrue($row->fresh()->isPending());
        $this->assertSame(0, BillingInvoice::query()->count());

        // past_due still counts as in force.
        Subscription::query()->where('tenant_id', $tenant->getKey())->update(['status' => 'past_due']);
        $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)->assertCreated();
    }

    public function test_a_renewal_carries_paid_addons_forward_to_the_new_end(): void
    {
        $plan = $this->plan(['max_users' => 2]);
        $tenant = $this->subscribedTenant($plan, 15);
        $oldEnd = $tenant->subscription_ends_at;
        $addon = $this->addonOn($plan);
        $row = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey()]);
        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)->assertCreated()->json('data.invoice.id');
        $this->verify($this->pay($tenant, $invoiceId, BillingInvoice::query()->findOrFail($invoiceId)->total));
        $this->assertSame($oldEnd->toIso8601String(), SubscriptionAddon::query()->firstOrFail()->ends_at->toIso8601String());

        // A normal plan invoice (renewal shape) extends the term by one month.
        $planInvoice = app(IssueSubscriptionInvoiceAction::class)->execute(new IssueSubscriptionInvoiceDTO(
            tenantId: (string) $tenant->getKey(),
            type: BillingInvoiceType::Renewal,
            planId: (int) $plan->getKey(),
            cycle: BillingCycle::Monthly,
            useFounderPrice: false,
            serviceLines: [],
            discount: '0.000',
            notes: null,
            periodStart: null,
            issuedBy: (int) $this->operator->getKey(),
        ), $this->operator);
        $this->verify($this->pay($tenant, (int) $planInvoice->getKey(), $planInvoice->total));

        $newEnd = $tenant->fresh()->subscription_ends_at;
        $this->assertTrue($newEnd->gt($oldEnd));
        $this->assertSame($newEnd->toIso8601String(), SubscriptionAddon::query()->firstOrFail()->ends_at->toIso8601String(), 'the paid add-on must not expire on renewal day');
        $this->assertSame('active', SubscriptionAddon::query()->firstOrFail()->status->value);
    }

    public function test_full_addon_flow_makes_the_addon_count_in_the_limits(): void
    {
        $plan = $this->plan(['max_users' => 2]);
        $tenant = $this->subscribedTenant($plan, 15);
        $end = $tenant->subscription_ends_at;
        $addon = $this->addonOn($plan, limits: ['users' => 5], unit: '100.000');
        $service = app(TenantEntitlementService::class);

        $this->assertSame(2, $service->entitlements($tenant)->limit(LimitResource::Users));

        $requestId = (int) $this->postJson('/api/v1/billing/plan-change-requests', ['kind' => 'addon', 'addon_key' => $addon->key, 'quantity' => 2, 'billing_cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertCreated()->json('data.id');
        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$requestId.'/invoice', [], $this->admin)->assertCreated()->json('data.invoice.id');
        $invoice = BillingInvoice::query()->findOrFail($invoiceId);

        // Nothing is granted before the payment is verified.
        $this->assertSame(0, SubscriptionAddon::query()->count());
        $this->assertSame(2, $service->entitlements($tenant)->limit(LimitResource::Users));

        $paymentId = $this->pay($tenant, $invoiceId, $invoice->total);
        $this->verify($paymentId);

        $line = SubscriptionAddon::query()->firstOrFail();
        $this->assertSame($addon->getKey(), $line->addon_id);
        $this->assertSame(2, $line->quantity);
        $this->assertSame('active', $line->status->value);
        $this->assertSame('100.000', $line->unit_price);
        $this->assertSame($end->toIso8601String(), $line->ends_at->toIso8601String(), 'aligned to the subscription end');
        $this->assertSame($tenant->getKey(), $line->tenant_id);

        // 2 (plan) + 5 x 2 (add-on).
        $this->assertSame(12, $service->entitlements($tenant)->limit(LimitResource::Users));
        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.addons.0.key', $addon->key)
            ->assertJsonPath('data.addons.0.quantity', 2);

        // The plan and the term are untouched, and a repeated verify adds nothing.
        $tenant->refresh();
        $this->assertSame($plan->getKey(), $tenant->plan_id);
        $this->assertSame($end->toIso8601String(), $tenant->subscription_ends_at->toIso8601String());
        $this->verify($paymentId);
        $this->assertSame(2, $line->fresh()->quantity);
        $this->assertSame(1, SubscriptionAddon::query()->count());

        // A later purchase of the same add-on tops the line up.
        $again = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey(), 'quantity' => 1]);
        $secondInvoice = (int) $this->postJson(self::QUEUE.'/'.$again->getKey().'/invoice', ['unit_price' => '150'], $this->admin)->assertCreated()->json('data.invoice.id');
        $this->verify($this->pay($tenant, $secondInvoice, BillingInvoice::query()->findOrFail($secondInvoice)->total));
        $this->assertSame(3, SubscriptionAddon::query()->firstOrFail()->quantity);
        $this->assertSame('100.000', SubscriptionAddon::query()->firstOrFail()->unit_price, 'a top-up must not re-price existing units');
        $this->assertSame(1, SubscriptionAddon::query()->count());
        $this->assertSame(17, $service->entitlements($tenant)->limit(LimitResource::Users));
    }

    public function test_an_addon_without_a_future_term_runs_one_cycle_from_now(): void
    {
        $plan = $this->plan(['max_users' => 1]);
        $tenant = $this->subscribedTenant($plan, 5);
        $addon = $this->addonOn($plan);
        $this->endTenancy();
        $tenant->forceFill(['subscription_ends_at' => null])->save();

        $row = $this->requestRow($tenant, ['kind' => 'addon', 'addon_id' => $addon->getKey(), 'billing_cycle' => 'yearly']);
        $invoiceId = (int) $this->postJson(self::QUEUE.'/'.$row->getKey().'/invoice', [], $this->admin)->assertCreated()->json('data.invoice.id');
        $this->verify($this->pay($tenant, $invoiceId, BillingInvoice::query()->findOrFail($invoiceId)->total));

        $line = SubscriptionAddon::query()->firstOrFail();
        $this->assertLessThan(120, abs(now()->addYear()->diffInSeconds($line->ends_at, true)));
    }
}
