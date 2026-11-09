<?php

declare(strict_types=1);

namespace App\Actions\POS;

use App\DTOs\POS\ListPosItemsDTO;
use App\Filters\Pos\PosItemCategoryFilter;
use App\Filters\Pos\PosItemSearchFilter;
use App\Filters\Pos\PosItemSortFilter;
use App\Filters\Pos\PosItemStockFilter;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\DB;

/**
 * POS-04/05: lean, paged item list for the POS grid. One select with a store_stocks join
 * (stock of the ACTIVE store) and a categories join (name/colour): no per-item queries.
 * Counters come from one grouped query that honours every filter except the category.
 */
final class GetPosItemsAction
{
    /**
     * @return array{paginator: LengthAwarePaginator<int, Item>, counts: array{total: int, categories: list<array{category_id: int|null, count: int}>}}
     */
    public function execute(ListPosItemsDTO $dto): array
    {
        $paginator = $this->filtered($dto, withCategory: true, withSort: true)
            ->select([
                'items.*',
                DB::raw(PosItemStockFilter::STOCK_EXPRESSION.' as calculated_stock'),
                'categories.name as category_name',
                'categories.color as category_color',
                'categories.color_light as category_color_light',
            ])
            ->paginate($dto->perPage, ['*'], 'page', $dto->page);

        return ['paginator' => $paginator, 'counts' => $this->counts($dto)];
    }

    /**
     * @return array{total: int, categories: list<array{category_id: int|null, count: int}>}
     */
    private function counts(ListPosItemsDTO $dto): array
    {
        $rows = $this->filtered($dto, withCategory: false, withSort: false)
            ->reorder()
            ->select('items.category_id', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('items.category_id')
            ->get();

        $total = 0;
        $categories = [];
        foreach ($rows as $row) {
            $count = (int) $row->getAttribute('aggregate');
            $total += $count;
            $categories[] = [
                'category_id' => $row->category_id !== null ? (int) $row->category_id : null,
                'count' => $count,
            ];
        }

        return ['total' => $total, 'categories' => $categories];
    }

    /**
     * @return Builder<Item>
     */
    private function filtered(ListPosItemsDTO $dto, bool $withCategory, bool $withSort): Builder
    {
        $storeId = $dto->storeId;

        $query = Item::query()
            ->where('items.is_active', true)
            ->leftJoin('store_stocks', function ($join) use ($storeId): void {
                $join->on('store_stocks.item_id', '=', 'items.id')
                    ->where('store_stocks.store_id', '=', $storeId)
                    ->whereNull('store_stocks.deleted_at');
            });

        if ($withSort) {
            $query->leftJoin('categories', function ($join): void {
                $join->on('categories.id', '=', 'items.category_id')
                    ->whereNull('categories.deleted_at');
            });
        }

        $filters = [new PosItemSearchFilter($dto), new PosItemStockFilter($dto)];
        if ($withCategory) {
            $filters[] = new PosItemCategoryFilter($dto);
        }
        if ($withSort) {
            $filters[] = new PosItemSortFilter($dto);
        }

        /** @var Builder<Item> */
        return app(Pipeline::class)->send($query)->through($filters)->thenReturn();
    }
}
