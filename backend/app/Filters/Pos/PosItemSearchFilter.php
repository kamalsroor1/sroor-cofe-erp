<?php

declare(strict_types=1);

namespace App\Filters\Pos;

use App\DTOs\POS\ListPosItemsDTO;
use App\Models\Item;
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
            // '!' is the LIKE escape char: portable (MySQL and SQLite), unlike backslash.
            $escaped = preg_replace('/[!%_]/', '!$0', $search) ?? $search;
            $query->where(function (Builder $q) use ($search, $escaped): void {
                $q->where('items.code', $search)
                    ->orWhereRaw("items.code LIKE ? ESCAPE '!'", [$escaped.'%'])
                    ->orWhereRaw("items.name LIKE ? ESCAPE '!'", ['%'.$escaped.'%']);
            });
        }

        return $next($query);
    }
}
