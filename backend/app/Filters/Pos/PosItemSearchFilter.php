<?php

declare(strict_types=1);

namespace App\Filters\Pos;

use App\DTOs\POS\ListPosItemsDTO;
use App\Models\Item;
use App\Support\Database\LikePattern;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * POS-04: code matches exactly or by prefix, the name by contains. Bound parameters only,
 * LIKE wildcards in the input are escaped.
 */
final class PosItemSearchFilter
{
    public function __construct(private readonly ListPosItemsDTO $dto) {}

    /**
     * @param  Builder<Item>  $query
     */
    public function handle(Builder $query, Closure $next): mixed
    {
        $search = $this->dto->search;

        if ($search !== null) {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('items.code', $search);
                LikePattern::orWherePrefix($q, 'items.code', $search);
                LikePattern::orWhereContains($q, 'items.name', $search);
            });
        }

        return $next($query);
    }
}
