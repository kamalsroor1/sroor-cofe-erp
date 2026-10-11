<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Shifts\GetDailyJournalAction;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ShiftService;
use App\Support\TenantClock;
use Closure;
use Tests\TenantTestCase;

/**
 * BUG-01: the shift "expected cash" counted every cash sale twice (once from the invoice,
 * once from the payment voucher the invoice writes) and added cash payments of every store.
 * The drawer is computed from the cash payment rows of the shift's store and time window.
 */
final class ShiftExpectedCashTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $adminId;

    private int $storeA;

    private int $storeB;

    private int $itemId;

    private int $customerId;

    private int $supplierId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->adminId = (int) $this->tenantAdmin($this->tenant)->id;
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;

        [$this->storeB, $this->itemId, $this->customerId, $this->supplierId] = $this->inTenant($this->tenant, function (): array {
            $storeB = Store::create([
                'name' => 'فرع ب',
                'code' => 'BR-B',
                'type' => 'branch',
                'is_active' => true,
                'is_main' => false,
            ]);

            User::query()->findOrFail($this->adminId)->stores()->syncWithoutDetaching([$this->storeA, $storeB->id]);

            $item = Item::create([
                'code' => 'COF-SHIFT',
                'name' => 'بن تجربة',
                'category' => 'بن سادة',
                'unit' => 'كجم',
                'current_stock' => '200.000',
                'cost_price' => '50.000',
                'weighted_avg_cost' => '50.000',
                'selling_price' => '100.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);

            foreach ([$this->storeA, $storeB->id] as $storeId) {
                StoreStock::create([
                    'store_id' => $storeId,
                    'item_id' => $item->id,
                    'quantity' => '100.000',
                    'min_stock' => '0.000',
                ]);
            }

            $customer = Customer::create([
                'name' => 'عميل الوردية',
                'phone' => '01000007301',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::create([
                'name' => 'مورد الوردية',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            return [(int) $storeB->id, (int) $item->id, (int) $customer->id, (int) $supplier->id];
        });
    }

    private function asAdmin(Closure $callback): mixed
    {
        return $this->inTenant($this->tenant, function () use ($callback) {
            $this->actingAs(User::query()->findOrFail($this->adminId));

            return $callback();
        });
    }

    private function openShift(int $storeId, string $openingCash): CashShift
    {
        $shift = app(ShiftService::class)->openShift($openingCash, null, $storeId);
        // The shift starts strictly before anything recorded by the test.
        $shift->forceFill(['opened_at' => now()->subMinute()])->save();

        return $shift->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function sell(int $storeId, string $quantity, array $overrides = []): Invoice
    {
        return app(InvoiceService::class)->confirmInvoice(array_merge([
            'customer_id' => $this->customerId,
            'store_id' => $storeId,
            'payment_type' => 'cash',
            'items' => [[
                'item_id' => $this->itemId,
                'quantity' => $quantity,
                'unit_price' => '100.000',
            ]],
        ], $overrides));
    }

    private function receipt(int $storeId, string $amount, ?int $invoiceId = null, string $method = 'cash'): void
    {
        app(PaymentService::class)->recordCustomerPayment([
            'customer_id' => $this->customerId,
            'store_id' => $storeId,
            'invoice_id' => $invoiceId,
            'amount' => $amount,
            'payment_method' => $method,
        ]);
    }

    public function test_expected_cash_counts_each_sale_once_and_only_the_shift_store(): void
    {
        $this->asAdmin(function (): void {
            $shift = $this->openShift($this->storeA, '500.000');

            $this->sell($this->storeA, '1.000');   // 100.000 cash in A
            $this->sell($this->storeB, '1.000');   // 100.000 cash in B: not this drawer
            $this->receipt($this->storeA, '50.000'); // debt collection in A
            $this->receipt($this->storeB, '70.000'); // debt collection in B: not this drawer

            $totals = app(ShiftService::class)->calculateShiftTotals($shift->refresh());

            $this->assertSame('500.000', $totals['opening_cash_balance']);
            $this->assertSame('100.000', $totals['total_cash_sales']);
            $this->assertSame('50.000', $totals['total_customer_receipts']);
            $this->assertSame('150.000', $totals['total_payments_collected']);
            $this->assertSame('650.000', $totals['expected_cash_balance']);
        });
    }

    public function test_close_shift_stores_the_corrected_expected_cash_and_difference(): void
    {
        $this->asAdmin(function (): void {
            $shift = $this->openShift($this->storeA, '500.000');
            $this->sell($this->storeA, '1.000');
            $this->sell($this->storeB, '1.000');
            $this->receipt($this->storeA, '50.000');

            $closed = app(ShiftService::class)->closeShift($shift->refresh(), '650.000');

            $this->assertSame('650.000', (string) $closed->expected_cash_balance);
            $this->assertSame('0.000', (string) $closed->cash_difference);
            $this->assertSame('100.000', (string) $closed->total_cash_sales);
            $this->assertSame('150.000', (string) $closed->total_payments_collected);
        });
    }

    public function test_a_receipt_against_a_partial_invoice_of_the_same_shift_is_not_counted_twice(): void
    {
        $this->asAdmin(function (): void {
            $shift = $this->openShift($this->storeA, '0.000');

            $invoice = $this->sell($this->storeA, '1.000', ['payment_type' => 'partial', 'paid_amount' => '40.000']);
            $this->receipt($this->storeA, '60.000', (int) $invoice->id);

            $totals = app(ShiftService::class)->calculateShiftTotals($shift->refresh());

            $this->assertSame('40.000', $totals['total_cash_sales']);
            $this->assertSame('60.000', $totals['total_customer_receipts']);
            $this->assertSame('100.000', $totals['expected_cash_balance']);
        });
    }

    public function test_only_the_cash_part_of_a_split_sale_reaches_the_drawer(): void
    {
        $this->asAdmin(function (): void {
            $shift = $this->openShift($this->storeA, '10.000');

            // 0.250 kg x 100.000 = 25.000: 15.000 cash + 10.000 instapay.
            $this->sell($this->storeA, '0.250', [
                'payments' => [
                    ['method' => 'cash', 'amount' => '15.000'],
                    ['method' => 'instapay', 'amount' => '10.000'],
                ],
            ]);
            $this->receipt($this->storeA, '30.000', null, 'instapay');

            $totals = app(ShiftService::class)->calculateShiftTotals($shift->refresh());

            $this->assertSame('15.000', $totals['total_cash_sales']);
            $this->assertSame('0.000', $totals['total_customer_receipts']);
            $this->assertSame('25.000', $totals['expected_cash_balance']);
        });
    }

    public function test_cash_received_before_the_shift_opened_or_on_a_cancelled_invoice_is_excluded(): void
    {
        $this->asAdmin(function (): void {
            $this->receipt($this->storeA, '80.000'); // before the shift

            $shift = app(ShiftService::class)->openShift('100.000', null, $this->storeA);
            $shift->forceFill(['opened_at' => now()->addSecond()])->save();
            $this->travel(5)->seconds();

            $kept = $this->sell($this->storeA, '1.000');
            $cancelled = $this->sell($this->storeA, '2.000');
            app(InvoiceService::class)->cancelInvoice($cancelled, 'test');

            $totals = app(ShiftService::class)->calculateShiftTotals($shift->refresh());

            $this->assertNotNull($kept->id);
            $this->assertSame('100.000', $totals['total_cash_sales']);
            $this->assertSame('0.000', $totals['total_customer_receipts']);
            $this->assertSame('200.000', $totals['expected_cash_balance']);
        });
    }

    public function test_supplier_cash_paid_from_another_store_does_not_reduce_this_drawer(): void
    {
        $this->asAdmin(function (): void {
            $shift = $this->openShift($this->storeA, '500.000');

            foreach ([[$this->storeA, '30.000'], [$this->storeB, '45.000']] as [$storeId, $amount]) {
                app(PaymentService::class)->recordSupplierPayment([
                    'supplier_id' => $this->supplierId,
                    'store_id' => $storeId,
                    'amount' => $amount,
                    'payment_method' => 'cash',
                ]);
            }

            $totals = app(ShiftService::class)->calculateShiftTotals($shift->refresh());

            $this->assertSame('30.000', $totals['total_supplier_paid']);
            $this->assertSame('470.000', $totals['expected_cash_balance']);
        });
    }

    public function test_a_receipt_without_explicit_store_is_booked_to_the_active_store(): void
    {
        $this->asAdmin(function (): void {
            session(['current_store_id' => $this->storeB]);

            $payment = app(PaymentService::class)->recordCustomerPayment([
                'customer_id' => $this->customerId,
                'amount' => '20.000',
                'payment_method' => 'cash',
            ]);

            $this->assertSame($this->storeB, (int) $payment->store_id);
        });
    }

    public function test_a_legacy_receipt_without_store_belongs_to_its_users_default_store_only(): void
    {
        $this->asAdmin(function (): void {
            User::query()->findOrFail($this->adminId)->update(['default_store_id' => $this->storeB]);

            $shiftA = $this->openShift($this->storeA, '0.000');
            $shiftB = $this->openShift($this->storeB, '0.000');

            // A voucher written before payments.store_id existed.
            Payment::create([
                'payment_number' => 'PAY-CUST-LEGACY',
                'customer_id' => $this->customerId,
                'user_id' => $this->adminId,
                'amount' => '12.500',
                'payment_date' => app(TenantClock::class)->businessDate(),
                'payment_method' => 'cash',
            ]);

            $service = app(ShiftService::class);
            $this->assertSame('0.000', $service->calculateShiftTotals($shiftA->refresh())['expected_cash_balance']);
            $this->assertSame('12.500', $service->calculateShiftTotals($shiftB->refresh())['expected_cash_balance']);
        });
    }

    public function test_daily_journal_counts_each_sale_once_and_only_the_selected_store(): void
    {
        $this->asAdmin(function (): void {
            $this->openShift($this->storeA, '500.000');

            $this->sell($this->storeA, '1.000');
            $this->sell($this->storeB, '1.000');
            $this->receipt($this->storeA, '50.000');
            $this->receipt($this->storeB, '70.000');

            $date = app(TenantClock::class)->businessDate();
            $summary = app(GetDailyJournalAction::class)->execute($date, $this->storeA)['summary'];

            $this->assertSame(100.0, $summary['cash_sales']);
            $this->assertSame(50.0, $summary['customer_payments']);
            $this->assertSame(150.0, $summary['total_cash_in']);
            $this->assertSame(650.0, $summary['expected_cash_in_drawer']);
        });
    }
}
