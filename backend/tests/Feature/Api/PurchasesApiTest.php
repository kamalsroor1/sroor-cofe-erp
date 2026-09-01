<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PurchasesApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected User $unauthorizedUser;
    protected string $unauthorizedToken;
    protected Store $store;
    protected Supplier $supplier;
    protected Item $itemA;
    protected Item $itemB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'warehouse',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name'             => 'مستخدم بدون صلاحيات',
            'phone'            => '01000000000',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;

        $this->supplier = Supplier::create([
            'name'         => 'شركة البن البرازيلي',
            'company_name' => 'البن البرازيلي للاستيراد',
            'phone'        => '01234567890',
            'balance'      => '0.000',
            'is_active'    => true,
        ]);

        $this->itemA = Item::create([
            'name'          => 'بن برازيلي سانتوس',
            'code'          => 'BN-BR-01',
            'category'      => 'coffee_beans',
            'unit'          => 'كجم',
            'cost_price'    => '350.000',
            'selling_price' => '450.000',
            'current_stock' => '20.000',
            'min_stock'     => '10.000',
            'is_active'     => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->itemA->id,
            'quantity' => '20.000',
        ]);

        $this->itemB = Item::create([
            'name'          => 'بن كولومبي سوبريمو',
            'code'          => 'BN-COL-01',
            'category'      => 'coffee_beans',
            'unit'          => 'كجم',
            'cost_price'    => '450.000',
            'selling_price' => '600.000',
            'current_stock' => '10.000',
            'min_stock'     => '5.000',
            'is_active'     => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->itemB->id,
            'quantity' => '10.000',
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/purchases');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_purchases_or_create(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->unauthorizedToken)
            ->getJson('/api/v1/purchases');

        $response->assertStatus(403);
    }

    public function test_can_list_purchases_with_pagination_and_metrics(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/purchases');

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
            'supplier_id'          => $this->supplier->id,
            'purchase_date'        => now()->toDateString(),
            'supplier_invoice_ref' => 'SUP-INV-9988',
            'paid_amount'          => '2000.000',
            'discount_amount'      => '500.000',
            'payment_method'       => 'cash',
            'notes'                => 'توريد بن جديد',
            'store_id'             => $this->store->id,
            'items'                => [
                [
                    'item_id'   => $this->itemA->id,
                    'quantity'  => 50.000,
                    'unit_cost' => 360.000,
                ],
                [
                    'item_id'   => $this->itemB->id,
                    'quantity'  => 30.000,
                    'unit_cost' => 460.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/purchases', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'supplier_id' => $this->supplier->id,
                    'status'      => 'confirmed',
                    'subtotal'    => 31800.000, // (50*360) + (30*460) = 18000 + 13800 = 31800
                    'net_total'   => 31300.000, // 31800 - 500 = 31300
                    'paid_amount' => 2000.000,
                ],
            ]);

        // Verify stock was incremented
        $this->assertEquals(70.000, (float)Item::find($this->itemA->id)->current_stock);
        $this->assertEquals(40.000, (float)Item::find($this->itemB->id)->current_stock);

        // Verify store stock incremented
        $this->assertDatabaseHas('store_stocks', [
            'store_id' => $this->store->id,
            'item_id'  => $this->itemA->id,
            'quantity' => '70.000',
        ]);
    }

    public function test_create_purchase_fails_validation_on_missing_fields(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/purchases', [
                'supplier_id' => $this->supplier->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_date', 'items']);
    }

    public function test_can_view_single_purchase_with_items(): void
    {
        $payload = [
            'supplier_id'   => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'paid_amount'   => '1000.000',
            'items'         => [
                [
                    'item_id'   => $this->itemA->id,
                    'quantity'  => 10.000,
                    'unit_cost' => 350.000,
                ],
            ],
            'store_id'      => $this->store->id,
        ];

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/purchases', $payload);

        $purchaseId = $createResponse->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/purchases/' . $purchaseId);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'          => $purchaseId,
                    'supplier_id' => $this->supplier->id,
                    'status'      => 'confirmed',
                ],
            ]);
    }

    public function test_can_cancel_purchase_and_reverse_inventory(): void
    {
        $payload = [
            'supplier_id'   => $this->supplier->id,
            'purchase_date' => now()->toDateString(),
            'paid_amount'   => '0.000',
            'items'         => [
                [
                    'item_id'   => $this->itemA->id,
                    'quantity'  => 15.000,
                    'unit_cost' => 350.000,
                ],
            ],
            'store_id'      => $this->store->id,
        ];

        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/purchases', $payload);

        $purchaseId = $createResponse->json('data.id');

        // Verify stock increased from 20 to 35
        $this->assertEquals(35.000, (float)Item::find($this->itemA->id)->current_stock);

        // Cancel the purchase
        $cancelResponse = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/purchases/' . $purchaseId . '/cancel', [
                'reason' => 'بضاعة غير مطابقة للمواصفات',
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify stock returned to 20
        $this->assertEquals(20.000, (float)Item::find($this->itemA->id)->current_stock);

        $this->assertDatabaseHas('purchases', [
            'id'     => $purchaseId,
            'status' => 'cancelled',
        ]);
    }

    public function test_can_get_smart_reorder_suggestions(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/purchases/smart-reorder?analysis_days=14&target_cover_days=15');

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
}
