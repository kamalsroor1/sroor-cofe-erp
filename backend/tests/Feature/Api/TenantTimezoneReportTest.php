<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * SETG-2 (CTO Q-S1): the tenant timezone drives display and the day boundaries of
 * reports, the dashboard, the daily journal and shifts. Storage is unchanged: timestamps
 * stay in the application timezone (config('app.timezone')), DATE columns stay as-is.
 *
 * Fixed instant used everywhere: 2026-10-08 18:00 UTC
 *  - Africa/Cairo (app/storage timezone and tenant default): still 2026-10-08 (evening)
 *  - Asia/Tokyo (UTC+9, no DST):                            already 2026-10-09 03:00
 */
final class TenantTimezoneReportTest extends TenantTestCase
{
    private const NOW_UTC = '2026-10-08 18:00:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse(self::NOW_UTC, 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_clock_falls_back_to_the_default_timezone(): void
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function (): void {
            $clock = app(TenantClock::class);

            $this->assertSame('Africa/Cairo', $clock->timezone());
            $this->assertSame('2026-10-08', $clock->today());
        });
    }

    public function test_clock_resolves_the_tenant_day_and_its_storage_bounds(): void
    {
        $tenant = $this->tenantInTimezone('Asia/Tokyo');

        $this->inTenant($tenant, function (): void {
            $clock = app(TenantClock::class);
            $storageTz = (string) config('app.timezone');

            $this->assertSame('Asia/Tokyo', $clock->timezone());
            $this->assertSame('2026-10-09', $clock->today());
            $this->assertSame('Asia/Tokyo', $clock->now()->getTimezone()->getName());

            // Tokyo midnight of 2026-10-09 is 2026-10-08 15:00 UTC, expressed in the storage timezone.
            [$start, $end] = $clock->dayBounds('2026-10-09');
            $this->assertSame($storageTz, $start->getTimezone()->getName());
            $this->assertSame($storageTz, $end->getTimezone()->getName());
            $this->assertTrue($start->equalTo(CarbonImmutable::parse('2026-10-08 15:00:00', 'UTC')));
            $this->assertTrue($end->equalTo(CarbonImmutable::parse('2026-10-09 14:59:59.999999', 'UTC')));

            // Display: a stored instant rendered on the tenant wall clock.
            $stored = CarbonImmutable::parse(self::NOW_UTC, 'UTC')->setTimezone($storageTz);
            $this->assertSame('2026-10-09 03:00:00', $clock->toTenant($stored)?->format('Y-m-d H:i:s'));
            $this->assertNull($clock->toTenant(null));
        });
    }

    public function test_today_report_starts_at_midnight_in_the_tenant_timezone(): void
    {
        $tenant = $this->tenantInTimezone('Asia/Tokyo');
        $this->seedInvoices($tenant, ['2026-10-08' => '100.000', '2026-10-09' => '250.000']);

        $this->getJson('/api/v1/reports/summary?period=today', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('period.from_date', '2026-10-09')
            ->assertJsonPath('period.to_date', '2026-10-09')
            ->assertJsonPath('summary.invoices_count', 1)
            ->assertJsonPath('summary.total_sales', 250);

        $this->getJson('/api/v1/reports/summary?period=yesterday', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('period.from_date', '2026-10-08')
            ->assertJsonPath('summary.invoices_count', 1)
            ->assertJsonPath('summary.total_sales', 100);

        // Default period (this_month) ends on the tenant's today, not the server's.
        $this->getJson('/api/v1/reports/summary', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('period.from_date', '2026-10-01')
            ->assertJsonPath('period.to_date', '2026-10-09')
            ->assertJsonPath('summary.invoices_count', 2);
    }

    public function test_explicit_report_dates_are_not_shifted(): void
    {
        $tenant = $this->tenantInTimezone('Asia/Tokyo');
        $this->seedInvoices($tenant, ['2026-10-08' => '100.000', '2026-10-09' => '250.000']);

        $this->getJson('/api/v1/reports/summary?from_date=2026-10-08&to_date=2026-10-08', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('period.from_date', '2026-10-08')
            ->assertJsonPath('period.to_date', '2026-10-08')
            ->assertJsonPath('summary.total_sales', 100);
    }

    public function test_each_tenant_uses_its_own_timezone(): void
    {
        $tokyo = $this->tenantInTimezone('Asia/Tokyo');
        $cairo = $this->createTenant();
        $this->seedInvoices($tokyo, ['2026-10-09' => '250.000']);
        $this->seedInvoices($cairo, ['2026-10-08' => '70.000', '2026-10-09' => '999.000']);

        $this->getJson('/api/v1/reports/summary?period=today', $this->tenantHeaders($cairo))
            ->assertStatus(200)
            ->assertJsonPath('period.from_date', '2026-10-08')
            ->assertJsonPath('summary.invoices_count', 1)
            ->assertJsonPath('summary.total_sales', 70);

        $this->getJson('/api/v1/reports/summary?period=today', $this->tenantHeaders($tokyo))
            ->assertStatus(200)
            ->assertJsonPath('period.from_date', '2026-10-09')
            ->assertJsonPath('summary.total_sales', 250);
    }

    public function test_dashboard_today_uses_the_tenant_day(): void
    {
        $tenant = $this->tenantInTimezone('Asia/Tokyo');
        $this->seedInvoices($tenant, ['2026-10-08' => '100.000', '2026-10-09' => '250.000']);

        $response = $this->getJson('/api/v1/dashboard', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('metrics.today_invoices_count', 1)
            ->assertJsonPath('metrics.today_sales', 250)
            ->assertJsonPath('data.analytics.today.invoices_count', 1);

        $trend = $response->json('data.analytics.daily_trend');
        $this->assertIsArray($trend);
        $this->assertSame('2026-10-09', end($trend)['date']);
    }

    public function test_daily_journal_defaults_to_the_tenant_day(): void
    {
        $tenant = $this->tenantInTimezone('Asia/Tokyo');
        $this->seedInvoices($tenant, ['2026-10-08' => '100.000', '2026-10-09' => '250.000']);

        $this->getJson('/api/v1/daily-journal', $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('data.date', '2026-10-09')
            ->assertJsonCount(1, 'data.invoices')
            ->assertJsonPath('data.summary.total_sales', 250);
    }

    public function test_shift_number_rolls_over_at_tenant_midnight(): void
    {
        $tenant = $this->tenantInTimezone('Asia/Tokyo');

        // Opened at 23:00 Tokyo on 2026-10-08 (same server day as "now", previous tenant day).
        $this->inTenant($tenant, function () use ($tenant): void {
            DB::table('cash_shifts')->insert([
                'user_id' => $this->tenantAdmin($tenant)->getKey(),
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'shift_number' => 'SHIFT-20261008-001',
                'status' => 'closed',
                'opened_at' => $this->storageTime('2026-10-08 14:00:00'),
                'closed_at' => $this->storageTime('2026-10-08 14:30:00'),
                'created_at' => $this->storageTime('2026-10-08 14:00:00'),
                'updated_at' => $this->storageTime('2026-10-08 14:30:00'),
            ]);
        });

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '0'], $this->tenantHeaders($tenant))
            ->assertStatus(201)
            ->assertJsonPath('data.shift_number', 'SHIFT-20261009-001');
    }

    public function test_z_report_shows_times_on_the_tenant_clock(): void
    {
        $tenant = $this->tenantInTimezone('Asia/Tokyo');

        $shiftId = $this->inTenant($tenant, function () use ($tenant): int {
            return (int) DB::table('cash_shifts')->insertGetId([
                'user_id' => $this->tenantAdmin($tenant)->getKey(),
                'store_id' => $this->tenantStore($tenant)->getKey(),
                'shift_number' => 'SHIFT-20261009-001',
                'status' => 'closed',
                'opened_at' => $this->storageTime('2026-10-08 16:00:00'),
                'closed_at' => $this->storageTime('2026-10-08 17:30:00'),
                'created_at' => $this->storageTime('2026-10-08 16:00:00'),
                'updated_at' => $this->storageTime('2026-10-08 17:30:00'),
            ]);
        });

        $this->getJson("/api/v1/shifts/{$shiftId}/z-report", $this->tenantHeaders($tenant))
            ->assertStatus(200)
            ->assertJsonPath('report.opened_at', '2026-10-09 01:00:00')
            ->assertJsonPath('report.closed_at', '2026-10-09 02:30:00');
    }

    public function test_reports_still_require_authentication_and_permission(): void
    {
        $tenant = $this->tenantInTimezone('Asia/Tokyo');

        $this->getJson('/api/v1/reports/summary?period=today', [
            'Accept' => 'application/json',
            'X-Tenant' => (string) $tenant->getTenantKey(),
        ])->assertStatus(401);

        /** @var User $cashier */
        $cashier = $this->createTenantUser($tenant);

        $this->getJson('/api/v1/reports/summary?period=today', $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403);
    }

    private function tenantInTimezone(string $timezone): Tenant
    {
        $tenant = $this->createTenant();

        $this->inTenant($tenant, function () use ($timezone): void {
            Setting::set('timezone', $timezone);
        });

        return $tenant;
    }

    /**
     * Confirmed invoices on the tenant's main store, one per business date.
     *
     * @param  array<string, string>  $totalsByDate  invoice_date => net_total
     */
    private function seedInvoices(Tenant $tenant, array $totalsByDate): void
    {
        $this->inTenant($tenant, function () use ($tenant, $totalsByDate): void {
            $now = $this->storageTime(self::NOW_UTC);
            $customerId = DB::table('customers')->insertGetId([
                'name' => 'Timezone Customer',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $i = 0;
            foreach ($totalsByDate as $date => $total) {
                $i++;
                DB::table('invoices')->insert([
                    'invoice_number' => 'TZ-'.$i,
                    'customer_id' => $customerId,
                    'user_id' => $this->tenantAdmin($tenant)->getKey(),
                    'store_id' => $this->tenantStore($tenant)->getKey(),
                    'invoice_date' => $date,
                    'payment_type' => 'cash',
                    'status' => 'confirmed',
                    'payment_status' => 'paid',
                    'subtotal' => $total,
                    'net_total' => $total,
                    'paid_amount' => $total,
                    'remaining_amount' => '0.000',
                    'total_cost' => '0.000',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }

    /** A UTC instant written the way the app stores timestamps (application timezone). */
    private function storageTime(string $utc): string
    {
        return CarbonImmutable::parse($utc, 'UTC')
            ->setTimezone((string) config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }
}
