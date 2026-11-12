<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DashboardCache;
use App\Support\TenantClock;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-02: the dashboard runs a FIXED number of SQL statements whatever the invoice volume (no
 * per-invoice reading anywhere, including the peak-hours chart), returns the best sellers in a
 * stable order when quantities tie, and buckets the peak hours exactly on the tenant clock for
 * half-hour (+05:30) and three-quarter-hour (+05:45) timezones. Expected figures are computed
 * by hand from the fixtures.
 */
#[Group('mysql')]
#[Group('mariadb-reports')]
final class DashboardQueryBudgetApiTest extends TenantTestCase
{
    /** Upper bound of statements of a cold dashboard (cache miss) with an open shift. */
    private const QUERY_BUDGET = 60;

    /** 2026-11-10 06:00 UTC: Cairo (storage, UTC+2 in November) 08:00. */
    private const NOW_UTC = '2026-11-10 06:00:00';

    private Tenant $tenant;

    private int $store;

    private int $userId;

    private int $customerId;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(CarbonImmutable::parse(self::NOW_UTC, 'UTC'));
        $this->tenant = $this->createTenant();
        $this->store = (int) $this->tenantStore($this->tenant)->id;
        $this->inTenant($this->tenant, function (): void {
            $this->userId = (int) User::query()->orderBy('id')->value('id');
            $this->customerId = (int) DB::table('customers')->insertGetId(['name' => 'عميل', 'phone' => '01000008501', 'price_tier' => 'retail', 'is_active' => true, 'current_balance' => '0.000', 'created_at' => $this->storage(), 'updated_at' => $this->storage()]);
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_the_query_count_of_a_cold_dashboard_does_not_grow_with_the_invoices(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $opened = $this->storage(CarbonImmutable::now()->subHours(3));
            DB::table('cash_shifts')->insert(['user_id' => $this->userId, 'store_id' => $this->store, 'shift_number' => 'Q-SHIFT', 'status' => 'open', 'opened_at' => $opened, 'opening_cash_balance' => '100.000', 'created_at' => $opened, 'updated_at' => $opened]);
        });

        $this->invoices(50);
        $this->dashboard()->assertOk(); // warm-up: token, settings and permission caches
        $fifty = $this->coldDashboardQueries();

        $this->invoices(450);
        $fiveHundred = $this->coldDashboardQueries();

        $this->assertSame($fifty, $fiveHundred, 'the dashboard must not run a query per invoice');
        $this->assertLessThanOrEqual(self::QUERY_BUDGET, $fiveHundred);
    }

