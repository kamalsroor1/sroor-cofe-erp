<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\ReportController;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-01: report period limit (366 days -> 422), paged item / inventory lists with
 * page-independent totals, capped lists in the comprehensive bundle, and a capped treasury
 * ledger whose running balances equal the full ledger's.
 */
#[Group('mysql')]
final class ReportsPeriodAndPagingApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeId;

    private int $customerId;

    private int $userId;

    private string $today;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);
        $this->inTenant($this->tenant, function (): void {
            $this->today = app(TenantClock::class)->businessDate();
            $this->userId = (int) User::query()->orderBy('id')->value('id');
            $this->customerId = (int) Customer::create(['name' => 'عميل الصفحات', 'phone' => '01000008401', 'price_tier' => 'retail', 'is_active' => true])->id;
        });
    }

    public function test_a_period_longer_than_366_days_is_a_translated_422(): void
    {
        $this->getJson('/api/v1/reports/summary?from=2025-01-01&to=2026-01-02', $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to'])
            ->assertJsonPath('errors.to.0', __('reports.period_too_long', ['days' => 366], 'ar'));
        $this->getJson('/api/v1/reports/comprehensive?from_date=2025-01-01&to_date=2026-01-02', $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['to_date']);

        // Without an end date the period runs to today.
        $longAgo = CarbonImmutable::parse($this->today)->subDays(366)->toDateString();
        $this->getJson("/api/v1/reports/stores?from={$longAgo}", $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['from']);

        // 366 days (a leap year) is allowed; so are the presets.
        $this->getJson('/api/v1/reports/summary?from=2024-01-01&to=2024-12-31', $this->headers)->assertOk();
        $this->getJson('/api/v1/reports/summary?period=this_year', $this->headers)->assertOk();

        $this->getJson('/api/v1/reports/items?per_page=500', $this->headers)->assertStatus(422)->assertJsonValidationErrors(['per_page']);
        $this->getJson('/api/v1/reports/items?page=0', $this->headers)->assertStatus(422)->assertJsonValidationErrors(['page']);
    }

    public function test_a_reversed_period_is_a_422_on_every_report_endpoint(): void
    {
        foreach (['summary', 'comprehensive', 'items', 'top-items', 'stores', 'customers', 'expenses', 'inventory', 'treasury'] as $report) {
            $this->getJson("/api/v1/reports/{$report}?from=2026-03-10&to=2026-03-01", $this->headers)
                ->assertStatus(422)->assertJsonValidationErrors(['to']);
            $this->getJson("/api/v1/reports/{$report}?from_date=2026-03-10&to_date=2026-03-09", $this->headers)
                ->assertStatus(422)->assertJsonValidationErrors(['to_date']);
        }

        // Without an end date the period ends today: a start in the future is reversed too.
        $tomorrow = CarbonImmutable::parse($this->today)->addDay()->toDateString();
        $this->getJson("/api/v1/reports/summary?from={$tomorrow}", $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['from']);

        // A one-day period (start = end) is valid.
        $this->getJson('/api/v1/reports/summary?from=2026-03-10&to=2026-03-10', $this->headers)->assertOk();
    }

    public function test_the_english_locale_gets_the_english_period_message(): void
    {
        $this->getJson('/api/v1/reports/summary?from=2025-01-01&to=2026-01-02', array_merge($this->headers, ['Accept-Language' => 'en']))
            ->assertStatus(422);
        $this->assertNotSame(__('reports.period_too_long', ['days' => 366], 'ar'), __('reports.period_too_long', ['days' => 366], 'en'));
    }

    public function test_item_profitability_is_paged_by_net_revenue_with_page_independent_totals(): void
    {
        $items = $this->makeItems(3);
        $this->sell([[$items[0], '1.000', '10.000'], [$items[1], '3.000', '10.000'], [$items[2], '0.250', '200.000']]);

        $page1 = $this->getJson('/api/v1/reports/items?period=today&per_page=2&page=1', $this->headers)->assertOk();
        $page2 = $this->getJson('/api/v1/reports/items?period=today&per_page=2&page=2', $this->headers)->assertOk();

        $this->assertSame([$items[2], $items[1]], array_column($page1->json('data'), 'item_id'));
        $this->assertSame([$items[0]], array_column($page2->json('data'), 'item_id'));
        $page1->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('totals.items_count', 3)
            ->assertJsonPath('totals.total_qty', '4.250')
            ->assertJsonPath('totals.total_revenue', '90.000')
            ->assertJsonPath('totals.total_cogs', '21.250')
            ->assertJsonPath('totals.profit', '68.750')
            ->assertJsonPath('totals.margin', '76.3');
        $this->assertSame($page1->json('totals'), $page2->json('totals'));

        $this->getJson('/api/v1/reports/top-items?period=today', $this->headers)->assertOk()
            ->assertJsonCount(3, 'top_items')->assertJsonPath('top_items.0.item_id', $items[2]);
    }

    public function test_inventory_valuation_is_paged_most_valuable_first_and_its_totals_cover_every_item(): void
    {
        $items = $this->makeItems(3, stock: ['5.000', '0.000', '12.500']);

        $page1 = $this->getJson('/api/v1/reports/inventory?per_page=2', $this->headers)->assertOk();
        $page2 = $this->getJson('/api/v1/reports/inventory?per_page=2&page=2', $this->headers)->assertOk();

        // cost 5 each: 12.5 x 5 = 62.5, 5 x 5 = 25, 0.
        $this->assertSame([$items[2], $items[0]], array_column($page1->json('data.items'), 'id'));
        $this->assertSame([$items[1]], array_column($page2->json('data.items'), 'id'));
        foreach ([$page1, $page2] as $page) {
            $page->assertJsonPath('data.stock_cost_valuation', '87.500')
                ->assertJsonPath('data.stock_selling_valuation', '175.000')
                ->assertJsonPath('data.expected_stock_profit', '87.500')
                ->assertJsonPath('data.meta.total', 3);
        }

        $this->getJson('/api/v1/reports/inventory?stock_filter=in_stock', $this->headers)->assertOk()
            ->assertJsonPath('data.meta.total', 2)->assertJsonPath('data.stock_cost_valuation', '87.500');
        $this->getJson('/api/v1/reports/inventory?stock_filter=zero_stock', $this->headers)->assertOk()
            ->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.items.0.id', $items[1]);
    }

    public function test_the_comprehensive_bundle_caps_its_lists_and_reports_the_full_counts(): void
    {
        $limit = ReportController::COMPREHENSIVE_LIST_LIMIT;
        $items = $this->makeItems($limit + 5);
        $this->sell(array_map(fn (int $id): array => [$id, '1.000', '10.000'], $items));

        $response = $this->getJson('/api/v1/reports/comprehensive?period=today', $this->headers)->assertOk();

        $this->assertCount($limit, $response->json('item_profits'));
        $this->assertCount($limit, $response->json('inventory_data.items'));
        $response->assertJsonPath('meta.list_limit', $limit)
            ->assertJsonPath('meta.item_profits.total', $limit + 5)
            ->assertJsonPath('meta.item_profits.shown', $limit)
            ->assertJsonPath('meta.inventory_items.total', $limit + 5)
            ->assertJsonPath('item_profits_totals.total_revenue', number_format(($limit + 5) * 10, 3, '.', ''))
            ->assertJsonPath('summary.total_sales', number_format(($limit + 5) * 10, 3, '.', ''));
        $this->assertLessThanOrEqual($limit, count($response->json('inventory_data.abc_data.items')));
    }

    public function test_the_treasury_ledger_is_paged_with_the_running_balances_of_the_full_ledger(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $base = CarbonImmutable::parse($this->today);
            foreach (['100.000', '-20.500', '45.250', '300.000', '12.125'] as $i => $amount) {
                $at = $base->setTime(9, 0)->addMinutes($i * 5)->format('Y-m-d H:i:s');
                DB::table('payments')->insert([
                    'payment_number' => 'TRS-PAY-'.$i, 'customer_id' => $this->customerId, 'user_id' => $this->userId,
                    'store_id' => $this->storeId, 'amount' => $amount, 'payment_date' => $this->today, 'payment_method' => 'cash',
                    'created_at' => $at, 'updated_at' => $at,
                ]);
            }
            DB::table('expenses')->insert([
                'expense_number' => 'TRS-EXP-1', 'category' => 'rent', 'title' => 'إيجار', 'amount' => '33.333',
                'expense_date' => $this->today, 'payment_method' => 'cash', 'user_id' => $this->userId, 'store_id' => $this->storeId,
                'cost_center' => 'general', 'created_at' => $base->setTime(9, 12)->format('Y-m-d H:i:s'), 'updated_at' => $base->format('Y-m-d H:i:s'),
            ]);
        });

        $full = $this->getJson('/api/v1/reports/treasury?period=today&per_page=200', $this->headers)->assertOk()->json('data');
        $this->assertSame(6, $full['ledger_total']);
        $this->assertCount(6, $full['ledger_entries']);

        // Every page is exactly the matching slice of the full ledger (running balances included).
        foreach ([1, 2, 3] as $page) {
            $paged = $this->getJson("/api/v1/reports/treasury?period=today&per_page=2&page={$page}", $this->headers)->assertOk()->json('data');
            $this->assertSame(array_slice($full['ledger_entries'], ($page - 1) * 2, 2), $paged['ledger_entries'], "page {$page}");
            $this->assertSame(['current_page' => $page, 'per_page' => 2, 'total' => 6, 'last_page' => 3], $paged['meta']);
            $this->assertSame($full['total_inflows'], $paged['total_inflows']);
            $this->assertSame($full['total_liquidity'], $paged['total_liquidity']);
        }
        $this->assertSame([], $this->getJson('/api/v1/reports/treasury?period=today&per_page=2&page=4', $this->headers)->assertOk()->json('data.ledger_entries'));

        // Newest first; the oldest entry's balance is the opening plus its own movement, the
        // newest one's is the closing balance of the period.
        $oldest = end($full['ledger_entries']);
        $this->assertSame(bcsub(bcadd($full['accounts']['cash']['opening_balance'], $oldest['debit'], 3), $oldest['credit'], 3), $oldest['running_balance']);
        $this->assertSame($full['accounts']['cash']['closing_balance'], $full['ledger_entries'][0]['running_balance']);
        $this->assertSame('436.875', $full['total_inflows']); // 100 - 20.5 + 45.25 + 300 + 12.125 (the refund voucher is negative)
        $this->assertSame(bcsub($full['total_liquidity'], $full['total_opening'], 3), $full['total_net_change']);
    }

    /**
     * @param  list<string>|null  $stock
     * @return list<int>
     */
    private function makeItems(int $count, ?array $stock = null): array
    {
        return $this->inTenant($this->tenant, function () use ($count, $stock): array {
            $ids = [];
            $now = now()->format('Y-m-d H:i:s');
            for ($k = 0; $k < $count; $k++) {
                $qty = $stock[$k] ?? '50.000';
                $id = (int) DB::table('items')->insertGetId([
                    'code' => sprintf('PG-%04d', $k), 'name' => 'صنف صفحات '.$k, 'category' => 'coffee_beans', 'unit' => 'كجم',
                    'current_stock' => $qty, 'cost_price' => '5.000', 'weighted_avg_cost' => '5.000', 'selling_price' => '10.000',
                    'min_stock_level' => '0.000', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('store_stocks')->insert(['store_id' => $this->storeId, 'item_id' => $id, 'quantity' => $qty, 'created_at' => $now, 'updated_at' => $now]);
                $ids[] = $id;
            }

            return $ids;
        });
    }

    /**
     * One confirmed invoice with the given lines [item, qty, unit price] at cost 5.
     *
     * @param  list<array{0: int, 1: string, 2: string}>  $lines
     */
    private function sell(array $lines): void
    {
        $this->inTenant($this->tenant, function () use ($lines): void {
            $now = now()->format('Y-m-d H:i:s');
            $net = '0.000';
            $cost = '0.000';
            foreach ($lines as [, $qty, $price]) {
                $net = bcadd($net, bcmul($qty, $price, 3), 3);
                $cost = bcadd($cost, bcmul($qty, '5.000', 3), 3);
            }
            $invoiceId = (int) DB::table('invoices')->insertGetId([
                'invoice_number' => 'PG-INV-'.uniqid(), 'customer_id' => $this->customerId, 'user_id' => $this->userId,
                'store_id' => $this->storeId, 'invoice_date' => $this->today, 'payment_type' => 'credit', 'status' => 'confirmed',
                'subtotal' => $net, 'net_total' => $net, 'paid_amount' => '0.000', 'remaining_amount' => $net, 'total_cost' => $cost,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (array_chunk($lines, 200) as $chunk) {
                DB::table('invoice_items')->insert(array_map(fn (array $l): array => [
                    'invoice_id' => $invoiceId, 'item_id' => $l[0], 'quantity' => $l[1], 'cost_price' => '5.000',
                    'unit_price' => $l[2], 'total_price' => bcmul($l[1], $l[2], 3), 'created_at' => $now, 'updated_at' => $now,
                ], $chunk));
            }
        });
    }
}
