<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-01 characterization net: every report / dashboard figure on a small, fixed dataset must
 * stay byte-identical when the invoice side moves from model loading to SQL aggregates.
 *
 * The expected values in tests/Fixtures/reports/perf01-report-baseline.json were captured from
 * the code BEFORE PERF-01 (model loading). Ids are replaced by '#' (the harness offsets them per
 * tenant). Only the keys of the baseline are compared, so PERF-01 may ADD keys (meta, totals).
 *
 * Dataset (business "today" = 2026-06-15): 2 stores, 4 items (one sold by weight, one never
 * sold), 2 customers, 10 invoices over 7 months (fractional quantities, line and invoice
 * discounts, cash / credit / partial, cash and InstaPay), one cancelled invoice, a linked
 * return today, a linked return in May in branch B, an invoice-less return, expenses in both
 * branches and an on-account customer receipt.
 */
#[Group('mysql')]
final class ReportsPerformanceRegressionTest extends TenantTestCase
{
    private const FIXTURE = __DIR__.'/../../Fixtures/reports/perf01-report-baseline.json';

    /**
     * The baseline was captured on sqlite, where two pre-existing BUG-01 return computations are
     * float / integer artefacts (unchanged by PERF-01). On MySQL - production - the exact DECIMAL
     * results below apply instead, and every figure derived from them:
     *  - the invoice-less return of 0.500 x 123.457 = 61.7285 rounds half-up to 61.729 (sqlite's
     *    float ROUND gives 61.728);
     *  - the May return of 2 of the line 5 x 260 - 13 = 1287 is valued 2 x 1287 / 5 = 514.800 on
     *    the line basis (sqlite divides the integers 2574 / 5 = 514).
     */
    private const MYSQL_EXACT_DECIMALS = [
        'all.month.summary.returns_cost' => '241.729',
        'all.month.summary.total_cogs' => '2819.999',
        'all.month.summary.gross_profit' => '884.101',
        'all.month.summary.net_profit' => '418.351',
        'all.month.items.2.total_cogs' => '180.000',
        'all.month.items.2.profit' => '116.600',
        'all.month.items.2.returned_cogs' => '61.729',
        'all.month.top_items.2.total_cogs' => '180.000',
        'all.month.top_items.2.profit' => '116.600',
        'all.month.top_items.2.returned_cogs' => '61.729',
        'all.ytd.summary.returns_cost' => '601.729',
        'all.ytd.summary.total_cogs' => '4248.642',
        'all.ytd.summary.gross_profit' => '1477.658',
        'all.ytd.summary.net_profit' => '1011.908',
        'all.ytd.items.1.total_revenue' => '2072.200',
        'all.ytd.items.1.profit' => '632.200',
        'all.ytd.items.1.returned_value' => '774.800',
        'all.ytd.items.2.total_cogs' => '488.642',
        'all.ytd.items.2.profit' => '307.958',
        'all.ytd.items.2.returned_cogs' => '61.729',
        'all.ytd.top_items.1.total_revenue' => '2072.200',
        'all.ytd.top_items.1.profit' => '632.200',
        'all.ytd.top_items.1.returned_value' => '774.800',
        'all.ytd.top_items.2.total_cogs' => '488.642',
        'all.ytd.top_items.2.profit' => '307.958',
        'all.ytd.top_items.2.returned_cogs' => '61.729',
        'all.wide.summary.returns_cost' => '601.729',
        'all.wide.summary.total_cogs' => '4428.642',
        'all.wide.summary.gross_profit' => '1557.658',
        'all.wide.summary.net_profit' => '1091.908',
        'all.wide.items.1.total_revenue' => '2332.200',
        'all.wide.items.1.profit' => '712.200',
        'all.wide.items.1.returned_value' => '774.800',
        'all.wide.items.2.total_cogs' => '488.642',
        'all.wide.items.2.profit' => '307.958',
        'all.wide.items.2.returned_cogs' => '61.729',
        'all.wide.top_items.1.total_revenue' => '2332.200',
        'all.wide.top_items.1.profit' => '712.200',
        'all.wide.top_items.1.returned_value' => '774.800',
        'all.wide.top_items.2.total_cogs' => '488.642',
        'all.wide.top_items.2.profit' => '307.958',
        'all.wide.top_items.2.returned_cogs' => '61.729',
        'all.ytd.stores.0.gross_profit' => '980.211',
        'all.month.stores.0.gross_profit' => '618.854',
        'all.comprehensive.summary.returns_cost' => '601.729',
        'all.comprehensive.summary.total_cogs' => '4248.642',
        'all.comprehensive.summary.gross_profit' => '1477.658',
        'all.comprehensive.summary.net_profit' => '1011.908',
        'all.comprehensive.item_profits.1.total_revenue' => '2072.200',
        'all.comprehensive.item_profits.1.profit' => '632.200',
        'all.comprehensive.item_profits.1.returned_value' => '774.800',
        'all.comprehensive.item_profits.2.total_cogs' => '488.642',
        'all.comprehensive.item_profits.2.profit' => '307.958',
        'all.comprehensive.item_profits.2.returned_cogs' => '61.729',
        'all.comprehensive.store_breakdown.0.gross_profit' => '980.211',
        'all.dashboard.metrics.monthly_gross_profit' => '884.101',
        'a.month.summary.returns_cost' => '241.729',
        'a.month.summary.total_cogs' => '2290.246',
        'a.month.summary.gross_profit' => '618.854',
        'a.month.summary.net_profit' => '198.354',
        'a.month.items.2.total_cogs' => '10.246',
        'a.month.items.2.profit' => '11.354',
        'a.month.items.2.returned_cogs' => '61.729',
        'a.month.top_items.2.total_cogs' => '10.246',
        'a.month.top_items.2.profit' => '11.354',
        'a.month.top_items.2.returned_cogs' => '61.729',
        'a.ytd.summary.returns_cost' => '241.729',
        'a.ytd.summary.total_cogs' => '3178.889',
        'a.ytd.summary.gross_profit' => '980.211',
        'a.ytd.summary.net_profit' => '559.711',
        'a.ytd.items.2.total_cogs' => '318.889',
        'a.ytd.items.2.profit' => '202.711',
        'a.ytd.items.2.returned_cogs' => '61.729',
        'a.ytd.top_items.2.total_cogs' => '318.889',
        'a.ytd.top_items.2.profit' => '202.711',
        'a.ytd.top_items.2.returned_cogs' => '61.729',
        'a.wide.summary.returns_cost' => '241.729',
        'a.wide.summary.total_cogs' => '3358.889',
        'a.wide.summary.gross_profit' => '1060.211',
        'a.wide.summary.net_profit' => '639.711',
        'a.wide.items.2.total_cogs' => '318.889',
        'a.wide.items.2.profit' => '202.711',
        'a.wide.items.2.returned_cogs' => '61.729',
        'a.wide.top_items.2.total_cogs' => '318.889',
        'a.wide.top_items.2.profit' => '202.711',
        'a.wide.top_items.2.returned_cogs' => '61.729',
        'a.ytd.stores.0.gross_profit' => '980.211',
        'a.month.stores.0.gross_profit' => '618.854',
        'a.comprehensive.summary.returns_cost' => '241.729',
        'a.comprehensive.summary.total_cogs' => '3178.889',
        'a.comprehensive.summary.gross_profit' => '980.211',
        'a.comprehensive.summary.net_profit' => '559.711',
        'a.comprehensive.item_profits.2.total_cogs' => '318.889',
        'a.comprehensive.item_profits.2.profit' => '202.711',
        'a.comprehensive.item_profits.2.returned_cogs' => '61.729',
        'a.comprehensive.store_breakdown.0.gross_profit' => '980.211',
        'a.dashboard.metrics.monthly_gross_profit' => '618.854',
        'b.ytd.items.0.total_revenue' => '1292.200',
        'b.ytd.items.0.profit' => '392.200',
        'b.ytd.items.0.returned_value' => '514.800',
        'b.ytd.top_items.0.total_revenue' => '1292.200',
        'b.ytd.top_items.0.profit' => '392.200',
        'b.ytd.top_items.0.returned_value' => '514.800',
        'b.wide.items.0.total_revenue' => '1292.200',
        'b.wide.items.0.profit' => '392.200',
        'b.wide.items.0.returned_value' => '514.800',
        'b.wide.top_items.0.total_revenue' => '1292.200',
        'b.wide.top_items.0.profit' => '392.200',
        'b.wide.top_items.0.returned_value' => '514.800',
        'b.ytd.stores.0.gross_profit' => '980.211',
        'b.month.stores.0.gross_profit' => '618.854',
        'b.comprehensive.item_profits.0.total_revenue' => '1292.200',
        'b.comprehensive.item_profits.0.profit' => '392.200',
        'b.comprehensive.item_profits.0.returned_value' => '514.800',
        'b.comprehensive.store_breakdown.0.gross_profit' => '980.211',
    ];

