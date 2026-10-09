<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class PurchasesApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $storeId;

    protected int $supplierId;

    protected int $itemAId;

    protected int $itemBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);

        [$this->supplierId, $this->itemAId, $this->itemBId] = $this->inTenant($this->tenant, fn (): array => $this->seedCatalog($this->storeId));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/purchases', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_purchases_or_create(): void
    {
        $response = $this->getJson('/api/v1/purchases', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_can_list_purchases_with_pagination_and_metrics(): void
    {
        $response = $this->getJson('/api/v1/purchases', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_purchases', 'unpaid_total', 'confirmed_count'],
            ]);
    }

    public function test_can_create_purchase_invoice_and_inbound_stock(): void
    {
        $payload = [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'supplier_invoice_ref' => 'SUP-INV-9988',
            'paid_amount' => '2000.000',
            'discount_amount' => '500.000',
            'payment_method' => 'cash',
            'notes' => 'توريد بن جديد',
            'store_id' => $this->storeId,
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 50.000,
                    'unit_cost' => 360.000,
                ],
                [
                    'item_id' => $this->itemBId,
                    'quantity' => 30.000,
                    'unit_cost' => 460.000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/purchases', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'supplier_id' => $this->supplierId,
                    'status' => 'confirmed',
                    'subtotal' => 31800.000, // (50*360) + (30*460) = 18000 + 13800 = 31800
                    'net_total' => 31300.000, // 31800 - 500 = 31300
                    'paid_amount' => 2000.000,
                ],
            ]);

        $this->inTenant($this->tenant, function (): void {
            // Verify stock was incremented
            $this->assertEquals(70.000, (float) Item::find($this->itemAId)->current_stock);
            $this->assertEquals(40.000, (float) Item::find($this->itemBId)->current_stock);

            // Verify store stock incremented
            $this->assertDatabaseHas('store_stocks', [
                'store_id' => $this->storeId,
                'item_id' => $this->itemAId,
                'quantity' => '70.000',
            ]);
        });
    }

    public function test_create_purchase_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_date', 'items']);
    }

    public function test_can_view_single_purchase_with_items(): void
    {
        $payload = [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '1000.000',
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 10.000,
                    'unit_cost' => 350.000,
                ],
            ],
            'store_id' => $this->storeId,
        ];

        $createResponse = $this->postJson('/api/v1/purchases', $payload, $this->adminHeaders);

        $purchaseId = $createResponse->json('data.id');

        $response = $this->getJson('/api/v1/purchases/'.$purchaseId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $purchaseId,
                    'supplier_id' => $this->supplierId,
                    'status' => 'confirmed',
                ],
            ]);
    }

    public function test_can_cancel_purchase_and_reverse_inventory(): void
    {
        $payload = [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '0.000',
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 15.000,
                    'unit_cost' => 350.000,
                ],
            ],
            'store_id' => $this->storeId,
        ];

        $createResponse = $this->postJson('/api/v1/purchases', $payload, $this->adminHeaders);

        $purchaseId = $createResponse->json('data.id');

        // Verify stock increased from 20 to 35
        $this->assertEquals(35.000, $this->inTenant($this->tenant, fn (): float => (float) Item::find($this->itemAId)->current_stock));

        // Cancel the purchase
        $cancelResponse = $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', [
            'reason' => 'بضاعة غير مطابقة للمواصفات',
        ], $this->adminHeaders);

        $cancelResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->inTenant($this->tenant, function () use ($purchaseId): void {
            // Verify stock returned to 20
            $this->assertEquals(20.000, (float) Item::find($this->itemAId)->current_stock);

            $this->assertDatabaseHas('purchases', [
                'id' => $purchaseId,
                'status' => 'cancelled',
            ]);
        });
    }

    public function test_can_get_smart_reorder_suggestions(): void
    {
        $response = $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=14&target_cover_days=15', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'critical_count',
                    'warning_count',
                    'safe_count',
                    'total_estimated_cost',
                    'suggestions',
                ],
            ]);
    }

    public function test_purchases_of_another_tenant_are_invisible_and_cannot_be_cancelled(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        [$foreignSupplierId, $foreignItemId] = $this->inTenant($other, fn (): array => $this->seedCatalog($otherStoreId));

        $foreignPurchaseId = (int) $this->postJson('/api/v1/purchases', [
            'supplier_id' => $foreignSupplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '0.000',
            'store_id' => $otherStoreId,
            'items' => [['item_id' => $foreignItemId, 'quantity' => 4.000, 'unit_cost' => 350.000]],
        ], $this->tenantHeaders($other))->assertCreated()->json('data.id');

        $this->getJson('/api/v1/purchases', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/purchases/'.$foreignPurchaseId, $this->adminHeaders)->assertNotFound();
        $this->postJson('/api/v1/purchases/'.$foreignPurchaseId.'/cancel', ['reason' => 'اختراق'], $this->adminHeaders)
            ->assertNotFound();

        // A's purchase cannot reference B's supplier/item ids: they do not exist in A's DB.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $foreignSupplierId,
            'purchase_date' => now()->toDateString(),
            'store_id' => $this->storeId,
            'items' => [['item_id' => $foreignItemId, 'quantity' => 1.000, 'unit_cost' => 1.000]],
        ], $this->adminHeaders)->assertStatus(422);

        $this->inTenant($other, function () use ($foreignPurchaseId, $foreignItemId): void {
            $this->assertDatabaseHas('purchases', ['id' => $foreignPurchaseId, 'status' => 'confirmed']);
            $this->assertSame('24.000', (string) Item::findOrFail($foreignItemId)->current_stock);
        });
        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, Purchase::query()->count());
            $this->assertSame('20.000', (string) Item::findOrFail($this->itemAId)->current_stock);
        });
    }

    /**
     * Runs inside the tenant: supplier + two coffee items stocked in $storeId.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedCatalog(int $storeId): array
    {
        $supplier = Supplier::create([
            'name' => 'شركة البن البرازيلي',
            'company_name' => 'البن البرازيلي للاستيراد',
            'phone' => '01234567890',
            'balance' => '0.000',
            'is_active' => true,
        ]);

        $itemA = Item::create([
            'name' => 'بن برازيلي سانتوس',
            'code' => 'BN-BR-01',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '350.000',
            'selling_price' => '450.000',
            'current_stock' => '20.000',
            'min_stock' => '10.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $storeId,
            'item_id' => $itemA->id,
            'quantity' => '20.000',
        ]);

        $itemB = Item::create([
            'name' => 'بن كولومبي سوبريمو',
            'code' => 'BN-COL-01',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '450.000',
            'selling_price' => '600.000',
            'current_stock' => '10.000',
            'min_stock' => '5.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $storeId,
            'item_id' => $itemB->id,
            'quantity' => '10.000',
        ]);

        return [$supplier->id, $itemA->id, $itemB->id];
    }
}
