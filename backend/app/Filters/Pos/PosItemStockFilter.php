<?php

declare(strict_types=1);

namespace App\Filters\Pos;

use App\DTOs\POS\ListPosItemsDTO;
use App\Models\Item;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * POS-04: only_in_stock (store stock, falling back to items.current_stock like /pos/bootstrap)
 * and the is_weighted flag. Expects the store_stocks left join of GetPosItemsAction.
 */
final class PosItemStockFilter
{
    public const STOCK_EXPRESSION = 'COALESCE(store_stocks.quantity, items.current_stock)';

    public function __construct(private readonly ListPosItemsDTO $dto) {}

    /**
     * @param  Builder<Item>  $query
     */
    public function handle(Builder $query, Closure $next): mixed
    {
        if ($this->dto->onlyInStock) {
            $query->whereRaw(self::STOCK_EXPRESSION.' > 0');
        }

        if ($this->dto->onlyLowStock) {
            // Same rule the POS tab always used: above zero, at or under the item's minimum (5 when none is set).
            $query->whereRaw(self::STOCK_EXPRESSION.' > 0')
                ->whereRaw(self::STOCK_EXPRESSION.' <= CASE WHEN items.min_stock_level > 0 THEN items.min_stock_level ELSE 5 END');
        }

        if ($this->dto->isWeighted !== null) {
            $query->where('items.is_weighted', $this->dto->isWeighted);
        }

        return $next($query);
    }
}
