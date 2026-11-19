<?php

declare(strict_types=1);

namespace App\Filters\Treasury;

use App\DTOs\Treasury\TreasuryTransferFilterDTO;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/** SHOP-06: transfers whose business date falls in [from_date, to_date]. */
final class TransferDateRangeFilter
{
    public function __construct(private readonly TreasuryTransferFilterDTO $filters) {}

    public function handle(Builder $query, Closure $next): mixed
    {
        if ($this->filters->fromDate !== null) {
            $query->whereDate('transfer_date', '>=', $this->filters->fromDate);
        }

        if ($this->filters->toDate !== null) {
            $query->whereDate('transfer_date', '<=', $this->filters->toDate);
        }

        return $next($query);
    }
}
