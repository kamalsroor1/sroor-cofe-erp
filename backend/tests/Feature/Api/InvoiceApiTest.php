<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

class InvoiceApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    protected string $adminToken;

    protected User $unauthorizedUser;

    protected string $unauthorizedToken;

    protected Store $mainStore;

    protected Store $branchStore;

    protected Customer $customer;

    protected Item $itemA;

    protected Item $itemB;

    protected CashShift $shift;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixtures and DB assertions run inside the tenant; every request selects it with X-Tenant.
        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->mainStore = $this->adoptMainStore([
            'name' => 'المحمصة الرئيسية',
            'code' => 'MAIN-001',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->branchStore = Store::create([
            'name' => 'فرع المعادي',
            'code' => 'MAADI-001',
            'type' => 'branch',
            'is_main' => false,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
            'pos_pin' => Hash::make('1234'),
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('test-spa')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name' => 'مستخدم بدون صلاحيات',
            'phone' => '01000000000',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;

        $this->customer = Customer::create([
            'name' => 'كافيه الأهرام',
            'phone' => '01098765432',
            'balance' => '0.000',
            'current_balance' => '0.000',
            'price_tier' => 'retail',
            'is_active' => true,
        ]);

        $this->itemA = Item::create([
            'name' => 'بن كولومبي وسط',
            'code' => 'BN-COL-MED',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '400.000',
            'selling_price' => '550.000',
            'price_retail' => '550.000',
            'price_wholesale' => '500.000',
            'current_stock' => '50.000',
            'min_stock' => '10.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $this->mainStore->id,
            'item_id' => $this->itemA->id,
            'quantity' => '50.000',
        ]);

        $this->itemB = Item::create([
            'name' => 'بن حبشي إسبريسو',
            'code' => 'BN-ETH-ESP',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '450.000',
            'selling_price' => '650.000',
            'price_retail' => '650.000',
            'price_wholesale' => '600.000',
            'current_stock' => '30.000',
            'min_stock' => '5.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $this->mainStore->id,
            'item_id' => $this->itemB->id,
            'quantity' => '30.000',
        ]);

        $this->shift = CashShift::create([
            'user_id' => $this->adminUser->id,
            'store_id' => $this->mainStore->id,
            'shift_number' => 'SHF-260821-001',
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash_balance' => '500.000',
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/invoices');
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_cancel_invoice(): void
    {
        $invoice = Invoice::create([
            'invoice_number' => 'INV-100',
            'store_id' => $this->mainStore->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->adminUser->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '550.000',
            'net_total' => '550.000',
            'paid_amount' => '550.000',
            'remaining_amount' => '0.000',
            'status' => 'confirmed',
            'payment_type' => 'cash',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->unauthorizedToken)
            ->postJson('/api/v1/invoices/'.$invoice->id.'/cancel', [
                'reason' => 'غير مصرح به',
            ]);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_list_sales_invoices_with_summary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/invoices');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_count', 'total_sales', 'total_paid', 'total_due'],
            ]);
    }

    public function test_invoices_summary_returns_precise_scale3_decimal_strings(): void
    {
        Invoice::create([
            'invoice_number' => 'INV-PRECISE-1',
            'store_id' => $this->mainStore->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->adminUser->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '0.100',
            'net_total' => '0.100',
            'paid_amount' => '0.040',
            'remaining_amount' => '0.060',
            'status' => 'confirmed',
            'payment_type' => 'partial',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-PRECISE-2',
            'store_id' => $this->mainStore->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->adminUser->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '0.200',
            'net_total' => '0.200',
            'paid_amount' => '0.160',
            'remaining_amount' => '0.040',
            'status' => 'confirmed',
            'payment_type' => 'partial',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-PRECISE-CANCELLED',
            'store_id' => $this->mainStore->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->adminUser->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '500.000',
            'net_total' => '500.000',
            'paid_amount' => '500.000',
            'remaining_amount' => '0.000',
            'status' => 'cancelled',
            'payment_type' => 'cash',
        ]);

        Invoice::create([
            'invoice_number' => 'INV-PRECISE-BRANCH',
            'store_id' => $this->branchStore->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->adminUser->id,
            'invoice_date' => now()->toDateString(),
            'subtotal' => '999.000',
            'net_total' => '999.000',
            'paid_amount' => '999.000',
            'remaining_amount' => '0.000',
            'status' => 'confirmed',
            'payment_type' => 'cash',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Store-Id' => (string) $this->mainStore->id,
        ])->getJson('/api/v1/invoices');

        $response->assertStatus(200);

        $summary = $response->json('summary');
        $this->assertIsInt($summary['total_count']);
        $this->assertSame(3, $summary['total_count']);

        $this->assertIsString($summary['total_sales']);
        $this->assertSame('0.300', $summary['total_sales']);

        $this->assertIsString($summary['total_paid']);
        $this->assertSame('0.200', $summary['total_paid']);

        $this->assertIsString($summary['total_due']);
        $this->assertSame('0.100', $summary['total_due']);
    }

    public function test_can_create_sales_invoice_and_deduct_inventory(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'store_id' => $this->mainStore->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '50.000',
            'items' => [
                [
                    'item_id' => $this->itemA->id,
                    'quantity' => 5.000,
                    'unit_price' => 550.000,
                ],
                [
                    'item_id' => $this->itemB->id,
                    'quantity' => 2.000,
                    'unit_price' => 650.000,
                ],
            ],
        ];

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/invoices', $payload);

        // Subtotal = (5 * 550) + (2 * 650) = 2750 + 1300 = 4050
        // Net Total = 4050 - 50 = 4000
        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_id' => $this->customer->id,
                    'subtotal' => 4050.000,
                    'net_total' => 4000.000,
                    'paid_amount' => 4000.000,
                    'remaining_amount' => 0.000,
                    'status' => 'confirmed',
                ],
            ]);

        // Stock deduction check
        $this->assertEquals(45.000, (float) Item::find($this->itemA->id)->current_stock);
        $this->assertEquals(28.000, (float) Item::find($this->itemB->id)->current_stock);
    }

    public function test_invoices_and_payments_get_distinct_consecutive_locked_numbers(): void
    {
        $other = Customer::create(['name' => 'عميل ثاني', 'balance' => '0.000', 'current_balance' => '0.000', 'price_tier' => 'retail', 'is_active' => true]);

        $numbers = [];
        foreach ([$this->customer->id, $other->id, $this->customer->id] as $customerId) {
            $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
                ->postJson('/api/v1/invoices', [
                    'customer_id' => $customerId,
                    'store_id' => $this->mainStore->id,
                    'payment_type' => 'cash',
                    'payment_method' => 'cash',
                    'discount_type' => 'fixed',
                    'discount_value' => '0.000',
                    'items' => [['item_id' => $this->itemA->id, 'quantity' => '1.000', 'unit_price' => '550.000']],
                ])->assertStatus(201);

            $numbers[] = (string) $response->json('data.invoice_number');
        }

        $date = str_replace('-', '', app(TenantClock::class)->businessDate());
        $this->assertSame(
            ["INV-MAIN001-{$date}-0001", "INV-MAIN001-{$date}-0002", "INV-MAIN001-{$date}-0003"],
            $numbers,
        );

        // One cash voucher per sale, numbered by the day's locked PAY-INV sequence (no random suffix).
        $this->assertSame(
            ["PAY-INV-{$date}-000001", "PAY-INV-{$date}-000002", "PAY-INV-{$date}-000003"],
            Payment::query()->where('payment_number', 'like', 'PAY-INV-%')->orderBy('id')->pluck('payment_number')->all(),
        );
    }

    public function test_create_sales_invoice_fails_validation_on_missing_items(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $this->customer->id,
                'payment_type' => 'cash',
                'items' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }

    public function test_can_show_invoice_with_whatsapp_payload(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'store_id' => $this->mainStore->id,
            'payment_type' => 'cash',
            'items' => [
                [
                    'item_id' => $this->itemA->id,
                    'quantity' => 1.000,
                    'unit_price' => 550.000,
                ],
            ],
        ];

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/invoices', $payload);

        $invoiceId = $createResponse->json('data.id');

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/invoices/'.$invoiceId);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'invoice_number',
                    'customer_name',
                    'items',
                ],
                'whatsapp' => [
                    'phone',
                    'clean_phone',
                    'message_text',
                    'whatsapp_url',
                ],
            ]);
    }

    public function test_can_cancel_sales_invoice_and_restore_inventory(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'store_id' => $this->mainStore->id,
            'payment_type' => 'cash',
            'items' => [
                [
                    'item_id' => $this->itemA->id,
                    'quantity' => 10.000,
                    'unit_price' => 550.000,
                ],
            ],
        ];

        $createResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/invoices', $payload);

        $invoiceId = $createResponse->json('data.id');

        // Stock decreased from 50 to 40
        $this->assertEquals(40.000, (float) Item::find($this->itemA->id)->current_stock);

        // Cancel
        $cancelResponse = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', [
                'reason' => 'إلغاء الطلب من العميل',
                'override' => ['user_id' => $this->adminUser->id, 'pin' => '1234'],
            ]);

        $cancelResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        // Stock restored back to 50
        $this->assertEquals(50.000, (float) Item::find($this->itemA->id)->current_stock);
    }

    public function test_invoices_of_another_tenant_are_invisible_and_immutable(): void
    {
        $other = $this->createTenant(); // ends tenancy
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        $foreign = $this->inTenant($other, function () use ($otherStoreId, $otherAdminId): array {
            $customer = Customer::create(['name' => 'عميل مستأجر آخر', 'current_balance' => '0.000', 'is_active' => true]);
            $item = Item::create([
                'name' => 'بن مستأجر آخر',
                'code' => 'BN-FOREIGN',
                'cost_price' => '100.000',
                'selling_price' => '200.000',
                'current_stock' => '20.000',
                'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $otherStoreId, 'item_id' => $item->id, 'quantity' => '20.000']);
            $invoice = Invoice::create([
                'invoice_number' => 'INV-FOREIGN-0001',
                'store_id' => $otherStoreId,
                'customer_id' => $customer->id,
                'user_id' => $otherAdminId,
                'invoice_date' => now()->toDateString(),
                'subtotal' => '400.000',
                'net_total' => '400.000',
                'paid_amount' => '400.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
                'payment_type' => 'cash',
            ]);

            return ['invoice' => (int) $invoice->id, 'item' => (int) $item->id, 'customer' => (int) $customer->id];
        });
        $this->useTenantForTest($this->tenant);

        $list = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)->getJson('/api/v1/invoices');
        $list->assertStatus(200);
        $this->assertStringNotContainsString('INV-FOREIGN-0001', (string) $list->getContent());

        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->getJson('/api/v1/invoices/'.$foreign['invoice'])
            ->assertStatus(404);

        $cancel = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/invoices/'.$foreign['invoice'].'/cancel', ['reason' => 'محاولة إلغاء فاتورة مستأجر آخر']);
        $this->assertContains($cancel->status(), [404, 422]);

        // Selling the other tenant's item to its customer fails validation; nothing moves.
        $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/invoices', [
                'customer_id' => $foreign['customer'],
                'payment_type' => 'cash',
                'items' => [['item_id' => $foreign['item'], 'quantity' => 1.000, 'unit_price' => 200.000]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id', 'items.0.item_id']);

        $this->assertSame(0, Invoice::query()->count());
        $this->inTenant($other, function () use ($foreign): void {
            $this->assertSame('confirmed', Invoice::query()->findOrFail($foreign['invoice'])->status);
            $this->assertSame('20.000', (string) Item::query()->findOrFail($foreign['item'])->current_stock);
        });
    }
}
