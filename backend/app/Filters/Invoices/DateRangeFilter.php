<?php

declare(strict_types=1);

namespace App\Filters\Invoices;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class DateRangeFilter
{
    public function __construct(private readonly Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $fromDate = $this->request->input('from_date') ?: $this->request->input('from');
        $toDate = $this->request->input('to_date') ?: $this->request->input('to');

        if ($fromDate) {
            $query->whereDate('invoice_date', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('invoice_date', '<=', $toDate);
        }

        return $next($query);
    }
}
