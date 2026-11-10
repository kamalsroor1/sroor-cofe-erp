<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TreasuryTransfer;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\TreasuryService;
use App\Support\TenantClock;
use Tests\TenantTestCase;

/**
 * BE-11: per-branch treasury balances respect payments.store_id, cover every payment method,
 * count a treasury-paid purchase expense (PAY-EXP) once, and transfers check the balance under a lock.
 *
 * Fixture (all through the real services, tenant business day):
 *  A: cash sale 25.000 (0.250 x 100), visa sale 100.000, bank_transfer sale 50.000, customer receipt 12.500,
 *     purchase paid 5.000 cash + PAY-EXP 3.500 cash, cash expense 2.000
 *  B: cash sale 125.500 (1.255 x 100), purchase paid 4.250 cash + PAY-EXP 7.000 cash
 *  => A cash: in 37.500, out 10.500, balance 27.000 | B cash: in 125.500, out 11.250, balance 114.250
 */
final class TreasuryStoreIsolationTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeA;

    private int $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;
        $adminId = (int) $this->tenantAdmin($this->tenant)->id;

        $this->storeB = $this->inTenant($this->tenant, function () use ($adminId): int {
            $this->actingAs(User::query()->findOrFail($adminId));

            $storeB = (int) Store::query()->create([
                'name' => 'فرع ب',
                'code' => 'TR-B',
                'type' => 'retail',
                'is_main' => false,
                'is_active' => true,
            ])->id;

            $item = Item::query()->create([
                'code' => 'COF-TR',
                'name' => 'بن خزينة',
                'category' => 'بن سادة',
                'unit' => 'كجم',
                'current_stock' => '200.000',
                'cost_price' => '50.000',
                'weighted_avg_cost' => '50.000',
                'selling_price' => '100.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);
            foreach ([$this->storeA, $storeB] as $storeId) {
                StoreStock::query()->create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => '100.000', 'min_stock' => '0.000']);
            }

            $customerId = (int) Customer::query()->create(['name' => 'عميل', 'phone' => '01000007411', 'current_balance' => '0.000', 'is_active' => true])->id;
            $supplierId = (int) Supplier::query()->create(['name' => 'مورد', 'current_balance' => '0.000', 'is_active' => true])->id;

            $sale = fn (int $storeId, string $qty, string $method) => app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $customerId,
                'store_id' => $storeId,
                'payment_type' => 'cash',
                'payment_method' => $method,
                'items' => [['item_id' => $item->id, 'quantity' => $qty, 'unit_price' => '100.000']],
            ]);
            $sale($this->storeA, '0.250', 'cash');
            $sale($this->storeA, '1.000', 'visa');
            $sale($this->storeA, '0.500', 'bank_transfer');
            $sale($storeB, '1.255', 'cash');

            app(PaymentService::class)->recordCustomerPayment([
                'customer_id' => $customerId, 'store_id' => $this->storeA, 'amount' => '12.500', 'payment_method' => 'cash',
            ]);

            $purchase = fn (int $storeId, string $paid, string $expense) => app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplierId,
                'store_id' => $storeId,
                'paid_amount' => $paid,
                'payment_method' => 'cash',
                'items' => [['item_id' => $item->id, 'quantity' => '10.000', 'cost_price' => '2.000']],
                'additional_expenses' => [['title' => 'نقل', 'amount' => $expense, 'paid_by' => 'treasury_cash', 'allocation_method' => 'by_quantity']],
            ]);
            $purchase($this->storeA, '5.000', '3.500');
            $purchase($storeB, '4.250', '7.000');

            Expense::query()->create([
                'store_id' => $this->storeA,
                'user_id' => $adminId,
                'expense_number' => 'EXP-TR-A',
                'title' => 'مصروف نقدي',
                'amount' => '2.000',
                'category' => 'تشغيلي',
                'payment_method' => 'cash',
                'expense_date' => app(TenantClock::class)->today(),
            ]);

            return $storeB;
        });
    }

    /** @return array<string, mixed> */
    private function balances(?int $storeId): array
    {
        return $this->inTenant($this->tenant, fn (): array => app(TreasuryService::class)->getBalances($storeId));
    }

    /** @param array<string, mixed> $data */
    private function transfer(array $data): TreasuryTransfer
    {
        return $this->inTenant($this->tenant, function () use ($data): TreasuryTransfer {
            $this->actingAs($this->tenantAdmin($this->tenant));

            return app(TreasuryService::class)->transfer($data);
        });
    }

    public function test_vouchers_created_by_checkout_and_purchase_carry_the_document_store(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, Payment::query()->whereNull('store_id')->whereNotNull('invoice_id')->count());
            $this->assertSame(0, Payment::query()->whereNull('store_id')->whereNotNull('purchase_id')->count());
            $this->assertSame(
                ['100.000', '125.500', '25.000', '50.000'],
                $this->sorted(Payment::query()->where('payment_number', 'like', 'PAY-INV-%')->pluck('amount')->all()),
            );
        });
    }

    public function test_branch_balances_only_count_that_branch(): void
    {
        $a = $this->balances($this->storeA);
        $this->assertSame('37.500', $a['cash']['inflows']);
        $this->assertSame('10.500', $a['cash']['outflows']);
        $this->assertSame('27.000', $a['cash']['balance']);

        $b = $this->balances($this->storeB);
        $this->assertSame('125.500', $b['cash']['inflows']);
        $this->assertSame('11.250', $b['cash']['outflows']);
        $this->assertSame('114.250', $b['cash']['balance']);
        $this->assertArrayNotHasKey('visa', $b);

        $all = $this->balances(null);
        $this->assertSame('163.000', $all['cash']['inflows']);
        $this->assertSame('21.750', $all['cash']['outflows']);
        $this->assertSame('141.250', $all['cash']['balance']);
    }

    public function test_visa_and_bank_transfer_sales_appear_in_balances(): void
    {
        $a = $this->balances($this->storeA);

        $this->assertSame('100.000', $a['visa']['balance']);
        $this->assertSame('100.000', $a['visa']['inflows']);
        $this->assertSame('50.000', $a['bank_transfer']['balance']);
        $this->assertSame('0.000', $a['instapay']['balance']);
        $this->assertArrayNotHasKey('check', $a);
        $this->assertSame('177.000', $a['total_liquidity']);

        $this->assertSame('291.250', $this->balances(null)['total_liquidity']);
    }

    public function test_pay_exp_is_counted_once(): void
    {
        // A: 5.000 PAY-PUR + 3.500 PAY-EXP (both supplier vouchers) + 2.000 expense = 10.500, not 14.000.
        $this->assertSame('10.500', $this->balances($this->storeA)['cash']['outflows']);

        $report = $this->inTenant($this->tenant, fn (): array => app(TreasuryService::class)
            ->getTreasuryReport('2000-01-01', '2999-12-31', $this->storeA));
        $this->assertSame('10.500', $report['accounts']['cash']['outflows']);
        $this->assertSame('37.500', $report['accounts']['cash']['inflows']);
        $this->assertSame('27.000', $report['accounts']['cash']['closing_balance']);
        $this->assertSame('100.000', $report['accounts']['visa']['closing_balance']);
        $this->assertSame('177.000', $report['total_liquidity']);
    }

    public function test_report_ledger_is_scoped_to_the_branch(): void
    {
        $report = $this->inTenant($this->tenant, fn (): array => app(TreasuryService::class)
            ->getTreasuryReport('2000-01-01', '2999-12-31', $this->storeB, 'cash'));

        $debits = array_map(fn (array $e): string => $e['debit'], $report['ledger_entries']);
        $this->assertSame(['125.500'], array_values(array_filter($debits, fn (string $d): bool => $d !== '0.000')));
        $this->assertSame('114.250', $report['accounts']['cash']['closing_balance']);
        $this->assertSame('114.250', $report['ledger_entries'][0]['running_balance']);
    }

    public function test_insufficient_transfer_is_refused_and_persists_nothing(): void
    {
        try {
            // A has 27.000 cash: 27.000 + 0.001 fee is one millime too much (B's money must not help).
            $this->transfer(['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '27.000', 'transfer_fee' => '0.001', 'store_id' => $this->storeA]);
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException $e) {
            $this->assertSame(422, $e->status());
            $this->assertSame('treasury.transfer_insufficient_balance', $e->errorCode());
            $this->assertSame('27.000', $e->params['available']);
            $this->assertSame('27.001', $e->params['required']);
        }

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, TreasuryTransfer::query()->count()));
        $this->assertSame('27.000', $this->balances($this->storeA)['cash']['balance']);
    }

    public function test_successful_transfer_moves_exact_amounts_including_the_fee(): void
    {
        $transfer = $this->transfer(['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '20.500', 'transfer_fee' => '1.250', 'store_id' => $this->storeA]);
        $this->assertStringStartsWith('TRF-', $transfer->transfer_number);

        $a = $this->balances($this->storeA);
        $this->assertSame('5.250', $a['cash']['balance']);
        $this->assertSame('20.500', $a['instapay']['balance']);
        $this->assertSame('177.000', bcadd($a['total_liquidity'], '1.250', 3));

        // Branch B is untouched.
        $this->assertSame('114.250', $this->balances($this->storeB)['cash']['balance']);

        // A second transfer sees the first one's outflow.
        $this->expectException(BusinessRuleException::class);
        $this->transfer(['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '5.250', 'transfer_fee' => '0.001', 'store_id' => $this->storeA]);
    }

    public function test_branch_cannot_transfer_with_another_branchs_money(): void
    {
        $this->expectException(BusinessRuleException::class);
        // B has 114.250 cash but A only 27.000.
        $this->transfer(['from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '100.000', 'store_id' => $this->storeA]);
    }

    public function test_all_branches_transfer_draws_on_the_combined_balance(): void
    {
        $this->transfer(['from_method' => 'visa', 'to_method' => 'cash', 'amount' => '100.000', 'store_id' => null]);
        $this->assertSame('0.000', $this->balances(null)['visa']['balance']);

        $this->expectException(BusinessRuleException::class);
        $this->transfer(['from_method' => 'visa', 'to_method' => 'cash', 'amount' => '0.001', 'store_id' => null]);
    }

    public function test_backfill_migration_fills_only_empty_store_ids_from_the_document(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $invoicePayment = Payment::query()->where('payment_number', 'like', 'PAY-INV-%')->where('amount', '125.500')->firstOrFail();
            $receipt = Payment::query()->whereNull('invoice_id')->whereNull('purchase_id')->whereNotNull('customer_id')->firstOrFail();
            Payment::query()->whereKey($invoicePayment->id)->update(['store_id' => null]);
            Payment::query()->where('purchase_id', '!=', 0)->update(['store_id' => null]);
            $storeOfReceipt = $receipt->store_id;

            (require database_path('migrations/tenant/2026_11_10_120000_backfill_payments_store_id.php'))->up();

            $this->assertSame($this->storeB, (int) $invoicePayment->fresh()->store_id);
            $this->assertSame(0, Payment::query()->whereNull('store_id')->whereNotNull('purchase_id')->count());
            $this->assertSame($storeOfReceipt, $receipt->fresh()->store_id);
        });
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function sorted(array $values): array
    {
        $values = array_map(fn ($v): string => (string) $v, $values);
        sort($values, SORT_STRING);

        return $values;
    }
}
