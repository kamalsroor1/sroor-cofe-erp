<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralPermission;
use App\Enums\SignupRequestStatus;
use App\Enums\TenantStatus;
use App\Models\BillingInvoice;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\SignupRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Branding\PlatformBranding;
use Carbon\CarbonImmutable;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * SELL-06 (owner decision 2026-11-12, option A): an approved signup always starts a trial on
 * the plan it chose (platform default length when the plan has none), the console previews
 * it before approving, the console summary follows the tenant status, and the read-only 423
 * says why and what to do next.
 */
final class SignupTrialLifecycleTest extends BillingTestCase
{
    private const SIGNUPS = '/api/v1/super-admin/signup-requests';

    private const SETTINGS = '/api/v1/super-admin/platform-settings';

    private const OWNER_PASSWORD = 'fixtureOwnerOwn123';

    private CentralUser $operator;

    /** @var list<string> tenants provisioned by approvals, dropped in tearDown */
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

    // ------------------------------------------------------------------ approval

    public function test_a_paid_plan_signup_starts_a_trial_on_that_plan_and_the_shop_can_write(): void
    {
        $basic = Plan::query()->where('slug', 'basic')->sole();
        $this->assertSame(0, $basic->trial_days, 'fixture: the catalog basic plan has no trial of its own');
        $now = CarbonImmutable::now();

        [$slug, $email] = $this->approve('basic');

        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertSame(TenantStatus::Trial->value, $tenant->status);
        $this->assertSame((int) $basic->id, (int) $tenant->plan_id);
        $this->assertSame($now->addDays(14)->toIso8601String(), $tenant->trial_ends_at?->toIso8601String());
        $this->assertNull($tenant->read_only_since);
        $this->assertNull($tenant->subscription_ends_at);

        $subscription = Subscription::query()->where('tenant_id', $slug)->sole();
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertSame((int) $basic->id, (int) $subscription->plan_id);
        $this->assertSame($now->addDays(14)->toIso8601String(), $subscription->ends_at->toIso8601String());

        // The owner works at once: a write reaches the controller and succeeds.
        $this->postJson('/api/v1/customers', ['name' => 'عميل التجربة'], $this->ownerHeaders($slug, $email))
            ->assertCreated();
    }

    public function test_a_plan_with_its_own_trial_keeps_it_whatever_the_platform_default(): void
    {
        $this->setSignupTrialDays('21');
        $free = Plan::query()->where('slug', 'free')->sole();
        $now = CarbonImmutable::now();

        [$slug] = $this->approve('free');

        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertSame(TenantStatus::Trial->value, $tenant->status);
        $this->assertSame((int) $free->id, (int) $tenant->plan_id);
        $this->assertSame($now->addDays($free->trial_days)->toIso8601String(), $tenant->trial_ends_at?->toIso8601String());
        $this->assertSame(SubscriptionStatus::Trialing, Subscription::query()->where('tenant_id', $slug)->sole()->status);
    }

    public function test_the_platform_setting_sets_the_trial_of_plans_without_one(): void
    {
        $this->setSignupTrialDays('21');
        $now = CarbonImmutable::now();

        [$slug] = $this->approve('pro');

        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertSame(TenantStatus::Trial->value, $tenant->status);
        $this->assertSame($now->addDays(21)->toIso8601String(), $tenant->trial_ends_at?->toIso8601String());
    }

    public function test_approving_the_same_request_twice_is_409_with_one_tenant_and_one_trial(): void
    {
        [$slug, , $request] = $this->approve('basic');
        $tenants = Tenant::query()->count();

        $this->postJson(self::SIGNUPS."/{$request->id}/approve", ['subdomain' => $this->slug(), 'owner_email' => 'again@owner.test'], $this->operatorHeaders())
            ->assertStatus(409);

        $this->assertSame($tenants, Tenant::query()->count());
        $this->assertSame(1, Subscription::query()->where('tenant_id', $slug)->count());
        $this->assertSame(0, BillingInvoice::query()->where('tenant_id', $slug)->count());
    }

    // ------------------------------------------------------------- full lifecycle

