<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Billing\ActivateSubscriptionAction;
use App\Actions\Tenants\CollectDueLifecycleRemindersAction;
use App\Actions\Tenants\MoveTenantToFreePlanAction;
use App\Enums\Billing\BillingPaymentMethod;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantStatus;
use App\Exceptions\TenantLifecycleException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralAuditLog;
use App\Models\Plan;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Services\Billing\SubscriptionTimelineService;
use App\Support\Tenancy\TenantLifecyclePolicy;
use Carbon\CarbonImmutable;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PLAN-10 review (docs/03-architecture/free-plan-and-self-signup-2026-11-18.md):
 *  - "on a free plan" is computed identically on the request path and in the sweep;
 *  - the sweep clears a stale paid term of an active free tenant and reactivates a legacy
 *    read-only free tenant (never an operator's read-only);
 *  - is_free cannot be switched on while shops pay for the plan; no trial on a free plan;
 *  - the catalog data migration never rewrites an edited (priced) `free` row;
 *  - downgrade to a free plan: scheduled (never invoiced), applied by the sweep at the end of the
 *    paid term, cancellable, applied at once for a trial;
 *  - payment vs move races, a trial-end invoice paid after the fallback, the free subscription row;
 *  - a trial that falls back to free is never announced as going view-only.
 */
final class FreePlanLifecycleReviewTest extends PlanChangeTestCase
{
    private const REQUESTS = '/api/v1/billing/plan-change-requests';

    private const QUEUE = '/api/v1/super-admin/billing/plan-change-requests';

    private const PLANS = '/api/v1/super-admin/plans';

    private Plan $free;

    private Plan $basic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
        $this->free = Plan::query()->where('slug', 'free')->sole();
        $this->basic = Plan::query()->where('slug', 'basic')->sole();
        Carbon::setTestNow(Carbon::now()->startOfSecond());
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ------------------------------------------------------- finding 1: same answer everywhere

    public function test_the_request_path_reads_a_free_tenant_with_a_past_paid_end_as_active(): void
    {
        $tenant = $this->tenantOn($this->free, [
            'status' => 'active', 'subscription_ends_at' => now()->subDays(40), 'grace_ends_at' => now()->subDays(37), 'status_changed_at' => now()->subDays(60),
        ]);
        $this->freeRow($tenant);

        $fresh = Tenant::query()->findOrFail($tenant->id);
        $this->assertFalse($fresh->relationLoaded('plan'));
        $this->assertTrue($fresh->lifecycleSnapshot()->onFreePlan, 'No relation needed to know the plan is free.');

        // EnsureTenantActive: writes are allowed (no 423 past_due -> read_only drift).
        $this->postJson('/api/v1/customers', ['name' => 'عميل'], $this->tenantHeaders($tenant))->assertCreated();

        $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.tenant_status.value', 'active')
            ->assertJsonPath('data.plan.is_free', true)
            ->assertJsonPath('data.subscription.is_free', true)
            ->assertJsonPath('data.subscription.status.value', 'active');
    }

    public function test_the_sweep_clears_the_stale_paid_term_of_an_active_free_tenant(): void
    {
        $tenant = $this->tenantOn($this->free, [
            'status' => 'active', 'subscription_ends_at' => now()->subDays(40), 'grace_ends_at' => now()->subDays(37), 'status_changed_at' => now()->subDays(60),
        ]);

        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertNull($fresh?->subscription_ends_at);
        $this->assertNull($fresh?->grace_ends_at);
        $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    // --------------------------------------------------------- finding 2: legacy read-only

    public function test_a_legacy_read_only_free_tenant_goes_back_to_active_instead_of_drifting_to_suspended(): void
    {
        $tenant = $this->tenantOn($this->free, [
            'status' => 'read_only', 'trial_ends_at' => now()->subDays(50), 'subscription_ends_at' => null,
            'status_changed_at' => now()->subDays(40), 'read_only_since' => now()->subDays(40),
        ]);

        // Before the sweep the policy no longer derives `suspended` (30 read-only days passed).
        $this->assertSame(TenantStatus::ReadOnly, app(TenantLifecyclePolicy::class)
            ->evaluate(Tenant::query()->findOrFail($tenant->id)->lifecycleSnapshot(), CarbonImmutable::now())->effectiveStatus);

        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertNull($fresh?->read_only_since);
        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame(['read_only', 'active'], [$event->from_status?->value, $event->to_status->value]);
        $this->assertSame('free_plan', $event->properties['source'] ?? null);

        $this->postJson('/api/v1/customers', ['name' => 'رجع يشتغل'], $this->tenantHeaders($tenant))->assertCreated();
    }

    public function test_an_operator_read_only_free_tenant_is_left_alone(): void
    {
        $tenant = $this->tenantOn($this->free, ['status' => 'read_only', 'status_changed_at' => now()->subDays(40), 'subscription_ends_at' => null]);
        TenantLifecycleEvent::query()->create([
            'tenant_id' => $tenant->id, 'from_status' => 'active', 'to_status' => 'read_only', 'actor' => 'super_admin',
        ]);

        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $this->assertSame(TenantStatus::ReadOnly->value, $tenant->fresh()?->status);
        $this->assertSame(1, TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->count());
    }

    // --------------------------------------------------------------- plan editor rules

    public function test_is_free_cannot_be_switched_on_while_shops_pay_for_the_plan(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $paid = $this->plan(['price_monthly' => '100.000', 'price_yearly' => '1000.000']);
        $this->subscribedTenant($paid);
        $payload = $this->planPayload(['is_free' => true]);

        $this->putJson(self::PLANS.'/'.$paid->id, $payload, $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_free'])
            ->assertJsonPath('errors.is_free.0', __('billing.plan_editor.free_plan_has_paying_tenants', ['count' => 1]));
        $this->assertFalse($paid->fresh()?->is_free);

        // A plan nobody pays for can become free.
        $empty = $this->plan(['price_monthly' => '100.000', 'price_yearly' => '1000.000']);
        $this->putJson(self::PLANS.'/'.$empty->id, $payload, $headers)->assertOk()->assertJsonPath('data.is_free', true);
    }

    public function test_a_free_plan_cannot_have_trial_days(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->postJson(self::PLANS, $this->planPayload(['is_free' => true, 'trial_days' => 14]), $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.trial_days.0', __('billing.plan_editor.free_plan_trial_days'));
        $this->putJson(self::PLANS.'/'.$this->free->id, $this->planPayload(['trial_days' => 7]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['trial_days']);
        $this->assertSame(0, $this->free->fresh()?->trial_days);

        $this->postJson(self::PLANS, $this->planPayload(['is_free' => false, 'price_monthly' => '9', 'price_yearly' => '90', 'trial_days' => 14]), $headers)
            ->assertCreated();
    }

    // ------------------------------------------------------------- finding 5: migration

    public function test_the_catalog_migration_never_rewrites_a_priced_free_row(): void
    {
        DB::table('plans')->where('id', $this->free->id)->update([
            'is_free' => false, 'trial_days' => 14, 'price_monthly' => '99.000', 'price_yearly' => '990.000', 'max_users' => 5,
        ]);

        $migration = require database_path('migrations/2026_11_18_200200_apply_permanent_free_plan.php');
        $migration->up();

        $row = DB::table('plans')->where('id', $this->free->id)->first();
        $this->assertNotNull($row);
        $this->assertFalse((bool) $row->is_free);
        $this->assertSame(14, (int) $row->trial_days);
        $this->assertSame(5, (int) $row->max_users);
    }

    // ------------------------------------------------------------- finding 4: downgrade

    public function test_a_downgrade_to_free_is_scheduled_never_invoiced_and_applied_at_the_end_of_the_term(): void
    {
        $tenant = $this->subscribedTenant($this->basic, 10);
        $headers = $this->tenantHeaders($tenant);

        $id = (int) $this->postJson(self::REQUESTS, ['kind' => 'plan_change', 'requested_plan_code' => 'free', 'billing_cycle' => 'monthly'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'scheduled')
            ->assertJsonPath('data.downgrade_to_free', true)
            ->assertJsonPath('message', __('billing.messages.downgrade_scheduled'))
            ->json('data.id');

        $this->getJson('/api/v1/billing/subscription', $headers)
            ->assertOk()
            ->assertJsonPath('data.pending_plan_change.id', $id)
            ->assertJsonPath('data.pending_plan_change.status.value', 'scheduled');

        // Never reaches the invoice action (not pending), and not listed as pending for support.
        $console = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $this->postJson(self::QUEUE.'/'.$id.'/invoice', ['amount' => '10'], $console)
            ->assertStatus(409)->assertJsonPath('error_code', 'billing.request_not_pending');
        $this->assertSame(0, BillingInvoice::query()->where('tenant_id', $tenant->id)->count());

        // Only one open plan change at a time.
        $this->postJson(self::REQUESTS, ['kind' => 'plan_change', 'requested_plan_code' => 'pro', 'billing_cycle' => 'monthly'], $headers)
            ->assertStatus(422)->assertJsonPath('error_code', 'billing.request_already_pending');

        // The term still runs: nothing moves.
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->assertSame((int) $this->basic->id, (int) $tenant->fresh()?->plan_id);

        $this->travel(11)->days();
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertSame((int) $this->free->id, (int) $fresh?->plan_id);
        $this->assertNull($fresh?->subscription_ends_at);
        $this->assertSame(PlanChangeRequestStatus::Completed, PlanChangeRequest::query()->findOrFail($id)->status);

        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame(['active', 'active'], [$event->from_status?->value, $event->to_status->value]);
        $this->assertSame('downgrade', $event->properties['source'] ?? null);
        $this->assertSame($id, (int) ($event->properties['plan_change_request_id'] ?? 0));
        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMovedToFreePlan->value)->sole();
        $this->assertSame('downgrade', $audit->properties['source'] ?? null);

        $rows = Subscription::query()->where('tenant_id', $tenant->id)->orderBy('id')->get();
        $this->assertSame([SubscriptionStatus::Expired, SubscriptionStatus::Active], $rows->pluck('status')->all());
        $this->assertSame('0.000', $rows->last()?->amount);

        // Idempotent, and the shop keeps working on the free plan.
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->assertSame(1, TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->count());
        $this->postJson('/api/v1/customers', ['name' => 'بعد النزول'], $this->tenantHeaders($tenant))->assertCreated();
    }

    public function test_the_shop_can_cancel_a_scheduled_downgrade(): void
    {
        $tenant = $this->subscribedTenant($this->basic, 5);
        $headers = $this->tenantHeaders($tenant);
        $id = (int) $this->postJson(self::REQUESTS, ['kind' => 'plan_change', 'requested_plan_code' => 'free', 'billing_cycle' => 'monthly'], $headers)
            ->assertCreated()->json('data.id');

        $this->postJson(self::REQUESTS.'/'.$id.'/cancel', [], $headers)
            ->assertOk()->assertJsonPath('data.status.value', 'cancelled');
        $this->postJson(self::REQUESTS.'/'.$id.'/cancel', [], $headers)->assertStatus(409);

        $this->travel(6)->days();
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertSame((int) $this->basic->id, (int) $fresh?->plan_id);
        $this->assertSame(TenantStatus::PastDue->value, $fresh?->status, 'The normal paid-term path applies.');

        // The move itself refuses a cancelled request.
        $this->expectMoveRefused('subscription.status_conflict', fn () => app(MoveTenantToFreePlanAction::class)
            ->execute((string) $tenant->id, $this->free, TenantStatus::Active, MoveTenantToFreePlanAction::SOURCE_DOWNGRADE, $id));
        $this->expectMoveRefused('subscription.free_plan_move_not_due', fn () => app(MoveTenantToFreePlanAction::class)
            ->execute((string) $tenant->id, $this->free, TenantStatus::PastDue, MoveTenantToFreePlanAction::SOURCE_DOWNGRADE, $id));
    }

    public function test_a_trialing_shop_moves_to_free_at_once(): void
    {
        $tenant = $this->selfSignupTrial(CarbonImmutable::now()->addDays(10));

        $this->postJson(self::REQUESTS, ['kind' => 'plan_change', 'requested_plan_code' => 'free', 'billing_cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertCreated()
            ->assertJsonPath('data.status.value', 'completed')
            ->assertJsonPath('message', __('billing.messages.downgrade_applied'));

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertSame((int) $this->free->id, (int) $fresh?->plan_id);
        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame(['trial', 'active'], [$event->from_status?->value, $event->to_status->value]);
        $this->assertSame('downgrade', $event->properties['source'] ?? null);
    }

    public function test_paying_a_plan_invoice_cancels_a_scheduled_downgrade(): void
    {
        $tenant = $this->subscribedTenant($this->basic, 5);
        $id = (int) $this->postJson(self::REQUESTS, ['kind' => 'plan_change', 'requested_plan_code' => 'free', 'billing_cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertCreated()->json('data.id');

        $this->pay($this->planInvoice($tenant, $this->basic, 'renewal'));

        $this->assertSame(PlanChangeRequestStatus::Cancelled, PlanChangeRequest::query()->findOrFail($id)->status);
        $this->travel(40)->days();
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->assertSame((int) $this->basic->id, (int) $tenant->fresh()?->plan_id);
    }

    // ----------------------------------------------------- races and the free subscription row

    public function test_a_payment_that_wins_the_race_makes_the_fallback_move_a_status_conflict(): void
    {
        $trialEnd = CarbonImmutable::now()->addDays(5);
        $tenant = $this->selfSignupTrial($trialEnd);
        $this->artisan('billing:issue-trial-invoices')->assertSuccessful();
        $invoice = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();

        // The sweep decided "fall back" on a stale read; the payment commits first.
        $this->travelTo($trialEnd->addDays(10));
        $this->pay($invoice);

        $this->expectMoveRefused('subscription.status_conflict', fn () => app(MoveTenantToFreePlanAction::class)
            ->execute((string) $tenant->id, $this->free, TenantStatus::Trial, MoveTenantToFreePlanAction::SOURCE_TRIAL_FALLBACK));

        $fresh = $tenant->fresh();
        $this->assertSame((int) $this->basic->id, (int) $fresh?->plan_id);
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertNotNull($fresh?->subscription_ends_at);
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMovedToFreePlan->value)->count());
    }

    public function test_the_fallback_move_rechecks_the_deadline_and_the_signup_source_under_the_lock(): void
    {
        $running = $this->selfSignupTrial(CarbonImmutable::now()->addDays(5));
        $this->expectMoveRefused('subscription.free_plan_move_not_due', fn () => app(MoveTenantToFreePlanAction::class)
            ->execute((string) $running->id, $this->free, TenantStatus::Trial, MoveTenantToFreePlanAction::SOURCE_TRIAL_FALLBACK));

        $console = $this->tenantOn($this->basic, [
            'status' => 'trial', 'trial_ends_at' => now()->subDays(20), 'subscription_ends_at' => null, 'status_changed_at' => now()->subDays(34), 'signup_source' => 'console',
        ]);
        $this->expectMoveRefused('subscription.free_plan_move_not_due', fn () => app(MoveTenantToFreePlanAction::class)
            ->execute((string) $console->id, $this->free, TenantStatus::Trial, MoveTenantToFreePlanAction::SOURCE_TRIAL_FALLBACK));

        $this->assertSame((int) $this->basic->id, (int) $running->fresh()?->plan_id);
        $this->assertSame((int) $this->basic->id, (int) $console->fresh()?->plan_id);
    }

    public function test_paying_the_trial_end_invoice_after_the_fallback_upgrades_and_keeps_the_free_period_in_history(): void
    {
        $trialEnd = CarbonImmutable::now()->addDays(5);
        $tenant = $this->selfSignupTrial($trialEnd);
        $this->artisan('billing:issue-trial-invoices')->assertSuccessful();
        $invoice = BillingInvoice::query()->where('tenant_id', $tenant->id)->sole();

        $this->travelTo($trialEnd->addDays(10));
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->assertSame((int) $this->free->id, (int) $tenant->fresh()?->plan_id);
        $freeRow = Subscription::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->firstOrFail();
        $this->assertTrue($freeRow->isFreePlanRow());

        $this->travel(2)->days();
        $paidAt = CarbonImmutable::now();
        $this->pay($invoice);

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertSame((int) $this->basic->id, (int) $fresh?->plan_id);
        $this->assertSame($paidAt->addMonthNoOverflow()->toIso8601String(), $fresh?->subscription_ends_at?->toIso8601String());

        $closed = $freeRow->fresh();
        $this->assertSame(SubscriptionStatus::Expired, $closed?->status);
        $this->assertSame('0.000', $closed?->amount);
        $this->assertSame((int) $this->free->id, (int) $closed?->plan_id);
        $this->assertSame($paidAt->toIso8601String(), $closed?->ends_at->toIso8601String());

        $paid = Subscription::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->firstOrFail();
        $this->assertNotSame((int) $freeRow->id, (int) $paid->id, 'A new paid row, not the free row overwritten.');
        $this->assertSame(SubscriptionStatus::Active, $paid->status);
        $this->assertSame((int) $this->basic->id, (int) $paid->plan_id);
        $this->assertSame($invoice->subtotal, $paid->amount);
        $this->assertSame($paidAt->toIso8601String(), $paid->starts_at->toIso8601String());
    }

    // ------------------------------------------------------ timeline / banner / reminders

    public function test_a_trial_that_falls_back_to_free_is_never_announced_as_going_view_only(): void
    {
        $trialEnd = CarbonImmutable::now()->addDays(3);
        $tenant = $this->selfSignupTrial($trialEnd);

        $timeline = app(SubscriptionTimelineService::class)->forTenant(Tenant::query()->findOrFail($tenant->id), CarbonImmutable::now());
        $this->assertNotNull($timeline);
        $this->assertTrue($timeline->fallsBackToFreePlan);

        $due = collect(app(CollectDueLifecycleRemindersAction::class)->execute(CarbonImmutable::now()))
            ->firstWhere('tenantId', (string) $tenant->id);
        $this->assertNotNull($due);
        $this->assertTrue($due->fallsBackToFreePlan);

        // In the trial grace days (before the move): still `trial`, no grace / view-only countdown.
        $this->travelTo($trialEnd->addDay());
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.subscription_banner.state', 'trial')
            ->assertJsonPath('data.subscription_banner.days_left', 0)
            ->assertJsonPath('data.subscription_banner.grace_ends_at', null)
            ->assertJsonPath('data.subscription_banner.falls_back_to_free', true);

        // The bell reminder is worded "moves to the free plan".
        $this->artisan('tenants:send-lifecycle-reminders')->assertSuccessful();
        $reminder = collect($this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))->assertOk()->json('data.notifications'))
            ->firstWhere('link', '/billing');
        $this->assertNotNull($reminder);
        $this->assertSame((string) __('subscription.reminders.trial_fallback.grace'), $reminder['title']);
        $this->assertSame((string) __('subscription.reminders.trial_fallback.description'), $reminder['description']);
    }

    // ------------------------------------------------------------------ helpers

    private function selfSignupTrial(CarbonImmutable $trialEnd): Tenant
    {
        return $this->tenantOn($this->basic, [
            'status' => 'trial',
            'trial_ends_at' => $trialEnd,
            'subscription_ends_at' => null,
            'status_changed_at' => now(),
            'signup_source' => 'self_signup',
        ]);
    }

    private function freeRow(Tenant $tenant): Subscription
    {
        return Subscription::query()->create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->free->id, 'billing_cycle' => 'monthly',
            'status' => SubscriptionStatus::Active, 'amount' => '0.000', 'currency' => 'EGP',
            'starts_at' => now()->subDays(60), 'ends_at' => now()->subDays(60),
        ]);
    }

    private function planInvoice(Tenant $tenant, Plan $plan, string $type): BillingInvoice
    {
        return $this->pendingInvoice($tenant, [
            'type' => $type,
            'billing_cycle' => 'monthly',
            'lines' => [['type' => 'plan', 'plan_id' => $plan->id, 'description' => 'Plan', 'quantity' => 1, 'unit_price' => '499.000', 'amount' => '499.000']],
            'subtotal' => '499.000',
            'total' => '499.000',
        ]);
    }

    private function pay(BillingInvoice $invoice): void
    {
        $this->endTenancy();
        $payment = BillingPayment::query()->create([
            'billing_invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id, 'amount' => $invoice->total,
            'method' => BillingPaymentMethod::Instapay, 'status' => BillingPaymentStatus::Verified,
            'verified_at' => now(), 'paid_at' => now(),
        ]);

        DB::connection($this->centralConnectionName())->transaction(
            fn (): Subscription => app(ActivateSubscriptionAction::class)->execute($invoice, $payment),
        );
    }

    private function expectMoveRefused(string $errorCode, callable $move): void
    {
        try {
            $move();
            $this->fail('The move to the free plan should have been refused.');
        } catch (TenantLifecycleException $e) {
            $this->assertSame($errorCode, $e->errorCode());
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Free Custom',
            'price_monthly' => '0',
            'price_yearly' => '0',
            'max_users' => 2,
            'max_stores' => 1,
            'max_items' => 100,
            'is_active' => true,
            'is_popular' => false,
            'features' => ['pos.access' => true, 'invoices.create' => true, 'shifts.manage' => true],
        ], $overrides);
    }
}
