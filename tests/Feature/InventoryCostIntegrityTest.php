<?php

namespace Tests\Feature;

use App\Livewire\InvoiceIndex;
use App\Livewire\ItemIndex;
use App\Livewire\PurchaseCreate;
use App\Livewire\TrashIndex;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\ReturnItem;
use App\Models\StockDeposit;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\StockService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regression tests for the 2026-09-29 inventory cost audit (weighted average cost integrity).
 */
class InventoryCostIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Supplier $supplier;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->actingAs($this->admin);

        $this->supplier = Supplier::create(['name' => 'مورد البن', 'is_active' => true]);
        $this->customer = Customer::create(['name' => 'عميل التكلفة', 'current_balance' => '0.000', 'is_active' => true]);
    }

    private function makeItem(string $stock, string $wac, string $cost, string $code = 'COST-01'): Item
    {
        return Item::create([
            'code' => $code,
            'name' => 'صنف اختبار التكلفة '.$code,
            'unit' => 'كجم',
            'current_stock' => $stock,
            'cost_price' => $cost,
            'weighted_avg_cost' => $wac,
            'selling_price' => '500.000',
            'is_active' => true,
        ]);
    }

    private function purchase(Item $item, string $qty, string $cost, string $discount = '0.000'): Purchase
    {
        return app(PurchaseService::class)->createPurchase([
            'supplier_id' => $this->supplier->id,
            'discount_amount' => $discount,
            'items' => [['item_id' => $item->id, 'quantity' => $qty, 'cost_price' => $cost]],
        ]);
    }

    private function sell(Item $item, string $qty): Invoice
    {
        return app(InvoiceService::class)->confirmInvoice([
            'customer_id' => $this->customer->id,
            'payment_type' => 'credit',
            'items' => [['item_id' => $item->id, 'quantity' => $qty, 'unit_price' => '500.000']],
        ]);
    }

    /** Asserts every movement's stock_before equals the previous movement's stock_after. */
    private function assertLedgerIsContinuous(Item $item): void
    {
        $movements = StockMovement::where('item_id', $item->id)->orderBy('id')->get();
        $previousAfter = null;
        foreach ($movements as $movement) {
            if ($previousAfter !== null) {
                $this->assertSame($previousAfter, (string) $movement->stock_before, "Ledger gap at movement #{$movement->id} ({$movement->movement_type})");
            }
            $previousAfter = (string) $movement->stock_after;
        }
        $this->assertSame((string) $item->fresh()->current_stock, $previousAfter);
    }

    // ---------------------------------------------------------------- B1: "0.000" ?: fallback

    public function test_purchase_blends_with_cost_price_when_wac_is_zero(): void
    {
        $item = $this->makeItem('10.000', '0.000', '100.000');

        $this->purchase($item, '10.000', '200.000');

        // Before the fix "0.000" was truthy, so the 10 units on hand were blended at 0 -> 100.000
        $this->assertSame('150.000', (string) $item->fresh()->weighted_avg_cost);
    }

    public function test_sale_uses_cost_price_when_wac_is_zero(): void
    {
        $item = $this->makeItem('10.000', '0.000', '120.000');

        $invoice = $this->sell($item, '2.000');

        $this->assertSame('120.000', (string) $invoice->items()->first()->cost_price);
        $this->assertSame('240.000', (string) $invoice->fresh()->total_cost);
    }

    // ---------------------------------------------------------------- RC3: cancel reverses WAC

    public function test_cancelling_purchase_reverts_weighted_average_cost(): void
    {
        $item = $this->makeItem('10.000', '100.000', '100.000');

        $purchase = $this->purchase($item, '10.000', '200.000');
        $this->assertSame('150.000', (string) $item->fresh()->weighted_avg_cost);

        app(PurchaseService::class)->cancelPurchase($purchase, 'اختبار');

        $item->refresh();
        $this->assertSame('10.000', (string) $item->current_stock);
        $this->assertSame('100.000', (string) $item->weighted_avg_cost);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $item->id,
            'movement_type' => 'purchase_cancel_out',
            'unit_cost' => '200.000',
        ]);
    }

    public function test_cancel_then_restore_returns_to_the_same_wac(): void
    {
        $item = $this->makeItem('10.000', '100.000', '100.000');
        $purchase = $this->purchase($item, '10.000', '200.000');

        $service = app(PurchaseService::class);
        $service->cancelPurchase($purchase, 'اختبار');
        $service->restorePurchase($purchase->fresh());

        $this->assertSame('150.000', (string) $item->fresh()->weighted_avg_cost);
        $this->assertSame('20.000', (string) $item->fresh()->current_stock);
    }

    public function test_deleting_purchase_keeps_its_stock_movements(): void
    {
        $item = $this->makeItem('0.000', '0.000', '100.000');
        $purchase = $this->purchase($item, '5.000', '100.000');

        app(PurchaseService::class)->deletePurchase($purchase);

        $this->assertSame(2, StockMovement::where('item_id', $item->id)->count()); // purchase_in + purchase_cancel_out
        $this->assertLedgerIsContinuous($item);
    }

    // ---------------------------------------------------------------- RC2 + F7: purchase line costs

    public function test_purchase_with_zero_cost_line_is_rejected_and_nothing_is_written(): void
    {
        $item = $this->makeItem('0.000', '0.000', '0.000');

        try {
            $this->purchase($item, '10.000', '0.000');
            $this->fail('Zero-cost purchase line must be rejected');
        } catch (Exception $e) {
            $this->assertStringContainsString('أكبر من صفر', $e->getMessage());
        }

        $this->assertSame(0, Purchase::count());
        $this->assertSame('0.000', (string) $item->fresh()->current_stock);
    }

    public function test_purchase_discount_is_allocated_into_landed_cost_by_value(): void
    {
        $itemA = $this->makeItem('0.000', '0.000', '0.000', 'DISC-A');
        $itemB = $this->makeItem('0.000', '0.000', '0.000', 'DISC-B');

        $purchase = app(PurchaseService::class)->createPurchase([
            'supplier_id' => $this->supplier->id,
            'discount_amount' => '400.000',
            'items' => [
                ['item_id' => $itemA->id, 'quantity' => '10.000', 'cost_price' => '100.000'],
                ['item_id' => $itemB->id, 'quantity' => '10.000', 'cost_price' => '300.000'],
            ],
        ]);

        // Values 1000 / 3000 -> discount shares 100 / 300 -> 10 / 30 per unit
        $this->assertSame('90.000', (string) $itemA->fresh()->weighted_avg_cost);
        $this->assertSame('270.000', (string) $itemB->fresh()->weighted_avg_cost);
        $this->assertSame('3600.000', (string) $purchase->fresh()->net_total);
        $this->assertSame('100.000', (string) $purchase->items()->where('item_id', $itemA->id)->first()->base_cost_price);
    }

    public function test_purchase_create_component_rejects_zero_cost_lines(): void
    {
        $store = Store::create(['name' => 'المخزن الرئيسي', 'code' => 'MAIN-01', 'type' => 'main', 'is_active' => true, 'is_main' => true]);
        $item = $this->makeItem('0.000', '0.000', '0.000');

        Livewire::test(PurchaseCreate::class)
            ->set('supplier_id', $this->supplier->id)
            ->set('store_id', $store->id)
            ->call('addItem', $item->id, '5.000')
            ->call('savePurchase')
            ->assertHasErrors(['items.0.cost_price']);

        $this->assertSame(0, Purchase::count());
    }

    // ---------------------------------------------------------------- RC4 / B12: deposits

    public function test_deposit_blends_into_weighted_average_cost(): void
    {
        $item = $this->makeItem('10.000', '100.000', '100.000');

        app(StockService::class)->depositStock($item, '10.000', '200.000');

        $item->refresh();
        $this->assertSame('20.000', (string) $item->current_stock);
        $this->assertSame('150.000', (string) $item->weighted_avg_cost);
        $this->assertSame('200.000', (string) $item->cost_price);
    }

    public function test_zero_cost_deposit_is_valued_at_current_cost(): void
    {
        $item = $this->makeItem('10.000', '100.000', '90.000');

        $deposit = app(StockService::class)->depositStock($item, '5.000', '0.000');

        $item->refresh();
        $this->assertSame('100.000', (string) $deposit->cost_price);
        $this->assertSame('100.000', (string) $item->weighted_avg_cost);
        $this->assertSame('90.000', (string) $item->cost_price); // not overwritten by an implied cost
    }

    public function test_zero_cost_deposit_without_any_known_cost_is_rejected(): void
    {
        $item = $this->makeItem('0.000', '0.000', '0.000');

        $this->expectException(Exception::class);
        try {
            app(StockService::class)->depositStock($item, '5.000', '0.000');
        } finally {
            $this->assertSame('0.000', (string) $item->fresh()->current_stock);
            $this->assertSame(0, StockDeposit::count());
        }
    }

    // ---------------------------------------------------------------- RC1 / B2: item screen

    public function test_item_with_opening_stock_requires_a_cost(): void
    {
        Livewire::test(ItemIndex::class)
            ->call('openCreateModal')
            ->set('code', 'OPEN-0')
            ->set('name', 'صنف رصيد أول بدون تكلفة')
            ->set('current_stock', '10.000')
            ->set('cost_price', '0.000')
            ->call('saveItem')
            ->assertHasErrors(['cost_price']);

        $this->assertSame(0, Item::where('code', 'OPEN-0')->count());
    }

    public function test_item_with_opening_stock_seeds_wac_and_deposit_at_its_cost(): void
    {
        Livewire::test(ItemIndex::class)
            ->call('openCreateModal')
            ->set('code', 'OPEN-1')
            ->set('name', 'صنف رصيد أول')
            ->set('current_stock', '10.000')
            ->set('cost_price', '100.000')
            ->call('saveItem')
            ->assertHasNoErrors();

        $item = Item::where('code', 'OPEN-1')->firstOrFail();
        $this->assertSame('10.000', (string) $item->current_stock);
        $this->assertSame('100.000', (string) $item->weighted_avg_cost);
        $this->assertDatabaseHas('stock_movements', ['item_id' => $item->id, 'movement_type' => 'stock_deposit_in', 'unit_cost' => '100.000']);
    }

    public function test_cost_of_item_with_movements_cannot_be_edited_manually(): void
    {
        $item = $this->makeItem('0.000', '0.000', '100.000');
        $this->purchase($item, '5.000', '100.000');

        Livewire::test(ItemIndex::class)
            ->call('openEditModal', $item->id)
            ->set('cost_price', '150.000')
            ->call('saveItem')
            ->assertHasErrors(['cost_price']);

        $this->assertSame('100.000', (string) $item->fresh()->cost_price);
        $this->assertSame('100.000', (string) $item->fresh()->weighted_avg_cost);
    }

    public function test_cost_of_item_without_movements_can_be_edited_and_updates_wac(): void
    {
        $item = $this->makeItem('0.000', '0.000', '100.000');

        Livewire::test(ItemIndex::class)
            ->call('openEditModal', $item->id)
            ->set('cost_price', '150.000')
            ->call('saveItem')
            ->assertHasNoErrors();

        $this->assertSame('150.000', (string) $item->fresh()->cost_price);
        $this->assertSame('150.000', (string) $item->fresh()->weighted_avg_cost);
    }

    // ---------------------------------------------------------------- B3 / B4 / B5 / RC6: invoices

    public function test_invoice_edit_keeps_a_continuous_ledger(): void
    {
        $item = $this->makeItem('100.000', '200.000', '200.000');
        $invoice = $this->sell($item, '10.000');

        app(InvoiceService::class)->updateInvoice($invoice, [
            'customer_id' => $this->customer->id,
            'payment_type' => 'credit',
            'items' => [['item_id' => $item->id, 'quantity' => '5.000', 'unit_price' => '500.000']],
        ]);

        $this->assertSame('95.000', (string) $item->fresh()->current_stock);
        $this->assertSame(
            ['sales_out', 'cancellation_in', 'sales_out'],
            StockMovement::where('item_id', $item->id)->orderBy('id')->pluck('movement_type')->all()
        );
        $this->assertLedgerIsContinuous($item);
        $this->assertSame('200.000', (string) $item->fresh()->weighted_avg_cost);
    }

    public function test_invoice_cancellation_restocks_at_the_sold_cost(): void
    {
        $item = $this->makeItem('100.000', '200.000', '200.000');
        $invoice = $this->sell($item, '50.000');         // 50 left @ 200
        $this->purchase($item, '50.000', '300.000');      // (50x200 + 50x300)/100 = 250

        app(InvoiceService::class)->cancelInvoice($invoice, 'اختبار');

        // (100 x 250 + 50 x 200) / 150 = 233.333
        $this->assertSame('233.333', (string) $item->fresh()->weighted_avg_cost);
        $this->assertSame('150.000', (string) $item->fresh()->current_stock);
        $this->assertLedgerIsContinuous($item);
    }

    public function test_invoice_deletion_keeps_movements_and_blends_restock(): void
    {
        $item = $this->makeItem('100.000', '200.000', '200.000');
        $invoice = $this->sell($item, '50.000');
        $this->purchase($item, '50.000', '300.000');

        app(InvoiceService::class)->deleteInvoice($invoice);

        $this->assertSame('233.333', (string) $item->fresh()->weighted_avg_cost);
        $this->assertSame(1, StockMovement::where('source_type', Invoice::class)->where('source_id', $invoice->id)->where('movement_type', 'sales_out')->count());
        $this->assertLedgerIsContinuous($item);
    }

    public function test_deleted_invoice_cannot_be_restored_from_trash(): void
    {
        $item = $this->makeItem('10.000', '100.000', '100.000');
        $invoice = $this->sell($item, '2.000');
        app(InvoiceService::class)->deleteInvoice($invoice);

        Livewire::test(TrashIndex::class)->call('restoreInvoice', $invoice->id);

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSame('10.000', (string) $item->fresh()->current_stock);
    }

    // ---------------------------------------------------------------- B6 / B7 / B8: returns

    public function test_sales_return_restocks_at_original_line_cost_and_stores_it(): void
    {
        $item = $this->makeItem('10.000', '100.000', '100.000');
        $invoice = $this->sell($item, '5.000');           // sold at 100, 5 left
        $this->purchase($item, '5.000', '200.000');       // (5x100 + 5x200)/10 = 150

        app(ReturnService::class)->createSalesReturn([
            'customer_id' => $this->customer->id,
            'invoice_id' => $invoice->id,
            'items' => [['item_id' => $item->id, 'quantity' => '5.000', 'unit_price' => '500.000']],
        ]);

        // (10 x 150 + 5 x 100) / 15 = 133.333
        $this->assertSame('133.333', (string) $item->fresh()->weighted_avg_cost);
        $this->assertSame('100.000', (string) ReturnItem::first()->cost_price);
        $this->assertDatabaseHas('stock_movements', ['item_id' => $item->id, 'movement_type' => 'sales_return_in', 'unit_cost' => '100.000']);
    }

    public function test_purchase_return_removes_quantity_at_its_purchase_cost(): void
    {
        $item = $this->makeItem('10.000', '100.000', '100.000');
        $purchase = $this->purchase($item, '10.000', '200.000'); // 150

        app(ReturnService::class)->createPurchaseReturn([
            'supplier_id' => $this->supplier->id,
            'purchase_id' => $purchase->id,
            'items' => [['item_id' => $item->id, 'quantity' => '10.000', 'unit_price' => '200.000']],
        ]);

        $this->assertSame('100.000', (string) $item->fresh()->weighted_avg_cost);
        $this->assertSame('10.000', (string) $item->fresh()->current_stock);
        $this->assertSame('200.000', (string) ReturnItem::first()->cost_price);
    }

    public function test_purchase_return_without_store_id_does_not_crash(): void
    {
        $item = $this->makeItem('10.000', '100.000', '100.000');

        $return = app(ReturnService::class)->createPurchaseReturn([
            'supplier_id' => $this->supplier->id,
            'items' => [['item_id' => $item->id, 'quantity' => '2.000', 'unit_price' => '100.000']],
        ]);

        $this->assertNotNull($return->id);
        $this->assertSame('8.000', (string) $item->fresh()->current_stock);
    }

    // ---------------------------------------------------------------- B10: ledger unit cost

    public function test_sales_movement_records_the_weighted_average_cost(): void
    {
        $item = $this->makeItem('10.000', '150.000', '200.000');

        $this->sell($item, '1.000');

        $this->assertDatabaseHas('stock_movements', ['item_id' => $item->id, 'movement_type' => 'sales_out', 'unit_cost' => '150.000']);
    }

    // ---------------------------------------------------------------- review fixes

    public function test_purchase_discount_equal_to_base_total_is_rejected(): void
    {
        $item = $this->makeItem('0.000', '0.000', '0.000');

        $this->expectException(Exception::class);
        try {
            $this->purchase($item, '10.000', '100.000', '1000.000');
        } finally {
            $this->assertSame(0, Purchase::count());
            $this->assertSame('0.000', (string) $item->fresh()->weighted_avg_cost);
        }
    }

    public function test_negative_purchase_discount_is_rejected(): void
    {
        $item = $this->makeItem('0.000', '0.000', '0.000');

        $this->expectException(Exception::class);
        try {
            $this->purchase($item, '10.000', '100.000', '-50.000');
        } finally {
            $this->assertSame(0, Purchase::count());
        }
    }

    public function test_invoice_index_refuses_to_restore_deleted_invoice(): void
    {
        Permission::firstOrCreate(['name' => 'trash.access', 'guard_name' => 'web']);
        $this->admin->givePermissionTo('trash.access');
        $item = $this->makeItem('10.000', '100.000', '100.000');
        $invoice = $this->sell($item, '2.000');
        app(InvoiceService::class)->deleteInvoice($invoice);

        Livewire::test(InvoiceIndex::class)->call('restoreInvoice', $invoice->id);

        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
        $this->assertSame('10.000', (string) $item->fresh()->current_stock);
    }

    public function test_cost_field_is_locked_for_items_with_movements(): void
    {
        $withMovements = $this->makeItem('0.000', '0.000', '100.000', 'LOCK-1');
        $this->purchase($withMovements, '5.000', '100.000');
        $fresh = $this->makeItem('0.000', '0.000', '100.000', 'LOCK-2');

        Livewire::test(ItemIndex::class)
            ->call('openEditModal', $withMovements->id)
            ->assertSet('costLocked', true)
            ->assertSee('التكلفة تُحسب تلقائياً من فواتير الشراء');

        Livewire::test(ItemIndex::class)
            ->call('openEditModal', $fresh->id)
            ->assertSet('costLocked', false);
    }
}
