<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Item;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Invoice;
use App\Models\Expense;
use App\Models\CashShift;
use App\Services\InvoiceService;
use App\Services\ShiftService;
use App\Livewire\InvoiceEdit;
use App\Livewire\DailyJournalIndex;
use Livewire\Livewire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class InvoiceEditAndDailyJournalTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Customer $customer;
    protected Item $coffeeItem;
    protected Item $teaItem;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin']);

        $this->admin = User::create([
            'name'      => 'كمال سرور',
            'phone'     => '01012316954',
            'email'     => 'admin@sroor.com',
            'password'  => bcrypt('password'),
            'is_active' => true,
        ]);
        $this->admin->assignRole('admin');

        $this->customer = Customer::create([
            'name'            => 'عميل مميز للبن',
            'phone'           => '01099999999',
            'current_balance' => '0.000',
            'is_active'       => true,
        ]);

        $this->coffeeItem = Item::create([
            'code'              => 'COF-BRAZIL',
            'name'              => 'بن برازيلي وسط',
            'category'          => 'بن وتوليفات',
            'unit'              => 'كجم',
            'current_stock'     => '100.000',
            'cost_price'        => '200.000',
            'weighted_avg_cost' => '200.000',
            'selling_price'     => '250.000',
            'min_stock_level'   => '10.000',
            'is_active'         => true,
        ]);

        $this->teaItem = Item::create([
            'code'              => 'TEA-CEYLON',
            'name'              => 'شاي سيلاني فاخر',
            'category'          => 'شاي وأعشاب',
            'unit'              => 'كجم',
            'current_stock'     => '50.000',
            'cost_price'        => '120.000',
            'weighted_avg_cost' => '120.000',
            'selling_price'     => '180.000',
            'min_stock_level'   => '5.000',
            'is_active'         => true,
        ]);
    }

    public function test_editing_confirmed_invoice_reverses_old_stock_and_applies_new_stock_and_recalculates_balance()
    {
        $this->actingAs($this->admin);

        // InvoiceEdit requires a branch; mirror production where every item lives in the main store.
        $mainStore = \App\Models\Store::create([
            'name'      => 'المخزن الرئيسي',
            'code'      => 'MAIN-01',
            'type'      => 'main',
            'is_active' => true,
            'is_main'   => true,
        ]);
        foreach ([$this->coffeeItem, $this->teaItem] as $stockedItem) {
            \App\Models\StoreStock::create([
                'store_id' => $mainStore->id,
                'item_id'  => $stockedItem->id,
                'quantity' => $stockedItem->current_stock,
            ]);
        }

        $invoiceService = app(InvoiceService::class);

        // 1. Create initial invoice: 10 kg coffee on credit (Total = 2500)
        $invoice = $invoiceService->confirmInvoice([
            'customer_id'   => $this->customer->id,
            'payment_type'  => 'credit',
            'discount_type' => 'fixed',
            'discount_value'=> '0.000',
            'items'         => [
                [
                    'item_id'    => $this->coffeeItem->id,
                    'quantity'   => '10.000',
                    'unit_price' => '250.000',
                ]
            ]
        ]);

        $this->coffeeItem->refresh();
        $this->customer->refresh();
        $this->assertEquals('90.000', $this->coffeeItem->current_stock); // 100 - 10 = 90
        $this->assertEquals('2500.000', $this->customer->current_balance);

        // 2. Edit the invoice via Livewire: change coffee to 5 kg, and add 2 kg tea
        Livewire::test(InvoiceEdit::class, ['id' => $invoice->id])
            ->set('items.0.quantity', '5.000') // 5 kg coffee * 250 = 1250
            ->call('addItem', $this->teaItem->id, '2.000') // 2 kg tea * 180 = 360 -> Total = 1610
            ->call('saveInvoice')
            ->assertHasNoErrors();

        // 3. Verify stock:
        // Coffee: 100 - 5 = 95.000
        // Tea: 50 - 2 = 48.000
        $this->coffeeItem->refresh();
        $this->teaItem->refresh();
        $this->customer->refresh();

        $this->assertEquals('95.000', $this->coffeeItem->current_stock);
        $this->assertEquals('48.000', $this->teaItem->current_stock);
        $this->assertEquals('1610.000', $this->customer->current_balance);

        $invoice->refresh();
        $this->assertEquals('1610.000', $invoice->net_total);
    }

    public function test_daily_journal_aggregates_daily_sales_expenses_and_drawer_cash()
    {
        $this->actingAs($this->admin);

        $today = now()->toDateString();
        $invoiceService = app(InvoiceService::class);

        // 1. Create a cash invoice of 500
        $invoiceService->confirmInvoice([
            'customer_id'   => $this->customer->id,
            'invoice_date'  => $today,
            'payment_type'  => 'cash',
            'discount_type' => 'fixed',
            'discount_value'=> '0.000',
            'items'         => [
                [
                    'item_id'    => $this->coffeeItem->id,
                    'quantity'   => '2.000',
                    'unit_price' => '250.000',
                ]
            ]
        ]);

        // 2. Create an expense of 75 for bags (شنط وأكياس)
        Expense::create([
            'expense_number' => 'EXP-TEST-001',
            'category'       => 'شنط وأكياس',
            'title'          => 'شنط مطبوعة 1كجم',
            'amount'         => '75.000',
            'expense_date'   => $today,
            'payment_method' => 'cash',
            'user_id'        => $this->admin->id,
        ]);

        // 3. Test DailyJournalIndex component
        Livewire::test(DailyJournalIndex::class)
            ->assertSee('500.00') // total sales & cash collected
            ->assertSee('75.00')  // expense
            ->assertSee('425.00') // net drawer cash (500 - 75 = 425)
            ->assertHasNoErrors();
    }

    public function test_daily_shift_open_and_close_flow()
    {
        $this->actingAs($this->admin);

        $shiftService = app(ShiftService::class);

        Livewire::test(DailyJournalIndex::class)
            ->set('opening_cash_balance', '300.000')
            ->set('open_notes', 'رصيد الفكة للدرج')
            ->call('startShift')
            ->assertHasNoErrors();

        $active = $shiftService->getActiveShift();
        $this->assertNotNull($active);
        $this->assertEquals('300.000', $active->opening_cash_balance);

        Livewire::test(DailyJournalIndex::class)
            ->call('openCloseModal')
            ->set('actual_cash_balance', '300.000')
            ->set('close_notes', 'تقفيل نهاية اليوم')
            ->call('submitCloseShift')
            ->assertHasNoErrors();

        $this->assertNull($shiftService->getActiveShift());
    }

    public function test_opening_cash_plus_sales_calculates_exact_expected_drawer_cash()
    {
        $this->actingAs($this->admin);

        $shiftService = app(ShiftService::class);
        $invoiceService = app(InvoiceService::class);

        // 1. Open shift with 500 LE
        $shiftService->openShift('500.000', 'افتتاح الوردية');

        // 2. Make cash sale of 250 LE
        $invoiceService->confirmInvoice([
            'customer_id'   => $this->customer->id,
            'invoice_date'  => now()->toDateString(),
            'payment_type'  => 'cash',
            'discount_type' => 'fixed',
            'discount_value'=> '0.000',
            'items'         => [
                [
                    'item_id'    => $this->coffeeItem->id,
                    'quantity'   => '1.000',
                    'unit_price' => '250.000',
                ]
            ]
        ]);

        // 3. Daily Journal MUST show 750.00 LE (500 + 250)
        Livewire::test(DailyJournalIndex::class)
            ->assertSee('500.00') // opening balance
            ->assertSee('250.00') // cash sales
            ->assertSee('750.00') // total expected in drawer
            ->assertHasNoErrors();
    }
}
