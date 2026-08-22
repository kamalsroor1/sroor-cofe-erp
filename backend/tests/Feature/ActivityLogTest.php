<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Store;
use App\Models\Item;
use App\Models\Customer;
use App\Models\ActivityLog;
use App\Services\InvoiceService;
use App\Services\ShiftService;
use App\Services\StockTransferService;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use App\Livewire\ActivityLogIndex;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $cashier;
    protected Store $mainStore;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);

        $this->mainStore = Store::create([
            'name'       => 'المخزن الرئيسي',
            'code'       => 'MAIN',
            'type'       => 'main_warehouse',
            'is_default' => true,
            'is_active'  => true,
        ]);

        $this->admin = User::factory()->create([
            'name'             => 'كمال سرور',
            'phone'            => '01012316954',
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->admin->assignRole('admin');

        $this->cashier = User::factory()->create([
            'name'             => 'أحمد كاشير',
            'phone'            => '01099998888',
            'is_active'        => true,
            'default_store_id' => $this->mainStore->id,
        ]);
        $this->cashier->assignRole('cashier');
    }

    public function test_unauthorized_user_cannot_view_activity_logs()
    {
        $token = $this->cashier->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/activity-logs')
            ->assertStatus(403);
    }

    public function test_admin_can_view_activity_logs()
    {
        $response = $this->actingAs($this->admin)->get(route('activity-logs.index'));
        $response->assertStatus(200);
    }

    public function test_sales_invoice_creation_generates_activity_log()
    {
        $item = Item::create([
            'name'           => 'بن كولومبي فاخر',
            'code'           => 'COL01',
            'cost_price'     => '100.000',
            'selling_price'  => '150.000',
            'current_stock'  => '50.000',
            'min_stock_level'=> '5.000',
            'unit'           => 'kg',
            'is_active'      => true,
        ]);

        $customer = Customer::create([
            'name'           => 'كافيه رويال',
            'phone'          => '01011112222',
            'current_balance'=> '0.000',
            'is_active'      => true,
        ]);

        $this->actingAs($this->admin);

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->confirmInvoice([
            'customer_id'   => $customer->id,
            'store_id'      => $this->mainStore->id,
            'invoice_date'  => now()->toDateString(),
            'payment_type'  => 'cash',
            'items'         => [
                [
                    'item_id'         => $item->id,
                    'quantity'        => '2.000',
                    'unit_price'      => '150.000',
                    'discount_amount' => '0.000',
                ]
            ],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module'      => 'sales',
            'action'      => 'created',
            'store_id'    => $this->mainStore->id,
            'user_id'     => $this->admin->id,
        ]);

        $log = ActivityLog::where('module', 'sales')->where('action', 'created')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString($invoice->invoice_number, $log->description);
        $this->assertStringContainsString('كافيه رويال', $log->description);
    }

    public function test_sales_invoice_cancellation_generates_activity_log()
    {
        $item = Item::create([
            'name'           => 'بن برازيلي مطحون',
            'code'           => 'BRZ01',
            'cost_price'     => '120.000',
            'selling_price'  => '160.000',
            'current_stock'  => '50.000',
            'min_stock_level'=> '5.000',
            'unit'           => 'kg',
            'is_active'      => true,
        ]);

        $customer = Customer::create([
            'name'           => 'عميل تجريبي',
            'phone'          => '01033334444',
            'current_balance'=> '0.000',
            'is_active'      => true,
        ]);

        $this->actingAs($this->admin);

        /** @var InvoiceService $invoiceService */
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->confirmInvoice([
            'customer_id'   => $customer->id,
            'store_id'      => $this->mainStore->id,
            'invoice_date'  => now()->toDateString(),
            'payment_type'  => 'cash',
            'items'         => [
                [
                    'item_id'         => $item->id,
                    'quantity'        => '1.000',
                    'unit_price'      => '160.000',
                    'discount_amount' => '0.000',
                ]
            ],
        ]);

        $invoiceService->cancelInvoice($invoice, 'خطأ في نوع البن');

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'sales',
            'action' => 'cancelled',
        ]);

        $log = ActivityLog::where('module', 'sales')->where('action', 'cancelled')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('إلغاء', $log->description);
        $this->assertStringContainsString('خطأ في نوع البن', $log->description);
    }

    public function test_shift_opened_and_closed_generates_activity_log()
    {
        $this->actingAs($this->admin);

        /** @var ShiftService $shiftService */
        $shiftService = app(ShiftService::class);
        $shift = $shiftService->openShift('500.000', 'شفت الصباح', $this->mainStore->id);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'shifts',
            'action' => 'shift_opened',
        ]);

        $shiftService->closeShift($shift, '500.000', 'تقفيل سليم');

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'shifts',
            'action' => 'shift_closed',
        ]);
    }

    public function test_stock_transfer_generates_activity_log()
    {
        $branchStore = Store::create([
            'name'       => 'فرع المعادي',
            'code'       => 'MADI',
            'type'       => 'branch',
            'is_active'  => true,
        ]);

        $item = Item::create([
            'name'           => 'بن حبوب كاملة',
            'code'           => 'WBN01',
            'cost_price'     => '200.000',
            'selling_price'  => '260.000',
            'current_stock'  => '100.000',
            'min_stock_level'=> '10.000',
            'unit'           => 'kg',
            'is_active'      => true,
        ]);

        \App\Models\StoreStock::create([
            'store_id'  => $this->mainStore->id,
            'item_id'   => $item->id,
            'quantity'  => '100.000',
            'min_stock' => '5.000',
        ]);

        $this->actingAs($this->admin);

        /** @var StockTransferService $transferService */
        $transferService = app(StockTransferService::class);
        $transfer = $transferService->createTransfer([
            'from_store_id' => $this->mainStore->id,
            'to_store_id'   => $branchStore->id,
            'transfer_date' => now()->toDateString(),
            'items'         => [
                [
                    'item_id'  => $item->id,
                    'quantity' => '10.000',
                ]
            ],
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'inventory',
            'action' => 'created',
        ]);
    }

    public function test_livewire_activity_log_filters_and_export()
    {
        $this->actingAs($this->admin);

        ActivityLog::create([
            'user_id'      => $this->admin->id,
            'store_id'     => $this->mainStore->id,
            'module'       => 'sales',
            'action'       => 'created',
            'description'  => 'فاتورة مبيعات مميزة للمعاينة',
            'properties'   => ['amount' => '120.000'],
        ]);

        Livewire::test(ActivityLogIndex::class)
            ->assertSee('سجل العمليات والرقابة الذاتية')
            ->assertSee('فاتورة مبيعات مميزة للمعاينة')
            ->set('search', 'فاتورة مبيعات مميزة')
            ->assertSee('فاتورة مبيعات مميزة للمعاينة')
            ->set('search', 'نص غير موجود إطلاقاً')
            ->assertSee('لا توجد عمليات مسجلة تطابق بحثك');
    }
}
