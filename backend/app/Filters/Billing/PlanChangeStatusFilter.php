<?php

declare(strict_types=1);

namespace App\Filters\Billing;

use App\Enums\Billing\PlanChangeRequestStatus;
use App\Models\PlanChangeRequest;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * `?status=pending|invoiced|rejected|cancelled` on the plan-change queue. Unknown values are
 * ignored (the FormRequest already rejects them with 422).
 */
final class PlanChangeStatusFilter
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  Builder<PlanChangeRequest>  $query
     * @return mixed
     */
    public function handle(Builder $query, Closure $next)
    {
        $status = PlanChangeRequestStatus::tryFrom((string) $this->request->query('status'));

        if ($status !== null) {
            $query->where('status', $status->value);
        }

        return $next($query);
    }
}
