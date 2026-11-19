<?php

declare(strict_types=1);

namespace App\Filters\StockCounts;

use App\Enums\StockCountStatus;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * SHOP-02: `status` = one StockCountStatus value, or `open` (draft + counting). Anything else: no filter.
 */
final class StatusFilter
{
    public function __construct(private readonly Request $request) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $status = (string) $this->request->input('status', '');

        if ($status === 'open') {
            $query->whereIn('status', [StockCountStatus::Draft->value, StockCountStatus::Counting->value]);
        } elseif (in_array($status, StockCountStatus::values(), true)) {
            $query->where('status', $status);
        }

        return $next($query);
    }
}
