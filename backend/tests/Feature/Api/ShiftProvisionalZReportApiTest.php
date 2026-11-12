<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Shifts\GetDailyJournalPrintDataAction;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ShiftService;
use Tests\TenantTestCase;

/**
 * BUG-03:
 *  - the Z report of an OPEN shift is a provisional X report computed live with the figures of
 *    GET /shifts/current (no closing time, nothing counted); a closed shift reads its stored lines;
 *  - the daily journal's expected_cash_in_drawer is the open shift's expected balance whatever the
 *    selected date (null without an open shift); net_cash_today stays the day's movement;
 *  - treasury summary / report count a cash refund to a customer as an OUTFLOW (never netted out of
 *    the receipts) and the closing balance stays the same.
 */
final class ShiftProvisionalZReportApiTest extends TenantTestCase
{
    private const FIGURES = [
        'opening_cash_balance', 'total_cash_sales', 'total_customer_receipts', 'total_credit_sales',
        'total_payments_collected', 'total_expenses', 'total_supplier_paid', 'total_refunds', 'expected_cash_balance',
    ];

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
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000007603', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-B3', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'بن', 'code' => 'ZR-3', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
                'price_retail' => '20.000', 'price_wholesale' => '20.000',
                'current_stock' => '200.000', 'min_stock_level' => '1.000', 'is_active' => true,
            ]);
            $this->itemId = (int) $item->id;
            foreach ([$this->storeId, $this->storeB] as $store) {
                StoreStock::create(['store_id' => $store, 'item_id' => $item->id, 'quantity' => '100.000']);
            }
        });
    }

    public function test_the_z_report_of_an_open_shift_is_provisional_and_equals_the_live_metrics(): void
    {
        $metrics = $this->dayWithSaleReturnAndExpense();

        // 100 opening + 520 cash sale - 150 expense - 260 cash refund.
        $this->assertSame('520.000', (string) $metrics['total_cash_sales']);
        $this->assertSame('260.000', (string) $metrics['total_refunds']);
        $this->assertSame('150.000', (string) $metrics['total_expenses']);
        $this->assertSame('210.000', (string) $metrics['expected_cash_balance']);

        $shiftId = (int) $this->getJson('/api/v1/shifts/current', $this->headers)->json('active_shift.id');
        $report = $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->headers)->assertOk()->json('report');

        foreach (self::FIGURES as $line) {
            $this->assertSame((string) $metrics[$line], $report[$line], 'Provisional line '.$line.' equals /shifts/current.');
        }
        $this->assertSame('open', $report['status']);
        $this->assertTrue($report['is_provisional']);
        $this->assertNull($report['closed_at']);
        $this->assertNull($report['actual_cash_balance']);
        $this->assertNull($report['cash_difference']);
        $this->assertNotNull($report['generated_at']);
    }

    public function test_after_closing_the_z_report_reads_the_same_figures_from_the_stored_lines(): void
    {
        $metrics = $this->dayWithSaleReturnAndExpense();
        $shiftId = (int) $this->getJson('/api/v1/shifts/current', $this->headers)->json('active_shift.id');
        $provisional = $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->headers)->assertOk()->json('report');

        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '205.500'], $this->headers)->assertOk();

        $report = $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->headers)->assertOk()->json('report');
        foreach (self::FIGURES as $line) {
            $this->assertSame((string) $metrics[$line], $report[$line], 'Stored line '.$line.' equals the live metric before the close.');
            $this->assertSame($provisional[$line], $report[$line]);
        }
        $this->assertSame('closed', $report['status']);
        $this->assertFalse($report['is_provisional']);
        $this->assertNotNull($report['closed_at']);
        $this->assertSame($report['closed_at'], $report['generated_at']);
        $this->assertSame('205.500', $report['actual_cash_balance']);
        $this->assertSame('-4.500', $report['cash_difference']);
    }

    public function test_the_journal_expects_the_open_shifts_drawer_and_keeps_net_cash_to_the_selected_day(): void
    {
        // Yesterday: shift opened with 100, cash sale 520.
        $this->travelTo(now()->setDate(2026, 11, 11)->setTime(9, 0));
        $this->openShift('100.000');
        $this->travel(5)->minutes();
        $this->cashSale('26.000');

        // Today: cash sale 100, same shift still open.
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(9, 0));
        $this->cashSale('5.000');
        $this->travel(5)->minutes();

        $expected = $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()->json('metrics.expected_cash_balance');
        $this->assertSame('720.000', (string) $expected);

        $this->getJson('/api/v1/daily-journal?date=2026-11-12', $this->headers)->assertOk()
            ->assertJsonPath('data.summary.expected_cash_in_drawer', '720.000')
            ->assertJsonPath('data.summary.net_cash_today', '100.000')
            ->assertJsonPath('data.summary.opening_cash_balance', '100.000');

        // Another selected day: same drawer, that day's movement.
        $this->getJson('/api/v1/daily-journal?date=2026-11-11', $this->headers)->assertOk()
            ->assertJsonPath('data.summary.expected_cash_in_drawer', '720.000')
            ->assertJsonPath('data.summary.net_cash_today', '520.000');
    }

    public function test_the_journal_has_no_expected_drawer_cash_without_an_open_shift(): void
    {
        $this->getJson('/api/v1/daily-journal', $this->headers)->assertOk()
            ->assertJsonPath('data.active_shift', null)
            ->assertJsonPath('data.summary.expected_cash_in_drawer', null)
            ->assertJsonPath('data.summary.net_cash_today', '0.000');

        // After the close: the day's movement stays, nothing is expected in a drawer any more.
        $this->dayWithSaleReturnAndExpense();
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '210.000'], $this->headers)->assertOk();

        $this->getJson('/api/v1/daily-journal', $this->headers)->assertOk()
            ->assertJsonPath('data.summary.expected_cash_in_drawer', null)
            ->assertJsonPath('data.summary.net_cash_today', '110.000'); // 520 - 150 - 260
    }

    public function test_the_journal_of_a_store_ignores_another_stores_open_shift(): void
    {
        $this->dayWithSaleReturnAndExpense();

        $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($this->tenant, null, $this->storeB))->assertOk()
            ->assertJsonPath('data.summary.expected_cash_in_drawer', null);
    }

    public function test_the_journal_a4_print_shows_the_open_shifts_expected_drawer_or_no_open_shift(): void
    {
        $render = fn (?int $storeId): array => $this->inTenant($this->tenant, function () use ($storeId): array {
            $data = app(GetDailyJournalPrintDataAction::class)->execute(now()->toDateString(), $storeId);

            return [$data['expectedCashInDrawer'], view('layouts.print-daily-journal-a4', $data)->render()];
        });

        [$none, $html] = $render($this->storeId);
        $this->assertNull($none);
        $this->assertStringContainsString(e(__('treasury.no_active_shift_desc')), $html);

        $this->dayWithSaleReturnAndExpense();
        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '50.000'], $this->tenantHeaders($this->tenant, null, $this->storeB))->assertCreated();

        [$expected, $html] = $render($this->storeId);
        $this->assertSame('210.000', $expected);
        $this->assertStringContainsString(e(__('treasury.expected_drawer_open_shift_sub')), $html);

        // Every store: the sum of each store's open shift (210 + 50).
        [$all] = $render(null);
        $this->assertSame('260.000', $all);
    }

    public function test_the_treasury_summary_reports_a_cash_refund_as_outflow_with_an_unchanged_balance(): void
    {
        $this->dayWithSaleReturnAndExpense();

        $response = $this->getJson('/api/v1/treasury/summary', $this->headers)->assertOk();
        $cash = $response->json('balances.accounts.cash');

        // Before BUG-03: inflows 260 (520 netted with the 260 refund), outflows 150, balance 210.
        $this->assertSame('520.000', $cash['inflows']);
        $this->assertSame('410.000', $cash['outflows']);
        $this->assertSame('260.000', $cash['refunds']);
        $this->assertSame('210.000', $cash['balance']); // 100 opening + 520 - 410, as before the change
        $this->assertSame(bcsub(bcadd('100.000', $cash['inflows'], 3), $cash['outflows'], 3), $cash['balance']);

        // The "today" section agrees with the accounts.
        $response->assertJsonPath('today.total_inflow', '520.000')
            ->assertJsonPath('today.total_outflow', '410.000')
            ->assertJsonPath('today.cash_refunds', '260.000');
    }

    public function test_the_treasury_report_reports_a_cash_refund_as_outflow_with_an_unchanged_closing_balance(): void
    {
        $this->dayWithSaleReturnAndExpense();

        $data = $this->getJson('/api/v1/reports/treasury?period=today&per_page=200', $this->headers)->assertOk()->json('data');
        $cash = $data['accounts']['cash'];

        $this->assertSame('100.000', $cash['opening_balance']);
        $this->assertSame('520.000', $cash['inflows']);
        $this->assertSame('410.000', $cash['outflows']);
        $this->assertSame('260.000', $cash['refunds']);
        $this->assertSame('110.000', $cash['net_change']);
        $this->assertSame('210.000', $cash['closing_balance']); // unchanged by BUG-03
        $this->assertSame('520.000', $data['total_inflows']);
        $this->assertSame('410.000', $data['total_outflows']);
        $this->assertSame('260.000', $data['total_refunds']);
        $this->assertSame('210.000', $data['total_liquidity']);

        // The ledger agrees: receipts are its debits, outflows (refund included) its credits.
        $debits = '0.000';
        $credits = '0.000';
        foreach ($data['ledger_entries'] as $entry) {
            $debits = bcadd($debits, (string) $entry['debit'], 3);
            $credits = bcadd($credits, (string) $entry['credit'], 3);
        }
        $this->assertSame($data['total_inflows'], $debits);
        $this->assertSame($data['total_outflows'], $credits);
        $refunds = array_values(array_filter($data['ledger_entries'], fn (array $e): bool => bccomp((string) $e['credit'], '260.000', 3) === 0));
        $this->assertCount(1, $refunds);
        $this->assertSame('outflow', $refunds[0]['type']);
    }

    public function test_a_cashier_gets_404_for_another_cashiers_and_another_stores_shift(): void
    {
        $this->dayWithSaleReturnAndExpense();
        $shiftB = (int) $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '50.000'], $this->tenantHeaders($this->tenant, null, $this->storeB))
            ->assertCreated()->json('data.id');
        $shiftA = (int) $this->getJson('/api/v1/shifts/current', $this->headers)->json('active_shift.id');

        // A pos.access-only cashier (no daily_journal.view): see ShiftReportAuthorizationApiTest.
        $cashierHeaders = $this->tenantHeaders($this->tenant, $this->createTenantUser($this->tenant, null, ['pos.access']));

        // The admin's (another cashier's) open shift of the cashier's own store, and store B's: 404,
        // so the live provisional figures never leak.
        $this->getJson('/api/v1/shifts/'.$shiftA.'/z-report', $cashierHeaders)->assertNotFound();
        $this->getJson('/api/v1/shifts/'.$shiftB.'/z-report', $cashierHeaders)->assertNotFound();

        // Another tenant's shift id is not found either.
        $other = $this->createTenant();
        $this->getJson('/api/v1/shifts/'.$shiftA.'/z-report', $this->tenantHeaders($other))->assertNotFound();
    }

    public function test_the_touched_endpoints_require_authentication(): void
    {
        $guest = $this->tenantGuestHeaders($this->tenant);

        foreach (['/api/v1/shifts/1/z-report', '/api/v1/daily-journal', '/api/v1/treasury/summary', '/api/v1/reports/treasury'] as $uri) {
            $this->getJson($uri, $guest)->assertUnauthorized();
        }
    }

    /**
     * Opens the main store's shift with 100 and books a cash sale of 520 (26 x 20), a cash return
     * refund of 260 (13 units) and a cash expense of 150. Returns GET /shifts/current metrics.
     *
     * @return array<string, mixed>
     */
    private function dayWithSaleReturnAndExpense(): array
    {
        $this->openShift('100.000');
        [$invoiceId, $lineId] = $this->cashSale('26.000');

        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => 'cash',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '13.000']],
        ], $this->headers)->assertCreated();

        $this->postJson('/api/v1/expenses', [
            'title' => 'مصروف', 'category' => 'تشغيل', 'cost_center' => 'general', 'amount' => '150.000',
            'expense_date' => now()->toDateString(), 'payment_method' => 'cash',
        ], $this->headers)->assertCreated();

        return $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()->json('metrics');
    }

    private function openShift(string $opening): void
    {
        $this->inTenant($this->tenant, function () use ($opening): void {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $shift = app(ShiftService::class)->openShift($opening, null, $this->storeId);
            $shift->forceFill(['opened_at' => now()->subMinute()])->save();
        });
    }

    /**
     * @return array{int, int} invoice id and its line id
     */
    private function cashSale(string $quantity): array
    {
        return $this->inTenant($this->tenant, function () use ($quantity): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId, 'store_id' => $this->storeId,
                'payment_type' => 'cash', 'payment_method' => 'cash',
                'items' => [['item_id' => $this->itemId, 'quantity' => $quantity, 'unit_price' => '20.000']],
            ]);

            return [(int) $invoice->id, (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->value('id')];
        });
    }
}
