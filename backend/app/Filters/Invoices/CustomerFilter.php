<?php

declare(strict_types=1);

namespace App\Filters\Invoices;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class CustomerFilter
{
    public function __construct(private readonly Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $customerId = $this->request->input('customer_id');

        if ($customerId && $customerId !== 'all') {
            $query->where('customer_id', (int) $customerId);
        }

        return $next($query);
    }
}