    public function test_tied_best_sellers_keep_the_same_order_on_consecutive_requests(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->itemSale('TIE-B', '2.500', '12.000');
            $this->itemSale('TIE-A', '2.500', '10.000');
            $this->itemSale('TIE-C', '1.000', '7.000');
        });

        $first = $this->coldDashboard()->json('data.top_selling_items');
        $second = $this->coldDashboard()->json('data.top_selling_items');

        $this->assertSame($first, $second);
        // Equal quantities: lower item id first (TIE-B was created before TIE-A).
        $this->assertSame(['TIE-B', 'TIE-A', 'TIE-C'], array_column($first, 'code'));
        $this->assertSame(['2.500', '2.500', '1.000'], array_column($first, 'total_qty'));
        $this->assertSame(['30.000', '25.000', '7.000'], array_column($first, 'total_revenue'));
    }

    public function test_peak_hours_are_exact_for_a_half_hour_timezone(): void
    {
        // Asia/Kolkata = UTC+05:30. Cairo 08:29 = 06:29 UTC = 11:59 local; Cairo 08:30 = 12:00 local:
        // two invoices of the same storage hour land in two different tenant hours.
        $this->tenantTimezone('Asia/Kolkata');
        $this->inTenant($this->tenant, function (): void {
            $this->invoice('2026-11-10', '2026-11-10 08:29:00', '10.250');
            $this->invoice('2026-11-10', '2026-11-10 08:30:00', '20.500');
            $this->invoice('2026-11-10', '2026-11-10 08:44:59', '0.125');
            $this->invoice('2026-11-10', '2026-11-10 07:59:59', '1.000'); // 11:29 local
        });

        $hours = $this->hourly();
        $this->assertSame(['sales' => '11.250', 'invoices' => 2], $hours[11]);
        $this->assertSame(['sales' => '20.625', 'invoices' => 2], $hours[12]);
        $this->assertSame('31.875', $this->sumHours($hours));
    }

    public function test_peak_hours_are_exact_for_a_three_quarter_hour_timezone(): void
    {
        // Asia/Kathmandu = UTC+05:45. Cairo 08:14 = 06:14 UTC = 11:59 local; Cairo 08:15 = 12:00 local.
        $this->tenantTimezone('Asia/Kathmandu');
        $this->inTenant($this->tenant, function (): void {
            $this->invoice('2026-11-10', '2026-11-10 08:14:00', '3.333');
            $this->invoice('2026-11-10', '2026-11-10 08:15:00', '4.444');
            $this->invoice('2026-11-10', '2026-11-10 09:14:59', '0.001'); // 12:59 local
        });

        $hours = $this->hourly();
        $this->assertSame(['sales' => '3.333', 'invoices' => 1], $hours[11]);
        $this->assertSame(['sales' => '4.445', 'invoices' => 2], $hours[12]);
        $this->assertSame('7.778', $this->sumHours($hours));
    }

    public function test_peak_hours_are_exact_for_a_negative_half_hour_timezone(): void
    {
        // America/St_Johns = UTC-03:30 in November (DST ended on 1 November 2026). Cairo storage is
        // UTC+02:00, so local = storage - 5:30. Cairo 07:29 = 05:29 UTC = 01:59 local; Cairo 07:30 =
        // 02:00 local; Cairo 05:29 = 03:29 UTC = 23:59 local of the PREVIOUS day (wraps to hour 23).
        $this->tenantTimezone('America/St_Johns');
        $this->inTenant($this->tenant, function (): void {
            $day = app(TenantClock::class)->businessDate();
            $this->invoice($day, '2026-11-10 07:29:00', '1.500');
            $this->invoice($day, '2026-11-10 07:30:00', '2.250');
            $this->invoice($day, '2026-11-10 07:59:59', '0.250');
            $this->invoice($day, '2026-11-10 05:29:00', '9.999');
        });

        $hours = $this->hourly();
        $this->assertSame(['sales' => '1.500', 'invoices' => 1], $hours[1]);
        $this->assertSame(['sales' => '2.500', 'invoices' => 2], $hours[2]);
        $this->assertSame(['sales' => '9.999', 'invoices' => 1], $hours[23]);
        $this->assertSame('13.999', $this->sumHours($hours));
    }

    public function test_an_invoice_without_created_at_is_left_out_of_the_peak_hours_only(): void
    {
        // Default tenant timezone = the storage timezone (Africa/Cairo): Cairo 07:10 is hour 7.
        $this->inTenant($this->tenant, function (): void {
            $day = app(TenantClock::class)->businessDate();
            $this->invoice($day, '2026-11-10 07:10:00', '5.000');
            $this->invoice($day, null, '2.500');
        });

        $response = $this->coldDashboard();
        $hours = [];
        foreach ($response->json('data.analytics.hourly_sales') as $row) {
            $hours[(int) $row['hour']] = ['sales' => $row['sales'], 'invoices' => $row['invoices']];
        }

        $this->assertSame(['sales' => '5.000', 'invoices' => 1], $hours[7]);
        $this->assertSame('5.000', $this->sumHours($hours), 'no hour can be derived without created_at');
        // ... but it is still a sale of the day.
        $response->assertJsonPath('data.metrics.today_invoices_count', 2)
            ->assertJsonPath('data.metrics.today_gross_sales', '7.500');
    }

    private function dashboard(): TestResponse
    {
        return $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, null, $this->store));
    }

    /** A dashboard request with the aggregate cache invalidated first (the settings stay warm). */
    private function coldDashboard(): TestResponse
    {
        $this->inTenant($this->tenant, fn () => DashboardCache::invalidate());

        return $this->dashboard()->assertOk();
    }

    private function coldDashboardQueries(): int
    {
        $this->inTenant($this->tenant, fn () => DashboardCache::invalidate());
        $count = 0;
        $counting = true;
        DB::listen(function (QueryExecuted $query) use (&$count, &$counting): void {
            if ($counting) {
                $count++;
            }
        });
        $this->dashboard()->assertOk();
        $counting = false;

        return $count;
    }

    /**
     * @return array<int, array{sales: string, invoices: int}>
     */
    private function hourly(): array
    {
        $out = [];
        foreach ($this->coldDashboard()->json('data.analytics.hourly_sales') as $row) {
            $out[(int) $row['hour']] = ['sales' => $row['sales'], 'invoices' => $row['invoices']];
        }

        return $out;
    }

    /**
     * @param  array<int, array{sales: string, invoices: int}>  $hours
     */
    private function sumHours(array $hours): string
    {
        return array_reduce($hours, fn (string $carry, array $h): string => bcadd($carry, $h['sales'], 3), '0.000');
    }

    private function tenantTimezone(string $timezone): void
    {
        $this->inTenant($this->tenant, fn () => Setting::set('timezone', $timezone));
    }

    /** $count cash invoices spread over the last 7 days and the hours of the day, with a line and a sale voucher each. */
    private function invoices(int $count): void
    {
        $this->inTenant($this->tenant, function () use ($count): void {
            $itemId = (int) DB::table('items')->insertGetId(['code' => 'Q-ITEM-'.(++$this->seq), 'name' => 'صنف', 'unit' => 'كجم', 'current_stock' => '100.000', 'cost_price' => '5.000', 'weighted_avg_cost' => '5.000', 'selling_price' => '8.000', 'min_stock_level' => '1.000', 'is_active' => true, 'created_at' => $this->storage(), 'updated_at' => $this->storage()]);
            $today = app(TenantClock::class)->businessDate();
            $base = (int) DB::table('invoices')->max('id') + 1;
            $invoices = $lines = $payments = [];
            for ($i = 0; $i < $count; $i++) {
                $id = $base + $i;
                $at = CarbonImmutable::parse($today.' 07:00:00', (string) config('app.timezone'))->subDays($i % 7)->addMinutes(($i * 37) % 600);
                $stamp = $at->format('Y-m-d H:i:s');
                $invoices[] = [
                    'id' => $id, 'invoice_number' => 'Q-BULK-'.$id, 'customer_id' => $this->customerId, 'user_id' => $this->userId, 'store_id' => $this->store,
                    'invoice_date' => $at->toDateString(), 'payment_type' => 'cash', 'payment_method' => 'cash', 'status' => 'confirmed',
                    'subtotal' => '8.000', 'net_total' => '8.000', 'paid_amount' => '8.000', 'remaining_amount' => '0.000', 'total_cost' => '5.000',
                    'created_at' => $stamp, 'updated_at' => $stamp,
                ];
                $lines[] = ['invoice_id' => $id, 'item_id' => $itemId, 'quantity' => '1.000', 'cost_price' => '5.000', 'unit_price' => '8.000', 'discount_amount' => '0.000', 'total_price' => '8.000', 'created_at' => $stamp, 'updated_at' => $stamp];
                $payments[] = ['payment_number' => 'PAY-INV-Q'.$id, 'customer_id' => $this->customerId, 'invoice_id' => $id, 'user_id' => $this->userId, 'store_id' => $this->store, 'amount' => '8.000', 'payment_date' => $at->toDateString(), 'payment_method' => 'cash', 'created_at' => $stamp, 'updated_at' => $stamp];
            }
            foreach (['invoices' => $invoices, 'invoice_items' => $lines, 'payments' => $payments] as $table => $rows) {
                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        });
    }

    private function itemSale(string $code, string $quantity, string $unitPrice): void
    {
        $itemId = (int) DB::table('items')->insertGetId(['code' => $code, 'name' => 'صنف '.$code, 'unit' => 'كجم', 'current_stock' => '100.000', 'cost_price' => '5.000', 'weighted_avg_cost' => '5.000', 'selling_price' => $unitPrice, 'min_stock_level' => '1.000', 'is_active' => true, 'created_at' => $this->storage(), 'updated_at' => $this->storage()]);
        $total = bcmul($quantity, $unitPrice, 3);
        $id = $this->invoice(app(TenantClock::class)->businessDate(), $this->storage(), $total);
        DB::table('invoice_items')->insert(['invoice_id' => $id, 'item_id' => $itemId, 'quantity' => $quantity, 'cost_price' => '5.000', 'unit_price' => $unitPrice, 'discount_amount' => '0.000', 'total_price' => $total, 'created_at' => $this->storage(), 'updated_at' => $this->storage()]);
    }

    private function invoice(string $date, ?string $createdAt, string $net, string $type = 'credit'): int
    {
        $this->seq++;

        return (int) DB::table('invoices')->insertGetId([
            'invoice_number' => 'Q-INV-'.$this->seq, 'customer_id' => $this->customerId, 'user_id' => $this->userId, 'store_id' => $this->store,
            'invoice_date' => $date, 'payment_type' => $type, 'payment_method' => 'cash', 'status' => 'confirmed',
            'subtotal' => $net, 'net_total' => $net, 'paid_amount' => $type === 'cash' ? $net : '0.000', 'remaining_amount' => $type === 'cash' ? '0.000' : $net,
            'total_cost' => '0.000', 'created_at' => $createdAt, 'updated_at' => $createdAt,
        ]);
    }

    private function storage(?CarbonImmutable $at = null): string
    {
        return ($at ?? CarbonImmutable::now())->setTimezone((string) config('app.timezone'))->format('Y-m-d H:i:s');
    }
}
