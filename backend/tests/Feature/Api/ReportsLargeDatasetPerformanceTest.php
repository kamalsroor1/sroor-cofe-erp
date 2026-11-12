<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsLargeReportDataset;
use Tests\TenantTestCase;

/**
 * PERF-01 acceptance on a real InnoDB server: full-year reports and the dashboard over a
 * report-sized tenant (5,000 items, 50,000 invoices, ~130,000 lines, ~40,000 sale vouchers)
 * answer 200 within the budget below, and the report queries use the PERF-01 indexes.
 *
 * MySQL only (sqlite cannot show the query plans, and its timings say nothing about MySQL).
 * The budget per request:
 *  - peak memory growth of the request <= 64 MB;
 *  - wall time <= PERF_REPORT_MAX_SECONDS (default 2 s; the faster of two cold runs, cache
 *    emptied before each). Timing depends on the machine, so CI sets a looser budget; memory,
 *    response size and query count are hard limits everywhere;
 *  - response body <= 300 KB;
 *  - a FIXED number of queries (independent of the number of invoices), see QUERY_BUDGET.
 */
#[Group('mysql')]
#[Group('mysql-only')]
final class ReportsLargeDatasetPerformanceTest extends TenantTestCase
{
    use BuildsLargeReportDataset;

    private const MAX_MEMORY_BYTES = 64 * 1024 * 1024;

    /** Default wall-time budget; CI overrides it with PERF_REPORT_MAX_SECONDS (slower shared runners). */
    private const DEFAULT_MAX_SECONDS = 2.0;

    private const MAX_RESPONSE_BYTES = 300 * 1024;

    /** Upper bound of SQL statements per request, whatever the period or the data volume. */
    private const QUERY_BUDGET = 120;

    private Tenant $tenant;

    private string $today;

    private int $mainStore;

    private int $queries = 0;

    /** @var list<array{ms: float, sql: string}> */
    private array $statements = [];

    private int $base = 0;

    public function test_full_year_reports_and_the_dashboard_stay_within_the_budget_on_a_large_tenant(): void
    {
        $this->seedLargeTenant();

        $from = CarbonImmutable::parse($this->today)->subDays(364)->toDateString();
        $year = "from={$from}&to={$this->today}";
        $all = array_merge($this->tenantHeaders($this->tenant), ['X-Store-Id' => 'all']);
        $main = $this->tenantHeaders($this->tenant, null, $this->mainStore);

        $requests = [
            'summary (all, 12 months)' => ["/api/v1/reports/summary?{$year}", $all],
            'summary (store, 12 months)' => ["/api/v1/reports/summary?{$year}", $main],
            'comprehensive (all, 12 months)' => ["/api/v1/reports/comprehensive?{$year}", $all],
            'comprehensive (store, 12 months)' => ["/api/v1/reports/comprehensive?{$year}", $main],
            'stores (12 months)' => ["/api/v1/reports/stores?{$year}", $all],
            'items (12 months)' => ["/api/v1/reports/items?{$year}", $all],
            'top items (12 months)' => ["/api/v1/reports/top-items?{$year}", $all],
            'customers (12 months)' => ["/api/v1/reports/customers?{$year}", $all],
            'inventory (all)' => ["/api/v1/reports/inventory?{$year}", $all],
            'treasury (12 months)' => ["/api/v1/reports/treasury?{$year}", $all],
            'dashboard (all)' => ['/api/v1/dashboard', $all],
            'dashboard (store)' => ['/api/v1/dashboard', $main],
        ];

        DB::listen(function (QueryExecuted $query): void {
            $this->queries++;
            $this->statements[] = ['ms' => (float) $query->time, 'sql' => $query->sql];
        });

        // Each request runs twice with the cache emptied before each run (no ABC / dashboard cache
        // hit can help): memory, size and query count come from the first run, the time is the
        // faster of the two, which filters out noise from a busy machine.
        $results = [];
        foreach ($requests as $label => [$uri, $headers]) {
            Cache::flush();
            $first = $this->measure($uri, $headers);
            Cache::flush();
            $second = $this->measure($uri, $headers);
            $first['seconds'] = min($first['seconds'], $second['seconds']);
            $results[$label] = $first;
        }

        $this->report($results);

        foreach ($results as $label => $r) {
            $this->assertSame(200, $r['status'], $label);
            $this->assertLessThanOrEqual(self::MAX_MEMORY_BYTES, $r['memory'], "{$label}: peak memory");
            $this->assertLessThanOrEqual($this->maxSeconds(), $r['seconds'], "{$label}: time");
            $this->assertLessThanOrEqual(self::MAX_RESPONSE_BYTES, $r['bytes'], "{$label}: response size");
            $this->assertLessThanOrEqual(self::QUERY_BUDGET, $r['queries'], "{$label}: query count");
        }

        $this->assertQueryPlansUseThePerfIndexes();
    }

