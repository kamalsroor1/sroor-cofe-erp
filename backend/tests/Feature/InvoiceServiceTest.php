<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\User;
use App\Services\InvoiceService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoiceService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceService = app(InvoiceService::class);
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $this->user = User::factory()->create();
        $this->user->assignRole($adminRole);
        $this->actingAs($this->user);
    }

    public function test_invoice_creation_with_db_transaction_and_stock_deduction(): void
    {
        $item = Item::create([
            'code' => 'ITM-001',
            'name' => 'شاشه ديل 27 بوصة',
            'unit' => 'قطعة',
            'current_stock' => '10.000',
            'cost_price' => '4500.000',
            'weighted_avg_cost' => '4500.000',
            'selling_price' => '5500.000',
            'min_stock_level' => '2.000',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'شركة الأمل للتجارة',
            'phone' => '01012345678',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        $invoiceData = [
            'customer_id' => $customer->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '100.000',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => '2.000',
                    'unit_price' => '5500.000',
                    'discount_amount' => '0.000',
                ],
            ],
        ];

        $invoice = $this->invoiceService->confirmInvoice($invoiceData);

        // 2 items * 5500 = 11,000 subtotal - 100 fixed discount = 10,900 net total
        $this->assertEquals('11000.000', $invoice->subtotal);
        $this->assertEquals('100.000', $invoice->discount_amount);
        $this->assertEquals('10900.000', $invoice->net_total);
        $this->assertEquals('10900.000', $invoice->paid_amount);
        $this->assertEquals('0.000', $invoice->remaining_amount);
        $this->assertEquals('paid', $invoice->payment_status);

        // Stock deduction check: 10 - 2 = 8
        $item->refresh();
        $this->assertEquals('8.000', $item->current_stock);

        // Stock movement ledger check
        $movement = StockMovement::where('item_id', $item->id)->first();
        $this->assertNotNull($movement);
        $this->assertEquals('sales_out', $movement->movement_type);
        $this->assertEquals('2.000', $movement->quantity);
        $this->assertEquals('10.000', $movement->stock_before);
        $this->assertEquals('8.000', $movement->stock_after);
    }

    public function test_insufficient_stock_throws_exception_and_rolls_back(): void
    {
        $item = Item::create([
            'code' => 'ITM-LOW',
            'name' => 'ماوس لاسلكي',
            'current_stock' => '1.000',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'عميل نقدي',
            'is_active' => true,
        ]);

        $invoiceData = [
            'customer_id' => $customer->id,
            'payment_type' => 'cash',
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => '5.000', // Greater than available 1.000
                    'unit_price' => '150.000',
                ],
            ],
        ];

        $this->expectException(Exception::class);
        $this->invoiceService->confirmInvoice($invoiceData);

        // Verify rollback: stock remains 1.000 and 0 invoices created
        $item->refresh();
        $this->assertEquals('1.000', $item->current_stock);
        $this->assertEquals(0, Invoice::count());
    }

    public function test_invoice_cancellation_reverses_stock(): void
    {
        $item = Item::create([
            'code' => 'ITM-REV',
            'name' => 'لوحة مفاتيح ميكانيكية',
            'current_stock' => '5.000',
            'cost_price' => '300.000',
            'selling_price' => '400.000',
            'is_active' => true,
        ]);

        $customer = Customer::create(['name' => 'عميل اختبار', 'is_active' => true]);

        $invoice = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'payment_type' => 'credit',
            'items' => [
                ['item_id' => $item->id, 'quantity' => '3.000', 'unit_price' => '400.000'],
            ],
        ]);

        $item->refresh();
        $this->assertEquals('2.000', $item->current_stock);

        // Cancel invoice
        $this->invoiceService->cancelInvoice($invoice, 'طلب العميل إلغاء الطلب');

        $invoice->refresh();
        $this->assertEquals('cancelled', $invoice->status);

        $item->refresh();
        $this->assertEquals('5.000', $item->current_stock);

        $cancellationMovement = StockMovement::where('item_id', $item->id)
            ->where('movement_type', 'cancellation_in')
            ->first();
        $this->assertNotNull($cancellationMovement);
        $this->assertEquals('3.000', $cancellationMovement->quantity);
    }

    public function test_generate_unique_number_prevents_duplicate_after_soft_delete(): void
    {
        $item = Item::create([
            'code' => 'ITM-UNIQ-1',
            'name' => 'صنف اختبار فريد 1',
            'current_stock' => '50.000',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'is_active' => true,
        ]);

        $customer = Customer::create(['name' => 'عميل اختبار الأرقام الفريدة', 'is_active' => true]);

        // 1. Create first invoice (INV-YYYYMMDD-0001)
        $inv1 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'payment_type' => 'cash',
            'items' => [
                ['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '150.000'],
            ],
        ]);

        $mainStore = Store::getMainStore();
        $storeCode = $mainStore?->code ? preg_replace('/[^A-Za-z0-9]/', '', strtoupper($mainStore->code)) : 'MAIN';
        $todayPrefix = "INV-{$storeCode}-".date('Ymd');
        $this->assertEquals($todayPrefix.'-0001', $inv1->invoice_number);

        // 2. Soft-delete the first invoice
        $this->invoiceService->deleteInvoice($inv1);
        $this->assertSoftDeleted('invoices', ['id' => $inv1->id]);

        // 3. Generate number for next invoice - must NOT be 0001 again
        $nextNumber = $this->invoiceService->generateUniqueNumber();
        $this->assertEquals($todayPrefix.'-0002', $nextNumber);

        // 4. Create second invoice - must succeed without unique constraint error
        $inv2 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'payment_type' => 'cash',
            'items' => [
                ['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '150.000'],
            ],
        ]);

        $this->assertEquals($todayPrefix.'-0002', $inv2->invoice_number);
        $this->assertEquals(1, Invoice::count()); // 1 active
        $this->assertEquals(2, Invoice::withTrashed()->count()); // 2 total in DB
    }

    public function test_generate_unique_number_sequential_increment_with_deleted_records(): void
    {
        $item = Item::create([
            'code' => 'ITM-UNIQ-2',
            'name' => 'صنف اختبار فريد 2',
            'current_stock' => '50.000',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'is_active' => true,
        ]);

        $customer = Customer::create(['name' => 'عميل اختبار تسلسل الأرقام', 'is_active' => true]);
        $mainStore = Store::getMainStore();
        $storeCode = $mainStore?->code ? preg_replace('/[^A-Za-z0-9]/', '', strtoupper($mainStore->code)) : 'MAIN';
        $todayPrefix = "INV-{$storeCode}-".date('Ymd');

        // Create 3 invoices
        $inv1 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id, 'payment_type' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '150.000']],
        ]);
        $inv2 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id, 'payment_type' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '150.000']],
        ]);
        $inv3 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id, 'payment_type' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '150.000']],
        ]);

        $this->assertEquals($todayPrefix.'-0001', $inv1->invoice_number);
        $this->assertEquals($todayPrefix.'-0002', $inv2->invoice_number);
        $this->assertEquals($todayPrefix.'-0003', $inv3->invoice_number);

        // Delete invoice #2
        $this->invoiceService->deleteInvoice($inv2);

        // Create 4th invoice - must get sequence 0004 (not 0003 or 0002)
        $inv4 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id, 'payment_type' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '150.000']],
        ]);

        $this->assertEquals($todayPrefix.'-0004', $inv4->invoice_number);
    }

    public function test_multi_store_independent_invoice_numbering(): void
    {
        $storeMaadi = Store::create([
            'name' => 'فرع المعادي',
            'code' => 'SHOP-MAADI',
            'type' => 'retail_shop',
            'is_active' => true,
        ]);

        $storeVan = Store::create([
            'name' => 'عربية توزيع جملة 1',
            'code' => 'VAN-01',
            'type' => 'wholesale_van',
            'is_active' => true,
        ]);

        $item = Item::create([
            'code' => 'ITM-BRANCH-TEST',
            'name' => 'صنف اختبار الفروع',
            'current_stock' => '100.000',
            'cost_price' => '50.000',
            'selling_price' => '80.000',
            'is_active' => true,
        ]);

        $customer = Customer::create(['name' => 'عميل فروع متعددة', 'is_active' => true]);

        // Invoice 1 for Maadi Branch
        $invMaadi1 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'store_id' => $storeMaadi->id,
            'payment_type' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '80.000']],
        ]);

        // Invoice 1 for Van Branch
        $invVan1 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'store_id' => $storeVan->id,
            'payment_type' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '80.000']],
        ]);

        // Invoice 2 for Maadi Branch
        $invMaadi2 = $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'store_id' => $storeMaadi->id,
            'payment_type' => 'cash',
            'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '80.000']],
        ]);

        $today = date('Ymd');
        $this->assertEquals("INV-SHOPMAADI-{$today}-0001", $invMaadi1->invoice_number);
        $this->assertEquals("INV-VAN01-{$today}-0001", $invVan1->invoice_number);
        $this->assertEquals("INV-SHOPMAADI-{$today}-0002", $invMaadi2->invoice_number);
    }
}
