<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\AddonType;
use App\Enums\Billing\BillingCycle;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Services\Billing\FounderPricingService;
use Database\Seeders\Catalog\FeatureCatalog;
use Database\Seeders\Catalog\PlanCatalog;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.8: the catalog seeder writes the approved plans, features and add-ons, is
 * idempotent, and never overwrites what a super-admin edited.
 *
 * Approved values: pricing-model-recommendation-2026-10 §4/§5/§7, Q-E2/Q-E3/Q-E8/Q-E9 and
 * CTO W1 Q3/Q4 [2026-10-09] (yearly = 10 months, founder 299/599/999 and 2,990/5,990/9,990,
 * premium_support recurring, quotations.manage core = 18 core features). VAT excluded.
 */
#[Group('billing')]
#[Group('mysql')]
final class PlansAndFeaturesSeederTest extends TenantTestCase
{
    /** slug => [monthly, yearly, founder monthly, founder yearly] */
    private const PRICES = [
        'free' => ['0.000', '0.000', null, null],
        'basic' => ['449.000', '4490.000', '299.000', '2990.000'],
        'pro' => ['899.000', '8990.000', '599.000', '5990.000'],
        'enterprise' => ['1799.000', '17990.000', '999.000', '9990.000'],
    ];

    /** slug => [stores, warehouses, vans, users, items, invoices/month, storage MB, trial days] */
    private const LIMITS = [
        'free' => [1, 0, 0, 3, 200, 300, 500, 14],
        'basic' => [1, 1, 0, 3, 3000, 6000, 2048, 0],
        'pro' => [3, 2, 1, 8, 20000, 30000, 10240, 0],
        'enterprise' => [10, 5, 5, 25, null, null, 51200, 0],
    ];

    private const CORE = [
        'pos.access', 'invoices.create', 'invoices.edit', 'whatsapp.share', 'quotations.manage',
        'items.manage', 'items.movements', 'purchases.manage', 'expenses.manage', 'payments.manage',
        'shifts.manage', 'treasury.view', 'returns.manage', 'reports.basic', 'reports.export',
        'printing.thermal', 'printing.a4', 'telegram.notifications',
    ];

    /** Non-core features switched on per plan (§5); everything else is off. */
    private const EXTRAS = [
        'free' => ['transfers.manage', 'mixes.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs'],
        'basic' => [],
        'pro' => ['transfers.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs'],
        'enterprise' => ['transfers.manage', 'mixes.manage', 'purchases.reorder', 'reports.advanced', 'audit.logs', 'api.access'],
    ];

    /** key => [type, monthly/one-time, yearly, tiers [min_qty, unit, yearly], public] */
    private const ADDONS = [
        'addon.store' => ['recurring', '249.000', '2490.000', [[3, '212.000', '2120.000'], [6, '187.000', '1870.000']], true],
        'addon.warehouse' => ['recurring', '149.000', '1490.000', [], true],
        'addon.van' => ['recurring', '249.000', '2490.000', [[3, '212.000', '2120.000'], [6, '187.000', '1870.000']], true],
        'addon.user' => ['recurring', '79.000', '790.000', [[6, '59.000', '590.000']], true],
        'addon.storage_10gb' => ['recurring', '49.000', '490.000', [], true],
        'addon.items_5k' => ['recurring', '99.000', '990.000', [], true],
        'mixes.manage' => ['recurring', '199.000', '1990.000', [], true],
        'reports.advanced' => ['recurring', '149.000', '1490.000', [], true],
        'audit.logs' => ['recurring', '99.000', '990.000', [], true],
        'api.access' => ['recurring', '299.000', '2990.000', [], true],
        'custom.domain' => ['recurring', '149.000', '1490.000', [], false],
        'addon.premium_support' => ['recurring', '299.000', '2990.000', [], true],
        'service.onboarding' => ['service', '1500.000', null, [], true],
    ];

