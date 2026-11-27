<?php

namespace App\Filters\Tenants;

use App\Support\Database\LikePattern;
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
        $search = trim((string) ($this->request->query('search') ?? $this->request->input('search') ?? ''));

        if ($search !== '') {
            LikePattern::whereContainsAny($query, ['name', 'slug', 'email', 'phone'], $search);
        }

        return $next($query);
    }
}
