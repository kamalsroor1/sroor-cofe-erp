<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Enums\Billing\AddonType;
use App\Enums\Billing\BillingCycle;
use App\Exceptions\Billing\AddonPricingException;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanAddon;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * ENTI-1.4: Addon::unitPriceFor() / PlanAddon::unitPriceFor().
 *
 * - tier prices are explicit stored decimals (no percentage, no rounding rule);
 * - the WHOLE quantity is billed at the tier price (Q-E3);
 * - users: 79, 59 from the 6th, no 15%/25% volume discount (Q-E2) — pure data;
 * - the yearly price is an explicit stored value, never monthly × 10;
 * - service add-ons return their one-time price;
 * - biennial is enum-only in Phase 1 (Q-E6): asking for its price throws a translated exception.
 */
#[Group('billing')]
final class AddonPricingTest extends TenantTestCase
{
    /**
     * @return array<string, array{int, string}>
     */
    public static function storeTiers(): array
    {
        return [
            '1 store' => [1, '249.000'],
            '2 stores' => [2, '249.000'],
            '3 stores' => [3, '212.000'],
            '5 stores' => [5, '212.000'],
            '6 stores' => [6, '187.000'],
            '40 stores' => [40, '187.000'],
        ];
    }

    #[DataProvider('storeTiers')]
    public function test_store_tiers_return_the_stored_prices_literally(int $quantity, string $expected): void
    {
        $this->assertSame($expected, $this->storeAddon()->unitPriceFor(BillingCycle::Monthly, $quantity));
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function storeYearlyTiers(): array
    {
        return [
            '1 store' => [1, '2490.000'],
            '4 stores' => [4, '2120.000'],
            '6 stores' => [6, '1870.000'],
        ];
    }

    #[DataProvider('storeYearlyTiers')]
    public function test_yearly_price_is_the_explicit_stored_value(int $quantity, string $expected): void
    {
        $this->assertSame($expected, $this->storeAddon()->unitPriceFor(BillingCycle::Yearly, $quantity));
    }

    public function test_yearly_price_is_not_derived_from_the_monthly_price(): void
    {
        $addon = $this->addon(['key' => 'addon.odd', 'unit_price' => '100.000', 'yearly_price' => '950.500']);

        $this->assertSame('950.500', $addon->unitPriceFor(BillingCycle::Yearly));
    }

    public function test_users_get_59_from_the_sixth_and_no_volume_discount(): void
    {
        $users = $this->addon([
            'key' => 'addon.user',
            'unit_price' => '79.000',
            'yearly_price' => '790.000',
            'price_tiers' => [['min_qty' => 6, 'unit_price' => '59.000', 'yearly_price' => '590.000']],
        ]);

        // No 15% at 3-5 users (Q-E2): still 79.
        $this->assertSame('79.000', $users->unitPriceFor(BillingCycle::Monthly, 1));
        $this->assertSame('79.000', $users->unitPriceFor(BillingCycle::Monthly, 3));
        $this->assertSame('79.000', $users->unitPriceFor(BillingCycle::Monthly, 5));
        $this->assertSame('59.000', $users->unitPriceFor(BillingCycle::Monthly, 6));
        $this->assertSame('59.000', $users->unitPriceFor(BillingCycle::Monthly, 25));
        $this->assertSame('590.000', $users->unitPriceFor(BillingCycle::Yearly, 6));
    }

    public function test_tiers_are_resolved_regardless_of_storage_order(): void
    {
        $addon = $this->addon([
            'key' => 'addon.van',
            'unit_price' => '249',
            'price_tiers' => [
                ['min_qty' => 6, 'unit_price' => '187'],
                ['min_qty' => 3, 'unit_price' => '212'],
            ],
        ]);

        $this->assertSame('249.000', $addon->unitPriceFor(BillingCycle::Monthly, 2));
        $this->assertSame('212.000', $addon->unitPriceFor(BillingCycle::Monthly, 4));
        $this->assertSame('187.000', $addon->unitPriceFor(BillingCycle::Monthly, 7));
    }

    public function test_default_quantity_is_one(): void
    {
        $this->assertSame('249.000', $this->storeAddon()->unitPriceFor(BillingCycle::Monthly));
    }

    public function test_service_addon_returns_its_one_time_price(): void
    {
        $onboarding = $this->addon([
            'key' => 'service.onboarding',
            'type' => AddonType::Service,
            'unit_price' => '1500.000',
            'yearly_price' => null,
        ]);

        $this->assertSame('1500.000', $onboarding->unitPriceFor(BillingCycle::Monthly));
        $this->assertSame('1500.000', $onboarding->unitPriceFor(BillingCycle::Yearly), 'A service is a one-time charge, not a yearly price.');
        $this->assertSame('1500.000', $onboarding->unitPriceFor(BillingCycle::Monthly, 3), 'The unit price of a service does not depend on quantity.');
    }

    public function test_biennial_price_throws_a_translated_exception(): void
    {
        $addon = $this->storeAddon();

        app()->setLocale('en');
        $en = $this->captureException(fn () => $addon->unitPriceFor(BillingCycle::Biennial));
        $this->assertSame(__('billing.addon_pricing.cycle_not_sellable', ['cycle' => BillingCycle::Biennial->label()]), $en->getMessage());
        $this->assertStringNotContainsString('billing.addon_pricing', $en->getMessage(), 'The key must be translated, not echoed.');

        app()->setLocale('ar');
        $ar = $this->captureException(fn () => $addon->unitPriceFor(BillingCycle::Biennial));
        $this->assertStringNotContainsString('billing.addon_pricing', $ar->getMessage());
        $this->assertNotSame($en->getMessage(), $ar->getMessage());
        $this->assertSame('cycle_not_sellable', $ar->reason());

        $service = $this->addon(['key' => 'service.support', 'type' => AddonType::Service, 'unit_price' => '299']);
        $this->assertSame('cycle_not_sellable', $this->captureException(fn () => $service->unitPriceFor(BillingCycle::Biennial))->reason());
    }

    public function test_missing_yearly_price_throws_instead_of_computing_one(): void
    {
        $noYearly = $this->addon(['key' => 'addon.no_yearly', 'unit_price' => '149.000', 'yearly_price' => null]);
        $this->assertSame('yearly_price_missing', $this->captureException(fn () => $noYearly->unitPriceFor(BillingCycle::Yearly))->reason());

        $tierWithoutYearly = $this->addon([
            'key' => 'addon.tier_no_yearly',
            'unit_price' => '249.000',
            'yearly_price' => '2490.000',
            'price_tiers' => [['min_qty' => 3, 'unit_price' => '212.000']],
        ]);
        $this->assertSame('2490.000', $tierWithoutYearly->unitPriceFor(BillingCycle::Yearly, 2));
        $this->assertSame('212.000', $tierWithoutYearly->unitPriceFor(BillingCycle::Monthly, 3));
        $this->assertSame('yearly_price_missing', $this->captureException(fn () => $tierWithoutYearly->unitPriceFor(BillingCycle::Yearly, 3))->reason());
    }

    public function test_quantity_below_one_is_rejected(): void
    {
        $addon = $this->storeAddon();

        $this->assertSame('invalid_quantity', $this->captureException(fn () => $addon->unitPriceFor(BillingCycle::Monthly, 0))->reason());
        $this->assertSame('invalid_quantity', $this->captureException(fn () => $addon->unitPriceFor(BillingCycle::Monthly, -2))->reason());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidTiers(): array
    {
        return [
            'float price' => [[['min_qty' => 3, 'unit_price' => 212.5]]],
            'negative price' => [[['min_qty' => 3, 'unit_price' => '-1.000']]],
            'four decimals' => [[['min_qty' => 3, 'unit_price' => '212.0001']]],
            'missing min_qty' => [[['unit_price' => '212.000']]],
            'min_qty of one' => [[['min_qty' => 1, 'unit_price' => '212.000']]],
            'string min_qty' => [[['min_qty' => '3', 'unit_price' => '212.000']]],
            'duplicate min_qty' => [[['min_qty' => 3, 'unit_price' => '212.000'], ['min_qty' => 3, 'unit_price' => '200.000']]],
            'not a list of objects' => [['212.000']],
            'bad yearly price' => [[['min_qty' => 3, 'unit_price' => '212.000', 'yearly_price' => 'abc']]],
        ];
    }

    #[DataProvider('invalidTiers')]
    public function test_malformed_tiers_are_rejected_not_guessed(mixed $tiers): void
    {
        $addon = $this->addon(['key' => 'addon.bad', 'unit_price' => '249.000', 'price_tiers' => $tiers]);

        $this->assertSame('invalid_price_tiers', $this->captureException(fn () => $addon->unitPriceFor(BillingCycle::Monthly, 4))->reason());
    }

    public function test_integer_prices_in_tiers_are_normalised_to_three_decimals(): void
    {
        $addon = $this->addon(['key' => 'addon.int', 'unit_price' => '249', 'price_tiers' => [['min_qty' => 3, 'unit_price' => 212]]]);

        $this->assertSame('212.000', $addon->unitPriceFor(BillingCycle::Monthly, 3));
    }

    public function test_plan_override_replaces_the_catalog_price_for_that_cycle_only(): void
    {
        $plan = Plan::query()->create([
            'name' => 'pro',
            'slug' => 'pro',
            'price_monthly' => '899.000',
            'price_yearly' => '8990.000',
            'features' => [],
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $store = $this->storeAddon();

        $overridden = PlanAddon::query()->create([
            'plan_id' => $plan->id,
            'addon_id' => $store->id,
            'unit_price_override' => '199',
        ]);
        $overridden = PlanAddon::query()->findOrFail($overridden->id);

        $this->assertSame('199.000', $overridden->unitPriceFor(BillingCycle::Monthly, 1));
        $this->assertSame('199.000', $overridden->unitPriceFor(BillingCycle::Monthly, 6), 'An override is a flat plan price.');
        $this->assertSame('1870.000', $overridden->unitPriceFor(BillingCycle::Yearly, 6), 'No yearly override: catalog yearly tiers apply.');
        $this->assertSame('cycle_not_sellable', $this->captureException(fn () => $overridden->unitPriceFor(BillingCycle::Biennial))->reason());

        $overridden->update(['unit_price_override' => null]);
        $this->assertSame('187.000', $overridden->fresh()?->unitPriceFor(BillingCycle::Monthly, 6));
    }

    public function test_pricing_does_not_use_float_arithmetic(): void
    {
        $source = (string) file_get_contents(app_path('Models/Addon.php'));

        $this->assertDoesNotMatchRegularExpression('/\(float\)|floatval|round\(|number_format\(/', $source);
    }

    private function storeAddon(): Addon
    {
        return $this->addon([
            'key' => 'addon.store',
            'bundled_limits' => ['stores' => 1, 'users' => 1],
            'unit_price' => '249.000',
            'yearly_price' => '2490.000',
            'price_tiers' => [
                ['min_qty' => 3, 'unit_price' => '212.000', 'yearly_price' => '2120.000'],
                ['min_qty' => 6, 'unit_price' => '187.000', 'yearly_price' => '1870.000'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function addon(array $attributes): Addon
    {
        $addon = Addon::query()->create(array_merge(['name_key' => 'plans.addons.test'], $attributes));

        return Addon::query()->findOrFail($addon->id);
    }

    private function captureException(Closure $callback): AddonPricingException
    {
        try {
            $callback();
        } catch (AddonPricingException $exception) {
            return $exception;
        }

        $this->fail('Expected '.AddonPricingException::class.' was not thrown.');
    }
}