    public function test_trial_expiry_goes_read_only_after_the_grace_and_a_paid_invoice_makes_it_active(): void
    {
        [$slug, $email] = $this->approve('basic');
        $trialEnd = CarbonImmutable::instance(Tenant::query()->findOrFail($slug)->trial_ends_at);
        $this->changeOwnerPassword($slug, $email);

        // Trial end - 3 days: the daily command issues the first invoice (exactly one).
        $this->travelTo($trialEnd->subDays(3));
        $this->artisan('billing:issue-trial-invoices')->assertSuccessful();
        $this->artisan('billing:issue-trial-invoices')->assertSuccessful();
        $invoice = BillingInvoice::query()->where('tenant_id', $slug)->sole();
        $this->assertSame('plan', $invoice->type->value);
        $this->assertSame($trialEnd->toIso8601String(), $invoice->period_start?->toIso8601String());

        // Grace days after the trial end: still full access (SEC-01).
        $this->travelTo($trialEnd->addDays(2));
        $this->postJson('/api/v1/customers', ['name' => 'عميل السماح'], $this->ownerHeaders($slug, $email, false))->assertCreated();

        // After the grace: read-only, and the 423 names the reason and the next step.
        $this->travelTo($trialEnd->addDays(4));
        $owner = $this->ownerHeaders($slug, $email, false);
        $this->getJson('/api/v1/customers', $owner)->assertOk();
        $this->postJson('/api/v1/customers', ['name' => 'عميل مرفوض'], $owner)
            ->assertStatus(423)
            ->assertJsonPath('error_code', 'subscription.read_only')
            ->assertJsonPath('details.status', 'read_only')
            ->assertJsonPath('details.reason', 'trial_ended')
            ->assertJsonPath('details.action', 'pay_invoice')
            ->assertJsonPath('details.action_path', '/billing')
            ->assertJsonPath('details.has_pending_invoice', true)
            ->assertJsonPath('message', __('subscription.errors.read_only_guidance.trial_ended.pay_invoice'));

        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->assertSame(TenantStatus::ReadOnly->value, Tenant::query()->findOrFail($slug)->status);

        // The shop pays (receipt upload is allowed while read-only), the operator verifies: active.
        $paymentId = (int) $this->post('/api/v1/billing/invoices/'.$invoice->getKey().'/payments', [
            'receipt' => $this->receipt(),
            'method' => 'vodafone_cash',
            'amount' => $invoice->total,
            'payer_reference' => '01000000888',
            'paid_at' => now()->toDateString(),
        ], $this->multipart($owner))->assertCreated()->json('data.id');
        $this->postJson('/api/v1/super-admin/billing/payments/'.$paymentId.'/verify', [], $this->operatorHeaders())->assertOk();

        $tenant = Tenant::query()->findOrFail($slug);
        $this->assertSame(TenantStatus::Active->value, $tenant->status);
        // Paid AFTER the trial end: the month starts at the payment, as before SELL-06.
        $this->assertSame(CarbonImmutable::now()->addMonthNoOverflow()->toIso8601String(), $tenant->subscription_ends_at?->toIso8601String());
        $this->postJson('/api/v1/customers', ['name' => 'عميل بعد الدفع'], $this->ownerHeaders($slug, $email, false))->assertCreated();
    }

    // ------------------------------------------------------------------ preview

