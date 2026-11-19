<?php

declare(strict_types=1);

namespace App\Actions\StockCounts;

use App\DTOs\StockCounts\PostStockCountDTO;
use App\Enums\StockCountStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Services\ActivityLogService;
use App\Services\StockService;
use App\Support\Database\TransactionRetry;
use Illuminate\Support\Facades\Auth;

/**
 * SHOP-02: post a stocktake session. One transaction, global lock order (the session row, then
 * items by id, then store_stocks): every counted line sets the store balance to the counted
 * quantity through StockService::applyCountedQuantity(), which computes the difference against
 * the LIVE balance at posting time and writes a `stock_count_in` / `stock_count_out` movement.
 * A failure anywhere rolls back every line. A posted session is immutable; posting it again is a 422.
 */
final class PostStockCountAction
{
    public function __construct(
        private readonly StockService $stockService,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(PostStockCountDTO $dto): StockCount
    {
        return TransactionRetry::run(function () use ($dto): StockCount {
            $count = StockCount::query()->whereKey($dto->stockCountId)->lockForUpdate()->firstOrFail();

            if ($count->status === StockCountStatus::Posted) {
                throw BusinessRuleException::make('stock_counts.already_posted');
            }

            if (! $count->isOpen()) {
                throw BusinessRuleException::make('stock_counts.not_open');
            }

            $lines = StockCountLine::query()
                ->where('stock_count_id', $count->id)
                ->when(! $dto->zeroUncounted, fn ($q) => $q->whereNotNull('counted_qty'))
                ->orderBy('item_id')
                ->get();

            if ($lines->isEmpty()) {
                throw BusinessRuleException::make('stock_counts.nothing_counted');
            }

            $storeId = (int) $count->store_id;
            $actorId = Auth::id() ?? $dto->userId;
            $notes = (string) __('stock_counts.movement_note', ['number' => $count->count_number]);

            $this->stockService->lockStockRows($lines->pluck('item_id')->all(), [$storeId]);

            $adjusted = 0;
            $netQty = '0.000';
            foreach ($lines as $line) {
                $counted = $line->counted_qty === null ? '0.000' : (string) $line->counted_qty;

                $result = $this->stockService->applyCountedQuantity(
                    (int) $line->item_id,
                    $storeId,
                    $counted,
                    $count,
                    $count->count_number,
                    $notes,
                    $actorId,
                );

                $line->counted_qty = $counted;
                $line->system_qty_at_post = $result['system_qty'];
                $line->difference = $result['difference'];
                $line->unit_cost = $result['unit_cost'];
                $line->stock_movement_id = $result['movement']?->id;
                $line->save();

                if ($result['movement'] !== null) {
                    $adjusted++;
                    $netQty = bcadd($netQty, $result['difference'], 3);
                }
            }

            $count->status = StockCountStatus::Posted;
            $count->open_store_id = null;
            $count->posted_by = $actorId;
            $count->posted_at = now();
            $count->save();

            $this->activityLog->log(
                module: 'inventory',
                action: 'stock_count_posted',
                description: (string) __('stock_counts.log_posted', ['number' => $count->count_number, 'adjusted' => $adjusted]),
                subject: $count,
                properties: ['lines' => $lines->count(), 'adjusted' => $adjusted, 'net_quantity' => $netQty, 'zero_uncounted' => $dto->zeroUncounted],
                storeId: $storeId,
            );

            return $count;
        });
    }
}
