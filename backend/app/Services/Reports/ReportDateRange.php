<?php

declare(strict_types=1);

namespace App\Services\Reports;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;

/**
 * PERF-01: the one date-window rule of the reports, the same as SalesReturnAggregates uses for
 * returns.return_date: column >= from AND column < to + 1 day (half-open), on the column itself.
 *
 * It equals whereDate(col, '>=', from) + whereDate(col, '<=', to) for a DATE column (MySQL) and
 * for the 'Y-m-d H:i:s' strings sqlite stores, but unlike DATE(col) it lets the database use
 * the date indexes. Either bound may be omitted (null / '').
 */
final class ReportDateRange
{
    /**
     * @param  Builder|EloquentBuilder<covariant Model>  $query
     */
    public static function apply(Builder|EloquentBuilder $query, string $column, ?string $fromDate, ?string $toDate): void
    {
        if ($fromDate !== null && $fromDate !== '') {
            $query->where($column, '>=', CarbonImmutable::parse($fromDate)->toDateString());
        }
        if ($toDate !== null && $toDate !== '') {
            $query->where($column, '<', CarbonImmutable::parse($toDate)->addDay()->toDateString());
        }
    }

    /**
     * Only an upper bound strictly before $date (an opening balance "before the period").
     *
     * @param  Builder|EloquentBuilder<covariant Model>  $query
     */
    public static function before(Builder|EloquentBuilder $query, string $column, string $date): void
    {
        $query->where($column, '<', CarbonImmutable::parse($date)->toDateString());
    }
}
