<?php

declare(strict_types=1);

namespace App\Filters\Pos;

use App\DTOs\POS\ListPosItemsDTO;
use App\Models\Item;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * POS-04: whitelisted sort (the DTO already restricts it to ListPosItemsDTO::SORTS) with a stable id tiebreaker.
 */
final class PosItemSortFilter
{
    public function __construct(private readonly ListPosItemsDTO $dto) {}

    /**
     * @param  Builder<Item>  $query
     */
    public function handle(Builder $query, Closure $next): mixed
    {
        $column = match ($this->dto->sort) {
            'code' => 'items.code',
            'pos_sales_count' => 'items.pos_sales_count',
            default => 'items.name',
        };

        $query->orderBy($column, $this->dto->direction === 'desc' ? 'desc' : 'asc')
            ->orderBy('items.id');

        return $next($query);
    }
}
