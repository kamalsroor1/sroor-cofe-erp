<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\IssueSubscriptionInvoiceAction;
use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\SignupRequestStatus;
use App\Enums\TenantStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\SignupRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * SELL-06 security review fixes: the read-only 423 details are for authenticated callers only;
 * paying the trial-end invoice early keeps the rest of the trial; one unpaid plan-type invoice
 * at a time; plan codes are case-insensitive; repeat signups are visible to the operator.
 */
final class SignupTrialHardeningTest extends BillingTestCase
{
    private const SIGNUPS = '/api/v1/super-admin/signup-requests';

    private CentralUser $operator;

    /** @var list<string> */
    private array $provisioned = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
        $this->operator = $this->centralSuperAdmin();
        Carbon::setTestNow(Carbon::now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        foreach ($this->provisioned as $id) {
            $this->dropProvisionedTenant($id);
        }

        parent::tearDown();
    }

    // ------------------------------------------------ 1. anonymous read-only callers

    public function test_an_anonymous_caller_gets_the_plain_refusal_and_triggers_no_billing_lookup(): void
    {
        $tenant = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now(), 'read_only_since' => now(), 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(20)]);
        $this->pendingInvoice($tenant);

        $billingQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$billingQueries): void {
            if (str_contains($query->sql, 'billing_invoices')) {
                $billingQueries++;
            }
        });

        $this->postJson('/api/v1/customers', ['name' => 'x'], $this->tenantGuestHeaders($tenant))
            ->assertStatus(423)
            ->assertJsonPath('error_code', 'subscription.read_only')
            ->assertJsonPath('details.status', 'read_only')
            ->assertJsonMissingPath('details.reason')
            ->assertJsonMissingPath('details.action')
            ->assertJsonMissingPath('details.has_pending_invoice')
            ->assertJsonMissingPath('details.support')
            ->assertJsonPath('message', __('subscription.errors.read_only'));
        $this->assertSame(0, $billingQueries, 'An anonymous caller must not trigger a billing_invoices lookup.');

        // A forged / expired token is anonymous too.
        $this->postJson('/api/v1/customers', ['name' => 'x'], $this->tenantGuestHeaders($tenant) + ['Authorization' => 'Bearer 999|not-a-real-token'])
            ->assertStatus(423)
            ->assertJsonMissingPath('details.reason');

        $this->postJson('/api/v1/customers', ['name' => 'x'], $this->tenantHeaders($tenant))
            ->assertStatus(423)
            ->assertJsonPath('details.reason', 'trial_ended')
            ->assertJsonPath('details.action', 'pay_invoice')
            ->assertJsonPath('details.has_pending_invoice', true);
        $this->assertGreaterThan(0, $billingQueries);
    }

    // --------------------------------------------- 2. early payment keeps the trial

    public function test_paying_the_trial_end_invoice_early_starts_the_month_at_the_trial_end(): void
    {
        $basic = $this->catalogPlan('basic');
        $trialEnd = CarbonImmutable::now()->addDays(3); // day 11 of a 14-day trial
        $tenant = $this->trialShop($basic, $trialEnd);

        $this->artisan('billing:issue-trial-invoices')->assertSuccessful();
        $invoice = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();
        $this->pay($tenant, $invoice); // paid the same day

        $fresh = Tenant::query()->findOrFail($tenant->id);
        $this->assertSame(TenantStatus::Active->value, $fresh->status);
        $this->assertSame($trialEnd->addMonthNoOverflow()->toIso8601String(), $fresh->subscription_ends_at?->toIso8601String());
        $subscription = Subscription::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->firstOrFail();
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame($trialEnd->addMonthNoOverflow()->toIso8601String(), $subscription->ends_at->toIso8601String());

        // Between the payment and the trial end, and well after it: full access, never locked out.
        foreach ([$trialEnd->subDay(), $trialEnd->addDay(), $trialEnd->addDays(20)] as $moment) {
            $this->travelTo($moment);
            $this->assertNotContains($this->postJson('/api/v1/customers', ['name' => 'عميل'], $this->tenantHeaders($tenant))->status(), [403, 423]);
        }
    }

    public function test_paying_inside_the_grace_days_starts_the_month_at_the_payment(): void
    {
        $basic = $this->catalogPlan('basic');
        $trialEnd = CarbonImmutable::now()->subDays(2);
        $tenant = $this->trialShop($basic, $trialEnd);

        $this->artisan('billing:issue-trial-invoices')->assertSuccessful();
        $invoice = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame($trialEnd->toIso8601String(), $invoice->period_start?->toIso8601String());
        $this->pay($tenant, $invoice);

        $this->assertSame(
            CarbonImmutable::now()->addMonthNoOverflow()->toIso8601String(),
            Tenant::query()->findOrFail($tenant->id)->subscription_ends_at?->toIso8601String(),
        );
    }

    // ------------------------------------------ 3. one unpaid plan-type invoice at a time

    public function test_a_shop_cannot_request_a_renewal_next_to_the_auto_issued_invoice(): void
    {
        $tenant = $this->trialShop($this->catalogPlan('basic'), CarbonImmutable::now()->addDays(2));
        $this->artisan('billing:issue-trial-invoices')->assertSuccessful();
        $auto = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();

        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'billing.invoice_duplicate')
            ->assertJsonPath('details.invoice_id', (int) $auto->getKey())
            ->assertJsonPath('details.invoice_number', $auto->number)
            ->assertJsonPath('details.invoice_type', 'plan')
            ->assertJsonPath('details.open_invoice', true)
            ->assertJsonPath('message', __('billing.errors.open_invoice_exists', ['number' => $auto->number]));

        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $tenant->id)->count());
    }

    public function test_the_command_does_not_issue_next_to_a_renewal_the_shop_requested(): void
    {
        $tenant = $this->trialShop($this->catalogPlan('basic'), CarbonImmutable::now()->addDays(2));

        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $this->tenantHeaders($tenant))->assertCreated();
        $this->artisan('billing:issue-trial-invoices')
            ->expectsOutputToContain((string) __('console.trial_invoices.reasons.open_invoice'))
            ->assertSuccessful();

        $this->assertSame(['renewal'], BillingInvoice::query()->where('tenant_id', $tenant->id)->pluck('type')->map(fn ($type) => $type->value)->all());
    }

    public function test_the_open_invoice_rule_is_enforced_inside_the_locked_issue_transaction(): void
    {
        $basic = $this->catalogPlan('basic');
        $tenant = $this->trialShop($basic, CarbonImmutable::now()->addDays(2));
        $existing = $this->pendingInvoice($tenant, ['type' => 'renewal']);

        try {
            app(IssueSubscriptionInvoiceAction::class)->execute(new IssueSubscriptionInvoiceDTO(
                tenantId: (string) $tenant->id,
                type: BillingInvoiceType::Plan,
                planId: (int) $basic->id,
                cycle: BillingCycle::Monthly,
                useFounderPrice: false,
                serviceLines: [],
                discount: '0.000',
                notes: null,
                periodStart: CarbonImmutable::instance($tenant->trial_ends_at),
                issuedBy: null,
                refuseWhenOpenPlanInvoice: true,
            ));
            $this->fail('An open renewal invoice must block a second plan-type invoice.');
        } catch (BillingException $e) {
            $this->assertSame('billing.invoice_duplicate', $e->errorCode());
            $this->assertTrue($e->details()['open_invoice'] ?? false);
            $this->assertSame((int) $existing->getKey(), $e->details()['invoice_id'] ?? null);
        }

        $this->assertSame(1, BillingInvoice::query()->where('tenant_id', $tenant->id)->count());
    }

    // ------------------------------------------------------- 4. plan code case

    public function test_plan_codes_are_normalised_on_signup_and_on_approval(): void
    {
        $this->postJson('/api/v1/public/signup-requests', [
            'business_name' => 'محمصة الحروف',
            'contact_name' => 'أحمد علي',
            'phone' => '01000000611',
            'email' => 'case@example.test',
            'business_type' => 'weighted',
            'plan_code' => ' PRO ',
            'terms_accepted' => true,
        ])->assertCreated();
        $this->assertSame('pro', SignupRequest::query()->where('phone', '01000000611')->sole()->plan_code);

        // An older row stored in mixed case is still previewed and approved on the right plan.
        $slug = 'sell06-'.Str::lower(Str::random(6));
        $this->provisioned[] = $slug;
        $legacy = $this->pending(['plan_code' => 'Basic', 'preferred_subdomain' => $slug, 'email' => $slug.'@owner.test']);

        $row = collect($this->getJson(self::SIGNUPS, $this->steppedUpCentralHeaders($this->operator))->assertOk()->json('data'))->firstWhere('id', $legacy->id);
        $this->assertSame('basic', $row['approval_preview']['plan']['code']);

        $this->postJson(self::SIGNUPS."/{$legacy->id}/approve", ['owner_password' => 'secret12345', 'plan_code' => 'BASIC'], $this->steppedUpCentralHeaders($this->operator))
            ->assertOk();
        $this->assertSame((int) $this->catalogPlan('basic')->id, (int) Tenant::query()->findOrFail($slug)->plan_id);
    }

    // ------------------------------------------------------ 7. repeat signups

    public function test_the_preview_lists_existing_shops_with_the_same_phone_or_email(): void
    {
        $basic = $this->catalogPlan('basic');
        $byPhone = $this->centralShop($basic, ['phone' => '+201000000123', 'status' => 'read_only']);
        $byEmail = $this->centralShop($basic, ['email' => 'Repeat.Owner@Shop.test', 'status' => 'trial', 'trial_ends_at' => now()->addDays(3)]);
        $this->centralShop($basic, ['phone' => '01099999999', 'email' => 'someone.else@shop.test']);

        $phoneMatch = $this->pending(['plan_code' => 'basic', 'phone' => '01000000123', 'email' => null]);
        $emailMatch = $this->pending(['plan_code' => 'basic', 'phone' => '01000000456', 'email' => ' repeat.owner@shop.test ']);
        $noMatch = $this->pending(['plan_code' => 'basic', 'phone' => '01000000999', 'email' => 'new.owner@shop.test']);

        $rows = collect($this->getJson(self::SIGNUPS, $this->steppedUpCentralHeaders($this->operator))->assertOk()->json('data'))->keyBy('id');

        $matches = $rows[$phoneMatch->id]['approval_preview']['matching_tenants'];
        $this->assertSame([$byPhone->id], array_column($matches, 'id'));
        $this->assertSame('read_only', $matches[0]['status']['value']);
        $this->assertSame(TenantStatus::ReadOnly->label(), $matches[0]['status']['label']);
        $this->assertSame($byPhone->name, $matches[0]['name']);
        $this->assertArrayHasKey('created_at', $matches[0]);
        $this->assertSame(['id', 'name', 'status', 'created_at'], array_keys($matches[0]));

        $this->assertSame([$byEmail->id], array_column($rows[$emailMatch->id]['approval_preview']['matching_tenants'], 'id'));
        $this->assertSame([], $rows[$noMatch->id]['approval_preview']['matching_tenants']);
    }

    // ------------------------------------------------------------------ helpers

    private function catalogPlan(string $slug): Plan
    {
        return Plan::query()->where('slug', $slug)->sole();
    }

    /** A provisioned (harness) shop on trial, with a trialing subscription. */
    private function trialShop(Plan $plan, CarbonImmutable $trialEndsAt): Tenant
    {
        $tenant = $this->createTenant([
            'plan_id' => $plan->id,
            'status' => 'trial',
            'status_changed_at' => now(),
            'trial_ends_at' => $trialEndsAt,
            'subscription_ends_at' => null,
        ]);
        Subscription::query()->create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'billing_cycle' => 'monthly',
            'status' => SubscriptionStatus::Trialing, 'amount' => $plan->price_monthly, 'currency' => 'EGP',
            'starts_at' => now(), 'ends_at' => $trialEndsAt,
        ]);

        return $tenant;
    }

    private function pay(Tenant $tenant, BillingInvoice $invoice): void
    {
        $paymentId = (int) $this->post('/api/v1/billing/invoices/'.$invoice->getKey().'/payments', [
            'receipt' => $this->receipt(),
            'method' => 'vodafone_cash',
            'amount' => $invoice->total,
            'payer_reference' => '01000000888',
            'paid_at' => now()->toDateString(),
        ], $this->multipart($this->tenantHeaders($tenant)))->assertCreated()->json('data.id');

        $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->steppedUpCentralHeaders($this->operator))->assertOk();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function centralShop(Plan $plan, array $attributes): Tenant
    {
        $id = 'sell06-'.Str::lower(Str::random(8));

        return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create(array_merge([
            'id' => $id,
            'name' => 'محل '.$id,
            'slug' => $id,
            'email' => $id.'@central.test',
            'plan_id' => $plan->id,
            'status' => 'active',
            'subscription_ends_at' => now()->addMonth(),
        ], $attributes)));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pending(array $overrides = []): SignupRequest
    {
        return SignupRequest::query()->create(array_merge([
            'business_name' => 'محمصة التجربة',
            'contact_name' => 'أحمد علي',
            'phone' => '01000000'.str_pad((string) random_int(100, 999), 3, '0', STR_PAD_LEFT),
            'email' => 'applicant@example.test',
            'business_type' => 'weighted',
            'plan_code' => null,
            'preferred_subdomain' => null,
            'status' => SignupRequestStatus::Pending->value,
        ], $overrides));
    }

    private function dropProvisionedTenant(string $id): void
    {
        $this->endTenancy();
        $tenant = Tenant::query()->find($id);
        if (! $tenant instanceof Tenant) {
            return;
        }

        $manager = $tenant->database()->manager();
        $database = (string) $tenant->database()->getName();
        if ($manager->databaseExists($database)) {
            gc_collect_cycles();
            $manager->deleteDatabase($tenant);
        }

        $storage = storage_path().'/'.config('tenancy.filesystem.suffix_base', 'tenant').$id;
        if (is_dir($storage)) {
            File::deleteDirectory($storage);
        }
    }
}
