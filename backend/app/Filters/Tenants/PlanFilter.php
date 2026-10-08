<?php

namespace App\Filters\Tenants;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class PlanFilter
{
    public function __construct(
        protected Request $request
    ) {}

    public function handle(Builder $query, Closure $next)
    {
        $planId = $this->request->query('plan_id') ?? $this->request->input('plan_id');

        if ($planId && $planId !== 'all') {
            $query->where('plan_id', $planId);
        }

        return $next($query);
    }
}
