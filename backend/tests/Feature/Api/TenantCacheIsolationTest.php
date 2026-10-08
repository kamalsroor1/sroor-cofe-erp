<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InventoryAnalyticsService;
use App\Services\ProfitLossService;
use App\Support\TenantCache;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * P0-X1 regression: report caches (P&L, ABC) must be keyed per tenant and
 * clearCache() must actually invalidate the keys the reports write.
 *
 * Tenancy is switched by hand (no bootstrappers) so both "tenants" share the
 * same sqlite :memory: DB. That is the worst case for a cache leak: if a
 * report computed for tenant A is served to tenant B, B sees stale/foreign data.
 */
class TenantCacheIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Store $store;

    private User $admin;

    private Customer $customer;

    private Item $item;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private string $from;

    private string $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);
        $this->seed(PermissionsSeeder::class);

        $this->store = Store::create([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-001',
            'type' => 'warehouse',
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->admin = User::factory()->create([
            'name' => 'كمال سرور',
            'phone' => self::ADMIN_PHONE,
            'password' => Hash::make('password'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $this->admin->assignRole(Role::findByName('admin'));

        $this->customer = Customer::create([
            'name' => 'كافيه العروبة',
            'phone' => '01000007003',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);

        $this->item = Item::create([
            'name' => 'بن برازيلي',
            'code' => 'BN-QA-01',
            'category' => 'coffee_beans',
            'cost_price' => '300.000',
            'selling_price' => '500.000',
            'current_stock' => '50.000',
            'min_stock_level' => '1.000',
            'is_active' => true,
        ]);

        $this->tenantA = new Tenant(['id' => 'tenant-a']);
        $this->tenantB = new Tenant(['id' => 'tenant-b']);

        $this->from = now()->subDays(7)->toDateString();
        $this->to = now()->toDateString();
    }

    protected function tearDown(): void
    {
        tenancy()->tenant = null;
        tenancy()->initialized = false;

        parent::tearDown();
    }

    private function actAsTenant(?Tenant $tenant): void
    {
        tenancy()->tenant = $tenant;
        tenancy()->initialized = $tenant !== null;
    }

    private function createConfirmedInvoice(string $number = 'INV-QA-001'): Invoice
    {
        $invoice = Invoice::create([
            'invoice_number' => $number,
            'store_id' => $this->store->id,
            'customer_id' => $this->customer->id,
            'user_id' => $this->admin->id,
            'invoice_date' => now()->toDateString(),
            'discount_amount' => '0.000',
            'net_total' => '1000.000',
            'paid_amount' => '1000.000',
            'remaining_amount' => '0.000',
            'total_cost' => '600.000',
            'status' => 'confirmed',
            'payment_method' => 'cash',
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_id' => $this->item->id,
            'quantity' => '2.000',
            'unit_price' => '500.000',
            'cost_price' => '300.000',
            'discount_amount' => '0.000',
            'total_price' => '1000.000',
        ]);

        return $invoice;
    }

    public function test_profit_loss_report_is_not_served_from_another_tenants_cache(): void
    {
        $service = app(ProfitLossService::class);

        $this->actAsTenant($this->tenantA);
        $reportA = $service->getProfitLossReport($this->from, $this->to, $this->store->id);
        $this->assertSame('0.000', $reportA['grand_revenue']);

        $this->createConfirmedInvoice();

        $this->actAsTenant($this->tenantB);
        $reportB = $service->getProfitLossReport($this->from, $this->to, $this->store->id);

        $this->assertSame(
            '1000.000',
            $reportB['grand_revenue'],
            'Tenant B received tenant A\'s cached P&L report (cache key is not tenant-scoped).'
        );
        $this->assertSame(1, $reportB['stores'][0]['invoices_count']);
    }

    public function test_abc_analysis_is_not_served_from_another_tenants_cache(): void
    {
        $service = app(InventoryAnalyticsService::class);

        $this->actAsTenant($this->tenantA);
        $reportA = $service->getAbcAnalysis($this->from, $this->to, $this->store->id);
        $this->assertSame('0.000', $reportA['total_revenue']);

        $this->createConfirmedInvoice();

        $this->actAsTenant($this->tenantB);
        $reportB = $service->getAbcAnalysis($this->from, $this->to, $this->store->id);

        $this->assertSame(
            '1000.000',
            (string) $reportB['total_revenue'],
            'Tenant B received tenant A\'s cached ABC analysis (cache key is not tenant-scoped).'
        );
    }

    public function test_profit_loss_clear_cache_invalidates_the_cached_report(): void
    {
        $service = app(ProfitLossService::class);

        $this->actAsTenant($this->tenantA);
        $before = $service->getProfitLossReport($this->from, $this->to, $this->store->id);
        $this->assertSame('0.000', $before['grand_revenue']);

        $this->createConfirmedInvoice();
        ProfitLossService::clearCache();

        $after = $service->getProfitLossReport($this->from, $this->to, $this->store->id);
        $this->assertSame(
            '1000.000',
            $after['grand_revenue'],
            'ProfitLossService::clearCache() did not invalidate the cached report.'
        );
    }

    public function test_profit_loss_clear_cache_for_store_invalidates_the_cached_report(): void
    {
        $service = app(ProfitLossService::class);

        $this->actAsTenant($this->tenantA);
        $service->getProfitLossReport($this->from, $this->to, $this->store->id);
        $service->getProfitLossReport($this->from, $this->to, null);

        $this->createConfirmedInvoice();
        ProfitLossService::clearCache($this->store->id);

        $this->assertSame('1000.000', $service->getProfitLossReport($this->from, $this->to, $this->store->id)['grand_revenue']);
        $this->assertSame('1000.000', $service->getProfitLossReport($this->from, $this->to, null)['grand_revenue']);
    }

    public function test_abc_clear_cache_invalidates_the_cached_report(): void
    {
        $service = app(InventoryAnalyticsService::class);

        $this->actAsTenant($this->tenantA);
        $before = $service->getAbcAnalysis($this->from, $this->to, $this->store->id);
        $this->assertSame('0.000', $before['total_revenue']);

        $this->createConfirmedInvoice();
        InventoryAnalyticsService::clearCache();

        $after = $service->getAbcAnalysis($this->from, $this->to, $this->store->id);
        $this->assertSame(
            '1000.000',
            (string) $after['total_revenue'],
            'InventoryAnalyticsService::clearCache() did not invalidate the cached report.'
        );
    }

    public function test_clear_cache_in_one_tenant_does_not_require_touching_the_other(): void
    {
        $service = app(ProfitLossService::class);

        // Both tenants cache an empty report.
        $this->actAsTenant($this->tenantA);
        $service->getProfitLossReport($this->from, $this->to, $this->store->id);
        $this->actAsTenant($this->tenantB);
        $service->getProfitLossReport($this->from, $this->to, $this->store->id);

        $this->createConfirmedInvoice();

        // Only tenant A clears; A sees fresh data.
        $this->actAsTenant($this->tenantA);
        ProfitLossService::clearCache();
        $this->assertSame('1000.000', $service->getProfitLossReport($this->from, $this->to, $this->store->id)['grand_revenue']);
    }

    public function test_tenant_cache_key_differs_between_tenants_and_central(): void
    {
        $this->actAsTenant(null);
        $central = TenantCache::key('x');

        $this->actAsTenant($this->tenantA);
        $keyA = TenantCache::key('x');

        $this->actAsTenant($this->tenantB);
        $keyB = TenantCache::key('x');

        $this->assertNotSame($keyA, $keyB);
        $this->assertNotSame($keyA, $central);
        $this->assertNotSame($keyB, $central);
        $this->assertStringContainsString('tenant-a', $keyA);
        $this->assertStringContainsString('tenant-b', $keyB);

        // Deterministic within a tenant.
        $this->actAsTenant($this->tenantA);
        $this->assertSame($keyA, TenantCache::key('x'));
    }
}
