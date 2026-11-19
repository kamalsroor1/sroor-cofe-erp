<?php

declare(strict_types=1);

namespace App\Actions\StockCounts;

use App\Enums\StockCountStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\StockCount;
use App\Services\ActivityLogService;
use App\Support\Database\TransactionRetry;
use Illuminate\Support\Facades\Auth;

/**
 * SHOP-02: discard an open session without touching stock; frees the store for a new count.
 */
final class CancelStockCountAction
{
    public function __construct(private readonly ActivityLogService $activityLog) {}

    public function execute(int $stockCountId, ?int $userId = null): StockCount
    {
        return TransactionRetry::run(function () use ($stockCountId, $userId): StockCount {
            $count = StockCount::query()->whereKey($stockCountId)->lockForUpdate()->firstOrFail();

            if (! $count->isOpen()) {
                throw BusinessRuleException::make('stock_counts.not_open');
            }

            $count->status = StockCountStatus::Cancelled;
            $count->open_store_id = null;
            $count->cancelled_by = Auth::id() ?? $userId;
            $count->cancelled_at = now();
            $count->save();

            $this->activityLog->log(
                module: 'inventory',
                action: 'stock_count_cancelled',
                description: (string) __('stock_counts.log_cancelled', ['number' => $count->count_number]),
                subject: $count,
                storeId: (int) $count->store_id,
            );

            return $count;
        });
    }
}
