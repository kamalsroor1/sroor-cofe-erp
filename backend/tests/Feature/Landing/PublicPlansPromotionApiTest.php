<?php

declare(strict_types=1);

namespace Tests\Feature\Landing;

use App\Models\Plan;
use App\Support\PublicCatalogCache;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * PRICE-2026-11 on GET /api/v1/public/plans: a promotion ("was" price) is shown only when its
 * compare-at price is above the real price AND now is inside [promo_starts_at, promo_ends_at).
 * The window is decided on every request, never frozen inside the cached payload. Enterprise
 * is the service plan (premium support included), and the payload exposes slot counts only.
 */
final class PublicPlansPromotionApiTest extends TenantTestCase
{
    private const URL = '/api/v1/public/plans';

    private const START = '2026-12-01 00:00:00';

    private const END = '2026-12-31 23:59:59';

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
        $this->seed(PlansAndFeaturesSeeder::class);
        PublicCatalogCache::forget();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(string $code): array
    {
        $plans = $this->getJson(self::URL)->assertOk()->json('data.plans');
        $this->assertIsArray($plans);
        $row = collect($plans)->firstWhere('code', $code);
        $this->assertIsArray($row, "{$code} must be public");

        return $row;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function promote(array $attributes = []): void
    {
        Plan::query()->where('slug', 'basic')->firstOrFail()->update(array_merge([
            'compare_at_price_monthly' => '599.000',
            'compare_at_price_yearly' => '5990.000',
            'promo_starts_at' => self::START,
            'promo_ends_at' => self::END,
        ], $attributes));
    }

    public function test_the_promotion_window_is_start_inclusive_and_end_exclusive(): void
    {
        $this->promote();

        $cases = [
            'one second before the start' => ['2026-11-30 23:59:59', false],
            'exactly at the start' => [self::START, true],
            'inside the window' => ['2026-12-15 12:00:00', true],
            'one second before the end' => ['2026-12-31 23:59:58', true],
            'exactly at the end' => [self::END, false],
            'after the end' => ['2027-01-01 00:00:00', false],
        ];

        foreach ($cases as $label => [$moment, $shown]) {
            // Every probe is a separate request on a cache that may hold the previous answer.
            $this->travelTo(Carbon::parse($moment));
            $promotion = $this->plan('basic')['promotion'];

            if ($shown) {
                $this->assertNotNull($promotion, $label);
                $this->assertSame('599.000', $promotion['compare_at_monthly'], $label);
                $this->assertSame('5990.000', $promotion['compare_at_yearly'], $label);
                $this->assertTrue(Carbon::parse($promotion['ends_at'])->equalTo(Carbon::parse(self::END)), $label);
            } else {
                $this->assertNull($promotion, $label);
            }
        }
    }

    public function test_the_real_price_is_never_changed_by_a_promotion(): void
    {
        $this->promote();
        $this->travelTo(Carbon::parse('2026-12-15 12:00:00'));

        $basic = $this->plan('basic');

        $this->assertSame('499.000', $basic['price_monthly']);
        $this->assertSame('4990.000', $basic['price_yearly']);
        $this->assertSame('599.000', $basic['promotion']['compare_at_monthly']);
    }

    public function test_a_promotion_without_a_start_is_already_running(): void
    {
        $this->promote(['promo_starts_at' => null]);
        $this->travelTo(Carbon::parse('2026-11-20 00:00:00'));

        $this->assertNotNull($this->plan('basic')['promotion']);
    }

    public function test_a_cached_payload_stops_showing_the_promotion_after_its_end(): void
    {
        $now = Carbon::parse('2026-12-10 12:00:00');
        $this->travelTo($now);
        $this->promote(['promo_starts_at' => null, 'promo_ends_at' => $now->copy()->addMinute()]);
        PublicCatalogCache::forget();

        $this->assertNotNull($this->plan('basic')['promotion'], 'Warms the cache with a running promotion.');

        // Still inside the 5-minute cache TTL, but past the promotion end.
        $this->travelTo($now->copy()->addMinutes(2));

        // Wipe the source row without model events: only the cache can still serve the plan.
        DB::connection((new Plan)->getConnectionName())->table('plans')->where('slug', 'basic')->update(['name' => 'changed-behind-the-cache']);

        $basic = $this->plan('basic');
        $this->assertNotSame('changed-behind-the-cache', $basic['name'], 'The response must come from the cache.');
        $this->assertNull($basic['promotion'], 'A cached offer is never shown after its end.');
    }

    public function test_a_cached_payload_does_not_show_the_promotion_before_its_start(): void
    {
        $now = Carbon::parse('2026-12-10 12:00:00');
        $this->travelTo($now);
        $this->promote(['promo_starts_at' => $now->copy()->addMinute(), 'promo_ends_at' => $now->copy()->addDay()]);
        PublicCatalogCache::forget();

        $this->assertNull($this->plan('basic')['promotion']);

        $this->travelTo($now->copy()->addMinutes(2));
        $this->assertNotNull($this->plan('basic')['promotion'], 'The same cached payload starts showing it once the start passes.');
    }

    public function test_the_internal_promotion_window_key_never_leaks(): void
    {
        $this->promote();
        $this->travelTo(Carbon::parse('2026-12-15 12:00:00'));

        $json = $this->getJson(self::URL)->assertOk();

        $this->assertArrayNotHasKey('_promotions', $json->json('data'));
        $this->assertStringNotContainsString('promo_starts_at', (string) $json->getContent());
    }

    public function test_a_compare_at_price_that_is_not_above_the_price_is_not_shown_after_a_price_edit(): void
    {
        $this->promote(['compare_at_price_yearly' => null]);
        $this->travelTo(Carbon::parse('2026-12-15 12:00:00'));
        $this->assertNotNull($this->plan('basic')['promotion']);

        // The super admin raises the price to the "was" figure: the offer is no longer real.
        Plan::query()->where('slug', 'basic')->firstOrFail()->update(['price_monthly' => '599.000']);
        $this->assertNull($this->plan('basic')['promotion'], 'compare-at == price');

        Plan::query()->where('slug', 'basic')->firstOrFail()->update(['price_monthly' => '650.000']);
        $this->assertNull($this->plan('basic')['promotion'], 'compare-at < price');
    }

    public function test_a_cycle_with_a_stale_compare_at_is_dropped_but_the_other_cycle_stays(): void
    {
        $this->promote();
        $this->travelTo(Carbon::parse('2026-12-15 12:00:00'));

        Plan::query()->where('slug', 'basic')->firstOrFail()->update(['price_yearly' => '5990.000']);

        $promotion = $this->plan('basic')['promotion'];
        $this->assertNotNull($promotion);
        $this->assertSame('599.000', $promotion['compare_at_monthly']);
        $this->assertNull($promotion['compare_at_yearly']);
    }

    public function test_a_stored_promotion_without_an_end_is_never_shown(): void
    {
        // Bypasses the request rules on purpose: the read side must not trust the row.
        $this->promote(['promo_ends_at' => null]);

        $this->assertNull($this->plan('basic')['promotion']);
    }

    public function test_plans_without_a_promotion_report_null_not_a_missing_key(): void
    {
        foreach ($this->getJson(self::URL)->assertOk()->json('data.plans') as $plan) {
            $this->assertArrayHasKey('promotion', $plan);
            $this->assertNull($plan['promotion']);
        }
    }

    public function test_the_payload_exposes_slot_counts_but_never_tenant_data(): void
    {
        $tenant = $this->createTenant(['name' => 'Secret Shop Name', 'email' => 'owner-secret@tenant.test']);
        $basic = Plan::query()->where('slug', 'basic')->firstOrFail();
        $tenant->forceFill(['plan_id' => $basic->id])->save();

        $response = $this->getJson(self::URL)->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringNotContainsString('Secret Shop Name', $body);
        $this->assertStringNotContainsString('owner-secret@tenant.test', $body);
        $this->assertStringNotContainsString((string) $tenant->getKey(), $body);
        $this->assertStringNotContainsString('tenants_count', $body);
        $this->assertStringNotContainsString('subscription', $body);
        $this->assertSame(
            ['available', 'slots_total', 'slots_left', 'months', 'transition_months'],
            array_keys($response->json('data.founder')),
        );
        $this->assertSame(50, $response->json('data.founder.slots_total'));
        $this->assertSame(12, $response->json('data.founder.transition_months'));

        foreach ($response->json('data.plans') as $plan) {
            $this->assertArrayNotHasKey('id', $plan);
            $this->assertArrayNotHasKey('tenants_count', $plan);
            $this->assertArrayNotHasKey('is_active', $plan);
        }
    }

    public function test_the_public_prices_are_the_approved_founder_schedule(): void
    {
        $expected = [
            'basic' => ['499.000', '349.000', '429.000', '4990.000', '3490.000', '4290.000'],
            'pro' => ['999.000', '699.000', '849.000', '9990.000', '6990.000', '8490.000'],
            'enterprise' => ['1499.000', '1099.000', '1299.000', '14990.000', '10990.000', '12990.000'],
        ];

        foreach ($expected as $code => [$list, $founder, $transition, $listY, $founderY, $transitionY]) {
            $plan = $this->plan($code);

            $this->assertSame($list, $plan['price_monthly'], $code);
            $this->assertSame($founder, $plan['founder_price_monthly'], $code);
            $this->assertSame($transition, $plan['founder_transition_price_monthly'], $code);
            $this->assertSame($listY, $plan['price_yearly'], $code);
            $this->assertSame($founderY, $plan['founder_price_yearly'], $code);
            $this->assertSame($transitionY, $plan['founder_transition_price_yearly'], $code);
        }
    }

    public function test_transition_prices_disappear_with_the_founder_prices_when_no_slot_is_left(): void
    {
        DB::connection((new Plan)->getConnectionName())->table('billing_sequences')
            ->where('key', 'founder_slot')->update(['last_value' => 50]);
        PublicCatalogCache::forget();

        foreach ($this->getJson(self::URL)->assertOk()->json('data.plans') as $plan) {
            $this->assertNull($plan['founder_price_monthly'], $plan['code']);
            $this->assertNull($plan['founder_transition_price_monthly'], $plan['code']);
            $this->assertNull($plan['founder_transition_price_yearly'], $plan['code']);
        }
    }

    public function test_enterprise_is_the_service_plan_with_included_services(): void
    {
        $enterprise = $this->plan('enterprise');

        $included = collect($enterprise['included_addons'])->keyBy('key');
        $this->assertTrue($included->has('addon.premium_support'), 'Premium support is included in Enterprise.');
        $this->assertSame(1, $included['addon.premium_support']['quantity']);
        $this->assertNull($included['addon.premium_support']['billing_cycle'], 'Support is included on any cycle.');

        $this->assertTrue($included->has('service.onboarding'));
        $this->assertSame('yearly', $included['service.onboarding']['billing_cycle'], 'Onboarding only with a yearly subscription.');

        // Non-service plans include nothing.
        $this->assertSame([], $this->plan('basic')['included_addons']);
        $this->assertSame([], $this->plan('pro')['included_addons']);
    }

    public function test_enterprise_does_not_sell_premium_support_and_has_no_api_access(): void
    {
        $addons = collect($this->getJson(self::URL)->assertOk()->json('data.addons'))->keyBy('key');

        $this->assertTrue($addons->has('addon.premium_support'));
        $this->assertNotContains('enterprise', array_column($addons['addon.premium_support']['plans'], 'code'), 'An included service is not for sale on the plan that includes it.');
        $this->assertContains('pro', array_column($addons['addon.premium_support']['plans'], 'code'), 'Other plans still sell it.');

        $enterprise = Plan::query()->where('slug', 'enterprise')->firstOrFail();
        $this->assertFalse($enterprise->features['api.access']);
        $this->assertNotContains('api.access', array_column($this->plan('enterprise')['features'], 'key'));
    }
}
