<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Actions\Tenants\ApplyTenantLifecycleAction;
use App\DTOs\CreateTenantDTO;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\CentralPermission;
use App\Enums\TenantSignupSource;
use App\Enums\TenantStatus;
use App\Models\BillingInvoice;
use App\Models\CentralAuditLog;
use App\Models\Plan;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Services\Branding\PlatformBranding;
use App\Services\Signup\SignupTrialTerms;
use App\Services\Signup\TrialFallbackPlan;
use Carbon\CarbonImmutable;
use Database\Seeders\Catalog\PlanCatalog;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PLAN-10 (docs/03-architecture/free-plan-and-self-signup-2026-11-18.md 3, 4.5, 5):
 *  - the catalog `free` plan is the permanent free plan (values + data migration);
 *  - plans.is_free in the plan editor (every price 0, no founder / promotion price);
 *  - platform settings default_signup_plan_id / trial_fallback_plan_id / self_signup_enabled /
 *    signup_daily_cap (200 / 422 / 401 / 403);
 *  - a free tenant never leaves `active` through the lifecycle sweep and is never invoiced;
 *  - a SELF-SIGNUP trial that ends unpaid falls back to the free plan as `active`; a console
 *    trial keeps the grace -> read-only path.
 */
final class PermanentFreePlanTest extends BillingTestCase
{
    private const SETTINGS = '/api/v1/super-admin/platform-settings';

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

    // ---------------------------------------------------------------- catalog

    public function test_the_catalog_free_plan_is_the_permanent_free_plan(): void
    {
        $free = $this->free;

        $this->assertTrue($free->is_free);
        $this->assertSame(0, $free->trial_days);
        $this->assertSame('0.000', $free->price_monthly);
        $this->assertSame('0.000', $free->price_yearly);
        $this->assertNull($free->founder_price_monthly);
        $this->assertNull($free->compare_at_price_monthly);
        $this->assertSame(
            [1, 0, 0, 2, 100, 150, 200],
            [$free->max_stores, $free->max_warehouses, $free->max_vans, $free->max_users, $free->max_items, $free->max_invoices_per_month, $free->max_storage_mb],
        );

        $features = $free->features ?? [];
        $this->assertFalse($features['reports.export']);
        $this->assertFalse($features['telegram.notifications']);
        foreach (['transfers.manage', 'mixes.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs'] as $extra) {
            $this->assertFalse($features[$extra], $extra);
        }
        foreach (['pos.access', 'invoices.create', 'shifts.manage', 'treasury.view', 'returns.manage', 'purchases.manage', 'reports.basic', 'printing.thermal'] as $core) {
            $this->assertTrue($features[$core], $core);
        }

        foreach (['basic', 'pro', 'enterprise'] as $paid) {
            $plan = Plan::query()->where('slug', $paid)->sole();
            $this->assertFalse($plan->is_free, $paid);
            $this->assertTrue(($plan->features ?? [])['reports.export'], $paid.' keeps report export');
        }
    }

    public function test_the_data_migration_turns_an_existing_trial_free_row_into_the_permanent_free_plan(): void
    {
        $features = PlanCatalog::features('free');
        foreach (['reports.export', 'telegram.notifications', 'transfers.manage', 'mixes.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs'] as $key) {
            $features[$key] = true;
        }
        $features['custom.unknown'] = true;
        // The row as PRICE-2026-11 left it: a 14-day trial plan.
        DB::table('plans')->where('id', $this->free->id)->update([
            'is_free' => false, 'trial_days' => 14, 'max_warehouses' => 1, 'max_users' => 3, 'max_items' => 200,
            'max_invoices_per_month' => 300, 'max_storage_mb' => 500, 'features' => json_encode($features),
        ]);

        $migration = require database_path('migrations/2026_11_18_200200_apply_permanent_free_plan.php');
        $migration->up();
        $migration->up(); // idempotent

        $free = $this->free->fresh();
        $this->assertNotNull($free);
        $this->assertTrue($free->is_free);
        $this->assertSame([0, 0, 2, 100, 150, 200], [$free->trial_days, $free->max_warehouses, $free->max_users, $free->max_items, $free->max_invoices_per_month, $free->max_storage_mb]);
        $this->assertFalse($free->features['reports.export']);
        $this->assertFalse($free->features['audit.logs']);
        $this->assertTrue($free->features['custom.unknown'], 'Keys the migration does not know are kept.');

        $migration->down();
        $free = $this->free->fresh();
        $this->assertNotNull($free);
        $this->assertFalse($free->is_free);
        $this->assertSame([14, 1, 3], [$free->trial_days, $free->max_warehouses, $free->max_users]);
        $this->assertTrue($free->features['reports.export']);
    }

