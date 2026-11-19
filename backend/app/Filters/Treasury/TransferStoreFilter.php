<?php

declare(strict_types=1);

namespace App\Filters\Treasury;

use App\DTOs\Treasury\TreasuryTransferFilterDTO;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/** SHOP-06: one branch's transfers (null = a verified `all`). */
final class TransferStoreFilter
{
    public function __construct(private readonly TreasuryTransferFilterDTO $filters) {}

    public function handle(Builder $query, Closure $next): mixed
    {
        if ($this->filters->storeId !== null) {
            $query->where('store_id', $this->filters->storeId);
        }

        return $next($query);
    }
}
