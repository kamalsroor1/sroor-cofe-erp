<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\DTOs\Items\ItemDTO;
use App\Models\Item;
use App\Models\Tenant;
use Tests\TenantTestCase;

/** R3: a missing min_selling_price must never default to the cost price. */
final class ItemMinSellingPriceDefaultTest extends TenantTestCase
{
    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'صنف اختبار',
            'unit' => 'كجم',
            'cost_price' => '150.000',
            'selling_price' => '200.000',
        ], $overrides);
    }

    private function storedMin(Tenant $tenant, int $id): string
    {
        return $this->inTenant($tenant, fn (): string => (string) Item::query()->findOrFail($id)->min_selling_price);
    }

    public function test_dto_leaves_min_price_null_when_key_is_missing(): void
    {
        $dto = ItemDTO::fromArray(['name' => 'x', 'cost_price' => '150.000']);

        $this->assertNull($dto->min_selling_price);
        $this->assertSame('150.000', $dto->cost_price);
    }

    public function test_dto_keeps_an_explicit_min_price(): void
    {
        $dto = ItemDTO::fromArray(['name' => 'x', 'cost_price' => '150.000', 'min_selling_price' => '170']);

        $this->assertSame('170', $dto->min_selling_price);
    }

    public function test_create_without_min_price_stores_no_floor_not_cost(): void
    {
        $tenant = $this->createTenant();

        $id = (int) $this->postJson('/api/v1/items', $this->payload(), $this->tenantHeaders($tenant))
            ->assertStatus(201)->json('data.id');

        $this->assertSame('0.000', $this->storedMin($tenant, $id));
    }

    public function test_update_without_min_price_keeps_stored_value(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);
        $id = (int) $this->postJson('/api/v1/items', $this->payload(['min_selling_price' => '180.000']), $headers)
            ->assertStatus(201)->json('data.id');

        $this->putJson('/api/v1/items/'.$id, $this->payload(['name' => 'اسم جديد']), $headers)->assertOk();
        $this->assertSame('180.000', $this->storedMin($tenant, $id));

        $this->putJson('/api/v1/items/'.$id, $this->payload(['min_selling_price' => null]), $headers)->assertOk();
        $this->assertSame('180.000', $this->storedMin($tenant, $id));

        $this->putJson('/api/v1/items/'.$id, $this->payload(['min_selling_price' => '190.000']), $headers)->assertOk();
        $this->assertSame('190.000', $this->storedMin($tenant, $id));
    }

    public function test_wholesale_accessor_falls_back_to_retail_when_no_floor(): void
    {
        $item = new Item(['selling_price' => '200.000', 'min_selling_price' => '0.000']);
        $this->assertSame('200.000', $item->price_wholesale);

        $item = new Item(['selling_price' => '200.000', 'min_selling_price' => '180.000']);
        $this->assertSame('180.000', $item->price_wholesale);
    }
}
