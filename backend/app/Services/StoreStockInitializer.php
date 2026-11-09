<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the (store, item) grid of `store_stocks` complete (BE-04).
 *
 * Every active store must have a row (qty 0.000) for every item, otherwise the per-store
 * stock check has nothing to compare against. Insert-only and idempotent: existing rows
 * (including soft-deleted ones) are never touched, thanks to the unique (store_id, item_id)
 * index plus insertOrIgnore. Callers run it inside their own transaction.
 */
final class StoreStockInitializer
{
    private const CHUNK = 500;

    /** Create zero-quantity rows for a new store across every existing item. */
    public function seedStore(int $storeId): int
    {
        $created = 0;

        Item::query()
            ->select(['id', 'min_stock_level'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($items) use ($storeId, &$created): void {
                $created += $this->insertMissing(
                    $items->map(fn (Item $item): array => [$storeId, (int) $item->id, (string) $item->min_stock_level])->all()
                );
            });

        return $created;
    }

    /** Create zero-quantity rows for a new item across every existing store. */
    public function seedItem(int $itemId, string $minStock = '0.000'): int
    {
        $created = 0;

        Store::query()
            ->select(['id'])
            ->orderBy('id')
            ->chunkById(self::CHUNK, function ($stores) use ($itemId, $minStock, &$created): void {
                $created += $this->insertMissing(
                    $stores->map(fn (Store $store): array => [(int) $store->id, $itemId, $minStock])->all()
                );
            });

        return $created;
    }

    /**
     * @param  list<array{0: int, 1: int, 2: string}>  $pairs  [store_id, item_id, min_stock]
     */
    private function insertMissing(array $pairs): int
    {
        if ($pairs === []) {
            return 0;
        }

        $now = now();
        $rows = array_map(static fn (array $p): array => [
            'store_id' => $p[0],
            'item_id' => $p[1],
            'quantity' => '0.000',
            'min_stock' => $p[2],
            'custom_selling_price' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], $pairs);

        return (int) DB::table((new StoreStock)->getTable())->insertOrIgnore($rows);
    }
}
