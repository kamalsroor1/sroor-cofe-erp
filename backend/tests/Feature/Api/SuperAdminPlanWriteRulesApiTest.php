<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanAddon;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * PRICE-2026-11 / PLAN-EDIT: write rules of POST and PUT /api/v1/super-admin/plans[/{id}].
 *
 * Every rejected write must leave the plans table, plan_addon and the public catalog untouched.
 * Money is asserted as DECIMAL(12,3) strings. Auth: 401 guest, 403 without plans.manage, 403
 * step_up_required without (or with a stale) second-factor proof.
 */
final class SuperAdminPlanWriteRulesApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const URL = '/api/v1/super-admin/plans';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Chain Plan',
            'description' => 'Custom',
            'price_monthly' => '2000',
            'price_yearly' => '20000',
            'founder_price_monthly' => '1500',
            'founder_price_yearly' => '15000',
            'founder_transition_price_monthly' => '1700',
            'founder_transition_price_yearly' => '17000',
            'compare_at_price_monthly' => null,
            'compare_at_price_yearly' => null,
            'promo_starts_at' => null,
            'promo_ends_at' => null,
            'max_users' => 40,
            'max_stores' => 15,
            'max_items' => null,
            'trial_days' => 0,
            'is_active' => true,
            'is_public' => false,
            'is_popular' => false,
            'features' => ['pos.access' => true, 'invoices.create' => true, 'shifts.manage' => true],
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function admin(): array
    {
        return $this->steppedUpCentralHeaders($this->centralSuperAdmin());
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPlan(array $overrides = []): Plan
    {
        $id = $this->postJson(self::URL, $this->payload($overrides), $this->admin())->assertCreated()->json('data.id');

        return Plan::query()->findOrFail($id);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'empty name' => [['name' => ''], ['name']],
            'name over 255 characters' => [['name' => str_repeat('ب', 256)], ['name']],
            'negative price' => [['price_monthly' => '-1'], ['price_monthly']],
            'non-numeric price' => [['price_yearly' => 'abc'], ['price_yearly']],
            'price with 4 decimals' => [['price_monthly' => '10.1234'], ['price_monthly']],
            'price above DECIMAL(12,3)' => [['price_monthly' => '1000000000'], ['price_monthly']],
            'missing yearly price' => [['price_yearly' => null], ['price_yearly']],
            'negative founder price' => [['founder_price_monthly' => '-5'], ['founder_price_monthly']],
            'founder price with 4 decimals' => [['founder_price_yearly' => '1.0001'], ['founder_price_yearly']],
            'transition below the founder price (monthly)' => [['founder_transition_price_monthly' => '1499.999'], ['founder_transition_price_monthly']],
            'transition above the list price (monthly)' => [['founder_transition_price_monthly' => '2000.001'], ['founder_transition_price_monthly']],
            'transition below the founder price (yearly)' => [['founder_transition_price_yearly' => '14999.999'], ['founder_transition_price_yearly']],
            'transition above the list price (yearly)' => [['founder_transition_price_yearly' => '20000.001'], ['founder_transition_price_yearly']],
            'transition without a founder price (monthly)' => [['founder_price_monthly' => null], ['founder_transition_price_monthly']],
            'transition without a founder price (yearly)' => [['founder_price_yearly' => null], ['founder_transition_price_yearly']],
            'compare-at equal to the price' => [['compare_at_price_monthly' => '2000'], ['compare_at_price_monthly']],
            'compare-at below the price' => [['compare_at_price_yearly' => '19999.999'], ['compare_at_price_yearly']],
            'promotion window not a date' => [['promo_starts_at' => 'not-a-date'], ['promo_starts_at']],
            'unknown feature key' => [['features' => ['no.such.feature' => true]], ['features']],
            'features is not a map' => [['features' => 'pos.access'], ['features']],
            'pos without invoices.create' => [['features' => ['pos.access' => true, 'shifts.manage' => true]], ['features']],
            'pos without shifts.manage' => [['features' => ['pos.access' => true, 'invoices.create' => true]], ['features']],
            'zero users' => [['max_users' => 0], ['max_users']],
            'limit above the cap' => [['max_stores' => 1000000001], ['max_stores']],
            'trial over a year' => [['trial_days' => 366], ['trial_days']],
            'unknown add-on id' => [['addons' => [['addon_id' => 99999999, 'is_available' => true]]], ['addons.0.addon_id']],
            'invalid included cycle' => [['addons' => [['addon_id' => 1, 'is_available' => true, 'included_billing_cycle' => 'biennial']]], ['addons.0.included_billing_cycle']],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<string>  $errorFields
     */
    #[DataProvider('invalidPayloads')]
    public function test_post_rejects_invalid_input_and_creates_nothing(array $overrides, array $errorFields): void
    {
        $count = Plan::query()->count();

        $this->postJson(self::URL, $this->payload($overrides), $this->admin())
            ->assertStatus(422)
            ->assertJsonValidationErrors($errorFields);

        $this->assertSame($count, Plan::query()->count());
    }

    public function test_post_promotion_rules(): void
    {
        $headers = $this->admin();
        $count = Plan::query()->count();
        $end = now()->addDays(10);

        // An offer needs an end.
        $this->postJson(self::URL, $this->payload(['compare_at_price_monthly' => '2500', 'promo_ends_at' => null]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['promo_ends_at']);
        $this->postJson(self::URL, $this->payload(['compare_at_price_yearly' => '25000']), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['promo_ends_at']);

        // The end must be after the start, strictly.
        $this->postJson(self::URL, $this->payload(['compare_at_price_monthly' => '2500', 'promo_starts_at' => $end->toIso8601String(), 'promo_ends_at' => $end->toIso8601String()]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['promo_ends_at']);
        $this->postJson(self::URL, $this->payload(['compare_at_price_monthly' => '2500', 'promo_starts_at' => $end->copy()->addDay()->toIso8601String(), 'promo_ends_at' => $end->toIso8601String()]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['promo_ends_at']);

        // A new offer cannot already be over.
        $this->postJson(self::URL, $this->payload(['compare_at_price_monthly' => '2500', 'promo_ends_at' => now()->subSecond()->toIso8601String()]), $headers)
            ->assertStatus(422)->assertJsonValidationErrors(['promo_ends_at']);

        $this->assertSame($count, Plan::query()->count());

        // The valid shape.
        $response = $this->postJson(self::URL, $this->payload([
            'compare_at_price_monthly' => '2500',
            'compare_at_price_yearly' => '25000',
            'promo_starts_at' => now()->addDay()->toIso8601String(),
            'promo_ends_at' => $end->toIso8601String(),
        ]), $headers)->assertCreated();

        $plan = Plan::query()->findOrFail($response->json('data.id'));
        $this->assertSame('2500.000', $plan->compare_at_price_monthly);
        $this->assertSame('25000.000', $plan->compare_at_price_yearly);
        $this->assertLessThanOrEqual(1, abs($plan->promo_ends_at->getTimestamp() - $end->getTimestamp()));
        $response->assertJsonPath('data.promotion', null);
    }

    public function test_post_accepts_transition_exactly_at_the_founder_and_the_list_price(): void
    {
        $atFounder = $this->createPlan(['name' => 'At Founder', 'founder_transition_price_monthly' => '1500', 'founder_transition_price_yearly' => '15000']);
        $atList = $this->createPlan(['name' => 'At List', 'founder_transition_price_monthly' => '2000', 'founder_transition_price_yearly' => '20000']);

        $this->assertSame('1500.000', $atFounder->founder_transition_price_monthly);
        $this->assertSame('20000.000', $atList->founder_transition_price_yearly);
    }

    public function test_post_without_a_transition_price_is_allowed_and_stores_null(): void
    {
        $plan = $this->createPlan(['founder_transition_price_monthly' => null, 'founder_transition_price_yearly' => null]);

        $this->assertNull($plan->founder_transition_price_monthly);
        $this->assertNull($plan->founder_transition_price_yearly);
        $this->assertSame('1500.000', $plan->founder_price_monthly);
    }

    public function test_post_stores_arabic_text_and_exact_decimal_strings(): void
    {
        $plan = $this->createPlan(['name' => 'باقة السلسلة', 'description' => 'وصف عربي', 'price_monthly' => 1999.5, 'price_yearly' => '19995.125']);

        $this->assertSame('باقة السلسلة', $plan->name);
        $this->assertSame('وصف عربي', $plan->description);
        $this->assertSame('1999.500', $plan->price_monthly);
        $this->assertSame('19995.125', $plan->price_yearly);
    }

    public function test_put_updates_every_pricing_field_and_the_public_catalog_follows(): void
    {
        $headers = $this->admin();
        $plan = $this->createPlan(['is_public' => true, 'name' => 'Public Custom']);
        $end = now()->addDays(5);

        $this->getJson('/api/v1/public/plans')->assertOk();

        $this->putJson(self::URL.'/'.$plan->id, $this->payload([
            'name' => 'Public Custom',
            'is_public' => true,
            'price_monthly' => '1800',
            'founder_price_monthly' => '1200',
            'founder_transition_price_monthly' => '1500',
            'compare_at_price_monthly' => '2300',
            'promo_ends_at' => $end->toIso8601String(),
        ]), $headers)
            ->assertOk()
            ->assertJsonPath('data.price_monthly', '1800.000')
            ->assertJsonPath('data.founder_price_monthly', '1200.000')
            ->assertJsonPath('data.founder_transition_price_monthly', '1500.000')
            ->assertJsonPath('data.promotion.compare_at_monthly', '2300.000');

        $plan->refresh();
        $this->assertSame('1800.000', $plan->price_monthly);
        $this->assertSame('1200.000', $plan->founder_price_monthly);
        $this->assertSame('1500.000', $plan->founder_transition_price_monthly);
        $this->assertSame('2300.000', $plan->compare_at_price_monthly);

        // The cached public pricing page is invalidated by the write.
        $public = collect($this->getJson('/api/v1/public/plans')->assertOk()->json('data.plans'))->firstWhere('code', $plan->slug);
        $this->assertSame('1800.000', $public['price_monthly']);
        $this->assertSame('1500.000', $public['founder_transition_price_monthly']);
        $this->assertSame('2300.000', $public['promotion']['compare_at_monthly']);
    }

    #[DataProvider('invalidPayloads')]
    public function test_put_rejects_the_same_invalid_input_and_changes_nothing(array $overrides, array $errorFields): void
    {
        $plan = $this->createPlan();
        $before = $plan->fresh()->getAttributes();

        $this->putJson(self::URL.'/'.$plan->id, $this->payload($overrides), $this->admin())
            ->assertStatus(422)
            ->assertJsonValidationErrors($errorFields);

        $this->assertSame($before, $plan->fresh()->getAttributes());
    }

    public function test_put_checks_cross_field_rules_against_the_stored_values_of_omitted_fields(): void
    {
        $plan = $this->createPlan();
        $payload = $this->payload();
        unset($payload['founder_price_monthly'], $payload['founder_transition_price_monthly']);

        // Omitted fields keep their stored value: the update goes through and keeps them.
        $this->putJson(self::URL.'/'.$plan->id, $payload, $this->admin())->assertOk();
        $this->assertSame('1500.000', $plan->fresh()->founder_price_monthly);
        $this->assertSame('1700.000', $plan->fresh()->founder_transition_price_monthly);

        // Removing only the founder price while the stored transition price stays is invalid.
        $payload = $this->payload();
        unset($payload['founder_transition_price_monthly']);
        $payload['founder_price_monthly'] = null;
        $this->putJson(self::URL.'/'.$plan->id, $payload, $this->admin())
            ->assertStatus(422)->assertJsonValidationErrors(['founder_transition_price_monthly']);

        // Lowering the list price under the stored transition price is invalid.
        $payload = $this->payload(['price_monthly' => '1600']);
        unset($payload['founder_transition_price_monthly']);
        $this->putJson(self::URL.'/'.$plan->id, $payload, $this->admin())
            ->assertStatus(422)->assertJsonValidationErrors(['founder_transition_price_monthly']);

        $this->assertSame('2000.000', $plan->fresh()->price_monthly);
    }

    public function test_an_ended_untouched_promotion_does_not_block_other_edits_but_changing_it_does(): void
    {
        $plan = $this->createPlan();
        $plan->forceFill([
            'compare_at_price_monthly' => '2600.000',
            'promo_starts_at' => now()->subDays(10),
            'promo_ends_at' => now()->subDay(),
        ])->save();

        $payload = $this->payload(['description' => 'edited while the offer is over']);
        unset($payload['compare_at_price_monthly'], $payload['compare_at_price_yearly'], $payload['promo_starts_at'], $payload['promo_ends_at']);
        $this->putJson(self::URL.'/'.$plan->id, $payload, $this->admin())
            ->assertOk()
            ->assertJsonPath('data.promotion', null);
        $this->assertSame('edited while the offer is over', $plan->fresh()->description);

        // Re-saving a changed compare-at price against the finished window is refused.
        $this->putJson(self::URL.'/'.$plan->id, $this->payload([
            'compare_at_price_monthly' => '2700',
            'promo_starts_at' => $plan->fresh()->promo_starts_at->toIso8601String(),
            'promo_ends_at' => $plan->fresh()->promo_ends_at->toIso8601String(),
        ]), $this->admin())->assertStatus(422)->assertJsonValidationErrors(['promo_ends_at']);
        $this->assertSame('2600.000', $plan->fresh()->compare_at_price_monthly);
    }

    public function test_clearing_a_promotion_on_put_removes_the_offer(): void
    {
        $plan = $this->createPlan(['compare_at_price_monthly' => '2500', 'promo_ends_at' => now()->addDays(3)->toIso8601String()]);
        $this->assertNotNull($plan->compare_at_price_monthly);

        $this->putJson(self::URL.'/'.$plan->id, $this->payload(['compare_at_price_monthly' => null, 'promo_ends_at' => null]), $this->admin())
            ->assertOk()
            ->assertJsonPath('data.promotion', null);

        $plan->refresh();
        $this->assertNull($plan->compare_at_price_monthly);
        $this->assertNull($plan->promo_ends_at);
    }

    public function test_put_cannot_include_a_store_addon_and_changes_nothing(): void
    {
        $plan = $this->createPlan();
        $store = Addon::query()->where('key', 'addon.store')->firstOrFail();
        $rows = PlanAddon::query()->where('plan_id', $plan->id)->count();

        $this->putJson(self::URL.'/'.$plan->id, $this->payload([
            'name' => 'Renamed',
            'addons' => [['addon_id' => $store->id, 'is_available' => true, 'included_quantity' => 1]],
        ]), $this->admin())->assertStatus(422)->assertJsonValidationErrors(['addons.0.included_quantity']);

        $this->assertSame('Chain Plan', $plan->fresh()->name, 'A rejected write must not rename the plan.');
        $this->assertSame($rows, PlanAddon::query()->where('plan_id', $plan->id)->count());
    }

    public function test_put_can_include_support_with_a_billing_cycle(): void
    {
        $plan = $this->createPlan();
        $support = Addon::query()->where('key', 'addon.premium_support')->firstOrFail();

        $this->putJson(self::URL.'/'.$plan->id, $this->payload([
            'addons' => [['addon_id' => $support->id, 'is_available' => false, 'included_quantity' => 1, 'included_billing_cycle' => 'yearly']],
        ]), $this->admin())
            ->assertOk()
            ->assertJsonPath('data.included_addons.0.key', 'addon.premium_support')
            ->assertJsonPath('data.included_addons.0.billing_cycle', 'yearly');

        $line = PlanAddon::query()->where('plan_id', $plan->id)->where('addon_id', $support->id)->sole();
        $this->assertFalse($line->is_available);
        $this->assertSame(1, $line->included_quantity);
        $this->assertSame('yearly', $line->included_billing_cycle->value);
    }

    public function test_put_unknown_plan_is_404_and_a_hidden_feature_is_reported_not_written(): void
    {
        $this->putJson(self::URL.'/99999999', $this->payload(), $this->admin())->assertNotFound();

        $plan = $this->createPlan();
        $this->putJson(self::URL.'/'.$plan->id, $this->payload(['features' => ['pos.access' => true, 'invoices.create' => true, 'shifts.manage' => true, 'quotations.manage' => true]]), $this->admin())
            ->assertOk()
            ->assertJsonPath('ignored_features', ['quotations.manage']);
        $this->assertFalse($plan->fresh()->features['quotations.manage']);
    }

    public function test_authentication_and_authorization_on_both_writes(): void
    {
        $plan = $this->createPlan();
        $count = Plan::query()->count();
        $before = $plan->fresh()->getAttributes();

        // 401: no token, and a tenant token (wrong guard).
        $this->postJson(self::URL, $this->payload())->assertStatus(401);
        $this->putJson(self::URL.'/'.$plan->id, $this->payload(['name' => 'Hacked']))->assertStatus(401);
        $tenantHeaders = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($this->createTenant())];
        $this->postJson(self::URL, $this->payload(), $tenantHeaders)->assertStatus(401);
        $this->putJson(self::URL.'/'.$plan->id, $this->payload(['name' => 'Hacked']), $tenantHeaders)->assertStatus(401);

        // 403: a support operator has plans.view but not plans.manage (even with a fresh 2FA proof).
        $support = $this->steppedUpCentralHeaders($this->centralSupport());
        $this->postJson(self::URL, $this->payload(), $support)->assertStatus(403);
        $this->putJson(self::URL.'/'.$plan->id, $this->payload(['name' => 'Hacked']), $support)->assertStatus(403);

        $this->assertSame($count, Plan::query()->count());
        $this->assertSame($before, $plan->fresh()->getAttributes());
    }

    public function test_both_writes_need_a_recent_second_factor(): void
    {
        $plan = $this->createPlan();
        $operator = $this->centralSuperAdmin();
        $count = Plan::query()->count();
        $before = $plan->fresh()->getAttributes();
        $stale = now()->subMinutes((int) config('central.step_up_ttl_minutes', 15) + 1);

        foreach ([$this->centralHeaders($operator), $this->steppedUpCentralHeaders($operator, $stale)] as $headers) {
            $this->postJson(self::URL, $this->payload(), $headers)
                ->assertStatus(403)->assertJsonPath('error_code', 'central_auth.step_up_required');
            $this->putJson(self::URL.'/'.$plan->id, $this->payload(['name' => 'Hacked']), $headers)
                ->assertStatus(403)->assertJsonPath('error_code', 'central_auth.step_up_required');
        }

        $this->assertSame($count, Plan::query()->count());
        $this->assertSame($before, $plan->fresh()->getAttributes());
    }

    public function test_reading_plans_needs_a_central_token_but_no_second_factor(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->getJson(self::URL, $this->centralHeaders($this->centralSuperAdmin()))->assertOk();
    }

    public function test_a_custom_plan_survives_the_seeder_and_the_2026_11_15_data_migrations_untouched(): void
    {
        $support = Addon::query()->where('key', 'addon.premium_support')->firstOrFail();
        $custom = $this->createPlan([
            'name' => 'Pro',
            'price_monthly' => '777',
            'price_yearly' => '7770',
            'founder_price_monthly' => '500',
            'founder_price_yearly' => '5000',
            'founder_transition_price_monthly' => '600',
            'founder_transition_price_yearly' => '6000',
            'compare_at_price_monthly' => '900',
            'promo_ends_at' => now()->addDays(20)->toIso8601String(),
            'features' => ['pos.access' => true, 'invoices.create' => true, 'shifts.manage' => true, 'api.access' => true],
            'addons' => [['addon_id' => $support->id, 'is_available' => true, 'included_quantity' => 0]],
        ]);
        $this->assertSame('custom-pro', $custom->slug);

        $planBefore = DB::table('plans')->where('id', $custom->id)->first();
        $addonsBefore = DB::table('plan_addon')->where('plan_id', $custom->id)->orderBy('addon_id')->get()->all();
        $catalogBefore = DB::table('plans')->whereIn('slug', Plan::CATALOG_SLUGS)->orderBy('slug')->get()->all();

        for ($run = 1; $run <= 2; $run++) {
            $this->seed(PlansAndFeaturesSeeder::class);

            foreach (['2026_11_15_100000_add_founder_transition_and_promotion_to_plans_table', '2026_11_15_100100_apply_2026_11_price_list', '2026_11_15_100200_add_included_billing_cycle_to_plan_addon_table', '2026_11_15_100300_apply_enterprise_service_plan'] as $name) {
                (require database_path('migrations/'.$name.'.php'))->up();
            }

            $this->assertEquals($planBefore, DB::table('plans')->where('id', $custom->id)->first(), "custom plan row after run {$run}");
            $this->assertEquals($addonsBefore, DB::table('plan_addon')->where('plan_id', $custom->id)->orderBy('addon_id')->get()->all(), "custom plan add-ons after run {$run}");
            $this->assertSame(1, Plan::query()->where('slug', 'custom-pro')->count());
            $this->assertSame(1, Plan::query()->where('slug', 'pro')->count());
        }

        // The catalog is idempotent too: a second pass writes the same rows.
        $this->assertEquals($catalogBefore, DB::table('plans')->whereIn('slug', Plan::CATALOG_SLUGS)->orderBy('slug')->get()->all());
    }

    public function test_the_enterprise_data_migration_is_idempotent_and_keeps_support_unpurchasable(): void
    {
        $enterprise = Plan::query()->where('slug', 'enterprise')->firstOrFail();
        $support = Addon::query()->where('key', 'addon.premium_support')->firstOrFail();

        for ($run = 0; $run < 2; $run++) {
            (require database_path('migrations/2026_11_15_100300_apply_enterprise_service_plan.php'))->up();

            $line = PlanAddon::query()->where('plan_id', $enterprise->id)->where('addon_id', $support->id)->sole();
            $this->assertFalse($line->is_available, 'Included support is not for sale on Enterprise.');
            $this->assertSame(1, $line->included_quantity);
            $this->assertFalse($enterprise->fresh()->features['api.access']);
            $this->assertSame(1, PlanAddon::query()->where('plan_id', $enterprise->id)->where('addon_id', $support->id)->count());
        }
    }
}
