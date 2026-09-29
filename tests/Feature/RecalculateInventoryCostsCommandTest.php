<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StockDeposit;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * inventory:recost on seeded "legacy" data that reproduces the production bug:
 * an opening balance booked at cost 0 drags the weighted average and the COGS of every sale.
 */
class RecalculateInventoryCostsCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $openingCostsPath;

    private Item $item;

    private Invoice $saleBeforePurchase;

    private Invoice $saleAfterPurchase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);

        $customer = Customer::create(['name' => 'عميل', 'current_balance' => '0.000', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'مورد', 'is_active' => true]);

        // Legacy opening balance: 10 kg at cost 0 (the old item screen allowed it)
        $this->item = Item::create([
            'code' => 'LEG-1', 'name' => 'صنف رصيده الأول بتكلفة صفر', 'unit' => 'كجم',
            'current_stock' => '10.000', 'cost_price' => '0.000', 'weighted_avg_cost' => '0.000',
            'selling_price' => '150.000', 'is_active' => true,
        ]);
        $deposit = StockDeposit::create([
            'item_id' => $this->item->id, 'user_id' => $admin->id, 'deposit_type' => 'opening_balance',
            'quantity' => '10.000', 'cost_price' => '0.000', 'deposit_date' => now()->toDateString(),
        ]);
        StockMovement::create([
            'item_id' => $this->item->id, 'movement_type' => 'stock_deposit_in', 'quantity' => '10.000',
            'stock_before' => '0.000', 'stock_after' => '10.000', 'unit_cost' => '0.000',
            'source_type' => StockDeposit::class, 'source_id' => $deposit->id,
            'document_number' => "DEP-{$deposit->id}", 'user_id' => $admin->id,
        ]);

        $sell = fn (string $qty) => app(InvoiceService::class)->confirmInvoice([
            'customer_id' => $customer->id, 'payment_type' => 'credit',
            'items' => [['item_id' => $this->item->id, 'quantity' => $qty, 'unit_price' => '150.000']],
        ]);

        $this->saleBeforePurchase = $sell('5.000');                 // booked at cost 0
        app(PurchaseService::class)->createPurchase([               // (5 x 0 + 5 x 100) / 10 = 50
            'supplier_id' => $supplier->id,
            'items' => [['item_id' => $this->item->id, 'quantity' => '5.000', 'cost_price' => '100.000']],
        ]);
        $this->saleAfterPurchase = $sell('4.000');                  // booked at 50

        $this->openingCostsPath = tempnam(sys_get_temp_dir(), 'opening_costs_');
        file_put_contents($this->openingCostsPath, json_encode([(string) $this->item->id => '100']));
    }

    protected function tearDown(): void
    {
        @unlink($this->openingCostsPath);
        parent::tearDown();
    }

    private function recost(array $options = []): PendingCommand
    {
        // Tests do not run in maintenance mode, so --apply needs --force here.
        if (! empty($options['--apply']) && ! array_key_exists('--force', $options)) {
            $options['--force'] = true;
        }

        return $this->artisan('inventory:recost', array_merge([
            '--opening-costs' => $this->openingCostsPath,
            '--report' => sys_get_temp_dir().'/recost_test_'.uniqid().'.csv',
        ], $options));
    }

    public function test_seeded_data_reproduces_the_legacy_distortion(): void
    {
        $this->assertSame('50.000', (string) $this->item->fresh()->weighted_avg_cost);
        $this->assertSame('0.000', (string) $this->saleBeforePurchase->fresh()->total_cost);
        $this->assertSame('200.000', (string) $this->saleAfterPurchase->fresh()->total_cost);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->recost(['--recost-invoices' => true, '--fix-deposit-costs' => true])->assertExitCode(0);

        $this->assertSame('50.000', (string) $this->item->fresh()->weighted_avg_cost);
        $this->assertSame('0.000', (string) $this->saleBeforePurchase->fresh()->total_cost);
        $this->assertSame('0.000', (string) StockDeposit::first()->cost_price);
        $this->assertSame(0, AuditLog::where('action_type', 'like', '%cost_recalculated')->count());
    }

    public function test_apply_recalculates_wac_invoice_costs_and_deposits(): void
    {
        $this->recost(['--apply' => true, '--recost-invoices' => true, '--fix-deposit-costs' => true])->assertExitCode(0);

        $this->assertSame('100.000', (string) $this->item->fresh()->weighted_avg_cost);
        $this->assertSame('100.000', (string) $this->item->fresh()->cost_price);

        $this->assertSame('500.000', (string) $this->saleBeforePurchase->fresh()->total_cost);
        $this->assertSame('400.000', (string) $this->saleAfterPurchase->fresh()->total_cost);
        $this->assertSame('100.000', (string) $this->saleAfterPurchase->items()->first()->cost_price);

        $this->assertSame('100.000', (string) StockDeposit::first()->cost_price);
        $this->assertDatabaseHas('stock_movements', ['movement_type' => 'stock_deposit_in', 'unit_cost' => '100.000']);

        $this->assertSame(1, AuditLog::where('action_type', 'inventory_cost_recalculated')->count());
        $this->assertSame(2, AuditLog::where('action_type', 'invoice_cost_recalculated')->count());

        // Stock quantities are never touched
        $this->assertSame('6.000', (string) $this->item->fresh()->current_stock);
    }

    public function test_second_apply_is_idempotent(): void
    {
        $options = ['--apply' => true, '--recost-invoices' => true, '--fix-deposit-costs' => true];
        $this->recost($options)->assertExitCode(0);
        $auditCount = AuditLog::count();

        $this->recost($options)->assertExitCode(0);

        $this->assertSame($auditCount, AuditLog::count());
        $this->assertSame('100.000', (string) $this->item->fresh()->weighted_avg_cost);
        $this->assertSame('500.000', (string) $this->saleBeforePurchase->fresh()->total_cost);
    }

    public function test_item_with_zero_cost_and_no_opening_cost_is_skipped_untouched(): void
    {
        file_put_contents($this->openingCostsPath, json_encode([]));

        $this->recost(['--apply' => true, '--recost-invoices' => true])->assertExitCode(1);

        $this->assertSame('50.000', (string) $this->item->fresh()->weighted_avg_cost);
        $this->assertSame('0.000', (string) $this->saleBeforePurchase->fresh()->total_cost);
    }

    public function test_excluded_item_is_left_untouched(): void
    {
        $this->recost(['--apply' => true, '--recost-invoices' => true, '--exclude' => (string) $this->item->id])->assertExitCode(0);

        $this->assertSame('50.000', (string) $this->item->fresh()->weighted_avg_cost);
        $this->assertSame('200.000', (string) $this->saleAfterPurchase->fresh()->total_cost);
    }

    public function test_replay_matches_the_runtime_after_the_fixes(): void
    {
        // An item created and traded only through the fixed code paths must already be correct.
        $clean = Item::create([
            'code' => 'CLEAN-1', 'name' => 'صنف سليم', 'unit' => 'كجم', 'current_stock' => '0.000',
            'cost_price' => '80.000', 'weighted_avg_cost' => '80.000', 'selling_price' => '120.000', 'is_active' => true,
        ]);
        app(StockService::class)->depositStock($clean, '10.000', '80.000', 'opening_balance');
        $supplier = Supplier::first();
        $purchase = app(PurchaseService::class)->createPurchase([
            'supplier_id' => $supplier->id,
            'items' => [['item_id' => $clean->id, 'quantity' => '10.000', 'cost_price' => '120.000']],
        ]);
        app(PurchaseService::class)->cancelPurchase($purchase, 'اختبار');
        $wacBefore = (string) $clean->fresh()->weighted_avg_cost;

        $this->recost(['--apply' => true, '--items' => (string) $clean->id])->assertExitCode(0);

        $this->assertSame('80.000', $wacBefore);
        $this->assertSame($wacBefore, (string) $clean->fresh()->weighted_avg_cost);
        $this->assertSame(0, AuditLog::where('action_type', 'inventory_cost_recalculated')->count());
    }

    // ---------------------------------------------------------------- review fixes

    public function test_stock_before_first_costed_inflow_is_unresolved_without_opening_cost(): void
    {
        // adjustment-in -> sale -> purchase: runtime costs the sale at cost_price (80), the replay has no cost.
        $item = Item::create([
            'code' => 'ADJ-1', 'name' => 'صنف بدأ بتسوية جرد', 'unit' => 'كجم', 'current_stock' => '0.000',
            'cost_price' => '80.000', 'weighted_avg_cost' => '0.000', 'selling_price' => '120.000', 'is_active' => true,
        ]);
        app(StockService::class)->adjustStock($item, '10.000', 'جرد افتتاحي');
        $sale = app(InvoiceService::class)->confirmInvoice([
            'customer_id' => Customer::first()->id, 'payment_type' => 'credit',
            'items' => [['item_id' => $item->id, 'quantity' => '4.000', 'unit_price' => '120.000']],
        ]);
        app(PurchaseService::class)->createPurchase([
            'supplier_id' => Supplier::first()->id,
            'items' => [['item_id' => $item->id, 'quantity' => '5.000', 'cost_price' => '100.000']],
        ]);
        $wacBefore = (string) $item->fresh()->weighted_avg_cost;   // (6 x 80 + 5 x 100) / 11
        $this->assertSame('89.091', $wacBefore);
        $this->assertSame('320.000', (string) $sale->fresh()->total_cost);

        file_put_contents($this->openingCostsPath, json_encode([]));
        $this->recost(['--apply' => true, '--recost-invoices' => true, '--items' => (string) $item->id])
            ->expectsOutputToContain('stock at zero cost before the first costed inflow')
            ->assertExitCode(1);

        // Nothing written: no zero cost on the sale, WAC untouched (before the fix: sale -> 0, WAC -> 45.455)
        $this->assertSame('320.000', (string) $sale->fresh()->total_cost);
        $this->assertSame('80.000', (string) $sale->items()->first()->cost_price);
        $this->assertSame($wacBefore, (string) $item->fresh()->weighted_avg_cost);

        // With an explicit opening cost the item resolves to the runtime result
        file_put_contents($this->openingCostsPath, json_encode([(string) $item->id => '80']));
        $this->recost(['--apply' => true, '--recost-invoices' => true, '--items' => (string) $item->id])->assertExitCode(0);
        $this->assertSame('89.091', (string) $item->fresh()->weighted_avg_cost);
        $this->assertSame('320.000', (string) $sale->fresh()->total_cost);
    }

    public function test_cancel_restore_cancel_purchase_replays_without_error(): void
    {
        $item = Item::create([
            'code' => 'CRC-1', 'name' => 'صنف إلغاء واستعادة', 'unit' => 'كجم', 'current_stock' => '0.000',
            'cost_price' => '100.000', 'weighted_avg_cost' => '100.000', 'selling_price' => '150.000', 'is_active' => true,
        ]);
        app(StockService::class)->depositStock($item, '10.000', '100.000', 'opening_balance');
        $service = app(PurchaseService::class);
        $purchase = $service->createPurchase([
            'supplier_id' => Supplier::first()->id,
            'items' => [['item_id' => $item->id, 'quantity' => '10.000', 'cost_price' => '200.000']],
        ]);
        $service->cancelPurchase($purchase, 'اختبار 1');
        $service->restorePurchase($purchase->fresh());
        $service->cancelPurchase($purchase->fresh(), 'اختبار 2');
        $this->assertSame('100.000', (string) $item->fresh()->weighted_avg_cost);

        $this->recost(['--apply' => true, '--items' => (string) $item->id])->assertExitCode(0);

        $this->assertSame('100.000', (string) $item->fresh()->weighted_avg_cost);
        $this->assertSame(0, AuditLog::where('action_type', 'inventory_cost_recalculated')->count());
    }

    public function test_apply_is_refused_outside_maintenance_mode_without_force(): void
    {
        $this->recost(['--apply' => true, '--force' => false, '--recost-invoices' => true])
            ->expectsOutputToContain('php artisan down')
            ->assertExitCode(1);

        $this->assertSame('50.000', (string) $this->item->fresh()->weighted_avg_cost);
        $this->assertSame('0.000', (string) $this->saleBeforePurchase->fresh()->total_cost);
    }

    public function test_unwritable_report_path_fails_before_any_change(): void
    {
        $this->recost(['--apply' => true, '--recost-invoices' => true, '--report' => sys_get_temp_dir()])
            ->expectsOutputToContain('Cannot open report file')
            ->assertExitCode(1);

        $this->assertSame('50.000', (string) $this->item->fresh()->weighted_avg_cost);
        $this->assertSame(0, AuditLog::where('action_type', 'like', '%cost_recalculated')->count());
    }
}