    /**
     * Deliberate PERF-01 differences from the captured baseline (path => new value). There is
     * exactly one cause: a stock line value (quantity x price) used to be TRUNCATED to 3 decimals
     * (bcmul(..., 3)); it is now rounded HALF-UP per line (Decimal::mul), the money rule. Only a
     * weighed item (PF-BEAN, fractional stock x 123.457) is affected, by 0.001 per branch. The
     * all-branches valuation (items.current_stock, a whole number here) is unchanged.
     * Second, display-only: the dashboard's payment_distribution amounts were Collection::sum()
     * floats cast to string ("830", "426.6"); they are now SQL sums at scale 3 ("830.000").
     * Third, format-only and sqlite-only: the ABC analysis passed the raw SQL SUM through
     * (sqlite "11" / "891.6", MySQL already "11.000"); it is now normalized to scale 3.
     * Fourth, same rule as the first: the ABC analysis' cogs and stock_value were truncated
     * (bcmul(..., 3)); they are now half-up (3.083 x 123.457 = 380.617931 -> 380.618).
     */
    private const DELIBERATE_CHANGES = [
        'a.inventory.abc_totals.total_profit' => '1160.982',
        'a.inventory.abc_totals.class_a.profit' => '1160.982',
        'a.inventory.abc_items.0.cogs' => '380.618',
        'a.inventory.abc_items.0.gross_profit' => '235.982',
        'all.inventory.abc_items.0.revenue' => '891.600',
        'all.inventory.abc_items.1.quantity_sold' => '11.000',
        'all.inventory.abc_items.1.revenue' => '2847.000',
        'all.inventory.abc_items.2.quantity_sold' => '4.000',
        'all.inventory.abc_items.2.revenue' => '2925.000',
        'a.inventory.abc_items.0.revenue' => '616.600',
        'a.inventory.abc_items.1.quantity_sold' => '4.000',
        'a.inventory.abc_items.1.revenue' => '1040.000',
        'a.inventory.abc_items.2.quantity_sold' => '4.000',
        'a.inventory.abc_items.2.revenue' => '2925.000',
        'b.inventory.abc_items.0.revenue' => '275.000',
        'b.inventory.abc_items.1.quantity_sold' => '7.000',
        'b.inventory.abc_items.1.revenue' => '1807.000',
        'all.dashboard.analytics.payment_distribution.0.amount' => '830.000',
        'all.dashboard.analytics.payment_distribution.1.amount' => '426.600',
        'a.dashboard.analytics.payment_distribution.0.amount' => '830.000',
        'a.dashboard.analytics.payment_distribution.1.amount' => '326.600',
        'b.dashboard.analytics.payment_distribution.1.amount' => '100.000',
        'a.month.summary.stock_cost_valuation' => '78892.711',
        'a.month.summary.expected_stock_profit' => '27665.064',
        'a.ytd.summary.stock_cost_valuation' => '78892.711',
        'a.ytd.summary.expected_stock_profit' => '27665.064',
        'a.wide.summary.stock_cost_valuation' => '78892.711',
        'a.wide.summary.expected_stock_profit' => '27665.064',
        'a.today.summary.stock_cost_valuation' => '78892.711',
        'a.today.summary.expected_stock_profit' => '27665.064',
        'a.inventory.totals.stock_cost_valuation' => '78892.711',
        'a.inventory.totals.expected_stock_profit' => '27665.064',
        'a.inventory.items.0.cost_val' => '5853.961',
        'a.inventory.items.0.profit' => '3629.439',
        'a.comprehensive.summary.stock_cost_valuation' => '78892.711',
        'a.comprehensive.summary.expected_stock_profit' => '27665.064',
        'b.month.summary.stock_cost_valuation' => '81181.847',
        'b.month.summary.expected_stock_profit' => '28357.528',
        'b.ytd.summary.stock_cost_valuation' => '81181.847',
        'b.ytd.summary.expected_stock_profit' => '28357.528',
        'b.wide.summary.stock_cost_valuation' => '81181.847',
        'b.wide.summary.expected_stock_profit' => '28357.528',
        'b.today.summary.stock_cost_valuation' => '81181.847',
        'b.today.summary.expected_stock_profit' => '28357.528',
        'b.inventory.totals.stock_cost_valuation' => '81181.847',
        'b.inventory.totals.expected_stock_profit' => '28357.528',
        'b.inventory.items.0.cost_val' => '6003.097',
        'b.inventory.items.0.profit' => '3721.903',
        'b.comprehensive.summary.stock_cost_valuation' => '81181.847',
        'b.comprehensive.summary.expected_stock_profit' => '28357.528',
    ];

