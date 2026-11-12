<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\ShiftService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TenantTestCase;

/**
 * BUG-06: a closed shift stores its cash expenses and cash supplier payments, so the Z report
 * lines (opening + collected - expenses - supplier payments - refunds) add up to the stored
 * expected cash balance; `shifts:recalculate-totals` fills both for shifts closed before the fix.
 */
final class ShiftZReportTotalsApiTest extends TenantTestCase
{
    private const COMMAND = 'shifts:recalculate-totals';

    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private int $storeId;

    private int $storeB;

    private int $customerId;

    private int $supplierId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);

        $this->inTenant($this->tenant, function (): void {
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000007606', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->supplierId = (int) Supplier::create(['name' => 'مورد', 'phone' => '01000007607', 'current_balance' => '1000.000', 'is_active' => true])->id;
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-B6', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $item = Item::create([
                'name' => 'بن', 'code' => 'ZR-6', 'category' => 'coffee_beans', 'unit' => 'كجم',
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

    public function test_every_z_line_equals_the_live_metric_and_the_lines_add_up_to_expected(): void
    {
        $metrics = $this->fullDayBeforeClose();

        // 100 opening + 200 cash sale - 30.250 expense - 40.500 supplier - 60 refund.
        $this->assertSame('30.250', (string) $metrics['total_expenses']);
        $this->assertSame('40.500', (string) $metrics['total_supplier_paid']);
        $this->assertSame('60.000', (string) $metrics['total_refunds']);
        $this->assertSame('169.250', (string) $metrics['expected_cash_balance']);

        $shiftId = $this->closeShift('169.250')->json('data.id');
        $report = $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->headers)->assertOk()->json('report');

        foreach (['opening_cash_balance', 'total_cash_sales', 'total_credit_sales', 'total_payments_collected', 'total_expenses', 'total_supplier_paid', 'total_refunds', 'expected_cash_balance'] as $line) {
            $this->assertSame((string) $metrics[$line], $report[$line], 'Z line '.$line.' equals the live metric taken before the close.');
        }

        $this->assertSame($report['expected_cash_balance'], $this->linesTotal($report));
        $this->assertSame('169.250', $report['actual_cash_balance']);
        $this->assertSame('0.000', $report['cash_difference']);
    }

    public function test_a_non_cash_expense_and_another_stores_expense_are_excluded(): void
    {
        $this->fullDayBeforeClose();

        $shiftId = (int) $this->closeShift('169.250')->json('data.id');

        $this->inTenant($this->tenant, function () use ($shiftId): void {
            $shift = CashShift::query()->findOrFail($shiftId);
            // The visa expense (500) and branch B's cash expense (70) are not in the drawer.
            $this->assertSame('30.250', (string) $shift->total_expenses);
            $this->assertSame('40.500', (string) $shift->total_supplier_paid);
            $this->assertSame('169.250', (string) $shift->expected_cash_balance);
        });
    }

    public function test_the_close_response_and_the_shifts_index_carry_both_lines(): void
    {
        $this->fullDayBeforeClose();

        $this->closeShift('170.000')
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.total_expenses', '30.250')
            ->assertJsonPath('data.total_supplier_paid', '40.500')
            ->assertJsonPath('data.total_refunds', '60.000')
            ->assertJsonPath('data.expected_cash_balance', '169.250')
            ->assertJsonPath('data.cash_difference', '0.750');

        $this->getJson('/api/v1/shifts', $this->headers)->assertOk()
            ->assertJsonPath('data.0.total_expenses', '30.250')
            ->assertJsonPath('data.0.total_supplier_paid', '40.500');
    }

    public function test_an_open_shift_resource_carries_both_fields(): void
    {
        $this->openShift();

        $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()
            ->assertJsonPath('active_shift.total_expenses', '0.000')
            ->assertJsonPath('active_shift.total_supplier_paid', '0.000');
    }

    public function test_the_command_backfills_an_old_closed_shift_without_touching_its_signed_figures(): void
    {
        $this->fullDayBeforeClose();
        $shiftId = (int) $this->closeShift('170.000')->json('data.id');
        $this->simulatePreFixClose($shiftId);
        $before = $this->storedFigures($shiftId);
        $this->assertSame('0.000', $before['total_expenses']);
        $this->assertSame('0.000', $before['total_supplier_paid']);

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id]));
        $output = Artisan::output();

        $after = $this->storedFigures($shiftId);
        $this->assertSame('30.250', $after['total_expenses']);
        $this->assertSame('40.500', $after['total_supplier_paid']);
        foreach (['opening_cash_balance', 'total_cash_sales', 'total_credit_sales', 'total_payments_collected', 'total_refunds', 'expected_cash_balance', 'actual_cash_balance', 'cash_difference', 'status', 'closed_at'] as $column) {
            $this->assertSame($before[$column], $after[$column], $column.' is the signed-off record and never changes.');
        }
        $this->assertSame('170.000', $after['actual_cash_balance']);
        $this->assertSame('0.750', $after['cash_difference']);

        $report = $this->getJson('/api/v1/shifts/'.$shiftId.'/z-report', $this->headers)->assertOk()->json('report');
        $this->assertSame($report['expected_cash_balance'], $this->linesTotal($report));
        $this->assertStringNotContainsString(__('console.shift_totals.status_mismatch'), $output);

        // Idempotent: a second run changes nothing.
        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id]));
        $this->assertSame($after, $this->storedFigures($shiftId));
        $this->assertStringContainsString(__('console.shift_totals.done', ['checked' => 1, 'changed' => 0, 'total' => 1]), Artisan::output());
    }

    public function test_the_command_also_backfills_a_soft_deleted_closed_shift(): void
    {
        $this->fullDayBeforeClose();
        $shiftId = (int) $this->closeShift('170.000')->json('data.id');
        $this->simulatePreFixClose($shiftId);
        $this->inTenant($this->tenant, fn () => CashShift::query()->whereKey($shiftId)->delete());

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id]));

        // A shift restored later must not come back with zero lines.
        $stored = $this->inTenant($this->tenant, function () use ($shiftId): array {
            $shift = CashShift::withTrashed()->findOrFail($shiftId);

            return [$shift->trashed(), (string) $shift->total_expenses, (string) $shift->total_supplier_paid];
        });
        $this->assertSame([true, '30.250', '40.500'], $stored);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->fullDayBeforeClose();
        $shiftId = (int) $this->closeShift('169.250')->json('data.id');
        $this->simulatePreFixClose($shiftId);
        $before = $this->storedFigures($shiftId);

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id, '--dry-run' => true]));

        $this->assertSame($before, $this->storedFigures($shiftId));
        $this->assertStringContainsString(__('console.shift_totals.dry_run', ['checked' => 1, 'changed' => 1, 'total' => 1]), Artisan::output());
    }

    public function test_a_shift_edited_after_close_is_reported_and_its_expected_balance_is_kept(): void
    {
        $this->fullDayBeforeClose();
        $shiftId = (int) $this->closeShift('169.250')->json('data.id');
        $this->simulatePreFixClose($shiftId);
        $shiftNumber = $this->storedFigures($shiftId)['shift_number'];

        // A cash expense booked later but dated inside the closed shift's window.
        $this->inTenant($this->tenant, function () use ($shiftId): void {
            $shift = CashShift::query()->findOrFail($shiftId);
            $expense = Expense::create([
                'expense_number' => 'EXP-LATE-1', 'category' => 'misc', 'cost_center' => 'general', 'title' => 'متأخر',
                'amount' => '9.000', 'expense_date' => now()->toDateString(), 'payment_method' => 'cash', 'store_id' => $this->storeId,
            ]);
            $expense->forceFill(['created_at' => $shift->closed_at])->save();
        });

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id]));
        $output = Artisan::output();

        $after = $this->storedFigures($shiftId);
        $this->assertSame('39.250', $after['total_expenses']);
        $this->assertSame('40.500', $after['total_supplier_paid']);
        $this->assertSame('169.250', $after['expected_cash_balance'], 'Expected is never rewritten.');
        $this->assertStringContainsString(__('console.shift_totals.mismatches', [
            'count' => 1,
            'shifts' => $this->tenant->id.':'.$shiftNumber,
        ]), $output);
    }

    public function test_the_command_ignores_open_shifts_and_rejects_an_unknown_tenant(): void
    {
        $this->openShift();

        $this->assertSame(1, Artisan::call(self::COMMAND, ['--tenant' => 'no-such-tenant']));

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id]));
        $this->assertStringContainsString(__('console.shift_totals.done', ['checked' => 0, 'changed' => 0, 'total' => 1]), Artisan::output());
    }

    /**
     * Opens the shift and books, in the main store: a cash sale (200), a cash expense (30.250), a
     * visa expense (500, not cash), a cash supplier payment (40.500) and a cash sales-return refund
     * (60); in branch B: a cash expense (70). Returns the live metrics of GET /shifts/current.
     *
     * @return array<string, mixed>
     */
    private function fullDayBeforeClose(): array
    {
        $this->openShift();

        [$invoiceId, $lineId] = $this->inTenant($this->tenant, function (): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId, 'store_id' => $this->storeId,
                'payment_type' => 'cash', 'payment_method' => 'cash',
                'items' => [['item_id' => $this->itemId, 'quantity' => '10.000', 'unit_price' => '20.000']],
            ]);

            return [(int) $invoice->id, (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->value('id')];
        });

        foreach ([['30.250', 'cash'], ['500.000', 'visa']] as [$amount, $method]) {
            $this->postJson('/api/v1/expenses', [
                'title' => 'مصروف', 'category' => 'تشغيل', 'cost_center' => 'general', 'amount' => $amount,
                'expense_date' => now()->toDateString(), 'payment_method' => $method,
            ], $this->headers)->assertCreated();
        }

        $this->inTenant($this->tenant, fn () => Expense::create([
            'expense_number' => 'EXP-B-1', 'category' => 'misc', 'cost_center' => 'general', 'title' => 'فرع ب',
            'amount' => '70.000', 'expense_date' => now()->toDateString(), 'payment_method' => 'cash', 'store_id' => $this->storeB,
        ]));

        $this->postJson('/api/v1/payments/supplier-voucher', [
            'supplier_id' => $this->supplierId, 'amount' => '40.500', 'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
        ], $this->headers)->assertCreated();

        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'refund_method' => 'cash',
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '3.000']],
        ], $this->headers)->assertCreated();

        return $this->getJson('/api/v1/shifts/current', $this->headers)->assertOk()->json('metrics');
    }

    private function openShift(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $shift = app(ShiftService::class)->openShift('100.000', null, $this->storeId);
            $shift->forceFill(['opened_at' => now()->subMinute()])->save();
        });
    }

    /** @return TestResponse<Response> */
    private function closeShift(string $actual): TestResponse
    {
        return $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => $actual], $this->headers)->assertOk();
    }

    /** A shift closed before BUG-06: neither line was stored. */
    private function simulatePreFixClose(int $shiftId): void
    {
        $this->inTenant($this->tenant, fn () => CashShift::query()->whereKey($shiftId)->update(['total_expenses' => '0.000', 'total_supplier_paid' => '0.000']));
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

    /**
     * @return array<string, string>
     */
    private function storedFigures(int $shiftId): array
    {
        return $this->inTenant($this->tenant, function () use ($shiftId): array {
            $shift = CashShift::query()->findOrFail($shiftId);

            return array_map(fn ($value): string => (string) $value, [
                'shift_number' => $shift->shift_number,
                'status' => $shift->status,
                'closed_at' => $shift->closed_at?->toDateTimeString(),
                'opening_cash_balance' => $shift->opening_cash_balance,
                'total_cash_sales' => $shift->total_cash_sales,
                'total_credit_sales' => $shift->total_credit_sales,
                'total_payments_collected' => $shift->total_payments_collected,
                'total_expenses' => $shift->total_expenses,
                'total_supplier_paid' => $shift->total_supplier_paid,
                'total_refunds' => $shift->total_refunds,
                'expected_cash_balance' => $shift->expected_cash_balance,
                'actual_cash_balance' => $shift->actual_cash_balance,
                'cash_difference' => $shift->cash_difference,
            ]);
        });
    }
}