    public function test_it_seeds_the_approved_plans_prices_and_limits(): void
    {
        $this->seedCatalog();

        $this->assertSame(PlanCatalog::SLUGS, Plan::query()->orderBy('sort_order')->pluck('slug')->all());

        foreach (self::PRICES as $slug => [$monthly, $yearly, $founderMonthly, $founderYearly]) {
            $plan = $this->plan($slug);
            $this->assertSame($monthly, $plan->price_monthly, "{$slug} monthly");
            $this->assertSame($yearly, $plan->price_yearly, "{$slug} yearly");
            $this->assertSame($founderMonthly, $plan->founder_price_monthly, "{$slug} founder monthly");
            $this->assertSame($founderYearly, $plan->founder_price_yearly, "{$slug} founder yearly");

            // Yearly = exactly 10 months, founder yearly = exactly 10 founder months (bcmath).
            $this->assertSame(0, bccomp(bcmul($monthly, '10', 3), $yearly, 3), "{$slug}: yearly must be 10 months.");
            if ($founderMonthly !== null) {
                $this->assertSame(0, bccomp(bcmul($founderMonthly, '10', 3), (string) $founderYearly, 3), "{$slug}: founder yearly must be 10 months.");
            }
        }

        foreach (self::LIMITS as $slug => [$stores, $warehouses, $vans, $users, $items, $invoices, $storage, $trialDays]) {
            $plan = $this->plan($slug);
            $this->assertSame(
                [$stores, $warehouses, $vans, $users, $items, $invoices, $storage, $trialDays],
                [$plan->max_stores, $plan->max_warehouses, $plan->max_vans, $plan->max_users, $plan->max_items, $plan->max_invoices_per_month, $plan->max_storage_mb, $plan->trial_days],
                "{$slug} limits",
            );
        }

        $this->assertTrue($this->plan('pro')->is_popular);
        $this->assertSame('plans.plans.basic.name', $this->plan('basic')->name_key);
    }

    public function test_no_limit_is_an_all_nines_or_negative_sentinel(): void
    {
        $this->seedCatalog();

        foreach (Plan::query()->get() as $plan) {
            foreach (Plan::LIMIT_COLUMNS as $column) {
                $value = $plan->{$column};
                if ($value === null) {
                    continue;
                }

                $this->assertGreaterThanOrEqual(0, $value, "{$plan->slug}.{$column} is negative.");
                $this->assertDoesNotMatchRegularExpression('/^9{2,}$/', (string) $value, "{$plan->slug}.{$column} is a 999 sentinel; NULL means unlimited.");
            }
        }
    }

    public function test_it_seeds_the_feature_registry_with_18_core_and_two_hidden_keys(): void
    {
        $this->seedCatalog();

        $features = DB::connection($this->centralConnectionName())->table('plan_features')->orderBy('sort_order')->get()->keyBy('key');

        $this->assertCount(26, $features);
        $this->assertSame(FeatureCatalog::keys(), $features->keys()->all());
        $this->assertArrayNotHasKey('blender.access', $features->all());

        $core = $features->filter(fn (object $feature): bool => (bool) $feature->is_core)->keys()->sort()->values()->all();
        $expectedCore = self::CORE;
        sort($expectedCore);
        $this->assertSame($expectedCore, $core);
        $this->assertCount(18, $core);

        $hidden = $features->reject(fn (object $feature): bool => (bool) $feature->is_public)->keys()->sort()->values()->all();
        $this->assertSame(['custom.domain', 'pos.offline'], $hidden);

        foreach ($features as $key => $feature) {
            $this->assertSame(FeatureCatalog::nameKey($key), $feature->name_key);
            $this->assertSame(__($feature->name_key), $feature->name, "{$key}: name comes from lang/plans.php.");
            $this->assertNotSame($feature->name_key, $feature->name, "{$key}: translation is missing.");
            $this->assertNull($feature->icon, 'No emoji icons.');
            $this->assertSame($feature->is_core ? 'true' : 'false', $feature->default_value);
        }

        $this->assertSame('inventory', $features['mixes.manage']->module);
    }

    public function test_plan_feature_matrix_matches_the_approved_distribution(): void
    {
        $this->seedCatalog();

        foreach (self::EXTRAS as $slug => $extras) {
            $features = $this->plan($slug)->features ?? [];
            // MySQL's JSON type re-orders object keys: compare the key sets, not their order.
            $this->assertEqualsCanonicalizing(FeatureCatalog::keys(), array_keys($features), "{$slug} must list every catalog key.");

            foreach ($features as $key => $enabled) {
                $expected = in_array($key, self::CORE, true) || in_array($key, $extras, true);
                $this->assertSame($expected, $enabled, "{$slug}.{$key}");
            }
        }

        foreach (PlanCatalog::SLUGS as $slug) {
            $this->assertFalse($this->plan($slug)->features['pos.offline'], 'pos.offline has no route until the end of Phase 2.');
            $this->assertFalse($this->plan($slug)->features['custom.domain'], 'custom.domain is hidden until Phase 3 (Q-E9).');
        }
    }

