<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CoffeeBlenderApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $adminToken;
    protected Store $store;
    protected Customer $customer;
    protected Item $itemCol;
    protected Item $itemEth;
    protected Item $itemBrz;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::create(['name' => 'admin']);
        Permission::create(['name' => 'items.create']);
        Permission::create(['name' => 'invoices.create']);
        Permission::create(['name' => 'pos.access']);

        $this->store = Store::create([
            'name'      => 'المحمصة والفرع الرئيسي',
            'code'      => 'ROAST-001',
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
            'name'            => 'عميل ذواقة مميز',
            'phone'           => '01055554444',
            'balance'         => '0.000',
            'price_tier'      => 'retail',
            'is_active'       => true,
        ]);

        $this->itemCol = Item::create([
            'name'            => 'بن كولومبي إكسلسو',
            'code'            => 'BN-COL-EXC',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '400.000',
            'selling_price'   => '600.000',
            'price_retail'    => '600.000',
            'price_wholesale' => '550.000',
            'current_stock'   => '50.000',
            'min_stock'       => '10.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->itemCol->id,
            'quantity' => '50.000',
        ]);

        $this->itemEth = Item::create([
            'name'            => 'بن إثيوبي سيدامو',
            'code'            => 'BN-ETH-SID',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '450.000',
            'selling_price'   => '700.000',
            'price_retail'    => '700.000',
            'price_wholesale' => '650.000',
            'current_stock'   => '30.000',
            'min_stock'       => '5.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->itemEth->id,
            'quantity' => '30.000',
        ]);

        $this->itemBrz = Item::create([
            'name'            => 'بن برازيلي فاخر',
            'code'            => 'BN-BRZ-PREM',
            'category'        => 'coffee_beans',
            'unit'            => 'كجم',
            'cost_price'      => '350.000',
            'selling_price'   => '500.000',
            'price_retail'    => '500.000',
            'price_wholesale' => '460.000',
            'current_stock'   => '40.000',
            'min_stock'       => '10.000',
            'is_active'       => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id'  => $this->itemBrz->id,
            'quantity' => '40.000',
        ]);
    }

    public function test_can_calculate_coffee_blend_cost_and_margins(): void
    {
        $payload = [
            'target_weight_grams' => 500,
            'cardamom_grams'      => 10,
            'components'          => [
                [
                    'item_id'    => $this->itemCol->id,
                    'percentage' => 50,
                    'unit_price' => 600.000,
                ],
                [
                    'item_id'    => $this->itemEth->id,
                    'percentage' => 30,
                    'unit_price' => 700.000,
                ],
                [
                    'item_id'    => $this->itemBrz->id,
                    'percentage' => 20,
                    'unit_price' => 500.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/coffee-blender/calculate', $payload);

        // 500g total:
        // Col: 250g (0.25kg) * 400 = 100 cost | 0.25 * 600 = 150 price
        // Eth: 150g (0.15kg) * 450 = 67.5 cost | 0.15 * 700 = 105 price
        // Brz: 100g (0.10kg) * 350 = 35 cost | 0.10 * 500 = 50 price
        // Cardamom: 10g * 1.5 = 15 cost | 10g * 2.5 = 25 price
        // Total Cost = 100 + 67.5 + 35 + 15 = 217.5
        // Total Price = 150 + 105 + 50 + 25 = 330.0
        // Profit = 330 - 217.5 = 112.5 (34.1% margin)

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'target_weight_grams' => 500,
                    'total_percentage'    => 100,
                    'total_cost'          => 217.5,
                    'total_price'         => 330.0,
                    'profit_amount'       => 112.5,
                ],
            ]);
    }

    public function test_can_issue_and_confirm_blender_invoice(): void
    {
        $payload = [
            'blend_name'          => 'توليفة الملوك الخاصة',
            'customer_id'         => $this->customer->id,
            'target_weight_grams' => 250,
            'roast_type'          => 'وسط',
            'grind_level'         => 'تركي ناعم',
            'cardamom_grams'      => 5,
            'notes'               => 'بدون سكر مع زيادة وش',
            'components'          => [
                [
                    'item_id'    => $this->itemCol->id,
                    'grams'      => 150,
                    'unit_price' => 600.000,
                ],
                [
                    'item_id'    => $this->itemEth->id,
                    'grams'      => 100,
                    'unit_price' => 700.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->adminToken)
            ->postJson('/api/v1/coffee-blender/invoice', $payload);

        // 150g (0.150kg) Col * 600 = 90
        // 100g (0.100kg) Eth * 700 = 70
        // Subtotal = 160.000
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data'    => [
                    'customer_id' => $this->customer->id,
                    'subtotal'    => 160.000,
                    'net_total'   => 160.000,
                    'status'      => 'confirmed',
                ],
            ]);

        // Stock deductions:
        // Col: 50.000 - 0.150 = 49.850
        // Eth: 30.000 - 0.100 = 29.900
        $this->assertEquals(49.850, (float)Item::find($this->itemCol->id)->current_stock);
        $this->assertEquals(29.900, (float)Item::find($this->itemEth->id)->current_stock);
    }
}
