<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Actions\Returns\ReverseDeletedReturnAction;
use App\Models\Customer;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\PurchaseItem;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * BUG-01: sales returns reduce the sales, cost and profit of every sales / profit report in the
 * period of their RETURN date, at the refunded value and the ORIGINAL invoice line's cost.
 *
 * Scenario (the "full working day" run): 3 x 260 (cost 180) + 750 with a 10% line discount +
 * 750 (cost 580 each) = 2205 sales / 1700 cost; returning 1 x 260 gives net sales 1945,
 * cost 1520, gross profit 425.
 *
 * In the `mysql` group: the returns side is raw grouped SQL (correlated subquery, ROUND,
 * leftJoinSub), which sqlite can accept while MySQL rejects it.
 */
#[Group('mysql')]
final class ReportsSalesReturnsApiTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private int $storeId;

    private int $customerId;

    private int $cupId;

    private int $grinderId;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);

        $this->inTenant($this->tenant, function (): void {
            $this->today = app(TenantClock::class)->businessDate();
            $this->customerId = (int) Customer::create(['name' => 'عميل التقارير', 'phone' => '01000008001', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->cupId = $this->makeItem('RPT-CUP', '180.000', '260.000', $this->storeId);
            $this->grinderId = $this->makeItem('RPT-GRD', '580.000', '750.000', $this->storeId);
        });
    }

    public function test_full_day_scenario_reports_net_sales_cost_and_profit_after_a_sales_return(): void
    {
        $cupLine = $this->sellScenario();

        $this->summary()->assertOk()
            ->assertJsonPath('summary.total_sales', '2205.000')
            ->assertJsonPath('summary.total_returns', '0.000')
            ->assertJsonPath('summary.returns_count', 0)
            ->assertJsonPath('summary.total_cogs', '1700.000')
            ->assertJsonPath('summary.gross_profit', '505.000');

        $this->salesReturn($cupLine['invoice_id'], $cupLine['line_id'], '1.000')->assertCreated();

        $this->summary()->assertOk()
            ->assertJsonPath('summary.gross_sales', '2205.000')
            ->assertJsonPath('summary.total_returns', '260.000')
            ->assertJsonPath('summary.returns_count', 1)
            ->assertJsonPath('summary.returns_cost', '180.000')
            ->assertJsonPath('summary.net_sales', '1945.000')
            ->assertJsonPath('summary.total_sales', '1945.000')
            ->assertJsonPath('summary.gross_cogs', '1700.000')
            ->assertJsonPath('summary.total_cogs', '1520.000')
            ->assertJsonPath('summary.gross_profit', '425.000')
            ->assertJsonPath('summary.net_profit', '425.000')
            ->assertJsonPath('summary.invoices_count', 3);

        $comprehensive = $this->getJson('/api/v1/reports/comprehensive?period=today', $this->headers)->assertOk()
            ->assertJsonPath('summary.total_sales', '1945.000')
            ->assertJsonPath('summary.total_cogs', '1520.000')
            ->assertJsonPath('summary.gross_profit', '425.000');
        $cup = $this->rowFor($comprehensive->json('item_profits'), 'item_id', $this->cupId);
        $this->assertSame('2.000', $cup['total_qty']);
        $this->assertSame('520.000', $cup['total_revenue']);
    }

    public function test_item_profitability_and_top_items_are_net_of_the_return(): void
    {
        $cupLine = $this->sellScenario();
        $this->salesReturn($cupLine['invoice_id'], $cupLine['line_id'], '1.000')->assertCreated();

        $cup = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->assertOk()->json('data'), 'item_id', $this->cupId);
        $this->assertSame('2.000', $cup['total_qty']);
        $this->assertSame('520.000', $cup['total_revenue']);
        $this->assertSame('360.000', $cup['total_cogs']);
        $this->assertSame('160.000', $cup['profit']);
        $this->assertSame('3.000', $cup['gross_qty']);
        $this->assertSame('780.000', $cup['gross_revenue']);
        $this->assertSame('1.000', $cup['returned_qty']);
        $this->assertSame('260.000', $cup['returned_value']);
        $this->assertSame('180.000', $cup['returned_cogs']);

        $grinder = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->grinderId);
        $this->assertSame('2.000', $grinder['total_qty']);
        $this->assertSame('1425.000', $grinder['total_revenue']);
        $this->assertSame('1160.000', $grinder['total_cogs']);

        $top = $this->getJson('/api/v1/reports/top-items?period=today', $this->headers)->assertOk()->json('top_items');
        $this->assertSame($this->grinderId, $top[0]['item_id']);
        $topCup = $this->rowFor($top, 'item_id', $this->cupId);
        $this->assertSame('2.000', $topCup['total_qty']);
        $this->assertSame('520.000', $topCup['total_revenue']);
    }

    public function test_dashboard_and_stores_report_show_the_same_net_figures(): void
    {
        $cupLine = $this->sellScenario();
        $this->salesReturn($cupLine['invoice_id'], $cupLine['line_id'], '1.000')->assertCreated();

        $dashboard = $this->getJson('/api/v1/dashboard', $this->headers)->assertOk()
            ->assertJsonPath('data.metrics.today_sales', '1945.000')
            ->assertJsonPath('data.metrics.today_gross_sales', '2205.000')
            ->assertJsonPath('data.metrics.today_returns', '260.000')
            ->assertJsonPath('data.metrics.monthly_sales', '1945.000')
            ->assertJsonPath('data.metrics.monthly_gross_sales', '2205.000')
            ->assertJsonPath('data.metrics.monthly_returns', '260.000')
            ->assertJsonPath('data.metrics.monthly_gross_profit', '425.000')
            ->assertJsonPath('data.analytics.today.sales', '1945.000')
            ->assertJsonPath('data.analytics.today.returns', '260.000')
            ->assertJsonPath('data.analytics.period.sales', '1945.000');
        $topCup = $this->rowFor($dashboard->json('data.top_selling_items'), 'item_id', $this->cupId);
        $this->assertSame('2.000', $topCup['total_qty']);
        $this->assertSame('520.000', $topCup['total_revenue']);
        $todayTrend = $this->rowFor($dashboard->json('data.analytics.daily_trend'), 'date', $this->today);
        $this->assertSame('260.000', $todayTrend['returns']);

        $store = $this->rowFor($this->getJson('/api/v1/reports/stores?period=today', $this->headers)->assertOk()->json('data'), 'id', $this->storeId);
        $this->assertSame('1945.000', $store['total_sales']);
        $this->assertSame('2205.000', $store['gross_sales']);
        $this->assertSame('260.000', $store['total_returns']);
        $this->assertSame('1945.000', $store['net_sales']);
        $this->assertSame('425.000', $store['gross_profit']);

        $customer = $this->rowFor($this->getJson('/api/v1/reports/customers?period=today', $this->headers)->assertOk()->json('data'), 'customer_id', $this->customerId);
        $this->assertSame('1945.000', $customer['total_bought']);
        $this->assertSame('2205.000', $customer['gross_bought']);
        $this->assertSame('260.000', $customer['total_returns']);
        $this->assertSame(3, $customer['total_invoices']);
    }

    public function test_a_return_on_a_later_day_reduces_the_return_period_not_the_invoice_period(): void
    {
        $saleDay = CarbonImmutable::parse($this->today)->subDays(3)->toDateString();
        $cupLine = $this->invoice([['item_id' => $this->cupId, 'quantity' => '3.000', 'unit_price' => '260.000']], invoiceDate: $saleDay);
        $this->salesReturn($cupLine['invoice_id'], $cupLine['line_id'], '1.000')->assertCreated();

        $this->summary("from_date={$saleDay}&to_date={$saleDay}")->assertOk()
            ->assertJsonPath('summary.total_sales', '780.000')
            ->assertJsonPath('summary.total_returns', '0.000')
            ->assertJsonPath('summary.total_cogs', '540.000')
            ->assertJsonPath('summary.gross_profit', '240.000');

        $this->summary("from_date={$this->today}&to_date={$this->today}")->assertOk()
            ->assertJsonPath('summary.gross_sales', '0.000')
            ->assertJsonPath('summary.total_returns', '260.000')
            ->assertJsonPath('summary.total_sales', '-260.000')
            ->assertJsonPath('summary.total_cogs', '-180.000')
            ->assertJsonPath('summary.gross_profit', '-80.000');

        $this->summary("from_date={$saleDay}&to_date={$this->today}")->assertOk()
            ->assertJsonPath('summary.total_sales', '520.000')
            ->assertJsonPath('summary.total_cogs', '360.000');
    }

    public function test_partial_return_from_a_discounted_line_deducts_the_actual_refunded_value(): void
    {
        // 4 x 50 with a 20 line discount = 180; a 18 invoice discount leaves 162 -> 40.500 a unit.
        $line = $this->invoice(
            [['item_id' => $this->cupId, 'quantity' => '4.000', 'unit_price' => '50.000', 'discount_amount' => '20.000']],
            invoiceDiscount: '18.000',
        );

        $refund = (string) $this->salesReturn($line['invoice_id'], $line['line_id'], '1.000')->assertCreated()->json('data.total_amount');
        $this->assertSame('40.500', $refund);

        $this->summary()->assertOk()
            ->assertJsonPath('summary.gross_sales', '162.000')
            ->assertJsonPath('summary.total_returns', '40.500')
            ->assertJsonPath('summary.total_sales', '121.500')
            ->assertJsonPath('summary.returns_cost', '180.000')
            ->assertJsonPath('summary.total_cogs', '540.000');

        // Item rows start from the LINE total (180, before the invoice discount), so they take
        // the return back on that basis: 1 x 180 / 4 = 45 -> 180 - 45 = 135.
        $cup = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->cupId);
        $this->assertSame('3.000', $cup['total_qty']);
        $this->assertSame('135.000', $cup['total_revenue']);
        $this->assertSame('540.000', $cup['total_cogs']);
        $this->assertSame('45.000', $cup['returned_value']);
    }

    public function test_full_return_of_a_line_with_line_and_invoice_discounts_leaves_nothing_on_the_item(): void
    {
        $line = $this->invoice(
            [['item_id' => $this->cupId, 'quantity' => '4.000', 'unit_price' => '50.000', 'discount_amount' => '20.000']],
            invoiceDiscount: '18.000',
        );
        $this->salesReturn($line['invoice_id'], $line['line_id'], '4.000')->assertCreated()->assertJsonPath('data.total_amount', '162.000');

        $this->summary()->assertOk()
            ->assertJsonPath('summary.total_sales', '0.000')
            ->assertJsonPath('summary.total_cogs', '0.000')
            ->assertJsonPath('summary.gross_profit', '0.000');

        foreach ([
            $this->getJson('/api/v1/reports/items?period=today', $this->headers)->assertOk()->json('data'),
            $this->getJson('/api/v1/reports/top-items?period=today', $this->headers)->assertOk()->json('top_items'),
            $this->getJson('/api/v1/reports/comprehensive?period=today', $this->headers)->assertOk()->json('item_profits'),
        ] as $rows) {
            $cup = $this->rowFor($rows, 'item_id', $this->cupId);
            $this->assertSame('0.000', $cup['total_qty']);
            $this->assertSame('0.000', $cup['total_revenue']);
            $this->assertSame('0.000', $cup['total_cogs']);
            $this->assertSame('0.000', $cup['profit']);
            $this->assertSame('0.0', $cup['margin']);
        }

        $top = $this->rowFor($this->getJson('/api/v1/dashboard', $this->headers)->assertOk()->json('data.top_selling_items'), 'item_id', $this->cupId);
        $this->assertSame('0.000', $top['total_qty']);
        $this->assertSame('0.000', $top['total_revenue']);
    }

    public function test_item_line_basis_rounds_half_up_and_several_returns_of_a_whole_line_add_back_exactly(): void
    {
        // 3 x 100 with a 0.001 line discount = 299.999 (not divisible by 3).
        $line = $this->invoice([['item_id' => $this->cupId, 'quantity' => '3.000', 'unit_price' => '100.000', 'discount_amount' => '0.001']]);

        $this->salesReturn($line['invoice_id'], $line['line_id'], '1.000')->assertCreated();
        // 1 x 299.999 / 3 = 99.999666... -> 100.000 (half-up) -> 299.999 - 100.000 = 199.999
        $cup = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->cupId);
        $this->assertSame('100.000', $cup['returned_value']);
        $this->assertSame('199.999', $cup['total_revenue']);

        $this->salesReturn($line['invoice_id'], $line['line_id'], '2.000')->assertCreated();
        // Valued per invoice line on the summed quantity: 3 x 299.999 / 3 = 299.999 exactly.
        $cup = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->cupId);
        $this->assertSame('0.000', $cup['total_qty']);
        $this->assertSame('299.999', $cup['returned_value']);
        $this->assertSame('0.000', $cup['total_revenue']);
        $this->assertSame('0.000', $cup['total_cogs']);
    }

    public function test_legacy_return_without_a_line_link_takes_the_cost_the_sale_was_costed_at(): void
    {
        // Sold at the weighted average (180) while the item's cost_price is 200.
        $legacyId = $this->inTenant($this->tenant, fn (): int => $this->makeItem('RPT-LEG', '200.000', '260.000', $this->storeId, wac: '180.000'));
        $line = $this->invoice([['item_id' => $legacyId, 'quantity' => '1.000', 'unit_price' => '260.000']]);
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $line['invoice_id'],
            'return_date' => $this->today, 'items' => [['invoice_item_id' => $line['line_id'], 'quantity' => '1.000']],
        ], $this->headers)->assertCreated();

        // A return booked before BE-01: invoice on the document, no link on the line. Its stock
        // movement was written at cost_price (200).
        $this->inTenant($this->tenant, function () use ($legacyId): void {
            ReturnItem::query()->where('item_id', $legacyId)->update(['invoice_item_id' => null]);
            $this->assertSame('200.000', (string) StockMovement::query()->where('item_id', $legacyId)->where('movement_type', 'sales_return_in')->value('unit_cost'));
        });

        $this->summary()->assertOk()
            ->assertJsonPath('summary.returns_cost', '180.000')
            ->assertJsonPath('summary.total_cogs', '0.000')
            ->assertJsonPath('summary.gross_profit', '0.000');
    }

    public function test_invoice_less_return_without_its_stock_movement_falls_back_to_the_current_item_cost(): void
    {
        $returnId = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => $this->today,
            'items' => [['item_id' => $this->cupId, 'quantity' => '2.000', 'unit_price' => '250.000']],
        ], $this->headers)->assertCreated()->json('data.id');

        $this->inTenant($this->tenant, function () use ($returnId): void {
            StockMovement::query()->where('source_type', ReturnDocument::class)->where('source_id', $returnId)->delete();
            Item::query()->whereKey($this->cupId)->update(['cost_price' => '210.000']);
        });

        $this->summary()->assertOk()
            ->assertJsonPath('summary.total_returns', '500.000')
            ->assertJsonPath('summary.returns_cost', '420.000');
    }

    public function test_fractional_return_quantity_uses_the_original_line_cost(): void
    {
        $line = $this->invoice([['item_id' => $this->cupId, 'quantity' => '2.000', 'unit_price' => '260.000']]);
        $this->salesReturn($line['invoice_id'], $line['line_id'], '0.250')->assertCreated();

        // The item's cost changes after the sale: the report keeps the invoice line's 180.
        $this->inTenant($this->tenant, fn () => Item::query()->whereKey($this->cupId)->update(['cost_price' => '999.000', 'weighted_avg_cost' => '999.000']));

        $this->summary()->assertOk()
            ->assertJsonPath('summary.total_returns', '65.000')
            ->assertJsonPath('summary.returns_cost', '45.000')
            ->assertJsonPath('summary.total_sales', '455.000')
            ->assertJsonPath('summary.total_cogs', '315.000');
    }

    public function test_deleting_the_return_puts_every_figure_back(): void
    {
        $cupLine = $this->sellScenario();
        $returnId = (int) $this->salesReturn($cupLine['invoice_id'], $cupLine['line_id'], '1.000')->assertCreated()->json('data.id');
        $this->summary()->assertJsonPath('summary.total_sales', '1945.000');

        $this->deleteJson("/api/v1/returns/{$returnId}", [], $this->headers)->assertOk();

        $this->summary()->assertOk()
            ->assertJsonPath('summary.total_sales', '2205.000')
            ->assertJsonPath('summary.total_returns', '0.000')
            ->assertJsonPath('summary.returns_count', 0)
            ->assertJsonPath('summary.total_cogs', '1700.000')
            ->assertJsonPath('summary.gross_profit', '505.000');
        $cup = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->cupId);
        $this->assertSame('3.000', $cup['total_qty']);
        $this->assertSame('780.000', $cup['total_revenue']);
        $this->getJson('/api/v1/dashboard', $this->headers)->assertJsonPath('data.metrics.today_sales', '2205.000');
    }

    public function test_a_deleted_return_cannot_be_restored_and_booking_it_again_gives_the_with_return_figures_exactly(): void
    {
        $cupLine = $this->sellScenario();
        $returnId = (int) $this->salesReturn($cupLine['invoice_id'], $cupLine['line_id'], '1.000')->assertCreated()->json('data.id');
        $withReturn = $this->summary()->assertOk()->json('summary');
        $withReturnItem = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->cupId);

        $this->deleteJson("/api/v1/returns/{$returnId}", [], $this->headers)->assertOk();

        // BE-02: a return is never restored from the trash (its stock/balance were reversed).
        $this->postJson("/api/v1/trash/returns/{$returnId}/restore", [], $this->headers)->assertStatus(422);
        // Re-running the deletion reversal on it changes nothing (it was reversed when deleted).
        $reversed = $this->inTenant($this->tenant, fn (): array => app(ReverseDeletedReturnAction::class)->execute($returnId));
        $this->assertFalse($reversed['reversed']);
        $this->summary()->assertJsonPath('summary.total_sales', '2205.000')->assertJsonPath('summary.total_cogs', '1700.000');

        // Booking the same return again lands on exactly the with-return figures.
        $this->salesReturn($cupLine['invoice_id'], $cupLine['line_id'], '1.000')->assertCreated();
        $again = $this->summary()->assertOk()->json('summary');
        foreach (['total_sales', 'gross_sales', 'total_returns', 'returns_cost', 'returns_count', 'total_cogs', 'gross_profit', 'net_profit', 'margin_percentage'] as $key) {
            $this->assertSame($withReturn[$key], $again[$key], $key);
        }
        $this->assertSame('1945.000', $again['total_sales']);
        $this->assertSame($withReturnItem, $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->cupId));
    }

    public function test_top_items_rejects_an_unparseable_date_with_422(): void
    {
        $this->getJson('/api/v1/reports/top-items?from_date=abc', $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['from_date']);
        $this->getJson('/api/v1/reports/top-items?to_date=not-a-date', $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['to_date']);

        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $this->getJson('/api/v1/reports/top-items', $this->tenantHeaders($this->tenant, $cashier))->assertStatus(403);
    }

    public function test_invoice_less_return_uses_the_cost_recorded_when_the_goods_came_back(): void
    {
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => $this->today,
            'items' => [['item_id' => $this->cupId, 'quantity' => '1.000', 'unit_price' => '250.000']],
        ], $this->headers)->assertCreated();

        $this->inTenant($this->tenant, fn () => Item::query()->whereKey($this->cupId)->update(['cost_price' => '999.000', 'weighted_avg_cost' => '999.000']));

        $this->summary()->assertOk()
            ->assertJsonPath('summary.total_returns', '250.000')
            ->assertJsonPath('summary.returns_cost', '180.000')
            ->assertJsonPath('summary.total_sales', '-250.000')
            ->assertJsonPath('summary.total_cogs', '-180.000');

        // Returned in the period but sold earlier: the item still gets its (negative) row.
        $cup = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->cupId);
        $this->assertSame('-1.000', $cup['total_qty']);
        $this->assertSame('-250.000', $cup['total_revenue']);
        $this->assertSame('-180.000', $cup['total_cogs']);
    }

    public function test_a_purchase_return_never_changes_sales_figures(): void
    {
        $this->sellScenario();
        $supplierId = $this->inTenant($this->tenant, fn (): int => (int) Supplier::create(['name' => 'مورد التقارير', 'phone' => '01000008002', 'current_balance' => '0.000', 'is_active' => true])->id);
        $purchaseId = $this->inTenant($this->tenant, function () use ($supplierId): int {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());

            return (int) app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplierId, 'store_id' => $this->storeId, 'paid_amount' => '0.000',
                'items' => [['item_id' => $this->cupId, 'quantity' => '5.000', 'cost_price' => '180.000']],
            ])->id;
        });
        $purchaseLine = $this->inTenant($this->tenant, fn (): int => (int) PurchaseItem::query()->where('purchase_id', $purchaseId)->value('id'));

        $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return', 'supplier_id' => $supplierId, 'purchase_id' => $purchaseId,
            'return_date' => $this->today, 'items' => [['purchase_item_id' => $purchaseLine, 'quantity' => '2.000']],
        ], $this->headers)->assertCreated();

        $this->summary()->assertOk()
            ->assertJsonPath('summary.total_sales', '2205.000')
            ->assertJsonPath('summary.total_returns', '0.000')
            ->assertJsonPath('summary.total_cogs', '1700.000');
        $this->getJson('/api/v1/dashboard', $this->headers)->assertJsonPath('data.metrics.today_sales', '2205.000');
        $cup = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $this->headers)->json('data'), 'item_id', $this->cupId);
        $this->assertSame('3.000', $cup['total_qty']);
    }

    public function test_a_return_in_store_a_never_affects_store_b(): void
    {
        $storeB = $this->inTenant($this->tenant, function (): int {
            $id = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'RPT-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            StoreStock::create(['store_id' => $id, 'item_id' => $this->cupId, 'quantity' => '50.000']);

            return $id;
        });
        $lineA = $this->invoice([['item_id' => $this->cupId, 'quantity' => '3.000', 'unit_price' => '260.000']]);
        $this->invoice([['item_id' => $this->cupId, 'quantity' => '2.000', 'unit_price' => '260.000']], storeId: $storeB);
        $this->salesReturn($lineA['invoice_id'], $lineA['line_id'], '1.000')->assertCreated();

        $headersB = $this->tenantHeaders($this->tenant, null, $storeB);
        $this->getJson('/api/v1/reports/summary?period=today', $headersB)->assertOk()
            ->assertJsonPath('summary.total_sales', '520.000')
            ->assertJsonPath('summary.total_returns', '0.000')
            ->assertJsonPath('summary.total_cogs', '360.000');
        $this->getJson('/api/v1/dashboard', $headersB)->assertOk()
            ->assertJsonPath('data.metrics.today_sales', '520.000')
            ->assertJsonPath('data.metrics.today_returns', '0.000');
        $cupB = $this->rowFor($this->getJson('/api/v1/reports/items?period=today', $headersB)->json('data'), 'item_id', $this->cupId);
        $this->assertSame('2.000', $cupB['total_qty']);

        $this->summary()->assertJsonPath('summary.total_sales', '520.000')->assertJsonPath('summary.total_returns', '260.000');

        $stores = $this->getJson('/api/v1/reports/stores?period=today', $this->headers)->assertOk()->json('data');
        $this->assertSame('260.000', $this->rowFor($stores, 'id', $this->storeId)['total_returns']);
        $this->assertSame('520.000', $this->rowFor($stores, 'id', $this->storeId)['total_sales']);
        $this->assertSame('0.000', $this->rowFor($stores, 'id', $storeB)['total_returns']);
        $this->assertSame('520.000', $this->rowFor($stores, 'id', $storeB)['total_sales']);
    }

    public function test_another_tenants_returns_never_reach_this_tenants_reports(): void
    {
        $this->sellScenario();

        $other = $this->createTenant();
        $otherStore = (int) $this->tenantStore($other)->id;
        $otherCustomer = $this->inTenant($other, fn (): int => (int) Customer::create(['name' => 'عميل آخر', 'phone' => '01000008003', 'price_tier' => 'retail', 'is_active' => true])->id);
        $otherItem = $this->inTenant($other, fn (): int => $this->makeItem('RPT-OTHER', '100.000', '333.000', $otherStore));
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $otherCustomer, 'return_date' => $this->today,
            'items' => [['item_id' => $otherItem, 'quantity' => '3.000', 'unit_price' => '333.000']],
        ], $this->tenantHeaders($other))->assertCreated();

        $this->getJson('/api/v1/reports/summary?period=today', $this->tenantHeaders($other))->assertOk()
            ->assertJsonPath('summary.total_returns', '999.000');

        $this->summary()->assertOk()
            ->assertJsonPath('summary.total_sales', '2205.000')
            ->assertJsonPath('summary.total_returns', '0.000')
            ->assertJsonPath('summary.total_cogs', '1700.000');
    }

    public function test_reports_still_require_authentication_and_permission(): void
    {
        $this->getJson('/api/v1/reports/summary', $this->tenantGuestHeaders($this->tenant))->assertStatus(401);

        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $this->getJson('/api/v1/reports/summary', $this->tenantHeaders($this->tenant, $cashier))->assertStatus(403);
    }

    /**
     * 3 x 260 (cost 180) on one invoice, 750 with a 10% line discount and 750 (cost 580) on two
     * more: 2205 sales, 1700 cost.
     *
     * @return array{invoice_id: int, line_id: int} the 3 x 260 line
     */
    private function sellScenario(): array
    {
        $cupLine = $this->invoice([['item_id' => $this->cupId, 'quantity' => '3.000', 'unit_price' => '260.000']]);
        $this->invoice([['item_id' => $this->grinderId, 'quantity' => '1.000', 'unit_price' => '750.000', 'discount_amount' => '75.000']]);
        $this->invoice([['item_id' => $this->grinderId, 'quantity' => '1.000', 'unit_price' => '750.000']]);

        return $cupLine;
    }

    /**
     * @param  list<array<string, string|int>>  $items
     * @return array{invoice_id: int, line_id: int} the invoice and its first line
     */
    private function invoice(array $items, string $invoiceDiscount = '0.000', ?int $storeId = null, ?string $invoiceDate = null): array
    {
        return $this->inTenant($this->tenant, function () use ($items, $invoiceDiscount, $storeId, $invoiceDate): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $data = [
                'customer_id' => $this->customerId,
                'store_id' => $storeId ?? $this->storeId,
                'payment_type' => 'credit', 'payment_method' => 'cash', 'paid_amount' => '0.000',
                'discount_type' => 'fixed', 'discount_value' => $invoiceDiscount,
                'items' => $items,
            ];
            if ($invoiceDate !== null) {
                $data['invoice_date'] = $invoiceDate;
            }
            $invoice = app(InvoiceService::class)->confirmInvoice($data);

            return [
                'invoice_id' => (int) $invoice->id,
                'line_id' => (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->orderBy('id')->value('id'),
            ];
        });
    }

    private function salesReturn(int $invoiceId, int $lineId, string $qty): TestResponse
    {
        return $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => $this->today, 'items' => [['invoice_item_id' => $lineId, 'quantity' => $qty]],
        ], $this->headers);
    }

    private function summary(string $query = 'period=today'): TestResponse
    {
        return $this->getJson('/api/v1/reports/summary?'.$query, $this->headers);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $rows
     * @return array<string, mixed>
     */
    private function rowFor(?array $rows, string $key, int|string $value): array
    {
        foreach ($rows ?? [] as $row) {
            if ($row[$key] == $value) {
                return $row;
            }
        }

        $this->fail("No row with {$key} = {$value}.");
    }

    private function makeItem(string $code, string $cost, string $price, int $storeId, ?string $wac = null): int
    {
        $item = Item::create([
            'name' => 'صنف '.$code, 'code' => $code, 'category' => 'coffee_beans', 'unit' => 'كجم',
            'cost_price' => $cost, 'weighted_avg_cost' => $wac ?? $cost, 'selling_price' => $price,
            'price_retail' => $price, 'price_wholesale' => $price,
            'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => '100.000']);

        return (int) $item->id;
    }
}
