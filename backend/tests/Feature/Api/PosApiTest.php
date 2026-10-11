<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class PosApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    protected string $adminToken;

    protected User $unauthorizedUser;

    protected string $unauthorizedToken;

    protected Store $store;

    protected Customer $customer;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixtures and DB assertions run inside the tenant; every request selects it with X-Tenant.
        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore([
            'name' => 'فرع الكافيه الرئيسي',
            'code' => 'POS-MAIN',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name' => 'مستخدم بدون صلاحيات',
            'phone' => '01000000000',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;

        $this->customer = Customer::create([
            'name' => 'عميل نقطة البيع',
            'phone' => '01000007003',
            'balance' => '0.000',
            'price_tier' => 'retail',
            'is_active' => true,
        ]);

        $this->item = Item::create([
            'name' => 'قهوة تركية كلاسيك',
            'code' => 'COF-TRK-001',
            'category' => 'مشروبات ساخنة',
            'unit' => 'كوب',
            'cost_price' => '15.000',
            'selling_price' => '35.000',
            'current_stock' => '100.000',
            'min_stock_level' => '10.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id' => $this->item->id,
            'quantity' => '100.000',
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/pos/bootstrap');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_pos_bootstrap_or_checkout(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->unauthorizedToken)
            ->getJson('/api/v1/pos/bootstrap');

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_bootstrap_pos_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/pos/bootstrap');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'items',
                    'categories',
                    'customers',
                    'default_customer',
                    'active_store',
                    'active_shift',
                    'quick_keys',
                ],
            ])
            ->assertJson(['success' => true]);
    }

    public function test_can_checkout_fast_pos_invoice_atomically(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 2.000,
                    'unit_price' => 35.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/pos/checkout', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'invoice_number',
                    'net_total',
                    'paid_amount',
                ],
                'whatsapp',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'net_total' => 70.000,
                    'paid_amount' => 70.000,
                ],
            ]);

        // Verify stock decreased from 100 to 98
        $this->assertEquals(98.000, (float) Item::find($this->item->id)->current_stock);
    }

    public function test_pos_checkout_fails_validation_on_missing_items(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/pos/checkout', [
                'customer_id' => $this->customer->id,
                'store_id' => $this->store->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_can_quick_register_customer_from_pos(): void
    {
        $payload = [
            'name' => 'عميل سريع جديد',
            'phone' => '01000007004',
            'price_tier' => 'retail',
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/pos/quick-customer', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'customer' => [
                    'name' => 'عميل سريع جديد',
                    'phone' => '01000007004',
                ],
            ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'عميل سريع جديد',
            'phone' => '01000007004',
        ]);
    }

    public function test_can_fetch_last_sold_price_for_customer_and_item(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/pos/last-price?customer_id='.$this->customer->id.'&item_id='.$this->item->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'last_price',
            ]);
    }

    public function test_pos_never_sees_or_sells_another_tenants_catalog(): void
    {
        $other = $this->createTenant(); // ends tenancy
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $foreign = $this->inTenant($other, function () use ($otherStoreId): array {
            $customer = Customer::create(['name' => 'عميل مستأجر آخر', 'current_balance' => '0.000', 'is_active' => true]);
            $item = Item::create([
                'name' => 'صنف مستأجر آخر',
                'code' => 'POS-FOREIGN',
                'cost_price' => '10.000',
                'selling_price' => '20.000',
                'current_stock' => '20.000',
                'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $otherStoreId, 'item_id' => $item->id, 'quantity' => '20.000']);

            return ['item' => (int) $item->id, 'customer' => (int) $customer->id];
        });
        $this->useTenantForTest($this->tenant);

        $bootstrap = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)->getJson('/api/v1/pos/bootstrap');
        $bootstrap->assertStatus(200);
        $this->assertStringNotContainsString('POS-FOREIGN', (string) $bootstrap->getContent());
        $this->assertStringNotContainsString('عميل مستأجر آخر', (string) $bootstrap->getContent());

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/pos/checkout', [
                'customer_id' => $foreign['customer'],
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'items' => [['item_id' => $foreign['item'], 'quantity' => 0.250, 'unit_price' => 20.000]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'items.0.item_id']);

        $this->assertSame(0, Invoice::query()->count());
        $this->inTenant($other, function () use ($foreign): void {
            $this->assertSame('20.000', (string) Item::query()->findOrFail($foreign['item'])->current_stock);
            $this->assertSame('20.000', (string) StoreStock::query()->where('item_id', $foreign['item'])->value('quantity'));
            $this->assertSame(0, Invoice::query()->count());
        });

        // This tenant's token cannot check out against the other tenant.
        $this->withHeaders(['X-Tenant' => (string) $other->getTenantKey(), 'Authorization' => 'Bearer '.$this->adminToken])
            ->postJson('/api/v1/pos/checkout', [
                'customer_id' => $foreign['customer'],
                'payment_type' => 'cash',
                'items' => [['item_id' => $foreign['item'], 'quantity' => 1.000, 'unit_price' => 20.000]],
            ])
            ->assertStatus(401);
    }
}