    public function test_it_seeds_the_addon_catalog_with_explicit_prices(): void
    {
        $this->seedCatalog();

        $this->assertSame(array_keys(self::ADDONS), Addon::query()->orderBy('sort_order')->pluck('key')->all());

        foreach (self::ADDONS as $key => [$type, $unit, $yearly, $tiers, $public]) {
            $addon = Addon::query()->where('key', $key)->firstOrFail();
            $this->assertSame($type, $addon->type->value, "{$key} type");
            $this->assertSame($unit, $addon->unit_price, "{$key} price");
            $this->assertSame($yearly, $addon->yearly_price, "{$key} yearly price");
            $this->assertSame($public, $addon->is_public, "{$key} visibility");
            $this->assertTrue($addon->is_active);
            $this->assertNotSame($addon->name_key, __($addon->name_key), "{$key}: translation is missing.");

            // assertEquals: same tiers in the same order, inner JSON keys in any order (MySQL JSON).
            $this->assertEquals(
                array_map(static fn (array $tier): array => ['min_qty' => $tier[0], 'unit_price' => $tier[1], 'yearly_price' => $tier[2]], $tiers),
                $addon->price_tiers ?? [],
                "{$key} tiers are explicit stored prices",
            );
            foreach ($addon->price_tiers ?? [] as $tier) {
                $this->assertIsInt($tier['min_qty']);
                $this->assertIsString($tier['unit_price'], 'Tier prices are stored as decimal strings, never floats.');
                $this->assertIsString($tier['yearly_price']);
            }

            if ($yearly !== null) {
                $this->assertSame(0, bccomp(bcmul($unit, '10', 3), $yearly, 3), "{$key}: yearly must be 10 months.");
            }
        }

        $this->assertSame('mixes.manage', Addon::query()->where('key', 'mixes.manage')->value('feature_key'));
        $this->assertEquals(['stores' => 1, 'users' => 1], Addon::query()->where('key', 'addon.store')->firstOrFail()->bundled_limits);
        $this->assertSame(AddonType::Recurring, Addon::query()->where('key', 'addon.premium_support')->firstOrFail()->type, 'premium_support is a monthly recurring add-on (CTO W1 Q4).');
        $this->assertSame(AddonType::Service, Addon::query()->where('key', 'service.onboarding')->firstOrFail()->type);
    }

    public function test_seeded_addons_price_through_the_billing_engine(): void
    {
        $this->seedCatalog();
        $addon = fn (string $key): Addon => Addon::query()->where('key', $key)->firstOrFail();

        $this->assertSame('249.000', $addon('addon.store')->unitPriceFor(BillingCycle::Monthly, 2));
        $this->assertSame('212.000', $addon('addon.store')->unitPriceFor(BillingCycle::Monthly, 3));
        $this->assertSame('2120.000', $addon('addon.store')->unitPriceFor(BillingCycle::Yearly, 5));
        $this->assertSame('1870.000', $addon('addon.van')->unitPriceFor(BillingCycle::Yearly, 6));
        $this->assertSame('79.000', $addon('addon.user')->unitPriceFor(BillingCycle::Monthly, 5));
        $this->assertSame('59.000', $addon('addon.user')->unitPriceFor(BillingCycle::Monthly, 6));
        $this->assertSame('2990.000', $addon('addon.premium_support')->unitPriceFor(BillingCycle::Yearly));
        $this->assertSame('1500.000', $addon('service.onboarding')->unitPriceFor(BillingCycle::Yearly));

        $founder = app(FounderPricingService::class);
        $this->assertTrue($founder->isEligiblePlan($this->plan('basic'), BillingCycle::Yearly), 'The founder yearly price is set (CTO W1 Q4).');
        $this->assertFalse($founder->isEligiblePlan($this->plan('free'), BillingCycle::Monthly));
    }

