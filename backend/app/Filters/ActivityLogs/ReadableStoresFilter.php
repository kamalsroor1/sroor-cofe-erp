<?php

declare(strict_types=1);

namespace App\Filters\ActivityLogs;

use App\DTOs\Logs\ActivityLogStoreScope;
use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * LOG-01: limits an activity-log query to the branches of ActivityLogStoreScope. A restricted
 * reader never gets another branch's rows nor the store-less (shop-wide) ones; an explicit,
 * access-checked `store_id` narrows to that one branch.
 */
final class ReadableStoresFilter
{
    public function __construct(
        private readonly ActivityLogStoreScope $scope,
    ) {}

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public function apply(Builder $query): Builder
    {
        if ($this->scope->storeId !== null) {
            $query->where('store_id', $this->scope->storeId);
        }

        if (! $this->scope->seesEveryStore()) {
            // whereIn() with an empty list matches nothing: a user assigned to no branch sees no log.
            $query->whereIn('store_id', $this->scope->readableStoreIds ?? []);
        }

        return $query;
    }

    public function handle(Builder $query, Closure $next): mixed
    {
        return $next($this->apply($query));
    }
}