    public function test_pending_requests_carry_the_approval_preview_and_decided_ones_do_not(): void
    {
        $this->setSignupTrialDays('21');
        $now = CarbonImmutable::now();
        $basic = $this->pending(['plan_code' => 'basic']);
        $free = $this->pending(['plan_code' => 'free']);
        $default = $this->pending(['plan_code' => null]);
        $unknown = $this->pending(['plan_code' => 'nope']);
        $rejected = $this->pending(['plan_code' => 'basic', 'status' => SignupRequestStatus::Rejected->value]);

        $rows = collect($this->getJson(self::SIGNUPS, $this->operatorHeaders())->assertOk()->json('data'))->keyBy('id');

        $preview = $rows[$basic->id]['approval_preview'];
        $this->assertSame('basic', $preview['plan']['code']);
        $this->assertSame(0, $preview['plan']['trial_days']);
        $this->assertSame(21, $preview['default_trial_days']);
        $this->assertSame(21, $preview['trial_days']);
        $this->assertSame('trial', $preview['tenant_status']['value']);
        $this->assertSame(TenantStatus::Trial->label(), $preview['tenant_status']['label']);
        $this->assertSame('trialing', $preview['subscription_status']['value']);
        $this->assertSame($now->addDays(21)->toIso8601String(), $preview['trial_ends_at']);

        $this->assertSame(14, $rows[$free->id]['approval_preview']['trial_days']);
        $this->assertSame('free', $rows[$default->id]['approval_preview']['plan']['code']);
        $this->assertNull($rows[$unknown->id]['approval_preview']);
        $this->assertNull($rows[$rejected->id]['approval_preview']);
    }

    // ------------------------------------------------------- platform setting API

    public function test_the_signup_trial_days_setting_is_editable_and_validated(): void
    {
        $headers = $this->operatorHeaders();

        $this->getJson(self::SETTINGS, $headers)->assertOk()->assertJsonPath('data.signup_trial_days', 14);
        $this->putJson(self::SETTINGS, ['signup_trial_days' => 30], $headers)->assertOk()->assertJsonPath('data.signup_trial_days', 30);
        $this->assertSame('30', PlatformSetting::query()->where('key', 'signup_trial_days')->value('value'));

        foreach ([0, 91, 'abc', 1.5, -3] as $invalid) {
            $this->putJson(self::SETTINGS, ['signup_trial_days' => $invalid], $headers)
                ->assertStatus(422)->assertJsonValidationErrors(['signup_trial_days']);
        }

        // null = back to the config default.
        $this->putJson(self::SETTINGS, ['signup_trial_days' => null], $headers)->assertOk()->assertJsonPath('data.signup_trial_days', 14);
    }

    public function test_the_signup_trial_days_setting_needs_an_authorised_operator(): void
    {
        $this->putJson(self::SETTINGS, ['signup_trial_days' => 30])->assertUnauthorized();
        $this->putJson(self::SETTINGS, ['signup_trial_days' => 30], $this->steppedUpCentralHeaders($this->centralOperator(CentralPermission::ROLE_SUPPORT)))
            ->assertForbidden();
        $this->putJson(self::SETTINGS, ['signup_trial_days' => 30], $this->steppedUpCentralHeaders($this->centralOperator(null)))
            ->assertForbidden();

        $this->assertNull(PlatformSetting::query()->where('key', 'signup_trial_days')->first());
    }

    // ---------------------------------------------------------- console summary

    public function test_the_console_summary_does_not_report_a_running_period_for_a_read_only_shop(): void
    {
        $basic = Plan::query()->where('slug', 'basic')->sole();
        // As provisioned before SELL-06: read-only at birth, subscription row pending_payment with a placeholder end.
        $readOnly = $this->centralTenant($basic, ['status' => 'read_only', 'status_changed_at' => now(), 'read_only_since' => now(), 'trial_ends_at' => null]);
        Subscription::query()->create([
            'tenant_id' => $readOnly->id, 'plan_id' => $basic->id, 'billing_cycle' => 'monthly',
            'status' => SubscriptionStatus::PendingPayment, 'amount' => '449.000', 'currency' => 'EGP',
            'starts_at' => now(), 'ends_at' => now()->addDays(30),
        ]);
        $trial = $this->centralTenant($basic, ['status' => 'trial', 'trial_ends_at' => now()->addDays(10)]);
        Subscription::query()->create([
            'tenant_id' => $trial->id, 'plan_id' => $basic->id, 'billing_cycle' => 'monthly',
            'status' => SubscriptionStatus::Trialing, 'amount' => '449.000', 'currency' => 'EGP',
            'starts_at' => now(), 'ends_at' => now()->addDays(10),
        ]);
        $headers = $this->operatorHeaders();

        $this->getJson('/api/v1/super-admin/tenants/'.$readOnly->id.'/billing-invoices', $headers)
            ->assertOk()
            ->assertJsonPath('subscription.tenant.effective_status.value', 'read_only')
            ->assertJsonPath('subscription.ends_at', null)
            ->assertJsonPath('subscription.days_left', null)
            // The plan is there for the "issue invoice" form to pre-fill.
            ->assertJsonPath('subscription.plan.id', (int) $basic->id);

        $this->getJson('/api/v1/super-admin/tenants/'.$trial->id.'/billing-invoices', $headers)
            ->assertOk()
            ->assertJsonPath('subscription.tenant.effective_status.value', 'trial')
            ->assertJsonPath('subscription.ends_at', now()->addDays(10)->toIso8601String())
            ->assertJsonPath('subscription.trial_ends_at', now()->addDays(10)->toIso8601String())
            ->assertJsonPath('subscription.days_left', 10);
    }

