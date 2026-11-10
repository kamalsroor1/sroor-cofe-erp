<?php

declare(strict_types=1);

namespace App\Filters\Signup;

use App\Models\SignupRequest;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * PRD-05: `?search=` over business name, contact name and phone (bound LIKE, no raw SQL).
 */
final class SearchFilter
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * @param  Builder<SignupRequest>  $query
     * @param  Closure(Builder<SignupRequest>): Builder<SignupRequest>  $next
     * @return Builder<SignupRequest>
     */
    public function handle(Builder $query, Closure $next): Builder
    {
        $search = $this->request->query('search');

        if (is_string($search) && trim($search) !== '') {
            // Wildcards typed by the operator only widen their own search; the value is always bound.
            $like = '%'.mb_substr(trim($search), 0, 100).'%';

            $query->where(function (Builder $q) use ($like): void {
                $q->where('business_name', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        return $next($query);
    }
}
