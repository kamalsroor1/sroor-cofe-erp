<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PDOException;
use Tests\Concerns\SeedsStoreStock;
use Tests\TenantTestCase;

final class PosCheckoutContentionAndIsolationTest extends TenantTestCase
{
    use SeedsStoreStock;

    private Tenant $tenant;

    private Store $store;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->admin = $this->tenantAdmin($this->tenant);
        $this->store = $this->tenantStore($this->tenant);
    }

    public function test_cash_walk_in_sale_does_not_lock_customer_row_or_change_walk_in_balance(): void
    {
        [$itemId, $walkInId, $queries] = $this->inTenant($this->tenant, function (): array {
            $this->actingAs(User::findOrFail($this->admin->id));

            $walkIn = app(ResolveWalkInCustomerAction::class)->execute();
            $this->assertSame('0.000', (string) $walkIn->current_balance);

            $item = Item::create([
                'code' => 'CASH-WALKIN-01',
                'name' => 'قهوة سريعة',
                'unit' => 'قطعة',
                'cost_price' => '10.000',
                'selling_price' => '25.000',
                'current_stock' => '50.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);
            $this->stockInAllStores($item);

            $connection = DB::connection();
            $connection->flushQueryLog();
            $connection->enableQueryLog();

            try {
                $invoice = app(InvoiceService::class)->confirmInvoice([
                    'client_uuid' => (string) Str::uuid(),
                    'customer_id' => $walkIn->id,
                    'store_id' => $this->store->id,
                    'payment_type' => 'cash',
                    'payment_method' => 'cash',
                    'items' => [
                        ['item_id' => $item->id, 'quantity' => '2.000', 'unit_price' => '25.000'],
                    ],
                ]);
            } finally {
                $queries = $connection->getQueryLog();
                $connection->disableQueryLog();
            }

            $this->assertSame('50.000', (string) $invoice->net_total);
            $this->assertSame('0.000', (string) $invoice->remaining_amount);

            return [$item->id, $walkIn->id, $queries];
        });

        $customerWriteOrLockQueries = array_values(array_filter(
            $queries,
            static function (array $entry): bool {
                $sql = strtolower((string) ($entry['query'] ?? ''));

                return str_contains($sql, 'customers')
                    && (str_contains($sql, 'update') || str_contains($sql, 'for update'));
            },
        ));

        $this->assertSame([], $customerWriteOrLockQueries, 'Cash walk-in sale must not update or lock the customers row.');

        $this->inTenant($this->tenant, function () use ($walkInId, $itemId): void {
            $walkIn = Customer::query()->findOrFail($walkInId);
            $this->assertSame('0.000', (string) $walkIn->current_balance);
            $this->assertSame('48.000', (string) Item::query()->findOrFail($itemId)->current_stock);
        });
    }

    public function test_credit_and_partial_sales_update_customer_balance_exact(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->admin->id));

            $customer = Customer::create([
                'name' => 'عميل آجل منتظم',
                'phone' => '01000001234',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            $item = Item::create([
                'code' => 'CREDIT-ITEM-01',
                'name' => 'بن ممتاز',
                'unit' => 'كجم',
                'cost_price' => '80.000',
                'selling_price' => '120.000',
                'current_stock' => '50.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);
            $this->stockInAllStores($item);

            // Credit sale: 2.500 * 120.000 = 300.000 added to customer receivable balance.
            $creditInvoice = app(InvoiceService::class)->confirmInvoice([
                'client_uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'store_id' => $this->store->id,
                'payment_type' => 'credit',
                'items' => [
                    ['item_id' => $item->id, 'quantity' => '2.500', 'unit_price' => '120.000'],
                ],
            ]);

            $this->assertSame('300.000', (string) $creditInvoice->remaining_amount);
            $this->assertSame('300.000', (string) Customer::query()->findOrFail($customer->id)->current_balance);

            // Partial sale: 1.000 * 100.000 = 100.000, paid 35.250 -> remaining 64.750.
            $partialInvoice = app(InvoiceService::class)->confirmInvoice([
                'client_uuid' => (string) Str::uuid(),
                'customer_id' => $customer->id,
                'store_id' => $this->store->id,
                'payment_type' => 'partial',
                'payment_method' => 'cash',
                'paid_amount' => '35.250',
                'items' => [
                    ['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '100.000'],
                ],
            ]);

            $this->assertSame('64.750', (string) $partialInvoice->remaining_amount);
            $this->assertSame('364.750', (string) Customer::query()->findOrFail($customer->id)->current_balance);
        });
    }

    public function test_invoice_numbers_are_unique_and_sequential_across_many_sales(): void
    {
        $numbers = $this->inTenant($this->tenant, function (): array {
            $this->actingAs(User::findOrFail($this->admin->id));

            $walkIn = app(ResolveWalkInCustomerAction::class)->execute();
            $item = Item::create([
                'code' => 'SEQ-ITEM-01',
                'name' => 'صنف تسلسل',
                'unit' => 'قطعة',
                'cost_price' => '10.000',
                'selling_price' => '20.000',
                'current_stock' => '1000.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);
            $this->stockInAllStores($item);

            $invoiceNumbers = [];
            for ($i = 1; $i <= 30; $i++) {
                $invoice = app(InvoiceService::class)->confirmInvoice([
                    'client_uuid' => (string) Str::uuid(),
                    'customer_id' => $walkIn->id,
                    'store_id' => $this->store->id,
                    'payment_type' => 'cash',
                    'payment_method' => 'cash',
                    'items' => [
                        ['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '20.000'],
                    ],
                ]);
                $invoiceNumbers[] = (string) $invoice->invoice_number;
            }

            return $invoiceNumbers;
        });

        $this->assertCount(30, $numbers);
        $this->assertCount(30, array_unique($numbers));

        $suffixes = array_map(static fn (string $n): int => (int) substr($n, (int) strrpos($n, '-') + 1), $numbers);
        $this->assertSame(range(1, 30), $suffixes);

        $this->inTenant($this->tenant, function (): void {
            $paymentNumbers = Payment::query()->pluck('payment_number')->all();
            $this->assertCount(30, array_unique($paymentNumbers));
        });
    }

    public function test_stock_rows_are_locked_in_ascending_item_id_order_regardless_of_line_order(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->admin->id));

            $walkIn = app(ResolveWalkInCustomerAction::class)->execute();
            $items = [];
            for ($i = 1; $i <= 3; $i++) {
                $item = Item::create([
                    'code' => "ORD-ITEM-{$i}",
                    'name' => "صنف ترتيب {$i}",
                    'unit' => 'قطعة',
                    'cost_price' => '10.000',
                    'selling_price' => '25.000',
                    'current_stock' => '10.000',
                    'min_stock_level' => '0.000',
                    'is_active' => true,
                ]);
                $this->stockInAllStores($item);
                $items[] = $item;
            }

            [$item1, $item2, $item3] = $items;

            $connection = DB::connection();
            $connection->flushQueryLog();
            $connection->enableQueryLog();

            try {
                // Deliberately pass lines in descending + duplicate order: [3, 1, 2, 1].
                app(InvoiceService::class)->confirmInvoice([
                    'client_uuid' => (string) Str::uuid(),
                    'customer_id' => $walkIn->id,
                    'store_id' => $this->store->id,
                    'payment_type' => 'cash',
                    'payment_method' => 'cash',
                    'items' => [
                        ['item_id' => $item3->id, 'quantity' => '1.000', 'unit_price' => '25.000'],
                        ['item_id' => $item1->id, 'quantity' => '2.000', 'unit_price' => '25.000'],
                        ['item_id' => $item2->id, 'quantity' => '3.000', 'unit_price' => '25.000'],
                        ['item_id' => $item1->id, 'quantity' => '4.000', 'unit_price' => '25.000'],
                    ],
                ]);
            } finally {
                $queries = $connection->getQueryLog();
                $connection->disableQueryLog();
            }

            $itemLockQueries = array_values(array_filter(
                $queries,
                static fn (array $q): bool => str_contains(strtolower((string) ($q['query'] ?? '')), 'from "items"')
                    && str_contains(strtolower((string) ($q['query'] ?? '')), 'order by "id" asc'),
            ));
            $storeStockLockQueries = array_values(array_filter(
                $queries,
                static fn (array $q): bool => str_contains(strtolower((string) ($q['query'] ?? '')), 'from "store_stocks"')
                    && str_contains(strtolower((string) ($q['query'] ?? '')), 'order by "item_id" asc'),
            ));

            $this->assertNotEmpty($itemLockQueries);
            $this->assertNotEmpty($storeStockLockQueries);
            $this->assertSame([(int) $item1->id, (int) $item2->id, (int) $item3->id], array_map('intval', $itemLockQueries[0]['bindings']));

            // Item 1 was sold twice on the same invoice (2 + 4 = 6): remaining must be 4.000.
            $this->assertSame('4.000', (string) Item::query()->findOrFail($item1->id)->current_stock);
            $this->assertSame('4.000', (string) StoreStock::query()->where('store_id', $this->store->id)->where('item_id', $item1->id)->value('quantity'));
            $this->assertSame('7.000', (string) Item::query()->findOrFail($item2->id)->current_stock);
            $this->assertSame('9.000', (string) Item::query()->findOrFail($item3->id)->current_stock);

            // Cumulative quantity across duplicate lines must never drive stock negative (4 remaining < 3 + 2 = 5).
            $this->expectException(InsufficientStockException::class);
            app(InvoiceService::class)->confirmInvoice([
                'client_uuid' => (string) Str::uuid(),
                'customer_id' => $walkIn->id,
                'store_id' => $this->store->id,
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'items' => [
                    ['item_id' => $item1->id, 'quantity' => '3.000', 'unit_price' => '25.000'],
                    ['item_id' => $item1->id, 'quantity' => '2.000', 'unit_price' => '25.000'],
                ],
            ]);
        });
    }

    public function test_per_tenant_checkout_rate_limit_returns_429_and_isolates_other_tenants(): void
    {
        config([
            'rate_limits.pos_checkout.per_tenant_per_minute' => 2,
            'rate_limits.pos_checkout.max_concurrent_per_tenant' => 0,
        ]);

        [$itemIdA, $walkInIdA] = $this->inTenant($this->tenant, function (): array {
            $walkIn = app(ResolveWalkInCustomerAction::class)->execute();
            $item = Item::create([
                'code' => 'RL-A-01',
                'name' => 'صنف حد المعدل أ',
                'unit' => 'قطعة',
                'cost_price' => '10.000',
                'selling_price' => '25.000',
                'current_stock' => '50.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);
            $this->stockInAllStores($item);

            return [$item->id, $walkIn->id];
        });

        $headersA = $this->tenantHeaders($this->tenant, $this->admin);
        RateLimiter::clear('pos-checkout|t:'.$this->tenant->getTenantKey());

        $payloadA = fn (): array => [
            'client_uuid' => (string) Str::uuid(),
            'customer_id' => $walkInIdA,
            'store_id' => $this->store->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $itemIdA, 'quantity' => '1.000', 'unit_price' => '25.000']],
        ];

        $this->postJson('/api/v1/pos/checkout', $payloadA(), $headersA)->assertStatus(201);
        $this->postJson('/api/v1/pos/checkout', $payloadA(), $headersA)->assertStatus(201);

        $throttled = $this->postJson('/api/v1/pos/checkout', $payloadA(), $headersA);
        $throttled->assertStatus(429)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('auth.too_many_requests'));
        $this->assertNotNull($throttled->headers->get('Retry-After'));

        // Tenant B keeps its own independent checkout quota.
        $tenantB = $this->createTenant();
        $adminB = $this->tenantAdmin($tenantB);
        $storeB = $this->tenantStore($tenantB);
        [$itemIdB, $walkInIdB] = $this->inTenant($tenantB, function (): array {
            $walkIn = app(ResolveWalkInCustomerAction::class)->execute();
            $item = Item::create([
                'code' => 'RL-B-01',
                'name' => 'صنف حد المعدل ب',
                'unit' => 'قطعة',
                'cost_price' => '10.000',
                'selling_price' => '25.000',
                'current_stock' => '50.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);
            $this->stockInAllStores($item);

            return [$item->id, $walkIn->id];
        });

        RateLimiter::clear('pos-checkout|t:'.$tenantB->getTenantKey());
        $headersB = $this->tenantHeaders($tenantB, $adminB);

        $this->postJson('/api/v1/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'customer_id' => $walkInIdB,
            'store_id' => $storeB->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $itemIdB, 'quantity' => '1.000', 'unit_price' => '25.000']],
        ], $headersB)->assertStatus(201);
    }

    public function test_per_tenant_checkout_concurrency_limit_returns_translated_429_and_isolates_tenants(): void
    {
        config([
            'rate_limits.pos_checkout.per_tenant_per_minute' => 300,
            'rate_limits.pos_checkout.max_concurrent_per_tenant' => 2,
            'rate_limits.pos_checkout.lock_ttl_seconds' => 30,
        ]);

        [$itemIdA, $walkInIdA] = $this->inTenant($this->tenant, function (): array {
            User::query()->whereKey($this->admin->id)->update(['locale' => null]);
            $walkIn = app(ResolveWalkInCustomerAction::class)->execute();
            $item = Item::create([
                'code' => 'CC-A-01',
                'name' => 'صنف التزامن أ',
                'unit' => 'قطعة',
                'cost_price' => '10.000',
                'selling_price' => '25.000',
                'current_stock' => '50.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);
            $this->stockInAllStores($item);

            return [$item->id, $walkIn->id];
        });

        $headersA = $this->tenantHeaders($this->tenant, $this->admin);
        $prefixA = 'pos-checkout-concurrency|t:'.$this->tenant->getTenantKey().'|';
        $slot1 = Cache::lock($prefixA.'1', 30);
        $slot2 = Cache::lock($prefixA.'2', 30);

        $this->assertTrue($slot1->get());
        $this->assertTrue($slot2->get());

        try {
            $payloadA = fn (): array => [
                'client_uuid' => (string) Str::uuid(),
                'customer_id' => $walkInIdA,
                'store_id' => $this->store->id,
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'items' => [['item_id' => $itemIdA, 'quantity' => '1.000', 'unit_price' => '25.000']],
            ];

            $enResponse = $this->postJson('/api/v1/pos/checkout', $payloadA(), $headersA + ['X-Locale' => 'en']);
            $enResponse->assertStatus(429)
                ->assertHeader('Retry-After', '1')
                ->assertJsonPath('success', false)
                ->assertJsonPath('retry_after', 1)
                ->assertJsonPath('message', __('pos.too_many_concurrent_checkouts', [], 'en'));

            $arResponse = $this->postJson('/api/v1/pos/checkout', $payloadA(), $headersA + ['X-Locale' => 'ar']);
            $arResponse->assertStatus(429)
                ->assertHeader('Retry-After', '1')
                ->assertJsonPath('message', __('pos.too_many_concurrent_checkouts', [], 'ar'));

            // Another tenant is completely unaffected while Tenant A's slots are saturated.
            $tenantB = $this->createTenant();
            $adminB = $this->tenantAdmin($tenantB);
            $storeB = $this->tenantStore($tenantB);
            [$itemIdB, $walkInIdB] = $this->inTenant($tenantB, function (): array {
                $walkIn = app(ResolveWalkInCustomerAction::class)->execute();
                $item = Item::create([
                    'code' => 'CC-B-01',
                    'name' => 'صنف التزامن ب',
                    'unit' => 'قطعة',
                    'cost_price' => '10.000',
                    'selling_price' => '25.000',
                    'current_stock' => '50.000',
                    'min_stock_level' => '0.000',
                    'is_active' => true,
                ]);
                $this->stockInAllStores($item);

                return [$item->id, $walkIn->id];
            });

            $this->postJson('/api/v1/pos/checkout', [
                'client_uuid' => (string) Str::uuid(),
                'customer_id' => $walkInIdB,
                'store_id' => $storeB->id,
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'items' => [['item_id' => $itemIdB, 'quantity' => '1.000', 'unit_price' => '25.000']],
            ], $this->tenantHeaders($tenantB, $adminB))->assertStatus(201);
        } finally {
            $slot1->release();
            $slot2->release();
        }

        // Once a slot is released, Tenant A can immediately check out again.
        $this->postJson('/api/v1/pos/checkout', [
            'client_uuid' => (string) Str::uuid(),
            'customer_id' => $walkInIdA,
            'store_id' => $this->store->id,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $itemIdA, 'quantity' => '1.000', 'unit_price' => '25.000']],
        ], $headersA)->assertStatus(201);
    }

    public function test_lock_wait_timeout_on_checkout_maps_to_clean_409_error_without_retry(): void
    {
        [$itemId, $walkInId] = $this->inTenant($this->tenant, function (): array {
            User::query()->whereKey($this->admin->id)->update(['locale' => null]);
            $walkIn = app(ResolveWalkInCustomerAction::class)->execute();
            $item = Item::create([
                'code' => 'LWT-01',
                'name' => 'صنف مهلة القفل',
                'unit' => 'قطعة',
                'cost_price' => '10.000',
                'selling_price' => '40.000',
                'current_stock' => '20.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);
            $this->stockInAllStores($item);

            return [$item->id, $walkIn->id];
        });

        $attempts = 0;
        $armed = true;
        Invoice::creating(static function () use (&$attempts, &$armed): void {
            if (! $armed) {
                return;
            }
            $attempts++;
            $pdo = new PDOException('SQLSTATE[HY000]: General error: 1205 Lock wait timeout exceeded; try restarting transaction');
            $pdo->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded; try restarting transaction'];

            throw new QueryException('tenant', 'insert into `invoices`', [], $pdo);
        });

        try {
            $response = $this->postJson('/api/v1/pos/checkout', [
                'client_uuid' => (string) Str::uuid(),
                'customer_id' => $walkInId,
                'store_id' => $this->store->id,
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'items' => [['item_id' => $itemId, 'quantity' => '2.000', 'unit_price' => '40.000']],
            ], $this->tenantHeaders($this->tenant, $this->admin) + ['X-Locale' => 'en']);

            $response->assertStatus(409)
                ->assertHeader('Retry-After', '1')
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'common.database_busy')
                ->assertJsonPath('message', __('common.database_busy', [], 'en'));
        } finally {
            $armed = false;
        }

        $this->assertSame(1, $attempts, 'A 1205 lock wait timeout must fail immediately without retrying.');

        $this->inTenant($this->tenant, function () use ($itemId): void {
            $this->assertSame(0, Invoice::withTrashed()->count());
            $this->assertSame('20.000', (string) Item::query()->findOrFail($itemId)->current_stock);
        });
    }

    public function test_store_date_report_indexes_exist_and_migration_is_idempotent(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $schema = DB::connection()->getSchemaBuilder();

            $invoiceIndexNames = array_map(
                static fn (array $idx): string => strtolower((string) $idx['name']),
                $schema->getIndexes('invoices'),
            );
            $movementIndexNames = array_map(
                static fn (array $idx): string => strtolower((string) $idx['name']),
                $schema->getIndexes('stock_movements'),
            );

            $this->assertContains('invoices_store_date_idx', $invoiceIndexNames);
            $this->assertContains('stock_movements_store_created_idx', $movementIndexNames);
            $this->assertContains('stock_movements_store_type_created_idx', $movementIndexNames);

            $migration = require database_path('migrations/tenant/2026_11_28_000100_add_store_date_report_indexes.php');
            $migration->up();
            $migration->down();
            $migration->up();

            $reappliedMovementIndexes = array_map(
                static fn (array $idx): string => strtolower((string) $idx['name']),
                $schema->getIndexes('stock_movements'),
            );
            $this->assertContains('stock_movements_store_created_idx', $reappliedMovementIndexes);
            $this->assertContains('stock_movements_store_type_created_idx', $reappliedMovementIndexes);
        });
    }
}
