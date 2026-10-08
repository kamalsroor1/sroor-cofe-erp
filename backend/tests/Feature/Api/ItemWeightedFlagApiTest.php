<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * POSB-2: `is_weighted` is an explicit item flag (replaces guessing from the unit name).
 * Accepted by POST/PUT /api/v1/items, returned by ItemResource and in /pos/bootstrap items.
 */
final class ItemWeightedFlagApiTest extends TenantTestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'بن برازيلي',
            'unit' => 'كجم',
            'cost_price' => '150.000',
            'selling_price' => '200.000',
        ], $overrides);
    }

    private function itemFlag(Tenant $tenant, int $id): bool
    {
        return $this->inTenant($tenant, fn (): bool => (bool) Item::query()->findOrFail($id)->is_weighted);
    }

    public function test_create_accepts_is_weighted_true(): void
    {
        $tenant = $this->createTenant();

        $response = $this->postJson('/api/v1/items', $this->payload(['is_weighted' => true]), $this->tenantHeaders($tenant));

        $response->assertStatus(201)->assertJsonPath('data.is_weighted', true);
        $this->assertTrue($this->itemFlag($tenant, (int) $response->json('data.id')));
    }

    public function test_create_defaults_to_not_weighted_even_for_kg_unit(): void
    {
        $tenant = $this->createTenant();

        $response = $this->postJson('/api/v1/items', $this->payload(), $this->tenantHeaders($tenant));

        $response->assertStatus(201)->assertJsonPath('data.is_weighted', false);
        $this->assertFalse($this->itemFlag($tenant, (int) $response->json('data.id')));
    }

    public function test_update_sets_and_clears_flag_and_keeps_it_when_omitted(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);
        $id = (int) $this->postJson('/api/v1/items', $this->payload(), $headers)->assertStatus(201)->json('data.id');

        $this->putJson('/api/v1/items/'.$id, $this->payload(['is_weighted' => true]), $headers)
            ->assertOk()
            ->assertJsonPath('data.is_weighted', true);
        $this->assertTrue($this->itemFlag($tenant, $id));

        // Legacy clients that do not know the flag must not reset it.
        $this->putJson('/api/v1/items/'.$id, $this->payload(['name' => 'بن برازيلي محوج']), $headers)
            ->assertOk()
            ->assertJsonPath('data.is_weighted', true);
        $this->assertTrue($this->itemFlag($tenant, $id));

        $this->putJson('/api/v1/items/'.$id, $this->payload(['is_weighted' => false]), $headers)
            ->assertOk()
            ->assertJsonPath('data.is_weighted', false);
        $this->assertFalse($this->itemFlag($tenant, $id));
    }

    public function test_is_weighted_must_be_boolean(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);

        $this->postJson('/api/v1/items', $this->payload(['is_weighted' => 'kg']), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_weighted']);

        $id = (int) $this->postJson('/api/v1/items', $this->payload(), $headers)->json('data.id');
        $this->putJson('/api/v1/items/'.$id, $this->payload(['is_weighted' => 'yes please']), $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['is_weighted']);

        $this->assertSame(1, $this->inTenant($tenant, fn (): int => Item::query()->count()));
    }

    public function test_requires_authentication_and_item_permission(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->postJson('/api/v1/items', $this->payload(['is_weighted' => true]), [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
        ])->assertStatus(401);

        $this->postJson('/api/v1/items', $this->payload(['is_weighted' => true]), $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403);

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Item::query()->count()));
    }

    public function test_flag_is_isolated_per_tenant(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $idA = (int) $this->postJson('/api/v1/items', $this->payload(['is_weighted' => true]), $this->tenantHeaders($tenantA))
            ->assertStatus(201)
            ->json('data.id');

        $this->assertSame(0, $this->inTenant($tenantB, fn (): int => Item::query()->count()));
        $this->getJson('/api/v1/items/'.$idA, $this->tenantHeaders($tenantB))->assertStatus(404);
    }

    public function test_pos_bootstrap_items_expose_is_weighted(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);

        $weighed = (int) $this->postJson('/api/v1/items', $this->payload(['is_weighted' => true]), $headers)->json('data.id');
        $piece = (int) $this->postJson('/api/v1/items', $this->payload(['name' => 'كوب', 'unit' => 'قطعة']), $headers)->json('data.id');

        $items = collect($this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant))->assertOk()->json('data.items'))
            ->keyBy('id');

        $this->assertTrue($items[$weighed]['is_weighted']);
        $this->assertFalse($items[$piece]['is_weighted']);
    }
}
