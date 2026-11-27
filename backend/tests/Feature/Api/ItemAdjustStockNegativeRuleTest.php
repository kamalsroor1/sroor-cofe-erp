<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use Tests\TenantTestCase;

/**
 * INV-061: shortage stock adjustments (`stock_adjustment_out`, `waste_out`) must obey the
 * tenant `allow_negative_stock` setting inside a locked DB transaction with bcmath.
 */
final class ItemAdjustStockNegativeRuleTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->tenantAdmin($this->tenant);
        $this->headers = $this->tenantHeaders($this->tenant);
        $this->store = $this->tenantStore($this->tenant);
    }

    public function test_shortage_adjustment_making_stock_negative_is_rejected_with_422_when_disabled(): void
    {
        $itemId = $this->inTenant($this->tenant, function (): int {
            $item = $this->createItemWithStock('10.000');

            return (int) $item->id;
        });

        $response = $this->postJson(
            "/api/v1/items/{$itemId}/adjust-stock",
            [
                'store_id' => $this->store->id,
                'movement_type' => 'stock_adjustment_out',
                'quantity' => '50.000',
                'notes' => 'Shortage exceeding stock',
            ],
            [...$this->headers, 'X-Locale' => 'ar'],
        );

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', InsufficientStockException::ERROR_CODE);
        $this->assertStringContainsString('غير كافٍ', (string) $response->json('message'));

        $enResponse = $this->postJson(
            "/api/v1/items/{$itemId}/adjust-stock",
            [
                'store_id' => $this->store->id,
                'movement_type' => 'waste_out',
                'quantity' => '10.001',
                'notes' => 'Fractional waste exceeding stock',
            ],
            [...$this->headers, 'X-Locale' => 'en'],
        );

        $enResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', InsufficientStockException::ERROR_CODE);
        $this->assertStringContainsString('Insufficient stock', (string) $enResponse->json('message'));

        $this->inTenant($this->tenant, function () use ($itemId): void {
            $this->assertSame('10.000', (string) Item::findOrFail($itemId)->current_stock);
            $this->assertSame(
                '10.000',
                (string) StoreStock::where('store_id', $this->store->id)->where('item_id', $itemId)->firstOrFail()->quantity,
            );
            $this->assertSame(0, StockMovement::where('item_id', $itemId)->count());
        });
    }

    public function test_shortage_adjustment_on_item_without_store_stock_row_is_rejected_and_rolled_back(): void
    {
        $itemId = $this->inTenant($this->tenant, function (): int {
            $item = Item::create([
                'code' => 'NO-ROW-001',
                'name' => 'No StoreStock Item',
                'unit' => 'كجم',
                'current_stock' => '0.000',
                'cost_price' => '50.000',
                'selling_price' => '80.000',
                'is_active' => true,
            ]);

            return (int) $item->id;
        });

        $this->postJson(
            "/api/v1/items/{$itemId}/adjust-stock",
            [
                'store_id' => $this->store->id,
                'movement_type' => 'stock_adjustment_out',
                'quantity' => '0.001',
            ],
            $this->headers,
        )
            ->assertStatus(422)
            ->assertJsonPath('error_code', InsufficientStockException::ERROR_CODE);

        $this->inTenant($this->tenant, function () use ($itemId): void {
            $this->assertNull(StoreStock::withTrashed()->where('store_id', $this->store->id)->where('item_id', $itemId)->first());
            $this->assertSame(0, StockMovement::where('item_id', $itemId)->count());
        });
    }

    public function test_exact_and_fractional_shortage_within_available_balance_succeeds_when_disabled(): void
    {
        $itemId = $this->inTenant($this->tenant, fn (): int => (int) $this->createItemWithStock('10.000')->id);

        $this->postJson(
            "/api/v1/items/{$itemId}/adjust-stock",
            [
                'store_id' => $this->store->id,
                'movement_type' => 'stock_adjustment_out',
                'quantity' => '0.250',
            ],
            $this->headers,
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('movement.stock_before', '10.000')
            ->assertJsonPath('movement.stock_after', '9.750');

        $this->postJson(
            "/api/v1/items/{$itemId}/adjust-stock",
            [
                'store_id' => $this->store->id,
                'movement_type' => 'waste_out',
                'quantity' => '9.750',
            ],
            $this->headers,
        )
            ->assertOk()
            ->assertJsonPath('movement.stock_before', '9.750')
            ->assertJsonPath('movement.stock_after', '0.000');

        $this->inTenant($this->tenant, function () use ($itemId): void {
            $this->assertSame('0.000', (string) Item::findOrFail($itemId)->current_stock);
            $this->assertSame(
                '0.000',
                (string) StoreStock::where('store_id', $this->store->id)->where('item_id', $itemId)->firstOrFail()->quantity,
            );
        });
    }

    public function test_shortage_adjustment_can_go_negative_when_allow_negative_stock_is_enabled(): void
    {
        $itemId = $this->inTenant($this->tenant, function (): int {
            Setting::set('allow_negative_stock', '1');

            return (int) $this->createItemWithStock('10.000')->id;
        });

        $this->postJson(
            "/api/v1/items/{$itemId}/adjust-stock",
            [
                'store_id' => $this->store->id,
                'movement_type' => 'stock_adjustment_out',
                'quantity' => '50.000',
                'notes' => 'Allowed negative adjustment',
            ],
            $this->headers,
        )
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('movement.stock_before', '10.000')
            ->assertJsonPath('movement.stock_after', '-40.000');

        $this->inTenant($this->tenant, function () use ($itemId): void {
            $this->assertSame('-40.000', (string) Item::findOrFail($itemId)->current_stock);
            $this->assertSame(
                '-40.000',
                (string) StoreStock::where('store_id', $this->store->id)->where('item_id', $itemId)->firstOrFail()->quantity,
            );
        });

        // Tenant isolation: another tenant with default setting still rejects negative adjustment.
        $otherTenant = $this->createTenant();
        $this->tenantAdmin($otherTenant);
        $otherStore = $this->tenantStore($otherTenant);
        $otherItemId = $this->inTenant($otherTenant, fn (): int => (int) $this->createItemWithStock('10.000', $otherStore->id)->id);

        $this->postJson(
            "/api/v1/items/{$otherItemId}/adjust-stock",
            [
                'store_id' => $otherStore->id,
                'movement_type' => 'stock_adjustment_out',
                'quantity' => '50.000',
            ],
            $this->tenantHeaders($otherTenant),
        )
            ->assertStatus(422)
            ->assertJsonPath('error_code', InsufficientStockException::ERROR_CODE);
    }

    private function createItemWithStock(string $qty, ?int $storeId = null): Item
    {
        $item = Item::create([
            'code' => 'ADJ-NEG-'.uniqid(),
            'name' => 'بن اختبار التسوية',
            'unit' => 'كجم',
            'current_stock' => $qty,
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'min_stock_level' => '2.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $storeId ?? $this->store->id,
            'item_id' => $item->id,
            'quantity' => $qty,
            'min_stock' => '2.000',
        ]);

        return $item;
    }
}
