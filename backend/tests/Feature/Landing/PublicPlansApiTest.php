<?php

declare(strict_types=1);

namespace Tests\Feature\Landing;

use App\Models\Addon;
use App\Models\BillingSequence;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Services\Billing\FounderPricingService;
use App\Support\PublicCatalogCache;
use Database\Seeders\PlansAndFeaturesSeeder;
use Tests\TenantTestCase;

/**
 * PRD-07: GET /api/v1/public/plans reads the live central catalog. No authentication, no
 * tenancy, no hardcoded price: every price below is compared with the database row.
 */
final class PublicPlansApiTest extends TenantTestCase
{
    private const URL = '/api/v1/public/plans';

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
        $this->seed(PlansAndFeaturesSeeder::class);
        PublicCatalogCache::forget();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        $plans = $this->getJson(self::URL)->assertOk()->json('data.plans');
        $this->assertIsArray($plans);

        return $plans;
    }

    public function test_public_plans_are_served_without_authentication(): void
    {
        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.currency', 'EGP')
            ->assertJsonPath('data.prices_exclude_vat', true)
            ->assertJsonStructure(['data' => [
                'currency', 'prices_exclude_vat',
                'founder' => ['available', 'slots_left', 'months'],
                'brand' => ['name', 'subtitle', 'support_phone', 'support_email', 'website_url'],
                'plans' => [['code', 'name', 'description', 'is_popular', 'sort_order', 'trial_days', 'price_monthly', 'price_yearly',
                    'founder_price_monthly', 'founder_price_yearly',
                    'limits' => ['users', 'stores', 'warehouses', 'vans', 'items', 'invoices_per_month', 'storage_mb'],
                    'features' => [['key', 'name', 'description', 'module']]]],
                'addons',
            ]]);
    }

    public function test_prices_are_strings_taken_from_the_database(): void
    {
        $plans = collect($this->plans())->keyBy('code');
        $this->assertNotEmpty($plans);

        foreach (Plan::query()->where('is_active', true)->where('is_public', true)->get() as $plan) {
            $row = $plans[$plan->slug];
            $this->assertSame($plan->price_monthly, $row['price_monthly']);
            $this->assertSame($plan->price_yearly, $row['price_yearly']);
            $this->assertIsString($row['price_monthly']);
            $this->assertSame($plan->max_users, $row['limits']['users']);
            $this->assertSame($plan->max_invoices_per_month, $row['limits']['invoices_per_month']);
            $this->assertSame($plan->trial_days, $row['trial_days']);
        }

        // An edited price shows up (cache is invalidated by the model event).
        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $this->getJson(self::URL)->assertOk();
        $basic->forceFill(['price_monthly' => '123.500'])->save();

        $this->assertSame('123.500', collect($this->plans())->firstWhere('code', 'basic')['price_monthly']);
    }

    public function test_null_limit_means_unlimited(): void
    {
        $enterprise = collect($this->plans())->firstWhere('code', 'enterprise');

        $this->assertNull($enterprise['limits']['items']);
        $this->assertNull($enterprise['limits']['invoices_per_month']);
    }

    public function test_hidden_features_never_appear(): void
    {
        $hidden = PlanFeature::query()->where('is_public', false)->pluck('key')->all();
        $this->assertContains('quotations.manage', $hidden);

        foreach ($this->plans() as $plan) {
            $keys = array_column($plan['features'], 'key');
            $this->assertNotEmpty($keys);
            $this->assertSame([], array_values(array_intersect($keys, $hidden)));
            $this->assertNotContains('quotations.manage', $keys);
        }

        $this->assertStringNotContainsString('quotations', (string) $this->getJson(self::URL)->getContent());
    }

    public function test_only_features_enabled_on_the_plan_are_listed(): void
    {
        $basic = collect($this->plans())->firstWhere('code', 'basic');
        $keys = array_column($basic['features'], 'key');

        $this->assertContains('pos.access', $keys);
        $this->assertNotContains('api.access', $keys);
    }

    public function test_founder_prices_show_while_slots_are_left(): void
    {
        $this->getJson(self::URL)
            ->assertJsonPath('data.founder.available', true)
            ->assertJsonPath('data.founder.slots_left', app(FounderPricingService::class)->totalSlots());

        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $row = collect($this->plans())->firstWhere('code', 'basic');

        $this->assertSame($basic->founder_price_monthly, $row['founder_price_monthly']);
        $this->assertSame($basic->founder_price_yearly, $row['founder_price_yearly']);
        // The trial plan has no founder price.
        $this->assertNull(collect($this->plans())->firstWhere('code', 'free')['founder_price_monthly']);
    }

    public function test_founder_prices_disappear_when_all_slots_are_taken(): void
    {
        $total = app(FounderPricingService::class)->totalSlots();
        BillingSequence::query()->updateOrCreate(
            ['key' => FounderPricingService::SLOT_SEQUENCE, 'period' => ''],
            ['last_value' => $total],
        );
        PublicCatalogCache::forget();

        $this->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.founder.available', false)
            ->assertJsonPath('data.founder.slots_left', 0);

        foreach ($this->plans() as $plan) {
            $this->assertNull($plan['founder_price_monthly']);
            $this->assertNull($plan['founder_price_yearly']);
        }
    }

    public function test_inactive_and_non_public_plans_are_excluded(): void
    {
        Plan::query()->where('slug', 'pro')->update(['is_public' => false]);
        Plan::query()->where('slug', 'enterprise')->update(['is_active' => false]);
        PublicCatalogCache::forget();

        $codes = array_column($this->plans(), 'code');

        $this->assertNotContains('pro', $codes);
        $this->assertNotContains('enterprise', $codes);
        $this->assertContains('basic', $codes);
    }

    public function test_hidden_addons_are_excluded_and_public_ones_carry_prices(): void
    {
        $addons = collect($this->getJson(self::URL)->assertOk()->json('data.addons'))->keyBy('key');

        $hidden = Addon::query()->where('is_public', false)->pluck('key');
        $this->assertNotEmpty($hidden);
        foreach ($hidden as $key) {
            $this->assertFalse($addons->has($key), "{$key} must stay hidden");
        }

        $store = Addon::query()->where('key', 'addon.store')->firstOrFail();
        $this->assertSame($store->unit_price, $addons['addon.store']['unit_price']);
        $this->assertSame($store->yearly_price, $addons['addon.store']['yearly_price']);
        $this->assertNotEmpty($addons['addon.store']['price_tiers']);
        $this->assertContains('basic', array_column($addons['addon.store']['plans'], 'code'));
    }

    public function test_names_follow_the_request_locale(): void
    {
        $en = $this->getJson(self::URL, ['X-Locale' => 'en'])->assertOk()->json('data.plans');
        $ar = $this->getJson(self::URL, ['X-Locale' => 'ar'])->assertOk()->json('data.plans');

        $this->assertSame('Starter', collect($en)->firstWhere('code', 'basic')['name']);
        $this->assertNotSame('Starter', collect($ar)->firstWhere('code', 'basic')['name']);
    }

    public function test_it_ignores_tenant_selection_and_works_on_any_host(): void
    {
        $this->getJson(self::URL, ['X-Tenant' => 'does-not-exist'])->assertOk();
        $this->getJson('http://some-shop.example.test'.self::URL)->assertOk();
    }

    public function test_it_is_throttled(): void
    {
        $limit = (int) config('rate_limits.public_api.per_minute', 60);

        for ($i = 0; $i < $limit; $i++) {
            $this->getJson(self::URL)->assertOk();
        }

        $this->getJson(self::URL)->assertStatus(429);
    }

    public function test_brochure_is_a_permanent_redirect_to_pricing(): void
    {
        $this->get('/brochure')->assertStatus(301)->assertRedirect('/pricing');
    }
}
