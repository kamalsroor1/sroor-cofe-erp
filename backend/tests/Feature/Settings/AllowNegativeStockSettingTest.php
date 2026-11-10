<?php

declare(strict_types=1);

namespace Tests\Feature\Settings;

use App\Exceptions\Stock\InsufficientStockException;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Services\InvoiceService;
use App\Services\Settings\TenantSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

final class AllowNegativeStockSettingTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);
    }

    public function test_settings_cache_is_invalidated_only_after_the_transaction_commits(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Setting::set('allow_negative_stock', '0');
            $this->assertFalse(Setting::getBool('allow_negative_stock', false)); // warms the cache

            DB::transaction(function (): void {
                Setting::set('allow_negative_stock', '1');

                // Not committed yet: the cached dictionary must not be dropped (a concurrent
                // reader would re-cache the stale value).
                $this->assertArrayHasKey(
                    'allow_negative_stock',
                    Cache::get(Setting::getCacheKey(), [])
                );
            });

            $this->assertNull(Cache::get(Setting::getCacheKey()));
            $this->assertTrue(app(TenantSettings::class)->allowNegativeStock());
        });
    }

    public function test_default_allow_negative_stock_is_false(): void
    {
        $this->getJson('/api/v1/settings', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('settings.allow_negative_stock', false);

        $this->inTenant($this->tenant, function (): void {
            $this->assertFalse(app(TenantSettings::class)->allowNegativeStock());
            $this->assertFalse(Setting::getBool('allow_negative_stock', false));
        });
    }

    public function test_admin_can_enable_and_disable_allow_negative_stock_with_audit_log(): void
    {
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => true,
        ], $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/settings', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('settings.allow_negative_stock', true);

        $this->inTenant($this->tenant, function (): void {
            $this->assertTrue(app(TenantSettings::class)->allowNegativeStock());
            $this->assertSame('1', Setting::get('allow_negative_stock'));

            $log = ActivityLog::query()
                ->where('module', 'settings')
                ->where('action', 'update')
                ->latest('id')
                ->first();

            $this->assertNotNull($log);
            $this->assertSame('allow_negative_stock', $log->properties['setting'] ?? null);
            $this->assertSame(false, $log->properties['old'] ?? null);
            $this->assertSame(true, $log->properties['new'] ?? null);
        });

        // Re-saving unchanged does not create a duplicate audit log.
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => true,
        ], $this->adminHeaders)->assertOk();

        $this->inTenant($this->tenant, function (): void {
            $this->assertSame(
                1,
                ActivityLog::query()->where('module', 'settings')->where('action', 'update')->count(),
            );
        });

        // Disabling logs the transition back to false.
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => false,
        ], $this->adminHeaders)->assertOk();

        $this->getJson('/api/v1/settings', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('settings.allow_negative_stock', false);

        $this->inTenant($this->tenant, function (): void {
            $this->assertFalse(app(TenantSettings::class)->allowNegativeStock());
            $this->assertSame('0', Setting::get('allow_negative_stock'));

            $logs = ActivityLog::query()
                ->where('module', 'settings')
                ->where('action', 'update')
                ->orderBy('id')
                ->get();

            $this->assertCount(2, $logs);
            $last = $logs->last();
            $this->assertNotNull($last);
            $this->assertSame(true, $last->properties['old'] ?? null);
            $this->assertSame(false, $last->properties['new'] ?? null);
        });
    }

    public function test_unauthenticated_and_unauthorized_requests_are_rejected(): void
    {
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => true,
        ], $this->tenantGuestHeaders($this->tenant))->assertStatus(401);

        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => true,
        ], $this->tenantHeaders($this->tenant, $cashier))->assertStatus(403);

        $this->inTenant($this->tenant, function (): void {
            $this->assertFalse(Setting::getBool('allow_negative_stock', false));
        });
    }

    public function test_non_boolean_allow_negative_stock_returns_422(): void
    {
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => 'not-a-boolean',
        ], $this->adminHeaders)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['allow_negative_stock']);

        $this->inTenant($this->tenant, function (): void {
            $this->assertFalse(Setting::getBool('allow_negative_stock', false));
        });
    }

    public function test_enabling_allow_negative_stock_allows_overselling_and_disabling_blocks_it(): void
    {
        // 1. Enable allow_negative_stock via API
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => true,
        ], $this->adminHeaders)->assertOk();

        $this->inTenant($this->tenant, function (): void {
            $store = Store::where('is_main', true)->firstOrFail();
            $customer = Customer::create(['name' => 'عميل نقدي', 'is_active' => true]);
            $item = Item::create([
                'code' => 'NEG-001',
                'name' => 'بن برازيلي',
                'unit' => 'كجم',
                'current_stock' => '5.000',
                'cost_price' => '100.000',
                'weighted_avg_cost' => '100.000',
                'selling_price' => '150.000',
                'min_stock_level' => '2.000',
                'is_active' => true,
            ]);
            StoreStock::updateOrCreate(
                ['store_id' => $store->id, 'item_id' => $item->id],
                ['quantity' => '5.000', 'min_stock' => '2.000'],
            );

            // Sell 8.000 when only 5.000 is in stock -> succeeds and balance becomes -3.000
            app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $customer->id,
                'payment_type' => 'cash',
                'store_id' => $store->id,
                'items' => [['item_id' => $item->id, 'quantity' => '8.000', 'unit_price' => '150.000']],
            ]);

            $this->assertSame('-3.000', (string) $item->fresh()->current_stock);
            $this->assertSame(
                '-3.000',
                (string) StoreStock::where('store_id', $store->id)->where('item_id', $item->id)->firstOrFail()->quantity,
            );
        });

        // 2. Disable allow_negative_stock via API
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => false,
        ], $this->adminHeaders)->assertOk();

        $this->inTenant($this->tenant, function (): void {
            $store = Store::where('is_main', true)->firstOrFail();
            $customer = Customer::firstOrFail();
            $item = Item::where('code', 'NEG-001')->firstOrFail();

            try {
                app(InvoiceService::class)->confirmInvoice([
                    'customer_id' => $customer->id,
                    'payment_type' => 'cash',
                    'store_id' => $store->id,
                    'items' => [['item_id' => $item->id, 'quantity' => '1.000', 'unit_price' => '150.000']],
                ]);
                $this->fail('Expected InsufficientStockException when allow_negative_stock is disabled.');
            } catch (InsufficientStockException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }

            $this->assertSame('-3.000', (string) $item->fresh()->current_stock);
        });
    }

    public function test_allow_negative_stock_is_isolated_per_tenant(): void
    {
        $otherTenant = $this->createTenant();

        $this->postJson('/api/v1/settings', [
            'company_name' => 'محامص سرور',
            'allow_negative_stock' => true,
        ], $this->adminHeaders)->assertOk();

        $this->getJson('/api/v1/settings', $this->tenantHeaders($otherTenant))
            ->assertOk()
            ->assertJsonPath('settings.allow_negative_stock', false);
    }
}
