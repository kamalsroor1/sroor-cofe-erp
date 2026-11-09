<?php

declare(strict_types=1);

namespace App\Filters\Invoices;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class IdsFilter
{
    public function __construct(private readonly Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $ids = $this->request->input('ids');

        if (is_array($ids) && $ids !== []) {
            $query->whereIn('id', array_map('intval', $ids));
        }

        return $next($query);
    }
}
