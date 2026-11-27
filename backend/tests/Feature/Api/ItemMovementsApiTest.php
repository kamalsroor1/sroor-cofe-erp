<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * INV-070 (#421): GET /api/v1/items/{id}/movements returns all movement types
 * (purchase, sale, transfer, adjustment, stock count) ordered newest-first
 * (`created_at desc, id desc`) with `stock_before`, `stock_after`, `document_number`,
 * and `user`.
 */
final class ItemMovementsApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private User $admin;

    private int $mainStoreId;

    private int $branchStoreId;

    private int $itemId;

    private int $supplierId;

    private int $customerId;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->admin = $this->tenantAdmin($this->tenant);
        $this->mainStoreId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant, $this->admin, $this->mainStoreId);

        [$this->branchStoreId, $this->itemId, $this->supplierId, $this->customerId] = $this->inTenant($this->tenant, function (): array {
            $branch = Store::create([
                'name' => 'فرع المعادي',
                'code' => 'BR-MAADI',
                'type' => 'retail',
                'is_main' => false,
                'is_active' => true,
            ]);

            $item = Item::create([
                'name' => 'بن كولومبي محمص',
                'code' => 'ITM-MOV-01',
                'category' => 'coffee_beans',
                'unit' => 'كجم',
                'cost_price' => '200.000',
                'selling_price' => '350.000',
                'price_retail' => '350.000',
                'current_stock' => '0.000',
                'min_stock_level' => '2.000',
                'is_active' => true,
            ]);

            StoreStock::create([
                'store_id' => $this->mainStoreId,
                'item_id' => $item->id,
                'quantity' => '0.000',
            ]);

            $supplier = Supplier::create([
                'name' => 'مورد البن الأول',
                'phone' => '01000008801',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            $customer = Customer::create([
                'name' => 'عميل الجرد والحركات',
                'phone' => '01000008802',
                'current_balance' => '0.000',
                'price_tier' => 'retail',
                'is_active' => true,
            ]);

            CashShift::create([
                'store_id' => $this->mainStoreId,
                'user_id' => $this->admin->id,
                'shift_number' => 'SHF-MOV-1',
                'status' => 'open',
                'opened_at' => now(),
                'opening_cash_balance' => '500.000',
            ]);

            return [(int) $branch->id, (int) $item->id, (int) $supplier->id, (int) $customer->id];
        });
    }

    public function test_movements_ledger_covers_all_five_movement_types_ordered_newest_first_with_balances_doc_and_user(): void
    {
        $today = now()->toDateString();

        // 1. Purchase (+20.000 -> stock 0.000 -> 20.000)
        $purchase = $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => $today,
            'paid_amount' => '0.000',
            'payment_method' => 'cash',
            'store_id' => $this->mainStoreId,
            'items' => [
                ['item_id' => $this->itemId, 'quantity' => '20.000', 'unit_cost' => '200.000'],
            ],
        ], $this->headers)->assertStatus(201);
        $purchaseNumber = (string) $purchase->json('data.purchase_number');

        // 2. Sale (-4.000 -> stock 20.000 -> 16.000)
        $sale = $this->postJson('/api/v1/invoices', [
            'customer_id' => $this->customerId,
            'invoice_date' => $today,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'paid_amount' => '1400.000',
            'items' => [
                ['item_id' => $this->itemId, 'quantity' => '4.000', 'unit_price' => '350.000', 'discount_amount' => '0.000'],
            ],
        ], $this->headers)->assertStatus(201);
        $invoiceNumber = (string) $sale->json('data.invoice_number');

        // 3. Transfer (3.000 from main -> branch: transfer_out 16->13 on main, transfer_in 0->3 on branch)
        $transfer = $this->postJson('/api/v1/transfers', [
            'from_store_id' => $this->mainStoreId,
            'to_store_id' => $this->branchStoreId,
            'transfer_date' => $today,
            'notes' => 'تحويل فرعي',
            'items' => [
                ['item_id' => $this->itemId, 'quantity' => '3.000'],
            ],
        ], $this->headers)->assertStatus(201);
        $transferNumber = (string) $transfer->json('data.transfer_number');

        // 4. Manual adjustment (-1.000 on main: 13.000 -> 12.000)
        $adj = $this->postJson("/api/v1/items/{$this->itemId}/adjust-stock", [
            'store_id' => $this->mainStoreId,
            'movement_type' => 'stock_adjustment_out',
            'quantity' => '1.000',
            'notes' => 'تسوية تالف',
        ], $this->headers)->assertOk();
        $adjNumber = (string) $adj->json('movement.document_number');

        // 5. Stock count on main (counted 14.500 vs live 12.000 -> stock_count_in +2.500)
        $count = $this->postJson('/api/v1/stock-counts', [
            'notes' => 'جرد شامل',
        ], $this->headers)->assertStatus(201);
        $countId = (int) $count->json('data.id');
        $countNumber = (string) $count->json('data.count_number');

        $this->putJson("/api/v1/stock-counts/{$countId}/lines", [
            'lines' => [
                ['item_id' => $this->itemId, 'counted_qty' => '14.500'],
            ],
        ], $this->headers)->assertOk();

        $this->postJson("/api/v1/stock-counts/{$countId}/post", [], $this->headers)->assertOk();

        // Assign distinct chronological timestamps, leaving the two transfer legs tied on the same timestamp
        // to also exercise the secondary `id desc` deterministic tie-breaker.
        $this->inTenant($this->tenant, function () use ($purchaseNumber, $invoiceNumber, $transferNumber, $adjNumber, $countNumber): void {
            $t0 = now()->subMinutes(50);
            StockMovement::query()->where('document_number', $purchaseNumber)->update(['created_at' => $t0]);
            StockMovement::query()->where('document_number', $invoiceNumber)->update(['created_at' => $t0->copy()->addMinutes(10)]);
            StockMovement::query()->where('document_number', $transferNumber)->update(['created_at' => $t0->copy()->addMinutes(20)]);
            StockMovement::query()->where('document_number', $adjNumber)->update(['created_at' => $t0->copy()->addMinutes(30)]);
            StockMovement::query()->where('document_number', $countNumber)->update(['created_at' => $t0->copy()->addMinutes(40)]);
        });

        // Read all stores ledger
        $response = $this->getJson("/api/v1/items/{$this->itemId}/movements?store_id=all&per_page=20", $this->headers)
            ->assertOk();

        $rows = $response->json('data.data');
        $this->assertCount(6, $rows);

        // Newest-first order: stock_count_in -> stock_adjustment_out -> transfer_in (higher id) -> transfer_out -> sales_out -> purchase_in
        $this->assertSame([
            'stock_count_in',
            'stock_adjustment_out',
            'transfer_in',
            'transfer_out',
            'sales_out',
            'purchase_in',
        ], array_column($rows, 'movement_type'));

        // Verify document references, user, and stock_before / stock_after on each movement type
        $byType = collect($rows)->keyBy('movement_type');

        $this->assertSame($purchaseNumber, $byType['purchase_in']['document_number']);
        $this->assertSame('0.000', $byType['purchase_in']['stock_before']);
        $this->assertSame('20.000', $byType['purchase_in']['stock_after']);
        $this->assertSame('20.000', $byType['purchase_in']['quantity']);

        $this->assertSame($invoiceNumber, $byType['sales_out']['document_number']);
        $this->assertSame('20.000', $byType['sales_out']['stock_before']);
        $this->assertSame('16.000', $byType['sales_out']['stock_after']);
        $this->assertSame('4.000', $byType['sales_out']['quantity']);

        $this->assertSame($transferNumber, $byType['transfer_out']['document_number']);
        $this->assertSame('16.000', $byType['transfer_out']['stock_before']);
        $this->assertSame('13.000', $byType['transfer_out']['stock_after']);
        $this->assertSame($this->mainStoreId, $byType['transfer_out']['store_id']);

        $this->assertSame($transferNumber, $byType['transfer_in']['document_number']);
        $this->assertSame('0.000', $byType['transfer_in']['stock_before']);
        $this->assertSame('3.000', $byType['transfer_in']['stock_after']);
        $this->assertSame($this->branchStoreId, $byType['transfer_in']['store_id']);

        $this->assertSame($adjNumber, $byType['stock_adjustment_out']['document_number']);
        $this->assertSame('13.000', $byType['stock_adjustment_out']['stock_before']);
        $this->assertSame('12.000', $byType['stock_adjustment_out']['stock_after']);

        $this->assertSame($countNumber, $byType['stock_count_in']['document_number']);
        $this->assertSame('15.000', $byType['stock_count_in']['stock_before']);
        $this->assertSame('17.500', $byType['stock_count_in']['stock_after']);
        $this->assertSame('2.500', $byType['stock_count_in']['quantity']);

        foreach ($rows as $row) {
            $this->assertSame([
                'id' => $this->admin->id,
                'name' => $this->admin->name,
            ], $row['user']);
            $this->assertNotEmpty($row['store']['name']);
            $this->assertNotEmpty($row['created_at']);
        }

        // Verify summary stats across all movements:
        // IN: 20.000 (purchase) + 3.000 (transfer_in) + 2.500 (stock_count_in) = 25.500
        // OUT: 4.000 (sale) + 3.000 (transfer_out) + 1.000 (adjustment) = 8.000
        // NET: 17.500
        $response->assertJsonPath('data.stats.total_in', '25.500')
            ->assertJsonPath('data.stats.total_out', '8.000')
            ->assertJsonPath('data.stats.net_movement', '17.500')
            ->assertJsonPath('data.stats.movements_count', 6);
    }

    public function test_movements_ordering_prioritizes_created_at_desc_over_id_desc(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $firstInserted = StockMovement::create([
                'item_id' => $this->itemId,
                'store_id' => $this->mainStoreId,
                'user_id' => $this->admin->id,
                'movement_type' => 'purchase_in',
                'quantity' => '5.000',
                'stock_before' => '0.000',
                'stock_after' => '5.000',
                'unit_cost' => '200.000',
                'source_type' => Item::class,
                'source_id' => $this->itemId,
                'document_number' => 'DOC-NEWER-TIME-LOWER-ID',
            ]);
            // Give the lower-id row a newer created_at than the higher-id row
            $firstInserted->forceFill(['created_at' => now()->addMinute()])->save();

            $secondInserted = StockMovement::create([
                'item_id' => $this->itemId,
                'store_id' => $this->mainStoreId,
                'user_id' => $this->admin->id,
                'movement_type' => 'sales_out',
                'quantity' => '1.000',
                'stock_before' => '5.000',
                'stock_after' => '4.000',
                'unit_cost' => '200.000',
                'source_type' => Item::class,
                'source_id' => $this->itemId,
                'document_number' => 'DOC-OLDER-TIME-HIGHER-ID',
            ]);
            $secondInserted->forceFill(['created_at' => now()->subMinute()])->save();
        });

        $response = $this->getJson("/api/v1/items/{$this->itemId}/movements", $this->headers)->assertOk();

        $this->assertSame(
            ['DOC-NEWER-TIME-LOWER-ID', 'DOC-OLDER-TIME-HIGHER-ID'],
            array_column($response->json('data.data'), 'document_number'),
        );
    }
}
