<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\BuildsLargeReportDataset;
use Tests\TenantTestCase;

/**
 * PERF-02 acceptance on a real InnoDB server, over the PERF-01 report-sized tenant (5,000 items,
 * 50,000 invoices, ~130,000 lines, ~40,000 sale vouchers) plus an open shift in the main branch:
 * the dashboard (cold and cached), the items list (50 and 200 rows) and the treasury summary.
 *
 * MySQL only (run it on MariaDB too by hand: same phpunit.mysql.xml, another DB_PORT). The wall
 * time is an in-process proxy (no HTTP, no FPM, no concurrency), the faster of two runs, with
 * the cache emptied before each cold run; CI loosens it with PERF_REPORT_MAX_SECONDS. Query
 * count and memory are hard limits everywhere. Measured on an idle-ish dev box (MySQL 8.4 /
 * MariaDB 11.8.8): dashboard store cold ~0.12 s, cached ~0.03 s, items 50 rows ~0.08 s,
 * treasury summary ~0.08 s.
 */
#[Group('mysql')]
#[Group('mysql-only')]
final class DashboardItemsTreasuryLargeDatasetPerformanceTest extends TenantTestCase
{
    use BuildsLargeReportDataset;

    private const MAX_MEMORY_BYTES = 64 * 1024 * 1024;

    /** Default wall-time budget per request; CI overrides it with PERF_REPORT_MAX_SECONDS. */
    private const DEFAULT_MAX_SECONDS = 1.0;

    /**
     * Upper bound of SQL statements per request (auth, tenancy, permissions included), whatever
     * the data volume. Before PERF-02 the items page ran ~12 statements per row (618 for 50 rows).
     *
     * @var array<string, int>
     */
    private const QUERY_BUDGETS = [
        'dashboard store (cold)' => 60,
        'dashboard store (cached)' => 30,
        'dashboard all (cold)' => 45,
        'items per_page=50' => 25,
        'items per_page=200' => 25,
        'treasury summary' => 40,
    ];

    private Tenant $tenant;

    private int $mainStore;

    private int $queries = 0;

    /** @var list<array{ms: float, sql: string}> */
    private array $statements = [];

    public function test_dashboard_items_and_treasury_summary_stay_flat_on_a_large_tenant(): void
    {
        $this->seedLargeTenant();

        $main = $this->tenantHeaders($this->tenant, null, $this->mainStore);
        $all = array_merge($main, ['X-Store-Id' => 'all']);

        DB::listen(function (QueryExecuted $query): void {
            $this->queries++;
            $this->statements[] = ['ms' => (float) $query->time, 'sql' => $query->sql];
        });

        $results = [];
        foreach ([
            'dashboard store (cold)' => ['/api/v1/dashboard', $main, true],
            'dashboard store (cached)' => ['/api/v1/dashboard', $main, false],
            'dashboard all (cold)' => ['/api/v1/dashboard', $all, true],
            'items per_page=50' => ['/api/v1/items?per_page=50', $main, true],
            'items per_page=200' => ['/api/v1/items?per_page=200', $main, true],
            'treasury summary' => ['/api/v1/treasury/summary', $main, true],
        ] as $label => [$uri, $headers, $cold]) {
            if ($cold) {
                Cache::flush();
            }
            $first = $this->measure($uri, $headers);
            if ($cold) {
                Cache::flush();
            }
            $second = $this->measure($uri, $headers);
            $first['seconds'] = min($first['seconds'], $second['seconds']);
            $results[$label] = $first;
        }

        $this->report($results);

        foreach ($results as $label => $r) {
            $this->assertSame(200, $r['status'], $label);
            $this->assertLessThanOrEqual(self::MAX_MEMORY_BYTES, $r['memory'], "{$label}: peak memory");
            $this->assertLessThanOrEqual(self::QUERY_BUDGETS[$label], $r['queries'], "{$label}: query count");
            $this->assertLessThanOrEqual($this->maxSeconds(), $r['seconds'], "{$label}: time");
        }
    }

    private function maxSeconds(): float
    {
        $value = getenv('PERF_REPORT_MAX_SECONDS');

        return is_string($value) && is_numeric($value) && (float) $value > 0 ? (float) $value : self::DEFAULT_MAX_SECONDS;
    }

    private function seedLargeTenant(): void
    {
        $this->tenant = $this->createTenant();
        $this->mainStore = (int) $this->tenantStore($this->tenant)->id;

        $this->inTenant($this->tenant, function (): void {
            $today = app(TenantClock::class)->businessDate();
            $userId = (int) User::query()->orderBy('id')->value('id');
            $stats = $this->buildLargeReportDataset($this->mainStore, $userId, $today);

            // The open shift of the main branch: the dashboard computes its live drawer total.
            $opened = now()->subHours(6)->format('Y-m-d H:i:s');
            DB::table('cash_shifts')->insert([
                'user_id' => $userId, 'store_id' => $this->mainStore, 'shift_number' => 'PERF-SHIFT-1', 'status' => 'open',
                'opened_at' => $opened, 'opening_cash_balance' => '500.000', 'created_at' => $opened, 'updated_at' => $opened,
            ]);

            fwrite(STDERR, sprintf("\nPERF-02 dataset: %d items, %d invoices, %d lines, %d payments\n", $stats['items'], $stats['invoices'], $stats['lines'], $stats['payments']));
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
            'slowest' => array_slice($this->statements, 0, 4),
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
        $lines = ["\nPERF-02 measurements (".DB::connection()->getDriverName().' '.DB::selectOne('SELECT VERSION() as v')->v.'):'];
        $lines[] = sprintf('  %-28s %6s %8s %8s %10s %10s %8s', 'request', 'status', 'time s', 'db s', 'peak MB', 'body KB', 'queries');
        foreach ($results as $label => $r) {
            $lines[] = sprintf(
                '  %-28s %6d %8.3f %8.3f %10.1f %10.1f %8d',
                $label, $r['status'], $r['seconds'], $r['db_seconds'], $r['memory'] / 1048576, $r['bytes'] / 1024, $r['queries'],
            );
            foreach ($r['seconds'] > 0.1 ? $r['slowest'] : [] as $statement) {
                $lines[] = sprintf('      %7.1f ms  %s', $statement['ms'], substr($statement['sql'], 0, 200));
            }
        }
        fwrite(STDERR, implode("\n", $lines)."\n");
    }
}
