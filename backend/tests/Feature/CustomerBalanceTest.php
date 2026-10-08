<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\User;
use App\Services\CustomerBalanceService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBalanceTest extends TestCase
{
    use RefreshDatabase;

    protected InvoiceService $invoiceService;

    protected PaymentService $paymentService;

    protected CustomerBalanceService $balanceService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoiceService = app(InvoiceService::class);
        $this->paymentService = app(PaymentService::class);
        $this->balanceService = app(CustomerBalanceService::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_customer_balance_calculation_with_invoices_and_payments(): void
    {
        $item = Item::create([
            'code' => 'ITM-BAL',
            'name' => 'طابعة حرارية 80مم',
            'current_stock' => '20.000',
            'cost_price' => '1000.000',
            'selling_price' => '1500.000',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => 'هايبر ماركت السلام',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        // Credit Invoice: 2 * 1500 = 3000.000 (Customer owes 3000)
        $this->invoiceService->confirmInvoice([
            'customer_id' => $customer->id,
            'payment_type' => 'credit',
            'items' => [
                ['item_id' => $item->id, 'quantity' => '2.000', 'unit_price' => '1500.000'],
            ],
        ]);

        $customer->refresh();
        $this->assertEquals('3000.000', $customer->current_balance);

        // Payment: 1000.000
        $this->paymentService->recordCustomerPayment([
            'customer_id' => $customer->id,
            'amount' => '1000.000',
        ]);

        $customer->refresh();
        $this->assertEquals('2000.000', $customer->current_balance);
    }
}
