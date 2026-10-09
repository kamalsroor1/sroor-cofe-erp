<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class ReturnsApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $storeId;

    protected int $customerId;

    protected int $supplierId;

    protected int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);

        [$this->customerId, $this->supplierId, $this->itemId] = $this->inTenant($this->tenant, fn (): array => $this->seedParties($this->storeId));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/returns', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_returns_or_create(): void
    {
        $response = $this->getJson('/api/v1/returns', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_can_list_returns_with_summary_metrics(): void
    {
        $response = $this->getJson('/api/v1/returns', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_value', 'sales_count', 'purchase_count', 'total_count'],
            ]);
    }

    public function test_can_create_sales_return_and_increase_inventory(): void
    {
        $payload = [
            'return_type' => 'sales_return',
            'customer_id' => $this->customerId,
            'return_date' => now()->toDateString(),
            'refund_amount' => '0.000',
            'reason' => 'مرتجع عبوة زائدة من العميل',
            'items' => [
                [
                    'item_id' => $this->itemId,
                    'quantity' => 5.000,
                    'unit_price' => 480.000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/returns', $payload, $this->adminHeaders);

        // 5 * 480 = 2400
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'return_type' => 'sales_return',
                    'customer_id' => $this->customerId,
                    'total_amount' => 2400.000,
                ],
            ]);

        // Stock increased from 100 to 105
        $this->assertEquals(105.000, $this->itemStock());
    }

    public function test_can_create_purchase_return_and_deduct_inventory(): void
    {
        $payload = [
            'return_type' => 'purchase_return',
            'supplier_id' => $this->supplierId,
            'return_date' => now()->toDateString(),
            'refund_amount' => '0.000',
            'reason' => 'مرتجع بضاعة غير مطابقة للمواصفات',
            'items' => [
                [
                    'item_id' => $this->itemId,
                    'quantity' => 10.000,
                    'unit_price' => 350.000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/returns', $payload, $this->adminHeaders);

        // 10 * 350 = 3500
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'return_type' => 'purchase_return',
                    'supplier_id' => $this->supplierId,
                    'total_amount' => 3500.000,
                ],
            ]);

        // Stock decreased from 100 to 90
        $this->assertEquals(90.000, $this->itemStock());
    }

    public function test_create_return_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['return_date', 'items']);
    }

    public function test_can_show_single_return_document(): void
    {
        $payload = [
            'return_type' => 'sales_return',
            'customer_id' => $this->customerId,
            'return_date' => now()->toDateString(),
            'refund_amount' => '0.000',
            'reason' => 'مرتجع للتجربة',
            'items' => [
                [
                    'item_id' => $this->itemId,
                    'quantity' => 2.000,
                    'unit_price' => 480.000,
                ],
            ],
        ];

        $createRes = $this->postJson('/api/v1/returns', $payload, $this->adminHeaders);

        $returnId = $createRes->json('data.id');

        $response = $this->getJson('/api/v1/returns/'.$returnId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $returnId,
                    'total_amount' => 960.000,
                    'items_count' => 1,
                ],
            ]);
    }

    public function test_can_delete_return_document(): void
    {
        $payload = [
            'return_type' => 'sales_return',
            'customer_id' => $this->customerId,
            'return_date' => now()->toDateString(),
            'items' => [
                [
                    'item_id' => $this->itemId,
                    'quantity' => 1.000,
                    'unit_price' => 480.000,
                ],
            ],
        ];

        $createRes = $this->postJson('/api/v1/returns', $payload, $this->adminHeaders);

        $returnId = $createRes->json('data.id');

        $response = $this->deleteJson('/api/v1/returns/'.$returnId, [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, fn () => $this->assertSoftDeleted('returns', ['id' => $returnId]));
    }

    public function test_returns_of_another_tenant_are_invisible_and_cannot_be_deleted(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        [$foreignCustomerId, , $foreignItemId] = $this->inTenant($other, fn (): array => $this->seedParties($otherStoreId));

        $foreignReturnId = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return',
            'customer_id' => $foreignCustomerId,
            'return_date' => now()->toDateString(),
            'items' => [['item_id' => $foreignItemId, 'quantity' => 3.000, 'unit_price' => 480.000]],
        ], $this->tenantHeaders($other))->assertCreated()->json('data.id');

        $this->getJson('/api/v1/returns', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/returns/'.$foreignReturnId, $this->adminHeaders)->assertNotFound();
        $this->deleteJson('/api/v1/returns/'.$foreignReturnId, [], $this->adminHeaders)->assertNotFound();

        $this->inTenant($other, function () use ($foreignReturnId, $foreignItemId): void {
            $this->assertNotSoftDeleted('returns', ['id' => $foreignReturnId]);
            $this->assertSame('103.000', (string) Item::findOrFail($foreignItemId)->current_stock);
        });
        $this->inTenant($this->tenant, fn () => $this->assertSame(0, ReturnDocument::query()->count()));
        $this->assertEquals(100.000, $this->itemStock());
    }

    private function itemStock(): float
    {
        return $this->inTenant($this->tenant, fn (): float => (float) Item::find($this->itemId)->current_stock);
    }

    /**
     * Runs inside the tenant.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedParties(int $storeId): array
    {
        $customer = Customer::create([
            'name' => 'كافيه البن العربي',
            'phone' => '01000007003',
            'balance' => '1000.000',
            'price_tier' => 'retail',
            'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'name' => 'شركة النيل للبن الأخضر',
            'phone' => '01234567890',
            'company_name' => 'النيل للاستيراد',
            'current_balance' => '5000.000',
            'is_active' => true,
        ]);

        $item = Item::create([
            'name' => 'بن برازيلي سانتوس',
            'code' => 'BN-BRZ-SAN',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '350.000',
            'selling_price' => '480.000',
            'price_retail' => '480.000',
            'price_wholesale' => '440.000',
            'current_stock' => '100.000',
            'min_stock_level' => '15.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $storeId,
            'item_id' => $item->id,
            'quantity' => '100.000',
        ]);

        return [$customer->id, $supplier->id, $item->id];
    }
}
