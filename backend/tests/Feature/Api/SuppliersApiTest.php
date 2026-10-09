<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class SuppliersApiTest extends TenantTestCase
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
        $response = $this->getJson('/api/v1/suppliers', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_suppliers(): void
    {
        $response = $this->getJson('/api/v1/suppliers', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_authenticated_user_can_list_suppliers_with_metrics(): void
    {
        $this->inTenant($this->tenant, fn () => Supplier::create([
            'name' => 'شركة النيل للبن والمستلزمات',
            'company_name' => 'النيل للاستيراد',
            'phone' => '01000007042',
            'current_balance' => '5000.000',
            'is_active' => true,
        ]));

        $response = $this->getJson('/api/v1/suppliers', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
                'meta' => ['current_page', 'last_page', 'total'],
                'summary' => ['total_payable', 'creditors_count', 'total_suppliers'],
            ])
            ->assertJson([
                'success' => true,
                'summary' => [
                    'creditors_count' => 1,
                    'total_suppliers' => 1,
                ],
            ]);
    }

    public function test_can_create_a_new_supplier_with_opening_balance(): void
    {
        $payload = [
            'name' => 'مؤسسة البن البرازيلي',
            'company_name' => 'البن البرازيلي ش.م.م',
            'phone' => '01000007043',
            'address' => 'ميناء الإسكندرية',
            'opening_balance' => '15000.000',
            'notes' => 'مورد حبوب بن خضراء رئيسي',
        ];

        $response = $this->postJson('/api/v1/suppliers', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'مؤسسة البن البرازيلي',
                    'company_name' => 'البن البرازيلي ش.م.م',
                    'current_balance' => 15000.000,
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('suppliers', [
            'name' => 'مؤسسة البن البرازيلي',
            'company_name' => 'البن البرازيلي ش.م.م',
        ]));
    }

    public function test_create_supplier_fails_validation_on_missing_name(): void
    {
        $response = $this->postJson('/api/v1/suppliers', [
            'company_name' => 'شركة بدون اسم',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_view_single_supplier_profile(): void
    {
        $supplierId = $this->inTenant($this->tenant, fn (): int => Supplier::create([
            'name' => 'مطاحن الشرق',
            'company_name' => 'الشرق لمعدات القهوة',
            'phone' => '01000007044',
            'current_balance' => '3200.000',
            'is_active' => true,
        ])->id);

        $response = $this->getJson('/api/v1/suppliers/'.$supplierId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $supplierId,
                    'name' => 'مطاحن الشرق',
                    'current_balance' => 3200.000,
                ],
            ]);
    }

    public function test_can_update_supplier_details(): void
    {
        $supplierId = $this->inTenant($this->tenant, fn (): int => Supplier::create([
            'name' => 'شركة الأهرام',
            'company_name' => 'الأهرام للتوزيع',
            'phone' => '01000007017',
            'current_balance' => '0.000',
            'is_active' => true,
        ])->id);

        $payload = [
            'name' => 'شركة الأهرام للتجارة والتوزيع',
            'company_name' => 'مجموعة الأهرام القابضة',
            'phone' => '01000007017',
            'address' => 'مدينة نصر، القاهرة',
        ];

        $response = $this->putJson('/api/v1/suppliers/'.$supplierId, $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'شركة الأهرام للتجارة والتوزيع',
                    'company_name' => 'مجموعة الأهرام القابضة',
                    'address' => 'مدينة نصر، القاهرة',
                ],
            ]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('suppliers', [
            'id' => $supplierId,
            'name' => 'شركة الأهرام للتجارة والتوزيع',
        ]));
    }

    public function test_can_pay_supplier_and_decrease_balance(): void
    {
        $supplierId = $this->inTenant($this->tenant, function (): int {
            $supplier = Supplier::create([
                'name' => 'مورد سداد دفعة',
                'company_name' => 'الشركة الحديثة',
                'phone' => '01000007045',
                'current_balance' => '5000.000',
                'is_active' => true,
            ]);

            $this->createCreditPurchase($supplier->id, 'PUR-1000', '5000.000');

            return $supplier->id;
        });

        $payload = [
            'amount' => '2000.000',
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
            'notes' => 'سداد دفعة للمورد',
        ];

        $response = $this->postJson('/api/v1/suppliers/'.$supplierId.'/pay', $payload, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'supplier' => [
                        'id' => $supplierId,
                        'current_balance' => 3000.000, // 5000 - 2000
                    ],
                ],
            ]);

        $this->inTenant($this->tenant, function () use ($supplierId): void {
            $this->assertEquals('3000.000', (string) Supplier::findOrFail($supplierId)->current_balance);
            $this->assertDatabaseHas('payments', [
                'supplier_id' => $supplierId,
                'amount' => '2000.000',
            ]);
        });
    }

    public function test_can_generate_supplier_account_statement_ledger(): void
    {
        $supplierId = $this->inTenant($this->tenant, function (): int {
            $supplier = Supplier::create([
                'name' => 'مورد كشف حساب',
                'company_name' => 'المطاحن الكبرى',
                'phone' => '01000007012',
                'current_balance' => '4000.000',
                'is_active' => true,
            ]);

            $this->createCreditPurchase($supplier->id, 'PUR-1001', '4000.000');

            return $supplier->id;
        });

        $response = $this->getJson('/api/v1/suppliers/'.$supplierId.'/statement', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'supplier' => ['id', 'name', 'current_balance'],
                    'summary' => ['total_purchases', 'total_paid', 'current_balance', 'transactions_count'],
                    'ledger' => [
                        '*' => ['date', 'type', 'ref_number', 'debit', 'credit', 'balance_after', 'notes'],
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_purchases' => 4000.000,
                        'current_balance' => 4000.000,
                    ],
                ],
            ]);
    }

    public function test_can_toggle_supplier_active_status(): void
    {
        $supplierId = $this->inTenant($this->tenant, fn (): int => Supplier::create([
            'name' => 'مورد إيقاف',
            'company_name' => 'شركة التوقف',
            'phone' => '01000007046',
            'current_balance' => '0.000',
            'is_active' => true,
        ])->id);

        $response = $this->patchJson('/api/v1/suppliers/'.$supplierId.'/toggle-active', [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_active' => false,
                ],
            ]);

        $this->assertFalse($this->inTenant($this->tenant, fn (): bool => (bool) Supplier::findOrFail($supplierId)->is_active));
    }

    public function test_suppliers_of_another_tenant_are_invisible_and_immutable(): void
    {
        $other = $this->createTenant();
        $foreignId = $this->inTenant($other, fn (): int => Supplier::create([
            'name' => 'مورد مستأجر آخر',
            'company_name' => 'شركة خارجية',
            'phone' => '01000007047',
            'current_balance' => '7000.000',
            'is_active' => true,
        ])->id);

        $this->getJson('/api/v1/suppliers', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('summary.total_suppliers', 0)
            ->assertJsonMissing(['name' => 'مورد مستأجر آخر']);

        $this->getJson('/api/v1/suppliers/'.$foreignId, $this->adminHeaders)->assertNotFound();
        $this->putJson('/api/v1/suppliers/'.$foreignId, ['name' => 'اختراق'], $this->adminHeaders)->assertNotFound();
        $this->postJson('/api/v1/suppliers/'.$foreignId.'/pay', [
            'amount' => '500.000',
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $this->adminHeaders)->assertNotFound();

        $this->inTenant($other, function () use ($foreignId): void {
            $supplier = Supplier::findOrFail($foreignId);
            $this->assertSame('مورد مستأجر آخر', $supplier->name);
            $this->assertSame('7000.000', (string) $supplier->current_balance);
            $this->assertSame(0, Payment::query()->count());
        });
    }

    /** Runs inside the tenant. */
    private function createCreditPurchase(int $supplierId, string $number, string $total): void
    {
        Purchase::create([
            'store_id' => $this->storeId,
            'supplier_id' => $supplierId,
            'user_id' => $this->adminUser->id,
            'purchase_number' => $number,
            'purchase_date' => now(),
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