    // ------------------------------------------------------------ plan editor

    public function test_a_free_plan_must_have_zero_prices_and_no_special_price(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $before = Plan::query()->count();

        $this->postJson(self::PLANS, $this->planPayload(['is_free' => true, 'price_monthly' => '10', 'founder_price_monthly' => '5']), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_monthly', 'founder_price_monthly'])
            ->assertJsonPath('errors.price_monthly.0', __('billing.plan_editor.free_plan_price_must_be_zero'));
        $this->assertSame($before, Plan::query()->count());

        $this->postJson(self::PLANS, $this->planPayload(['is_free' => 'not-a-bool']), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['is_free']);

        $id = (int) $this->postJson(self::PLANS, $this->planPayload(['is_free' => true]), $headers)
            ->assertCreated()
            ->assertJsonPath('data.is_free', true)
            ->json('data.id');
        $this->assertTrue(Plan::query()->findOrFail($id)->is_free);

        // Update checks the stored is_free: a price on the free catalog plan is refused.
        $this->putJson(self::PLANS.'/'.$this->free->id, $this->planPayload(['price_yearly' => '100']), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['price_yearly']);
        $this->assertSame('0.000', $this->free->fresh()?->price_yearly);

        // Switching is_free off allows a price again.
        $this->putJson(self::PLANS.'/'.$id, $this->planPayload(['is_free' => false, 'price_monthly' => '99', 'price_yearly' => '990']), $headers)
            ->assertOk()->assertJsonPath('data.is_free', false);
    }

    // ------------------------------------------------------- platform settings

    public function test_the_signup_settings_are_readable_and_editable(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->getJson(self::SETTINGS, $headers)
            ->assertOk()
            ->assertJsonPath('data.default_signup_plan_id', null)
            ->assertJsonPath('data.trial_fallback_plan_id', null)
            ->assertJsonPath('data.self_signup_enabled', true)
            ->assertJsonPath('data.signup_daily_cap', 100);

        $this->putJson(self::SETTINGS, [
            'default_signup_plan_id' => $this->basic->id,
            'trial_fallback_plan_id' => $this->free->id,
            'self_signup_enabled' => false,
            'signup_daily_cap' => 25,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('data.default_signup_plan_id', (int) $this->basic->id)
            ->assertJsonPath('data.trial_fallback_plan_id', (int) $this->free->id)
            ->assertJsonPath('data.self_signup_enabled', false)
            ->assertJsonPath('data.signup_daily_cap', 25);

        $this->assertSame((string) $this->free->id, PlatformSetting::query()->where('key', 'trial_fallback_plan_id')->value('value'));
        $this->assertSame('0', PlatformSetting::query()->where('key', 'self_signup_enabled')->value('value'));
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformSettingsUpdated->value)->count());

        // null = back to the default.
        $this->putJson(self::SETTINGS, ['default_signup_plan_id' => null, 'trial_fallback_plan_id' => null, 'signup_daily_cap' => null], $headers)
            ->assertOk()
            ->assertJsonPath('data.default_signup_plan_id', null)
            ->assertJsonPath('data.trial_fallback_plan_id', null)
            ->assertJsonPath('data.signup_daily_cap', 100);
    }

    public function test_the_signup_settings_are_validated(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $inactive = $this->plan(['is_active' => false]);
        $inactiveFree = $this->plan(['is_active' => false, 'is_free' => true, 'price_monthly' => '0.000', 'price_yearly' => '0.000']);

        $this->putJson(self::SETTINGS, ['trial_fallback_plan_id' => $this->basic->id], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['trial_fallback_plan_id'])
            ->assertJsonPath('errors.trial_fallback_plan_id.0', __('billing.signup_settings.fallback_plan_invalid'));
        $this->putJson(self::SETTINGS, ['trial_fallback_plan_id' => $inactiveFree->id], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['trial_fallback_plan_id']);
        $this->putJson(self::SETTINGS, ['default_signup_plan_id' => $inactive->id], $headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.default_signup_plan_id.0', __('billing.signup_settings.default_plan_invalid'));
        $this->putJson(self::SETTINGS, ['default_signup_plan_id' => 999999], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['default_signup_plan_id']);
        $this->putJson(self::SETTINGS, ['default_signup_plan_id' => 'abc'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['default_signup_plan_id']);
        foreach ([0, -1, 100001, 'x', 1.5] as $cap) {
            $this->putJson(self::SETTINGS, ['signup_daily_cap' => $cap], $headers)
                ->assertStatus(422)->assertJsonValidationErrors(['signup_daily_cap']);
        }
        $this->putJson(self::SETTINGS, ['self_signup_enabled' => 'maybe'], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['self_signup_enabled']);
        $this->putJson(self::SETTINGS, ['self_signup_enabled' => null], $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['self_signup_enabled']);

        foreach (['default_signup_plan_id', 'trial_fallback_plan_id', 'signup_daily_cap', 'self_signup_enabled'] as $key) {
            $this->assertNull(PlatformSetting::query()->where('key', $key)->first(), $key);
        }
    }

    public function test_the_signup_settings_need_an_authorised_operator(): void
    {
        $payload = ['trial_fallback_plan_id' => $this->free->id];

        $this->putJson(self::SETTINGS, $payload)->assertUnauthorized();
        $this->getJson(self::SETTINGS)->assertUnauthorized();
        $this->putJson(self::SETTINGS, $payload, $this->steppedUpCentralHeaders($this->centralOperator(CentralPermission::ROLE_SUPPORT)))
            ->assertForbidden();
        $this->putJson(self::SETTINGS, $payload, $this->steppedUpCentralHeaders($this->centralOperator(null)))
            ->assertForbidden();
        // A tenant token is not an operator.
        $tenant = $this->tenantOn($this->basic);
        $tenantBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];
        $this->putJson(self::SETTINGS, $payload, $tenantBearer)->assertUnauthorized();

        $this->assertNull(PlatformSetting::query()->where('key', 'trial_fallback_plan_id')->first());
    }

    // ------------------------------------------------------ signup plan terms

    public function test_the_default_signup_plan_setting_picks_the_plan_of_a_signup_without_one(): void
    {
        // A fresh instance per read: the service caches the active plans for one request/job.
        $terms = new SignupTrialTerms(app(PlatformBranding::class));
        $this->assertSame('free', $terms->resolvePlan(null)?->slug, 'Without a setting: the free plan.');
        $this->assertSame(0, $terms->trialDaysFor($this->free));
        $this->assertSame(14, $terms->trialDaysFor($this->basic));

        app(PlatformBranding::class)->update(['default_signup_plan_id' => (string) $this->basic->id]);
        $this->assertSame('basic', (new SignupTrialTerms(app(PlatformBranding::class)))->resolvePlan(null)?->slug);

        // A setting naming a plan that is no longer active falls back to the catalog.
        $this->basic->forceFill(['is_active' => false])->save();
        $this->assertSame('free', (new SignupTrialTerms(app(PlatformBranding::class)))->resolvePlan(null)?->slug);
    }

    public function test_the_signup_source_defaults_to_console(): void
    {
        $base = ['name' => 'x', 'slug' => 'x', 'email' => 'x@y.test', 'plan_id' => 1, 'password' => 'secret1234'];

        $this->assertSame(TenantSignupSource::Console, CreateTenantDTO::fromArray($base)->signupSource);
        $this->assertSame(TenantSignupSource::Console, CreateTenantDTO::fromArray($base + ['signup_source' => 'nonsense'])->signupSource);
        $this->assertSame(TenantSignupSource::SelfSignup, CreateTenantDTO::fromArray($base + ['signup_source' => 'self_signup'])->signupSource);

        $this->assertSame('console', $this->tenantOn($this->basic)->fresh()?->signup_source?->value);
    }

    // ---------------------------------------------------------------- lifecycle

    public function test_a_free_tenant_never_leaves_active_through_the_lifecycle_sweep(): void
    {
        $free = $this->tenantOn($this->free, ['status' => 'active', 'subscription_ends_at' => null, 'trial_ends_at' => null, 'status_changed_at' => now()]);
        // A stale paid end must not move a free tenant to past_due either.
        $stale = $this->tenantOn($this->free, ['status' => 'active', 'subscription_ends_at' => now()->subDays(40), 'status_changed_at' => now()->subDays(60)]);

        foreach ([0, 40, 400] as $days) {
            $this->travelTo(CarbonImmutable::now()->addDays($days));
            $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        }

        foreach ([$free, $stale] as $tenant) {
            $this->assertSame(TenantStatus::Active->value, $tenant->fresh()?->status);
            $this->assertSame(0, TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->count());
        }

        // The tenant works: no 423.
        $this->postJson('/api/v1/customers', ['name' => 'عميل مجاني'], $this->tenantHeaders($free))->assertCreated();
    }

    public function test_a_legacy_free_plan_trial_is_put_back_to_active_by_the_sweep(): void
    {
        $tenant = $this->tenantOn($this->free, [
            'status' => 'trial', 'trial_ends_at' => now()->subDays(20), 'subscription_ends_at' => null, 'status_changed_at' => now()->subDays(34),
        ]);

        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertSame((int) $this->free->id, (int) $fresh?->plan_id);
        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame(['trial', 'active'], [$event->from_status?->value, $event->to_status->value]);
        $this->assertSame('free_plan', $event->properties['source'] ?? null);
    }

    public function test_a_self_signup_trial_that_ends_unpaid_falls_back_to_the_free_plan_as_active(): void
    {
        app(PlatformBranding::class)->update(['trial_fallback_plan_id' => (string) $this->free->id]);
        $trialEnd = CarbonImmutable::now()->addDays(5);
        $tenant = $this->selfSignupTrial($trialEnd);
        Subscription::query()->create([
            'tenant_id' => $tenant->id, 'plan_id' => $this->basic->id, 'billing_cycle' => 'monthly',
            'status' => SubscriptionStatus::Trialing, 'amount' => '499.000', 'currency' => 'EGP',
            'starts_at' => now()->subDays(9), 'ends_at' => $trialEnd,
        ]);

        // Past the trial and its grace days, before the sweep: the derived status is already
        // active, so the shop is never read-only for a day.
        $this->travelTo($trialEnd->addDays(10));
        $this->postJson('/api/v1/customers', ['name' => 'قبل الكنس'], $this->tenantHeaders($tenant))->assertCreated();

        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful(); // idempotent

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertSame((int) $this->free->id, (int) $fresh?->plan_id);
        $this->assertNull($fresh?->subscription_ends_at);
        $this->assertNull($fresh?->read_only_since);

        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame(['trial', 'active'], [$event->from_status?->value, $event->to_status->value]);
        $this->assertSame('billing', $event->actor->value);
        $this->assertSame('trial_fallback', $event->properties['source'] ?? null);
        $this->assertSame((int) $this->basic->id, (int) ($event->properties['from_plan_id'] ?? 0));

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMovedToFreePlan->value)->sole();
        $this->assertSame('trial_fallback', $audit->properties['source'] ?? null);
        $this->assertSame((int) $this->free->id, (int) ($audit->properties['to_plan_id'] ?? 0));

        $subscriptions = Subscription::query()->where('tenant_id', $tenant->id)->orderBy('id')->get();
        $this->assertSame([SubscriptionStatus::Expired, SubscriptionStatus::Active], $subscriptions->pluck('status')->all());
        $this->assertSame((int) $this->free->id, (int) $subscriptions->last()?->plan_id);
        $this->assertSame('0.000', $subscriptions->last()?->amount);

        // The shop keeps working on the free plan, with the free plan's entitlements.
        $this->postJson('/api/v1/customers', ['name' => 'بعد الكنس'], $this->tenantHeaders($tenant))->assertCreated();
        $this->getJson('/api/v1/transfers', $this->tenantHeaders($tenant))
            ->assertStatus(403)->assertJsonPath('error_code', 'subscription.feature_unavailable');

        // And it never moves again.
        $this->travel(400)->days();
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->assertSame(TenantStatus::Active->value, $tenant->fresh()?->status);
    }

    public function test_a_trial_whose_owner_has_another_free_shop_goes_read_only_instead_of_free(): void
    {
        // Security review: the owner already runs a free shop under a gmail alias of the same mailbox.
        $this->tenantOn($this->free, ['email' => 'trial.owner@gmail.com', 'status' => 'active', 'subscription_ends_at' => null]);
        $trialEnd = CarbonImmutable::now()->addDays(2);
        $tenant = $this->tenantOn($this->basic, [
            'email' => 'trialowner+second@googlemail.com',
            'status' => 'trial',
            'trial_ends_at' => $trialEnd,
            'subscription_ends_at' => null,
            'status_changed_at' => now(),
            'signup_source' => 'self_signup',
        ]);
        // Another self-signup trial with a phone shared with a self-signup shop is refused too.
        $this->tenantOn($this->basic, ['phone' => '+20 100 000 0777', 'signup_source' => 'self_signup', 'status' => 'active']);
        $byPhone = $this->tenantOn($this->basic, [
            'phone' => '01000000777',
            'status' => 'trial',
            'trial_ends_at' => $trialEnd,
            'subscription_ends_at' => null,
            'status_changed_at' => now(),
            'signup_source' => 'self_signup',
        ]);
        // A self-signup trial with its own identity still falls back to free.
        $clean = $this->selfSignupTrial($trialEnd);

        $this->travelTo($trialEnd->addDays(10));
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        foreach ([$tenant, $byPhone] as $refused) {
            $fresh = $refused->fresh();
            $this->assertSame(TenantStatus::ReadOnly->value, $fresh?->status);
            $this->assertSame((int) $this->basic->id, (int) $fresh?->plan_id);

            $event = TenantLifecycleEvent::query()->where('tenant_id', $refused->id)->sole();
            $this->assertSame(['trial', 'read_only'], [$event->from_status?->value, $event->to_status->value]);
            $this->assertSame(ApplyTenantLifecycleAction::FALLBACK_REFUSED_FREE_SHOP_EXISTS, $event->properties['fallback_refused'] ?? null);
            $this->assertSame('lifecycle_sweep', $event->properties['source'] ?? null);
        }

        $this->assertSame(TenantStatus::Active->value, $clean->fresh()?->status);
        $this->assertSame((int) $this->free->id, (int) $clean->fresh()?->plan_id);
    }

    public function test_a_console_trial_end_keeps_the_read_only_path_even_with_a_fallback_plan(): void
    {
        app(PlatformBranding::class)->update(['trial_fallback_plan_id' => (string) $this->free->id]);
        $trialEnd = CarbonImmutable::now()->addDays(2);
        $console = $this->tenantOn($this->basic, [
            'status' => 'trial', 'trial_ends_at' => $trialEnd, 'subscription_ends_at' => null, 'status_changed_at' => now(), 'signup_source' => 'console',
        ]);

        $this->travelTo($trialEnd->addDays(10));
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $fresh = $console->fresh();
        $this->assertSame(TenantStatus::ReadOnly->value, $fresh?->status);
        $this->assertSame((int) $this->basic->id, (int) $fresh?->plan_id);
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMovedToFreePlan->value)->count());
    }

    public function test_without_the_setting_a_self_signup_trial_falls_back_to_the_catalog_free_plan(): void
    {
        // PLAN-11: trial_fallback_plan_id is unset by default; the active catalog `free` plan applies.
        $this->assertNull(app(PlatformBranding::class)->get()->trialFallbackPlanId);
        $this->assertSame((int) $this->free->id, app(TrialFallbackPlan::class)->planId());

        $trialEnd = CarbonImmutable::now()->addDays(2);
        $tenant = $this->selfSignupTrial($trialEnd);

        $this->travelTo($trialEnd->addDays(10));
        $this->postJson('/api/v1/customers', ['name' => 'قبل الكنس'], $this->tenantHeaders($tenant))->assertCreated();
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertSame((int) $this->free->id, (int) $fresh?->plan_id);
        $event = TenantLifecycleEvent::query()->where('tenant_id', $tenant->id)->sole();
        $this->assertSame('trial_fallback', $event->properties['source'] ?? null);

        // The console sees what applies even though nothing is stored.
        $this->getJson(self::SETTINGS, $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.trial_fallback_plan_id', null)
            ->assertJsonPath('data.effective_trial_fallback_plan_id', (int) $this->free->id);
    }

    public function test_the_setting_overrides_the_catalog_default_fallback(): void
    {
        $custom = Plan::query()->create([
            'name' => 'Free Custom', 'slug' => 'free-custom', 'price_monthly' => '0.000', 'price_yearly' => '0.000',
            'trial_days' => 0, 'is_free' => true, 'is_active' => true, 'is_public' => false, 'is_popular' => false,
            'features' => $this->free->features, 'max_users' => 1, 'max_stores' => 1, 'max_items' => 10,
        ]);
        app(PlatformBranding::class)->update(['trial_fallback_plan_id' => (string) $custom->id]);
        $this->assertSame((int) $custom->id, app(TrialFallbackPlan::class)->planId());

        $trialEnd = CarbonImmutable::now()->addDays(2);
        $tenant = $this->selfSignupTrial($trialEnd);

        $this->travelTo($trialEnd->addDays(10));
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();

        $fresh = $tenant->fresh();
        $this->assertSame(TenantStatus::Active->value, $fresh?->status);
        $this->assertSame((int) $custom->id, (int) $fresh?->plan_id);
    }

    public function test_without_a_valid_fallback_plan_a_self_signup_trial_goes_read_only(): void
    {
        // No setting and no active catalog free plan: no fallback.
        $this->free->forceFill(['is_active' => false])->save();
        $this->assertNull(app(TrialFallbackPlan::class)->planId());
        $trialEnd = CarbonImmutable::now()->addDays(2);
        $noSetting = $this->selfSignupTrial($trialEnd);

        $this->travelTo($trialEnd->addDays(10));
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $this->assertSame(TenantStatus::ReadOnly->value, $noSetting->fresh()?->status);

        // A fallback setting that stopped being an active free plan is never applied.
        Carbon::setTestNow(Carbon::now());
        app(PlatformBranding::class)->update(['trial_fallback_plan_id' => (string) $this->free->id]);
        $stale = $this->selfSignupTrial(CarbonImmutable::now()->addDays(2));

        $this->travel(12)->days();
        $this->artisan('tenants:apply-lifecycle')->assertSuccessful();
        $fresh = $stale->fresh();
        $this->assertSame(TenantStatus::ReadOnly->value, $fresh?->status);
        $this->assertSame((int) $this->basic->id, (int) $fresh?->plan_id);
    }

    // ------------------------------------------------------------------ billing

    public function test_a_free_plan_is_never_invoiced(): void
    {
        $tenant = $this->tenantOn($this->free, ['status' => 'active', 'subscription_ends_at' => null]);
        // Even a free tenant left in trial by an old row is skipped by the trial-invoice run.
        $legacy = $this->tenantOn($this->free, ['status' => 'trial', 'trial_ends_at' => now()->addDay(), 'subscription_ends_at' => null]);

        $this->artisan('billing:issue-trial-invoices')->assertSuccessful();

        $operator = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices', [
            'type' => 'plan', 'plan_id' => $this->free->id, 'billing_cycle' => 'monthly',
        ], $operator)
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.free_plan_not_billable')
            ->assertJsonPath('message', __('billing.errors.free_plan_not_billable'));

        $this->postJson('/api/v1/billing/renewal-invoice', ['cycle' => 'monthly'], $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'billing.free_plan_not_billable');

        $this->assertSame(0, BillingInvoice::query()->whereIn('tenant_id', [$tenant->id, $legacy->id])->count());

        // An upgrade to a paid plan is still invoiced normally.
        $this->postJson('/api/v1/super-admin/tenants/'.$tenant->getKey().'/billing-invoices', [
            'type' => 'plan', 'plan_id' => $this->basic->id, 'billing_cycle' => 'monthly',
        ], $operator)->assertCreated();
    }

    public function test_free_plan_name_is_free_never_trial_across_console_tenant_and_public_endpoints(): void
    {
        $tenant = $this->tenantOn($this->free, ['status' => 'active', 'subscription_ends_at' => null]);
        $headers = $this->centralHeaders($this->centralSuperAdmin());

        // Simulate a legacy production DB row where plans.name was still 'التجربة' and name_key was null.
        DB::table('plans')->where('id', $this->free->id)->update([
            'name' => 'التجربة',
            'name_key' => null,
        ]);

        // Console plans (Arabic + English)
        $arPlan = collect($this->getJson(self::PLANS, $headers)->assertOk()->json('data.plans'))
            ->firstWhere('slug', 'free');
        $this->assertSame('المجانية', $arPlan['name'] ?? null);

        $enPlan = collect($this->getJson(self::PLANS, $headers + ['X-Locale' => 'en'])->assertOk()->json('data.plans'))
            ->firstWhere('slug', 'free');
        $this->assertSame('Free', $enPlan['name'] ?? null);

        // Console tenants list & show
        $tenantsPayload = $this->getJson('/api/v1/super-admin/tenants', $headers)->assertOk()->json();
        $listedPlan = collect($tenantsPayload['plans'] ?? [])->firstWhere('slug', 'free');
        $this->assertSame('المجانية', $listedPlan['name'] ?? null);

        $listedTenant = collect($tenantsPayload['tenants']['data'] ?? [])->firstWhere('id', $tenant->id);
        $this->assertSame('المجانية', $listedTenant['plan_name'] ?? null);
        $this->assertSame('المجانية', $listedTenant['plan']['name'] ?? null);

        $this->getJson('/api/v1/super-admin/tenants/'.$tenant->id, $headers)
            ->assertOk()
            ->assertJsonPath('data.tenant.plan.name', 'المجانية')
            ->assertJsonPath('data.tenant.plan_name', 'المجانية');

        // Console dashboard metrics + plan_stats + recent_tenants
        $dashboard = $this->getJson('/api/v1/super-admin/dashboard', $headers)->assertOk()->json();
        $this->assertSame(1, $dashboard['metrics']['free_plan_tenants'] ?? null);
        $this->assertSame('المجانية', collect($dashboard['plan_stats'] ?? [])->firstWhere('slug', 'free')['name'] ?? null);
        $this->assertSame('المجانية', collect($dashboard['recent_tenants'] ?? [])->firstWhere('id', $tenant->id)['plan_name'] ?? null);

        // Tenant billing subscription context
        $sub = $this->getJson('/api/v1/billing/subscription', $this->tenantHeaders($tenant))
            ->assertOk()
            ->json('data');
        $this->assertSame('المجانية', $sub['plan']['name'] ?? null);
        $this->assertSame('المجانية', collect($sub['available_plans'] ?? [])->firstWhere('code', 'free')['name'] ?? null);

        // Data migration repairs the legacy DB row idempotently.
        $migration = require database_path('migrations/2026_11_27_000100_rename_free_plan_from_trial.php');
        $migration->up();
        $migration->up();

        $fresh = $this->free->fresh();
        $this->assertSame('المجانية', $fresh?->name);
        $this->assertSame('plans.plans.free.name', $fresh?->name_key);
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