    public function test_the_console_summary_needs_an_authorised_operator(): void
    {
        $tenant = $this->centralTenant(Plan::query()->where('slug', 'basic')->sole(), ['status' => 'trial', 'trial_ends_at' => now()->addDays(10)]);
        $url = '/api/v1/super-admin/tenants/'.$tenant->id.'/billing-invoices';

        $this->getJson($url)->assertUnauthorized();
        $this->getJson($url, $this->centralHeaders($this->centralOperator(null)))->assertForbidden();
    }

    // ----------------------------------------------------- read-only guidance

    /**
     * @return iterable<string, array{array<string, mixed>, string, string}>
     */
    public static function readOnlyReasons(): iterable
    {
        yield 'trial ended' => [['status' => 'read_only', 'trial_ends_at' => '-20 days', 'subscription_ends_at' => null], 'trial_ended', 'request_invoice'];
        yield 'never had a trial' => [['status' => 'read_only', 'trial_ends_at' => null, 'subscription_ends_at' => null], 'payment_pending', 'request_invoice'];
        yield 'paid period ended' => [['status' => 'read_only', 'trial_ends_at' => null, 'subscription_ends_at' => '-20 days'], 'subscription_ended', 'request_invoice'];
        // An operator made a shop read-only mid-trial / mid-term: never "your trial ended".
        yield 'operator, trial still running' => [['status' => 'read_only', 'trial_ends_at' => '+5 days', 'subscription_ends_at' => null], 'operator', 'contact_support'];
        yield 'operator, paid term still running' => [['status' => 'read_only', 'trial_ends_at' => '-60 days', 'subscription_ends_at' => '+20 days'], 'operator', 'contact_support'];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    #[DataProvider('readOnlyReasons')]
    public function test_the_read_only_refusal_names_the_reason_and_the_next_step(array $attributes, string $reason, string $action): void
    {
        foreach (['trial_ends_at', 'subscription_ends_at'] as $date) {
            if (is_string($attributes[$date])) {
                $attributes[$date] = now()->modify($attributes[$date]);
            }
        }
        $tenant = $this->createTenant($attributes + ['status_changed_at' => now(), 'read_only_since' => now()]);

        $this->postJson('/api/v1/customers', ['name' => 'x'], $this->tenantHeaders($tenant))
            ->assertStatus(423)
            ->assertJsonPath('details.reason', $reason)
            ->assertJsonPath('details.action', $action)
            ->assertJsonPath('details.has_pending_invoice', false)
            ->assertJsonPath('message', __('subscription.errors.read_only_guidance.'.$reason.'.'.$action));
    }

    public function test_the_read_only_hint_sees_only_the_own_pending_invoice_and_carries_the_support_contact(): void
    {
        app(PlatformBranding::class)->update(['support_email' => 'help@platform.test', 'support_phone' => '01000000777']);
        $readOnly = $this->createTenant(['status' => 'read_only', 'status_changed_at' => now(), 'read_only_since' => now(), 'subscription_ends_at' => null, 'trial_ends_at' => now()->subDays(20)]);
        $other = $this->createTenant();
        $this->pendingInvoice($other);

        // Another shop's unpaid invoice is not this shop's next step.
        $this->postJson('/api/v1/customers', ['name' => 'x'], $this->tenantHeaders($readOnly))
            ->assertStatus(423)
            ->assertJsonPath('details.action', 'request_invoice')
            ->assertJsonPath('details.has_pending_invoice', false)
            ->assertJsonPath('details.support.email', 'help@platform.test')
            ->assertJsonPath('details.support.phone', '01000000777');

        $this->pendingInvoice($readOnly);
        $this->postJson('/api/v1/customers', ['name' => 'x'], $this->tenantHeaders($readOnly))
            ->assertStatus(423)
            ->assertJsonPath('details.action', 'pay_invoice')
            ->assertJsonPath('details.has_pending_invoice', true);

        // The other (active) shop is not affected at all.
        $this->assertNotSame(423, $this->postJson('/api/v1/customers', ['name' => 'y'], $this->tenantHeaders($other))->status());
    }

    public function test_central_models_used_here_pin_the_central_connection(): void
    {
        $central = (string) config('tenancy.database.central_connection');
        $tenant = $this->createTenant();
        tenancy()->initialize($tenant);

        foreach ([new SignupRequest, new Plan, new Subscription, new BillingInvoice, new PlatformSetting] as $model) {
            $this->assertSame($central, $model->getConnectionName(), $model::class);
        }

        $this->endTenancy();
    }

    // ------------------------------------------------------------------ helpers

    /**
     * Approve a pending request for $planCode; returns [slug, owner email, request].
     *
     * @return array{0: string, 1: string, 2: SignupRequest}
     */
    private function approve(?string $planCode): array
    {
        $slug = $this->slug();
        $this->provisioned[] = $slug;
        $email = $slug.'@owner.test';
        $request = $this->pending(['plan_code' => $planCode, 'preferred_subdomain' => $slug, 'email' => $email]);

        $this->postJson(self::SIGNUPS."/{$request->id}/approve", ['owner_password' => 'secret12345'], $this->operatorHeaders())
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.approval_preview', null);

        return [$slug, $email, $request];
    }

    /**
     * Sign the owner in (changing the first-login password once) and return tenant headers.
     *
     * @return array<string, string>
     */
    private function ownerHeaders(string $slug, string $email, bool $firstLogin = true): array
    {
        if ($firstLogin) {
            $this->changeOwnerPassword($slug, $email);
        }

        $headers = ['Accept' => 'application/json', 'X-Tenant' => $slug];
        $token = (string) $this->postJson('/api/v1/auth/login', ['login' => $email, 'password' => self::OWNER_PASSWORD, 'device_name' => 'spa'], $headers)
            ->assertOk()->json('data.token');

        return $headers + ['Authorization' => 'Bearer '.$token];
    }

    private function changeOwnerPassword(string $slug, string $email): void
    {
        $headers = ['Accept' => 'application/json', 'X-Tenant' => $slug];
        $token = (string) $this->postJson('/api/v1/auth/login', ['login' => $email, 'password' => 'secret12345', 'device_name' => 'spa'], $headers)
            ->assertOk()->json('data.token');

        $this->postJson('/api/v1/auth/change-password', [
            'current_password' => 'secret12345',
            'password' => self::OWNER_PASSWORD,
            'password_confirmation' => self::OWNER_PASSWORD,
        ], $headers + ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    /**
     * @return array<string, string>
     */
    private function operatorHeaders(): array
    {
        return $this->steppedUpCentralHeaders($this->operator);
    }

    private function setSignupTrialDays(string $days): void
    {
        $this->endTenancy();
        app(PlatformBranding::class)->update(['signup_trial_days' => $days]);
    }

    /**
     * A central-only tenant row (no database): enough for the central billing endpoints.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function centralTenant(Plan $plan, array $attributes): Tenant
    {
        $id = 'sell06-'.Str::lower(Str::random(8));

        return Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create(array_merge([
            'id' => $id,
            'name' => 'محل '.$id,
            'slug' => $id,
            'email' => $id.'@central.test',
            'plan_id' => $plan->id,
            'subscription_ends_at' => null,
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

    private function slug(): string
    {
        return 'sell06-'.Str::lower(Str::random(6));
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
