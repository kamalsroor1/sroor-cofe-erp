<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\DashboardCache;
use App\Support\TenantClock;
use Carbon\CarbonInterval as Duration;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store as CacheStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
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

        // PERF-02: the owner accepts up to ~1 minute of staleness (TTL 60 s).
        $this->travel(DashboardCache::TTL_SECONDS - 1)->seconds();
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.today_sales', '100.000');

        $this->travel(2)->seconds();
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
            // ROLE-01 (owner 2026-11-12): without reports.view only the caller's own shift; this
            // cashier has none, so no store-wide sales either (was '100.000' before ROLE-01).
            ->assertJsonPath('data.scope', 'own_shift')
            ->assertJsonPath('data.metrics.today_sales', '0.000')
            ->assertJsonPath('data.metrics.monthly_sales', null);
    }

    public function test_the_cashier_cache_does_not_hide_figures_from_a_manager(): void
    {
        $this->sale($this->tenant, $this->storeA, $this->customerId, '100.000', '60.000');
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);

        $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier, $this->storeA))->assertOk()
            ->assertJsonPath('data.metrics.monthly_gross_profit', null);
        $this->dashboard($this->storeA)->assertJsonPath('data.metrics.monthly_gross_profit', '40.000');
    }

    public function test_two_viewer_scopes_never_share_a_cache_entry(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $key = fn (?string $viewer): string => DashboardCache::key($this->storeA, false, false, '2026-11-12', 'ar', $viewer);

            $keys = [$key(null), $key('u1'), $key('u2'), $key('u1.shift7')];
            $this->assertCount(4, array_unique($keys), 'every viewer scope has its own key');

            // Different scopes compute and keep their own figures.
            $this->assertSame(['scope' => 'shared'], DashboardCache::remember($key(null), fn (): array => ['scope' => 'shared']));
            $this->assertSame(['scope' => 'u1'], DashboardCache::remember($key('u1'), fn (): array => ['scope' => 'u1']));
            $this->assertSame(['scope' => 'shared'], DashboardCache::remember($key(null), fn (): array => ['scope' => 'recomputed']));

            // A viewer token cannot smuggle a separator to forge another scope's key.
            $this->expectException(\InvalidArgumentException::class);
            $key('u1:sall');
        });
    }

    public function test_the_dashboard_of_a_user_without_reports_view_is_never_cached(): void
    {
        $this->sale($this->tenant, $this->storeA, $this->customerId, '100.000', '60.000');
        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);

        $response = $this->getJson('/api/v1/dashboard', $this->tenantHeaders($this->tenant, $cashier, $this->storeA))->assertOk()
            ->assertJsonPath('data.scope', 'own_shift');
        $locale = (string) $response->headers->get('Content-Language');

        // ROLE-01: the own-shift payload is built per user and never stored, so it can neither
        // seed the shared entries nor be served from them (was cached under f0 before ROLE-01).
        $this->inTenant($this->tenant, function () use ($locale): void {
            $today = app(TenantClock::class)->today();

            $this->assertNull(Cache::get(DashboardCache::key($this->storeA, false, false, $today, $locale)), 'the cashier view is not cached');
            $this->assertNull(Cache::get(DashboardCache::key($this->storeA, true, false, $today, $locale)), 'no financial copy was built for the cashier');
        });
    }

    public function test_a_request_still_answers_correct_figures_when_another_worker_holds_the_lock(): void
    {
        $this->sale($this->tenant, $this->storeA, $this->customerId, '100.000', '60.000');

        // Another worker is computing this dashboard (cold key, lock taken) and never finishes.
        $this->inTenant($this->tenant, function (): void {
            $today = app(TenantClock::class)->today();
            foreach (['ar', 'en'] as $locale) {
                $this->assertTrue(Cache::lock(DashboardCache::key($this->storeA, true, true, $today, $locale).':lock', DashboardCache::LOCK_SECONDS)->get());
            }
        });

        // The wait sleeps through Illuminate\Support\Sleep: faked, and kept in step with the clock.
        Sleep::fake(syncWithCarbon: true);
        $waitedMicroseconds = 0;
        Sleep::whenFakingSleep(function (Duration $duration) use (&$waitedMicroseconds): void {
            $waitedMicroseconds += (int) $duration->totalMicroseconds;
        });

        $this->dashboard($this->storeA)
            ->assertJsonPath('data.metrics.today_sales', '100.000')
            ->assertJsonPath('data.metrics.monthly_gross_profit', '40.000');

        $this->assertGreaterThan(0, $waitedMicroseconds, 'the request waited for the other worker first');
        $this->assertLessThanOrEqual(DashboardCache::LOCK_WAIT_SECONDS * 1_000_000, $waitedMicroseconds, 'never longer than LOCK_WAIT_SECONDS');
        $this->assertSame(2, DashboardCache::LOCK_WAIT_SECONDS);
    }

    public function test_a_failing_computation_releases_the_lock_and_caches_nothing(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $key = DashboardCache::key($this->storeA, true, true, '2026-11-12', 'ar');

            try {
                DashboardCache::remember($key, fn (): array => throw new \RuntimeException('aggregate failed'));
                $this->fail('the exception must reach the caller');
            } catch (\RuntimeException $e) {
                $this->assertSame('aggregate failed', $e->getMessage());
            }

            $this->assertNull(Cache::get($key), 'nothing cached');
            $lock = Cache::lock($key.':lock', DashboardCache::LOCK_SECONDS);
            $this->assertTrue($lock->get(), 'the lock was released');
            $lock->release();

            $this->assertSame(['ok' => true], DashboardCache::remember($key, fn (): array => ['ok' => true]));
            $this->assertSame(['ok' => true], Cache::get($key));
        });
    }

    public function test_a_cache_store_without_locks_falls_back_to_a_plain_remember(): void
    {
        Cache::extend('perf02-nolock', fn ($app) => Cache::repository(new class implements CacheStore
        {
            private ArrayStore $inner;

            public function __construct()
            {
                $this->inner = new ArrayStore;
            }

            public function get($key)
            {
                return $this->inner->get($key);
            }

            public function many(array $keys)
            {
                return $this->inner->many($keys);
            }

            public function put($key, $value, $seconds)
            {
                return $this->inner->put($key, $value, $seconds);
            }

            public function putMany(array $values, $seconds)
            {
                return $this->inner->putMany($values, $seconds);
            }

            public function increment($key, $value = 1)
            {
                return $this->inner->increment($key, $value);
            }

            public function decrement($key, $value = 1)
            {
                return $this->inner->decrement($key, $value);
            }

            public function forever($key, $value)
            {
                return $this->inner->forever($key, $value);
            }

            public function touch($key, $seconds)
            {
                return $this->inner->touch($key, $seconds);
            }

            public function forget($key)
            {
                return $this->inner->forget($key);
            }

            public function flush()
            {
                return $this->inner->flush();
            }

            public function getPrefix()
            {
                return '';
            }
        }));
        config(['cache.stores.perf02-nolock' => ['driver' => 'perf02-nolock']]);
        $default = Cache::getDefaultDriver();
        Cache::setDefaultDriver('perf02-nolock');

        try {
            $this->assertNotInstanceOf(LockProvider::class, Cache::getStore());

            $computed = 0;
            $compute = function () use (&$computed): array {
                $computed++;

                return ['n' => $computed];
            };

            $this->assertSame(['n' => 1], DashboardCache::remember('perf02-nolock-key', $compute));
            $this->assertSame(['n' => 1], DashboardCache::remember('perf02-nolock-key', $compute), 'served from the cache');
            $this->assertSame(1, $computed);
        } finally {
            Cache::setDefaultDriver($default);
        }
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
