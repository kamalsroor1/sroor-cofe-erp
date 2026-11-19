<?php

declare(strict_types=1);

namespace App\Actions\StockCounts;

use App\Filters\Invoices\StoreFilter;
use App\Filters\StockCounts\SearchFilter;
use App\Filters\StockCounts\StatusFilter;
use App\Models\StockCount;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

/**
 * SHOP-02: paginated stocktake sessions of the active store (or every readable store for `all`).
 */
final class ListStockCountsAction
{
    /**
     * @return LengthAwarePaginator<int, StockCount>
     */
    public function execute(int $perPage): LengthAwarePaginator
    {
        /** @var Builder<StockCount> $query */
        $query = app(Pipeline::class)
            ->send(StockCount::query())
            ->through([StoreFilter::class, StatusFilter::class, SearchFilter::class])
            ->thenReturn();

        return $query
            ->with(['store', 'category', 'creator', 'poster'])
            ->withCount([
                'lines',
                'lines as counted_lines_count' => fn (Builder $q) => $q->whereNotNull('counted_qty'),
            ])
            ->latest('id')
            ->paginate(max(1, min(100, $perPage)));
    }
}
