<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\CentralAuditEvent;
use App\Models\Addon;
use App\Models\CentralAuditLog;
use App\Models\Plan;
use App\Models\PlanAddon;
use Database\Seeders\PlansAndFeaturesSeeder;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * PLAN-EDIT: POST /api/v1/super-admin/plans creates a custom plan with every setting, and PUT
 * writes add-on availability / included services. super_admin.plans.manage + step-up.
 */
final class SuperAdminPlanCreateApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const URL = '/api/v1/super-admin/plans';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Coffee Chain',
            'description' => 'For chains',
            'price_monthly' => 1999.5,
            'price_yearly' => '19995',
            'founder_price_monthly' => '1500',
            'founder_price_yearly' => '15000',
            'founder_transition_price_monthly' => '1700',
            'founder_transition_price_yearly' => '17000',
            'compare_at_price_monthly' => '2500',
            'compare_at_price_yearly' => null,
            'promo_starts_at' => null,
            'promo_ends_at' => now()->addDays(10)->toIso8601String(),
            'max_users' => 40,
            'max_stores' => 15,
            'max_warehouses' => 6,
            'max_vans' => 3,
            'max_items' => null,
            'trial_days' => 0,
            'is_active' => true,
            'is_public' => false,
            'is_popular' => false,
            'features' => ['pos.access' => true, 'invoices.create' => true, 'shifts.manage' => true, 'mixes.manage' => true, 'quotations.manage' => true],
        ], $overrides);
    }

    public function test_a_super_admin_creates_a_fully_configured_plan(): void
    {
        $support = Addon::query()->where('key', 'addon.premium_support')->firstOrFail();
        $store = Addon::query()->where('key', 'addon.store')->firstOrFail();

        $response = $this->postJson(self::URL, $this->payload(['addons' => [
            ['addon_id' => $support->id, 'is_available' => false, 'included_quantity' => 1, 'included_billing_cycle' => null],
            ['addon_id' => $store->id, 'is_available' => true],
        ]]), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.slug', 'coffee-chain')
            ->assertJsonPath('data.price_monthly', '1999.500')
            ->assertJsonPath('data.founder_transition_price_monthly', '1700.000')
            ->assertJsonPath('data.promotion.compare_at_monthly', '2500.000')
            ->assertJsonPath('data.included_addons.0.key', 'addon.premium_support')
            ->assertJsonPath('ignored_features', ['quotations.manage']);

        $plan = Plan::query()->where('slug', 'coffee-chain')->sole();
        $this->assertSame((int) $plan->id, $response->json('data.id'));
        $this->assertSame(40, $plan->max_users);
        $this->assertNull($plan->max_items);
        $this->assertFalse($plan->is_public);
        $this->assertTrue($plan->features['mixes.manage']);
        $this->assertFalse($plan->features['quotations.manage'], 'A hidden feature is never switched on from the console.');
        $this->assertFalse($plan->features['reports.advanced'], 'Unsent registry keys are off on a new plan.');
        $this->assertGreaterThan((int) Plan::query()->where('slug', 'enterprise')->value('sort_order'), $plan->sort_order);

        $line = PlanAddon::query()->where('plan_id', $plan->id)->where('addon_id', $support->id)->sole();
        $this->assertFalse($line->is_available);
        $this->assertSame(1, $line->included_quantity);

        $this->assertTrue(CentralAuditLog::query()->where('event', CentralAuditEvent::PlanCreated->value)->exists());
    }

    public function test_a_plan_is_created_with_the_invoice_and_storage_limits(): void
    {
        $this->postJson(self::URL, $this->payload(['max_invoices_per_month' => 1500, 'max_storage_mb' => 4096]), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertCreated()
            ->assertJsonPath('data.max_invoices_per_month', 1500)
            ->assertJsonPath('data.max_storage_mb', 4096);

        $plan = Plan::query()->where('slug', 'coffee-chain')->sole();
        $this->assertSame(1500, $plan->max_invoices_per_month);
        $this->assertSame(4096, $plan->max_storage_mb);
    }

    public function test_a_plan_created_without_the_invoice_and_storage_limits_is_unlimited_on_them(): void
    {
        // PLAN-ENF: never the legacy column defaults (100 invoices / 500 MB).
        $this->postJson(self::URL, $this->payload(), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertCreated()
            ->assertJsonPath('data.max_invoices_per_month', null)
            ->assertJsonPath('data.max_storage_mb', null);

        $plan = Plan::query()->where('slug', 'coffee-chain')->sole();
        $this->assertNull($plan->max_invoices_per_month);
        $this->assertNull($plan->max_storage_mb);
    }

    public function test_the_invoice_and_storage_limits_must_be_positive_integers(): void
    {
        $this->postJson(self::URL, $this->payload(['max_invoices_per_month' => 0, 'max_storage_mb' => -5]), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['max_invoices_per_month', 'max_storage_mb']);
    }

    public function test_a_plan_created_without_is_public_is_hidden(): void
    {
        $payload = $this->payload();
        unset($payload['is_public']);

        $this->postJson(self::URL, $payload, $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertCreated()
            ->assertJsonPath('data.is_public', false);
        $this->assertFalse(Plan::query()->where('slug', 'coffee-chain')->sole()->is_public);
    }

    public function test_promo_dates_accept_an_offset_and_come_back_in_the_app_timezone(): void
    {
        $ends = now()->addMonth()->startOfDay()->utc();

        $response = $this->postJson(self::URL, $this->payload(['promo_ends_at' => $ends->format('Y-m-d\TH:i:s\Z')]), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertCreated();

        $expected = $ends->copy()->setTimezone((string) config('app.timezone'))->toIso8601String();
        $this->assertSame($expected, $response->json('data.promo_ends_at'));
        $this->assertTrue(Plan::query()->where('slug', 'coffee-chain')->sole()->promo_ends_at?->equalTo($ends));
    }

    public function test_slugs_never_take_a_catalog_slug_and_stay_unique(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->postJson(self::URL, $this->payload(['name' => 'Pro']), $headers)->assertCreated()->assertJsonPath('data.slug', 'custom-pro');
        $this->postJson(self::URL, $this->payload(['name' => 'Pro']), $headers)->assertCreated()->assertJsonPath('data.slug', 'custom-pro-2');
        $this->postJson(self::URL, $this->payload(['name' => 'باقة خاصة']), $headers)->assertCreated()->assertJsonPath('data.name', 'باقة خاصة');
    }

    public function test_invalid_input_is_422_and_creates_nothing(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $count = Plan::query()->count();
        $store = Addon::query()->where('key', 'addon.store')->firstOrFail();

        $this->postJson(self::URL, $this->payload([
            'name' => '',
            'price_monthly' => '-1',
            'features' => ['no.such.feature' => true],
        ]), $headers)->assertStatus(422)->assertJsonValidationErrors(['name', 'price_monthly', 'features']);

        // Cross-field pricing rules.
        $this->postJson(self::URL, $this->payload([
            'compare_at_price_monthly' => '1000',
            'founder_transition_price_monthly' => '1400',
        ]), $headers)->assertStatus(422)->assertJsonValidationErrors(['compare_at_price_monthly', 'founder_transition_price_monthly']);

        $this->postJson(self::URL, $this->payload(['promo_ends_at' => null]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['promo_ends_at']);
        $this->postJson(self::URL, $this->payload(['promo_ends_at' => now()->subDay()->toIso8601String()]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['promo_ends_at']);

        // A founder price cannot be above the list price.
        $this->postJson(self::URL, $this->payload(['founder_price_yearly' => '20000']), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['founder_price_yearly']);

        // A retired add-on can be marked unavailable, never offered or included.
        $retired = Addon::query()->where('key', 'addon.items_5k')->firstOrFail();
        $this->assertFalse($retired->is_active);
        $this->postJson(self::URL, $this->payload(['addons' => [['addon_id' => $retired->id, 'is_available' => true]]]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['addons.0.addon_id']);

        // A store add-on raises limits: it can be offered, never "included".
        $this->postJson(self::URL, $this->payload(['addons' => [
            ['addon_id' => $store->id, 'is_available' => true, 'included_quantity' => 1],
        ]]), $headers)->assertStatus(422)->assertJsonValidationErrors(['addons.0.included_quantity']);

        $this->assertSame($count, Plan::query()->count());
    }

    public function test_update_writes_addon_availability(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $plan = Plan::query()->where('slug', 'basic')->firstOrFail();
        $mixes = Addon::query()->where('key', 'mixes.manage')->firstOrFail();

        $this->putJson(self::URL.'/'.$plan->id, $this->payload([
            'name' => $plan->name,
            'compare_at_price_monthly' => null,
            'promo_ends_at' => null,
            'addons' => [['addon_id' => $mixes->id, 'is_available' => false]],
        ]), $headers)->assertOk()->assertJsonPath('data.slug', 'basic');

        $this->assertFalse(PlanAddon::query()->where('plan_id', $plan->id)->where('addon_id', $mixes->id)->sole()->is_available);
    }

    public function test_the_editor_payload_lists_the_addon_catalog_and_the_feature_registry(): void
    {
        $data = $this->getJson(self::URL, $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertOk()->json('data');

        $this->assertSame('0.000', $data['tax_rate']);
        $support = collect($data['addons_catalog'])->firstWhere('key', 'addon.premium_support');
        $this->assertTrue($support['includable']);
        $this->assertFalse(collect($data['addons_catalog'])->firstWhere('key', 'addon.store')['includable']);
        $this->assertFalse(collect($data['features_registry'])->firstWhere('key', 'quotations.manage')['is_public']);

        $enterprise = collect($data['plans'])->firstWhere('slug', 'enterprise');
        $this->assertSame(['addon.premium_support', 'service.onboarding'], array_column($enterprise['included_addons'], 'key'));
        $this->assertSame('yearly', collect($enterprise['included_addons'])->firstWhere('key', 'service.onboarding')['billing_cycle']);
        $this->assertFalse(collect($enterprise['addons'])->firstWhere('key', 'addon.premium_support')['is_available']);
    }

    public function test_guest_is_401_support_is_403_and_nothing_is_created(): void
    {
        $count = Plan::query()->count();

        $this->postJson(self::URL, $this->payload())->assertStatus(401);
        $this->postJson(self::URL, $this->payload(), $this->centralHeaders($this->centralSupport()))->assertStatus(403);

        $tenant = $this->createTenant();
        $this->postJson(self::URL, $this->payload(), ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)])
            ->assertStatus(401);

        $this->assertSame($count, Plan::query()->count());
    }
}
