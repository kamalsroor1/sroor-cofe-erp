<?php

declare(strict_types=1);

namespace App\Filters\Treasury;

use App\DTOs\Treasury\TreasuryTransferFilterDTO;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/** SHOP-06: transfers out of or into one treasury account. */
final class TransferMethodFilter
{
    public function __construct(private readonly TreasuryTransferFilterDTO $filters) {}

    public function handle(Builder $query, Closure $next): mixed
    {
        $method = $this->filters->method;

        if ($method !== null) {
            $query->where(fn (Builder $q) => $q->where('from_method', $method)->orWhere('to_method', $method));
        }

        return $next($query);
    }
}
