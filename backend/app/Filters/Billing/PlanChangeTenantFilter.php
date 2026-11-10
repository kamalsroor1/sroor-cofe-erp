<?php

declare(strict_types=1);

namespace App\Filters\Billing;

use App\Models\PlanChangeRequest;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * `?tenant_id=<id>` on the plan-change queue (bound parameter, no raw SQL).
 */
final class PlanChangeTenantFilter
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  Builder<PlanChangeRequest>  $query
     * @return mixed
     */
    public function handle(Builder $query, Closure $next)
    {
        $tenantId = $this->request->query('tenant_id');

        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        return $next($query);
    }
}
