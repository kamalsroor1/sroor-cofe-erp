<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Settings\TenantSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TenantTestCase;

/**
 * SETG-10 (backend): the saved `inventory_units` setting IS the tenant's unit list —
 * exposed in /system/context and enforced on item create/update (a unit outside the list
 * is a translated 422; a legacy item keeps reading and may keep its old unit). Plus
 * `low_stock_default_threshold`, used for items without their own minimum.
 */
final class TenantUnitsApiTest extends TenantTestCase
{
    public function test_default_unit_list_and_threshold_are_exposed_in_the_system_context(): void
    {
        $tenant = $this->createTenant();

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.system.inventory_units', TenantSettings::parseUnits(TenantSettings::DEFAULT_INVENTORY_UNITS))
            ->assertJsonPath('data.system.low_stock_default_threshold', '5.000');
    }

    public function test_saved_unit_list_is_the_tenant_list(): void
    {
        $tenant = $this->tenantWithUnits(' كجم , جرام,,كجم ,شيكارة ');

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.system.inventory_units', ['كجم', 'جرام', 'شيكارة']);

        $this->getJson('/api/v1/settings', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('settings.inventory_units', 'كجم,جرام,شيكارة');
    }

    public function test_item_with_a_listed_unit_is_created(): void
    {
        $tenant = $this->tenantWithUnits('كجم,جرام');

        $this->postJson('/api/v1/items', $this->itemPayload(['unit' => 'جرام']), $this->tenantHeaders($tenant))
            ->assertStatus(201)
            ->assertJsonPath('data.unit', 'جرام');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unlistedUnits(): array
    {
        return [
            'unit not in the tenant list' => ['قطعة'],
            'unknown unit' => ['طن'],
            'latin spelling' => ['kg'],
        ];
    }

    #[DataProvider('unlistedUnits')]
    public function test_item_with_a_unit_outside_the_list_gets_a_translated_422(string $unit): void
    {
        $tenant = $this->tenantWithUnits('كجم,جرام');

        $response = $this->postJson('/api/v1/items', $this->itemPayload(['unit' => $unit]), $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit']);

        $message = (string) $response->json('errors.unit.0');
        $this->assertStringContainsString($unit, $message);
        $this->assertNotSame('inventory.unit_not_in_tenant_list', $message);

        $this->inTenant($tenant, fn () => $this->assertFalse(Item::query()->where('code', 'UNIT-TEST')->exists()));
    }

    public function test_english_locale_gets_the_english_message(): void
    {
        $tenant = $this->tenantWithUnits('كجم');

        $response = $this->postJson('/api/v1/items', $this->itemPayload(['unit' => 'box']), $this->tenantHeaders($tenant) + ['X-Locale' => 'en'])
            ->assertStatus(422);

        $this->assertSame(
            trans('inventory.unit_not_in_tenant_list', ['unit' => 'box'], 'en'),
            $response->json('errors.unit.0'),
        );
    }

    public function test_legacy_item_with_an_unknown_unit_still_reads_and_keeps_its_unit_on_update(): void
    {
        $tenant = $this->tenantWithUnits('كجم,جرام');
        $itemId = $this->inTenant($tenant, fn (): int => (int) Item::create([
            'name' => 'صنف قديم',
            'code' => 'LEGACY-1',
            'unit' => 'شوال',
            'cost_price' => '10.000',
            'selling_price' => '12.000',
            'is_active' => true,
        ])->id);

        $this->getJson("/api/v1/items/{$itemId}", $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.unit', 'شوال');

        // Editing the price without touching the unit works.
        $this->putJson("/api/v1/items/{$itemId}", $this->itemPayload([
            'name' => 'صنف قديم',
            'code' => 'LEGACY-1',
            'unit' => 'شوال',
            'selling_price' => '13.000',
        ]), $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.unit', 'شوال');

        // Moving it to another unlisted unit does not.
        $this->putJson("/api/v1/items/{$itemId}", $this->itemPayload([
            'name' => 'صنف قديم',
            'code' => 'LEGACY-1',
            'unit' => 'برميل',
        ]), $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['unit']);

        // Moving it to a listed unit does.
        $this->putJson("/api/v1/items/{$itemId}", $this->itemPayload([
            'name' => 'صنف قديم',
            'code' => 'LEGACY-1',
            'unit' => 'كجم',
        ]), $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.unit', 'كجم');
    }

    public function test_unit_list_of_one_tenant_does_not_apply_to_another(): void
    {
        $narrow = $this->tenantWithUnits('كجم');
        $other = $this->tenantWithUnits('قطعة,علبة');

        $this->postJson('/api/v1/items', $this->itemPayload(['unit' => 'قطعة']), $this->tenantHeaders($narrow))
            ->assertStatus(422);
        $this->postJson('/api/v1/items', $this->itemPayload(['unit' => 'قطعة']), $this->tenantHeaders($other))
            ->assertStatus(201);
    }

    public function test_item_create_still_requires_auth_and_permission(): void
    {
        $tenant = $this->tenantWithUnits('كجم');

        $this->postJson('/api/v1/items', $this->itemPayload(), $this->tenantGuestHeaders($tenant))
            ->assertStatus(401);

        $cashier = $this->createTenantUser($tenant);
        $this->postJson('/api/v1/items', $this->itemPayload(), $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403);
    }

    public function test_items_without_own_minimum_use_the_default_threshold(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $this->makeItem('NO-MIN-LOW', '4.000', '0.000');   // no minimum, 4 <= 5 -> low
            $this->makeItem('NO-MIN-OK', '6.000', '0.000');    // no minimum, 6 > 5  -> fine
            $this->makeItem('OWN-MIN-LOW', '9.000', '10.000'); // own minimum wins   -> low
            $this->makeItem('OWN-MIN-OK', '3.000', '2.000');   // own minimum wins   -> fine (even though 3 <= 5)
        });

        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('metrics.low_stock_count', 2);

        // Raising the default threshold to 7 adds the "no minimum, 6 in stock" item.
        $this->postJson('/api/v1/settings', ['company_name' => 'محل', 'low_stock_default_threshold' => '7'], $this->tenantHeaders($tenant))
            ->assertStatus(200);

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.system.low_stock_default_threshold', '7.000');
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('metrics.low_stock_count', 3);
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function invalidThresholds(): array
    {
        return [
            'negative' => ['-1'],
            'over precision' => ['1.2345'],
            'text' => ['many'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidThresholds')]
    public function test_invalid_threshold_is_rejected_with_422(mixed $value): void
    {
        $tenant = $this->createTenant();

        $this->postJson('/api/v1/settings', ['company_name' => 'محل', 'low_stock_default_threshold' => $value], $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['low_stock_default_threshold']);

        $this->inTenant($tenant, function (): void {
            $this->assertFalse(Setting::query()->where('key', 'low_stock_default_threshold')->exists());
            $this->assertSame('5.000', app(TenantSettings::class)->lowStockDefaultThreshold());
        });
    }

    private function tenantWithUnits(string $units): Tenant
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($units): void {
            Setting::set('inventory_units', $units);
        });

        return $tenant;
    }

    private function makeItem(string $code, string $stock, string $min): void
    {
        Item::create([
            'name' => 'صنف '.$code,
            'code' => $code,
            'unit' => 'كجم',
            'cost_price' => '10.000',
            'selling_price' => '12.000',
            'current_stock' => $stock,
            'min_stock_level' => $min,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function itemPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'صنف اختبار الوحدات',
            'code' => 'UNIT-TEST',
            'unit' => 'كجم',
            'cost_price' => '10.000',
            'selling_price' => '12.000',
        ], $overrides);
    }
}
