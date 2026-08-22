<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReturnsApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;
    protected Customer $customer;
    protected Supplier $supplier;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'returns.view']);
        Permission::create(['name' => 'returns.create']);
        Permission::create(['name' => 'returns.manage']);

        $this->store = Store::create([
            'name'      => 'الفرع الرئيسي',
            'code'      => 'MAIN-001',
            'type'      => 'retail',
            'is_main'   => true,
            'is_active' => true,
        ]);

        $this->adminUser = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'password'         => Hash::make('password'),
            'is_active'        => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($role);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->customer = Customer::create([
            'name'            => 'كافيه البن العربي',
            'phone'           => '01099887766',
            'balance'         => '1000.000',
            'price_tier'      => 'retail',
            'is_active'       => true,
        ]);

        $this->supplier = Supplier::create([
            'name'            => 'شركة النيل للبن الأخضر',
            'phone'           => '01234567890',
            'company_name'    => 'النيل للاستيراد',
            'current_balance' => '5000.000',
            'is_active'       => true,
        ]);

        $this->item = Item::create([
            'name'            => 'بن برازيلي سانتوس',
            'code'            => 'BN-BRZ-SAN',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '350.000',
            'selling_price'   => '480.000',
            'price_retail'    => '480.000',
            'price_wholesale' => '440.000',
            'current_stock'   => '100.000',
            'min_stock'       => '15.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->item->id,
            'quantity' => '100.000',
        ]);
    }

    public function test_can_list_returns_with_summary_metrics(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/returns');

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
            'return_type'   => 'sales_return',
            'customer_id'   => $this->customer->id,
            'return_date'   => now()->toDateString(),
            'refund_amount' => '0.000',
            'reason'        => 'مرتجع عبوة زائدة من العميل',
            'items'         => [
                [
                    'item_id'    => $this->item->id,
                    'quantity'   => 5.000,
                    'unit_price' => 480.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/returns', $payload);

        // 5 * 480 = 2400
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'return_type'  => 'sales_return',
                    'customer_id'  => $this->customer->id,
                    'total_amount' => 2400.000,
                ],
            ]);

        // Stock increased from 100 to 105
        $this->assertEquals(105.000, (float)Item::find($this->item->id)->current_stock);
    }

    public function test_can_create_purchase_return_and_deduct_inventory(): void
    {
        $payload = [
            'return_type'   => 'purchase_return',
            'supplier_id'   => $this->supplier->id,
            'return_date'   => now()->toDateString(),
            'refund_amount' => '0.000',
            'reason'        => 'مرتجع بضاعة غير مطابقة للمواصفات',
            'items'         => [
                [
                    'item_id'    => $this->item->id,
                    'quantity'   => 10.000,
                    'unit_price' => 350.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/returns', $payload);

        // 10 * 350 = 3500
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'return_type'  => 'purchase_return',
                    'supplier_id'  => $this->supplier->id,
                    'total_amount' => 3500.000,
                ],
            ]);

        // Stock decreased from 100 to 90
        $this->assertEquals(90.000, (float)Item::find($this->item->id)->current_stock);
    }

    public function test_can_show_single_return_document(): void
    {
        $payload = [
            'return_type'   => 'sales_return',
            'customer_id'   => $this->customer->id,
            'return_date'   => now()->toDateString(),
            'refund_amount' => '0.000',
            'reason'        => 'مرتجع للتجربة',
            'items'         => [
                [
                    'item_id'    => $this->item->id,
                    'quantity'   => 2.000,
                    'unit_price' => 480.000,
                ],
            ],
        ];

        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/returns', $payload);

        $returnId = $createRes->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->getJson('/api/v1/returns/' . $returnId);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'id'           => $returnId,
                    'total_amount' => 960.000,
                    'items_count'  => 1,
                ],
            ]);
    }

    public function test_can_delete_return_document(): void
    {
        $payload = [
            'return_type'   => 'sales_return',
            'customer_id'   => $this->customer->id,
            'return_date'   => now()->toDateString(),
            'items'         => [
                [
                    'item_id'    => $this->item->id,
                    'quantity'   => 1.000,
                    'unit_price' => 480.000,
                ],
            ],
        ];

        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/returns', $payload);

        $returnId = $createRes->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->deleteJson('/api/v1/returns/' . $returnId);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSoftDeleted('returns', ['id' => $returnId]);
    }
}
