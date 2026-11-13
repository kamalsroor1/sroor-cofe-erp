<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Shifts\ShiftAttribution;
use App\Services\ShiftService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TenantTestCase;

/**
 * DATE-01: payment vouchers, expenses and invoices are stamped with the cash shift open in their
 * store when they are created, so a shift's figures no longer depend on the wall clock (stored
 * as Africa/Cairo local time): the night the clock goes back, the night it goes forward, and two
 * shifts closed/opened in the same second. Unstamped (historical) rows keep the old time window.
 *
 * Egypt 2026: summer time ends 2026-10-30 00:00 EEST -> 2026-10-29 23:00 EET (21:00 UTC), and
 * starts 2026-04-24 00:00 EET -> 01:00 EEST (22:00 UTC). The clock is moved in UTC; the app
 * stores the Cairo wall clock.
 */
final class ShiftClockChangeApiTest extends TenantTestCase
{
    private const TZ = 'Africa/Cairo';

    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private int $storeId;

    private int $storeB;

    private int $customerId;

    private int $supplierId;

    private int $itemId;

    private string $previousTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        // The scenario is about the Cairo wall clock: pin it whatever APP_TIMEZONE the runner has.
        $this->previousTimezone = date_default_timezone_get();
        config(['app.timezone' => self::TZ]);
        date_default_timezone_set(self::TZ);

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);

        $this->inTenant($this->tenant, function (): void {
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000008101', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->supplierId = (int) Supplier::create(['name' => 'مورد', 'phone' => '01000008102', 'current_balance' => '1000.000', 'is_active' => true])->id;
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-DT1', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'بن', 'code' => 'DT-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '1000.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            $this->itemId = (int) $item->id;
            foreach ([$this->storeId, $this->storeB] as $store) {
                StoreStock::create(['store_id' => $store, 'item_id' => $item->id, 'quantity' => '500.000']);
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        date_default_timezone_set($this->previousTimezone);

        parent::tearDown();
    }

    public function test_the_fall_back_night_keeps_every_sale_of_the_repeated_hour(): void
    {
        $this->at('2026-10-29 20:30:00'); // 23:30 EEST (summer)
        $shiftId = $this->openShift($this->storeId);
        $this->at('2026-10-29 20:45:00'); // 23:45 EEST
        $this->sale('200.000');           // 4,000.000
        $this->at('2026-10-29 21:10:00'); // 23:10 EET: the clock went back an hour
        $this->sale('113.650');           // 2,273.000

        $this->at('2026-10-29 21:20:00'); // 23:20 EET
        $metrics = $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()->json('metrics');
        $this->assertSame('6273.000', $metrics['total_cash_sales']);
        $this->assertSame('6373.000', $metrics['expected_cash_balance']);

        $this->at('2026-10-29 21:25:00'); // 23:25 EET, the cashier counts only the opening 100
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '100.000'], $this->headers)->assertOk()
            ->assertJsonPath('data.total_cash_sales', '6273.000')
            ->assertJsonPath('data.expected_cash_balance', '6373.000')
            ->assertJsonPath('data.cash_difference', '-6273.000');

        [$openedAt, $closedAt] = $this->inTenant($this->tenant, function () use ($shiftId): array {
            $shift = CashShift::query()->findOrFail($shiftId);

            return [(string) $shift->getRawOriginal('opened_at'), (string) $shift->getRawOriginal('closed_at')];
        });
        $this->assertSame('2026-10-29 23:30:00', $openedAt);
        $this->assertGreaterThanOrEqual($openedAt, $closedAt, 'A shift never closes before it opened.');

        $report = $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->headers)->assertOk()->json('report');
        $this->assertSame('6373.000', $report['expected_cash_balance']);
        $this->assertSame('-6273.000', $report['cash_difference']);
        $this->assertSame($report['expected_cash_balance'], $this->linesTotal($report));
    }

    public function test_a_shift_spanning_the_spring_forward_hour_has_correct_figures(): void
    {
        $this->at('2026-04-23 21:30:00'); // 23:30 EET
        $shiftId = $this->openShift($this->storeId);
        $this->at('2026-04-23 21:50:00'); // 23:50 EET
        $this->sale('10.000');            // 200.000
        $this->at('2026-04-23 22:10:00'); // 01:10 EEST: 00:00-01:00 never happened
        $this->sale('0.250');             // 5.000

        $this->at('2026-04-23 22:20:00'); // 01:20 EEST
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '305.000'], $this->headers)->assertOk()
            ->assertJsonPath('data.total_cash_sales', '205.000')
            ->assertJsonPath('data.expected_cash_balance', '305.000')
            ->assertJsonPath('data.cash_difference', '0.000');

        $closedAt = $this->inTenant($this->tenant, fn () => (string) CashShift::query()->findOrFail($shiftId)->getRawOriginal('closed_at'));
        $this->assertSame('2026-04-24 01:20:00', $closedAt);
    }

    public function test_shifts_closed_and_opened_in_the_same_second_never_share_a_voucher(): void
    {
        $this->at('2026-11-02 08:00:00');
        $first = $this->openShift($this->storeId);
        $this->at('2026-11-02 10:00:00');
        $this->sale('1.000');             // 20 in the first shift

        $this->at('2026-11-02 14:00:00'); // one second: last sale, close, open, first sale
        $this->sale('2.000');             // 40, still the first shift
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '160.000'], $this->headers)->assertOk()
            ->assertJsonPath('data.total_cash_sales', '60.000');
        $second = $this->openShift($this->storeId);
        $this->sale('4.000');             // 80, the second shift

        [$firstTotals, $secondTotals] = $this->inTenant($this->tenant, function () use ($first, $second): array {
            $service = app(ShiftService::class);

            return [
                $service->calculateShiftTotals(CashShift::query()->findOrFail($first)),
                $service->calculateShiftTotals(CashShift::query()->findOrFail($second)),
            ];
        });

        // The window of the first shift ends, and the second one starts, at 14:00:00: under the old
        // rule both vouchers of that second were counted in both shifts.
        $this->assertSame('60.000', $firstTotals['total_cash_sales']);
        $this->assertSame('80.000', $secondTotals['total_cash_sales']);
        $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()
            ->assertJsonPath('metrics.total_cash_sales', '80.000');
    }

    public function test_expense_supplier_payment_and_refund_are_stamped_and_counted_and_other_stores_are_not(): void
    {
        $this->at('2026-10-29 20:30:00');
        $shiftId = $this->openShift($this->storeId);
        $shiftB = $this->openShift($this->storeB);
        $this->at('2026-10-29 20:45:00');
        $invoiceId = $this->sale('10.000'); // 200

        // All after the clock went back: only the stamp can tie them to the shift.
        $this->at('2026-10-29 21:05:00');
        $this->postJson('/api/v1/expenses', [
            'title' => 'مصروف', 'category' => 'تشغيل', 'cost_center' => 'general', 'amount' => '30.250',
            'expense_date' => now()->toDateString(), 'payment_method' => 'cash',
        ], $this->headers)->assertCreated();
        $this->postJson('/api/v1/payments/supplier-voucher', [
            'supplier_id' => $this->supplierId, 'amount' => '40.500', 'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
        ], $this->headers)->assertCreated();
        $lineId = $this->inTenant($this->tenant, fn () => (int) InvoiceItem::query()->where('invoice_id', $invoiceId)->value('id'));
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => 'cash',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '3.000']],
        ], $this->headers)->assertCreated();
        $this->postJson('/api/v1/expenses', [
            'title' => 'مصروف فرع ب', 'category' => 'تشغيل', 'cost_center' => 'general', 'amount' => '70.000',
            'expense_date' => now()->toDateString(), 'payment_method' => 'cash',
        ], $this->tenantHeaders($this->tenant, null, $this->storeB))->assertCreated();

        $this->inTenant($this->tenant, function () use ($shiftId, $shiftB, $invoiceId): void {
            $this->assertSame($shiftId, (int) Invoice::query()->findOrFail($invoiceId)->cash_shift_id);
            $this->assertSame($shiftId, (int) Expense::query()->where('amount', '30.250')->value('cash_shift_id'));
            $this->assertSame($shiftB, (int) Expense::query()->where('amount', '70.000')->value('cash_shift_id'));
            $this->assertSame($shiftId, (int) Payment::query()->whereNotNull('supplier_id')->value('cash_shift_id'));
            $refund = Payment::query()->where('payment_number', 'like', 'PAY-RET-%')->firstOrFail();
            $this->assertSame($shiftId, (int) $refund->cash_shift_id);
            $this->assertSame(0, Payment::query()->where('store_id', $this->storeId)->where('cash_shift_id', '!=', $shiftId)->count());
        });

        $this->at('2026-10-29 21:20:00');
        $metrics = $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()->json('metrics');
        $this->assertSame('200.000', $metrics['total_cash_sales']);
        $this->assertSame('30.250', $metrics['total_expenses']);
        $this->assertSame('40.500', $metrics['total_supplier_paid']);
        $this->assertSame('60.000', $metrics['total_refunds']);
        // 100 + 200 - 30.250 - 40.500 - 60 (branch B's 70 is not in this drawer)
        $this->assertSame('169.250', $metrics['expected_cash_balance']);

        $metricsB = $this->getJson('/api/v1/shifts/current', $this->tenantHeaders($this->tenant, null, $this->storeB))->assertOk()->json('metrics');
        $this->assertSame('70.000', $metricsB['total_expenses']);
        $this->assertSame('0.000', $metricsB['total_cash_sales']);
    }

    public function test_unstamped_history_keeps_the_time_window_and_a_normal_shift_is_unchanged(): void
    {
        $this->at('2026-11-03 06:00:00');
        $shiftId = $this->openShift($this->storeId);
        $this->at('2026-11-03 07:00:00');
        $this->sale('5.000'); // 100
        $this->postJson('/api/v1/expenses', [
            'title' => 'مصروف', 'category' => 'تشغيل', 'cost_center' => 'general', 'amount' => '12.500',
            'expense_date' => now()->toDateString(), 'payment_method' => 'cash',
        ], $this->headers)->assertCreated();

        $this->at('2026-11-03 08:00:00');
        [$stamped, $legacy] = $this->inTenant($this->tenant, function () use ($shiftId): array {
            $service = app(ShiftService::class);
            $stamped = $service->calculateShiftTotals(CashShift::query()->findOrFail($shiftId));

            // The same rows as history (written before DATE-01): no stamp, attributed by the window.
            foreach (['payments', 'expenses', 'invoices'] as $table) {
                DB::table($table)->update(['cash_shift_id' => null]);
            }

            return [$stamped, $service->calculateShiftTotals(CashShift::query()->findOrFail($shiftId))];
        });

        $this->assertSame('100.000', $stamped['total_cash_sales']);
        $this->assertSame('12.500', $stamped['total_expenses']);
        $this->assertSame('187.500', $stamped['expected_cash_balance']);
        $this->assertSame($stamped, $legacy, 'Without a clock change the stamp and the old window give the same figures.');
    }

    public function test_a_row_written_without_an_open_shift_stays_unstamped_and_is_not_counted_by_a_later_shift(): void
    {
        $this->at('2026-11-04 06:00:00');
        $this->postJson('/api/v1/expenses', [
            'title' => 'مصروف', 'category' => 'تشغيل', 'cost_center' => 'general', 'amount' => '15.000',
            'expense_date' => now()->toDateString(), 'payment_method' => 'cash',
        ], $this->headers)->assertCreated();

        $this->at('2026-11-04 07:00:00');
        $this->openShift($this->storeId);

        $stamp = $this->inTenant($this->tenant, fn () => Expense::query()->where('amount', '15.000')->value('cash_shift_id'));
        $this->assertNull($stamp);

        // Created before the shift opened, so it is outside the later shift's legacy window too.
        $this->at('2026-11-04 08:00:00');
        $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()
            ->assertJsonPath('metrics.total_expenses', '0.000')
            ->assertJsonPath('metrics.expected_cash_balance', '100.000');
    }

    public function test_a_client_supplied_cash_shift_id_is_ignored(): void
    {
        $this->at('2026-11-05 08:00:00');
        $shiftId = $this->openShift($this->storeId);
        $shiftB = $this->openShift($this->storeB);

        $this->at('2026-11-05 09:00:00');
        $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->customerId, 'payment_type' => 'cash', 'payment_method' => 'cash',
            'cash_shift_id' => $shiftB,
            'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_price' => '20.000']],
        ], $this->headers)->assertCreated();
        $this->postJson('/api/v1/expenses', [
            'title' => 'مصروف', 'category' => 'تشغيل', 'cost_center' => 'general', 'amount' => '9.000',
            'expense_date' => now()->toDateString(), 'payment_method' => 'cash', 'cash_shift_id' => $shiftB,
        ], $this->headers)->assertCreated();

        $this->inTenant($this->tenant, function () use ($shiftId): void {
            $this->assertSame([$shiftId], Invoice::query()->pluck('cash_shift_id')->map(fn ($id): int => (int) $id)->all());
            $this->assertSame([$shiftId], Payment::query()->pluck('cash_shift_id')->map(fn ($id): int => (int) $id)->all());
            $this->assertSame([$shiftId], Expense::query()->pluck('cash_shift_id')->map(fn ($id): int => (int) $id)->all());
        });
    }

    public function test_a_credit_sale_in_the_repeated_hour_counts_on_the_fall_back_night(): void
    {
        $this->at('2026-10-29 20:30:00');  // 23:30 EEST
        $this->openShift($this->storeId);
        $this->at('2026-10-29 20:45:00');  // 23:45 EEST
        $this->sale('10.000', 'credit');   // 200.000 on account
        $this->at('2026-10-29 21:10:00');  // 23:10 EET, the repeated hour
        $this->sale('5.000', 'credit');    // 100.000 on account

        $this->at('2026-10-29 21:20:00');
        $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()
            ->assertJsonPath('metrics.total_credit_sales', '300.000')
            ->assertJsonPath('metrics.total_cash_sales', '0.000')
            ->assertJsonPath('metrics.expected_cash_balance', '100.000');

        $this->at('2026-10-29 21:25:00');
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '100.000'], $this->headers)->assertOk()
            ->assertJsonPath('data.total_credit_sales', '300.000')
            ->assertJsonPath('data.cash_difference', '0.000');
    }

    public function test_the_cashier_own_shift_figures_count_the_repeated_hour_sale(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $cashierHeaders = $this->tenantHeaders($this->tenant, $cashier);

        $this->at('2026-10-29 20:30:00');  // 23:30 EEST, the store's shift (opened by the admin)
        $shiftId = $this->openShift($this->storeId);
        $this->at('2026-10-29 20:45:00');  // 23:45 EEST
        $this->sale('10.000', 'cash', $cashierHeaders);  // 200.000
        $this->at('2026-10-29 21:10:00');  // 23:10 EET: before opened_at on the wall clock
        $this->sale('5.000', 'cash', $cashierHeaders);   // 100.000

        $this->at('2026-10-29 21:20:00');
        $this->getJson('/api/v1/dashboard', $cashierHeaders)->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.active_shift.id', $shiftId)
            ->assertJsonPath('data.metrics.today_invoices_count', 2)
            ->assertJsonPath('data.metrics.today_sales', '300.000')
            ->assertJsonPath('data.metrics.cash_sales', '300.000');

        $this->getJson('/api/v1/treasury/summary', $cashierHeaders)->assertOk()
            ->assertJsonPath('scope', 'own_shift')
            ->assertJsonPath('today.window', 'shift')
            ->assertJsonPath('today.invoices_count', 2)
            ->assertJsonPath('today.sales_total', '300.000');
    }

    public function test_an_invoice_cancelled_in_a_later_shift_refunds_from_the_later_shift(): void
    {
        $this->at('2026-11-06 06:00:00');
        $first = $this->openShift($this->storeId);
        $this->at('2026-11-06 07:00:00');
        $invoiceId = $this->sale('10.000'); // 200.000 cash
        $this->at('2026-11-06 08:00:00');
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '300.000'], $this->headers)->assertOk()
            ->assertJsonPath('data.total_cash_sales', '200.000');
        $storedBefore = $this->inTenant($this->tenant, fn (): array => CashShift::query()->findOrFail($first)->only([
            'closed_at', 'total_cash_sales', 'total_refunds', 'expected_cash_balance', 'cash_difference',
        ]));

        $this->at('2026-11-06 09:00:00');
        $second = $this->openShift($this->storeId);
        $this->at('2026-11-06 10:00:00');
        // A paid invoice is cancelled with a manager PIN approval (the admin's).
        $adminId = $this->inTenant($this->tenant, function (): int {
            $admin = User::query()->orderBy('id')->firstOrFail();
            $admin->forceFill(['pos_pin' => Hash::make('1234')])->save();

            return (int) $admin->id;
        });
        $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', [
            'reason' => 'إلغاء في الوردية التالية',
            'override' => ['user_id' => $adminId, 'pin' => '1234'],
        ], $this->headers)->assertOk();

        [$refundShift, $refundAmount, $firstTotals, $storedAfter] = $this->inTenant($this->tenant, function () use ($first): array {
            $refund = Payment::query()->where('payment_number', 'like', 'PAY-REF-%')->firstOrFail();
            $shift = CashShift::query()->findOrFail($first);

            return [
                (int) $refund->cash_shift_id,
                (string) $refund->amount,
                app(ShiftService::class)->calculateShiftTotals($shift),
                $shift->only(['closed_at', 'total_cash_sales', 'total_refunds', 'expected_cash_balance', 'cash_difference']),
            ];
        });

        $this->assertSame($second, $refundShift, 'The PAY-REF voucher belongs to the shift open at the cancel.');
        $this->assertSame('-200.000', $refundAmount);
        $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()
            ->assertJsonPath('active_shift.id', $second)
            ->assertJsonPath('metrics.total_cash_sales', '0.000')
            ->assertJsonPath('metrics.total_refunds', '200.000')
            ->assertJsonPath('metrics.expected_cash_balance', '-100.000');

        // The earlier shift: its stored record and its recomputed figures are unchanged.
        $this->assertEquals($storedBefore, $storedAfter);
        $this->assertSame('200.000', $firstTotals['total_cash_sales']);
        $this->assertSame('0.000', $firstTotals['total_refunds']);
        $this->assertSame('300.000', $firstTotals['expected_cash_balance']);
    }

    public function test_a_voucher_without_store_id_is_stamped_with_the_shift_of_the_store_for_store_maps_it_to(): void
    {
        $storeless = $this->createTenantUser($this->tenant, 'cashier', [], ['default_store_id' => null]);
        $userOfB = $this->createTenantUser($this->tenant, 'cashier', [], ['default_store_id' => $this->storeB]);

        $this->at('2026-11-07 08:00:00');
        $shiftA = $this->openShift($this->storeId);
        $shiftB = $this->openShift($this->storeB);
        $this->at('2026-11-07 09:00:00');

        $this->inTenant($this->tenant, function () use ($storeless, $userOfB, $shiftA, $shiftB): void {
            $this->assertSame($this->storeId, (int) Store::getMainStore()?->id, 'fixture: the harness store is the main store');

            $invoiceB = Invoice::create([
                'invoice_number' => 'INV-DT-B', 'store_id' => $this->storeB, 'customer_id' => $this->customerId,
                'invoice_date' => now()->toDateString(), 'subtotal' => '50.000', 'discount_amount' => '0.000',
                'net_total' => '50.000', 'paid_amount' => '0.000', 'remaining_amount' => '50.000', 'total_cost' => '0.000',
                'status' => 'confirmed', 'payment_type' => 'credit', 'payment_method' => 'cash',
            ]);
            $purchaseB = Purchase::create([
                'purchase_number' => 'PUR-DT-B', 'supplier_id' => $this->supplierId, 'store_id' => $this->storeB,
                'purchase_date' => now()->toDateString(), 'status' => 'received', 'payment_status' => 'unpaid',
                'subtotal' => '80.000', 'discount_amount' => '0.000', 'net_total' => '80.000',
                'paid_amount' => '0.000', 'remaining_amount' => '80.000',
            ]);

            $cases = [
                'via its invoice' => [['customer_id' => $this->customerId, 'invoice_id' => $invoiceB->id, 'user_id' => $storeless->id], $this->storeB],
                'via its purchase' => [['supplier_id' => $this->supplierId, 'purchase_id' => $purchaseB->id, 'user_id' => $storeless->id], $this->storeB],
                "via the user's default store" => [['customer_id' => $this->customerId, 'user_id' => $userOfB->id], $this->storeB],
                'user without a store: main store' => [['customer_id' => $this->customerId, 'user_id' => $storeless->id], $this->storeId],
                'no user: main store' => [['customer_id' => $this->customerId], $this->storeId],
            ];
            $shiftOf = [$this->storeId => $shiftA, $this->storeB => $shiftB];
            $attribution = app(ShiftAttribution::class);

            $n = 0;
            foreach ($cases as $label => [$links, $expectedStore]) {
                $payment = Payment::create(array_merge([
                    'payment_number' => 'PAY-DT-'.(++$n), 'amount' => '10.000', 'payment_method' => 'cash',
                    'payment_date' => now()->toDateString(),
                ], $links));
                $this->assertNull($payment->store_id, $label);

                $mappedBy = array_values(array_filter(
                    [$this->storeId, $this->storeB],
                    fn (int $store): bool => Payment::query()->forStore($store)->whereKey($payment->id)->exists(),
                ));
                $this->assertSame([$expectedStore], $mappedBy, $label.': Payment::forStore maps it to exactly this store');
                $this->assertSame($expectedStore, $attribution->storeIdOf($payment), $label);
                $this->assertSame($shiftOf[$expectedStore], (int) $payment->cash_shift_id, $label.': stamped with that store\'s open shift');
            }
        });
    }

    /** Moves the clock to a UTC instant; the app sees (and stores) the Cairo wall clock. */
    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC')->setTimezone(self::TZ));
    }

    private function openShift(int $storeId): int
    {
        return $this->inTenant($this->tenant, function () use ($storeId): int {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());

            return (int) app(ShiftService::class)->openShift('100.000', null, $storeId)->id;
        });
    }

    /**
     * A POS sale of the item at 20.000 in the main store; returns the invoice id.
     *
     * @param  array<string, string>|null  $headers
     */
    private function sale(string $quantity, string $type = 'cash', ?array $headers = null): int
    {
        return (int) $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->customerId, 'payment_type' => $type, 'payment_method' => 'cash',
            'items' => [['item_id' => $this->itemId, 'quantity' => $quantity, 'unit_price' => '20.000']],
        ], $headers ?? $this->headers)->assertCreated()->json('data.id');
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function linesTotal(array $report): string
    {
        $total = bcadd((string) $report['opening_cash_balance'], (string) $report['total_payments_collected'], 3);
        $total = bcsub($total, (string) $report['total_expenses'], 3);
        $total = bcsub($total, (string) $report['total_supplier_paid'], 3);

        return bcsub($total, (string) $report['total_refunds'], 3);
    }
}
