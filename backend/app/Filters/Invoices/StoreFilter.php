<?php

declare(strict_types=1);

namespace App\Filters\Invoices;

use App\Support\ActiveStore;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * STOR-2: the active store (X-Store-Id); a query store_id is an access-checked branch filter (403).
 * Null (every store) only through a verified `all`.
 */
final class StoreFilter
{
    public function __construct(
        private readonly Request $request,
        private readonly ActiveStore $activeStore,
    ) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $storeId = $this->activeStore->readFilter($this->request);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        return $next($query);
    }
}