    private Tenant $tenant;

    private int $storeA;

    private int $storeB;

    /** @var array<string, int> */
    private array $customers = [];

    /** @var array<string, int> */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->at('2026-06-15 09:00:00');
        $this->tenant = $this->createTenant();
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;

        $this->inTenant($this->tenant, function (): void {
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'PERF-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $this->customers['c1'] = (int) Customer::create(['name' => 'عميل أول', 'phone' => '01000009001', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->customers['c2'] = (int) Customer::create(['name' => 'عميل ثان', 'phone' => '01000009002', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->items['cup'] = $this->makeItem('PF-CUP', '180.000', '260.000', '100.000');
            $this->items['grd'] = $this->makeItem('PF-GRD', '580.000', '750.000', '100.000');
            $this->items['bean'] = $this->makeItem('PF-BEAN', '123.457', '200.000', '50.000');
            $this->items['idle'] = $this->makeItem('PF-IDLE', '10.500', '15.250', '7.500');
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_reports_and_dashboard_figures_match_the_pre_perf01_baseline(): void
    {
        $this->seedDataset();
        $this->at('2026-06-15 12:00:00');

        $actual = $this->snapshot();

        $this->assertFileExists(self::FIXTURE);
        /** @var array<string, mixed> $expected */
        $expected = json_decode((string) file_get_contents(self::FIXTURE), true, 512, JSON_THROW_ON_ERROR);
        foreach (self::DELIBERATE_CHANGES as $path => $value) {
            $this->setPath($expected, $path, $value);
        }
        if (DB::connection()->getDriverName() === 'mysql') {
            foreach (self::MYSQL_EXACT_DECIMALS as $path => $value) {
                $this->setPath($expected, $path, $value);
            }
        }

        $mismatches = [];
        foreach ($expected as $key => $value) {
            if (! array_key_exists($key, $actual)) {
                $mismatches[] = "{$key}: missing";

                continue;
            }
            $this->collectMismatches($key, $value, $this->onlyBaselineKeys($value, $actual[$key]), $mismatches);
        }

        $this->assertSame([], $mismatches, implode("\n", $mismatches));
    }

    /**
     * @param  list<string>  $out
     */
    private function collectMismatches(string $path, mixed $expected, mixed $actual, array &$out): void
    {
        if (is_array($expected) && is_array($actual) && array_keys($expected) === array_keys($actual)) {
            foreach ($expected as $k => $v) {
                $this->collectMismatches($path.'.'.$k, $v, $actual[$k], $out);
            }

            return;
        }

        if ($expected !== $actual) {
            $out[] = $path.': expected '.json_encode($expected, JSON_UNESCAPED_UNICODE).' got '.json_encode($actual, JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function setPath(array &$data, string $path, mixed $value): void
    {
        // Top-level keys contain dots themselves ("a.month.summary"): match the longest one.
        foreach (array_keys($data) as $key) {
            if (str_starts_with($path, $key.'.')) {
                $rest = substr($path, strlen($key) + 1);
                $inner = $data[$key];
                $this->assertNotNull(data_get($inner, $rest), "Unknown baseline path {$path}");
                data_set($inner, $rest, $value);
                $data[$key] = $inner;

                return;
            }
        }
        $this->fail("No baseline key for {$path}");
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $a = $this->tenantHeaders($this->tenant, null, $this->storeA);
        $b = $this->tenantHeaders($this->tenant, null, $this->storeB);
        $all = array_merge($a, ['X-Store-Id' => 'all']);

        $ranges = [
            'month' => 'period=this_month',
            'ytd' => 'from=2026-01-01&to=2026-06-15',
            'wide' => 'from=2025-12-01&to=2026-06-15',
            'today' => 'period=today',
        ];

        $out = [];
        foreach (['all' => $all, 'a' => $a, 'b' => $b] as $scope => $headers) {
            foreach ($ranges as $rangeKey => $query) {
                $prefix = "{$scope}.{$rangeKey}";
                $out["{$prefix}.summary"] = $this->fetch("/api/v1/reports/summary?{$query}", $headers)['summary'];
                $out["{$prefix}.items"] = $this->fetch("/api/v1/reports/items?{$query}", $headers)['data'];
                $out["{$prefix}.top_items"] = $this->fetch("/api/v1/reports/top-items?{$query}", $headers)['top_items'];
                $out["{$prefix}.customers"] = $this->fetch("/api/v1/reports/customers?{$query}", $headers)['data'];
                $out["{$prefix}.expenses"] = $this->fetch("/api/v1/reports/expenses?{$query}", $headers)['data'];
            }
            $out["{$scope}.ytd.stores"] = $this->fetch('/api/v1/reports/stores?'.$ranges['ytd'], $headers)['data'];
            $out["{$scope}.month.stores"] = $this->fetch('/api/v1/reports/stores?'.$ranges['month'], $headers)['data'];

            $inventory = $this->fetch('/api/v1/reports/inventory?'.$ranges['ytd'], $headers)['data'];
            $out["{$scope}.inventory.totals"] = array_intersect_key($inventory, array_flip(['stock_cost_valuation', 'stock_selling_valuation', 'expected_stock_profit']));
            $out["{$scope}.inventory.items"] = $this->sortedBy($inventory['items'], 'code');
            $out["{$scope}.inventory.abc_totals"] = array_diff_key($inventory['abc_data'], array_flip(['items', 'dead_stock']));
            $out["{$scope}.inventory.abc_items"] = $this->sortedBy($inventory['abc_data']['items'], 'code');
            $out["{$scope}.inventory.in_stock_count"] = count($this->fetch('/api/v1/reports/inventory?stock_filter=in_stock&'.$ranges['ytd'], $headers)['data']['items']);

            $treasury = $this->fetch('/api/v1/reports/treasury?'.$ranges['month'], $headers)['data'];
            $out["{$scope}.treasury.totals"] = array_diff_key($treasury, array_flip(['accounts', 'transfers', 'ledger_entries']));
            $out["{$scope}.treasury.accounts"] = $treasury['accounts'];
            $out["{$scope}.treasury.ledger"] = $treasury['ledger_entries'];

            $comprehensive = $this->fetch('/api/v1/reports/comprehensive?'.$ranges['ytd'], $headers);
            $out["{$scope}.comprehensive.summary"] = $comprehensive['summary'];
            $out["{$scope}.comprehensive.item_profits"] = $comprehensive['item_profits'];
            $out["{$scope}.comprehensive.store_breakdown"] = $comprehensive['store_breakdown'];
            $out["{$scope}.comprehensive.customer_sales"] = $comprehensive['customer_sales'];
            $out["{$scope}.comprehensive.expenses_breakdown"] = $comprehensive['expenses_breakdown'];

            $dashboard = $this->fetch('/api/v1/dashboard', $headers)['data'];
            $out["{$scope}.dashboard.metrics"] = $dashboard['metrics'];
            $out["{$scope}.dashboard.analytics"] = $dashboard['analytics'];
            $out["{$scope}.dashboard.top_selling_items"] = $dashboard['top_selling_items'];
            $out["{$scope}.dashboard.recent_invoices"] = $dashboard['recent_invoices'];
            $out["{$scope}.dashboard.low_stock_items"] = $dashboard['low_stock_items'];
        }

        $statement = $this->fetch("/api/v1/customers/{$this->customers['c1']}/statement?from_date=2025-01-01&to_date=2026-06-15", $a)['data'];
        $out['statement.c1.summary'] = $statement['summary'];
        $out['statement.c1.ledger'] = $statement['ledger'];
        $statement = $this->fetch("/api/v1/customers/{$this->customers['c2']}/statement?from_date=2025-01-01&to_date=2026-06-15", $a)['data'];
        $out['statement.c2.summary'] = $statement['summary'];
        $out['statement.c2.ledger'] = $statement['ledger'];

        return $this->withoutIds($out);
    }

    private function seedDataset(): void
    {
        $c1 = $this->customers['c1'];
        $c2 = $this->customers['c2'];
        $cup = $this->items['cup'];
        $grd = $this->items['grd'];
        $bean = $this->items['bean'];

        // Outside the year, then early in the year.
        $this->invoice('2025-12-31 08:10:00', $this->storeA, $c1, 'cash', 'cash', '260.000', [[$cup, '1.000', '260.000']]);
        $this->invoice('2026-01-05 10:00:00', $this->storeA, $c2, 'cash', 'cash', '750.000', [[$grd, '1.000', '750.000']]);
        $this->invoice('2026-04-10 11:30:00', $this->storeA, $c1, 'credit', 'cash', '0.000', [[$bean, '2.500', '200.000']]);
        $inv7 = $this->invoice('2026-05-20 14:05:00', $this->storeB, $c2, 'cash', 'cash', '1287.000', [[$cup, '5.000', '260.000', '13.000']]);
        $this->invoice('2026-06-01 07:45:00', $this->storeA, $c1, 'credit', 'cash', '0.000', [[$grd, '2.000', '750.000']]);
        $inv4 = $this->invoice('2026-06-12 09:20:00', $this->storeA, $c2, 'credit', 'cash', '0.000', [[$grd, '1.000', '750.000']]);
        $this->invoice('2026-06-12 16:50:00', $this->storeA, $c1, 'cash', 'instapay', '326.600', [[$bean, '0.333', '200.000'], [$cup, '1.000', '260.000']]);
        // Today: a cash sale with a weighed line, a credit sale with line + invoice discount,
        // a partial InstaPay sale in branch B.
        $inv1 = $this->invoice('2026-06-15 06:15:00', $this->storeA, $c1, 'cash', 'cash', '830.000', [[$cup, '3.000', '260.000'], [$bean, '0.250', '200.000']]);
        $this->invoice('2026-06-15 10:40:00', $this->storeA, $c2, 'credit', 'cash', '0.000', [[$grd, '1.000', '750.000', '75.000']], ['discount_type' => 'percentage', 'discount_value' => '10']);
        $this->invoice('2026-06-15 11:05:00', $this->storeB, $c1, 'partial', 'instapay', '100.000', [[$cup, '2.000', '260.000'], [$bean, '1.375', '200.000']]);

        $admin = $this->tenantHeaders($this->tenant, null, $this->storeA);
        $adminB = $this->tenantHeaders($this->tenant, null, $this->storeB);

        // The 06-12 grinder sale is cancelled (excluded everywhere).
        $this->at('2026-06-13 08:00:00');
        $this->postJson("/api/v1/invoices/{$inv4['invoice_id']}/cancel", ['reason' => 'خطأ'], $admin)->assertOk();

        // Returns: linked in branch B in May, invoice-less on 06-12, linked today.
        $this->at('2026-05-21 09:00:00');
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $c2, 'invoice_id' => $inv7['invoice_id'],
            'return_date' => '2026-05-21', 'items' => [['invoice_item_id' => $inv7['line_ids'][0], 'quantity' => '2.000']],
        ], $adminB)->assertCreated();
        $this->at('2026-06-12 18:00:00');
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $c1, 'return_date' => '2026-06-12',
            'items' => [['item_id' => $bean, 'quantity' => '0.500', 'unit_price' => '190.000']],
        ], $admin)->assertCreated();
        $this->at('2026-06-15 11:30:00');
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $c1, 'invoice_id' => $inv1['invoice_id'],
            'return_date' => '2026-06-15', 'items' => [['invoice_item_id' => $inv1['line_ids'][0], 'quantity' => '1.000']],
        ], $admin)->assertCreated();

