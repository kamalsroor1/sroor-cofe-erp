<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * BE-04: stores created after their items (and vice versa) have no store_stocks rows, so
 * the per-store stock check was silently skipped. Adds the missing (store, item) rows.
 *
 * Items that predate store_stocks can hold real items.current_stock with no rows. Per item
 * the residual (current_stock - SUM of existing non-deleted rows, clamped at 0, bcmath) goes
 * to the main store (is_main, fallback first store, like Store::getMainStore); every other
 * missing store gets 0.000. Items whose rows already sum above current_stock are logged and
 * left alone. Idempotent: after one run SUM == current_stock, so the residual is 0 and
 * existing rows are never lowered or overwritten with 0.
 */
return new class extends Migration
{
    private const CHUNK = 500;

    public function up(): void
    {
        if (! Schema::hasTable('store_stocks') || ! Schema::hasTable('stores') || ! Schema::hasTable('items')) {
            return;
        }

        $storeIds = DB::table('stores')->whereNull('deleted_at')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($storeIds === []) {
            return;
        }

        $mainId = DB::table('stores')->whereNull('deleted_at')->where('is_main', true)->orderBy('id')->value('id');
        $mainId = $mainId !== null ? (int) $mainId : $storeIds[0];

        $overAllocated = 0;

        DB::table('items')->whereNull('deleted_at')->select(['id', 'current_stock', 'min_stock_level'])->orderBy('id')
            ->chunkById(self::CHUNK, function ($items) use ($storeIds, $mainId, &$overAllocated): void {
                $existing = DB::table('store_stocks')
                    ->whereIn('item_id', $items->pluck('id')->all())
                    ->get(['id', 'item_id', 'store_id', 'quantity', 'deleted_at'])
                    ->groupBy('item_id');

                $now = now();
                $inserts = [];

                foreach ($items as $item) {
                    $rows = $existing->get($item->id, collect());
                    $sum = '0.000';
                    foreach ($rows->whereNull('deleted_at') as $row) {
                        $sum = bcadd($sum, (string) $row->quantity, 3);
                    }

                    $current = bcadd((string) $item->current_stock, '0', 3);
                    if (bccomp($sum, $current, 3) > 0) {
                        $overAllocated++;
                        Log::warning('store_stocks backfill: SUM(store_stocks) exceeds items.current_stock', [
                            'item_id' => $item->id, 'sum' => $sum, 'current_stock' => $current,
                        ]);
                    }

                    $residual = bccomp($current, $sum, 3) > 0 ? bcsub($current, $sum, 3) : '0.000';
                    $byStore = $rows->keyBy('store_id');

                    foreach ($storeIds as $storeId) {
                        $isMain = $storeId === $mainId;
                        $row = $byStore->get($storeId);

                        if ($row === null) {
                            $inserts[] = [
                                'store_id' => $storeId,
                                'item_id' => $item->id,
                                'quantity' => $isMain ? $residual : '0.000',
                                'min_stock' => (string) $item->min_stock_level,
                                'custom_selling_price' => null,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        } elseif ($isMain && $row->deleted_at === null && bccomp($residual, '0.000', 3) > 0) {
                            DB::table('store_stocks')->where('id', $row->id)->update([
                                'quantity' => bcadd((string) $row->quantity, $residual, 3),
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }

                foreach (array_chunk($inserts, self::CHUNK) as $batch) {
                    DB::table('store_stocks')->insertOrIgnore($batch);
                }
            });

        if ($overAllocated > 0) {
            Log::warning("store_stocks backfill: {$overAllocated} item(s) have SUM(store_stocks) above current_stock; left untouched.");
        }
    }

    public function down(): void
    {
        // Data backfill; intentionally not reversed (rows may now carry history).
    }
};
