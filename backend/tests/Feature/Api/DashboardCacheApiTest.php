<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TenantTestCase;

/**
 * PERF-01: the dashboard aggregates are cached for DashboardCache::TTL_SECONDS. The cache key
 * must never let figures cross a scope: another store, another tenant, or a user who may not
 * see financial (profit / debt) figures.
 */
final class DashboardCacheApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeA;

    private int $storeB;

    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;
        $this->inTenant($this->tenant, function (): void {
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'DSH-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $this->customerId = (int) Customer::create(['name' => 'عميل الداشبورد', 'phone' => '01000008301', 'price_tier' => 'retail', 'is_active' => true, 'current_balance' => '75.000'])->id;
        });
    }

    public function test_figures_are_cached_for_the_ttl_then_refreshed(): void
    {
        $this->sale($this->tenant, $this->storeA, $this->customerId, '100.000', '60.000');
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.today_sales', '100.000');

        $this->sale($this->tenant, $this->storeA, $this->customerId, '50.000', '30.000');
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.today_sales', '100.000');

        $this->travel(31)->seconds();
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.today_sales', '150.000');
    }

    public function test_store_a_figures_are_never_served_to_store_b_or_all_stores(): void
    {
        $this->sale($this->tenant, $this->storeA, $this->customerId, '100.000', '60.000');
        $this->sale($this->tenant, $this->storeB, $this->customerId, '7.500', '5.000');

        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.today_sales', '100.000')
            ->assertJsonPath('data.active_store.id', $this->storeA);
        $this->dashboard($this->storeB)->assertJsonPath('data.metrics.today_sales', '7.500')
            ->assertJsonPath('data.active_store.id', $this->storeB);
        $this->dashboard('all')->assertJsonPath('data.metrics.today_sales', '107.500');
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.today_sales', '100.000');
    }

    public function test_another_tenants_cached_figures_are_never_served(): void
    {
        $this->sale($this->tenant, $this->storeA, $this->customerId, '100.000', '60.000');
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.today_sales', '100.000');

        $other = $this->createTenant();
        $otherStore = (int) $this->tenantStore($other)->id;
        $otherCustomer = $this->inTenant($other, fn (): int => (int) Customer::create(['name' => 'عميل آخر', 'phone' => '01000008302', 'price_tier' => 'retail', 'is_active' => true])->id);
        $this->sale($other, $otherStore, $otherCustomer, '3.250', '1.000');

        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($other, null, $otherStore))->assertOk()
            ->assertJsonPath('data.metrics.today_sales', '3.250');
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.today_sales', '100.000');
    }

    public function test_a_user_without_reports_view_never_receives_cached_financial_figures(): void
    {
        $this->sale($this->tenant, $this->storeA, $this->customerId, '100.000', '60.000');
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);

        // The admin warms the cache first, with profit and debts.
        $this->dashboard($this->storeA)
            ->assertJsonPath('data.can_view_financials', true)
            ->assertJsonPath('data.metrics.monthly_gross_profit', '40.000')
            ->assertJsonPath('data.metrics.customers_debt', '75.000');

        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier, $this->storeA))->assertOk()
            ->assertJsonPath('data.can_view_financials', false)
            ->assertJsonPath('data.metrics.monthly_gross_profit', null)
            ->assertJsonPath('data.metrics.monthly_margin', null)
            ->assertJsonPath('data.metrics.customers_debt', null)
            ->assertJsonPath('data.metrics.suppliers_debt', null)
            ->assertJsonPath('data.metrics.today_sales', '100.000');
    }

    public function test_the_cashier_cache_does_not_hide_figures_from_a_manager(): void
    {
        $this->sale($this->tenant, $this->storeA, $this->customerId, '100.000', '60.000');
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);

        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier, $this->storeA))->assertOk()
            ->assertJsonPath('data.metrics.monthly_gross_profit', null);
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.monthly_gross_profit', '40.000');
    }

    public function test_the_dashboard_still_requires_authentication(): void
    {
        $this->getJson('/api/v1/dashboard', $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
    }

    private function dashboard(int|string $store): TestResponse
    {
        $headers = $this->tenantHeaders($this->tenant, null, $store === 'all' ? $this->storeA : (int) $store);
        if ($store === 'all') {
            $headers['X-Store-Id'] = 'all';
        }

        return $this->getJson('/api/v1/dashboard', $headers)->assertOk();
    }

    private function sale(Tenant $tenant, int $storeId, int $customerId, string $net, string $cost): void
    {
        $this->inTenant($tenant, function () use ($storeId, $customerId, $net, $cost): void {
            $at = now()->format('Y-m-d H:i:s');
            DB::table('invoices')->insert([
                'invoice_number' => 'DSH-'.uniqid(), 'customer_id' => $customerId,
                'user_id' => (int) User::query()->orderBy('id')->value('id'), 'store_id' => $storeId,
                'invoice_date' => app(TenantClock::class)->businessDate(), 'payment_type' => 'credit', 'status' => 'confirmed',
                'subtotal' => $net, 'net_total' => $net, 'paid_amount' => '0.000', 'remaining_amount' => $net,
                'total_cost' => $cost, 'created_at' => $at, 'updated_at' => $at,
            ]);
        });
    }
}