    private function seedLargeTenant(): void
    {
        $this->tenant = $this->createTenant();
        $this->mainStore = (int) $this->tenantStore($this->tenant)->id;

        $this->inTenant($this->tenant, function (): void {
            $this->today = app(TenantClock::class)->businessDate();
            $userId = (int) User::query()->orderBy('id')->value('id');
            $stats = $this->buildLargeReportDataset($this->mainStore, $userId, $this->today);
            $this->base = $stats['base'];
            fwrite(STDERR, sprintf(
                "\nPERF-01 dataset: %d items, %d invoices, %d lines, %d payments, %d returns, %d expenses\n",
                $stats['items'], $stats['invoices'], $stats['lines'], $stats['payments'], $stats['returns'], $stats['expenses'],
            ));
        });
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{slowest: list<array{ms: float, sql: string}>, db_seconds: float, status: int, seconds: float, memory: int, bytes: int, queries: int}
     */
    private function measure(string $uri, array $headers): array
    {
        gc_collect_cycles();
        memory_reset_peak_usage();
        $baseline = memory_get_usage();
        $this->queries = 0;
        $this->statements = [];

        $started = hrtime(true);
        $response = $this->getJson($uri, $headers);
        $seconds = (hrtime(true) - $started) / 1e9;

        usort($this->statements, fn (array $a, array $b): int => $b['ms'] <=> $a['ms']);

        return [
            'slowest' => array_slice($this->statements, 0, 3),
            'db_seconds' => round(array_sum(array_column($this->statements, 'ms')) / 1000, 3),
            'status' => $response->getStatusCode(),
            'seconds' => round($seconds, 3),
            'memory' => memory_get_peak_usage() - $baseline,
            'bytes' => strlen((string) $response->getContent()),
            'queries' => $this->queries,
        ];
    }

    /**
     * @param  array<string, array{slowest: list<array{ms: float, sql: string}>, db_seconds: float, status: int, seconds: float, memory: int, bytes: int, queries: int}>  $results
     */
    private function report(array $results): void
    {
        $lines = ["\nPERF-01 measurements (MySQL):"];
        $lines[] = sprintf('  %-34s %6s %8s %8s %10s %10s %8s', 'request', 'status', 'time s', 'db s', 'peak MB', 'body KB', 'queries');
        foreach ($results as $label => $r) {
            $lines[] = sprintf(
                '  %-34s %6d %8.3f %8.3f %10.1f %10.1f %8d',
                $label, $r['status'], $r['seconds'], $r['db_seconds'], $r['memory'] / 1048576, $r['bytes'] / 1024, $r['queries'],
            );
            if ($r['seconds'] > 0.5) {
                foreach ($r['slowest'] as $statement) {
                    $lines[] = sprintf('      %7.1f ms  %s', $statement['ms'], substr($statement['sql'], 0, 160));
                }
            }
        }
        fwrite(STDERR, implode("\n", $lines)."\n");
    }

    private function maxSeconds(): float
    {
        $value = getenv('PERF_REPORT_MAX_SECONDS');

        return is_string($value) && is_numeric($value) && (float) $value > 0 ? (float) $value : self::DEFAULT_MAX_SECONDS;
    }

    /**
     * EXPLAIN of the main report statements on the LARGE tables (invoices, payments): the
     * optimizer uses an index LED by the expected columns (any name, so an equivalent index of
     * an existing tenant counts). Small tables are not checked: on a few rows MySQL / MariaDB
     * may rightly prefer any index or a scan.
     */
    private function assertQueryPlansUseThePerfIndexes(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $month = CarbonImmutable::parse($this->today)->subDays(29)->toDateString();
            $end = CarbonImmutable::parse($this->today)->addDay()->toDateString();
            $customerId = $this->base + 7;

            // A branch's invoices of a period (summary, stores, P&L, dashboard).
            $this->assertPlanLedBy(
                'SELECT COUNT(*), SUM(net_total), SUM(total_cost) FROM invoices WHERE store_id = ? AND status = ? AND invoice_date >= ? AND invoice_date < ? AND deleted_at IS NULL',
                [$this->mainStore, 'confirmed', $month, $end],
                'invoices',
                [['store_id', 'status', 'invoice_date']],
            );

            // Every branch's invoices of a month (all-stores reports, dashboard trend).
            $this->assertPlanLedBy(
                'SELECT COUNT(*), SUM(net_total) FROM invoices WHERE status = ? AND invoice_date >= ? AND invoice_date < ? AND deleted_at IS NULL',
                ['confirmed', $month, $end],
                'invoices',
                [['status', 'invoice_date'], ['invoice_date']],
            );

            // A customer's invoices before a recent date (statement opening balance).
            $this->assertPlanLedBy(
                'SELECT SUM(net_total) FROM invoices WHERE customer_id = ? AND status = ? AND invoice_date < ? AND deleted_at IS NULL',
                [$customerId, 'confirmed', $month],
                'invoices',
                [['customer_id', 'status', 'invoice_date']],
            );

            // A customer's payments before a recent date (statement opening balance).
            $this->assertPlanLedBy(
                'SELECT SUM(amount) FROM payments WHERE customer_id = ? AND payment_date < ? AND deleted_at IS NULL',
                [$customerId, $month],
                'payments',
                [['customer_id', 'payment_date']],
            );

            // A branch's vouchers of a period (treasury, cash flow).
            $this->assertPlanLedBy(
                'SELECT SUM(amount) FROM payments WHERE store_id = ? AND payment_date >= ? AND payment_date < ? AND deleted_at IS NULL',
                [$this->mainStore, $month, $end],
                'payments',
                [['store_id', 'payment_date']],
            );
        });
    }

    /**
     * @param  list<mixed>  $bindings
     * @param  list<list<string>>  $leadingColumns  acceptable leading column lists
     */
    private function assertPlanLedBy(string $sql, array $bindings, string $table, array $leadingColumns): void
    {
        $plan = DB::select('EXPLAIN '.$sql, $bindings);
        $row = collect($plan)->first(fn (object $r): bool => ($r->table ?? null) === $table);
        $this->assertNotNull($row, "EXPLAIN has no row for {$table}: {$sql}");

        $key = (string) ($row->key ?? '');
        $columns = [];
        foreach (Schema::getIndexes($table) as $index) {
            if (strcasecmp((string) $index['name'], $key) === 0) {
                $columns = array_map('strtolower', $index['columns']);
            }
        }

        $led = false;
        foreach ($leadingColumns as $expected) {
            if (array_slice($columns, 0, count($expected)) === $expected) {
                $led = true;
            }
        }

        $this->assertTrue($led, "{$sql}\nkey={$key} (".implode(',', $columns).') possible_keys='.($row->possible_keys ?? ''));
    }
}
