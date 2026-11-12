<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ReturnItem;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Support\TenantClock;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-01 follow-up of BUG-01: the per-store and comprehensive reports with all three kinds of
 * sales return in the period (linked line, legacy return without a line link, invoice-less).
 *
 * The cost of a legacy / invoice-less return line comes from correlated subqueries. When they
 * sat inside SUM() of a statement grouped by store, MariaDB 11.8.8 (the developer's server)
 * rejected /reports/stores and /reports/comprehensive with 1055 "'returns.invoice_id' isn't in
 * GROUP BY" under ONLY_FULL_GROUP_BY. MySQL 8.4 (CI) accepts that shape, so this class also
 * runs the reports through every branch of the cost fallback and pins the session sql_mode.
 *
 * Scenario: branch A sells 3 x 260 (cost 180) and returns 1 on the line (260 / 180), sells a
 * "legacy" item 1 x 260 at a weighted cost of 180 (item cost_price 200) and returns it without
 * a line link (260 / 180); branch B sells 2 x 200 (cost 120) and takes back 1 without an invoice
 * at 190 (cost 120 from the stock movement).
 */
#[Group('mysql')]
#[Group('mariadb-reports')]
final class ReportsSalesReturnsPortabilityApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeA;

    private int $storeB;

    private int $customerId;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;

        $this->inTenant($this->tenant, function (): void {
            $this->today = app(TenantClock::class)->businessDate();
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'PRT-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $this->customerId = (int) Customer::create(['name' => 'عميل المرتجعات', 'phone' => '01000008101', 'price_tier' => 'retail', 'is_active' => true])->id;
        });
    }

    public function test_stores_and_comprehensive_reports_net_linked_legacy_and_invoice_less_returns(): void
    {
        $this->seedScenario();

        $all = array_merge($this->tenantHeaders($this->tenant), ['X-Store-Id' => 'all']);

        $stores = $this->getJson('/api/v1/reports/stores?period=today', $all)->assertOk()->json('data');
        $a = $this->row($stores, $this->storeA);
        $this->assertSame('1040.000', $a['gross_sales']);
        $this->assertSame('520.000', $a['total_returns']);
        $this->assertSame(2, $a['returns_count']);
        $this->assertSame('520.000', $a['total_sales']);
        $this->assertSame('160.000', $a['gross_profit']);
        $b = $this->row($stores, $this->storeB);
        $this->assertSame('400.000', $b['gross_sales']);
        $this->assertSame('190.000', $b['total_returns']);
        $this->assertSame(1, $b['returns_count']);
        $this->assertSame('210.000', $b['total_sales']);
        $this->assertSame('90.000', $b['gross_profit']);

        $comprehensive = $this->getJson('/api/v1/reports/comprehensive?period=today', $all)->assertOk()
            ->assertJsonPath('summary.gross_sales', '1440.000')
            ->assertJsonPath('summary.total_returns', '710.000')
            ->assertJsonPath('summary.returns_count', 3)
            ->assertJsonPath('summary.returns_cost', '480.000')
            ->assertJsonPath('summary.total_sales', '730.000')
            ->assertJsonPath('summary.total_cogs', '480.000')
            ->assertJsonPath('summary.gross_profit', '250.000');
        $this->assertSame('520.000', $this->row($comprehensive->json('store_breakdown'), $this->storeA)['total_sales']);
        $this->assertSame('210.000', $this->row($comprehensive->json('store_breakdown'), $this->storeB)['total_sales']);

        // The other return aggregates (per item, per customer, per day) on the same data.
        $this->getJson('/api/v1/reports/items?period=today', $all)->assertOk();
        $this->getJson('/api/v1/reports/customers?period=today', $all)->assertOk();
        $this->getJson('/api/v1/dashboard', $all)->assertOk()->assertJsonPath('data.metrics.today_returns', '710.000');
    }

    /**
     * The reports run under the same sql_mode as production (config/database.php `strict`), so
     * a statement that ONLY_FULL_GROUP_BY rejects fails here too.
     */
    #[Group('mysql-only')]
    public function test_the_tenant_connection_runs_with_only_full_group_by(): void
    {
        $mode = (string) $this->inTenant($this->tenant, fn (): mixed => DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode);

        $this->assertStringContainsString('ONLY_FULL_GROUP_BY', $mode);
    }

    private function seedScenario(): void
    {
        [$cup, $legacy, $bean] = $this->inTenant($this->tenant, fn (): array => [
            $this->makeItem('PRT-CUP', '180.000', '180.000', '260.000'),
            $this->makeItem('PRT-LEG', '200.000', '180.000', '260.000'),
            $this->makeItem('PRT-BEAN', '120.000', '120.000', '200.000'),
        ]);

        $headersA = $this->tenantHeaders($this->tenant, null, $this->storeA);
        $headersB = $this->tenantHeaders($this->tenant, null, $this->storeB);

        // Linked return.
        $cupSale = $this->sell($this->storeA, $cup, '3.000', '260.000');
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $cupSale['invoice_id'],
            'return_date' => $this->today, 'items' => [['invoice_item_id' => $cupSale['line_id'], 'quantity' => '1.000']],
        ], $headersA)->assertCreated();

        // Legacy return: invoice on the document, no link on the line (booked before BE-01).
        $legacySale = $this->sell($this->storeA, $legacy, '1.000', '260.000');
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $legacySale['invoice_id'],
            'return_date' => $this->today, 'items' => [['invoice_item_id' => $legacySale['line_id'], 'quantity' => '1.000']],
        ], $headersA)->assertCreated();
        $this->inTenant($this->tenant, fn () => ReturnItem::query()->where('item_id', $legacy)->update(['invoice_item_id' => null]));

        // Invoice-less return in branch B.
        $this->sell($this->storeB, $bean, '2.000', '200.000');
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => $this->today,
            'items' => [['item_id' => $bean, 'quantity' => '1.000', 'unit_price' => '190.000']],
        ], $headersB)->assertCreated();
    }

    /**
     * @return array{invoice_id: int, line_id: int}
     */
    private function sell(int $storeId, int $itemId, string $qty, string $price): array
    {
        return $this->inTenant($this->tenant, function () use ($storeId, $itemId, $qty, $price): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId, 'store_id' => $storeId,
                'payment_type' => 'credit', 'payment_method' => 'cash', 'paid_amount' => '0.000',
                'discount_type' => 'fixed', 'discount_value' => '0.000',
                'items' => [['item_id' => $itemId, 'quantity' => $qty, 'unit_price' => $price]],
            ]);

            return [
                'invoice_id' => (int) $invoice->id,
                'line_id' => (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->value('id'),
            ];
        });
    }

    private function makeItem(string $code, string $cost, string $wac, string $price): int
    {
        $item = Item::create([
            'name' => 'صنف '.$code, 'code' => $code, 'category' => 'coffee_beans', 'unit' => 'كجم',
            'cost_price' => $cost, 'weighted_avg_cost' => $wac, 'selling_price' => $price,
            'price_retail' => $price, 'price_wholesale' => $price,
            'current_stock' => '200.000', 'min_stock_level' => '1.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $this->storeA, 'item_id' => $item->id, 'quantity' => '100.000']);
        StoreStock::create(['store_id' => $this->storeB, 'item_id' => $item->id, 'quantity' => '100.000']);

        return (int) $item->id;
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<string, mixed>
     */
    private function row(?array $rows, int $storeId): array
    {
        foreach ($rows ?? [] as $row) {
            if ((int) $row['id'] === $storeId) {
                return $row;
            }
        }

        $this->fail("No row for store {$storeId}.");
    }
}
