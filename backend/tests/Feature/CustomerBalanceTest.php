<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CustomerBalanceService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TenantTestCase;

class CustomerBalanceTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant);
    }

    public function test_customer_balance_calculation_with_invoices_and_payments(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $invoiceService = app(InvoiceService::class);
            $paymentService = app(PaymentService::class);
            app(CustomerBalanceService::class);

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
            $invoiceService->confirmInvoice([
                'customer_id' => $customer->id,
                'payment_type' => 'credit',
                'items' => [
                    ['item_id' => $item->id, 'quantity' => '2.000', 'unit_price' => '1500.000'],
                ],
            ]);

            $customer->refresh();
            $this->assertEquals('3000.000', $customer->current_balance);

            // Payment: 1000.000
            $paymentService->recordCustomerPayment([
                'customer_id' => $customer->id,
                'amount' => '1000.000',
            ]);

            $customer->refresh();
            $this->assertEquals('2000.000', $customer->current_balance);
        });
    }

    public function test_a_payment_in_one_tenant_cannot_touch_another_tenants_customer_balance(): void
    {
        $other = $this->createTenant();
        $foreignCustomerId = $this->inTenant($other, fn (): int => Customer::create([
            'name' => 'عميل مستأجر آخر',
            'current_balance' => '750.000',
            'is_active' => true,
        ])->id);

        $this->inTenant($this->tenant, function () use ($foreignCustomerId): void {
            $this->actingAs(User::findOrFail($this->user->id));

            try {
                app(PaymentService::class)->recordCustomerPayment([
                    'customer_id' => $foreignCustomerId,
                    'amount' => '100.000',
                ]);
                $this->fail('A payment for a customer id that only exists in another tenant was accepted.');
            } catch (ModelNotFoundException) {
                // expected: the id does not exist in this tenant's database
            }

            $this->assertSame(0, Payment::query()->count());
        });

        $this->inTenant($other, function () use ($foreignCustomerId): void {
            $this->assertSame('750.000', (string) Customer::findOrFail($foreignCustomerId)->current_balance);
            $this->assertSame(0, Payment::query()->count());
        });
    }
}
