<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\OpeningBalance;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerBalanceService;
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
            ->assertJsonPath('data.0.price_tier', 'retail')
            ->assertJson([
                'success' => true,
                'summary' => [
                    'debtors_count' => 1,
                    'total_customers' => 1,
                ],
            ]);
    }

    /**
     * Trello #239: the customers page filter tabs must narrow the list by balance. The SPA sent
     * balance_type while the API reads debt_status, so every tab showed every customer.
     */
    public function test_debt_status_filter_returns_only_matching_balances(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Customer::create(['name' => 'عميل مدين', 'phone' => '01000007101', 'current_balance' => '625.500', 'is_active' => true]);
            Customer::create(['name' => 'عميل رصيده صفر', 'phone' => '01000007102', 'current_balance' => '0.000', 'is_active' => true]);
            Customer::create(['name' => 'عميل دائن', 'phone' => '01000007103', 'current_balance' => '-100.000', 'is_active' => true]);
        });

        $names = fn (string $status): array => collect(
            $this->getJson('/api/v1/customers?debt_status='.$status, $this->adminHeaders)->assertOk()->json('data')
        )->pluck('name')->all();

        $this->assertSame(['عميل دائن'], $names('creditor'));
        $this->assertSame(['عميل مدين'], $names('debtor'));
        $this->assertSame(['عميل رصيده صفر'], $names('zero'));
        $this->assertCount(3, $names('all'));
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

    public function test_customer_opening_balance_is_a_statement_entry_that_survives_recalculation(): void
    {
        $id = (int) $this->postJson('/api/v1/customers', [
            'name' => 'عميل رصيد افتتاحي',
            'phone' => '01000007999',
            'opening_balance' => '2500.000',
        ], $this->adminHeaders)->assertStatus(201)->json('data.id');

        $this->inTenant($this->tenant, function () use ($id): void {
            $this->assertSame(1, OpeningBalance::query()->where('party_type', 'customer')->where('party_id', $id)->count());

            $service = app(CustomerBalanceService::class);
            $this->assertSame('2500.000', $service->updateBalance($id));
            $this->assertSame('2500.000', (string) Customer::query()->findOrFail($id)->current_balance);

            $ledger = $service->getCustomerLedger(Customer::query()->findOrFail($id));
            $this->assertCount(1, $ledger['entries']);
            $this->assertSame(__('import.opening_balance_entry'), $ledger['entries'][0]['type']);
            $this->assertSame('2500.000', (string) $ledger['entries'][0]['debit']);
        });

        // No opening balance = no entry and a zero balance.
        $zero = (int) $this->postJson('/api/v1/customers', ['name' => 'بدون رصيد'], $this->adminHeaders)->assertStatus(201)->json('data.id');
        $this->inTenant($this->tenant, function () use ($zero): void {
            $this->assertSame(0, OpeningBalance::query()->where('party_id', $zero)->count());
            $this->assertSame('0.000', (string) Customer::query()->findOrFail($zero)->current_balance);
        });
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

    public function test_can_create_and_update_customer_with_wholesale_price_tier(): void
    {
        $created = $this->postJson('/api/v1/customers', [
            'name' => 'عميل جملة',
            'phone' => '01000007099',
            'price_tier' => 'wholesale',
        ], $this->adminHeaders);

        $created->assertStatus(201)
            ->assertJsonPath('data.price_tier', 'wholesale');

        $customerId = (int) $created->json('data.id');
        $this->inTenant($this->tenant, function () use ($customerId): void {
            $this->assertSame('wholesale', Customer::findOrFail($customerId)->price_tier);
        });

        // Omitting price_tier on update keeps the stored tier.
        $this->putJson('/api/v1/customers/'.$customerId, [
            'name' => 'عميل جملة محدث',
        ], $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('data.price_tier', 'wholesale');

        // Explicitly updating price_tier to retail persists the change.
        $this->putJson('/api/v1/customers/'.$customerId, [
            'name' => 'عميل تجزئة',
            'price_tier' => 'retail',
        ], $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('data.price_tier', 'retail');

        // Updating back to wholesale persists wholesale.
        $this->putJson('/api/v1/customers/'.$customerId, [
            'name' => 'عميل جملة مجدداً',
            'price_tier' => 'wholesale',
        ], $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('data.price_tier', 'wholesale');

        $this->inTenant($this->tenant, function () use ($customerId): void {
            $this->assertSame('wholesale', Customer::findOrFail($customerId)->price_tier);
        });

        // Invalid price_tier is rejected with 422.
        $this->postJson('/api/v1/customers', [
            'name' => 'عميل غير صالح',
            'price_tier' => 'vip',
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['price_tier']);
    }

    /**
     * SAL-290 (#744): customer phone must reject non-numeric values, HTML tags, and short numbers
     * with localized AR/EN 422 errors across create, update, and POS quick-create, while allowing
     * valid local/international formats and preserving GEN-183 HTML-in-name handling.
     */
    public function test_customer_phone_validation_rejects_invalid_values_and_accepts_valid_phones(): void
    {
        $existingId = (int) $this->postJson('/api/v1/customers', [
            'name' => 'عميل هاتف صالح',
            'phone' => '01012345678',
        ], $this->adminHeaders)->assertCreated()->json('data.id');

        $invalidPhones = ['abc12', '12', '<b>x</b>', '123456', str_repeat('1', 16), '-01012345678', '01012345678-', '01000000001 / abc'];

        foreach ($invalidPhones as $badPhone) {
            foreach (['ar', 'en'] as $locale) {
                $headers = [...$this->adminHeaders, 'X-Locale' => $locale];
                $expectedMsg = trans('validation.phone', [
                    'attribute' => trans('validation.attributes.phone', [], $locale),
                ], $locale);

                $this->postJson('/api/v1/customers', [
                    'name' => 'عميل تجربة',
                    'phone' => $badPhone,
                ], $headers)
                    ->assertStatus(422)
                    ->assertJsonValidationErrors(['phone'])
                    ->assertJsonPath('errors.phone.0', $expectedMsg);

                $this->putJson('/api/v1/customers/'.$existingId, [
                    'name' => 'عميل هاتف صالح',
                    'phone' => $badPhone,
                ], $headers)
                    ->assertStatus(422)
                    ->assertJsonValidationErrors(['phone'])
                    ->assertJsonPath('errors.phone.0', $expectedMsg);

                $this->postJson('/api/v1/pos/quick-customer', [
                    'name' => 'عميل سريع',
                    'phone' => $badPhone,
                ], $headers)
                    ->assertStatus(422)
                    ->assertJsonValidationErrors(['phone'])
                    ->assertJsonPath('errors.phone.0', $expectedMsg);
            }
        }

        // Valid local, formatted, international, Arabic-Indic, and empty/null phones succeed.
        foreach (['01012345678', '+201012345678', '010-1234-5678', '010 1234 5678', '+20 100 123 4567', '٠١٠١٢٣٤٥٦٧٨', "\u{200F}01012345678\u{200E}", "010\u{00A0}1234\u{00A0}5678", '01000000001 / 01100000002', '01000000001، 01100000002', null, ''] as $index => $goodPhone) {
            $this->postJson('/api/v1/customers', [
                'name' => 'عميل رقم '.$index,
                'phone' => $goodPhone,
            ], $this->adminHeaders)->assertCreated();

            $this->postJson('/api/v1/pos/quick-customer', [
                'name' => 'عميل سريع '.$index,
                'phone' => $goodPhone,
            ], $this->adminHeaders)->assertCreated();
        }

        $this->putJson('/api/v1/customers/'.$existingId, [
            'name' => 'عميل محدث',
            'phone' => '+20 100 123 4567',
        ], $this->adminHeaders)->assertOk();

        // GEN-183: HTML characters in customer name remain accepted by API (escaped on render).
        $this->postJson('/api/v1/customers', [
            'name' => '<img src=x onerror=alert(1)>',
            'phone' => '01012345678',
        ], $this->adminHeaders)->assertCreated();
    }

    /**
     * SAL-294 (#869): Arabic 422 validation errors on customers must translate credit_limit and price_tier.
     */
    public function test_arabic_validation_messages_translate_credit_limit_and_price_tier(): void
    {
        $headers = [...$this->adminHeaders, 'X-Locale' => 'ar'];

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'عميل اختبار',
            'credit_limit' => '-5',
            'price_tier' => 'vip',
        ], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['credit_limit', 'price_tier']);

        $creditErr = (string) $response->json('errors.credit_limit.0');
        $tierErr = (string) $response->json('errors.price_tier.0');

        $this->assertStringContainsString('حد الائتمان', $creditErr);
        $this->assertStringNotContainsString('credit limit', $creditErr);
        $this->assertStringContainsString('فئة السعر', $tierErr);
        $this->assertStringNotContainsString('price tier', $tierErr);
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
