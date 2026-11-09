<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Item;
use App\Models\Setting;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * POS-04/05: GET /api/v1/pos/items — lean paged grid, real per-category counters, store stock.
 */
final class PosItemsApiTest extends TenantTestCase
{
    private const URL = '/api/v1/pos/items';

    private function category(Tenant $tenant, string $name): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Category::query()->create([
            'name' => $name, 'icon' => 'x', 'color' => '#64748B', 'color_light' => '#f1f5f9',
            'sort_order' => 1, 'is_active' => true,
        ])->id);
    }

    private function item(Tenant $tenant, string $code, array $extra = []): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Item::query()->create(array_merge([
            'code' => $code,
            'name' => 'صنف '.$code,
            'category' => 'general',
            'unit' => 'قطعة',
            'cost_price' => '10.000',
            'selling_price' => '32.500',
            'current_stock' => '7.000',
            'min_stock_level' => '0.000',
            'is_active' => true,
        ], $extra))->id);
    }

    private function bulk(Tenant $tenant, int $count, ?int $categoryId = null): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->item($tenant, sprintf('BULK-%03d', $i), ['name' => 'منتج '.$i, 'category_id' => $categoryId]);
        }
    }

    public function test_paginates_with_default_and_clamped_per_page_and_returns_counts(): void
    {
        $tenant = $this->createTenant();
        $a = $this->category($tenant, 'مشروبات');
        $b = $this->category($tenant, 'حلويات');
        $this->bulk($tenant, 5, $a);
        $this->item($tenant, 'SW-1', ['category_id' => $b]);
        $this->item($tenant, 'SW-2', ['category_id' => $b]);
        $this->item($tenant, 'NOCAT');
        $this->item($tenant, 'OFF', ['is_active' => false, 'category_id' => $a]);
        $h = $this->tenantHeaders($tenant);

        $r = $this->getJson(self::URL.'?per_page=3&sort=code', $h)->assertOk();
        $r->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.per_page', 3)
            ->assertJsonPath('meta.total', 8)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.counts.total', 8);
        $counts = collect($r->json('meta.counts.categories'))->mapWithKeys(fn (array $c): array => [(string) $c['category_id'] => $c['count']])->all();
        $this->assertSame(5, $counts[(string) $a]);
        $this->assertSame(2, $counts[(string) $b]);
        $this->assertSame(1, $counts['']);
        $this->assertCount(3, $counts);

        // counters ignore the category filter and the page
        $this->getJson(self::URL.'?category_id='.$b, $h)->assertOk()
            ->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2)->assertJsonPath('meta.counts.total', 8);

        $this->getJson(self::URL.'?per_page=5000', $h)->assertOk()->assertJsonPath('meta.per_page', 200);
        $this->getJson(self::URL.'?per_page=0', $h)->assertOk()->assertJsonPath('meta.per_page', 1);
        $this->getJson(self::URL, $h)->assertOk()->assertJsonPath('meta.per_page', 60);
        $this->getJson(self::URL.'?sort=bogus', $h)->assertStatus(422);
    }

    public function test_search_matches_code_exact_or_prefix_and_name_contains(): void
    {
        $tenant = $this->createTenant();
        $this->item($tenant, '1234', ['name' => 'A']);
        $this->item($tenant, '12345', ['name' => 'B']);
        $this->item($tenant, '991234', ['name' => 'C']);
        $this->item($tenant, 'X1', ['name' => 'قهوة تركي']);
        $this->item($tenant, 'X2', ['name' => '100%_شاي']);
        $h = $this->tenantHeaders($tenant);

        $codes = fn (string $q): array => collect($this->getJson(self::URL.'?search='.urlencode($q).'&sort=code', $h)->assertOk()->json('data'))->pluck('code')->all();

        $this->assertSame(['1234', '12345'], $codes('1234'));
        $this->assertSame(['X1'], $codes('تركي'));
        $this->assertSame(['X2'], $codes('0%_'));
        $this->assertSame([], $codes('%%'));
        $this->assertSame([], $codes("' OR 1=1 --"));
    }

    public function test_only_in_stock_and_weighted_filters_use_the_active_store_stock(): void
    {
        $tenant = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenant)->id;
        $storeB = (int) $this->inTenant($tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع B', 'code' => 'B-01', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
        ])->id);
        $in = $this->item($tenant, 'IN', ['is_weighted' => true]);
        $out = $this->item($tenant, 'OUT');
        $this->inTenant($tenant, function () use ($in, $out, $storeA, $storeB): void {
            DB::table('store_stocks')->insert([
                ['store_id' => $storeA, 'item_id' => $in, 'quantity' => '3.000', 'created_at' => now(), 'updated_at' => now()],
                ['store_id' => $storeB, 'item_id' => $in, 'quantity' => '9.000', 'created_at' => now(), 'updated_at' => now()],
                ['store_id' => $storeA, 'item_id' => $out, 'quantity' => '0.000', 'created_at' => now(), 'updated_at' => now()],
                ['store_id' => $storeB, 'item_id' => $out, 'quantity' => '4.000', 'created_at' => now(), 'updated_at' => now()],
            ]);
        });

        $this->getJson(self::URL.'?only_in_stock=1', $this->tenantHeaders($tenant, null, $storeA))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'IN')
            ->assertJsonPath('data.0.current_stock', 3)->assertJsonPath('meta.counts.total', 1);
        $this->getJson(self::URL.'?only_in_stock=1&sort=code', $this->tenantHeaders($tenant, null, $storeB))
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.current_stock', 9);
        $this->getJson(self::URL.'?is_weighted=1', $this->tenantHeaders($tenant, null, $storeB))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.is_weighted', true);
    }

    public function test_sorts_by_top_sellers_and_hides_cost_and_image_by_default(): void
    {
        $tenant = $this->createTenant();
        $this->item($tenant, 'LOW', ['pos_sales_count' => 1, 'image' => 'items/a.png']);
        $this->item($tenant, 'TOP', ['pos_sales_count' => 50, 'image' => 'items/b.png']);

        $this->getJson(self::URL.'?sort=pos_sales_count', $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonPath('data.0.code', 'TOP')
            ->assertJsonPath('data.0.cost_price', 10)
            ->assertJsonMissingPath('data.0.image');

        $this->inTenant($tenant, fn () => Setting::query()->create(['key' => 'pos_show_item_images', 'value' => '1']));
        $this->inTenant($tenant, fn () => Cache::forget(Setting::getCacheKey()));
        $this->getJson(self::URL.'?sort=pos_sales_count', $this->tenantHeaders($tenant))
            ->assertOk()->assertJsonPath('data.0.image', 'items/b.png');
    }

    public function test_cost_is_hidden_from_cashiers(): void
    {
        $tenant = $this->createTenant();
        $this->item($tenant, 'C1');
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson(self::URL, $this->tenantHeaders($tenant, $cashier))
            ->assertOk()->assertJsonPath('data.0.cost_price', null)->assertJsonPath('data.0.price_retail', 32.5);
    }

    public function test_requires_authentication_pos_access_and_store_access(): void
    {
        $tenant = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenant)->id;
        $storeB = (int) $this->inTenant($tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع B', 'code' => 'B-01', 'type' => 'retail', 'is_main' => false, 'is_active' => true,
        ])->id);

        $this->getJson(self::URL, ['Accept' => 'application/json', 'X-Tenant' => (string) $tenant->getTenantKey()])->assertStatus(401);

        $nobody = $this->createTenantUser($tenant);
        $this->getJson(self::URL, $this->tenantHeaders($tenant, $nobody))->assertStatus(403);

        /** @var User $cashier */
        $cashier = $this->createTenantUser($tenant, 'cashier', [], ['default_store_id' => $storeA]);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($cashier->id)->stores()->sync([$storeA]));
        $this->getJson(self::URL, $this->tenantHeaders($tenant, $cashier, $storeA))->assertOk();
        $this->getJson(self::URL, $this->tenantHeaders($tenant, $cashier, $storeB))->assertStatus(403);
    }

    public function test_items_are_isolated_between_tenants(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $this->item($tenantA, 'ONLY-A');

        $this->getJson(self::URL, $this->tenantHeaders($tenantB))
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.counts.total', 0);
        $this->getJson(self::URL, $this->tenantHeaders($tenantA))->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_query_count_does_not_grow_with_the_number_of_items(): void
    {
        $tenant = $this->createTenant();
        $cat = $this->category($tenant, 'مشروبات');
        $this->bulk($tenant, 10, $cat);
        $this->queryCount($tenant); // warm-up (permission cache, settings cache)
        $few = $this->queryCount($tenant);

        for ($i = 11; $i <= 50; $i++) {
            $this->item($tenant, sprintf('BULK-%03d', $i), ['category_id' => $cat]);
        }
        $many = $this->queryCount($tenant);

        $this->assertSame($few, $many, 'GET /pos/items query count grew with the number of items (N+1)');
    }

    private function queryCount(Tenant $tenant): int
    {
        $headers = $this->tenantHeaders($tenant);
        $counter = new class
        {
            public bool $on = true;

            public int $n = 0;
        };
        DB::listen(function (QueryExecuted $q) use ($counter): void {
            if ($counter->on) {
                $counter->n++;
            }
        });
        $response = $this->getJson(self::URL.'?per_page=200', $headers);
        $counter->on = false;
        $response->assertOk();

        return $counter->n;
    }

    public function test_min_price_value_is_hidden_from_cashiers_but_flagged(): void
    {
        $tenant = $this->createTenant();
        $this->item($tenant, 'FLOOR', ['min_selling_price' => '20.000']);
        $this->item($tenant, 'FREE');
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $viewer = $this->createTenantUser($tenant, 'cashier', ['items.view_cost']);

        $rows = fn (User $u): array => collect($this->getJson(self::URL.'?sort=code', $this->tenantHeaders($tenant, $u))->assertOk()->json('data'))->keyBy('code')->all();

        $c = $rows($cashier);
        $this->assertNull($c['FLOOR']['min_selling_price']);
        $this->assertTrue($c['FLOOR']['has_min_price']);
        $this->assertFalse($c['FREE']['has_min_price']);

        $v = $rows($viewer);
        $this->assertEquals(20, $v['FLOOR']['min_selling_price']);
        $this->assertTrue($v['FLOOR']['has_min_price']);
    }

    public function test_store_all_uses_the_default_store_for_admin_and_is_checked_for_cashier(): void
    {
        $tenant = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenant)->id;
        $id = $this->item($tenant, 'ALL1');
        $this->inTenant($tenant, fn () => DB::table('store_stocks')->insert([
            'store_id' => $storeA, 'item_id' => $id, 'quantity' => '5.000', 'created_at' => now(), 'updated_at' => now(),
        ]));

        $headers = $this->tenantHeaders($tenant);
        $headers['X-Store-Id'] = 'all';
        $this->getJson(self::URL, $headers)->assertOk()->assertJsonPath('data.0.current_stock', 5);

        /** @var User $cashier */
        $cashier = $this->createTenantUser($tenant, 'cashier', [], ['default_store_id' => $storeA]);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($cashier->id)->stores()->sync([$storeA]));
        $headers = $this->tenantHeaders($tenant, $cashier);
        $headers['X-Store-Id'] = 'all';
        // `all` is only for users who may see every store; a single-store cashier is refused.
        $this->getJson(self::URL, $headers)->assertStatus(403);
    }

    public function test_invalid_query_params_are_rejected(): void
    {
        $tenant = $this->createTenant();
        $h = $this->tenantHeaders($tenant);

        $this->getJson(self::URL.'?sort=evil', $h)->assertStatus(422)->assertJsonValidationErrors('sort');
        $this->getJson(self::URL.'?direction=x', $h)->assertStatus(422)->assertJsonValidationErrors('direction');
        $this->getJson(self::URL.'?per_page=abc', $h)->assertStatus(422)->assertJsonValidationErrors('per_page');
    }

    public function test_soft_deleted_stock_rows_and_categories_are_ignored(): void
    {
        $tenant = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenant)->id;
        $cat = $this->category($tenant, 'محذوفة');
        $id = $this->item($tenant, 'SD1', ['category_id' => $cat, 'current_stock' => '7.000']);
        $this->inTenant($tenant, function () use ($id, $storeA, $cat): void {
            DB::table('store_stocks')->insert([
                'store_id' => $storeA, 'item_id' => $id, 'quantity' => '99.000', 'deleted_at' => now(),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            Category::query()->whereKey($cat)->delete();
        });

        $this->getJson(self::URL, $this->tenantHeaders($tenant))->assertOk()
            ->assertJsonPath('data.0.current_stock', 7)
            ->assertJsonPath('data.0.category_color', null);
    }

    public function test_bootstrap_can_skip_the_item_dump_and_still_works_by_default(): void
    {
        $tenant = $this->createTenant();
        $this->item($tenant, 'B1');
        $h = $this->tenantHeaders($tenant);

        $this->getJson('/api/v1/pos/bootstrap', $h)->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson('/api/v1/pos/bootstrap?include_items=0', $h)->assertOk()
            ->assertJsonCount(0, 'data.items')->assertJsonStructure(['data' => ['categories', 'active_store', 'pos_settings', 'quick_keys']]);
    }
}