        // Expenses and an on-account receipt.
        $this->at('2026-06-01 12:00:00');
        $this->postJson('/api/v1/expenses', ['title' => 'كهرباء', 'category' => 'utilities', 'cost_center' => 'general', 'amount' => '300.000', 'expense_date' => '2026-06-01', 'payment_method' => 'instapay'], $admin)->assertCreated();
        $this->at('2026-06-15 11:45:00');
        $this->postJson('/api/v1/expenses', ['title' => 'إيجار', 'category' => 'rent', 'cost_center' => 'general', 'amount' => '120.500', 'expense_date' => '2026-06-15', 'payment_method' => 'cash'], $admin)->assertCreated();
        $this->postJson('/api/v1/expenses', ['title' => 'أدوات', 'category' => 'supplies', 'cost_center' => 'general', 'amount' => '45.250', 'expense_date' => '2026-06-15', 'payment_method' => 'cash'], $adminB)->assertCreated();
        $this->postJson("/api/v1/customers/{$c1}/collect-payment", ['amount' => '200.000', 'payment_method' => 'cash', 'payment_date' => '2026-06-15'], $admin)->assertSuccessful();
    }

    /**
     * @param  list<array{0: int, 1: string, 2: string, 3?: string}>  $lines  [item, qty, unit price, line discount]
     * @param  array<string, string>  $extra
     * @return array{invoice_id: int, line_ids: list<int>}
     */
    private function invoice(string $createdAtUtc, int $storeId, int $customerId, string $paymentType, string $method, string $paid, array $lines, array $extra = []): array
    {
        $this->at($createdAtUtc);

        return $this->inTenant($this->tenant, function () use ($createdAtUtc, $storeId, $customerId, $paymentType, $method, $paid, $lines, $extra): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $invoice = app(InvoiceService::class)->confirmInvoice(array_merge([
                'customer_id' => $customerId,
                'store_id' => $storeId,
                'invoice_date' => substr($createdAtUtc, 0, 10),
                'payment_type' => $paymentType,
                'payment_method' => $method,
                'paid_amount' => $paid,
                'discount_type' => 'fixed',
                'discount_value' => '0.000',
                'items' => array_map(fn (array $l): array => [
                    'item_id' => $l[0], 'quantity' => $l[1], 'unit_price' => $l[2], 'discount_amount' => $l[3] ?? '0.000',
                ], $lines),
            ], $extra));

            /** @var list<int> $lineIds */
            $lineIds = InvoiceItem::query()->where('invoice_id', $invoice->id)->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

            return ['invoice_id' => (int) $invoice->id, 'line_ids' => $lineIds];
        });
    }

    private function makeItem(string $code, string $cost, string $price, string $stock): int
    {
        $item = Item::create([
            'name' => 'صنف '.$code, 'code' => $code, 'category' => 'coffee_beans', 'unit' => 'كجم',
            'cost_price' => $cost, 'weighted_avg_cost' => $cost, 'selling_price' => $price,
            'price_retail' => $price, 'price_wholesale' => $price,
            'current_stock' => bcmul($stock, '2', 3), 'min_stock_level' => '1.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $this->storeA, 'item_id' => $item->id, 'quantity' => $stock]);
        StoreStock::create(['store_id' => $this->storeB, 'item_id' => $item->id, 'quantity' => $stock]);

        return (int) $item->id;
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow(Carbon::parse($utc, 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($utc, 'UTC'));
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    private function fetch(string $uri, array $headers): array
    {
        /** @var array<string, mixed> $json */
        $json = $this->getJson($uri, $headers)->assertOk()->json();

        return $json;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function sortedBy(array $rows, string $key): array
    {
        usort($rows, fn (array $x, array $y): int => strcmp((string) $x[$key], (string) $y[$key]));

        return $rows;
    }

    private function withoutIds(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $inner) {
            $value[$key] = in_array($key, ['id', 'item_id', 'customer_id', 'store_id', 'invoice_id'], true) && $inner !== null
                ? '#'
                : $this->withoutIds($inner);
        }

        return $value;
    }

    /**
     * $actual reduced to the keys present in $expected (recursively for string-keyed arrays),
     * so keys added by PERF-01 (meta, totals) do not break the comparison.
     */
    private function onlyBaselineKeys(mixed $expected, mixed $actual): mixed
    {
        if (! is_array($expected) || ! is_array($actual)) {
            return $actual;
        }

        if (array_is_list($expected) && array_is_list($actual)) {
            $out = [];
            foreach ($actual as $i => $row) {
                $out[$i] = array_key_exists($i, $expected) ? $this->onlyBaselineKeys($expected[$i], $row) : $row;
            }

            return $out;
        }

        $out = [];
        foreach ($expected as $key => $inner) {
            if (array_key_exists($key, $actual)) {
                $out[$key] = $this->onlyBaselineKeys($inner, $actual[$key]);
            }
        }

        return $out;
    }
}
