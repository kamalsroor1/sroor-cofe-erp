<?php

namespace Tests\Feature;

use App\Livewire\InvoiceEdit;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\TreasuryService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Editing an invoice that was already (partly) collected by a separate customer receipt
 * (سند قبض) must not collect that money again.
 */
class InvoiceEditReceiptSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoiceService;
    protected PaymentService $paymentService;
    protected Customer $customer;
    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceService = app(InvoiceService::class);
        $this->paymentService = app(PaymentService::class);
        $this->actingAs(User::factory()->create());

        $this->item = Item::create([
            'code'          => 'ITM-RCPT',
            'name'          => 'بن تجربة',
            'current_stock' => '100.000',
            'cost_price'    => '100.000',
            'selling_price' => '200.000',
            'is_active'     => true,
        ]);
        $this->customer = Customer::create(['name' => 'عميل تجربة', 'current_balance' => '0.000', 'is_active' => true]);
    }

    private function creditInvoice(string $qty): Invoice
    {
        return $this->invoiceService->confirmInvoice([
            'customer_id'  => $this->customer->id,
            'payment_type' => 'credit',
            'items'        => [['item_id' => $this->item->id, 'quantity' => $qty, 'unit_price' => '200.000']],
        ]);
    }

    private function edit(Invoice $invoice, string $type, string $qty, array $extra = []): Invoice
    {
        return $this->invoiceService->updateInvoice($invoice->fresh(), array_merge([
            'customer_id'  => $this->customer->id,
            'payment_type' => $type,
            'items'        => [['item_id' => $this->item->id, 'quantity' => $qty, 'unit_price' => '200.000']],
        ], $extra));
    }

    private function livePaymentsTotal(): string
    {
        return bcadd((string) Payment::where('customer_id', $this->customer->id)->sum('amount'), '0', 3);
    }

    public function test_switching_a_receipt_settled_credit_invoice_to_cash_does_not_collect_twice(): void
    {
        $invoice = $this->creditInvoice('5.000'); // 1000
        $this->paymentService->recordCustomerPayment(['customer_id' => $this->customer->id, 'amount' => '1000.000']);

        $this->assertSame('1000.000', (string) $invoice->fresh()->paid_amount);
        $this->assertSame('1000.000', $invoice->fresh()->receiptSettledAmount());

        $invoice = $this->edit($invoice, 'cash', '5.000');

        $this->assertSame('0.000', (string) $this->customer->fresh()->current_balance);
        $this->assertSame('1000.000', $this->livePaymentsTotal());
        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->where('payment_number', 'like', 'PAY-INV-%')->count());
        $this->assertSame('paid', $invoice->fresh()->payment_status);
        $this->assertSame('1000.000', (string) app(TreasuryService::class)->getBalances()['cash']['balance']);
    }

    public function test_editing_a_receipt_settled_credit_invoice_keeps_it_paid(): void
    {
        $invoice = $this->creditInvoice('5.000');
        $this->paymentService->recordCustomerPayment(['customer_id' => $this->customer->id, 'amount' => '1000.000']);

        $invoice = $this->edit($invoice, 'credit', '5.000', ['notes' => 'تعديل ملاحظة']);

        $fresh = $invoice->fresh();
        $this->assertSame('1000.000', (string) $fresh->paid_amount);
        $this->assertSame('0.000', (string) $fresh->remaining_amount);
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertSame('0.000', (string) $this->customer->fresh()->current_balance);
        $this->assertSame('1000.000', $this->livePaymentsTotal());
    }

    public function test_partly_settled_invoice_switched_to_cash_only_collects_the_rest(): void
    {
        $invoice = $this->creditInvoice('5.000'); // 1000
        $this->paymentService->recordCustomerPayment(['customer_id' => $this->customer->id, 'amount' => '600.000']);

        $invoice = $this->edit($invoice, 'cash', '5.000');

        $voucher = Payment::where('invoice_id', $invoice->id)->where('payment_number', 'like', 'PAY-INV-%')->sole();
        $this->assertSame('400.000', (string) $voucher->amount);
        $this->assertSame('1000.000', $this->livePaymentsTotal());
        $this->assertSame('0.000', (string) $this->customer->fresh()->current_balance);
    }

    public function test_reducing_a_settled_invoice_below_the_receipt_leaves_the_excess_as_customer_credit(): void
    {
        $invoice = $this->creditInvoice('5.000'); // 1000
        $this->paymentService->recordCustomerPayment(['customer_id' => $this->customer->id, 'amount' => '1000.000']);

        $invoice = $this->edit($invoice, 'cash', '3.000'); // 600

        $fresh = $invoice->fresh();
        $this->assertSame('600.000', (string) $fresh->paid_amount);
        $this->assertSame('paid', $fresh->payment_status);
        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->where('payment_number', 'like', 'PAY-INV-%')->count());
        $this->assertSame('-400.000', (string) $this->customer->fresh()->current_balance);
    }

    public function test_customer_cannot_be_changed_on_a_receipt_settled_invoice(): void
    {
        $invoice = $this->creditInvoice('5.000');
        $this->paymentService->recordCustomerPayment(['customer_id' => $this->customer->id, 'amount' => '1000.000']);
        $other = Customer::create(['name' => 'عميل آخر', 'current_balance' => '0.000', 'is_active' => true]);

        try {
            $this->edit($invoice, 'cash', '5.000', ['customer_id' => $other->id]);
            $this->fail('Expected the customer change to be refused');
        } catch (Exception $e) {
            $this->assertStringContainsString('لا يمكن تغيير عميل الفاتورة', $e->getMessage());
        }

        $this->assertSame($this->customer->id, $invoice->fresh()->customer_id);
        $this->assertSame('0.000', (string) $this->customer->fresh()->current_balance);
    }

    public function test_plain_cash_invoice_edit_still_replaces_its_own_voucher(): void
    {
        $invoice = $this->invoiceService->confirmInvoice([
            'customer_id'  => $this->customer->id,
            'payment_type' => 'cash',
            'items'        => [['item_id' => $this->item->id, 'quantity' => '5.000', 'unit_price' => '200.000']],
        ]);

        $invoice = $this->edit($invoice, 'cash', '4.000'); // 800

        $this->assertSame('800.000', $this->livePaymentsTotal());
        $this->assertSame('0.000', (string) $this->customer->fresh()->current_balance);
        $this->assertSame('0.000', $invoice->fresh()->receiptSettledAmount());
    }

    public function test_edit_screen_shows_the_receipt_settled_amount_as_paid(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('invoices.edit', 'web');
        $user->givePermissionTo('invoices.edit');
        $this->actingAs($user);

        $invoice = $this->creditInvoice('5.000');
        $this->paymentService->recordCustomerPayment(['customer_id' => $this->customer->id, 'amount' => '1000.000']);

        Livewire::test(InvoiceEdit::class, ['id' => $invoice->id])
            ->assertSet('payment_type', 'credit')
            ->assertSet('receipt_settled', '1000.000')
            ->call('calculateTotals')
            ->assertSet('paid_amount', '1000.000')
            ->assertSet('remaining_amount', '0.000')
            ->assertSee('بسند قبض من حساب العميل');
    }
}
