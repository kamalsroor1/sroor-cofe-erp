<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class CustomersApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/customers', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_create_or_delete_customer(): void
    {
        $response = $this->postJson('/api/v1/customers', [
            'name' => 'عميل ممنوع',
        ], $this->unauthorizedHeaders);

        $response->assertStatus(403);
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Customer::query()->count()));
    }

    public function test_authenticated_user_can_list_customers_with_metrics(): void
    {
        $this->inTenant($this->tenant, fn () => Customer::create([
            'name' => 'عميل تجريبي مدين',
            'phone' => '01000007002',
            'current_balance' => '1500.000',
            'is_active' => true,
        ]));

        $response = $this->getJson('/api/v1/customers', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_debt', 'debtors_count', 'total_customers'],
            ])
            ->assertJson([
                'success' => true,
                'summary' => [
                    'debtors_count' => 1,
                    'total_customers' => 1,
                ],
            ]);
    }

    public function test_can_create_a_new_customer_with_opening_balance(): void
    {
        $payload = [
            'name' => 'مطحن الأمل للبن',
            'phone' => '01000007003',
            'address' => 'وسط البلد، القاهرة',
            'tax_number' => '123-456-789',
            'opening_balance' => '2500.000',
            'notes' => 'عميل جملة',
        ];

        $response = $this->postJson('/api/v1/customers', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'مطحن الأمل للبن',
                    'phone' => '01000007003',
                    'current_balance' => 2500.000,
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('customers', [
            'name' => 'مطحن الأمل للبن',
            'phone' => '01000007003',
        ]));
    }

    public function test_create_customer_fails_validation_on_missing_name(): void
    {
        $response = $this->postJson('/api/v1/customers', [
            'phone' => '01000007002',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_view_single_customer_profile(): void
    {
        $customerId = $this->inTenant($this->tenant, fn (): int => Customer::create([
            'name' => 'كافيه السلام',
            'phone' => '01000007018',
            'current_balance' => '750.000',
            'is_active' => true,
        ])->id);

        $response = $this->getJson('/api/v1/customers/'.$customerId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $customerId,
                    'name' => 'كافيه السلام',
                    'current_balance' => 750.000,
                ],
            ]);
    }

    public function test_can_update_customer_details(): void
    {
        $customerId = $this->inTenant($this->tenant, fn (): int => Customer::create([
            'name' => 'محل النور',
            'phone' => '01000007004',
            'current_balance' => '0.000',
            'is_active' => true,
        ])->id);

        $payload = [
            'name' => 'محل النور للقهوة الفاخرة',
            'phone' => '01000007004',
            'address' => 'ميدان التحرير',
        ];

        $response = $this->putJson('/api/v1/customers/'.$customerId, $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'محل النور للقهوة الفاخرة',
                    'address' => 'ميدان التحرير',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('customers', [
            'id' => $customerId,
            'name' => 'محل النور للقهوة الفاخرة',
        ]));
    }

    public function test_can_collect_customer_payment_and_decrease_balance(): void
    {
        $customerId = $this->inTenant($this->tenant, function (): int {
            $customer = Customer::create([
                'name' => 'عميل سداد مديونية',
                'phone' => '01000007009',
                'current_balance' => '1000.000',
                'is_active' => true,
            ]);

            $this->createCreditInvoice($customer->id, 'INV-1000', '1000.000');

            return $customer->id;
        });

        $payload = [
            'amount' => 400.000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'notes' => 'سداد دفعة نقدية',
        ];

        $response = $this->postJson('/api/v1/customers/'.$customerId.'/collect-payment', $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer' => [
                        'id' => $customerId,
                        'current_balance' => 600.000, // 1000 - 400
                    ],
                ],
            ]);

        $this->inTenant($this->tenant, function () use ($customerId): void {
            $this->assertEquals('600.000', (string) Customer::findOrFail($customerId)->current_balance);
            $this->assertDatabaseHas('payments', [
                'customer_id' => $customerId,
                'amount' => '400.000',
            ]);
        });
    }

    public function test_can_generate_customer_account_statement_ledger(): void
    {
        $customerId = $this->inTenant($this->tenant, function (): int {
            $customer = Customer::create([
                'name' => 'عميل كشف حساب',
                'phone' => '01000007007',
                'current_balance' => '1200.000',
                'is_active' => true,
            ]);

            $this->createCreditInvoice($customer->id, 'INV-1001', '1200.000');

            return $customer->id;
        });

        $response = $this->getJson('/api/v1/customers/'.$customerId.'/statement', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'customer' => ['id', 'name', 'current_balance'],
                    'summary' => ['total_debit', 'total_credit', 'current_balance', 'transactions_count'],
                    'ledger' => [
                        '*' => ['date', 'type', 'ref_number', 'debit', 'credit', 'balance_after', 'notes'],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_debit' => 1200.000,
                        'current_balance' => 1200.000,
                    ],
                ],
            ]);
    }

    public function test_can_toggle_customer_active_status(): void
    {
        $customerId = $this->inTenant($this->tenant, fn (): int => Customer::create([
            'name' => 'عميل إيقاف',
            'phone' => '01000007019',
            'current_balance' => '0.000',
            'is_active' => true,
        ])->id);

        $response = $this->patchJson('/api/v1/customers/'.$customerId.'/toggle-active', [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => false,
                ],
            ]);

        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => (bool) Customer::findOrFail($customerId)->is_active));
    }

    public function test_can_delete_customer_successfully(): void
    {
        $customerId = $this->inTenant($this->tenant, fn (): int => Customer::create([
            'name' => 'عميل للحذف',
            'phone' => '01000007020',
            'current_balance' => '0.000',
            'is_active' => true,
        ])->id);

        $response = $this->deleteJson('/api/v1/customers/'.$customerId, [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->inTenant($this->tenant, fn () => $this->assertSoftDeleted('customers', ['id' => $customerId]));
    }

    public function test_customers_of_another_tenant_are_invisible_and_immutable(): void
    {
        $other = $this->createTenant();
        $foreignId = $this->inTenant($other, fn (): int => Customer::create([
            'name' => 'عميل مستأجر آخر',
            'phone' => '01000007030',
            'current_balance' => '900.000',
            'is_active' => true,
        ])->id);

        $this->getJson('/api/v1/customers', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('summary.total_customers', 0)
            ->assertJsonMissing(['name' => 'عميل مستأجر آخر']);

        $this->getJson('/api/v1/customers/'.$foreignId, $this->adminHeaders)->assertNotFound();
        $this->putJson('/api/v1/customers/'.$foreignId, ['name' => 'اختراق'], $this->adminHeaders)->assertNotFound();
        $this->postJson('/api/v1/customers/'.$foreignId.'/collect-payment', [
            'amount' => 100.000,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $this->adminHeaders)->assertNotFound();
        $this->deleteJson('/api/v1/customers/'.$foreignId, [], $this->adminHeaders)->assertNotFound();

        $this->inTenant($other, function () use ($foreignId): void {
            $customer = Customer::findOrFail($foreignId);
            $this->assertSame('عميل مستأجر آخر', $customer->name);
            $this->assertSame('900.000', (string) $customer->current_balance);
            $this->assertSame(0, Payment::query()->count());
        });
    }

    /** Runs inside the tenant. */
    private function createCreditInvoice(int $customerId, string $number, string $total): void
    {
        Invoice::create([
            'store_id' => $this->storeId,
            'customer_id' => $customerId,
            'user_id' => $this->adminUser->id,
            'invoice_number' => $number,
            'invoice_date' => now(),
            'subtotal' => $total,
            'discount_amount' => '0.000',
            'tax_amount' => '0.000',
            'net_total' => $total,
            'paid_amount' => '0.000',
            'remaining_amount' => $total,
            'payment_type' => 'credit',
            'status' => 'confirmed',
        ]);
    }
}