    public function test_addon_availability_per_plan(): void
    {
        $this->seedCatalog();

        $available = fn (string $slug): array => PlanAddon::query()
            ->join('addons', 'addons.id', '=', 'plan_addon.addon_id')
            ->where('plan_addon.plan_id', $this->plan($slug)->id)
            ->where('plan_addon.is_available', true)
            ->pluck('addons.key')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([], $available('free'), 'The trial buys nothing.');
        $this->assertSame([
            'addon.items_5k', 'addon.premium_support', 'addon.storage_10gb', 'addon.store', 'addon.user',
            'addon.van', 'addon.warehouse', 'audit.logs', 'mixes.manage', 'reports.advanced', 'service.onboarding',
        ], $available('basic'));
        $this->assertSame([
            'addon.premium_support', 'addon.storage_10gb', 'addon.store', 'addon.user', 'addon.van',
            'addon.warehouse', 'api.access', 'mixes.manage', 'service.onboarding',
        ], $available('pro'));
        $this->assertSame([
            'addon.storage_10gb', 'addon.store', 'addon.user', 'addon.van', 'addon.warehouse', 'service.onboarding',
        ], $available('enterprise'));

        // custom.domain exists, hidden, and is not sold on any plan until Phase 3.
        $domain = Addon::query()->where('key', 'custom.domain')->firstOrFail();
        $this->assertFalse($domain->is_public);
        $this->assertFalse(PlanAddon::query()->where('addon_id', $domain->id)->where('is_available', true)->exists());
        $this->assertSame(0, (int) PlanAddon::query()->sum('included_quantity'));
        $this->assertFalse(PlanAddon::query()->whereNotNull('unit_price_override')->orWhereNotNull('yearly_price_override')->exists());
    }

    public function test_running_the_seeder_twice_changes_nothing(): void
    {
        $this->seedCatalog();
        $first = $this->snapshot();

        $this->seedCatalog();

        $this->assertSame($first, $this->snapshot());
    }

    public function test_it_never_overwrites_super_admin_edits_and_merges_missing_keys(): void
    {
        $this->seedCatalog();
        $db = DB::connection($this->centralConnectionName());

        $basic = $this->plan('basic');
        $features = $basic->features ?? [];
        unset($features['quotations.manage']);
        $features['reports.advanced'] = true;
        $features['custom.extra'] = true;
        $basic->update([
            'name' => 'Edited name',
            'price_monthly' => '500.000',
            'founder_price_yearly' => null,
            'max_users' => 4,
            'max_items' => null,
            'trial_days' => 7,
            'is_public' => false,
            'features' => $features,
        ]);
        $db->table('plan_features')->where('key', 'pos.access')->update(['name' => 'Edited feature', 'is_public' => false, 'name_key' => null]);
        Addon::query()->where('key', 'addon.store')->update(['unit_price' => '260.000', 'price_tiers' => null, 'is_active' => false]);
        $proStore = PlanAddon::query()
            ->where('plan_id', $this->plan('pro')->id)
            ->where('addon_id', Addon::query()->where('key', 'addon.store')->value('id'));
        $proStore->update(['is_available' => false, 'unit_price_override' => '230.000']);

        $this->seedCatalog();

        $basic = $this->plan('basic');
        $this->assertSame('Edited name', $basic->name);
        $this->assertSame('500.000', $basic->price_monthly);
        $this->assertNull($basic->founder_price_yearly);
        $this->assertSame(4, $basic->max_users);
        $this->assertNull($basic->max_items);
        $this->assertSame(7, $basic->trial_days);
        $this->assertFalse($basic->is_public);
        $this->assertTrue($basic->features['reports.advanced'], 'An edited feature value is kept.');
        $this->assertTrue($basic->features['custom.extra'], 'A key the catalog does not know is kept.');
        $this->assertTrue($basic->features['quotations.manage'], 'A missing catalog key is merged back with its catalog value.');

        $posAccess = $db->table('plan_features')->where('key', 'pos.access')->first();
        $this->assertNotNull($posAccess);
        $this->assertSame('Edited feature', $posAccess->name);
        $this->assertFalse((bool) $posAccess->is_public);
        $this->assertSame('plans.features.pos_access.name', $posAccess->name_key, 'A missing name_key is filled in.');

        $store = Addon::query()->where('key', 'addon.store')->firstOrFail();
        $this->assertSame('260.000', $store->unit_price);
        $this->assertNull($store->price_tiers);
        $this->assertFalse($store->is_active);

        $row = $proStore->first();
        $this->assertNotNull($row);
        $this->assertFalse($row->is_available);
        $this->assertSame('230.000', $row->unit_price_override);
    }

