<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Controllers\InvoicePrintController;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TenantTestCase;

final class InvoiceEntrySequenceApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private User $adminUser;

    private string $adminToken;

    private User $unauthorizedUser;

    private string $unauthorizedToken;

    private Store $store;

    private Customer $customer;

    private Item $itemA;

    private Item $itemB;

    private Item $itemC;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->useTenantForTest($this->tenant);

        $this->store = $this->adoptMainStore([
            'name' => 'فرع الكافيه الرئيسي',
            'code' => 'POS-SEQ-MAIN',
            'type' => 'retail',
            'is_main' => true,
            'is_active' => true,
        ]);

        $adminRole = Role::findByName('admin');

        $this->adminUser = User::factory()->create([
            'name' => 'مدير النظام',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->adminUser->assignRole($adminRole);
        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;

        $this->unauthorizedUser = User::factory()->create([
            'name' => 'مستخدم بدون صلاحيات',
            'phone' => '01000000099',
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->unauthorizedToken = $this->unauthorizedUser->createToken('unauth-token')->plainTextToken;

        $this->customer = Customer::create([
            'name' => 'عميل تجريبي',
            'phone' => '01000007009',
            'current_balance' => '0.000',
            'price_tier' => 'retail',
            'is_active' => true,
        ]);

        $this->itemA = $this->createSeededItem('صنف أول A', 'SEQ-ITM-A', '20.000');
        $this->itemB = $this->createSeededItem('صنف ثاني B', 'SEQ-ITM-B', '30.000');
        $this->itemC = $this->createSeededItem('صنف ثالث C', 'SEQ-ITM-C', '40.000');
    }

    private function createSeededItem(string $name, string $code, string $price): Item
    {
        $item = Item::create([
            'name' => $name,
            'code' => $code,
            'category' => 'مشروبات',
            'unit' => 'قطعة',
            'cost_price' => '10.000',
            'selling_price' => $price,
            'current_stock' => '100.000',
            'min_stock_level' => '5.000',
            'is_active' => true,
        ]);

        StoreStock::create([
            'store_id' => $this->store->id,
            'item_id' => $item->id,
            'quantity' => '100.000',
        ]);

        return $item;
    }

    public function test_pos_checkout_persists_entry_sequence_and_orders_items_for_show_and_print(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Store-Id' => (string) $this->store->id,
        ])->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'paid_amount' => 90,
            'items' => [
                [
                    'item_id' => $this->itemC->id,
                    'quantity' => 1,
                    'unit_price' => 40,
                    'entry_sequence' => 5,
                ],
                [
                    'item_id' => $this->itemA->id,
                    'quantity' => 1,
                    'unit_price' => 20,
                    'entry_sequence' => 1,
                ],
                [
                    'item_id' => $this->itemB->id,
                    'quantity' => 1,
                    'unit_price' => 30,
                    'entry_sequence' => 3,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $items = $response->json('data.items');
        $this->assertCount(3, $items);

        // Returned in entry_sequence ascending order (1 -> itemA, 3 -> itemB, 5 -> itemC)
        $this->assertSame([1, 3, 5], array_column($items, 'entry_sequence'));
        $this->assertSame(
            [$this->itemA->id, $this->itemB->id, $this->itemC->id],
            array_column($items, 'item_id')
        );

        $invoiceId = (int) $response->json('data.id');

        // Verify GET /api/v1/invoices/{id} returns entry_sequence in ascending order
        $showResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Store-Id' => (string) $this->store->id,
        ])->getJson("/api/v1/invoices/{$invoiceId}");

        $showResponse->assertOk();
        $this->assertSame([1, 3, 5], array_column($showResponse->json('data.items'), 'entry_sequence'));

        // Verify thermal and A4 print views render items ordered by entry_sequence
        $this->actingAs($this->adminUser);
        $req = Request::create("/invoices/{$invoiceId}/print/thermal", 'GET');
        $req->setUserResolver(fn () => $this->adminUser);
        $thermalHtml = app(InvoicePrintController::class)->thermal($req, $invoiceId)->render();

        $posA = strpos($thermalHtml, 'صنف أول A');
        $posB = strpos($thermalHtml, 'صنف ثاني B');
        $posC = strpos($thermalHtml, 'صنف ثالث C');
        $this->assertNotFalse($posA);
        $this->assertNotFalse($posB);
        $this->assertNotFalse($posC);
        $this->assertLessThan($posB, $posA);
        $this->assertLessThan($posC, $posB);
    }

    public function test_omitted_entry_sequence_defaults_to_one_based_line_index(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Store-Id' => (string) $this->store->id,
        ])->postJson('/api/v1/invoices', [
            'customer_id' => $this->customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'paid_amount' => 50,
            'items' => [
                [
                    'item_id' => $this->itemA->id,
                    'quantity' => 1,
                    'unit_price' => 20,
                ],
                [
                    'item_id' => $this->itemB->id,
                    'quantity' => 1,
                    'unit_price' => 30,
                ],
            ],
        ]);

        $response->assertStatus(201);
        $items = $response->json('data.items');
        $this->assertSame([1, 2], array_column($items, 'entry_sequence'));
    }

    public function test_duplicate_entry_sequence_in_same_invoice_returns_422(): void
    {
        foreach (['/api/v1/pos/checkout', '/api/v1/invoices'] as $endpoint) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$this->adminToken,
                'X-Store-Id' => (string) $this->store->id,
            ])->postJson($endpoint, [
                'customer_id' => $this->customer->id,
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'paid_amount' => 50,
                'items' => [
                    [
                        'item_id' => $this->itemA->id,
                        'quantity' => 1,
                        'unit_price' => 20,
                        'entry_sequence' => 2,
                    ],
                    [
                        'item_id' => $this->itemB->id,
                        'quantity' => 1,
                        'unit_price' => 30,
                        'entry_sequence' => 2,
                    ],
                ],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['items.0.entry_sequence', 'items.1.entry_sequence']);
        }
    }

    public function test_negative_or_zero_entry_sequence_returns_422(): void
    {
        foreach ([0, -3] as $invalidSeq) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$this->adminToken,
                'X-Store-Id' => (string) $this->store->id,
            ])->postJson('/api/v1/pos/checkout', [
                'customer_id' => $this->customer->id,
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'paid_amount' => 20,
                'items' => [
                    [
                        'item_id' => $this->itemA->id,
                        'quantity' => 1,
                        'unit_price' => 20,
                        'entry_sequence' => $invalidSeq,
                    ],
                ],
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['items.0.entry_sequence']);
        }
    }

    public function test_unauthenticated_and_unauthorized_requests_are_rejected(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'paid_amount' => 20,
            'items' => [
                [
                    'item_id' => $this->itemA->id,
                    'quantity' => 1,
                    'unit_price' => 20,
                    'entry_sequence' => 1,
                ],
            ],
        ];

        $this->withHeaders(['X-Store-Id' => (string) $this->store->id])
            ->postJson('/api/v1/pos/checkout', $payload)
            ->assertStatus(401);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->unauthorizedToken,
            'X-Store-Id' => (string) $this->store->id,
        ])->postJson('/api/v1/pos/checkout', $payload)
            ->assertStatus(403);
    }

    public function test_tenant_isolation_for_invoice_entry_sequence(): void
    {
        $otherTenant = $this->createTenant();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$this->adminToken,
            'X-Store-Id' => (string) $this->store->id,
        ])->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->customer->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'paid_amount' => 20,
            'items' => [
                [
                    'item_id' => $this->itemA->id,
                    'quantity' => 1,
                    'unit_price' => 20,
                    'entry_sequence' => 4,
                ],
            ],
        ])->assertStatus(201);

        $this->inTenant($otherTenant, function (): void {
            $this->assertSame(0, Invoice::query()->count());
        });
    }
}
