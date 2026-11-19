<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Expenses\GetExpensesSummaryAction;
use App\Actions\Shifts\GetDailyJournalAction;
use App\DTOs\Reports\ReportFilterDTO;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\StockTransferService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Tests\TenantTestCase;

/**
 * DATE-03: date boundary fixes — the expenses "this month" total, business dates serialized as
 * Y-m-d, one number format per document type, report period presets (last_month, Saturday
 * week start, unknown preset = 422) and the daily journal opening float of a past date.
 */
final class DateBoundariesDate03Test extends TenantTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_expenses_month_total_is_the_business_month_up_to_today(): void
    {
        // 2026-11-01 01:30 Cairo with cutoff 03:00 -> business day 2026-10-31 (still October).
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-01 01:30:00', 'Africa/Cairo'));
        $tenant = $this->tenantWithCutoff();

        $this->inTenant($tenant, function () use ($tenant): void {
            $userId = (int) $this->tenantAdmin($tenant)->getKey();
            $storeId = (int) $this->tenantStore($tenant)->getKey();

            $this->expense('EXP-A', $userId, $storeId, '2026-09-30', '7.000', 'cash');   // last month
            $this->expense('EXP-B', $userId, $storeId, '2026-10-05', '10.000', 'cash');  // counted
            $this->expense('EXP-C', $userId, $storeId, '2026-10-31', '2.500', 'bank');   // counted (all methods)
            $this->expense('EXP-D', $userId, $storeId, '2026-11-01', '50.000', 'cash');  // calendar today, not business today
            $this->expense('EXP-E', $userId, $storeId, '2027-01-25', '999.000', 'cash'); // future

            $summary = app(GetExpensesSummaryAction::class)->execute($storeId);

            $this->assertSame('12.500', $summary['total_month']);
            $this->assertSame('10.000', $summary['total_cash']);
        });
    }

    public function test_business_dates_serialize_as_plain_dates(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $storeId = (int) $this->tenantStore($tenant)->getKey();
            $payment = Payment::create([
                'payment_number' => 'PAY-DATE03-1',
                'store_id' => $storeId,
                'amount' => '10.000',
                'payment_date' => '2026-11-12',
                'payment_method' => 'cash',
            ]);

            $this->assertSame('2026-11-12', $payment->fresh()?->toArray()['payment_date']);

            $expense = $this->expense('EXP-DATE03', (int) $this->tenantAdmin($tenant)->getKey(), $storeId, '2026-11-12', '1.000', 'cash');
            $this->assertSame('2026-11-12', $expense->fresh()?->toArray()['expense_date']);
        });
    }

    public function test_stock_transfer_numbers_keep_their_own_prefix(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-19 12:00:00', 'Africa/Cairo'));
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            // Treasury transfers are TTRF-… (TreasuryStoreIsolationTest); stock transfers stay TRF-….
            $number = app(StockTransferService::class)->generateUniqueNumber();

            $this->assertStringStartsWith('TRF-20261119-', $number);
            $this->assertStringStartsNotWith('TTRF-', $number);
        });
    }

    public function test_last_month_preset_is_the_whole_previous_month(): void
    {
        $dto = ReportFilterDTO::fromArray(['period' => 'last_month'], null, CarbonImmutable::parse('2026-03-31'));

        $this->assertSame('2026-02-01', $dto->from_date);
        $this->assertSame('2026-02-28', $dto->to_date);

        $january = ReportFilterDTO::fromArray(['period' => 'last_month'], null, CarbonImmutable::parse('2026-01-15'));
        $this->assertSame('2025-12-01', $january->from_date);
        $this->assertSame('2025-12-31', $january->to_date);
    }

    public function test_this_week_starts_on_saturday(): void
    {
        // 2026-11-20 is a Friday: the week began Saturday 2026-11-14.
        $friday = ReportFilterDTO::fromArray(['period' => 'this_week'], null, CarbonImmutable::parse('2026-11-20'));
        $this->assertSame('2026-11-14', $friday->from_date);
        $this->assertSame('2026-11-20', $friday->to_date);

        // On a Saturday the week starts that same day.
        $saturday = ReportFilterDTO::fromArray(['period' => 'this_week'], null, CarbonImmutable::parse('2026-11-21'));
        $this->assertSame('2026-11-21', $saturday->from_date);
    }

    public function test_reports_reject_an_unknown_period_and_accept_last_month(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->tenantHeaders($tenant);

        $this->getJson('/api/v1/reports/summary?period=bogus', $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['period']);

        $this->getJson('/api/v1/reports/summary?period=last_month', $headers)->assertOk();
        $this->getJson('/api/v1/reports/summary?period=this_week', $headers)->assertOk();
    }

    public function test_daily_journal_of_a_past_date_uses_that_days_opening_float(): void
    {
        Carbon::setTestNow(CarbonImmutable::parse('2026-11-19 12:00:00', 'Africa/Cairo'));
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($tenant): void {
            $userId = (int) $this->tenantAdmin($tenant)->getKey();
            $storeId = (int) $this->tenantStore($tenant)->getKey();

            $this->shift($userId, $storeId, 'SHF-D3-1', '2026-11-17 09:00:00', '2026-11-17 22:00:00', '150.000');
            $this->shift($userId, $storeId, 'SHF-D3-2', '2026-11-19 09:00:00', null, '500.000');

            $journal = app(GetDailyJournalAction::class);

            $this->assertSame('150.000', $journal->execute('2026-11-17', $storeId)['summary']['opening_cash_balance']);
            $this->assertSame('500.000', $journal->execute('2026-11-19', $storeId)['summary']['opening_cash_balance']);
            // No shift opened or running on the 18th.
            $this->assertSame('0.000', $journal->execute('2026-11-18', $storeId)['summary']['opening_cash_balance']);
        });
    }

    private function tenantWithCutoff(): Tenant
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => Setting::set('business_day_cutoff', '03:00'));

        return $tenant;
    }

    private function expense(string $number, int $userId, int $storeId, string $date, string $amount, string $method): Expense
    {
        return Expense::create([
            'expense_number' => $number,
            'title' => 'مصروف',
            'category' => 'other',
            'cost_center' => 'operational',
            'amount' => $amount,
            'expense_date' => $date,
            'payment_method' => $method,
            'user_id' => $userId,
            'store_id' => $storeId,
        ]);
    }

    private function shift(int $userId, int $storeId, string $number, string $openedAt, ?string $closedAt, string $opening): void
    {
        $toStorage = fn (string $cairo): CarbonImmutable => CarbonImmutable::parse($cairo, 'Africa/Cairo')->setTimezone((string) config('app.timezone'));

        CashShift::create([
            'user_id' => $userId,
            'store_id' => $storeId,
            'shift_number' => $number,
            'status' => $closedAt === null ? 'open' : 'closed',
            'opened_at' => $toStorage($openedAt),
            'closed_at' => $closedAt !== null ? $toStorage($closedAt) : null,
            'opening_cash_balance' => $opening,
        ]);
    }
}
