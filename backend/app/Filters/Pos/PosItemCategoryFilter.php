<?php

declare(strict_types=1);

namespace App\Filters\Pos;

use App\DTOs\POS\ListPosItemsDTO;
use App\Models\Item;
use Closure;
use Illuminate\Database\Eloquent\Builder;

final class PosItemCategoryFilter
{
    public function __construct(private readonly ListPosItemsDTO $dto) {}

    /**
     * @param  Builder<Item>  $query
     */
    public function handle(Builder $query, Closure $next): mixed
    {
        if ($this->dto->categoryId !== null) {
            $query->where('items.category_id', $this->dto->categoryId);
        }

        return $next($query);
    }
}
