<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class StockTransfersApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $sourceStoreId;

    protected int $destStoreId;

    protected int $itemAId;

    protected int $itemBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        // The harness main store is the transfer source.
        $this->sourceStoreId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);

        [$this->destStoreId, $this->itemAId, $this->itemBId] = $this->inTenant($this->tenant, fn (): array => $this->seedStock($this->sourceStoreId));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/transfers', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_or_create_transfers(): void
    {
        $response = $this->getJson('/api/v1/transfers', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_can_list_transfers_with_summary_counts(): void
    {
        $response = $this->getJson('/api/v1/transfers', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_count', 'confirmed_count', 'cancelled_count'],
            ]);
    }

    public function test_can_create_and_execute_stock_transfer(): void
    {
        $payload = [
            'from_store_id' => $this->sourceStoreId,
            'to_store_id' => $this->destStoreId,
            'transfer_date' => now()->toDateString(),
            'notes' => 'تحويل بضاعة افتتاح الفرع',
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 25.000,
                ],
                [
                    'item_id' => $this->itemBId,
                    'quantity' => 10.000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/transfers', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'from_store_id' => $this->sourceStoreId,
                    'to_store_id' => $this->destStoreId,
                    'status' => 'confirmed',
                    'items_count' => 2,
                ],
            ]);

        // Source store stock decreased: 100 - 25 = 75, 50 - 10 = 40
        $this->assertEquals(75.000, $this->storeQty($this->sourceStoreId, $this->itemAId));
        $this->assertEquals(40.000, $this->storeQty($this->sourceStoreId, $this->itemBId));

        // Destination store stock increased: 0 + 25 = 25, 0 + 10 = 10
        $this->assertEquals(25.000, $this->storeQty($this->destStoreId, $this->itemAId));
        $this->assertEquals(10.000, $this->storeQty($this->destStoreId, $this->itemBId));
    }

    public function test_create_transfer_fails_validation_on_same_source_and_dest(): void
    {
        $payload = [
            'from_store_id' => $this->sourceStoreId,
            'to_store_id' => $this->sourceStoreId,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 5.000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/transfers', $payload, $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from_store_id', 'to_store_id']);
    }

    public function test_can_show_single_stock_transfer(): void
    {
        $payload = [
            'from_store_id' => $this->sourceStoreId,
            'to_store_id' => $this->destStoreId,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 5.000,
                ],
            ],
        ];

        $createRes = $this->postJson('/api/v1/transfers', $payload, $this->adminHeaders);

        $transferId = $createRes->json('data.id');

        $response = $this->getJson('/api/v1/transfers/'.$transferId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $transferId,
                    'items_count' => 1,
                ],
            ]);
    }

    public function test_can_cancel_transfer_and_rollback_inventory(): void
    {
        $payload = [
            'from_store_id' => $this->sourceStoreId,
            'to_store_id' => $this->destStoreId,
            'transfer_date' => now()->toDateString(),
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 30.000,
                ],
            ],
        ];

        $createRes = $this->postJson('/api/v1/transfers', $payload, $this->adminHeaders);

        $transferId = $createRes->json('data.id');

        // Source = 70, Dest = 30
        $this->assertEquals(70.000, $this->storeQty($this->sourceStoreId, $this->itemAId));
        $this->assertEquals(30.000, $this->storeQty($this->destStoreId, $this->itemAId));

        // Cancel
        $cancelRes = $this->postJson('/api/v1/transfers/'.$transferId.'/cancel', [
            'reason' => 'إلغاء أمر النقل بناءً على طلب إدارة التشغيل',
        ], $this->adminHeaders);

        $cancelRes->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'status' => 'cancelled',
                    'is_cancelled' => true,
                ],
            ]);

        // Source restored to 100, Dest deducted back to 0
        $this->assertEquals(100.000, $this->storeQty($this->sourceStoreId, $this->itemAId));
        $this->assertEquals(0.000, $this->storeQty($this->destStoreId, $this->itemAId));
    }

    public function test_transfers_and_stores_of_another_tenant_are_unreachable(): void
    {
        $other = $this->createTenant();
        $otherSourceId = (int) $this->tenantStore($other)->id;
        [$otherDestId, $foreignItemId] = $this->inTenant($other, fn (): array => $this->seedStock($otherSourceId));

        $foreignTransferId = (int) $this->postJson('/api/v1/transfers', [
            'from_store_id' => $otherSourceId,
            'to_store_id' => $otherDestId,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $foreignItemId, 'quantity' => 12.000]],
        ], $this->tenantHeaders($other))->assertCreated()->json('data.id');

        $this->getJson('/api/v1/transfers', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/transfers/'.$foreignTransferId, $this->adminHeaders)->assertNotFound();
        $this->postJson('/api/v1/transfers/'.$foreignTransferId.'/cancel', ['reason' => 'اختراق'], $this->adminHeaders)
            ->assertNotFound();

        // B's store id does not exist in A: moving A's stock "into" it is rejected.
        $this->postJson('/api/v1/transfers', [
            'from_store_id' => $this->sourceStoreId,
            'to_store_id' => $otherDestId,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemAId, 'quantity' => 1.000]],
        ], $this->adminHeaders)->assertStatus(422)->assertJsonValidationErrors(['to_store_id']);

        $this->assertEquals(100.000, $this->storeQty($this->sourceStoreId, $this->itemAId));
        $this->inTenant($other, function () use ($otherSourceId, $otherDestId, $foreignItemId, $foreignTransferId): void {
            $this->assertDatabaseHas('stock_transfers', ['id' => $foreignTransferId, 'status' => 'confirmed']);
            $this->assertSame('88.000', (string) StoreStock::where('store_id', $otherSourceId)->where('item_id', $foreignItemId)->value('quantity'));
            $this->assertSame('12.000', (string) StoreStock::where('store_id', $otherDestId)->where('item_id', $foreignItemId)->value('quantity'));
        });
    }

    private function storeQty(int $storeId, int $itemId): float
    {
        return $this->inTenant(
            $this->tenant,
            fn (): float => (float) StoreStock::where('store_id', $storeId)->where('item_id', $itemId)->value('quantity'),
        );
    }

    /**
     * Runs inside the tenant: a destination branch + two items stocked in $sourceStoreId.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedStock(int $sourceStoreId): array
    {
        $dest = Store::create([
            'name' => 'فرع مدينة نصر',
            'code' => 'BR-NASR',
            'type' => 'retail',
            'is_main' => false,
            'is_active' => true,
        ]);

        $itemA = Item::create([
            'name' => 'بن كولومبي سوبريمو',
            'code' => 'BN-COL-SUP',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '420.000',
            'selling_price' => '580.000',
            'price_retail' => '580.000',
            'price_wholesale' => '540.000',
            'current_stock' => '100.000',
            'min_stock_level' => '20.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $sourceStoreId,
            'item_id' => $itemA->id,
            'quantity' => '100.000',
        ]);

        $itemB = Item::create([
            'name' => 'بن يمني مطري',
            'code' => 'BN-YEM-MAT',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '850.000',
            'selling_price' => '1100.000',
            'price_retail' => '1100.000',
            'price_wholesale' => '1000.000',
            'current_stock' => '50.000',
            'min_stock_level' => '10.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $sourceStoreId,
            'item_id' => $itemB->id,
            'quantity' => '50.000',
        ]);

        return [$dest->id, $itemA->id, $itemB->id];
    }
}
