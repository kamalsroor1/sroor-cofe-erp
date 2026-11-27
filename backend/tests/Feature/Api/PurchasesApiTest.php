<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Purchases\CreatePurchaseAction;
use App\DTOs\Purchases\PurchaseDTO;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReorderAssistantService;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

class PurchasesApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $storeId;

    protected int $supplierId;

    protected int $itemAId;

    protected int $itemBId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);

        [$this->supplierId, $this->itemAId, $this->itemBId] = $this->inTenant($this->tenant, fn (): array => $this->seedCatalog($this->storeId));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/purchases', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_purchases_or_create(): void
    {
        $response = $this->getJson('/api/v1/purchases', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_can_list_purchases_with_pagination_and_metrics(): void
    {
        $response = $this->getJson('/api/v1/purchases', $this->adminHeaders);

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
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'supplier_invoice_ref' => 'SUP-INV-9988',
            'paid_amount' => '2000.000',
            'discount_amount' => '500.000',
            'payment_method' => 'cash',
            'notes' => 'توريد بن جديد',
            'store_id' => $this->storeId,
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 50.000,
                    'unit_cost' => 360.000,
                ],
                [
                    'item_id' => $this->itemBId,
                    'quantity' => 30.000,
                    'unit_cost' => 460.000,
                ],
            ],
        ];

        $response = $this->postJson('/api/v1/purchases', $payload, $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'supplier_id' => $this->supplierId,
                    'status' => 'confirmed',
                    'subtotal' => 31800.000, // (50*360) + (30*460) = 18000 + 13800 = 31800
                    'net_total' => 31300.000, // 31800 - 500 = 31300
                    'paid_amount' => 2000.000,
                ],
            ]);

        $this->inTenant($this->tenant, function (): void {
            // Verify stock was incremented
            $this->assertEquals(70.000, (float) Item::find($this->itemAId)->current_stock);
            $this->assertEquals(40.000, (float) Item::find($this->itemBId)->current_stock);

            // Verify store stock incremented
            $this->assertDatabaseHas('store_stocks', [
                'store_id' => $this->storeId,
                'item_id' => $this->itemAId,
                'quantity' => '70.000',
            ]);
        });
    }

    public function test_create_purchase_fails_validation_on_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['purchase_date', 'items']);
    }

    public function test_can_view_single_purchase_with_items(): void
    {
        $payload = [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '1000.000',
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 10.000,
                    'unit_cost' => 350.000,
                ],
            ],
            'store_id' => $this->storeId,
        ];

        $createResponse = $this->postJson('/api/v1/purchases', $payload, $this->adminHeaders);

        $purchaseId = $createResponse->json('data.id');

        $response = $this->getJson('/api/v1/purchases/'.$purchaseId, $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $purchaseId,
                    'supplier_id' => $this->supplierId,
                    'status' => 'confirmed',
                ],
            ]);
    }

    public function test_can_cancel_purchase_and_reverse_inventory(): void
    {
        $payload = [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '0.000',
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => 15.000,
                    'unit_cost' => 350.000,
                ],
            ],
            'store_id' => $this->storeId,
        ];

        $createResponse = $this->postJson('/api/v1/purchases', $payload, $this->adminHeaders);

        $purchaseId = $createResponse->json('data.id');

        // Verify stock increased from 20 to 35
        $this->assertEquals(35.000, $this->inTenant($this->tenant, fn (): float => (float) Item::find($this->itemAId)->current_stock));

        // Cancel the purchase
        $cancelResponse = $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', [
            'reason' => 'بضاعة غير مطابقة للمواصفات',
        ], $this->adminHeaders);

        $cancelResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->inTenant($this->tenant, function () use ($purchaseId): void {
            // Verify stock returned to 20
            $this->assertEquals(20.000, (float) Item::find($this->itemAId)->current_stock);

            $this->assertDatabaseHas('purchases', [
                'id' => $purchaseId,
                'status' => 'cancelled',
            ]);
        });
    }

    public function test_can_get_smart_reorder_suggestions(): void
    {
        $response = $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=14&target_cover_days=15', $this->adminHeaders);

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

    public function test_purchases_of_another_tenant_are_invisible_and_cannot_be_cancelled(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        [$foreignSupplierId, $foreignItemId] = $this->inTenant($other, fn (): array => $this->seedCatalog($otherStoreId));

        $foreignPurchaseId = (int) $this->postJson('/api/v1/purchases', [
            'supplier_id' => $foreignSupplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '0.000',
            'store_id' => $otherStoreId,
            'items' => [['item_id' => $foreignItemId, 'quantity' => 4.000, 'unit_cost' => 350.000]],
        ], $this->tenantHeaders($other))->assertCreated()->json('data.id');

        $this->getJson('/api/v1/purchases', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
        $this->getJson('/api/v1/purchases/'.$foreignPurchaseId, $this->adminHeaders)->assertNotFound();
        $this->postJson('/api/v1/purchases/'.$foreignPurchaseId.'/cancel', ['reason' => 'اختراق'], $this->adminHeaders)
            ->assertNotFound();

        // A's purchase cannot reference B's supplier/item ids: they do not exist in A's DB.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $foreignSupplierId,
            'purchase_date' => now()->toDateString(),
            'store_id' => $this->storeId,
            'items' => [['item_id' => $foreignItemId, 'quantity' => 1.000, 'unit_cost' => 1.000]],
        ], $this->adminHeaders)->assertStatus(422);

        $this->inTenant($other, function () use ($foreignPurchaseId, $foreignItemId): void {
            $this->assertDatabaseHas('purchases', ['id' => $foreignPurchaseId, 'status' => 'confirmed']);
            $this->assertSame('24.000', (string) Item::findOrFail($foreignItemId)->current_stock);
        });
        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, Purchase::query()->count());
            $this->assertSame('20.000', (string) Item::findOrFail($this->itemAId)->current_stock);
        });
    }

    public function test_inv_261_rejects_paid_amount_exceeding_net_total(): void
    {
        $response = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '1500.000',
            'discount_amount' => '0.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '10.000', 'unit_cost' => '100.000'],
            ],
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['paid_amount']);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, Purchase::query()->count());
            $this->assertSame(0, Payment::query()->count());
            $this->assertSame('20.000', (string) Item::findOrFail($this->itemAId)->current_stock);
        });
    }

    public function test_inv_261_allows_paid_amount_equal_to_net_total(): void
    {
        $response = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '1000.000',
            'discount_amount' => '0.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '10.000', 'unit_cost' => '100.000'],
            ],
        ], $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJsonPath('data.net_total', '1000.000')
            ->assertJsonPath('data.paid_amount', '1000.000')
            ->assertJsonPath('data.remaining_amount', '0.000')
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_inv_261_accounts_for_discount_and_expense_payer_when_validating_paid_amount(): void
    {
        // Subtotal 1000 - discount 200 + supplier_account expense 50 = net 850.000; paying 850.001 fails.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '200.000',
            'paid_amount' => '850.001',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '10.000', 'unit_cost' => '100.000'],
            ],
            'additional_expenses' => [
                ['title' => 'Shipping', 'amount' => '50.000', 'paid_by' => 'supplier_account', 'allocation_method' => 'by_quantity'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paid_amount']);

        // Treasury-paid expense does not add to supplier net_total (stays 1000.000); paying 1100.000 fails.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '0.000',
            'paid_amount' => '1100.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '10.000', 'unit_cost' => '100.000'],
            ],
            'additional_expenses' => [
                ['title' => 'Shipping', 'amount' => '200.000', 'paid_by' => 'treasury_cash', 'allocation_method' => 'by_quantity'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paid_amount']);

        // Paying exactly net_total 850.000 succeeds.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '200.000',
            'paid_amount' => '850.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '10.000', 'unit_cost' => '100.000'],
            ],
            'additional_expenses' => [
                ['title' => 'Shipping', 'amount' => '50.000', 'paid_by' => 'supplier_account', 'allocation_method' => 'by_quantity'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(201)
            ->assertJsonPath('data.net_total', '850.000')
            ->assertJsonPath('data.paid_amount', '850.000')
            ->assertJsonPath('data.remaining_amount', '0.000')
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_inv_262_rejects_discount_amount_exceeding_subtotal(): void
    {
        $response = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '800.000',
            'paid_amount' => '0.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '5.000', 'unit_cost' => '100.000'],
            ],
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount_amount'])
            ->assertJsonMissingValidationErrors(['paid_amount']);

        // Even with supplier_account additional expenses, discount cannot exceed items subtotal.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '600.000',
            'paid_amount' => '0.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '5.000', 'unit_cost' => '100.000'],
            ],
            'additional_expenses' => [
                ['title' => 'Freight', 'amount' => '200.000', 'paid_by' => 'supplier_account', 'allocation_method' => 'by_quantity'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_amount']);

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, Purchase::query()->count());
            $this->assertSame('20.000', (string) Item::findOrFail($this->itemAId)->current_stock);
        });
    }

    public function test_inv_262_allows_discount_amount_equal_to_subtotal_and_handles_decimal_edge_cases(): void
    {
        // Exact equality: subtotal = 500.000, discount = 500.000 => net_total = 0.000.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '500.000',
            'paid_amount' => '0.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '5.000', 'unit_cost' => '100.000'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '500.000')
            ->assertJsonPath('data.discount_amount', '500.000')
            ->assertJsonPath('data.net_total', '0.000')
            ->assertJsonPath('data.remaining_amount', '0.000');

        // Decimal half-up edge case: 0.255 * 12.345 = 3.147975 -> 3.148.
        // 3.149 discount > 3.148 subtotal -> 422.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '3.149',
            'paid_amount' => '0.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '0.255', 'unit_cost' => '12.345'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_amount']);

        // 3.148 discount == 3.148 subtotal -> 201.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '3.148',
            'paid_amount' => '0.000',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '0.255', 'unit_cost' => '12.345'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '3.148')
            ->assertJsonPath('data.net_total', '0.000');

        // With 1.000 discount, net_total = 2.148: paid 2.149 fails, paid 2.148 succeeds.
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '1.000',
            'paid_amount' => '2.149',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '0.255', 'unit_cost' => '12.345'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paid_amount']);

        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '1.000',
            'paid_amount' => '2.148',
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '0.255', 'unit_cost' => '12.345'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(201)
            ->assertJsonPath('data.net_total', '2.148')
            ->assertJsonPath('data.paid_amount', '2.148')
            ->assertJsonPath('data.remaining_amount', '0.000')
            ->assertJsonPath('data.payment_status', 'paid');
    }

    public function test_purchase_validation_errors_are_localized_in_arabic_and_english(): void
    {
        $arDiscount = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'discount_amount' => '800.000',
            'store_id' => $this->storeId,
            'items' => [['item_id' => $this->itemAId, 'quantity' => '5.000', 'unit_cost' => '100.000']],
        ], array_merge($this->adminHeaders, ['X-Locale' => 'ar']))
            ->assertStatus(422);

        $this->assertSame(
            __('purchases.discount_exceeds_subtotal', ['discount' => '800.000', 'subtotal' => '500.000'], 'ar'),
            $arDiscount->json('errors.discount_amount.0'),
        );

        $enPaid = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '1500.000',
            'store_id' => $this->storeId,
            'items' => [['item_id' => $this->itemAId, 'quantity' => '10.000', 'unit_cost' => '100.000']],
        ], array_merge($this->adminHeaders, ['X-Locale' => 'en']))
            ->assertStatus(422);

        $this->assertSame(
            __('purchases.paid_exceeds_net_total', ['paid' => '1500.000', 'net' => '1000.000'], 'en'),
            $enPaid->json('errors.paid_amount.0'),
        );
    }

    public function test_create_purchase_action_defensively_rejects_excess_discount_and_overpayment(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->adminUser->id));
            $action = app(CreatePurchaseAction::class);

            try {
                $action->execute(PurchaseDTO::fromArray([
                    'supplier_id' => $this->supplierId,
                    'purchase_date' => now()->toDateString(),
                    'discount_amount' => '600.000',
                    'paid_amount' => '0.000',
                    'store_id' => $this->storeId,
                    'items' => [['item_id' => $this->itemAId, 'quantity' => '5.000', 'unit_cost' => '100.000']],
                ]));
                $this->fail('Expected ValidationException for discount_amount > subtotal.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('discount_amount', $e->errors());
            }

            try {
                $action->execute(PurchaseDTO::fromArray([
                    'supplier_id' => $this->supplierId,
                    'purchase_date' => now()->toDateString(),
                    'discount_amount' => '0.000',
                    'paid_amount' => '1500.000',
                    'store_id' => $this->storeId,
                    'items' => [['item_id' => $this->itemAId, 'quantity' => '10.000', 'unit_cost' => '100.000']],
                ]));
                $this->fail('Expected ValidationException for paid_amount > net_total.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('paid_amount', $e->errors());
            }

            $this->assertSame(0, Purchase::query()->count());
        });
    }

    public function test_inv_266_rejects_negative_and_over_precision_discounts_and_line_amounts(): void
    {
        $base = [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'store_id' => $this->storeId,
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '5.000', 'unit_cost' => '100.000'],
            ],
        ];

        // Exact QA defect: POST /purchases with discount: -1 must return 422 and persist nothing.
        $this->postJson('/api/v1/purchases', [...$base, 'discount' => -1], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount']);

        $this->postJson('/api/v1/purchases', [...$base, 'discount_amount' => '-0.001'], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount_amount']);

        $this->postJson('/api/v1/purchases', [...$base, 'paid_amount' => -1], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['paid_amount']);

        // `discount` alias exceeding subtotal also fails with 422 on `discount`.
        $this->postJson('/api/v1/purchases', [...$base, 'discount' => '600.000'], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount']);

        // Per-line negative fields and >3 decimal places are rejected.
        $this->postJson('/api/v1/purchases', [
            ...$base,
            'items' => [
                [
                    'item_id' => $this->itemAId,
                    'quantity' => '5.000',
                    'unit_cost' => -10,
                    'cost_price' => -5,
                    'discount' => -1,
                    'discount_amount' => -2,
                ],
            ],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'items.0.unit_cost',
                'items.0.cost_price',
                'items.0.discount',
                'items.0.discount_amount',
            ]);

        $this->postJson('/api/v1/purchases', [
            ...$base,
            'discount' => '10.1234',
            'paid_amount' => '1.1234',
            'items' => [
                ['item_id' => $this->itemAId, 'quantity' => '5.1234', 'unit_cost' => '100.1234'],
            ],
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['discount', 'paid_amount', 'items.0.quantity', 'items.0.unit_cost']);

        // Valid `discount` alias is applied cleanly.
        $this->postJson('/api/v1/purchases', [...$base, 'discount' => '100.000'], $this->adminHeaders)
            ->assertStatus(201)
            ->assertJsonPath('data.subtotal', '500.000')
            ->assertJsonPath('data.discount_amount', '100.000')
            ->assertJsonPath('data.net_total', '400.000');

        // Direct action call also rejects negative discount_amount / paid_amount.
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->adminUser->id));
            $action = app(CreatePurchaseAction::class);

            foreach ([['discount_amount' => '-1.000'], ['paid_amount' => '-1.000']] as $badField) {
                $field = array_key_first($badField);
                try {
                    $action->execute(PurchaseDTO::fromArray([
                        'supplier_id' => $this->supplierId,
                        'purchase_date' => now()->toDateString(),
                        'store_id' => $this->storeId,
                        'items' => [['item_id' => $this->itemAId, 'quantity' => '1.000', 'unit_cost' => '100.000']],
                        ...$badField,
                    ]));
                    $this->fail("Expected ValidationException for negative {$field}.");
                } catch (ValidationException $e) {
                    $this->assertArrayHasKey($field, $e->errors());
                }
            }
        });
    }

    public function test_inv_312_smart_reorder_validates_analysis_and_cover_days_and_guards_division(): void
    {
        // Invalid values: 0, negative, non-numeric, above max (365).
        $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=0', $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['analysis_days']);

        $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=-5', $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['analysis_days']);

        $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=abc', $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['analysis_days']);

        $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=366', $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['analysis_days']);

        $this->getJson('/api/v1/purchases/smart-reorder?target_cover_days=0', $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_cover_days']);

        $this->getJson('/api/v1/purchases/smart-reorder?target_cover_days=-3', $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_cover_days']);

        $this->getJson('/api/v1/purchases/smart-reorder?target_cover_days=xyz', $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['target_cover_days']);

        // Valid boundaries (1, 14, 30, 365).
        $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=1&target_cover_days=1', $this->adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=30&target_cover_days=365', $this->adminHeaders)
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        // Unauthorized user without purchases.view gets 403 from SmartReorderRequest::authorize().
        $this->getJson('/api/v1/purchases/smart-reorder?analysis_days=14', $this->unauthorizedHeaders)
            ->assertStatus(403);

        // Direct service call with 0 / negative days clamps to 1 and never throws DivisionByZeroError.
        $this->inTenant($this->tenant, function (): void {
            $result = app(ReorderAssistantService::class)->getReorderSuggestions(
                storeId: $this->storeId,
                analysisDays: 0,
                targetCoverDays: 0,
            );
            $this->assertSame(1, $result['analysis_days']);
            $this->assertSame(1, $result['target_cover_days']);
        });
    }

    /**
     * Runs inside the tenant: supplier + two coffee items stocked in $storeId.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedCatalog(int $storeId): array
    {
        $supplier = Supplier::create([
            'name' => 'شركة البن البرازيلي',
            'company_name' => 'البن البرازيلي للاستيراد',
            'phone' => '01234567890',
            'balance' => '0.000',
            'is_active' => true,
        ]);

        $itemA = Item::create([
            'name' => 'بن برازيلي سانتوس',
            'code' => 'BN-BR-01',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '350.000',
            'selling_price' => '450.000',
            'current_stock' => '20.000',
            'min_stock' => '10.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $storeId,
            'item_id' => $itemA->id,
            'quantity' => '20.000',
        ]);

        $itemB = Item::create([
            'name' => 'بن كولومبي سوبريمو',
            'code' => 'BN-COL-01',
            'category' => 'coffee_beans',
            'unit' => 'كجم',
            'cost_price' => '450.000',
            'selling_price' => '600.000',
            'current_stock' => '10.000',
            'min_stock' => '5.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $storeId,
            'item_id' => $itemB->id,
            'quantity' => '10.000',
        ]);

        return [$supplier->id, $itemA->id, $itemB->id];
    }
}
