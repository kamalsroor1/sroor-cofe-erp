<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Expenses\CreateExpenseAction;
use App\Actions\Items\AdjustItemStockAction;
use App\Actions\Logs\GetActivityLogsAction;
use App\DTOs\Expenses\ExpenseDTO;
use App\DTOs\Invoices\CreateInvoiceDTO;
use App\DTOs\Items\AdjustStockDTO;
use App\DTOs\Purchases\PurchaseDTO;
use App\DTOs\Returns\ReturnDocumentDTO;
use App\DTOs\Transfers\CreateTransferDTO;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Tenant;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TenantTestCase;

/**
 * SETG-2 ext, lane 2H: document date defaults and the daily counters (expense number,
 * adjustment number, activity-log "today" stats) follow the tenant BUSINESS day.
 *
 * Fixed instant: 2026-10-09 01:30 Africa/Cairo with cutoff 03:00 -> business day
 * 2026-10-08, i.e. [2026-10-08 03:00, 2026-10-09 03:00) on the tenant clock.
 */
final class BusinessDayDocumentDefaultsTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse('2026-10-09 01:30:00', 'Africa/Cairo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dto_date_defaults_are_the_business_date(): void
    {
        $tenant = $this->tenantWithCutoff();

        $this->inTenant($tenant, function (): void {
            $this->assertSame('2026-10-08', CreateInvoiceDTO::fromArray(['customer_id' => 1])->invoice_date);
            $this->assertSame('2026-10-08', PurchaseDTO::fromArray(['supplier_id' => 1])->purchase_date);
            $this->assertSame('2026-10-08', ReturnDocumentDTO::fromArray(['return_type' => 'sales_return'])->return_date);
            $this->assertSame('2026-10-08', CreateTransferDTO::fromArray(['from_store_id' => 1, 'to_store_id' => 2])->transfer_date);

            // An explicit date is never overridden.
            $this->assertSame('2026-01-02', CreateInvoiceDTO::fromArray(['customer_id' => 1, 'invoice_date' => '2026-01-02'])->invoice_date);
        });
    }

    public function test_expense_number_and_counter_follow_the_business_day(): void
    {
        $tenant = $this->tenantWithCutoff();

        $this->inTenant($tenant, function () use ($tenant): void {
            $userId = (int) $this->tenantAdmin($tenant)->getKey();
            $storeId = (int) $this->tenantStore($tenant)->getKey();

            // Previous business day (02:00 on the 8th is before the 03:00 cutoff): not counted.
            $this->expenseAt('EXP-OLD-1', $userId, $storeId, '2026-10-08 02:00:00');
            // Same business day, earlier calendar date: counted.
            $this->expenseAt('EXP-OLD-2', $userId, $storeId, '2026-10-08 20:00:00');

            $expense = app(CreateExpenseAction::class)->execute(ExpenseDTO::fromArray([
                'title' => 'مصروف ليلي',
                'category' => 'other',
                'amount' => '12.500',
                'expense_date' => '2026-10-08',
                'store_id' => $storeId,
            ]), $userId);

            $this->assertSame('EXP-261008-0002', $expense->expense_number);
        });
    }

    public function test_adjustment_number_and_counter_follow_the_business_day(): void
    {
        $tenant = $this->tenantWithCutoff();

        $this->inTenant($tenant, function () use ($tenant): void {
            $userId = (int) $this->tenantAdmin($tenant)->getKey();
            $storeId = (int) $this->tenantStore($tenant)->getKey();
            $item = Item::create([
                'name' => 'صنف تسوية',
                'code' => 'ADJ-ITEM-'.uniqid(),
                'unit' => 'كجم',
                'cost_price' => '10.000',
                'selling_price' => '15.000',
                'current_stock' => '0.000',
                'is_active' => true,
            ]);

            $movement = app(AdjustItemStockAction::class)->execute(AdjustStockDTO::fromArray((int) $item->id, [
                'store_id' => $storeId,
                'quantity' => '1.250',
                'movement_type' => 'stock_adjustment_in',
            ]), $userId);

            $this->assertSame('ADJ-261008-0001', $movement->document_number);
            $this->assertSame(1, StockMovement::query()->where('document_number', 'ADJ-261008-0001')->count());
        });
    }

    public function test_activity_log_today_stats_count_the_business_day(): void
    {
        $tenant = $this->tenantWithCutoff();

        $this->inTenant($tenant, function (): void {
            ActivityLog::query()->delete();
            $this->logAt('2026-10-08 02:59:59'); // previous business day
            $this->logAt('2026-10-08 03:00:00'); // first instant of the business day
            $this->logAt('2026-10-09 01:00:00'); // still the business day of the 8th
            $this->logAt('2026-10-09 03:00:00'); // next business day (future here, but outside)

            $stats = app(GetActivityLogsAction::class)->execute([])['stats'];

            $this->assertSame(2, $stats['today_total']);
        });
    }

    private function tenantWithCutoff(): Tenant
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('business_day_cutoff', '03:00'));

        return $tenant;
    }

    private function expenseAt(string $number, int $userId, int $storeId, string $cairoTime): void
    {
        $expense = Expense::create([
            'expense_number' => $number,
            'title' => 'سابق',
            'category' => 'other',
            'cost_center' => 'operational',
            'amount' => '1.000',
            'expense_date' => '2026-10-08',
            'payment_method' => 'cash',
            'user_id' => $userId,
            'store_id' => $storeId,
        ]);
        $expense->forceFill(['created_at' => $this->storageTime($cairoTime)])->saveQuietly();
    }

    private function logAt(string $cairoTime): void
    {
        $log = ActivityLog::create(['module' => 'system', 'action' => 'created', 'description' => 'x']);
        $log->forceFill(['created_at' => $this->storageTime($cairoTime)])->saveQuietly();
    }

    private function storageTime(string $cairoTime): CarbonImmutable
    {
        return CarbonImmutable::parse($cairoTime, 'Africa/Cairo')->setTimezone((string) config('app.timezone'));
    }
}
