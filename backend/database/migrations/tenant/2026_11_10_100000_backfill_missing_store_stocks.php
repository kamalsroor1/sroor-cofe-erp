<?php

use App\Support\Database\OnlineMigration;
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
 *
 * MIG-02: chunked, one transaction per chunk, items then store_stocks locked FOR UPDATE, relative
 * writes only, and each residual moved to the main store is recorded as a stock_adjustment_in
 * movement (note 'store_stocks_backfill'). Concurrent sales therefore cannot be lost.
 */
return new class extends Migration
{
    private const CHUNK = 500;

    public function up(): void
    {
        if (! Schema::hasTable('stock_movements') || ! Schema::hasTable('store_stocks') || ! Schema::hasTable('stores') || ! Schema::hasTable('items')) {
            return;
        }

        $storeIds = DB::table('stores')->whereNull('deleted_at')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();
        if ($storeIds === []) {
            return;
        }

        $mainId = DB::table('stores')->whereNull('deleted_at')->where('is_main', true)->orderBy('id')->value('id');
        $mainId = $mainId !== null ? (int) $mainId : $storeIds[0];

        $overAllocated = 0;
        $lastId = 0;

        // MIG-02: the shop keeps selling during a deploy, so every chunk is its own short transaction
        // that locks the items first and then their store_stocks rows (the sale path's lock order),
        // re-reads everything under those locks and only ever applies RELATIVE changes
        // (quantity = quantity + residual). A sale cannot slip between the read and the write.
        while (true) {
            $ids = DB::table('items')->whereNull('deleted_at')->where('id', '>', $lastId)
                ->orderBy('id')->limit(self::CHUNK)->pluck('id')->map(fn ($id): int => (int) $id)->all();
            if ($ids === []) {
                break;
            }
            $lastId = end($ids);

            $over = OnlineMigration::withShortLockTimeout(fn (): int => DB::transaction(function () use ($ids, $storeIds, $mainId): int {
                $over = 0;
                $items = DB::table('items')->whereIn('id', $ids)->whereNull('deleted_at')
                    ->orderBy('id')->lockForUpdate()
                    ->get(['id', 'current_stock', 'min_stock_level', 'cost_price']);

                $existing = DB::table('store_stocks')
                    ->whereIn('item_id', $ids)->orderBy('id')->lockForUpdate()
                    ->get(['id', 'item_id', 'store_id', 'quantity', 'deleted_at'])
                    ->groupBy('item_id');

                $now = now();
                $inserts = [];
                $movements = [];

                foreach ($items as $item) {
                    $rows = $existing->get($item->id, collect());
                    $sum = '0.000';
                    foreach ($rows->whereNull('deleted_at') as $row) {
                        $sum = bcadd($sum, (string) $row->quantity, 3);
                    }

                    $current = bcadd((string) $item->current_stock, '0', 3);
                    if (bccomp($sum, $current, 3) > 0) {
                        $over++;
                        Log::warning('store_stocks backfill: SUM(store_stocks) exceeds items.current_stock', [
                            'item_id' => $item->id, 'sum' => $sum, 'current_stock' => $current,
                        ]);
                    }

                    $residual = bccomp($current, $sum, 3) > 0 ? bcsub($current, $sum, 3) : '0.000';
                    $byStore = $rows->keyBy('store_id');
                    $moved = false;

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
                            $moved = $moved || ($isMain && bccomp($residual, '0.000', 3) > 0);
                        } elseif ($isMain && $row->deleted_at === null && bccomp($residual, '0.000', 3) > 0) {
                            DB::update(
                                'update store_stocks set quantity = quantity + ?, updated_at = ? where id = ?',
                                [$residual, $now, $row->id]
                            );
                            $moved = true;
                        }
                    }

                    if ($moved) {
                        // The difference shows on the item card instead of moving silently.
                        $movements[] = [
                            'item_id' => $item->id,
                            'store_id' => $mainId,
                            'movement_type' => 'stock_adjustment_in',
                            'quantity' => $residual,
                            'stock_before' => $sum,
                            'stock_after' => $current,
                            'unit_cost' => (string) ($item->cost_price ?? '0.000'),
                            'source_type' => 'App\\Models\\Item',
                            'source_id' => $item->id,
                            'document_number' => null,
                            'user_id' => null,
                            'notes' => 'store_stocks_backfill',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                foreach (array_chunk($inserts, self::CHUNK) as $batch) {
                    DB::table('store_stocks')->insertOrIgnore($batch);
                }
                foreach (array_chunk($movements, self::CHUNK) as $batch) {
                    DB::table('stock_movements')->insert($batch);
                }

                return $over;
            }));
            $overAllocated += $over;
        }

        if ($overAllocated > 0) {
            Log::warning("store_stocks backfill: {$overAllocated} item(s) have SUM(store_stocks) above current_stock; left untouched.");
        }
    }

    public function down(): void
    {
        // Data backfill; intentionally not reversed (rows may now carry history).
    }
};
