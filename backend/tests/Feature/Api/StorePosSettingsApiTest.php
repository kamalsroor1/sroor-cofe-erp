<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\StorePosSetting;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * POSB-2: GET/PUT /api/v1/stores/{store}/pos-settings (per-store scale-label parser
 * settings + max discount percent) and their presence in /pos/bootstrap.
 */
final class StorePosSettingsApiTest extends TenantTestCase
{
    private const DEFAULT_PREFIXES = ['20', '21', '22', '23', '24', '25', '26', '27', '28', '29'];

    private function url(int $storeId): string
    {
        return '/api/v1/stores/'.$storeId.'/pos-settings';
    }

    private function createStore(Tenant $tenant, string $code): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع '.$code,
            'code' => $code,
            'type' => 'retail',
            'is_main' => false,
            'is_active' => true,
        ])->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'scale_barcode_enabled' => true,
            'scale_prefixes' => ['20', '21', '02'],
            'scale_plu_length' => 5,
            'scale_value_type' => 'price',
            'scale_value_length' => 5,
            'scale_weight_divisor' => 1000,
            'scale_price_divisor' => 100,
            'scale_check_digit' => true,
            'max_discount_percent' => '12.5',
        ];
    }

    public function test_get_returns_defaults_without_creating_a_row(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;

        $response = $this->getJson($this->url($storeId), $this->tenantHeaders($tenant));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.store_id', $storeId)
            ->assertJsonPath('data.scale_barcode_enabled', false)
            ->assertJsonPath('data.scale_prefixes', self::DEFAULT_PREFIXES)
            ->assertJsonPath('data.scale_plu_length', 5)
            ->assertJsonPath('data.scale_value_type', 'weight')
            ->assertJsonPath('data.scale_value_length', 5)
            ->assertJsonPath('data.scale_weight_divisor', 1000)
            ->assertJsonPath('data.scale_price_divisor', 100)
            ->assertJsonPath('data.scale_check_digit', true)
            ->assertJsonPath('data.max_discount_percent', null);

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => StorePosSetting::query()->count()));
    }

    public function test_admin_updates_settings_and_amounts_are_scale_three_strings(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;

        $response = $this->putJson($this->url($storeId), $this->validPayload(), $this->tenantHeaders($tenant));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.scale_barcode_enabled', true)
            ->assertJsonPath('data.scale_prefixes', ['20', '21', '02'])
            ->assertJsonPath('data.scale_value_type', 'price')
            ->assertJsonPath('data.max_discount_percent', '12.500');
        $this->assertIsString($response->json('message'));

        $row = $this->inTenant($tenant, fn (): array => StorePosSetting::query()->where('store_id', $storeId)->firstOrFail()->only([
            'scale_barcode_enabled', 'scale_prefixes', 'scale_value_type', 'max_discount_percent',
        ]));
        $this->assertSame([
            'scale_barcode_enabled' => true,
            'scale_prefixes' => ['20', '21', '02'],
            'scale_value_type' => 'price',
            'max_discount_percent' => '12.500',
        ], $row);
    }

    public function test_put_is_partial_and_second_put_updates_the_same_row(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $headers = $this->tenantHeaders($tenant);

        $this->putJson($this->url($storeId), $this->validPayload(), $headers)->assertOk();
        $this->putJson($this->url($storeId), ['max_discount_percent' => null, 'scale_plu_length' => 6], $headers)
            ->assertOk()
            ->assertJsonPath('data.max_discount_percent', null)
            ->assertJsonPath('data.scale_plu_length', 6)
            ->assertJsonPath('data.scale_value_type', 'price')
            ->assertJsonPath('data.scale_prefixes', ['20', '21', '02']);

        $this->assertSame(1, $this->inTenant($tenant, fn (): int => StorePosSetting::query()->where('store_id', $storeId)->count()));
    }

    public function test_validation_rejects_bad_values(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $headers = $this->tenantHeaders($tenant);

        $cases = [
            'non-numeric prefix' => [['scale_prefixes' => ['2A']], 'scale_prefixes.0'],
            'too long prefix' => [['scale_prefixes' => ['2000']], 'scale_prefixes.0'],
            'empty prefix list' => [['scale_prefixes' => []], 'scale_prefixes'],
            'duplicate prefixes' => [['scale_prefixes' => ['20', '20']], 'scale_prefixes.0'],
            'weight divisor zero' => [['scale_weight_divisor' => 0], 'scale_weight_divisor'],
            'price divisor zero' => [['scale_price_divisor' => 0], 'scale_price_divisor'],
            'weight divisor not power of ten' => [['scale_weight_divisor' => 3], 'scale_weight_divisor'],
            'plu length too short' => [['scale_plu_length' => 3], 'scale_plu_length'],
            'plu length too long' => [['scale_plu_length' => 7], 'scale_plu_length'],
            'value length too long' => [['scale_value_length' => 7], 'scale_value_length'],
            'unknown value type' => [['scale_value_type' => 'volume'], 'scale_value_type'],
            'enabled not boolean' => [['scale_barcode_enabled' => 'maybe'], 'scale_barcode_enabled'],
            'discount over 100' => [['max_discount_percent' => '100.001'], 'max_discount_percent'],
            'discount negative' => [['max_discount_percent' => '-1'], 'max_discount_percent'],
            'discount over precision' => [['max_discount_percent' => '10.1234'], 'max_discount_percent'],
        ];

        foreach ($cases as [$payload, $errorKey]) {
            $this->putJson($this->url($storeId), $payload, $headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors([$errorKey]);
        }

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => StorePosSetting::query()->count()));
    }

    public function test_requires_authentication(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $headers = ['Accept' => 'application/json', 'X-Tenant' => (string) $tenant->getTenantKey()];

        $this->getJson($this->url($storeId), $headers)->assertStatus(401);
        $this->putJson($this->url($storeId), $this->validPayload(), $headers)->assertStatus(401);
        // Authentication runs before route-model binding: no store-id enumeration for guests.
        $this->getJson($this->url(999999), $headers)->assertStatus(401);
    }

    public function test_cashier_without_settings_manage_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $headers = $this->tenantHeaders($tenant, $cashier);

        $this->getJson($this->url($storeId), $headers)->assertStatus(403)->assertJsonPath('success', false);
        $this->putJson($this->url($storeId), $this->validPayload(), $headers)->assertStatus(403);

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => StorePosSetting::query()->count()));
    }

    public function test_store_scoped_manager_reaches_only_own_store(): void
    {
        $tenant = $this->createTenant();
        $storeA = $this->createStore($tenant, 'A-01');
        $storeB = $this->createStore($tenant, 'B-01');

        /** @var User $manager */
        $manager = $this->createTenantUser($tenant, null, ['settings.manage'], ['default_store_id' => $storeA]);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($manager->id)->stores()->sync([$storeA]));

        $headersA = $this->tenantHeaders($tenant, $manager, $storeA);

        $this->getJson($this->url($storeA), $headersA)->assertOk();
        $this->putJson($this->url($storeA), ['max_discount_percent' => '5'], $headersA)
            ->assertOk()
            ->assertJsonPath('data.max_discount_percent', '5.000');

        // Branch A cannot read or edit branch B — not even by sending X-Store-Id: B.
        $headersB = $this->tenantHeaders($tenant, $manager, $storeB);
        $this->getJson($this->url($storeB), $headersA)->assertStatus(403);
        $this->putJson($this->url($storeB), ['max_discount_percent' => '50'], $headersA)->assertStatus(403);
        $this->getJson($this->url($storeB), $headersB)->assertStatus(403);
        $this->putJson($this->url($storeB), ['max_discount_percent' => '50'], $headersB)->assertStatus(403);

        $this->assertFalse($this->inTenant($tenant, fn (): bool => StorePosSetting::query()->where('store_id', $storeB)->exists()));
    }

    public function test_missing_or_soft_deleted_store_is_404(): void
    {
        $tenant = $this->createTenant();
        $deleted = $this->createStore($tenant, 'DEL-01');
        $this->inTenant($tenant, fn () => Store::query()->findOrFail($deleted)->delete());
        $headers = $this->tenantHeaders($tenant);

        $this->getJson($this->url(999999), $headers)->assertStatus(404);
        $this->putJson($this->url($deleted), $this->validPayload(), $headers)->assertStatus(404);
    }

    public function test_settings_are_isolated_between_tenants(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenantA)->id;
        $storeB = (int) $this->tenantStore($tenantB)->id;
        $extraA = $this->createStore($tenantA, 'ONLY-A');

        $this->putJson($this->url($storeA), $this->validPayload(), $this->tenantHeaders($tenantA))->assertOk();

        // Same numeric id in tenant B is B's own store, still on defaults.
        $this->getJson($this->url($storeB), $this->tenantHeaders($tenantB))
            ->assertOk()
            ->assertJsonPath('data.scale_barcode_enabled', false)
            ->assertJsonPath('data.max_discount_percent', null);

        // A store id that exists only in tenant A does not exist for tenant B.
        $this->putJson($this->url($extraA), $this->validPayload(), $this->tenantHeaders($tenantB))->assertStatus(404);

        $this->assertSame(0, $this->inTenant($tenantB, fn (): int => StorePosSetting::query()->count()));
        $this->assertSame(1, $this->inTenant($tenantA, fn (): int => StorePosSetting::query()->count()));
    }

    public function test_pos_bootstrap_includes_settings_of_the_active_store_only(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $otherId = $this->createStore($tenant, 'OTHER-01');
        $headers = $this->tenantHeaders($tenant);

        $this->putJson($this->url($mainId), $this->validPayload(), $headers)->assertOk();
        $this->putJson($this->url($otherId), ['scale_barcode_enabled' => false, 'max_discount_percent' => '1'], $headers)->assertOk();

        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, null, $mainId))
            ->assertOk()
            ->assertJsonPath('data.pos_settings.store_id', $mainId)
            ->assertJsonPath('data.pos_settings.scale_barcode_enabled', true)
            ->assertJsonPath('data.pos_settings.scale_prefixes', ['20', '21', '02'])
            ->assertJsonPath('data.pos_settings.max_discount_percent', '12.500');

        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, null, $otherId))
            ->assertOk()
            ->assertJsonPath('data.pos_settings.store_id', $otherId)
            ->assertJsonPath('data.pos_settings.max_discount_percent', '1.000');
    }

    public function test_cashier_bootstrap_gets_defaults_when_store_has_no_settings(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, $cashier, $mainId))
            ->assertOk()
            ->assertJsonPath('data.pos_settings.store_id', $mainId)
            ->assertJsonPath('data.pos_settings.scale_barcode_enabled', false)
            ->assertJsonPath('data.pos_settings.scale_prefixes', self::DEFAULT_PREFIXES);
    }
}
