<?php

declare(strict_types=1);

namespace App\Filters\StockCounts;

use App\Support\Database\LikePattern;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * SHOP-02: free-text search on the session number and notes (bound parameters only).
 */
final class SearchFilter
{
    public function __construct(private readonly Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $search = trim((string) $this->request->input('search', ''));

        if ($search !== '') {
            LikePattern::whereContainsAny($query, ['count_number', 'notes'], $search);
        }

        return $next($query);
    }
}
