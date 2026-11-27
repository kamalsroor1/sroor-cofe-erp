<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Shifts\GetDailyJournalPrintDataAction;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

class DailyCashFlowReconciliationApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $store;

    private User $adminUser;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->store = $this->tenantStore($this->tenant);

        $this->adminUser = $this->createTenantUser($this->tenant, 'admin', attributes: [
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);

        $this->headers = $this->tenantHeaders($this->tenant, $this->adminUser, $this->store->id);
    }

    public function test_net_cash_today_deducts_cash_expenses_supplier_payments_and_cash_returns_consistently(): void
    {
        $today = now()->toDateString();

        $shift = $this->inTenant($this->tenant, function () use ($today): CashShift {
            $shift = CashShift::create([
                'user_id' => $this->adminUser->id,
                'store_id' => $this->store->id,
                'shift_number' => 'SHF-548',
                'status' => 'open',
                'opened_at' => now()->startOfDay(),
                'opening_cash_balance' => '2000.000',
            ]);

            $customer = Customer::create([
                'name' => 'عميل يومية 548',
                'phone' => '01000000548',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::create([
                'name' => 'مورد يومية 548',
                'phone' => '01000001548',
                'current_balance' => '3500.000',
                'is_active' => true,
            ]);

            // 1. Cash collections totaling 2050.000 (1250 cash sale + 800 customer collection)
            $invoice = Invoice::create([
                'store_id' => $this->store->id,
                'customer_id' => $customer->id,
                'user_id' => $this->adminUser->id,
                'cash_shift_id' => $shift->id,
                'invoice_number' => 'INV-548-01',
                'invoice_date' => $today,
                'subtotal' => '1250.000',
                'discount_amount' => '0.000',
                'tax_amount' => '0.000',
                'net_total' => '1250.000',
                'paid_amount' => '1250.000',
                'remaining_amount' => '0.000',
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'status' => 'confirmed',
            ]);

            $salePayment = new Payment([
                'payment_number' => Payment::SALE_NUMBER_PREFIX.'548-01',
                'user_id' => $this->adminUser->id,
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'store_id' => $this->store->id,
                'amount' => '1250.000',
                'payment_method' => 'cash',
                'payment_date' => $today,
            ]);
            $salePayment->cash_shift_id = $shift->id;
            $salePayment->save();

            $collectionPayment = new Payment([
                'payment_number' => 'PAY-548-02',
                'user_id' => $this->adminUser->id,
                'customer_id' => $customer->id,
                'store_id' => $this->store->id,
                'amount' => '800.000',
                'payment_method' => 'cash',
                'payment_date' => $today,
                'notes' => 'تحصيل عميل',
            ]);
            $collectionPayment->cash_shift_id = $shift->id;
            $collectionPayment->save();

            // 2. Cash expense: 3.000
            $expense = new Expense([
                'store_id' => $this->store->id,
                'user_id' => $this->adminUser->id,
                'expense_number' => 'EXP-548-01',
                'title' => 'مصروف نثري',
                'amount' => '3.000',
                'category' => 'تشغيلي',
                'cost_center' => 'فرع رئيسي',
                'expense_date' => $today,
                'payment_method' => 'cash',
            ]);
            $expense->cash_shift_id = $shift->id;
            $expense->save();

            // 3. Cash supplier payment: 3500.000
            $supplierPayment = new Payment([
                'payment_number' => 'PAY-SUP-548-01',
                'user_id' => $this->adminUser->id,
                'supplier_id' => $supplier->id,
                'store_id' => $this->store->id,
                'amount' => '3500.000',
                'payment_method' => 'cash',
                'payment_date' => $today,
                'notes' => 'سداد مورد نقدي',
            ]);
            $supplierPayment->cash_shift_id = $shift->id;
            $supplierPayment->save();

            // 4. Cash sales return refund voucher: -200.000 (cash paid back out of the drawer)
            $refundPayment = new Payment([
                'payment_number' => 'PAY-RET-548-01',
                'user_id' => $this->adminUser->id,
                'customer_id' => $customer->id,
                'invoice_id' => $invoice->id,
                'store_id' => $this->store->id,
                'amount' => '-200.000',
                'payment_method' => 'cash',
                'payment_date' => $today,
                'notes' => 'رد نقدي لمرتجع مبيعات',
            ]);
            $refundPayment->cash_shift_id = $shift->id;
            $refundPayment->save();

            return $shift;
        });

        // Formula verification: 2050 - 3 - 3500 - 200 = -1653.000
        // Opening 2000.000 + (-1653.000) = 347.000 expected cash in drawer
        $journal = $this->getJson('/api/v1/daily-journal?date='.$today, $this->headers);
        $journal->assertOk()
            ->assertJsonPath('data.summary.total_cash_in', '2050.000')
            ->assertJsonPath('data.summary.cash_expenses', '3.000')
            ->assertJsonPath('data.summary.supplier_payments', '3500.000')
            ->assertJsonPath('data.summary.cash_refunds', '200.000')
            ->assertJsonPath('data.summary.total_cash_out', '3703.000')
            ->assertJsonPath('data.summary.net_cash_today', '-1653.000')
            ->assertJsonPath('data.summary.opening_cash_balance', '2000.000')
            ->assertJsonPath('data.summary.expected_cash_in_drawer', '347.000');

        $dashboard = $this->getJson('/api/v1/dashboard', $this->headers);
        $dashboard->assertOk()
            ->assertJsonPath('data.metrics.total_cash_collected', '2050.000')
            ->assertJsonPath('data.metrics.today_expenses', '3.000')
            ->assertJsonPath('data.metrics.supplier_payments', '3500.000')
            ->assertJsonPath('data.metrics.net_cash_today', '-1653.000')
            ->assertJsonPath('data.active_shift.current_cash', '347.000');

        $treasury = $this->getJson('/api/v1/treasury/summary', $this->headers);
        $treasury->assertOk()
            ->assertJsonPath('today.total_inflow', '2050.000')
            ->assertJsonPath('today.cash_expenses', '3.000')
            ->assertJsonPath('today.supplier_paid', '3500.000')
            ->assertJsonPath('today.cash_refunds', '200.000')
            ->assertJsonPath('today.total_outflow', '3703.000')
            ->assertJsonPath('today.net_cash', '-1653.000');

        [$printData, $html] = $this->inTenant($this->tenant, function () use ($today): array {
            $data = app(GetDailyJournalPrintDataAction::class)->execute($today, (int) $this->store->id);

            return [$data, view('layouts.print-daily-journal-a4', $data)->render()];
        });
        $this->assertSame('200.000', $printData['totalCashRefunds']);
        $this->assertSame('-1653.000', $printData['netCashToday']);
        $this->assertSame('347.000', $printData['expectedCashInDrawer']);

        $this->assertStringContainsString((string) __('treasury.cash_refunds_label'), $html);
        $this->assertStringContainsString('-200.00', $html);

        $this->assertSame((int) $shift->id, $journal->json('data.active_shift.id'));
    }
}
