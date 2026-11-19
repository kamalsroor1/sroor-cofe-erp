<?php

declare(strict_types=1);

namespace App\Actions\StockCounts;

use App\DTOs\StockCounts\StartStockCountDTO;
use App\Enums\StockCountStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Item;
use App\Models\StockCount;
use App\Services\ActivityLogService;
use App\Services\DocumentNumberService;
use App\Support\Database\TransactionRetry;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * SHOP-02: open a stocktake session for one store and snapshot the store balance of every
 * active item (or of one category). One open session per store: the `open_store_id` unique
 * index settles two simultaneous starts, the loser gets the same 422 as a plain duplicate.
 */
final class StartStockCountAction
{
    private const INSERT_CHUNK = 500;

    public function __construct(
        private readonly DocumentNumberService $documentNumbers,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function execute(StartStockCountDTO $dto): StockCount
    {
        $this->documentNumbers->prepareDated('CNT', StockCount::class, 'count_number');

        try {
            return TransactionRetry::run(function () use ($dto): StockCount {
                if (StockCount::query()->where('open_store_id', $dto->storeId)->exists()) {
                    throw BusinessRuleException::make('stock_counts.already_open');
                }

                $rows = Item::query()
                    ->active()
                    ->when($dto->categoryId !== null, fn ($q) => $q->where('items.category_id', $dto->categoryId))
                    ->leftJoin('store_stocks as ss', function (JoinClause $join) use ($dto): void {
                        $join->on('ss.item_id', '=', 'items.id')
                            ->where('ss.store_id', '=', $dto->storeId)
                            ->whereNull('ss.deleted_at');
                    })
                    ->orderBy('items.name')
                    ->orderBy('items.id')
                    ->get(['items.id', 'items.cost_price', 'ss.quantity as store_quantity']);

                if ($rows->isEmpty()) {
                    throw BusinessRuleException::make('stock_counts.no_items');
                }

                $count = StockCount::create([
                    'count_number' => $this->documentNumbers->nextDated('CNT', 4, StockCount::class, 'count_number'),
                    'store_id' => $dto->storeId,
                    'open_store_id' => $dto->storeId,
                    'category_id' => $dto->categoryId,
                    'status' => StockCountStatus::Draft,
                    'is_blind' => $dto->isBlind,
                    'notes' => $dto->notes,
                    'created_by' => Auth::id() ?? $dto->userId,
                ]);

                $now = now();
                foreach ($rows->chunk(self::INSERT_CHUNK) as $chunk) {
                    DB::table('stock_count_lines')->insert($chunk->map(fn ($row): array => [
                        'stock_count_id' => $count->id,
                        'item_id' => (int) $row->id,
                        'system_qty' => bcadd((string) ($row->getAttribute('store_quantity') ?? '0'), '0', 3),
                        'unit_cost' => bcadd((string) ($row->cost_price ?? '0'), '0', 3),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->values()->all());
                }

                $this->activityLog->log(
                    module: 'inventory',
                    action: 'stock_count_started',
                    description: (string) __('stock_counts.log_started', ['number' => $count->count_number, 'lines' => $rows->count()]),
                    subject: $count,
                    properties: ['lines' => $rows->count(), 'category_id' => $dto->categoryId, 'is_blind' => $dto->isBlind],
                    storeId: $dto->storeId,
                );

                return $count;
            });
        } catch (UniqueConstraintViolationException) {
            throw BusinessRuleException::make('stock_counts.already_open');
        }
    }
}
