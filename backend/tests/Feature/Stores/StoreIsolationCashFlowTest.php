<?php

declare(strict_types=1);

namespace Tests\Feature\Stores;

use App\Actions\Dashboard\GetTenantDashboardAnalyticsAction;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DailyCashFlowService;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Support\ActiveStore;
use App\Support\TenantClock;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * BUG-01 follow-up: the "cash collected today" of the dashboards, the treasury summary and the
 * payments list come from one source (DailyCashFlowService: the branch's cash vouchers). Before,
 * they added invoices.paid_amount to every customer voucher of the tenant: a cash sale was
 * counted twice (the invoice + its PAY-INV voucher) and other branches' receipts and supplier
 * payments leaked into the active branch.
 *
 * Fixture per branch (tenant business day):
 *  - A: cash sale 0.250 x 100.000 = 25.000, receipt 12.500, supplier cash 5.250, cash expense 2.000
 *  - B: cash sale 1.000 x 100.000 = 100.000, receipt 70.000, supplier cash 45.000
 */
final class StoreIsolationCashFlowTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeA;

    private int $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;
        $adminId = (int) $this->tenantAdmin($this->tenant)->id;

        $this->storeB = $this->inTenant($this->tenant, function () use ($adminId): int {
            $this->actingAs(User::query()->findOrFail($adminId));

            $storeB = (int) Store::query()->create([
                'name' => 'فرع ب',
                'code' => 'CF-B',
                'type' => 'retail',
                'is_main' => false,
                'is_active' => true,
            ])->id;

            $item = Item::query()->create([
                'code' => 'COF-CF',
                'name' => 'بن تجربة',
                'category' => 'بن سادة',
                'unit' => 'كجم',
                'current_stock' => '200.000',
                'cost_price' => '50.000',
                'weighted_avg_cost' => '50.000',
                'selling_price' => '100.000',
                'min_stock_level' => '0.000',
                'is_active' => true,
            ]);

            foreach ([$this->storeA, $storeB] as $storeId) {
                StoreStock::query()->create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => '100.000', 'min_stock' => '0.000']);
            }

            $customerId = (int) Customer::query()->create([
                'name' => 'عميل الخزينة',
                'phone' => '01000007401',
                'current_balance' => '0.000',
                'is_active' => true,
            ])->id;
            $supplierId = (int) Supplier::query()->create([
                'name' => 'مورد الخزينة',
                'current_balance' => '100.000',
                'is_active' => true,
            ])->id;

            $day = [
                [$this->storeA, '0.250', '12.500', '5.250'],
                [$storeB, '1.000', '70.000', '45.000'],
            ];

            foreach ($day as [$storeId, $quantity, $receipt, $supplierPaid]) {
                app(InvoiceService::class)->confirmInvoice([
                    'customer_id' => $customerId,
                    'store_id' => $storeId,
                    'payment_type' => 'cash',
                    'items' => [['item_id' => $item->id, 'quantity' => $quantity, 'unit_price' => '100.000']],
                ]);
                app(PaymentService::class)->recordCustomerPayment([
                    'customer_id' => $customerId,
                    'store_id' => $storeId,
                    'amount' => $receipt,
                    'payment_method' => 'cash',
                ]);
                app(PaymentService::class)->recordSupplierPayment([
                    'supplier_id' => $supplierId,
                    'store_id' => $storeId,
                    'amount' => $supplierPaid,
                    'payment_method' => 'cash',
                ]);
            }

            Expense::query()->create([
                'store_id' => $this->storeA,
                'user_id' => $adminId,
                'expense_number' => 'EXP-CF-A',
                'title' => 'مصروف نقدي',
                'amount' => '2.000',
                'category' => 'تشغيلي',
                'payment_method' => 'cash',
                'expense_date' => app(TenantClock::class)->today(),
            ]);

            return $storeB;
        });
    }

    public function test_the_shared_cash_flow_counts_each_sale_once_per_store(): void
    {
        [$a, $b, $all] = $this->inTenant($this->tenant, function (): array {
            $service = app(DailyCashFlowService::class);
            $today = app(TenantClock::class)->today();

            return [$service->forDate($today, $this->storeA), $service->forDate($today, $this->storeB), $service->forDate($today, null)];
        });

        $this->assertSame([
            'cash_sales' => '25.000',
            'customer_receipts' => '12.500',
            'total_cash_in' => '37.500',
            'supplier_paid' => '5.250',
            'cash_expenses' => '2.000',
            'total_cash_out' => '7.250',
            'net_cash' => '30.250',
        ], $a);
        $this->assertSame('100.000', $b['cash_sales']);
        $this->assertSame('70.000', $b['customer_receipts']);
        $this->assertSame('45.000', $b['supplier_paid']);
        $this->assertSame('125.000', $b['net_cash']);
        $this->assertSame('207.500', $all['total_cash_in']);
    }

    public function test_treasury_summary_returns_the_active_branch_cash_as_exact_strings(): void
    {
        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, null, $this->storeA))
            ->assertOk()
            ->assertJsonPath('store_id', $this->storeA)
            ->assertJsonPath('today.sales_total', '25.000')
            ->assertJsonPath('today.cash_collected', '25.000')
            ->assertJsonPath('today.customer_receipts', '12.500')
            ->assertJsonPath('today.total_inflow', '37.500')
            ->assertJsonPath('today.supplier_paid', '5.250')
            ->assertJsonPath('today.expenses_total', '2.000')
            ->assertJsonPath('today.cash_expenses', '2.000')
            ->assertJsonPath('today.total_outflow', '7.250')
            ->assertJsonPath('today.net_cash', '30.250');

        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, null, $this->storeB))
            ->assertOk()
            ->assertJsonPath('today.cash_collected', '100.000')
            ->assertJsonPath('today.customer_receipts', '70.000')
            ->assertJsonPath('today.supplier_paid', '45.000')
            ->assertJsonPath('today.net_cash', '125.000');
    }

    public function test_dashboard_cash_collected_is_the_active_branch_vouchers_only(): void
    {
        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, null, $this->storeA))
            ->assertOk()
            ->assertJsonPath('data.metrics.total_cash_collected', 37.5)
            ->assertJsonPath('data.metrics.supplier_payments', 5.25)
            ->assertJsonPath('data.metrics.net_cash_today', 30.25);

        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, null, $this->storeB))
            ->assertOk()
            ->assertJsonPath('data.metrics.total_cash_collected', 170)
            ->assertJsonPath('data.metrics.supplier_payments', 45)
            ->assertJsonPath('data.metrics.net_cash_today', 125);

        // A verified `all` (admin) aggregates every branch, still counting each sale once.
        $all = $this->tenantHeaders($this->tenant);
        $all['X-Store-Id'] = ActiveStore::ALL;
        $this->getJson('/api/v1/dashboard', $all)
            ->assertOk()
            ->assertJsonPath('data.metrics.total_cash_collected', 207.5);
    }

    public function test_tenant_dashboard_analytics_uses_the_active_branch_vouchers_only(): void
    {
        $metrics = $this->inTenant($this->tenant, function (): array {
            $store = Store::query()->findOrFail($this->storeA);
            app(ActiveStore::class)->set($store);

            return app(GetTenantDashboardAnalyticsAction::class)->execute(null)['metrics'];
        });

        $this->assertSame(37.5, $metrics['total_cash_collected']);
        $this->assertSame(5.25, $metrics['supplier_payments']);
        $this->assertSame(30.25, $metrics['net_cash_today']);
    }

    public function test_payments_list_is_scoped_to_the_active_branch(): void
    {
        $route = Route::getRoutes()->getByName('api.payments.index');
        $this->assertNotNull($route);
        $this->assertContains('store.active', $route->gatherMiddleware());

        $a = $this->getJson('/api/v1/payments?per_page=50', $this->tenantHeaders($this->tenant, null, $this->storeA))
            ->assertOk()
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonPath('summary.total_collections', 37.5)
            ->assertJsonPath('summary.total_disbursements', 5.25);
        $this->assertSame(['12.500', '25.000', '5.250'], $this->sortedAmounts($a->json('data')));

        $b = $this->getJson('/api/v1/payments?per_page=50', $this->tenantHeaders($this->tenant, null, $this->storeB))
            ->assertOk()
            ->assertJsonPath('pagination.total', 3);
        $this->assertSame(['100.000', '45.000', '70.000'], $this->sortedAmounts($b->json('data')));

        $all = $this->tenantHeaders($this->tenant);
        $all['X-Store-Id'] = ActiveStore::ALL;
        $this->getJson('/api/v1/payments?per_page=50', $all)
            ->assertOk()
            ->assertJsonPath('pagination.total', 6);
    }

    public function test_a_branch_user_cannot_list_another_branchs_payments_or_all(): void
    {
        $cashierOfA = $this->createTenantUser($this->tenant, null, ['daily_journal.view']);

        $this->getJson('/api/v1/payments', $this->tenantHeaders($this->tenant, $cashierOfA, $this->storeB))
            ->assertStatus(403);

        $all = $this->tenantHeaders($this->tenant, $cashierOfA);
        $all['X-Store-Id'] = ActiveStore::ALL;
        $this->getJson('/api/v1/payments', $all)->assertStatus(403);

        $this->getJson('/api/v1/payments?store_id='.$this->storeB, $this->tenantHeaders($this->tenant, $cashierOfA, $this->storeA))
            ->assertStatus(403);

        $this->getJson('/api/v1/payments?per_page=50', $this->tenantHeaders($this->tenant, $cashierOfA, $this->storeA))
            ->assertOk()
            ->assertJsonPath('pagination.total', 3);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<string>
     */
    private function sortedAmounts(array $rows): array
    {
        $amounts = array_map(fn (array $row): string => (string) $row['amount'], $rows);
        sort($amounts, SORT_STRING);

        return $amounts;
    }
}
