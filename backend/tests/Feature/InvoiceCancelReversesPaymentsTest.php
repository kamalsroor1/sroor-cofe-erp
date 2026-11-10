<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Exceptions\BusinessRuleException;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CustomerBalanceService;
use App\Services\InvoiceService;
use App\Services\ShiftService;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * BE-03: cancelling an invoice that has payments reverses them in the treasury with negative
 * PAY-REF vouchers, in the same transaction, visible in the shift, the daily journal, the
 * treasury and the customer balance. Legacy cancellations (no reversal) stay hidden.
 */
final class InvoiceCancelReversesPaymentsTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private int $storeId;

    private int $storeB;

    private int $customerId;

    private int $walkInId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);

        $this->inTenant($this->tenant, function (): void {
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000007103', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->walkInId = (int) app(ResolveWalkInCustomerAction::class)->execute()->id;
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'بن', 'code' => 'CX-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            $this->itemId = (int) $item->id;
            foreach ([$this->storeId, $this->storeB] as $store) {
                StoreStock::create(['store_id' => $store, 'item_id' => $item->id, 'quantity' => '50.000']);
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cancelling_a_cash_sale_reverses_it_everywhere_for_a_named_customer(): void
    {
        $this->openShift();
        $before = $this->snapshot($this->customerId);
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        $afterSale = $this->snapshot($this->customerId);
        $this->assertSame(bcadd($before['expected'], '100.000', 3), $afterSale['expected']);

        $this->cancel($invoiceId)->assertOk();

        $this->inTenant($this->tenant, function () use ($invoiceId): void {
            $original = Payment::query()->where('invoice_id', $invoiceId)->where('payment_number', 'like', 'PAY-INV-%')->sole();
            $reversal = Payment::query()->where('invoice_id', $invoiceId)->where('payment_number', 'like', 'PAY-REF-%')->sole();
            $this->assertMatchesRegularExpression('/^PAY-REF-\d{8}-\d{6}$/', $reversal->payment_number);
            $this->assertSame('-100.000', (string) $reversal->amount);
            $this->assertSame('cash', $reversal->payment_method);
            $this->assertSame((int) $original->id, (int) $reversal->reverses_payment_id);
            $this->assertSame($this->storeId, (int) $reversal->store_id);
            $this->assertSame($this->customerId, (int) $reversal->customer_id);
            $this->assertNotNull($reversal->user_id);
            $this->assertSame($original->payment_date->toDateString(), $reversal->payment_date->toDateString());
            $this->assertSame('cancelled', Invoice::findOrFail($invoiceId)->status);
        });

        $after = $this->snapshot($this->customerId);
        $this->assertSame($before['expected'], $after['expected']);          // same shift: back to pre-sale
        $this->assertSame($before['treasury_cash'], $after['treasury_cash']);
        $this->assertSame($before['customer'], $after['customer']);
        $this->assertSame('100.000', $after['cash_sales']);                  // the sale is still reported...
        $this->assertSame('100.000', $after['refunds']);                     // ...and so is its reversal
        $this->assertSame('100.000', $after['journal_cash_sales']);
        $this->assertSame('100.000', $after['journal_refunds']);
        $this->assertSame($before['journal_net'], $after['journal_net']);    // net zero
        $this->assertSame('100.000', $after['summary_refunds']);
    }

    public function test_cancelling_a_walk_in_sale_restores_the_walk_in_balance(): void
    {
        $this->openShift();
        $before = $this->snapshot($this->walkInId);
        $invoiceId = $this->sell($this->walkInId, '5.000', 'cash');

        $this->cancel($invoiceId)->assertOk();

        $after = $this->snapshot($this->walkInId);
        $this->assertSame($before['customer'], $after['customer']);
        $this->assertSame($before['expected'], $after['expected']);
        $this->assertSame($before['treasury_cash'], $after['treasury_cash']);
        $this->inTenant($this->tenant, fn () => $this->assertSame($this->walkInId, (int) Payment::query()->refunds()->sole()->customer_id));
    }

    public function test_sale_in_a_closed_shift_cancelled_in_the_next_shift_leaves_the_closed_shift_unchanged(): void
    {
        Carbon::setTestNow(Carbon::now()->subHours(5));
        $shiftA = $this->openShift();
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        Carbon::setTestNow(Carbon::now()->addHour());
        $closedA = $this->inTenant($this->tenant, function () use ($shiftA): CashShift {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());

            return app(ShiftService::class)->closeShift(CashShift::findOrFail($shiftA), '200.000')->refresh();
        });
        $this->assertSame('200.000', (string) $closedA->expected_cash_balance);

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->openShift('50.000');
        $this->assertSame('50.000', $this->snapshot($this->customerId)['expected']);

        Carbon::setTestNow(Carbon::now()->addHour());
        $this->cancel($invoiceId)->assertOk();

        $this->assertSame('-50.000', $this->snapshot($this->customerId)['expected']); // shift B: opening 50 - 100 refund
        $this->inTenant($this->tenant, function () use ($shiftA, $closedA): void {
            $stored = CashShift::findOrFail($shiftA);
            $this->assertSame((string) $closedA->expected_cash_balance, (string) $stored->expected_cash_balance);
            $this->assertSame((string) $closedA->total_cash_sales, (string) $stored->total_cash_sales);
            // Recomputed on demand, shift A still shows the sale and no refund.
            $totals = app(ShiftService::class)->calculateShiftTotals($stored);
            $this->assertSame('100.000', $totals['total_cash_sales']);
            $this->assertSame('0.000', $totals['total_refunds']);
        });
    }

    public function test_split_payment_is_reversed_per_method(): void
    {
        $this->openShift();
        $before = $this->snapshot($this->customerId);
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash', [
            'payments' => [['method' => 'cash', 'amount' => '60.000'], ['method' => 'visa', 'amount' => '40.000']],
        ]);
        $this->assertSame('40.000', $this->snapshot($this->customerId)['treasury_visa']);

        $this->cancel($invoiceId)->assertOk();

        $this->inTenant($this->tenant, function () use ($invoiceId): void {
            $refunds = Payment::query()->where('invoice_id', $invoiceId)->refunds()->orderBy('id')->get();
            $this->assertCount(2, $refunds);
            $this->assertSame(['-60.000', '-40.000'], $refunds->pluck('amount')->map(fn ($a): string => (string) $a)->all());
            $this->assertSame(['cash', 'visa'], $refunds->pluck('payment_method')->all());
        });
        $after = $this->snapshot($this->customerId);
        $this->assertSame($before['expected'], $after['expected']);
        $this->assertSame('60.000', $after['refunds']);                      // only the cash part leaves the drawer
        $this->assertSame('60.000', $after['journal_refunds']);
        $this->assertSame('0.000', $after['treasury_visa']);
        $this->assertSame($before['treasury_cash'], $after['treasury_cash']);
        $this->assertSame($before['customer'], $after['customer']);
    }

    public function test_credit_sale_with_a_linked_collection_reverses_the_collection_only(): void
    {
        $this->openShift();
        $before = $this->snapshot($this->customerId);
        $invoiceId = $this->sell($this->customerId, '5.000', 'credit');
        $this->assertSame('100.000', $this->snapshot($this->customerId)['customer']);
        $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $this->customerId, 'invoice_id' => $invoiceId, 'amount' => '30.000', 'payment_method' => 'cash',
        ], $this->headers)->assertSuccessful();
        $mid = $this->snapshot($this->customerId);
        $this->assertSame('70.000', $mid['customer']);
        $this->assertSame(bcadd($before['expected'], '30.000', 3), $mid['expected']);

        $this->cancel($invoiceId)->assertOk();

        $this->inTenant($this->tenant, function () use ($invoiceId): void {
            $this->assertSame(1, Payment::query()->where('invoice_id', $invoiceId)->refunds()->count());
            $reversal = Payment::query()->where('invoice_id', $invoiceId)->refunds()->sole();
            $this->assertSame('-30.000', (string) $reversal->amount);
            $this->assertSame('PAY-CUST-', substr((string) Payment::findOrFail($reversal->reverses_payment_id)->payment_number, 0, 9));
        });
        $after = $this->snapshot($this->customerId);
        $this->assertSame($before['customer'], $after['customer']);          // 0 - (30 - 30)
        $this->assertSame($before['expected'], $after['expected']);
        $this->assertSame($before['treasury_cash'], $after['treasury_cash']);
    }

    public function test_cancelling_an_unpaid_invoice_books_no_voucher(): void
    {
        $this->openShift();
        $before = $this->snapshot($this->customerId);
        $invoiceId = $this->sell($this->customerId, '5.000', 'credit');

        $this->cancel($invoiceId)->assertOk();

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, Payment::query()->count()));
        $this->assertSame($before['customer'], $this->snapshot($this->customerId)['customer']);
        $this->assertSame($before['expected'], $this->snapshot($this->customerId)['expected']);
    }

    public function test_cancelling_twice_is_a_409_and_does_not_duplicate_reversals(): void
    {
        $this->openShift();
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        $this->cancel($invoiceId)->assertOk();
        $after = $this->snapshot($this->customerId);

        $this->cancel($invoiceId)->assertStatus(409);

        $this->inTenant($this->tenant, fn () => $this->assertSame(1, Payment::query()->refunds()->count()));
        $this->assertSame($after, $this->snapshot($this->customerId));
    }

    public function test_an_invoice_with_a_live_return_still_cannot_be_cancelled(): void
    {
        $this->openShift();
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        $lineId = (int) $this->inTenant($this->tenant, fn () => Invoice::findOrFail($invoiceId)->items()->value('id'));
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => 'balance',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '1.000']],
        ], $this->headers)->assertCreated();

        $this->cancel($invoiceId)->assertStatus(422);

        $this->inTenant($this->tenant, function () use ($invoiceId): void {
            $this->assertSame(0, Payment::query()->where('payment_number', 'like', 'PAY-REF-%')->count());
            $this->assertSame('confirmed', Invoice::findOrFail($invoiceId)->status);
        });
    }

    public function test_a_failure_after_the_reversal_rolls_everything_back(): void
    {
        $this->openShift();
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        $before = $this->snapshot($this->customerId);
        $stock = $this->inTenant($this->tenant, fn (): string => $this->stock());

        $this->mock(AuditLogService::class)->shouldReceive('log')->andThrow(new RuntimeException('boom'));
        $this->withoutExceptionHandling();

        try {
            $this->cancel($invoiceId);
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException) {
        }

        $this->assertSame($before, $this->snapshot($this->customerId));
        $this->inTenant($this->tenant, function () use ($invoiceId, $stock): void {
            $this->assertSame(0, Payment::query()->where('payment_number', 'like', 'PAY-REF-%')->count());
            $this->assertSame('confirmed', Invoice::findOrFail($invoiceId)->status);
            $this->assertSame($stock, $this->stock());
        });
    }

    public function test_deleting_a_cancelled_invoice_soft_deletes_originals_and_reversals(): void
    {
        $this->openShift();
        $before = $this->snapshot($this->customerId);
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        $this->cancel($invoiceId)->assertOk();

        $this->inTenant($this->tenant, function () use ($invoiceId): void {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            app(InvoiceService::class)->deleteInvoice(Invoice::findOrFail($invoiceId));

            $this->assertSame(0, Payment::query()->where('invoice_id', $invoiceId)->count());
            $this->assertSame(2, Payment::onlyTrashed()->where('invoice_id', $invoiceId)->count());
        });

        $after = $this->snapshot($this->customerId);
        $this->assertSame($before['customer'], $after['customer']);
        $this->assertSame($before['treasury_cash'], $after['treasury_cash']);
        $this->assertSame($before['expected'], $after['expected']);
        $this->assertSame('0.000', $after['refunds']);
    }

    public function test_a_legacy_cancelled_invoice_without_a_reversal_stays_hidden_everywhere(): void
    {
        $this->openShift();
        $before = $this->snapshot($this->customerId);
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        // Pre-BE-03 cancellation: status flipped, payment left in place, no PAY-REF voucher.
        $this->inTenant($this->tenant, function () use ($invoiceId): void {
            Invoice::whereKey($invoiceId)->update(['status' => 'cancelled', 'remaining_amount' => '0.000']);
            // Before BE-03 this recompute left a phantom -100 credit (the payment stayed, the invoice did not count).
            $this->assertSame('0.000', app(CustomerBalanceService::class)->updateBalance($this->customerId));
        });

        $after = $this->snapshot($this->customerId);
        $this->assertSame($before['customer'], $after['customer']);          // credit gone
        $this->assertSame($before['expected'], $after['expected']);
        $this->assertSame($before['treasury_cash'], $after['treasury_cash']);
        $this->assertSame('0.000', $after['cash_sales']);
        $this->assertSame('0.000', $after['journal_cash_sales']);
        $this->assertSame($before['journal_net'], $after['journal_net']);
        $ledger = $this->inTenant($this->tenant, fn (): array => app(CustomerBalanceService::class)->getCustomerLedger(Customer::findOrFail($this->customerId))['entries']);
        $this->assertSame([], $ledger);
    }

    public function test_authentication_and_admin_permission_are_required(): void
    {
        $this->openShift();
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');

        $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', ['reason' => 'x'], $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
        $nobody = $this->createTenantUser($this->tenant, attributes: ['name' => 'بدون صلاحيات']);
        $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', ['reason' => 'x'], $this->tenantHeaders($this->tenant, $nobody))->assertStatus(403);
        $cancelOnly = $this->createTenantUser($this->tenant, permissions: ['invoices.cancel'], attributes: ['name' => 'يلغي فقط']);
        $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', ['reason' => 'x'], $this->tenantHeaders($this->tenant, $cancelOnly))->assertStatus(403);

        $this->inTenant($this->tenant, function () use ($invoiceId): void {
            $this->assertSame(0, Payment::query()->refunds()->count());
            $this->assertSame('confirmed', Invoice::findOrFail($invoiceId)->status);
        });
    }

    public function test_another_stores_shift_and_treasury_are_untouched(): void
    {
        $shiftB = $this->inTenant($this->tenant, function (): CashShift {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $shift = app(ShiftService::class)->openShift('500.000', null, $this->storeB);
            $shift->forceFill(['opened_at' => now()->subMinute()])->save();

            return $shift->refresh();
        });
        $this->openShift();
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        $this->cancel($invoiceId)->assertOk();

        $this->inTenant($this->tenant, function () use ($shiftB): void {
            $this->assertSame($this->storeId, (int) Payment::query()->refunds()->sole()->store_id);
            $totals = app(ShiftService::class)->calculateShiftTotals($shiftB);
            $this->assertSame('0.000', $totals['total_refunds']);
            $this->assertSame('500.000', $totals['expected_cash_balance']);
        });
        $treasuryB = $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, null, $this->storeB))->assertOk();
        $this->assertSame('500.000', (string) $treasuryB->json('balances.accounts.cash.balance'));
    }

    public function test_a_cancelled_invoice_cannot_be_edited(): void
    {
        $this->openShift();
        $invoiceId = $this->sell($this->customerId, '5.000', 'cash');
        $this->cancel($invoiceId)->assertOk();
        $before = $this->snapshot($this->customerId);
        $payments = $this->inTenant($this->tenant, fn (): int => Payment::query()->count());

        $this->inTenant($this->tenant, function () use ($invoiceId): void {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            try {
                app(InvoiceService::class)->updateInvoice(Invoice::findOrFail($invoiceId), ['customer_id' => $this->customerId]);
                $this->fail('Expected a conflict.');
            } catch (BusinessRuleException $e) {
                $this->assertSame(409, $e->status());
            }
        });

        $this->assertSame($before, $this->snapshot($this->customerId));
        $this->inTenant($this->tenant, function () use ($payments): void {
            $this->assertSame($payments, Payment::query()->count());
            $this->assertSame(0, Payment::onlyTrashed()->count());
        });
    }

    public function test_a_receipt_against_a_cancelled_invoice_is_rejected(): void
    {
        $this->openShift();
        $invoiceId = $this->sell($this->customerId, '5.000', 'credit');
        $this->cancel($invoiceId)->assertOk();
        $before = $this->snapshot($this->customerId);

        $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $this->customerId, 'invoice_id' => $invoiceId, 'amount' => '30.000', 'payment_method' => 'cash',
        ], $this->headers)->assertStatus(409);

        $this->assertSame($before, $this->snapshot($this->customerId));
        $this->inTenant($this->tenant, fn () => $this->assertSame(0, Payment::query()->count()));
    }

    private function cancel(int $invoiceId): TestResponse
    {
        return $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', ['reason' => 'طلب العميل'], $this->headers);
    }

    private function stock(): string
    {
        return (string) StoreStock::where('store_id', $this->storeId)->where('item_id', $this->itemId)->value('quantity');
    }

    /**
     * Sells $qty at 20 (5 = 100.000). $type credit books no payment.
     *
     * @param  array<string, mixed>  $extra
     */
    private function sell(int $customerId, string $qty, string $type, array $extra = []): int
    {
        return $this->inTenant($this->tenant, function () use ($customerId, $qty, $type, $extra): int {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());

            return (int) app(InvoiceService::class)->confirmInvoice(array_merge([
                'customer_id' => $customerId, 'store_id' => $this->storeId,
                'payment_type' => $type, 'payment_method' => 'cash',
                'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'unit_price' => '20.000']],
            ], $extra))->id;
        });
    }

    private function openShift(string $opening = '100.000'): int
    {
        return $this->inTenant($this->tenant, function () use ($opening): int {
            $open = CashShift::query()->where('status', 'open')->where('store_id', $this->storeId)->first();
            if ($open !== null) {
                return (int) $open->id;
            }
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $shift = app(ShiftService::class)->openShift($opening, null, $this->storeId);
            $shift->forceFill(['opened_at' => now()->subMinute()])->save();

            return (int) $shift->id;
        });
    }

    /**
     * @return array<string, string>
     */
    private function snapshot(int $customerId): array
    {
        $metrics = $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()->json('metrics');
        $journal = $this->getJson('/api/v1/daily-journal?date='.now()->toDateString(), $this->headers)->assertOk()->json('data.summary');
        $treasury = $this->getJson('/api/v1/treasury/summary', $this->headers)->assertOk();

        return [
            'expected' => (string) $metrics['expected_cash_balance'],
            'refunds' => (string) $metrics['total_refunds'],
            'cash_sales' => (string) $metrics['total_cash_sales'],
            'journal_cash_sales' => bcadd((string) $journal['cash_sales'], '0', 3),
            'journal_refunds' => bcadd((string) $journal['cash_refunds'], '0', 3),
            'journal_net' => bcadd((string) $journal['net_cash_today'], '0', 3),
            'treasury_cash' => (string) $treasury->json('balances.accounts.cash.balance'),
            'treasury_visa' => bcadd((string) ($treasury->json('balances.accounts.visa.balance') ?? '0'), '0', 3),
            'summary_refunds' => (string) $treasury->json('today.cash_refunds'),
            'customer' => $this->inTenant($this->tenant, fn (): string => (string) Customer::findOrFail($customerId)->current_balance),
        ];
    }
}
