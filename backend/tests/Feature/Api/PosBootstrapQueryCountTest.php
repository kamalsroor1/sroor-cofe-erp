<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Item;
use App\Models\PosQuickKey;
use App\Models\Tenant;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * POSB-6: /pos/bootstrap must not grow its query count with the number of quick keys
 * (keys + items + categories are eager-loaded: a fixed number of queries).
 */
final class PosBootstrapQueryCountTest extends TenantTestCase
{
    public function test_bootstrap_query_count_does_not_grow_with_quick_keys(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $this->seedCatalog($tenant, 40);

        $this->seedKeys($tenant, $storeId, 2);
        $this->countBootstrapQueries($tenant, 2); // warm-up (permission cache, lazy boot)
        [$fewTotal, $fewKeyQueries] = $this->countBootstrapQueries($tenant, 2);

        $this->seedKeys($tenant, $storeId, 60);
        [$manyTotal, $manyKeyQueries] = $this->countBootstrapQueries($tenant, 60);

        $this->assertSame($fewTotal, $manyTotal, 'bootstrap query count grew with the number of quick keys (N+1)');
        $this->assertSame(1, $fewKeyQueries, 'pos_quick_keys must be read with exactly one query');
        $this->assertSame(1, $manyKeyQueries, 'pos_quick_keys must be read with exactly one query');
    }

    private function seedCatalog(Tenant $tenant, int $count): void
    {
        $this->inTenant($tenant, function () use ($count): void {
            for ($i = 1; $i <= $count; $i++) {
                Category::query()->create([
                    'name' => 'فئة '.$i,
                    'icon' => 'x',
                    'color' => '#64748B',
                    'color_light' => '#f1f5f9',
                    'sort_order' => $i,
                    'is_active' => true,
                ]);
                Item::query()->create([
                    'code' => 'QC-'.$i,
                    'name' => 'صنف '.$i,
                    'category' => 'general',
                    'unit' => 'قطعة',
                    'cost_price' => '1.000',
                    'selling_price' => '2.250',
                    'current_stock' => '0.000',
                    'min_stock_level' => '0.000',
                    'is_active' => true,
                    'is_weighted' => $i % 2 === 0,
                ]);
            }
        });
    }

    /**
     * Replace the store's layout with $count keys, alternating item / category keys.
     */
    private function seedKeys(Tenant $tenant, int $storeId, int $count): void
    {
        $itemIds = $this->inTenant($tenant, fn (): array => Item::query()->orderBy('id')->pluck('id')->all());
        $categoryIds = $this->inTenant($tenant, fn (): array => Category::query()->orderBy('id')->pluck('id')->all());

        $keys = [];
        for ($i = 0; $i < $count; $i++) {
            $keys[] = $i % 2 === 0
                ? ['page' => intdiv($i, 60) + 1, 'position' => $i % 60, 'type' => 'item', 'item_id' => $itemIds[$i % count($itemIds)]]
                : ['page' => intdiv($i, 60) + 1, 'position' => $i % 60, 'type' => 'category', 'category_id' => $categoryIds[$i % count($categoryIds)]];
        }

        $this->putJson('/api/v1/stores/'.$storeId.'/pos/quick-keys', ['keys' => $keys], $this->tenantHeaders($tenant))
            ->assertOk();
        $this->assertSame($count, $this->inTenant($tenant, fn (): int => PosQuickKey::query()->where('store_id', $storeId)->count()));
    }

    /**
     * @return array{0: int, 1: int} [all queries during the request, queries on pos_quick_keys]
     */
    private function countBootstrapQueries(Tenant $tenant, int $expectedKeys): array
    {
        $headers = $this->tenantHeaders($tenant);

        // One listener per call; the counter stops counting once the request returns.
        $counter = new class
        {
            public bool $listening = true;

            public int $total = 0;

            public int $keyQueries = 0;
        };
        DB::listen(function (QueryExecuted $query) use ($counter): void {
            if (! $counter->listening) {
                return;
            }
            $counter->total++;
            if (str_contains($query->sql, 'pos_quick_keys')) {
                $counter->keyQueries++;
            }
        });

        $response = $this->getJson('/api/v1/pos/bootstrap', $headers);
        $counter->listening = false;

        $response->assertOk();
        $response->assertJsonCount($expectedKeys, 'data.quick_keys');

        return [$counter->total, $counter->keyQueries];
    }
}
