<?php

namespace App\Filters\Tenants;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchFilter
{
    public function __construct(
        protected Request $request
    ) {}

    public function handle(Builder $query, Closure $next)
    {
        $search = $this->request->query('search') ?? $this->request->input('search');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        return $next($query);
    }
}
