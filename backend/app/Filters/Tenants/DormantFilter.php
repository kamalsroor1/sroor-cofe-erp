<?php

declare(strict_types=1);

namespace App\Filters\Tenants;

use App\Models\Tenant;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * FREE-15: `?dormant_days=60` keeps tenants whose last activity (else creation date) is at least
 * that many days old. The column names are constants and the cutoff is a bound parameter.
 */
final class DormantFilter
{
    private const MAX_DAYS = 3650;

    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  Builder<Tenant>  $query
     */
    public function handle(Builder $query, Closure $next): mixed
    {
        $days = $this->request->query('dormant_days');

        if (is_numeric($days) && (int) $days > 0) {
            $cutoff = now()->subDays(min((int) $days, self::MAX_DAYS))->toDateTimeString();
            $query->whereRaw('COALESCE(tenants.last_activity_at, tenants.created_at) <= ?', [$cutoff]);
        }

        return $next($query);
    }
}
