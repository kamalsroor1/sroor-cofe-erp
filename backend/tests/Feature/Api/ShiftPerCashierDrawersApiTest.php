<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Shifts\GetDailyJournalPrintDataAction;
use App\Enums\ShiftMode;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Payment;
use App\Models\ReturnDocument;
use App\Models\StorePosSetting;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ShiftService;
use App\Services\TreasuryService;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * SHIFT-02: per-cashier branches after the SHIFT-01 review.
 *
 *  - the daily journal (screen + A4 print), the treasury summary and the dashboard sum the
 *    drawers of EVERY open cashier shift of the branch, never one arbitrary cashier's;
 *  - a reversal / refund goes to the still-open shift that holds the original document's money
 *    (admin cancels a cashier's cash sale), else the writer's own open shift, else none;
 *  - a cash row written by someone without an open shift (an admin's expense) is in no shift's
 *    Z but is in the treasury and in the journal's "outside shifts" total;
 *  - the stamp follows the open shift's own mode, not a separate read of the branch setting.
 */
final class ShiftPerCashierDrawersApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeId;

    private int $customerId;

    private int $itemId;

    private int $adminId;

    private User $cashierA;

    private User $cashierB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;

        [$this->customerId, $this->itemId, $this->adminId] = $this->inTenant($this->tenant, function (): array {
            $admin = User::query()->orderBy('id')->firstOrFail();
            $admin->forceFill(['pos_pin' => Hash::make('4321')])->save();

            $customer = Customer::create(['name' => 'عميل الأدراج', 'phone' => '01000007801', 'price_tier' => 'retail', 'is_active' => true, 'current_balance' => '0.000']);
            $item = Item::create([
                'name' => 'بن درج', 'code' => 'DRW-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '500.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '500.000']);

            return [(int) $customer->id, (int) $item->id, (int) $admin->id];
        });

        $this->cashierA = $this->createTenantUser($this->tenant, 'cashier');
        $this->cashierB = $this->createTenantUser($this->tenant, 'cashier');
    }

    // ------------------------------------------------------------------ BLOCKER 1: branch drawers

    public function test_the_daily_journal_and_its_print_sum_every_cashiers_drawer(): void
    {
        $this->open($this->cashierA, '500')->assertCreated();
        $this->open($this->cashierB, '200')->assertCreated();
        $this->sale($this->cashierA, '50.000')->assertCreated(); // 1000 cash
        $this->sale($this->cashierB, '15.000')->assertCreated(); // 300 cash

        $journal = $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant))->assertOk();
        $journal->assertJsonPath('data.summary.opening_cash_balance', '700.000')
            ->assertJsonPath('data.summary.expected_cash_in_drawer', '2000.000')
            ->assertJsonPath('data.summary.cash_sales', '1300.000')
            ->assertJsonPath('data.summary.outside_shifts_cash_in', '0.000')
            ->assertJsonPath('data.open_shifts.count', 2)
            ->assertJsonPath('data.open_shifts.opening_cash_balance', '700.000')
            ->assertJsonPath('data.open_shifts.expected_cash_balance', '2000.000');
        $this->assertEqualsCanonicalizing(
            ['1500.000', '500.000'],
            array_column($journal->json('data.open_shifts.shifts'), 'expected_cash_balance'),
        );

        $this->inTenant($this->tenant, function (): void {
            $print = app(GetDailyJournalPrintDataAction::class)->execute(app(TenantClock::class)->today(), $this->storeId);
            $this->assertSame('700.000', $print['openingCashBalance']);
            $this->assertSame('2000.000', $print['expectedCashInDrawer']);
        });
    }

    public function test_a_cashier_sees_only_their_own_open_shift_in_the_journal(): void
    {
        $shiftA = (int) $this->open($this->cashierA, '500')->json('data.id');
        $this->open($this->cashierB, '200')->assertCreated();

        $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant, $this->cashierA))->assertOk()
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.active_shift.id', $shiftA)
            ->assertJsonPath('data.active_shift.is_own', true)
            ->assertJsonPath('data.summary.opening_cash_balance', null)
            ->assertJsonPath('data.summary.expected_cash_in_drawer', null)
            ->assertJsonPath('data.open_shifts.count', 2)
            ->assertJsonPath('data.open_shifts.opening_cash_balance', null)
            ->assertJsonCount(1, 'data.open_shifts.shifts')
            ->assertJsonPath('data.open_shifts.shifts.0.id', $shiftA);
    }

    public function test_a_past_day_opening_float_sums_each_cashiers_first_shift(): void
    {
        $yesterday = $this->inTenant($this->tenant, fn (): string => now()->subDay()->toDateString());

        $this->inTenant($this->tenant, function () use ($yesterday): void {
            $make = fn (User $user, string $float, string $at, ?string $closedAt) => CashShift::query()->create([
                'user_id' => $user->id, 'store_id' => $this->storeId, 'shift_number' => 'SH-'.uniqid(),
                'status' => $closedAt === null ? 'open' : 'closed', 'shift_mode' => ShiftMode::PerCashier->value,
                'opened_at' => $yesterday.' '.$at, 'closed_at' => $closedAt !== null ? $yesterday.' '.$closedAt : null,
                'opening_cash_balance' => $float,
            ]);
            $make($this->cashierA, '300.000', '09:00:00', '13:00:00');
            $make($this->cashierA, '999.000', '14:00:00', '18:00:00'); // A's second shift: not the day's opening
            $make($this->cashierB, '150.000', '10:00:00', '20:00:00');
        });

        $this->getJson('/api/v1/daily-journal?date='.$yesterday, $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.summary.opening_cash_balance', '450.000');
    }

    public function test_the_treasury_summary_and_the_dashboard_show_branch_totals(): void
    {
        $this->open($this->cashierA, '500')->assertCreated();
        $this->open($this->cashierB, '200')->assertCreated();
        $this->sale($this->cashierA, '50.000')->assertCreated();
        $this->sale($this->cashierB, '15.000')->assertCreated();

        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('active_shift.opening_cash_balance', '700.000')
            ->assertJsonPath('active_shift.open_shifts_count', 2)
            ->assertJsonPath('open_shifts.count', 2)
            ->assertJsonPath('open_shifts.expected_cash_balance', '2000.000');

        $dashboard = $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant))->assertOk();
        $this->assertSame('700.000', $dashboard->json('data.active_shift.starting_cash'));
        $this->assertSame('2000.000', $dashboard->json('data.active_shift.current_cash'));
        $this->assertSame(2, $dashboard->json('data.active_shift.open_shifts_count'));
        $this->assertSame(2, $dashboard->json('data.open_shifts.count'));
    }

    public function test_a_shared_branch_keeps_its_single_drawer_figures(): void
    {
        $this->setMode(ShiftMode::Shared);
        $this->open($this->cashierA, '500')->assertCreated();
        $this->sale($this->cashierA, '5.000')->assertCreated();
        $this->sale($this->cashierB, '2.500')->assertCreated();

        $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.summary.opening_cash_balance', '500.000')
            ->assertJsonPath('data.summary.expected_cash_in_drawer', '650.000')
            ->assertJsonPath('data.open_shifts.count', 1);
        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('active_shift.opening_cash_balance', '500.000');
    }

    // ------------------------------------------------------------------ BLOCKER 2: writers without a shift

    public function test_an_admin_cancelling_a_cashiers_cash_sale_refunds_from_that_cashiers_open_shift(): void
    {
        $shiftA = (int) $this->open($this->cashierA, '100')->json('data.id');
        $this->sale($this->cashierA, '2.000')->assertCreated(); // 40
        $before = $this->totals($shiftA)['expected_cash_balance'];
        $invoiceId = (int) $this->sale($this->cashierA, '7.500')->assertCreated()->json('data.id'); // 150
        $this->assertSame('290.000', $this->totals($shiftA)['expected_cash_balance']);

        $this->cancel($invoiceId)->assertOk(); // the admin has no shift of their own

        $totals = $this->totals($shiftA);
        $this->assertSame($before, $totals['expected_cash_balance']);
        $this->assertSame('140.000', $before);
        $this->assertSame('150.000', $totals['total_refunds']);
        $this->inTenant($this->tenant, function () use ($shiftA): void {
            $refund = Payment::query()->whereNotNull('reverses_payment_id')->sole();
            $this->assertSame($shiftA, (int) $refund->cash_shift_id);
            $this->assertSame($this->adminId, (int) $refund->user_id);
        });
    }

    public function test_a_reversal_of_a_closed_shifts_sale_goes_to_the_writers_own_open_shift_else_none(): void
    {
        $shiftA = (int) $this->open($this->cashierA, '0')->json('data.id');
        $first = (int) $this->sale($this->cashierA, '5.000')->json('data.id');
        $second = (int) $this->sale($this->cashierA, '2.000')->json('data.id');
        $this->inTenant($this->tenant, fn () => app(ShiftService::class)->closeShift(CashShift::query()->findOrFail($shiftA), '140.000'));

        // No shift for the admin: the refund is outside every drawer.
        $this->cancel($first)->assertOk();
        // The admin opens their own shift (later: an unstamped row of the very second a shift
        // opens falls in its inclusive legacy window): the next refund leaves that drawer.
        $this->travel(5)->minutes();
        $adminShift = (int) $this->open(null, '500')->assertCreated()->json('data.id');
        $this->cancel($second)->assertOk();

        $this->inTenant($this->tenant, function () use ($first, $second, $adminShift, $shiftA): void {
            $refundOf = fn (int $invoiceId) => Payment::query()->where('invoice_id', $invoiceId)->whereNotNull('reverses_payment_id')->sole();
            $this->assertNull($refundOf($first)->cash_shift_id);
            $this->assertSame($adminShift, (int) $refundOf($second)->cash_shift_id);

            $closed = app(ShiftService::class)->calculateShiftTotals(CashShift::query()->findOrFail($shiftA));
            $this->assertSame('0.000', $closed['total_refunds']);
            $this->assertSame('140.000', $closed['expected_cash_balance']);
        });
        $this->assertSame('460.000', $this->totals($adminShift)['expected_cash_balance']);

        $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.summary.outside_shifts_cash_out', '100.000')
            ->assertJsonPath('data.summary.outside_shifts_net_cash', '-100.000');
    }

    public function test_a_cash_return_of_a_cashiers_sale_refunds_from_the_original_open_shift(): void
    {
        $shiftA = (int) $this->open($this->cashierA, '100')->json('data.id');
        $invoiceId = (int) $this->sale($this->cashierA, '5.000')->json('data.id'); // 100
        $lineId = $this->inTenant($this->tenant, fn (): int => (int) InvoiceItem::query()->where('invoice_id', $invoiceId)->value('id'));

        // The admin (no shift) books a cash return against cashier A's sale.
        $returnId = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => 'cash',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '1.500']],
        ], $this->tenantHeaders($this->tenant))->assertCreated()->json('data.id');

        $this->inTenant($this->tenant, function () use ($shiftA, $returnId): void {
            $this->assertSame($shiftA, (int) ReturnDocument::query()->findOrFail($returnId)->cash_shift_id);
            $this->assertSame($shiftA, (int) Payment::query()->where('return_id', $returnId)->sole()->cash_shift_id);
        });
        $totals = $this->totals($shiftA);
        $this->assertSame('30.000', $totals['total_refunds']);
        $this->assertSame('170.000', $totals['expected_cash_balance']);
    }

    public function test_a_cash_return_taken_by_another_cashier_comes_out_of_that_cashiers_drawer(): void
    {
        $shiftA = (int) $this->open($this->cashierA, '100')->json('data.id');
        $shiftB = (int) $this->open($this->cashierB, '50')->json('data.id');
        $invoiceId = (int) $this->sale($this->cashierA, '5.000')->json('data.id'); // 100
        $lineId = $this->inTenant($this->tenant, function () use ($invoiceId): int {
            $this->cashierB->givePermissionTo('returns.manage');

            return (int) InvoiceItem::query()->where('invoice_id', $invoiceId)->value('id');
        });

        // Cashier B (own open shift) refunds A's customer in cash: B's drawer paid it.
        $returnId = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => 'cash',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '1.500']],
        ], $this->tenantHeaders($this->tenant, $this->cashierB))->assertCreated()->json('data.id');

        $this->inTenant($this->tenant, function () use ($shiftB, $returnId): void {
            $this->assertSame($shiftB, (int) ReturnDocument::query()->findOrFail($returnId)->cash_shift_id);
            $this->assertSame($shiftB, (int) Payment::query()->where('return_id', $returnId)->sole()->cash_shift_id);
        });
        $this->assertSame('0.000', $this->totals($shiftA)['total_refunds']);
        $this->assertSame('200.000', $this->totals($shiftA)['expected_cash_balance']);
        $this->assertSame('30.000', $this->totals($shiftB)['total_refunds']);
        $this->assertSame('20.000', $this->totals($shiftB)['expected_cash_balance']);
    }

    public function test_an_admin_expense_without_a_shift_is_in_the_treasury_but_in_no_shift(): void
    {
        $shiftA = (int) $this->open($this->cashierA, '100')->json('data.id');
        $shiftB = (int) $this->open($this->cashierB, '50')->json('data.id');
        $cashBefore = $this->treasuryCash();

        $expense = $this->inTenant($this->tenant, fn (): Expense => Expense::query()->create([
            'expense_number' => 'EXP-ADM-'.uniqid(), 'category' => 'packaging', 'title' => 'إيجار', 'amount' => '75.250',
            'expense_date' => app(TenantClock::class)->businessDate(), 'payment_method' => 'cash',
            'user_id' => $this->adminId, 'store_id' => $this->storeId,
        ]));

        $this->assertNull($expense->cash_shift_id);
        foreach ([[$shiftA, '100.000'], [$shiftB, '50.000']] as [$shiftId, $expected]) {
            $totals = $this->totals($shiftId);
            $this->assertSame('0.000', $totals['total_expenses']);
            $this->assertSame($expected, $totals['expected_cash_balance']);
        }
        $this->assertSame(bcsub($cashBefore, '75.250', 3), $this->treasuryCash());

        $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant))->assertOk()
            ->assertJsonPath('data.summary.cash_expenses', '75.250')
            ->assertJsonPath('data.summary.outside_shifts_cash_out', '75.250')
            ->assertJsonPath('data.summary.outside_shifts_cash_in', '0.000')
            ->assertJsonPath('data.summary.expected_cash_in_drawer', '150.000');
    }

    // ------------------------------------------------------------------ NIT 4: the shift's own mode

    public function test_stamping_follows_the_open_shifts_own_mode_not_the_branch_setting(): void
    {
        $shiftA = (int) $this->open($this->cashierA, '0')->json('data.id');
        // The setting is flipped behind the guard's back (a race the old unlocked read could see).
        $this->setMode(ShiftMode::Shared);

        $this->inTenant($this->tenant, function () use ($shiftA): void {
            $expense = fn (User $user): Expense => Expense::query()->create([
                'expense_number' => 'EXP-MODE-'.uniqid(), 'category' => 'packaging', 'title' => 'أكياس', 'amount' => '3.000',
                'expense_date' => app(TenantClock::class)->businessDate(), 'payment_method' => 'cash',
                'user_id' => $user->id, 'store_id' => $this->storeId,
            ]);

            $this->assertNull($expense($this->cashierB)->cash_shift_id);
            $this->assertSame($shiftA, (int) $expense($this->cashierA)->cash_shift_id);
        });
    }

    // ------------------------------------------------------------------ auth / isolation

    public function test_the_journal_is_guarded_and_isolated_between_tenants(): void
    {
        $this->open($this->cashierA, '500')->assertCreated();

        $this->getJson('/api/v1/daily-journal', $this->tenantGuestHeaders($this->tenant))->assertUnauthorized();

        $other = $this->createTenant();
        $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($other))->assertOk()
            ->assertJsonPath('data.open_shifts.count', 0)
            ->assertJsonPath('data.summary.opening_cash_balance', '0.000')
            ->assertJsonPath('data.summary.expected_cash_in_drawer', null);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @return array<string, string>
     */
    private function totals(int $shiftId): array
    {
        return $this->inTenant($this->tenant, fn (): array => app(ShiftService::class)->calculateShiftTotals(CashShift::query()->findOrFail($shiftId)));
    }

    private function treasuryCash(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) app(TreasuryService::class)->getBalances($this->storeId)['cash']['balance']);
    }

    private function setMode(ShiftMode $mode): void
    {
        $this->inTenant($this->tenant, fn () => StorePosSetting::query()->updateOrCreate(['store_id' => $this->storeId], ['shift_mode' => $mode->value]));
    }

    private function open(?User $user, string $float): TestResponse
    {
        return $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => $float], $this->tenantHeaders($this->tenant, $user));
    }

    private function sale(User $user, string $quantity): TestResponse
    {
        return $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $this->customerId, 'payment_type' => 'cash', 'payment_method' => 'cash',
            'items' => [['item_id' => $this->itemId, 'quantity' => $quantity, 'unit_price' => '20.000']],
        ], $this->tenantHeaders($this->tenant, $user));
    }

    private function cancel(int $invoiceId): TestResponse
    {
        return $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', [
            'reason' => 'طلب العميل',
            'override' => ['user_id' => $this->adminId, 'pin' => '4321'],
        ], $this->tenantHeaders($this->tenant));
    }
}
