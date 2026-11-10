<?php

declare(strict_types=1);

namespace App\Filters\Invoices;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class StatusFilter
{
    public function __construct(private readonly Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $status = (string) $this->request->input('status', 'all');

        if ($status !== 'all' && $status !== '') {
            $query->where('status', $status);
        }

        return $next($query);
    }
}
