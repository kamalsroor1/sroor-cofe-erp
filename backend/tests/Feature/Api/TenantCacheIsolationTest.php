<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\Tenant;
use App\Services\InventoryAnalyticsService;
use App\Services\ProfitLossService;
use App\Support\TenantCache;
use Tests\TenantTestCase;

/**
 * P0-X1 regression: report caches (P&L, ABC) must be keyed per tenant and
 * clearCache() must actually invalidate the keys the reports write.
 *
 * QA-4: two real harness tenants, each with its own database. Tenant B gets a store
 * with the SAME id as tenant A's main store, so the report cache keys of both tenants
 * differ ONLY by the tenant scope. That is the worst case for a cache leak: if a report
 * computed for tenant A is served to tenant B, B sees A's (empty) data instead of its own.
 */
class TenantCacheIsolationTest extends TenantTestCase
{
    private Tenant $tenantA;

    private Tenant $tenantB;

    /** Same id in both tenants (B's store is created with A's main store id). */
    private int $storeId;

    private string $from;

    private string $to;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = $this->createTenant();
        $this->tenantB = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenantA)->id;

        $storeId = $this->storeId;
        $this->inTenant($this->tenantB, function () use ($storeId): void {
            $store = new Store;
            $store->forceFill([
                'id' => $storeId,
                'name' => 'المخزن الرئيسي',
                'code' => 'MAIN-001',
                'type' => 'warehouse',
                'is_main' => false,
                'is_active' => true,
            ])->save();
        });
        $this->assertTrue($this->inTenant($this->tenantB, fn (): bool => Store::query()->whereKey($storeId)->exists()));

        $this->from = now()->subDays(7)->toDateString();
        $this->to = now()->toDateString();
    }

    /** One confirmed 1000.000 invoice on $this->storeId inside $tenant (2 kg at 500.000, cost 300.000). */
    private function createConfirmedInvoice(Tenant $tenant, string $number = 'INV-QA-001'): void
    {
        $adminId = (int) $this->tenantAdmin($tenant)->id;
        $storeId = $this->storeId;

        $this->inTenant($tenant, function () use ($adminId, $storeId, $number): void {
            $customer = Customer::create([
                'name' => 'كافيه العروبة',
                'phone' => '01000007003',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            $item = Item::create([
                'name' => 'بن برازيلي',
                'code' => 'BN-QA-01',
                'category' => 'coffee_beans',
                'cost_price' => '300.000',
                'selling_price' => '500.000',
                'current_stock' => '50.000',
                'min_stock_level' => '1.000',
                'is_active' => true,
            ]);

            $invoice = Invoice::create([
                'invoice_number' => $number,
                'store_id' => $storeId,
                'customer_id' => $customer->id,
                'user_id' => $adminId,
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
                'item_id' => $item->id,
                'quantity' => '2.000',
                'unit_price' => '500.000',
                'cost_price' => '300.000',
                'discount_amount' => '0.000',
                'total_price' => '1000.000',
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function profitLoss(Tenant $tenant, ?int $storeId): array
    {
        return $this->inTenant($tenant, fn (): array => app(ProfitLossService::class)->getProfitLossReport($this->from, $this->to, $storeId));
    }

    /** @return array<string, mixed> */
    private function abc(Tenant $tenant): array
    {
        return $this->inTenant($tenant, fn (): array => app(InventoryAnalyticsService::class)->getAbcAnalysis($this->from, $this->to, $this->storeId));
    }

    public function test_profit_loss_report_is_not_served_from_another_tenants_cache(): void
    {
        $reportA = $this->profitLoss($this->tenantA, $this->storeId);
        $this->assertSame('0.000', $reportA['grand_revenue']);

        $this->createConfirmedInvoice($this->tenantB);

        $reportB = $this->profitLoss($this->tenantB, $this->storeId);

        $this->assertSame(
            '1000.000',
            $reportB['grand_revenue'],
            'Tenant B received tenant A\'s cached P&L report (cache key is not tenant-scoped).'
        );
        $this->assertSame(1, $reportB['stores'][0]['invoices_count']);

        // And A still sees only its own (empty) data.
        $this->assertSame('0.000', $this->profitLoss($this->tenantA, $this->storeId)['grand_revenue']);
    }

    public function test_abc_analysis_is_not_served_from_another_tenants_cache(): void
    {
        $reportA = $this->abc($this->tenantA);
        $this->assertSame('0.000', $reportA['total_revenue']);

        $this->createConfirmedInvoice($this->tenantB);

        $reportB = $this->abc($this->tenantB);

        $this->assertSame(
            '1000.000',
            (string) $reportB['total_revenue'],
            'Tenant B received tenant A\'s cached ABC analysis (cache key is not tenant-scoped).'
        );
    }

    public function test_profit_loss_clear_cache_invalidates_the_cached_report(): void
    {
        $before = $this->profitLoss($this->tenantA, $this->storeId);
        $this->assertSame('0.000', $before['grand_revenue']);

        $this->createConfirmedInvoice($this->tenantA);
        $this->inTenant($this->tenantA, fn () => ProfitLossService::clearCache());

        $after = $this->profitLoss($this->tenantA, $this->storeId);
        $this->assertSame(
            '1000.000',
            $after['grand_revenue'],
            'ProfitLossService::clearCache() did not invalidate the cached report.'
        );
    }

    public function test_profit_loss_clear_cache_for_store_invalidates_the_cached_report(): void
    {
        $this->profitLoss($this->tenantA, $this->storeId);
        $this->profitLoss($this->tenantA, null);

        $this->createConfirmedInvoice($this->tenantA);
        $this->inTenant($this->tenantA, fn () => ProfitLossService::clearCache($this->storeId));

        $this->assertSame('1000.000', $this->profitLoss($this->tenantA, $this->storeId)['grand_revenue']);
        $this->assertSame('1000.000', $this->profitLoss($this->tenantA, null)['grand_revenue']);
    }

    public function test_abc_clear_cache_invalidates_the_cached_report(): void
    {
        $before = $this->abc($this->tenantA);
        $this->assertSame('0.000', $before['total_revenue']);

        $this->createConfirmedInvoice($this->tenantA);
        $this->inTenant($this->tenantA, fn () => InventoryAnalyticsService::clearCache());

        $after = $this->abc($this->tenantA);
        $this->assertSame(
            '1000.000',
            (string) $after['total_revenue'],
            'InventoryAnalyticsService::clearCache() did not invalidate the cached report.'
        );
    }

    public function test_clear_cache_in_one_tenant_does_not_require_touching_the_other(): void
    {
        // Both tenants cache an empty report.
        $this->profitLoss($this->tenantA, $this->storeId);
        $this->profitLoss($this->tenantB, $this->storeId);

        $this->createConfirmedInvoice($this->tenantA);
        $this->createConfirmedInvoice($this->tenantB);

        // Only tenant A clears; A sees fresh data.
        $this->inTenant($this->tenantA, fn () => ProfitLossService::clearCache());
        $this->assertSame('1000.000', $this->profitLoss($this->tenantA, $this->storeId)['grand_revenue']);

        // B's cache was not invalidated by A's clear: B is still served its own cached (stale) report.
        $this->assertSame('0.000', $this->profitLoss($this->tenantB, $this->storeId)['grand_revenue']);
    }

    public function test_tenant_cache_key_differs_between_tenants_and_central(): void
    {
        $this->endTenancy();
        $central = TenantCache::key('x');

        $keyA = $this->inTenant($this->tenantA, fn (): string => TenantCache::key('x'));
        $keyB = $this->inTenant($this->tenantB, fn (): string => TenantCache::key('x'));

        $this->assertNotSame($keyA, $keyB);
        $this->assertNotSame($keyA, $central);
        $this->assertNotSame($keyB, $central);
        $this->assertStringContainsString((string) $this->tenantA->getTenantKey(), $keyA);
        $this->assertStringContainsString((string) $this->tenantB->getTenantKey(), $keyB);

        // Deterministic within a tenant.
        $this->assertSame($keyA, $this->inTenant($this->tenantA, fn (): string => TenantCache::key('x')));
    }
}