    public function test_it_renames_a_legacy_blender_key_left_in_the_data(): void
    {
        $this->seedCatalog();
        $db = DB::connection($this->centralConnectionName());

        $legacy = Plan::query()->create([
            'name' => 'Legacy custom', 'slug' => 'legacy-custom', 'price_monthly' => '100.000', 'price_yearly' => '1000.000',
            'features' => ['pos.access' => true, 'blender.access' => true], 'is_active' => true, 'sort_order' => 9,
        ]);
        $db->table('plan_features')->insert([
            'key' => 'blender.access', 'name' => 'legacy', 'module' => 'inventory', 'type' => 'boolean',
            'default_value' => 'false', 'sort_order' => 99, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->seedCatalog();

        $features = Plan::query()->findOrFail($legacy->id)->features ?? [];
        $this->assertArrayNotHasKey('blender.access', $features);
        $this->assertTrue($features['mixes.manage']);
        $this->assertSame('100.000', Plan::query()->findOrFail($legacy->id)->price_monthly, 'A custom plan is never repriced.');
        $this->assertFalse($db->table('plan_features')->where('key', 'blender.access')->exists());
        $this->assertSame(1, $db->table('plan_features')->where('key', 'mixes.manage')->count());
    }

    public function test_catalog_wording_is_neutral_without_brand_trade_or_emoji(): void
    {
        $this->seedCatalog();
        $db = DB::connection($this->centralConnectionName());

        $texts = array_merge(
            $db->table('plans')->pluck('name')->all(),
            $db->table('plans')->pluck('description')->all(),
            $db->table('plan_features')->pluck('name')->all(),
            $db->table('plan_features')->pluck('description')->all(),
            $db->table('plan_features')->pluck('icon')->all(),
            array_map(static fn (string $key): string => (string) __($key), $db->table('addons')->pluck('name_key')->all()),
        );

        $sources = [
            database_path('seeders/PlansAndFeaturesSeeder.php'),
            database_path('seeders/Catalog/FeatureCatalog.php'),
            database_path('seeders/Catalog/PlanCatalog.php'),
            database_path('seeders/Catalog/AddonCatalog.php'),
            database_path('seeders/Catalog/CatalogWriter.php'),
            lang_path('ar/plans.php'),
            lang_path('en/plans.php'),
        ];
        foreach ($sources as $path) {
            $texts[] = (string) file_get_contents($path);
        }

        foreach ($texts as $text) {
            if ($text === null || $text === '') {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression('/sroor|سرور|coffee|roast|قهوة|محمص|محامص|تحميص|مطاحن|مطحنة|(^|[\s(،,])بن([\s)،,.]|$)/iu', $text, 'Catalog wording must be generic (no brand or coffee trade).');
            $this->assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE0F}]/u', $text, 'No emoji in the catalog.');
        }
    }

    public function test_seeder_writes_to_the_central_database_even_inside_a_tenant(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $this->seed(PlansAndFeaturesSeeder::class);
        });

        $this->assertSame(4, Plan::query()->whereIn('slug', PlanCatalog::SLUGS)->count());
        $this->assertSame(count(self::ADDONS), Addon::query()->count());
    }

    private function seedCatalog(): void
    {
        $this->seed(PlansAndFeaturesSeeder::class);
    }

    private function plan(string $slug): Plan
    {
        return Plan::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function snapshot(): array
    {
        $db = DB::connection($this->centralConnectionName());
        $snapshot = [];

        foreach (['plans' => 'slug', 'plan_features' => 'key', 'addons' => 'key', 'plan_addon' => 'id'] as $table => $order) {
            $snapshot[$table] = $db->table($table)->orderBy($order)->get()
                ->map(fn (object $row): array => (array) $row)
                ->all();
        }

        return $snapshot;
    }
}
