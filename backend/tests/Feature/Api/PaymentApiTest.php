<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class PaymentApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $customerId;

    protected int $supplierId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);

        [$this->customerId, $this->supplierId] = $this->inTenant($this->tenant, function (): array {
            $customer = Customer::create([
                'name' => 'عميل تجريبي للسندات',
                'phone' => '01000007002',
                'balance' => '1500.000',
                'current_balance' => '1500.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::create([
                'name' => 'مورد حبوب البن الفاخر',
                'phone' => '01000007011',
                'balance' => '5000.000',
                'current_balance' => '5000.000',
                'is_active' => true,
            ]);

            return [$customer->id, $supplier->id];
        });
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/payments', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_record_customer_receipt_or_supplier_voucher(): void
    {
        $response = $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $this->customerId,
            'amount' => 500,
        ], $this->unauthorizedHeaders);

        $response->assertStatus(403);
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Payment::query()->count()));
    }

    public function test_authenticated_user_can_list_payments_with_summary(): void
    {
        $this->inTenant($this->tenant, fn () => Payment::create([
            'payment_number' => 'PAY-TEST-001',
            'customer_id' => $this->customerId,
            'user_id' => $this->adminUser->id,
            'amount' => '500.000',
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ]));

        $response = $this->getJson('/api/v1/payments', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'summary' => [
                    'total_collections',
                    'total_disbursements',
                ],
                'data',
                'pagination' => [
                    'current_page',
                    'last_page',
                    'total',
                ],
            ]);

        $this->assertEquals(500.0, (float) $response->json('summary.total_collections'));
    }

    public function test_can_record_customer_receipt_and_update_balance(): void
    {
        $payload = [
            'customer_id' => $this->customerId,
            'amount' => 500.0,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'notes' => 'سداد دفعة نقدية',
        ];

        $response = $this->postJson('/api/v1/payments/customer-receipt', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'customer_id' => $this->customerId,
                    'amount' => '500.000',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('payments', [
            'customer_id' => $this->customerId,
            'amount' => '500.000',
        ]));
    }

    public function test_record_customer_receipt_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/payments/customer-receipt', [
            'amount' => 500,
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_can_record_supplier_voucher_and_update_balance(): void
    {
        $payload = [
            'supplier_id' => $this->supplierId,
            'amount' => 2000.0,
            'payment_method' => 'bank_transfer',
            'payment_date' => now()->toDateString(),
            'notes' => 'سداد تحويل بنكي للمورد',
        ];

        $response = $this->postJson('/api/v1/payments/supplier-voucher', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'supplier_id' => $this->supplierId,
                    'amount' => '2000.000',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('payments', [
            'supplier_id' => $this->supplierId,
            'amount' => '2000.000',
        ]));
    }

    public function test_record_supplier_voucher_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/payments/supplier-voucher', [
            'amount' => 1000,
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_payments_and_parties_of_another_tenant_are_unreachable(): void
    {
        $other = $this->createTenant();
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        [$foreignCustomerId, $foreignSupplierId] = $this->inTenant($other, function () use ($otherAdminId): array {
            $customer = Customer::create(['name' => 'عميل مستأجر آخر', 'current_balance' => '800.000', 'is_active' => true]);
            $supplier = Supplier::create(['name' => 'مورد مستأجر آخر', 'current_balance' => '900.000', 'is_active' => true]);
            Payment::create([
                'payment_number' => 'PAY-FOREIGN-001',
                'customer_id' => $customer->id,
                'user_id' => $otherAdminId,
                'amount' => '321.000',
                'payment_method' => 'cash',
                'payment_date' => now()->toDateString(),
            ]);

            return [$customer->id, $supplier->id];
        });

        $list = $this->getJson('/api/v1/payments', $this->adminHeaders)->assertOk();
        $this->assertSame(0, (int) $list->json('pagination.total'));
        $this->assertEquals(0.0, (float) $list->json('summary.total_collections'));
        $list->assertJsonMissing(['payment_number' => 'PAY-FOREIGN-001']);

        // The other tenant's parties do not exist here: `exists` validation runs on this tenant's DB.
        $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $foreignCustomerId,
            'amount' => 50.0,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $this->adminHeaders)->assertStatus(422)->assertJsonValidationErrors(['customer_id']);

        $this->postJson('/api/v1/payments/supplier-voucher', [
            'supplier_id' => $foreignSupplierId,
            'amount' => 50.0,
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $this->adminHeaders)->assertStatus(422)->assertJsonValidationErrors(['supplier_id']);

        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Payment::query()->count()));
        $this->inTenant($other, function () use ($foreignCustomerId, $foreignSupplierId): void {
            $this->assertSame(1, Payment::query()->count());
            $this->assertSame('800.000', (string) Customer::findOrFail($foreignCustomerId)->current_balance);
            $this->assertSame('900.000', (string) Supplier::findOrFail($foreignSupplierId)->current_balance);
        });
    }
}
