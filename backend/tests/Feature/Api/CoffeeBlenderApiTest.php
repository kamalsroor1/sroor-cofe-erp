<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class CoffeeBlenderApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected Store $store;

    protected Customer $customer;

    protected Item $itemCol;

    protected Item $itemEth;

    protected Item $itemBrz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->store = $this->tenantStore($this->tenant);

        $this->adminUser = $this->createTenantUser($this->tenant, 'admin', attributes: [
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminHeaders = $this->tenantHeaders($this->tenant, $this->adminUser);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: [
            'name' => 'مستخدم بدون صلاحيات',
            'phone' => '01000000000',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);

        $this->inTenant($this->tenant, function (): void {
            $this->customer = Customer::create([
                'name' => 'عميل ذواقة مميز',
                'phone' => '01000007004',
                'balance' => '0.000',
                'price_tier' => 'retail',
                'is_active' => true,
            ]);

            $this->itemCol = Item::create([
                'name' => 'بن كولومبي إكسلسو',
                'code' => 'BN-COL-EXC',
                'category' => 'coffee_beans',
                'unit' => 'كجم',
                'cost_price' => '400.000',
                'selling_price' => '600.000',
                'price_retail' => '600.000',
                'price_wholesale' => '550.000',
                'current_stock' => '50.000',
                'min_stock' => '10.000',
                'is_active' => true,
            ]);

            StoreStock::create([
                'store_id' => $this->store->id,
                'item_id' => $this->itemCol->id,
                'quantity' => '50.000',
            ]);

            $this->itemEth = Item::create([
                'name' => 'بن إثيوبي سيدامو',
                'code' => 'BN-ETH-SID',
                'category' => 'coffee_beans',
                'unit' => 'كجم',
                'cost_price' => '450.000',
                'selling_price' => '700.000',
                'price_retail' => '700.000',
                'price_wholesale' => '650.000',
                'current_stock' => '30.000',
                'min_stock' => '5.000',
                'is_active' => true,
            ]);

            StoreStock::create([
                'store_id' => $this->store->id,
                'item_id' => $this->itemEth->id,
                'quantity' => '30.000',
            ]);

            $this->itemBrz = Item::create([
                'name' => 'بن برازيلي فاخر',
                'code' => 'BN-BRZ-PREM',
                'category' => 'coffee_beans',
                'unit' => 'كجم',
                'cost_price' => '350.000',
                'selling_price' => '500.000',
                'price_retail' => '500.000',
                'price_wholesale' => '460.000',
                'current_stock' => '40.000',
                'min_stock' => '10.000',
                'is_active' => true,
            ]);

            StoreStock::create([
                'store_id' => $this->store->id,
                'item_id' => $this->itemBrz->id,
                'quantity' => '40.000',
            ]);

            ActivityLog::query()->delete();
        });
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/api/v1/coffee-blender/calculate', [], $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_user_without_permission_is_forbidden(): void
    {
        $response = $this->withHeaders($this->unauthorizedHeaders)
            ->postJson('/api/v1/coffee-blender/calculate', [
                'components' => [
                    ['item_id' => $this->itemCol->id, 'percentage' => 100],
                ],
            ]);

        $response->assertStatus(403);
    }

    public function test_can_calculate_coffee_blend_cost_and_margins(): void
    {
        $payload = [
            'target_weight_grams' => 500,
            'cardamom_grams' => 10,
            'components' => [
                [
                    'item_id' => $this->itemCol->id,
                    'percentage' => 50,
                    'unit_price' => 600.000,
                ],
                [
                    'item_id' => $this->itemEth->id,
                    'percentage' => 30,
                    'unit_price' => 700.000,
                ],
                [
                    'item_id' => $this->itemBrz->id,
                    'percentage' => 20,
                    'unit_price' => 500.000,
                ],
            ],
        ];

        $response = $this->withHeaders($this->adminHeaders)
            ->postJson('/api/v1/coffee-blender/calculate', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'target_weight_grams' => 500,
                    'total_percentage' => 100,
                    'total_cost' => 217.5,
                    'total_price' => 330.0,
                    'profit_amount' => 112.5,
                ],
            ]);
    }

    public function test_calculate_validation_fails_on_empty_components(): void
    {
        $response = $this->withHeaders($this->adminHeaders)
            ->postJson('/api/v1/coffee-blender/calculate', [
                'components' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['components']);
    }

    public function test_can_issue_and_confirm_blender_invoice(): void
    {
        $payload = [
            'blend_name' => 'توليفة الملوك الخاصة',
            'customer_id' => $this->customer->id,
            'target_weight_grams' => 250,
            'roast_type' => 'وسط',
            'grind_level' => 'تركي ناعم',
            'cardamom_grams' => 5,
            'notes' => 'بدون سكر مع زيادة وش',
            'components' => [
                [
                    'item_id' => $this->itemCol->id,
                    'grams' => 150,
                    'unit_price' => 600.000,
                ],
                [
                    'item_id' => $this->itemEth->id,
                    'grams' => 100,
                    'unit_price' => 700.000,
                ],
            ],
        ];

        $response = $this->withHeaders($this->adminHeaders)
            ->postJson('/api/v1/coffee-blender/invoice', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_id' => $this->customer->id,
                    'subtotal' => 160.000,
                    'net_total' => 160.000,
                    'status' => 'confirmed',
                ],
            ]);

        // Verify stock deductions
        $this->inTenant($this->tenant, function (): void {
            $this->assertEquals(49.850, (float) Item::find($this->itemCol->id)->current_stock);
            $this->assertEquals(29.900, (float) Item::find($this->itemEth->id)->current_stock);
            $this->assertSame('49.850', (string) Item::findOrFail($this->itemCol->id)->current_stock);
            $this->assertSame('29.900', (string) Item::findOrFail($this->itemEth->id)->current_stock);
        });
    }

    public function test_blender_cannot_use_or_touch_another_tenants_items(): void
    {
        $other = $this->createTenant();
        $foreign = $this->inTenant($other, function (): array {
            $item = Item::create([
                'name' => 'بن مستأجر آخر',
                'code' => 'BN-FOREIGN',
                'category' => 'coffee_beans',
                'unit' => 'كجم',
                'cost_price' => '100.000',
                'selling_price' => '200.000',
                'current_stock' => '20.000',
                'is_active' => true,
            ]);
            $customer = Customer::create(['name' => 'عميل مستأجر آخر', 'current_balance' => '0.000', 'is_active' => true]);

            return ['item' => (int) $item->id, 'customer' => (int) $customer->id];
        });

        $this->postJson('/api/v1/coffee-blender/calculate', [
            'components' => [['item_id' => $foreign['item'], 'percentage' => 100]],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['components.0.item_id']);

        $this->postJson('/api/v1/coffee-blender/invoice', [
            'blend_name' => 'توليفة مسروقة',
            'customer_id' => $foreign['customer'],
            'target_weight_grams' => 250,
            'components' => [['item_id' => $foreign['item'], 'grams' => 250, 'unit_price' => 200.000]],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'components.0.item_id']);

        $this->inTenant($other, function () use ($foreign): void {
            $this->assertSame('20.000', (string) Item::findOrFail($foreign['item'])->current_stock);
            $this->assertDatabaseCount('invoices', 0);
        });
        $this->inTenant($this->tenant, fn () => $this->assertDatabaseCount('invoices', 0));
    }

    public function test_create_invoice_validation_fails_on_missing_blend_name_or_empty_components(): void
    {
        $response = $this->withHeaders($this->adminHeaders)
            ->postJson('/api/v1/coffee-blender/invoice', [
                'customer_id' => $this->customer->id,
                'components' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['blend_name', 'components']);
    }
}
