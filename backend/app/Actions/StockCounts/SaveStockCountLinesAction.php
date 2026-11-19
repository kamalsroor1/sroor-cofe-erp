<?php

declare(strict_types=1);

namespace App\Actions\StockCounts;

use App\DTOs\StockCounts\SaveStockCountLinesDTO;
use App\Enums\StockCountStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Item;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StoreStock;
use App\Support\Database\TransactionRetry;
use Illuminate\Support\Facades\Auth;

/**
 * SHOP-02: save counted quantities of an open session (a draft; counting can resume later).
 * An item missing from the snapshot (e.g. scanned outside the counted category) is added with
 * its current store balance as snapshot. Stock is never touched here: only posting does that.
 */
final class SaveStockCountLinesAction
{
    public function execute(SaveStockCountLinesDTO $dto): StockCount
    {
        return TransactionRetry::run(function () use ($dto): StockCount {
            $count = StockCount::query()->whereKey($dto->stockCountId)->lockForUpdate()->firstOrFail();

            if (! $count->isOpen()) {
                throw BusinessRuleException::make('stock_counts.not_open');
            }

            $actorId = Auth::id() ?? $dto->userId;
            $now = now();
            $itemIds = array_values(array_unique(array_column($dto->lines, 'item_id')));

            $existing = StockCountLine::query()
                ->where('stock_count_id', $count->id)
                ->whereIn('item_id', $itemIds)
                ->get()
                ->keyBy('item_id');

            foreach ($dto->lines as $input) {
                $line = $existing->get($input['item_id']);

                if (! $line instanceof StockCountLine) {
                    $line = $this->newLine($count, $input['item_id']);
                    $existing->put($input['item_id'], $line);
                }

                $line->counted_qty = $input['counted_qty'];
                $line->counted_by = $input['counted_qty'] === null ? null : $actorId;
                $line->counted_at = $input['counted_qty'] === null ? null : $now;
                $line->save();
            }

            $anyCounted = StockCountLine::query()
                ->where('stock_count_id', $count->id)
                ->whereNotNull('counted_qty')
                ->exists();

            $count->status = $anyCounted ? StockCountStatus::Counting : StockCountStatus::Draft;
            $count->save();

            return $count;
        });
    }

    private function newLine(StockCount $count, int $itemId): StockCountLine
    {
        $item = Item::query()->findOrFail($itemId);
        $storeQty = StoreStock::query()
            ->where('store_id', $count->store_id)
            ->where('item_id', $itemId)
            ->value('quantity');

        return new StockCountLine([
            'stock_count_id' => $count->id,
            'item_id' => $itemId,
            'system_qty' => bcadd((string) ($storeQty ?? '0'), '0', 3),
            'unit_cost' => bcadd((string) ($item->cost_price ?? '0'), '0', 3),
        ]);
    }
}
