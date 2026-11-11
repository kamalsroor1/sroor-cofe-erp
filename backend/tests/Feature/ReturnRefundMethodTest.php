<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\CustomerBalanceService;
use App\Services\InvoiceService;
use App\Services\ShiftService;
use App\Support\TenantClock;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * BE-10: a sales return refunds in cash (a negative PAY-RET voucher that leaves the drawer) or
 * on the customer's balance (no cash movement). Only cash refunds reduce the shift's expected cash.
 */
final class ReturnRefundMethodTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private int $storeId;

    private int $storeB;

    private int $customerId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);

        $this->inTenant($this->tenant, function (): void {
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000007003', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'بن', 'code' => 'RF-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
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

    public function test_cash_refund_books_a_negative_voucher_and_leaves_the_drawer(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000'); // 200 cash in the drawer
        $before = $this->snapshot();
        $this->assertSame('300.000', $before['expected']); // 100 opening + 200 sale
        $this->assertSame('0.000', $before['customer']);

        $response = $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertCreated();
        $response->assertJsonPath('data.refund_method', 'cash')
            ->assertJsonPath('data.refund_amount', '60.000')
            ->assertJsonPath('data.total_amount', '60.000');
        $returnId = (int) $response->json('data.id');

        $this->inTenant($this->tenant, function () use ($returnId, $invoiceId): void {
            $voucher = Payment::query()->where('return_id', $returnId)->sole();
            $this->assertMatchesRegularExpression('/^PAY-RET-\d{8}-\d{6}$/', $voucher->payment_number);
            $this->assertSame('-60.000', (string) $voucher->amount);
            $this->assertSame('cash', $voucher->payment_method);
            $this->assertSame($this->customerId, (int) $voucher->customer_id);
            $this->assertSame($this->storeId, (int) $voucher->store_id);
            $this->assertSame($invoiceId, (int) $voucher->invoice_id);
            $this->assertSame('cash', ReturnDocument::findOrFail($returnId)->refund_method);
        });

        $after = $this->snapshot();
        $this->assertSame('240.000', $after['expected']);         // dropped by exactly the total
        $this->assertSame('60.000', $after['refunds']);
        $this->assertSame('200.000', $after['cash_sales']);       // the voucher is not a sale...
        $this->assertSame('0.000', $after['receipts']);           // ...nor a receipt
        $this->assertSame('60.000', $after['journal_refunds']);
        $this->assertSame(bcsub($before['journal_net'], '60.000', 3), $after['journal_net']);
        $this->assertSame(bcsub($before['treasury_cash'], '60.000', 3), $after['treasury_cash']);
        $this->assertSame('0.000', $after['customer']);           // return -60, voucher +60: net zero
        $this->assertSame('60.000', $after['summary_refunds']);
    }

    public function test_balance_refund_books_no_voucher_and_does_not_touch_the_drawer(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $before = $this->snapshot();

        $response = $this->postReturn($invoiceId, $lineId, '3.000', 'balance')->assertCreated();
        $response->assertJsonPath('data.refund_method', 'balance')->assertJsonPath('data.refund_amount', '0.000');

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, Payment::query()->where('payment_number', 'like', 'PAY-RET-%')->count());
        });
        $after = $this->snapshot();
        $this->assertSame($before['expected'], $after['expected']);   // the BE-10 regression: it used to drop
        $this->assertSame('0.000', $after['refunds']);
        $this->assertSame($before['treasury_cash'], $after['treasury_cash']);
        $this->assertSame('0.000', $after['journal_refunds']);
        $this->assertSame('-60.000', $after['customer']);
    }

    public function test_refund_method_defaults_to_balance_and_the_client_refund_amount_is_ignored(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');

        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_amount' => '999.000',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '1.000']],
        ], $this->headers)->assertCreated()
            ->assertJsonPath('data.refund_method', 'balance')
            ->assertJsonPath('data.refund_amount', '0.000');

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, Payment::query()->where('return_id', '!=', 0)->count()));
        $this->assertSame('300.000', $this->snapshot()['expected']);
    }

    public function test_walk_in_customer_is_always_refunded_in_cash(): void
    {
        $walkIn = $this->inTenant($this->tenant, fn (): int => (int) app(ResolveWalkInCustomerAction::class)->execute()->id);
        $this->openShift();

        foreach (['balance', null] as $requested) {
            $payload = [
                'return_type' => 'sales_return', 'customer_id' => $walkIn, 'return_date' => now()->toDateString(),
                'items' => [['item_id' => $this->itemId, 'quantity' => '2.000', 'unit_price' => '20.000']],
            ];
            if ($requested !== null) {
                $payload['refund_method'] = $requested;
            }
            $this->postJson('/api/v1/returns', $payload, $this->headers)->assertCreated()
                ->assertJsonPath('data.refund_method', 'cash')
                ->assertJsonPath('data.refund_amount', '40.000');
        }

        $this->assertSame('20.000', $this->snapshot()['expected']); // 100 - 40 - 40
        $this->inTenant($this->tenant, fn () => $this->assertSame('0.000', (string) Customer::findOrFail($walkIn)->current_balance));
    }

    public function test_deleting_a_cash_return_puts_everything_back_exactly(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $before = $this->snapshot();
        $returnId = (int) $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertCreated()->json('data.id');

        $this->deleteJson('/api/v1/returns/'.$returnId, [], $this->headers)->assertOk();

        $this->assertSame($before, $this->snapshot());
        $this->inTenant($this->tenant, function () use ($returnId): void {
            $this->assertSoftDeleted('returns', ['id' => $returnId]);
            $this->assertSoftDeleted('payments', ['return_id' => $returnId]);
            $this->assertSame(0, Payment::query()->where('return_id', $returnId)->count());
        });
    }

    public function test_deleting_a_balance_return_restores_the_customer_balance(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $before = $this->snapshot();
        $returnId = (int) $this->postReturn($invoiceId, $lineId, '3.000', 'balance')->assertCreated()->json('data.id');

        $this->deleteJson('/api/v1/returns/'.$returnId, [], $this->headers)->assertOk();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_a_failure_after_the_voucher_rolls_everything_back(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $before = $this->snapshot();
        $stock = $this->inTenant($this->tenant, fn (): string => (string) StoreStock::where('store_id', $this->storeId)->where('item_id', $this->itemId)->value('quantity'));

        $this->mock(AuditLogService::class)->shouldReceive('log')->andThrow(new RuntimeException('boom'));
        $this->withoutExceptionHandling();

        try {
            $this->postReturn($invoiceId, $lineId, '3.000', 'cash');
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException) {
        }

        $this->assertSame($before, $this->snapshot());
        $this->inTenant($this->tenant, function () use ($stock): void {
            $this->assertSame(0, ReturnDocument::query()->count());
            $this->assertSame(0, Payment::query()->where('payment_number', 'like', 'PAY-RET-%')->count());
            $this->assertSame($stock, (string) StoreStock::where('store_id', $this->storeId)->where('item_id', $this->itemId)->value('quantity'));
        });
    }

    public function test_an_invalid_refund_method_is_a_422(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');

        $this->postReturn($invoiceId, $lineId, '1.000', 'bitcoin')
            ->assertStatus(422)->assertJsonValidationErrors(['refund_method']);

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, ReturnDocument::query()->count()));
    }

    public function test_authentication_and_permission_are_required(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $payload = [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => 'cash',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '1.000']],
        ];

        $this->postJson('/api/v1/returns', $payload, $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
        $nobody = $this->createTenantUser($this->tenant, attributes: ['name' => 'بدون صلاحيات']);
        $this->postJson('/api/v1/returns', $payload, $this->tenantHeaders($this->tenant, $nobody))->assertStatus(403);

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, Payment::query()->where('payment_number', 'like', 'PAY-RET-%')->count()));
    }

    public function test_the_voucher_belongs_to_the_return_store_and_another_stores_shift_is_untouched(): void
    {
        $shiftB = $this->inTenant($this->tenant, function (): CashShift {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $shift = app(ShiftService::class)->openShift('500.000', null, $this->storeB);
            $shift->forceFill(['opened_at' => now()->subMinute()])->save();

            return $shift->refresh();
        });
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertCreated();

        $this->inTenant($this->tenant, function () use ($shiftB): void {
            $this->assertSame($this->storeId, (int) Payment::query()->where('payment_number', 'like', 'PAY-RET-%')->sole()->store_id);
            $totals = app(ShiftService::class)->calculateShiftTotals($shiftB);
            $this->assertSame('0.000', $totals['total_refunds']);
            $this->assertSame('500.000', $totals['expected_cash_balance']);
        });
        $this->assertSame('240.000', $this->snapshot()['expected']);
    }

    public function test_closing_a_shift_stores_only_the_cash_refunds(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertCreated();   // 60 out of the drawer
        $this->postReturn($invoiceId, $lineId, '2.000', 'balance')->assertCreated(); // 40 on the balance only

        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '240.000'], $this->headers)->assertOk();

        $this->inTenant($this->tenant, function (): void {
            $shift = CashShift::query()->latest('id')->firstOrFail();
            $this->assertSame('60.000', (string) $shift->total_refunds);
            $this->assertSame('240.000', (string) $shift->expected_cash_balance);
            $this->assertSame('0.000', (string) $shift->cash_difference);
        });
    }

    public function test_cash_refund_on_an_unpaid_credit_invoice_is_refused_and_nothing_is_persisted(): void
    {
        [$invoiceId, $lineId] = $this->invoiceOf('10.000', 'credit', '0.000');
        $before = $this->snapshot();

        $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertStatus(422);

        $this->assertSame($before, $this->snapshot());
        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(0, ReturnDocument::query()->count());
            $this->assertSame(0, Payment::query()->refunds()->count());
        });
        // The same return refunded on the balance is fine.
        $this->postReturn($invoiceId, $lineId, '3.000', 'balance')->assertCreated();
    }

    public function test_cash_refund_is_capped_by_what_was_paid_net_of_earlier_cash_refunds(): void
    {
        [$invoiceId, $lineId] = $this->invoiceOf('10.000', 'partial', '100.000'); // paid 100 of 200

        $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertCreated();          // 60 of 100
        $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertStatus(422);        // 60 > 40 left
        $this->postReturn($invoiceId, $lineId, '2.000', 'cash')->assertCreated();          // the remaining 40
        $this->postReturn($invoiceId, $lineId, '1.000', 'cash')->assertStatus(422);        // nothing left
        $this->postReturn($invoiceId, $lineId, '1.000', 'balance')->assertCreated();

        $this->inTenant($this->tenant, fn () => $this->assertSame('-100.000', bcadd((string) Payment::query()->refunds()->sum('amount'), '0', 3)));
    }

    public function test_the_refund_voucher_is_dated_today_even_for_a_backdated_return(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $before = $this->snapshot();

        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->subDays(5)->toDateString(), 'refund_method' => 'cash',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '3.000']],
        ], $this->headers)->assertCreated();

        $this->inTenant($this->tenant, fn () => $this->assertSame(
            app(TenantClock::class)->businessDate(),
            Payment::query()->refunds()->sole()->payment_date->toDateString(),
        ));
        $this->assertSame(bcsub($before['journal_net'], '60.000', 3), $this->snapshot()['journal_net']);
    }

    public function test_customer_ledger_shows_the_refund_as_a_debit_with_the_same_running_balance(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertCreated();

        $ledger = $this->inTenant($this->tenant, fn (): array => app(CustomerBalanceService::class)
            ->getCustomerLedger(Customer::findOrFail($this->customerId))['entries']);

        $row = collect($ledger)->firstWhere(fn (array $r): bool => str_starts_with((string) $r['ref_number'], 'PAY-RET-'));
        $this->assertSame('60.000', $row['debit']);
        $this->assertSame('0.000', $row['credit']);
        $this->assertSame(__('treasury.customer_refund_label'), $row['type']);
        // invoice +200, sale voucher -200, return -60, refund voucher +60
        $this->assertSame('0.000', end($ledger)['balance_after']);
        $this->assertSame('0.000', $this->snapshot()['customer']);
    }

    public function test_payments_summary_separates_refunds_from_collections(): void
    {
        [$invoiceId, $lineId] = $this->cashInvoice('10.000');
        $this->postReturn($invoiceId, $lineId, '3.000', 'cash')->assertCreated();

        $this->getJson('/api/v1/payments', $this->headers)->assertOk()
            ->assertJsonPath('summary.total_collections', '200.000')
            ->assertJsonPath('summary.total_refunds', '60.000');
    }

    /**
     * Opens a shift on the main store with 100 cash and sells $qty at 20 for cash to the customer.
     *
     * @return array{0: int, 1: int} invoice id, its line id
     */
    private function cashInvoice(string $qty): array
    {
        $this->openShift();

        return $this->inTenant($this->tenant, function () use ($qty): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId, 'store_id' => $this->storeId,
                'payment_type' => 'cash', 'payment_method' => 'cash',
                'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'unit_price' => '20.000']],
            ]);

            return [(int) $invoice->id, (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->value('id')];
        });
    }

    /**
     * @return array{0: int, 1: int} invoice id, its line id
     */
    private function invoiceOf(string $qty, string $paymentType, string $paid): array
    {
        $this->openShift();

        return $this->inTenant($this->tenant, function () use ($qty, $paymentType, $paid): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId, 'store_id' => $this->storeId,
                'payment_type' => $paymentType, 'payment_method' => 'cash', 'paid_amount' => $paid,
                'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'unit_price' => '20.000']],
            ]);

            return [(int) $invoice->id, (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->value('id')];
        });
    }

    private function openShift(): void
    {
        $this->inTenant($this->tenant, function (): void {
            if (CashShift::query()->where('status', 'open')->where('store_id', $this->storeId)->exists()) {
                return;
            }
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $shift = app(ShiftService::class)->openShift('100.000', null, $this->storeId);
            $shift->forceFill(['opened_at' => now()->subMinute()])->save();
        });
    }

    private function postReturn(int $invoiceId, int $lineId, string $qty, string $method): TestResponse
    {
        return $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => $method,
            'items' => [['invoice_item_id' => $lineId, 'quantity' => $qty]],
        ], $this->headers);
    }

    /**
     * Every figure a refund can move, read through the public API.
     *
     * @return array<string, string>
     */
    private function snapshot(): array
    {
        $metrics = $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()->json('metrics');
        $journal = $this->getJson('/api/v1/daily-journal?date='.now()->toDateString(), $this->headers)->assertOk()->json('data.summary');
        $treasury = $this->getJson('/api/v1/treasury/summary', $this->headers)->assertOk();

        return [
            'expected' => (string) $metrics['expected_cash_balance'],
            'refunds' => (string) $metrics['total_refunds'],
            'cash_sales' => (string) $metrics['total_cash_sales'],
            'receipts' => (string) $metrics['total_customer_receipts'],
            'journal_refunds' => bcadd((string) $journal['cash_refunds'], '0', 3),
            'journal_net' => bcadd((string) $journal['net_cash_today'], '0', 3),
            'treasury_cash' => (string) $treasury->json('balances.accounts.cash.balance'),
            'summary_refunds' => (string) $treasury->json('today.cash_refunds'),
            'customer' => $this->inTenant($this->tenant, fn (): string => (string) Customer::findOrFail($this->customerId)->current_balance),
        ];
    }
}
